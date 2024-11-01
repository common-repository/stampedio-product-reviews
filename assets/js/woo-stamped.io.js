var Woo_stamped = {
	hooks: {action: {}, filter: {}},
	addAction: function (action, callable, priority, tag) {
		Woo_stamped.addHook( 'action', action, callable, priority, tag );
	},
	addFilter: function (action, callable, priority, tag) {
		Woo_stamped.addHook( 'filter', action, callable, priority, tag );
	},
	doAction: function (action) {
		Woo_stamped.doHook( 'action', action, arguments );
	},
	applyFilters: function (action) {
		return Woo_stamped.doHook( 'filter', action, arguments );
	},
	removeAction: function (action, tag) {
		Woo_stamped.removeHook( 'action', action, tag );
	},
	removeFilter: function (action, priority, tag) {
		Woo_stamped.removeHook( 'filter', action, priority, tag );
	},
	addHook: function (hookType, action, callable, priority, tag) {
		if (undefined == Woo_stamped.hooks[hookType][action]) {
			Woo_stamped.hooks[hookType][action] = [];
		}
		var hooks = Woo_stamped.hooks[hookType][action];
		if (undefined == tag) {
			tag = action + '_' + hooks.length;
		}
		Woo_stamped.hooks[hookType][action].push( {tag: tag, callable: callable, priority: priority} );
	},
	doHook: function (hookType, action, args) {

		// splice args from object into array and remove first index which is the hook name
		args = Array.prototype.slice.call( args, 1 );

		if (undefined != Woo_stamped.hooks[hookType][action]) {
			var hooks = Woo_stamped.hooks[hookType][action], hook;
			// sort by priority
			hooks.sort(
				function (a, b) {
					return a["priority"] - b["priority"]
				}
			);
			for (var i = 0; i < hooks.length; i++) {
				hook = hooks[i].callable;
				if (typeof hook != 'function') {
					hook = window[hook];
				}
				if ('action' == hookType) {
					hook.apply( null, args );
				} else {
					args[0] = hook.apply( null, args );
				}
			}
		}
		if ('filter' == hookType) {
			return args[0];
		}
	},
	removeHook: function (hookType, action, priority, tag) {
		if (undefined != Woo_stamped.hooks[hookType][action]) {
			var hooks = Woo_stamped.hooks[hookType][action];
			for (var i = hooks.length - 1; i >= 0; i--) {
				if ((undefined == tag || tag == hooks[i].tag) && (undefined == priority || priority == hooks[i].priority)) {
					hooks.splice( i, 1 );
				}
			}
		}
	}
};
function send_ajax(el, action, cdata) {
	var data = {};
	if (typeof cdata != "undefined" && Object.keys( cdata ).length > 0) {
		data = cdata;
	}
	el.find( ".spinner" ).css( {"visibility": "visible"} );
	if (el.length > 0) {
		var n, v;

		el.find( "input" ).each(
			function () {
				var n = jQuery( this ).attr( "name" );
				var t = jQuery( this ).attr( "type" );

				if (t == "checkbox" || t == "radio") {
					var checked = jQuery( this ).is( ":checked" );
					console.log( checked );
					if (checked == true) {
						data[n] = true;
					}

				} else {
					if (t != "button") {
						v       = jQuery( this ).val();
						data[n] = v;
					}
				}
			}
		);
		el.find( "textarea" ).each(
			function () {
				n       = jQuery( this ).attr( "name" );
				v       = jQuery( this ).val();
				data[n] = v;
			}
		);
		el.find( "select" ).each(
			function () {
				n       = jQuery( this ).attr( "name" );
				v       = jQuery( this ).val();
				data[n] = v;
			}
		);
		el.find( "button" ).each(
			function () {
				n       = jQuery( this ).attr( "name" );
				v       = jQuery( this ).val();
				data[n] = v;
			}
		);
	}
	data["action"] = action;
	data           = Woo_stamped.applyFilters( "send_ajax_before_ajax", data );
	var url        = STMPD_Admin.ajax_url;
	if (Object.keys( data ).length > 0) {
		jQuery.ajax(
			{
				url: url,
				method: "POST",
				headers: {'X-Stmpd-Nonce': STMPD_Admin.nonces[action.substring("Woo_stamped_".length)]},
				data: data,
				success: function (resp, status, jqXHR) {
					Woo_stamped.doAction( "send_ajax_after_success", resp, status, jqXHR, action, el );
				},
				error: function (jqXHR, status, errorThrown) {
					Woo_stamped.doAction( "send_ajax_after_error", jqXHR, status, errorThrown, action, el );
				},
				complete: function (jqXHR, status) {
					Woo_stamped.doAction( "send_ajax_after_complete", jqXHR, status, action, el );
				}
			}
		).done(
			function () {
				jQuery( this ).addClass( "done" );
			}
		);
	}

}



function Woo_stamped_bulk_reviews(resp, el, status) {
	console.log( status );
	el.find( ".spinner" ).css( {"visibility": "hidden"} );
	if (status.status >= 200 && status.status < 400) {
		if (typeof resp != "undefined" && resp != "") {
			resp = jQuery.parseJSON( resp );
			if (resp.status == "success") {
				console.log( resp.laststep );
				if (typeof resp.laststep != "undefined") {
					el.find( ".bulk_import_fields" ).html( resp.response );
					el.find( ".woo_stamped_counter" ).val( 1 );
					return false;
				} else {
					el.find( ".bulk_import_fields" ).append( resp.response );
					el.find( ".woo_stamped_counter" ).val( resp.paged );
					el.find( "#Woo_stamped_bulk_review_button" ).removeAttr( "disabled" );
				}
				el.find( "#Woo_stamped_bulk_review_button" ).trigger( "click" );
			}
		}
	} else {
		var e         = document.createElement( 'span' )
		e.className   = 'woo_stamped_error';
		e.textContent = status.status + " " + status.statusText;
		el.find( ".bulk_import_fields" ).prepend( e );
		setTimeout(
			function () {
				el.find( ".bulk_import_fields" ).children( ".woo_stamped_error" ).remove();
			},
			5000
		);
	}
	el.find( "#Woo_stamped_bulk_review_button" ).removeAttr( "disabled" );
}

function Woo_stamped_clear_reviews_cache(resp, el, status) {
	el.find( ".spinner" ).css( { "visibility": "hidden" } );
	if (status.status >= 200 && status.status < 400) {
		if (typeof resp != "undefined" && resp != "") {
			resp = jQuery.parseJSON( resp );
			if (resp.status == "success") {
				console.log( el.find( ".response" ) );
				el.find( ".response" ).html( " " + resp.text );
			}
		}
	}

	el.find( "#Woo_stamped_clear_reviews_cache" ).removeAttr( "disabled" );
}

function Woo_stamped_bulk_export_review(resp, el, status) {
	console.log( status );
	el.find( ".spinner" ).css( {"visibility": "hidden"} );
	if (status.status >= 200 && status.status < 400) {
		if (typeof resp != "undefined" && resp != "") {
			resp = jQuery.parseJSON( resp );
			if (resp.status == "success") {
				console.log( resp.laststep );
				if (typeof resp.laststep != "undefined") {
					el.find( ".bulk_import_fields" ).html( resp.response );
					el.find( ".woo_stamped_counter" ).val( 1 );
					return false;
				} else {
					el.find( ".bulk_import_fields" ).append( resp.response );
					el.find( ".woo_stamped_counter" ).val( resp.paged );
					el.find( "#Woo_stamped_bulk_export_review_button" ).removeAttr( "disabled" );

				}
				el.find( "#Woo_stamped_bulk_export_review_button" ).trigger( "click", {"h": 'b'} );
			}
		}
	} else {
		el.find( ".bulk_import_fields" ).prepend( "<span class='woo_stamped_error'> " + status.status + " " + status.statusText + " </span>" );
		setTimeout(
			function () {
				el.find( ".bulk_import_fields" ).children( ".woo_stamped_error" ).remove();
			},
			5000
		);
	}
	el.find( "#Woo_stamped_bulk_export_review_button" ).removeAttr( "disabled" );

}


Woo_stamped.addAction(
	"send_ajax_after_success",
	function (resp, status, jqXHR, action, el) {
		if (action == "Woo_stamped_bulk_reviews") {
			Woo_stamped_bulk_reviews( resp, el, jqXHR );
		}

		if (action == "Woo_stamped_clear_reviews_cache") {
			Woo_stamped_clear_reviews_cache( resp, el, jqXHR );
		}

		if (action == "Woo_stamped_bulk_export_review") {
			Woo_stamped_bulk_export_review( resp, el, jqXHR );
		}

	}
);

Woo_stamped.addAction(
	"send_ajax_after_error",
	function (jqXHR, status, errorThrown, action, el) {
		if (action == "Woo_stamped_bulk_reviews") {
			Woo_stamped_bulk_reviews( "", el, jqXHR );
		}

		if (action == "Woo_stamped_clear_reviews_cache") {
			Woo_stamped_clear_reviews_cache( "", el, jqXHR );
		}

		if (action == "Woo_stamped_bulk_export_review") {
			Woo_stamped_bulk_export_review( "", el, jqXHR );
		}
	}
);

jQuery( document ).ready(
	function () {
		jQuery( "body" ).on(
			"click",
			"#Woo_stamped_bulk_review_button",
			function (e) {
				e.preventDefault();
				var elparent = jQuery( this ).parents( "#mainform" );
				elparent.find( ".response" ).html( "" );
				jQuery( this ).attr( "disabled", "disabled" );
				send_ajax( elparent, "Woo_stamped_bulk_reviews" );
			}
		);

		jQuery( "body" ).on(
			"click",
			"#Woo_stamped_clear_reviews_cache",
			function (e) {
				e.preventDefault();
				var elparent = jQuery( this ).parents( "#mainform" );
				elparent.find( ".response" ).html( "" );
				jQuery( this ).attr( "disabled", "disabled" );
				send_ajax( elparent, "Woo_stamped_clear_reviews_cache" );
			}
		);

		jQuery( "body" ).on(
			"click",
			"#Woo_stamped_bulk_export_review_button",
			function (e, v) {
				e.preventDefault();
				console.log( v );
				var elparent = jQuery( this ).parents( "#mainform" );
				elparent.find( ".response" ).html( "" );
				jQuery( this ).attr( "disabled", "disabled" );
				send_ajax( elparent, "Woo_stamped_bulk_export_review" );
			}
		);

	}
);
