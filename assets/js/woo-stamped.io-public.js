function myInit() {
	var initOpts = {
		apiKey: Woo_stamped.pub_key,
		storeUrl: Woo_stamped.url
	};

	if(Woo_stamped.store_hash) {
		initOpts.sId = Woo_stamped.store_hash;
	}
	StampedFn.init(initOpts);
}
//window.stamped_ajax_url = window.stamped_ajax_secure_url = "http://localhost:4999/api";
(function e() { var e = document.createElement( "script" ); e.type = "text/javascript", e.async = true, e.onload = myInit, e.src = "//cdn1.stamped.io/files/widget.min.js"; var t = document.getElementsByTagName( "script" )[0]; t.parentNode.insertBefore( e, t ) })();
