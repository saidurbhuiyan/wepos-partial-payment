<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Get partial payment settings
 * @return mixed
 */
function wepos_get_partial_payment_settings() {
	if (!function_exists('wepos_get_settings_fields')) {
		require_once WP_PLUGIN_DIR . '/wepos/includes/functions.php';
	}

	$settings_fields = wepos_get_settings_fields();
	$settings_fields['wepos_general']['enable_partial_payment'] = [
		'name'    => 'enable_partial_payment',
		'label'   => __( 'Enable Partial Payment', 'wepos' ),
		'desc'    => __( 'Choose if partial payment is allowed in POS cart and checkout', 'wepos' ),
		'type'    => 'select',
		'default' => 'yes',
		'options' => [
			'yes' => __( 'Yes', 'wepos' ),
			'no'  => __( 'No', 'wepos' ),
		],
	];

	return apply_filters( 'wepos_settings_fields', $settings_fields );
}

/**
 * Check if current user is an admin
 * @return bool
 */
function is_current_user_admin ()
{
    return current_user_can( 'manage_woocommerce' ) && current_user_can( 'administrator' );
}


/**
 * Check if HPOS is enabled
 * @return bool
 */
function is_hpos_enabled() {
	return class_exists(CustomOrdersTableController::class) &&
	       wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
}

/**
 * Get HPOS Screen ID
 * @return string
 */
function admin_shop_order_screen() {
	$screen = 'shop_order';
	if (is_hpos_enabled()) {
		$screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'woocommerce_page_wc-orders';  // Traditional Screen ID
	}
	return $screen;
}

/**
 * Get HPOS Hook Names from legacy hook
 * @param $hook_name
 *
 * @return string
 */
function get_hpos_hook_names($hook_name) {
	$hpos_hook_names = [
		'manage_edit-shop_order_columns' => 'manage_woocommerce_page_wc-orders_columns',
		'manage_shop_order_posts_custom_column' => 'manage_woocommerce_page_wc-orders_custom_column',
		'bulk_actions-edit-shop_order' => 'bulk_actions-woocommerce_page_wc-orders',
		'handle_bulk_actions-edit-shop_order' => 'handle_bulk_actions-woocommerce_page_wc-orders',
	];

	return is_hpos_enabled() && isset($hpos_hook_names[$hook_name]) ? $hpos_hook_names[$hook_name] : $hook_name;
}

