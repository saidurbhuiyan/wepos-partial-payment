<?php
/**
 * Plugin Name: WePOS Partial Payment
 * Description: Add partial payment functionality to WePOS plugin for WooCommerce.
 * Version: 1.0
 * Author: saidur
 * Text Domain: partial-payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'PARTIAL_FILE', __FILE__ );
define( 'PARTIAL_PATH', dirname( PARTIAL_FILE ) );
define( 'PARTIAL_INCLUDES', PARTIAL_PATH . '/includes' );
define('PARTIAL_PAYMENT_TABLE', 'wepos_order_partial_payment_stats');


// Autoload the classes (if using Composer's autoloader)
if ( file_exists( PARTIAL_PATH . '/vendor/autoload.php' ) ) {
	require PARTIAL_PATH . '/vendor/autoload.php';
}

/**
 * Check if the required plugins are installed and active
 * @return bool
 */
function check_required_plugins() {
	if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! is_plugin_active( 'wepos/wepos.php' ) || !class_exists( '\WeDevs\WePOS\REST\Manager' ) || !class_exists('\WeDevs\WePOS\Admin\Settings')) {
		// Deactivate the plugin.
		deactivate_plugins( plugin_basename( __FILE__ ) );

		// Display an admin notice.
		add_action( 'admin_notices', static function() {
			echo '<div class="error"><p>';
			_e( 'WooCommerce Partial Payment requires WooCommerce and WePOS plugins to be installed and active.', 'partial-payment' );
			echo '</p></div>';
		});

		// Prevent further execution.
		return false;
	}

	return true;
}

// Hook the check into the 'admin_init' action.
add_action( 'admin_init', 'check_required_plugins' );

// Include required files
require_once PARTIAL_INCLUDES . '/installer.php';
require_once PARTIAL_INCLUDES . '/db-functions.php';
require_once PARTIAL_INCLUDES . '/functions.php';

/**
 * Initialize the plugin
 * @return void
 */
function init_partial_payment() {
	new \WePOS\PartialPayment\PartialPayment();
	if (class_exists('\WeDevs\WePOS\REST\Manager')) {
		new \WePOS\PartialPayment\REST\Manager();
	}

}

add_action( 'plugins_loaded', 'init_partial_payment' );

function init_classes() {
	if (is_admin() && class_exists('\WeDevs\WePOS\Admin\Settings')) {
		new \WePOS\PartialPayment\Admin\Settings();
	}
}

add_action( 'init', 'init_classes', 11 );
