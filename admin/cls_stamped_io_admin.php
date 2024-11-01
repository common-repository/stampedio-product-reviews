<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class STMPD_Admin {

	public function __construct() {
		$this->pl_url = plugins_url( '', __FILE__ );
		// Adding A tab in Woocommerce Tabs for setting details
		add_action( 'admin_menu', array( $this, 'menu' ), 10 );
		add_action( 'admin_menu', array( $this, 'menu_modified_url' ), 11 );
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_setting_tab' ), 10, 1 );
	}

	// Adding A tab in Woocommerce Tabs for setting details
	public function add_setting_tab( $settings ) {
		$settings[] = include( plugin_dir_path( __FILE__ ) . '/cls_stamped_io_settings.php' );
		return $settings;
	}

	public function menu() {
		$icon_url = plugins_url( '/assets/images/699370-icon-23-star-128.png', dirname( __FILE__ ) );
		add_menu_page( 'Stamped.io', 'Stamped.io', 'manage_options', 'woo_stamped_io', 'menu_page', $icon_url );
	}

	public function menu_page() {
		return '';
	}

	public function menu_modified_url() {
		global $menu;
		if ( is_array( $menu ) && count( $menu ) > 0 ) {
			foreach ( $menu as $key => $val ) {
				if ( $val[2] == 'woo_stamped_io' ) {
					$menu[ $key ][2] = add_query_arg(
						array(
							'page' => 'wc-settings',
							'tab'  => 'woo_stamped_io',
						),
						admin_url( 'admin.php' )
					);
				}
			}
		}
	}
}

new STMPD_Admin();
