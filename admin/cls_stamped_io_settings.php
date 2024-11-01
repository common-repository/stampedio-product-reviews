<?php
if ( ! class_exists( 'STMPD_admin_tab' ) ) :

	/**
	 * WC_Settings_Webhooks
	 */
	class STMPD_admin_tab extends WC_Settings_Page {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id    = 'woo_stamped_io';
			$this->label = __( 'Stamped.io', 'stampedio-product-reviews' );
			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
			add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
			add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
			add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
			add_action( 'woocommerce_admin_field_Woo_stamped_button_html', array( $this, 'button_html' ) );
			add_action( 'woocommerce_admin_field_Woo_stamped_html', array( $this, 'html' ) );
		}

		/**
		 * Woocommerce Custom field for Button for WooStamped Order history
		 *
		 * @param type $value
		 */
		public function button_html( $value ) {
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for=""
						   <?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?>
					</label>

				</th>
				<td class="forminp">
					<input type="button" name=""<?php echo esc_attr( $value['id'] ); ?>" id="<?php echo esc_attr( $value['id'] ); ?>" class="<?php echo esc_attr( $value['class'] ); ?>" value="<?php echo esc_attr( $value['value'] ); ?>"> <i class="processing" style="display: inline-block;">
						<span class="spinner"></span>
						<span class="response" ></span>
					</i>
					<br />
					<p>
						<?php echo wp_kses( $value['desc'], 'post' ); ?>
					</p>
				</td>
			</tr>
			<?php
		}

		public function html() {
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<input type="hidden" name="paged" class="woo_stamped_counter" value="1">
					<input type="hidden" name="filename" class="woo_stamped_filename" value="<?php echo esc_attr( 'reviews-' . time() ); ?>">
				</th>
				<td class="forminp bulk_import_fields" counter="1">
				</td>
			</tr>

			<?php
		}

		/**
		 * Get sections
		 *
		 * @return array
		 */
		public function get_sections() {
			$sections = array(
				'STMPD_Includes'                => __( 'Stamped.io Settings', 'stampedio-product-reviews' ),
				'woo_stamped_io_import_history' => __( 'Import Order History', 'stampedio-product-reviews' ),
				'woo_stamped_io_export_reviews' => __( 'Export Product Reviews', 'stampedio-product-reviews' ),
			);
			return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
		}

		/**
		 * Output the settings
		 */
		public function output() {
			global $current_section;
			$settings = $this->get_settings( $current_section );
			WC_Admin_Settings::output_fields( $settings );
		}

		/**
		 * Save settings
		 */
		public function save() {
			global $current_section;
			$settings = $this->get_settings( $current_section );
			WC_Admin_Settings::save_fields( $settings );
		}

		/**
		 * Get settings array
		 *
		 * @return array
		 */
		public function get_settings( $current_section = '' ) {
			global $hide_save_button;
			if ( $current_section == 'woo_stamped_io_import_history' ) {
				$hide_save_button = true;
				$settings         = array(
					array(
						'title' => __( 'Import Order history', 'stampedio-product-reviews' ),
						'type'  => 'title',
						'id'    => 'checkout_page_history_order',
					),
					array(
						'title'    => __( 'Import Orders', 'stampedio-product-reviews' ),
						'id'       => 'Woo_stamped_bulk_review_button',
						'type'     => 'Woo_stamped_button_html',
						'class'    => 'button-primary woocommerce-save-button',
						'css'      => '',
						'value'    => 'Import Order History',
						'desc'     => '<i>Import your existing orders history up to 6 months to Stamped.io. <br/>If you need to import orders that are back-dated more than 6 months, please email us at hello@stamped.io</i>',
						'desc_tip' => false,
					),
					array(
						'id'    => 'Woo_stamped_bulk_review_button',
						'type'  => 'Woo_stamped_html',
						'class' => 'button-primary woocommerce-save-button',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'checkout_page_history_order',
					),
				);
				return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
			} elseif ( $current_section == 'woo_stamped_io_export_reviews' ) {
				$hide_save_button = true;
				$settings         = array(
					array(
						'title' => __( 'Export Product Reviews', 'stampedio-product-reviews' ),
						'type'  => 'title',
						'id'    => 'checkout_page_history_order',
					),
					array(
						'title'    => __( 'Export Product Reviews', 'stampedio-product-reviews' ),
						'id'       => 'Woo_stamped_bulk_export_review_button',
						'type'     => 'Woo_stamped_button_html',
						'class'    => 'button-primary woocommerce-save-button',
						'css'      => '.submit{display:none !important}',
						'value'    => 'Export Order reviews',
						'desc'     => '<i>Import your existing product reviews. <br />CSV will be generated and available for download once processing is complete.</i>',
						'desc_tip' => true,
					),
					array(
						'id'    => 'Woo_stamped_bulk_review_button',
						'type'  => 'Woo_stamped_html',
						'class' => 'button-primary woocommerce-save-button',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'checkout_page_history_order',
					),
				);
				return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
			} else {
				$settings = array(
					array(
						'title' => __( 'Stamped.io Settings', 'stampedio-product-reviews' ),
						'type'  => 'title',
						'id'    => 'stamped_io_settings',
					),
					array(
						'title'    => __( 'Store Hash', 'stampedio-product-reviews' ),
						'id'       => 'stamped_store_hash',
						'type'     => 'text',
						'class'    => '',
						'css'      => 'min-width:300px;',
						'desc' => "<i>Store Hash is <b>Highly Recommended</b> to be filled in.<br />  It can be retrieved from the Stamped Dashboard under Settings > API Keys</i>"
					),
					array(
						'title'    => __( 'API Public Key', 'stampedio-product-reviews' ),
						'id'       => 'stamped_public_key',
						'type'     => 'text',
						'class'    => '',
						'css'      => 'min-width:300px;',
						'desc'     => '',
						'desc_tip' => true,
					),
					array(
						'title'    => __( 'API Private Key', 'stampedio-product-reviews' ),
						'id'       => 'stamped_private_key',
						'type'     => 'text',
						'class'    => '',
						'css'      => 'min-width:300px;',
						// 'desc'=>"fgfj",
						'desc_tip' => true,
					),
					array(
						'title' => __( 'Store URL', 'stampedio-product-reviews' ),
						'id'    => 'stamped_store_url',
						'type'  => 'text',
						'class' => '',
						'css'   => 'min-width:300px;',
						'desc'  => "Use the store URL you've registered in Stamped.io. Note: Do not include http:// or https://.",
					),
					array(
						'title'       => __( 'Status', 'stampedio-product-reviews' ),
						'id'          => 'stamped_order_status',
						'type'        => 'multiselect',
						'options'     => wc_get_order_statuses(),
						'placeholder' => 'Choose Status',
						'class'       => 'wc-enhanced-select',
						'css'         => 'min-width:300px;',
						'default'     => 'wc-completed',
						'desc'        => __( '<i>Set the order statuses that will trigger the review requests to be sent, example of commonly used statuses are "Complete", "Shipped," "Pending," or "Received.</i>', 'stampedio-product-reviews' ),
						'desc_tip'    => true,
					),
					array(
						'type' => 'sectionend',
						'id'   => 'stamped_io_settings',
					),
					array(
						'title' => __( 'Display Settings', 'stampedio-product-reviews' ),
						'type'  => 'title',
						'id'    => 'stamped_io_extra_settings',
					),
					array(
						'title'    => __( 'Disable Native WooCommerce Reviews', 'stampedio-product-reviews' ),
						'id'       => 'stamped_disable_wc_review',
						'type'     => 'checkbox',
						'class'    => '',
						'css'      => '',
						'default'  => 'yes',
						'desc_tip' => true,
					),
					array(
						'title'    => __( 'Display Stamped.io Reviews', 'stampedio-product-reviews' ),
						'id'       => 'stamped_show_review_inside_below',
						'type'     => 'radio',
						'class'    => '',
						'default'  => 'below',
						'options'  => array(
							'inside' => __( 'Inside Tab' ),
							'below'  => __( 'Below Description' ),
							'Other'  => __( 'Custom location' ),
						),
						'css'      => '',
						'desc_tip' => true,
					),
					array(
						'title'    => __( 'Enable reviews ratings in Category Page', 'stampedio-product-reviews' ),
						'id'       => 'stamped_show_rating_on_grid_view',
						'type'     => 'checkbox',
						'class'    => '',
						'css'      => 'min-width:300px;',
						'desc_tip' => true,
						'default'  => 'yes',
					),
					array(
						'title'    => __( 'Enable reviews ratings in Product Page', 'stampedio-product-reviews' ),
						'id'       => 'stamped_rating_enable_on_product',
						'type'     => 'checkbox',
						'class'    => '',
						'css'      => 'min-width:300px;',
						'desc_tip' => true,
						'default'  => 'yes',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'stamped_io_rewards_settings',
					),
					array(
						'title' => __( 'Loyalty & Rewards', 'stampedio-product-reviews' ),
						'type'  => 'title',
						'id'    => 'stamped_io_rewards_status_settings',
					),
					array(
						'title'    => __( 'Enable Loyalty & Rewards', 'stampedio-product-reviews' ),
						'id'       => 'stamped_enable_rewards',
						'type'     => 'checkbox',
						'class'    => '',
						'css'      => 'min-width:300px;',
						'desc_tip' => true,
						'default'  => 'no',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'stamped_io_extra_settings',
					),
					array(
						'title' => __( 'SEO and Cache Settings', 'stampedio-product-reviews' ),
						'type'  => 'title',
						'id'    => 'stamped_io_seo_extra_settings',
					),
					array(
						'title'    => __( 'Enable reviews rich snippet', 'stampedio-product-reviews' ),
						'id'       => 'stamped_enable_rich_snippet',
						'type'     => 'checkbox',
						'class'    => '',
						'css'      => 'min-width:300px;',
						'desc_tip' => true,
						'default'  => 'yes',
					),
					array(
						'title'    => __( 'Enable reviews cache', 'stampedio-product-reviews' ),
						'id'       => 'stamped_enable_reviews_cache',
						'type'     => 'checkbox',
						'class'    => '',
						'css'      => 'min-width:300px;',
						'desc_tip' => true,
						'default'  => 'yes',
					),
					array(
						'title'    => __( 'Clear Reviews Cache', 'stampedio-product-reviews' ),
						'id'       => 'Woo_stamped_clear_reviews_cache',
						'type'     => 'Woo_stamped_button_html',
						'class'    => 'button-primary woocommerce-save-button',
						'css'      => '',
						'value'    => 'Clear Reviews Cache',
						'desc'     => 'Clear all reviews cache from local database',
						'desc_tip' => true,
					),
					array(
						'type' => 'sectionend',
						'id'   => 'stamped_io_seo_extra_settings',
					),
				);
				return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
			}
		}

		public function output_sections() {
			global $current_section;

			$sections = $this->get_sections();
			if ( STMPD_API::get_public_keys() != '' && STMPD_API::get_private_keys() != '' ) {
				echo '<p>' . esc_html( __( 'Customize your review request email & manage your scheduled review requests queue', 'stampedio-product-reviews' ) ) . ": <a href='" . esc_url( 'https://go.stamped.io/v3/#/signin?passwordHash=' . STMPD_API::get_token() ) . "' target='_blank'>" . esc_html( __( 'Dashboard', 'stampedio-product-reviews' ) ) . '</a></p>';
			} else {
				echo '<p>' . esc_html( __( "Don't have an account yet? Signup for a Free Stamped.io account here: ", 'stampedio-product-reviews' ) ) . "<a href='https://www.stamped.io/account/signup' target='_blank'>https://www.stamped.io/account/signup</a></p>";
			}
			echo '<ul class="subsubsub">';
			$array_keys = array_keys( $sections );

			if ( $current_section == '' ) {
				$current_section = $this->id;
			}
			foreach ( $sections as $id => $label ) {
				echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=' . $this->id . '&section=' . sanitize_title( $id ) ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . esc_html( $label ) . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
			}

			echo '</ul><br class="clear" />';
		}
	}

	return new STMPD_admin_tab();
	endif;
