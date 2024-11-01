<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class STMPD_View {

	private static $stampedDBData;

	public function __construct() {

		$this->display_settings();
		self::$stampedDBData = null;
	}

	public static function store_data( $product_id, $product_title ) {
		$show_stamped_rich_snippet = STMPD_API::get_enable_rich_snippet();
		$enabled_stamped_cache     = STMPD_API::get_enable_reviews_cache();

		if ( $show_stamped_rich_snippet == 'yes' || $enabled_stamped_cache == 'yes' ) {
			self::$stampedDBData = get_post_meta( $product_id, 'stamped_io_product_reviews_new', true );
			$ttl                 = (int) get_post_meta( $product_id, 'stamped_io_product_ttl', true );

			if ( self::$stampedDBData == null || self::$stampedDBData == '' || $ttl < time() ) {

				$outcome = (array) STMPD_API::get_request( $product_id, $product_title, array(), 'GET' );
				if ( isset( $outcome['count'] ) ) {
					if ( ! isset( $outcome['ttl'] ) ) {
						$ttl = (int) 86400 + time();
					} else {
						$ttl = (int) $outcome['ttl'] + time();
					}

					if ( $enabled_stamped_cache != 'yes' ) {
						$outcome['widget'] = '';
					}

					update_post_meta( $product_id, 'stamped_io_product_reviews_new', $outcome );
					update_post_meta( $product_id, 'stamped_io_product_ttl', $ttl );
					self::$stampedDBData = $outcome;
				}
			}
		}
	}

	public function woocommerce_structured_data_product( $markup, $product ) {
		$product_id    = $product->get_id();
		$product_title = $product->get_title();

		self::store_data( $product_id, $product_title );

		if ( self::$stampedDBData && self::$stampedDBData['rating'] != '0' && self::$stampedDBData['rating'] != 0 ) {
			$markup['aggregateRating'] = array(
				'@type'        => 'AggregateRating',
				'ratingValue'  => self::$stampedDBData['rating'],
				'reviewCount'  => self::$stampedDBData['count'],
				'worstRating'  => 1,
				'bestRating'   => 5,
				'itemReviewed' => $product->get_title(),
			);
		}

		return $markup;
	}

	/**
	 * All Setting Regarding Display coded here
	 */
	public function display_settings() {
		$show_stamped_rich_snippet = STMPD_API::get_enable_rich_snippet();

		// Cheking Archive Options is enable or disabled
		$show_stamped_rating_on_archive = STMPD_API::Show_stamped_rating_on_archive();

		if ( $show_stamped_rating_on_archive == 'yes' ) {
			add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'review_badge' ), 6 );
		}

		$show_stamped_rating_on_product = STMPD_API::get_rating_enable_on_product();
		if ( $show_stamped_rating_on_product == 'yes' ) {
			add_action( 'woocommerce_single_product_summary', array( $this, 'review_badge_single_product' ), 9 );
		}

		$selected_area = STMPD_API::get_selected_area_of_stamped_area();
		if ( $selected_area == 'below' ) {
			add_action( 'woocommerce_after_single_product_summary', array( $this, 'review_box' ), 4 );
		}

		if ( $selected_area == 'inside' ) {
			add_filter( 'woocommerce_product_tabs', array( $this, 'add_widget_inside_tabs' ), 11, 1 );
		}

		if ( $show_stamped_rich_snippet == 'yes' ) {
			add_filter( 'woocommerce_structured_data_product', array( $this, 'woocommerce_structured_data_product' ), 10, 2 );
		}

		$disallow_native_rating = STMPD_API::disallow_native_rating();

		if ( $disallow_native_rating == 'yes' ) {
			remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
			add_filter( 'woocommerce_product_tabs', array( $this, 'remove_review_box_at_single_product' ), 12, 1 );
		}

		add_action( 'woocommerce_thankyou', array( $this, 'stamped_conversion_tracking' ), 0 );

		// Loyalty & Rewards
		$show_stamped_rewards = STMPD_API::get_enable_rewards();

		if ( $show_stamped_rewards == 'yes' ) {
			add_action( 'wp_footer', array( $this, 'rewards_launcher' ), 10, 2 );
		}
	}

	public function rewards_launcher() {
		$domainName  = STMPD_API::get_site_url();
		$public_key  = STMPD_API::get_public_keys();
		$private_key = STMPD_API::get_private_keys();

		$dataAttrs = array( 'data-key-public' => $public_key );

		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();

			$message = get_current_user_id() . $current_user->user_email;

			// to lowercase hexits
			$hmacVal = hash_hmac( 'sha256', $message, $private_key );

			$dataAttrs += array(
				'data-key-auth'              => $hmacVal,
				'data-customer-id'           => get_current_user_id(),
				'data-customer-email'        => $current_user->user_email,
				'data-customer-first-name'   => $current_user->first_name,
				'data-customer-last-name'    => $current_user->last_name,
				'data-customer-orders-count' => 0,
				'data-customer-tags'         => '',
				'data-customer-total-spent'  => 0,
			);
		}

		$htmlAttributes = self::escape_html_attrs( $dataAttrs );

		echo "<!-- Stamped.io Rewards Launcher -->\n";
		echo "<div id='stamped-rewards-init' class='stamped-rewards-init' $htmlAttributes ></div>";  //phpcs:ignore WordPress.Security.EscapeOutput
		echo "\n<!-- Stamped.io Rewards Launcher -->\n";
	}

	public function stamped_conversion_tracking( $order_id ) {
		$order = wc_get_order( $order_id );

		$domainName = STMPD_API::get_site_url();
		$public_key = STMPD_API::get_public_keys();

		if ( $order ) {
			$url = sprintf( '//stamped.io/conversion_tracking.gif?shopUrl=%s&apiKey=%s&orderId=%d&orderAmount=%d&orderCurrency=%s', $domainName, $public_key, $order_id, $order->get_total(), $order->get_currency() );
			echo "<!-- Stamped.io Conversion Tracking plugin -->\n";
			echo '<img src="' . esc_url( $url ) . '">';
			echo "\n<!-- Stamped.io Conversion Tracking plugin -->\n";
		}
	}

	/**
	 * Removing Review Tabs from single product when WC Native Setting is enabled
	 *
	 * @param type $tabs global tabs array
	 * @return type
	 */
	public function remove_review_box_at_single_product( $tabs ) {
		if ( is_array( $tabs ) && count( $tabs ) > 0 ) {
			foreach ( $tabs as $key => $tab ) {
				if ( $key == 'reviews' ) {
					unset( $tabs[ $key ] );
				}
			}
		}
		return $tabs;
	}

	public function add_widget_inside_tabs( $tabs ) {
		if ( is_array( $tabs ) && count( $tabs ) > 0 ) {
			$priority = 30;
			foreach ( $tabs as $key => $tab ) {
				if ( $key == 'reviews' ) {
					$priority = (int) $tab['priority'];
				}
			}
			$priority++;
			$tabs['stamped_reviews_widget'] = array(
				'title'    => __( 'Reviews', 'stampedio-product-reviews' ),
				'callback' => array( 'STMPD_View', 'review_box' ),
				'priority' => $priority,
			);
		}
		return $tabs;
	}

	/**
	 * Create and display Review Box  with Post Review form
	 * This is Public Static function call directly from any class Like Woo_stamped_public::Woo_stamped_review_box()
	 *
	 * @global type $product
	 */
	public static function review_box() {
		global $product;
		// $desc = strip_tags($product->post->post_excerpt ? $product->post->post_excerpt : $product->post->post_content);
		$product_id    = '';
		$product_title = '';
		$product_sku   = '';
		$img           = '';

		if ( isset( $product ) && is_object( $product ) ) {
			$img           = get_the_post_thumbnail_url( $product->get_id() );
			$product_id    = $product->get_id();
			$product_title = $product->get_title();
			$product_sku   = $product->get_sku();
		}

		$content = '';

		self::store_data( $product_id, $product_title );

		if ( self::$stampedDBData != null ) {
			if ( self::$stampedDBData && self::$stampedDBData['rating'] != '0' && self::$stampedDBData['rating'] != 0 && self::$stampedDBData['widget'] != '' ) {
				$stamped_widget  = self::$stampedDBData['widget'];
				$stamped_product = self::$stampedDBData['product'];
				wp_enqueue_style( 'widget_css', 'https://cdn1.stamped.io/files/widget.min.css' );
				$content = str_replace( '<div class="stamped-content"> </div>', '<div class="stamped-content">' . $stamped_widget . '</div>', $stamped_product );
			}
		}

		$dataAttrs = array(
			'data-product-id'      => $product_id,
			'data-product-sku'     => $product_sku,
			'data-name'            => $product_title,
			'data-url'             => esc_url( get_the_permalink() ),
			'data-image-url'       => $img,
			'data-widget-language' => '',
		);

		$attrs = self::escape_html_attrs( $dataAttrs );
		// Not escaping $content as it's actually the stamped widget that we've fetched (and cached) from the Stamped API.
		// This is trusted content.
		add_filter( 'safe_style_css', array( 'STMPD_View', 'add_safe_style' ) );
		echo "<div id=\"stamped-main-widget\" class=\"stamped stamped-main-widget\" $attrs>" . self::stamped_kses( $content ) . '</div>';  //phpcs:ignore WordPress.Security.EscapeOutput
		remove_filter( 'safe_style_css', array( 'STMPD_View', 'add_safe_style' ) );
	}

	/**
	 * Create and display Review Badge
	 * This is Public Static function call directly from any class Like Woo_stamped_public::Woo_stamped_review_badge()
	 *
	 * @global type $product
	 */
	public static function review_badge() {
		global $product;

		$product_id    = '';
		$product_title = '';
		$product_sku   = '';

		try {
			if ( isset( $product ) && is_object( $product ) ) {
				$product_id    = $product->get_id();
				$product_title = $product->get_title();
				$product_sku   = $product->get_sku();
			}
		} catch ( Exception $e ) {
		}

		$dataAttrs = array(
			'data-id'          => $product_id,
			'data-name'        => $product_title,
			'data-product-sku' => $product_sku,
			'data-url'         => esc_url( get_the_permalink() ),
		);

		$attrs = self::escape_html_attrs( $dataAttrs );

		echo "<span class=\"stamped-product-reviews-badge\" $attrs></span>";  //phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function review_badge_single_product() {
		global $product;

		$product_id    = '';
		$product_title = '';
		$product_sku   = '';

		if ( isset( $product ) && is_object( $product ) ) {
			$product_id    = $product->get_id();
			$product_title = $product->get_title();
			$product_sku   = $product->get_sku();
		}

		$dataAttrs = array(
			'data-id'          => $product_id,
			'data-name'        => $product_title,
			'data-product-sku' => $product_sku,
			'data-url'         => esc_url( get_the_permalink() ),
		);

		$attrs = self::escape_html_attrs( $dataAttrs );

		echo "<span class=\"stamped-product-reviews-badge stamped-main-badge\" $attrs></span>";  //phpcs:ignore WordPress.Security.EscapeOutput
		echo "<span class=\"stamped-product-reviews-badge stamped-main-badge\" data-type=\"qna\" style=\"display:none;\" $attrs></span>";  //phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function aggregate_rating( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		if ( $data['reviewsAverage'] == '0' || $data['reviewsAverage'] == 0 ) {
			return '';
		}

		ob_start();
		global $product; ?>
		<div itemprop="aggregateRating" itemscope="" itemtype = "http://schema.org/AggregateRating">
			<span itemprop = "itemReviewed"><?php echo esc_html( $product->get_title() ); ?></span>
			<?php esc_html( __( 'has a rating of' ) ); ?> <span itemprop = "ratingValue"><?php echo esc_html( $data['reviewsAverage'] ); ?></span> stars
			<?php esc_html( __( 'based on' ) ); ?> <span itemprop = "ratingCount"><?php echo esc_html( $data['reviewsCount'] ); ?></span> reviews.
		</div>
		<?php
		return ob_get_clean();
	}

	private static function stamped_kses( $content ) {
		return wp_kses( $content, self::get_widget_escape_config(), self::get_allowed_kses_protocols() );
	}

	private static function escape_html_attrs( array $attrs ) {
		return array_reduce(
			array_keys( $attrs ),
			function ( $acc, $key ) use ( $attrs ) {
				$val = $attrs[ $key ];
				return sprintf( '%s %s="%s"', $acc, $key, esc_attr( $val ) );
			},
			''
		);
	}

	private static function get_allowed_kses_protocols() {
		return array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'javascript', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn' );
	}

	private static function get_widget_escape_config() {
		$config    = array(
			'address'    => array(),
			'a'          => array(
				'href'     => true,
				'rel'      => true,
				'rev'      => true,
				'name'     => true,
				'target'   => true,
				'download' => array(
					'valueless' => 'y',
				),
			),
			'abbr'       => array(),
			'acronym'    => array(),
			'area'       => array(
				'alt'    => true,
				'coords' => true,
				'href'   => true,
				'nohref' => true,
				'shape'  => true,
				'target' => true,
			),
			'article'    => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'aside'      => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'audio'      => array(
				'autoplay' => true,
				'controls' => true,
				'loop'     => true,
				'muted'    => true,
				'preload'  => true,
				'src'      => true,
			),
			'b'          => array(),
			'bdo'        => array(
				'dir' => true,
			),
			'big'        => array(),
			'blockquote' => array(
				'cite'     => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'br'         => array(),
			'button'     => array(
				'disabled' => true,
				'name'     => true,
				'type'     => true,
				'value'    => true,
			),
			'caption'    => array(
				'align' => true,
			),
			'cite'       => array(
				'dir'  => true,
				'lang' => true,
			),
			'code'       => array(),
			'col'        => array(
				'align'   => true,
				'char'    => true,
				'charoff' => true,
				'span'    => true,
				'dir'     => true,
				'valign'  => true,
				'width'   => true,
			),
			'colgroup'   => array(
				'align'   => true,
				'char'    => true,
				'charoff' => true,
				'span'    => true,
				'valign'  => true,
				'width'   => true,
			),
			'del'        => array(
				'datetime' => true,
			),
			'dd'         => array(),
			'dfn'        => array(),
			'details'    => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'open'     => true,
				'xml:lang' => true,
			),
			'div'        => array(
				'class'    => true,
				'style'    => true,
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'dl'         => array(),
			'dt'         => array(),
			'em'         => array(),
			'fieldset'   => array(),
			'figure'     => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'figcaption' => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'font'       => array(
				'color' => true,
				'face'  => true,
				'size'  => true,
			),
			'form'       => array(
				'action'         => true,
				'accept'         => true,
				'accept-charset' => true,
				'enctype'        => true,
				'method'         => true,
				'name'           => true,
				'target'         => true,
			),
			'footer'     => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'h1'         => array(
				'align' => true,
			),
			'h2'         => array(
				'align' => true,
			),
			'h3'         => array(
				'align' => true,
			),
			'h4'         => array(
				'align' => true,
			),
			'h5'         => array(
				'align' => true,
			),
			'h6'         => array(
				'align' => true,
			),
			'header'     => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'hgroup'     => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'hr'         => array(
				'align'   => true,
				'noshade' => true,
				'size'    => true,
				'width'   => true,
			),
			'i'          => array(
				'class' => true,
				'style' => true,
			),
			'input'      => array(
				'type'  => true,
				'value' => true,
			),
			'img'        => array(
				'alt'      => true,
				'align'    => true,
				'border'   => true,
				'height'   => true,
				'hspace'   => true,
				'loading'  => true,
				'longdesc' => true,
				'vspace'   => true,
				'src'      => true,
				'usemap'   => true,
				'width'    => true,
			),
			'ins'        => array(
				'datetime' => true,
				'cite'     => true,
			),
			'kbd'        => array(),
			'label'      => array(
				'for' => true,
			),
			'legend'     => array(
				'align' => true,
			),
			'li'         => array(
				'align' => true,
				'value' => true,
			),
			'main'       => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'map'        => array(
				'name' => true,
			),
			'mark'       => array(),
			'menu'       => array(
				'type' => true,
			),
			'meta'       => array(
				'itemprop' => true,
				'content'  => true,
			),
			'nav'        => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'object'     => array(
				'data' => array(
					'required'       => true,
					'value_callback' => '_wp_kses_allow_pdf_objects',
				),
				'type' => array(
					'required' => true,
					'values'   => array( 'application/pdf' ),
				),
			),
			'option'     => array(
				'value'    => true,
				'selected' => true,
			),
			'p'          => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'pre'        => array(
				'width' => true,
			),
			'q'          => array(
				'cite' => true,
			),
			's'          => array(),
			'samp'       => array(),
			'script'     => array(
				'type' => true,
				'src'  => true,
			),
			'span'       => array(
				'class'    => true,
				'dir'      => true,
				'align'    => true,
				'lang'     => true,
				'rel'      => true,
				'xml:lang' => true,
			),
			'section'    => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'select'     => array(),
			'small'      => array(),
			'strike'     => array(),
			'strong'     => array(),
			'style'      => array(
				'type' => true,
			),
			'sub'        => array(),
			'summary'    => array(
				'align'    => true,
				'dir'      => true,
				'lang'     => true,
				'xml:lang' => true,
			),
			'sup'        => array(),
			'table'      => array(
				'align'       => true,
				'bgcolor'     => true,
				'border'      => true,
				'cellpadding' => true,
				'cellspacing' => true,
				'dir'         => true,
				'rules'       => true,
				'summary'     => true,
				'width'       => true,
			),
			'tbody'      => array(
				'align'   => true,
				'char'    => true,
				'charoff' => true,
				'valign'  => true,
			),
			'td'         => array(
				'abbr'    => true,
				'align'   => true,
				'axis'    => true,
				'bgcolor' => true,
				'char'    => true,
				'charoff' => true,
				'colspan' => true,
				'dir'     => true,
				'headers' => true,
				'height'  => true,
				'nowrap'  => true,
				'rowspan' => true,
				'scope'   => true,
				'valign'  => true,
				'width'   => true,
			),
			'textarea'   => array(
				'cols'     => true,
				'rows'     => true,
				'disabled' => true,
				'name'     => true,
				'readonly' => true,
				'value'    => true,
			),
			'tfoot'      => array(
				'align'   => true,
				'char'    => true,
				'charoff' => true,
				'valign'  => true,
			),
			'th'         => array(
				'abbr'    => true,
				'align'   => true,
				'axis'    => true,
				'bgcolor' => true,
				'char'    => true,
				'charoff' => true,
				'colspan' => true,
				'headers' => true,
				'height'  => true,
				'nowrap'  => true,
				'rowspan' => true,
				'scope'   => true,
				'valign'  => true,
				'width'   => true,
			),
			'thead'      => array(
				'align'   => true,
				'char'    => true,
				'charoff' => true,
				'valign'  => true,
			),
			'title'      => array(),
			'tr'         => array(
				'align'   => true,
				'bgcolor' => true,
				'char'    => true,
				'charoff' => true,
				'valign'  => true,
			),
			'track'      => array(
				'default' => true,
				'kind'    => true,
				'label'   => true,
				'src'     => true,
				'srclang' => true,
			),
			'tt'         => array(),
			'u'          => array(),
			'ul'         => array(
				'type' => true,
			),
			'ol'         => array(
				'start'    => true,
				'type'     => true,
				'reversed' => true,
			),
			'var'        => array(),
			'video'      => array(
				'autoplay'    => true,
				'controls'    => true,
				'height'      => true,
				'loop'        => true,
				'muted'       => true,
				'playsinline' => true,
				'poster'      => true,
				'preload'     => true,
				'src'         => true,
				'width'       => true,
			),
		);
		$config    = array_merge( $config, self::get_svg_kses() );
		$ariaAttrs = self::get_aria_attributes();
		foreach ( $config as $k => $v ) {
			$config[ $k ]['class']      = true;
			$config[ $k ]['style']      = true;
			$config[ $k ]['onerror']    = true;
			$config[ $k ]['onclick']    = true;
			$config[ $k ]['onkeyup']    = true;
			$config[ $k ]['onkeydown']  = true;
			$config[ $k ]['onkeypress'] = true;
			$config[ $k ]['onsubmit']   = true;
			$config[ $k ]['onchange']   = true;
			$config[ $k ]['class']      = true;
			$config[ $k ]['id']         = true;
			$config[ $k ]['name']       = true;
			$config[ $k ]['style']      = true;
			$config[ $k ]['title']      = true;
			$config[ $k ]['role']       = true;
			$config[ $k ]['data-*']     = true;
			$config[ $k ]['tabindex']   = true;

			$config[ $k ] = array_merge( $config[ $k ], $ariaAttrs );

		}
		return $config;
	}

	public static function add_safe_style( $props = array() ) {
		$props = array_merge( $props, array( 'display', 'overflow', 'position' ) );
		return $props;
	}

	private static function get_aria_attributes() {

		$global_attributes = array(
			'aria-atomic'      => true,
			'aria-busy'        => true,
			'aria-controls'    => true,
			'aria-describedby' => true,
			'aria-disabled'    => true,
			'aria-dropeffect'  => true,
			'aria-flowto'      => true,
			'aria-grabbed'     => true,
			'aria-haspopup'    => true,
			'aria-hidden'      => true,
			'aria-invalid'     => true,
			'aria-label'       => true,
			'aria-labelledby'  => true,
			'aria-live'        => true,
			'aria-owns'        => true,
			'aria-relevant'    => true,
		);

		$widget_attributes = array(
			'aria-autocomplete'    => true,
			'aria-checked'         => true,
			'aria-disabled'        => true,
			'aria-expanded'        => true,
			'aria-haspopup'        => true,
			'aria-hidden'          => true,
			'aria-invalid'         => true,
			'aria-label'           => true,
			'aria-level'           => true,
			'aria-multiline'       => true,
			'aria-multiselectable' => true,
			'aria-orientation'     => true,
			'aria-pressed'         => true,
			'aria-readonly'        => true,
			'aria-required'        => true,
			'aria-selected'        => true,
			'aria-sort'            => true,
			'aria-valuemax'        => true,
			'aria-valuemin'        => true,
			'aria-valuenow'        => true,
			'aria-valuetext'       => true,
		);

		$live_region_attributes = array(
			'aria-atomic'   => true,
			'aria-busy'     => true,
			'aria-live'     => true,
			'aria-relevant' => true,
		);

		$drag_drop_attributes = array(
			'aria-dropeffect' => true,
			'aria-grabbed'    => true,
		);

		$relationship_attributes = array(
			'aria-activedescendant' => true,
			'aria-controls'         => true,
			'aria-describedby'      => true,
			'aria-flowto'           => true,
			'aria-labelledby'       => true,
			'aria-owns'             => true,
			'aria-posinset'         => true,
			'aria-setsize'          => true,
		);
		return array_merge( $global_attributes, $live_region_attributes, $widget_attributes, $drag_drop_attributes, $relationship_attributes );
	}

	private static function get_svg_kses() {
		$tags = array(
			'svg'      => array(
				'class'   => true,
				'xmlns'   => true,
				'width'   => true,
				'height'  => true,
				'viewbox' => true,
			),
			'g'        => array(),
			'title'    => array(),
			'path'     => array(
				'd' => true,
			),
			'polyline' => array(
				'points' => true,
			),
			'polygon'  => array(
				'points' => true,
			),
			'rect'     => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
			),
			'circle'   => array(
				'r'  => true,
				'cx' => true,
				'cy' => true,
			),
			'ellipse'  => array(
				'cx' => true,
				'cy' => true,
				'rx' => true,
				'ry' => true,
			),
			'line'     => array(
				'x1' => true,
				'x2' => true,
				'y1' => true,
				'y2' => true,
			),
		);

		$presentationAttributes = array(
			'clip-path'           => true,
			'clip-rule'           => true,
			'color'               => true,
			'color-interpolation' => true,
			'cursor'              => true,
			'display'             => true,
			'fill'                => true,
			'fill-opacity'        => true,
			'fill-rule'           => true,
			'filter'              => true,
			'mask'                => true,
			'opacity'             => true,
			'pointer-events'      => true,
			'shape-rendering'     => true,
			'stroke'              => true,
			'stroke-dasharray'    => true,
			'stroke-dashoffset'   => true,
			'stroke-linecap'      => true,
			'stroke-linejoin'     => true,
			'stroke-miterlimit'   => true,
			'stroke-opacity'      => true,
			'stroke-width'        => true,
			'transform'           => true,
			'vector-effect'       => true,
			'visibility'          => true,
		);
		foreach ( array( 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'path' ) as $prop ) {
			$tags[ $prop ] = array_merge( $tags[ $prop ], $presentationAttributes );
		}
		return $tags;
	}

}

new STMPD_View();
