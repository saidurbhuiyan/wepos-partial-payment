<?php

namespace WePOS\PartialPayment\REST;

class SettingController extends \WeDevs\WePOS\REST\SettingController {
	/**
	 * Get settings
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 *
	 */
	public function get_settings( $request ) {
		$settings = [];
		foreach ( wepos_get_partial_payment_settings() as $section_key => $settings_options ) {
			$section_option = get_option( $section_key, [] );
			foreach ( $settings_options as $settings_key => $settings_value ) {
				$settings[$section_key][$settings_key] = isset( $section_option[$settings_key] ) ? $section_option[$settings_key] : $settings_options[$settings_key]['default'];
			}
		}

		$tax_display_on_shop = get_option( 'woocommerce_tax_display_shop', 'excl' );
		$tax_display_on_cart = get_option( 'woocommerce_tax_display_cart', 'excl' );
		$settings['woo_tax'] = [
			'wc_tax_display_shop' => $tax_display_on_shop,
			'wc_tax_display_cart' => $tax_display_on_cart,
		];

		return rest_ensure_response( $settings );
	}
}