<?php
/*
  Plugin Name: Stamped.io Reviews & UGC for WooCommerce
  Plugin URI: https://stamped.io/
  Description: Stamped.io Product Reviews, Ratings, Questions & Answers, Social Integrations, Marketing & Upselling, Loyalty & Rewards and more!
  Version: 2.4.2
  Author: Stamped.io
  Author URI: https://stamped.io/
  License: GPLv2 or later
  License URI: http://www.gnu.org/licenses/gpl-2.0.html
  Text Domain: stamped-io-reviews-for-woocommerce
 */

define( 'STMPD_WC_LEAST_VERSION', '3.0.1' );

require_once( plugin_dir_path( __FILE__ ) . '/includes/stmpd_wc_dependencies.php' );

if ( ! STMPD_WC_Dependencies::is_woocommerce_active() ) {
	add_action( 'admin_notices', 'stmpd_woocommerce_not_installed_notice' );

	return;
}
add_action( 'plugins_loaded', 'stmpd_main', 0 );

/**
 * Include all Woo Stamped Io files
 */
function stmpd_main() {
	if ( STMPD_WC_Dependencies::is_woocommerce_active() ) {
		global $woocommerce;
		if ( ! version_compare( $woocommerce->version, STMPD_WC_LEAST_VERSION, '>=' ) ) {
			add_action( 'admin_notices', 'stmpd_woocommerce_version_check_notice' );
			return false;
		}
	}
	require( plugin_dir_path( __FILE__ ) . '/includes/stmpd_api.php' );
	require( plugin_dir_path( __FILE__ ) . '/admin/cls_stamped_io_admin.php' );
	require( plugin_dir_path( __FILE__ ) . '/view/stmpd_view.php' );
	require( plugin_dir_path( __FILE__ ) . '/includes/stmpd_includes.php' );
}

function stmpd_woocommerce_version_check_notice() {     ?>
	<div class="error">
		<p><?php esc_html( 'WooCommerce Stamped.io require WooCommerce version ' . STMPD_WC_LEAST_VERSION . ' or greater' ); ?></p>
	</div>
	<?php
}

function stmpd_woocommerce_not_installed_notice() {
	?>
	<div class="error">
		<p><?php esc_html( __( 'WooCommerce Stamped.io: WooCommerce  not Activated or not Installed.', 'woo-stamped-io' ) ); ?></p>
	</div>
	<?php
}
