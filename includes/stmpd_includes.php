<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class STMPD_Includes {

    public $pl_url;

    public function __construct() {
        $this->pl_url = plugins_url( '', dirname( __FILE__ ) );
        $this->global_init();
        if ( is_admin() ) {
            $this->admin_init();
        } else {
            $this->public_init();
        }
    }

    /**
     * GLobal Setting for admin and public
     */
    public function global_init() {
        $this->activate_order_status();
        add_shortcode( 'Woo_stamped_io', array( $this, 'shortcode' ) );
    }

    /**
     * Admin Setting for admin
     */
    public function admin_init() {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_js' ) );
        $this->admin_ajax();
    }

    /**
     * Public Setting for Public
     */
    public function public_init() {
        $this->prepare_scripts();
    }

    /**
     * All Wp Ajax Hooks
     */
    public function admin_ajax() {
        add_action( 'wp_ajax_Woo_stamped_bulk_reviews', array( $this, 'bulk_reviews_ajax' ) );
        add_action( 'wp_ajax_Woo_stamped_bulk_export_review', array( $this, 'bulk_export_review' ) );
        add_action( 'wp_ajax_Woo_stamped_clear_reviews_cache', array( $this, 'clear_reviews_cache' ) );
    }

    public function admin_js() {
        wp_enqueue_style( 'woo-stamped-io-admin-css', $this->pl_url . '/assets/css/woo-stamped.io.css', false, '1.9.2' );
        wp_register_script( 'woo-stamped-io-admin-js', $this->pl_url . '/assets/js/woo-stamped.io.js', false, '1.9.3' );
        wp_enqueue_script( 'woo-stamped-io-admin-js' );
        $this->admin_localize_scripts();
    }

    public function admin_localize_scripts() {
        $vars             = array();
        $vars['ajax_url'] = admin_url( 'admin-ajax.php' );
        $vars['nonces']['bulk_reviews'] = wp_create_nonce('stamped_bulk_reviews');
        $vars['nonces']['bulk_export_review'] = wp_create_nonce('stamped_bulk_export');
        $vars['nonces']['clear_reviews_cache'] = wp_create_nonce('stamped_clear_reviews_cache');
        wp_localize_script( 'woo-stamped-io-admin-js', 'STMPD_Admin', $vars );
    }

    public function prepare_scripts() {
        add_action( 'wp_enqueue_scripts', array( $this, 'public_js_css' ) );
    }

    public function public_js_css() {
        // wp_register_style('woo-stamped-io-public-css', "//cdn-stamped-io.azureedge.net/files/widget.min.css", false, '1.0.0');
        // wp_register_script('woo-stamped-io-public-cdn', "//cdn-stamped-io.azureedge.net/files/widget.min.js", false, '1.0.0');
        // wp_enqueue_style('woo-stamped-io-public-css');
        // wp_enqueue_script('woo-stamped-io-public-cdn');

        wp_register_script( 'woo-stamped-io-public-custom', $this->pl_url . '/assets/js/woo-stamped.io-public.js', false, '1.9.3' );
        wp_enqueue_script( 'woo-stamped-io-public-custom' );
        $this->localize_vars();
    }

    public function localize_vars() {
        $vars            = array();
        $vars['pub_key'] = STMPD_API::get_public_keys();
        $vars['store_hash'] = STMPD_API::get_store_hash();
        $vars['url']     = STMPD_API::get_site_url();
        wp_localize_script( 'woo-stamped-io-public-custom', 'Woo_stamped', $vars );
    }

    public function shortcode( $atts, $content = '' ) {
        global $post;
        $atts       = shortcode_atts(
            array(
                'type'       => 'badge',
                'product_id' => 0,
            ),
            $atts,
            'Woo_stamped_io_shortcode'
        );
        $type       = $atts['type'] ?? 'badge';
        $product_id = $atts['product_id'] ?? 0;

        global $product;
        if ( isset( $product_id ) && $product_id > 0 ) {
            $product = new WC_Product( $product_id );
        }

        if ( empty( $type ) ) {
            $type = 'badge';
        }

        if ( $type == 'badge' ) {
            ob_start();
            STMPD_View::review_badge();
            return ob_get_clean();
        }

        if ( $type == 'widget' ) {
            ob_start();
            STMPD_View::review_box();
            return ob_get_clean();
        }

        if ( $type == 'rich-snippet' ) {
            $data = $this->fetched_aggregate_rating( $product->get_id() );
            return STMPD_View::aggregate_rating( $data );
        }
    }

    public function bulk_reviews_ajax() {
        $this->check_nonce('bulk_reviews');
        $this->permission_check();
        $response = $this->bulk_review();
        $return   = array(
            'status' => 'success',
            'text'   => '<b>Order history exported to Stamped.io</b>',
        );
        echo wp_json_encode( $return );
        exit();
    }

    public function activate_order_status() {
        $statuses = STMPD_API::get_activated_status();
        if ( is_array( $statuses ) && count( $statuses ) > 0 ) {
            foreach ( $statuses as $status ) {
                add_action( "woocommerce_order_status_{$status}", array( $this, 'submit_review' ), 10 );
            }
        }
    }

    public static function order_json( $ID ) {
        $orderData = array();
        $order     = new WC_Order( $ID );
        if ( $order ) {
            $line_items = $order->get_items( 'line_item' );
            $items      = array();
            $pro        = 0;
            $productId  = 0;
            if ( is_array( $line_items ) && count( $line_items ) > 0 ) {
                foreach ( $line_items as $key => $v ) {
                    $productId = $v['variation_id'] !== '0' ? $v['product_id'] : $v['product_id'];
                    if ( $productId != '' ) {
                        /*
                        $parent_grouped_id = 0;

                        // The SQL query
                        $results = $wpdb->get_results( "
                            SELECT pm.meta_value as child_ids, pm.post_id
                            FROM {$wpdb->prefix}postmeta as pm
                            INNER JOIN {$wpdb->prefix}posts as p ON pm.post_id = p.ID
                            INNER JOIN {$wpdb->prefix}term_relationships as tr ON pm.post_id = tr.object_id
                            INNER JOIN {$wpdb->prefix}terms as t ON tr.term_taxonomy_id = t.term_id
                            WHERE p.post_type LIKE 'product'
                            AND p.post_status LIKE 'publish'
                            AND t.slug LIKE 'grouped'
                            AND pm.meta_key LIKE '_children'
                            ORDER BY p.ID
                        " );

                        // Retreiving the parent grouped product ID
                        foreach( $results as $result ){
                            foreach( maybe_unserialize( $result->child_ids ) as $child_id )
                                if( $child_id == $productId ){
                                    $parent_grouped_id = $result->post_id;
                                    break;
                                }
                            if( $parent_grouped_id != 0 ) break;
                        }

                        if( $parent_grouped_id != 0 ){
                            $productId = $parent_grouped_id;
                        } */

                        $products = new WC_Product( $productId );
                        $url      = get_the_post_thumbnail_url( $products->get_id() );
                        $items[]  = array(
                            'productId'       => $productId,
                            // "productDescription" => $products->post->post_content,
                            'productBrand'    => null,
                            'productTitle'    => $v['name'],
                            'productPrice'    => $products->get_price(),
                            'productType'     => null,
                            'productSKU'      => $products->get_sku(),
                            'productTags'     => strip_tags( wc_get_product_tag_list( $products->get_id() ) ),
                            'productUrl'      => get_the_permalink( $productId ),
                            'productImageUrl' => $url,
                        );
                    }
                }
            }
            $orderData = array(
                'email'            => $order->get_billing_email() ? $order->get_billing_email() : '',
                'firstName'        => $order->get_billing_first_name() ? $order->get_billing_first_name() : '',
                'lastName'         => $order->get_billing_last_name() ? $order->get_billing_last_name() : '',
                'location'         => $order->get_billing_country(),
                'phoneNumber'      => $order->get_billing_phone() ? $order->get_billing_phone() : '',
                'orderNumber'      => $order->get_order_number(),
                'orderId'          => $ID,
                'orderCurrencyISO' => $order->get_currency(),
                'orderTotalPrice'  => $order->get_total(),
                'orderSource'      => 'WooCommerce',
                'orderDate'        => $order->get_date_created()->format( 'c' ),
                'itemsList'        => $items,
            );

            // POLYLANG INTEGRATION
            if ( function_exists( 'pll_get_post_language' ) ) {
                $orderData['locale'] = pll_get_post_language( $ID );
            }
        }
        return $orderData;
    }

    public function submit_review( $ID ) {
        $orderData = self::order_json( $ID );
        $storeHash = STMPD_API::get_store_hash();
        if($storeHash) {
            $response = STMPD_API::send_request_v2($storeHash, '/survey/reviews', $orderData);
        } else {
            //Fallback to legacy
            $response  = STMPD_API::send_request( '/survey/reviews', $orderData );
        }
        if ( is_object( $response ) ) {
            update_post_meta( $ID, 'send_to_stamped_for_review', 1 );
        }
    }

    public function fetched_aggregate_rating( $product_id ) {
        $storeHash = STMPD_API::get_store_hash();
        $agrr_review = get_post_meta( $product_id, 'stamped_io_product_reviews', true );
        $ttl         = (int) get_post_meta( $product_id, 'stamped_io_product_ttl', true );
        if ( $agrr_review == null || $agrr_review == '' || $ttl < time() ) {
            if($storeHash) {
                $outcome = (array) STMPD_API::send_request_v2($storeHash,"/richSnippet?productId={$product_id}", array(), 'GET' );
            } else {
                $outcome = (array) STMPD_API::send_request( "/richSnippet?productId={$product_id}", array(), 'GET' );
            }

            if ( isset( $outcome['httpStatusCode'] ) ) {
                $ttl = (int) $outcome['ttl'] + time();
                update_post_meta( $product_id, 'stamped_io_product_reviews', $outcome );
                update_post_meta( $product_id, 'stamped_io_product_ttl', $ttl );
                $agrr_review = $outcome;
            }
        }
        return $agrr_review;
    }

    public function bulk_review() {
        $storeHash = STMPD_API::get_store_hash();
        $paged = $_POST['paged'];
        $per_page    = 500;
        $response    = array();
        $status      = STMPD_API::stamped_order_status();
        $args        = array(
            'post_type'      => 'shop_order',
            'post_status'    => ( ( is_array( $status ) && count( $status ) > 0 ) ? $status : 'any' ),
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'date_query'     => array(
                array( 'after' => '12 months ago' ),
            ),
        );

        $orders        = new WP_Query( $args );
        $fp            = $orders->found_posts;
        $bulkOrderData = array();
        if ( $orders->have_posts() ) {
            while ( $orders->have_posts() ) {
                $orders->the_post();
                global $post;
                $bulkOrderData[] = self::order_json( $post->ID );
            }
        }
        $response = '';
        if ( is_array( $bulkOrderData ) && count( $bulkOrderData ) > 0 ) {
            if($storeHash) {
                $responseFull = STMPD_API::send_request_v2($storeHash, '/survey/reviews/bulk', $bulkOrderData );
                $response = $responseFull->data;
            }
            else {
                $response = STMPD_API::send_request( '/survey/reviews/bulk', $bulkOrderData );
            }

            // STMPD_API::Woo_stamped_logging("Bulk call response " . print_r($response, true));

            if ( is_object( $response ) && count( $response ) > 0 ) {
                update_options( 'update_stamped_bulk_order', date( 'Y-m-d' ) );
                // STMPD_API::Woo_stamped_logging("Bulk call success ");

                $output = 'Success';
            }
        }
        $cal = '(' . ( ( $paged - 1 ) * $per_page ) . '-' . ( $paged * $per_page ) . ')';

        $response = '<b>' . __( "{$cal} Order imported to Stamped.io" ) . '</b><br />';

        $rm = $fp % $per_page;
        $cn = $fp / $per_page;
        $cn = (int) $cn;
        if ( $rm > 0 ) {
            $cn++;
        }
        // var_dump($cn);
        // var_dump($paged);
        $paged++;

        $returnOutput['status']   = 'success';
        $returnOutput['paged']    = $paged;
        $returnOutput['response'] = $response;
        if ( $paged > $cn ) {
            $returnOutput['laststep'] = 'yes';
            $returnOutput['response'] = '<b>' . __( "{$fp} Orders imported to Stamped.io " ) . '</b><br />';
        }

        echo wp_json_encode( $returnOutput );
        exit();
    }

    public function clear_reviews_cache() {
        $this->check_nonce('clear_reviews_cache');
        $this->permission_check();
        global $wpdb;
        $sql    = ( "DELETE FROM `{$wpdb->prefix}postmeta` WHERE `meta_key` LIKE '%stamped_io_product%'" );
        $result = $wpdb->get_results( $sql, ARRAY_A );

        $return = array(
            'status' => 'success',
            'text'   => '<b>Reviews Cache has been cleared</b>',
        );
        echo wp_json_encode( $return );

        exit();
    }

    public function bulk_export_review() {
        $this->check_nonce('bulk_export');
        $this->permission_check();
        global $wpdb;
        $filename = $_POST['filename'];
        $paged = $_POST['paged'];

        $path = plugin_dir_path( __FILE__ );
        $path = $path . 'csv';
        if ( ! file_exists( $path ) ) {
            mkdir( $path );
        }
        $handle   = fopen( "{$path}/{$filename}.csv", 'a' );
        $per_page = 500;
        $response = array();
        $paged_e  = $paged - 1;

        if ( $paged == 1 ) {
            fputcsv( $handle, array( 'product_id', 'author', 'email', 'rating', 'title', 'body', 'created_at', 'reply', 'replied_at', 'product_image', 'product_url', 'published' ) );
        }

        $fp         = 0;
        $sql_all    = sprintf( "SELECT * FROM `{$wpdb->prefix}comments` WHERE `comment_post_ID` IN (SELECT `ID` FROM `{$wpdb->prefix}posts` WHERE `post_status` LIKE 'publish' AND `post_type` LIKE 'product') ORDER BY `comment_post_id`" );
        $result_all = $wpdb->get_results( $sql_all, ARRAY_A );
        if ( is_array( $result_all ) && count( $result_all ) > 0 ) {
            $fp = count( $result_all );
        }
        $sql      = sprintf( "SELECT * FROM `{$wpdb->prefix}comments` WHERE `comment_post_ID` IN (SELECT `ID` FROM `{$wpdb->prefix}posts` WHERE `post_status` LIKE 'publish' AND `post_type` LIKE 'product') ORDER BY `comment_post_id` ASC LIMIT %d,500", ( $paged_e * $per_page ) );
        $result   = $wpdb->get_results( $sql, ARRAY_A );
        $complete = array();
        $comments = array();
        $child    = array();
        if ( is_array( $result ) && count( $result ) > 0 ) {
            foreach ( $result as $key => $val ) {

                $comment_ID = $val['comment_ID'];
                $comment_post_ID = $val['comment_post_ID'];
                $comment_approved = $val['comment_approved'];
                $comment_author = $val['comment_author'];
                $comment_author_email = $val['comment_author_email'];
                $comment_content = $val['comment_content'];
                $comment_date = $val['comment_date'];
                $comment_parent = $val['comment_parent'];
                $complete[ $comment_ID ] = $val;
                $post_thumbnail_id       = get_post_thumbnail_id( $comment_post_ID );
                $product_image           = '';
                if ( $post_thumbnail_id > 0 ) {
                    $image_attributes = wp_get_attachment_image_src( $post_thumbnail_id );
                    $product_image    = $image_attributes[0];
                }
                $product_url  = get_the_permalink( $comment_post_ID );
                $is_published = 'FALSE';
                if ( $comment_approved == 1 ) {
                    $is_published = 'TRUE';
                }

                $comments[ $comment_ID ] = array( $comment_post_ID, $comment_author, $comment_author_email, get_comment_meta( $comment_ID, 'rating', true ), null, $comment_content, $comment_date, ( $comment_parent == '0' ? '' : 1 ), '', $product_image, $product_url, $is_published );
                if ( $comment_parent != 0 ) {
                    $child[ $comment_ID ] = $comment_parent;
                }
            }
        }

        if ( is_array( $child ) && count( $child ) > 0 ) {
            foreach ( $child as $key => $v ) {
                $comments[ $v ][7] = ( $comments[ $key ][5] != '' ? 1 : '' );
                $comments[ $v ][8] = $comments[ $key ][6];
                unset( $comments[ $key ] );
            }
        }

        if ( is_array( $comments ) && count( $comments ) > 0 ) {
            foreach ( $comments as $key => $val ) {
                fputcsv( $handle, $val );
            }
        }
        fclose( $handle );
        $cal      = '(' . ( ( $paged - 1 ) * $per_page ) . '-' . ( $paged * $per_page ) . ')';
        $response = '<b></b><br />';
        $rm       = $fp % $per_page;
        $cn       = $fp / $per_page;
        $cn       = (int) $cn;
        if ( $rm > 0 ) {
            $cn++;
        }
        $paged++;
        $returnOutput['status']   = 'success';
        $returnOutput['paged']    = $paged;
        $returnOutput['response'] = $response;
        if ( $paged > $cn ) {
            $returnOutput['laststep'] = 'yes';
            $returnOutput['response'] = '<b>' . __( "Reviews Exported to CSV <a href='{$this->pl_url}/includes/csv/{$filename}.csv' " ) . " target='_blank'>Click here to download</a></b><br />";
        }
        if(count($result) === 0) {
            $returnOutput['laststep'] = 'yes';
            $returnOutput['response'] = '<b>No reviews to export!<br />';
        }
        echo wp_json_encode( $returnOutput );
        exit();
    }

    private function check_nonce($action) {
        $nonce = $_SERVER["HTTP_X_STMPD_NONCE"];
        if(false === wp_verify_nonce($nonce, "stamped_$action")){
            http_response_code(403);
            exit();
        }
    }

    public function permission_check(){
        if(!current_user_can('edit_posts')) {
            http_response_code(403);
            exit();
        }
    }

}

$woo_stamped = new STMPD_Includes();
