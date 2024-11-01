<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class STMPD_API {

    private static $API_HOST = "https://stamped.io";

	/**
	 *
	 * @return string get stamped io site url
	 */
	public static function get_site_url() {
		return get_option( 'stamped_store_url' );
	}

	/**
	 * checking native review function is disabled
	 *
	 * @return string return WC Native Rating is Enable or disabled
	 */
	public static function disallow_native_rating() {
		return get_option( 'stamped_disable_wc_review' );
	}

	/**
	 *
	 * @return type cheking stamped io rating is enable on archive and single product page
	 */
	public static function Show_stamped_rating_on_archive() {
		return get_option( 'stamped_show_rating_on_grid_view' );
	}

	/**
	 *
	 * @return String Getting Location of Stamped Io review box,Inside tab,Below Tabs,Custom Location
	 */
	public static function get_selected_area_of_stamped_area() {
		return get_option( 'stamped_show_review_inside_below' );
	}

	public static function get_rating_enable_on_product() {
		return get_option( 'stamped_rating_enable_on_product' );
	}

	public static function get_enable_rich_snippet() {
		return get_option( 'stamped_enable_rich_snippet' );
	}

	public static function get_enable_reviews_cache() {
		return get_option( 'stamped_enable_reviews_cache' );
	}

	public static function get_enable_rewards() {
		return get_option( 'stamped_enable_rewards' );
	}

    public static function get_store_hash() {
        return get_option('stamped_store_hash');
    }

	/**
	 *
	 * @return String Getting Public key
	 */
	public static function get_public_keys() {
		return get_option( 'stamped_public_key' );
	}

	/**
	 *
	 * @return String Getting Public Key
	 */
	public static function get_private_keys() {
		return get_option( 'stamped_private_key' );
	}

	/**
	 *
	 * @return String Token for basic Oauth
	 */
	public static function get_token() {
		$public_key  = self::get_public_keys();
		$private_key = self::get_private_keys();
		$token_key   = trim( $public_key ) . ':' . trim( $private_key );
		return base64_encode( $token_key );
	}

	/**
	 *
	 * @return string get all selected order status which one selected in setting page
	 */
	public static function stamped_order_status() {
		$status = get_option( 'stamped_order_status' );
		return $status;
	}

	/**
	 *
	 * @return array Get all activated order status without slicing wc-
	 */
	public static function get_activated_status() {
		$output = array();
		$status = self::stamped_order_status();
		if ( is_array( $status ) && count( $status ) > 0 ) {
			foreach ( $status as $val ) {
				if ( strpos( $val, 'wc-' ) > -1 ) {
					$val = substr( $val, 3 );
				}
				$output[] = $val;
			}
		}
		return $output;
	}

	/**
	 *
	 * @param type $requst_service string or part of service url   /survey/review/bulk
	 * @param type $postfields  array of json post data
	 * @param type $method Method use for curl request defult is POST
	 * @return type
	 */
	public static function send_request( $requst_service, $postfields = array(), $method = 'POST' ) {
		$domainName = self::get_site_url();
		$baseToken  = self::get_token();

		$curl = curl_init();

		$options = array(
			CURLOPT_URL            => self::$API_HOST."/api/{$domainName}{$requst_service}",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => "{$method}",
			CURLOPT_HTTPHEADER     => array(
				"authorization: Basic {$baseToken}",
				'cache-control: no-cache',
				'content-type: application/json',
			),
			CURLOPT_SSL_VERIFYPEER => false,
		);
		if ( count( $postfields ) > 0 ) {
			$options[ CURLOPT_POSTFIELDS ] = wp_json_encode( $postfields );
		}

		curl_setopt_array( $curl, $options );
		$response = curl_exec( $curl );
		$err      = curl_error( $curl );
		curl_close( $curl );
		if ( $err ) {
			return $err;
		} else {
			return json_decode( $response );
		}
	}

	public static function send_request_v2( $store_hash, $requst_service, $postfields = array(), $method = 'POST' ) {
		$baseToken = self::get_token();

		$curl = curl_init();

		$options = array(
			CURLOPT_URL            => self::$API_HOST."/api/v2/{$store_hash}{$requst_service}",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => "{$method}",
			CURLOPT_HTTPHEADER     => array(
				"authorization: Basic {$baseToken}",
				'cache-control: no-cache',
				'content-type: application/json',
			),
			CURLOPT_SSL_VERIFYPEER => false,
		);
		if ( count( $postfields ) > 0 ) {
			$options[ CURLOPT_POSTFIELDS ] = wp_json_encode( $postfields );
		}

		curl_setopt_array( $curl, $options );
		$response = curl_exec( $curl );
		$err      = curl_error( $curl );
		curl_close( $curl );
		if ( $err ) {
			return $err;
		} else {
			return json_decode( $response );
		}
	}

	public static function get_request( $product_id, $product_title, $postfields = array(), $method = 'POST' ) {
		$domainName = self::get_site_url();
		$public_key = self::get_public_keys();
        $storeHash = self::get_store_hash();

		$request = wp_remote_get(
            self::$API_HOST."/api/widget?productId={$product_id}&sId={$storeHash}&apiKey={$public_key}&storeUrl={$domainName}&productName={$product_title}",
			array(
				'timeout'   => 5,
				'sslverify' => false,
				'headers'   => array(
					'authorization' => 'Basic {$baseToken}',
					'cache-control' => 'no-cache',
					'content-type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $request ) ) {
			return false; // Bail early
		}

		$body = wp_remote_retrieve_body( $request );

		$data = json_decode( $body );

		if ( ! empty( $data ) ) {
			return $data;
		}
	}
}
