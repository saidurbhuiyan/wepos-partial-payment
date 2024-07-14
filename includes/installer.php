<?php

/**
 * Install the plugin
 * @return void
 */
function wepos_partial_payment_install() {
	global $wpdb;
	$table_name = $wpdb->prefix . PARTIAL_PAYMENT_TABLE;
	$charset_collate = $wpdb->get_charset_collate();

	if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE $table_name (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            paid double NOT NULL DEFAULT '0',
            date_created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (ID),
            KEY order_id (order_id)
        ) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
}

register_activation_hook(PARTIAL_FILE, 'wepos_partial_payment_install');
