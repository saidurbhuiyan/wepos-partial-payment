<?php

/**
 * Insert partial payment stat
 * @param $order_id
 * @param $paid
 *
 * @return int|WP_Error
 */
function insert_partial_payment_stat($order_id, $paid) {
	global $wpdb;
	$table_name = $wpdb->prefix . PARTIAL_PAYMENT_TABLE;

	$result = $wpdb->insert(
		$table_name,
		array(
			'order_id' => $order_id,
			'paid' => $paid,
			'date_created' => current_time('mysql'),
		),
		array(
			'%d',
			'%f',
			'%s',
		)
	);

	if ($result === false) {
		return new WP_Error('db_insert_error', 'Could not insert data into the database', $wpdb->last_error);
	}

	return $wpdb->insert_id;
}

/**
 * Get partial payment stats
 * @param $order_id
 *
 * @return array|WP_Error
 */
function get_partial_payment_stats($order_id, $orderBy = 'ID', $order = 'DESC') {
	global $wpdb;
	$table_name = $wpdb->prefix . PARTIAL_PAYMENT_TABLE;

	$allowed_columns = array('ID', 'paid', 'date_created');
	$orderBy = in_array( $orderBy, $allowed_columns, true ) ? $orderBy : 'ID';
	$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

	$query = $wpdb->prepare("SELECT * FROM $table_name WHERE order_id = %d ORDER BY $orderBy $order", $order_id);
	$results = $wpdb->get_results($query);

	if ($results === false) {
		return new WP_Error('db_query_error', 'Could not retrieve data from the database', $wpdb->last_error);
	}

	return $results;
}


/**
 * Get total paid
 * @param $order_id
 *
 * @return string|WP_Error|null
 */
function get_total_paid($order_id) {
	global $wpdb;
	$table_name = $wpdb->prefix . PARTIAL_PAYMENT_TABLE;

	$query = $wpdb->prepare("SELECT SUM(paid) as total_paid FROM $table_name WHERE order_id = %d", $order_id);
	$result = $wpdb->get_var($query);

	if ($result === false) {
		return new WP_Error('db_query_error', 'Could not retrieve data from the database', $wpdb->last_error);
	}

	return $result ?? '0';
}

/**
 * Get due amount
 * @param $order_id
 * @param $total_amount
 *
 * @return int|string|WP_Error|null
 */
function get_due_amount($order_id, $total_amount) {
	$total_paid = get_total_paid($order_id);

	if (is_wp_error($total_paid)) {
		return $total_paid;
	}

	$due_amount = $total_amount - $total_paid;

	return $due_amount < 0 ? 0 : $due_amount;
}