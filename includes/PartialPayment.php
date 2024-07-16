<?php

namespace WePOS\PartialPayment;

/**
 * Partial Payment class
 */
class PartialPayment {

	public function __construct() {

		$this->init_hooks();
	}

	/**
	 * Init hooks method.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Manipulate WooCommerce Order Data.
		add_filter('manage_edit-shop_order_columns', [$this, 'add_order_due_amount_column']);
		add_action('manage_shop_order_posts_custom_column', [$this, 'render_order_due_amount_content'], 10, 2);
		add_action('admin_print_styles', [$this, 'add_order_due_amount_column_style']);
		add_action('init', [$this, 'register_partial_payment_order_status']);

		add_filter('wc_order_statuses', [$this, 'add_partial_payment_to_order_statuses']);
		add_filter('bulk_actions-edit-shop_order', [$this, 'add_partial_payment_bulk_action'], 20, 1);
		add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_partial_payment_bulk_action'], 10, 3);
		add_action('admin_notices', [$this, 'partial_payment_bulk_action_admin_notice']);
		add_action('admin_head', [$this, 'add_partial_payment_status_styles']);
		add_filter('woocommerce_admin_order_actions', [$this, 'add_actions_button_on_partial_order_status'], 100, 2);

		add_filter('woocommerce_analytics_excluded_order_statuses', [$this, 'append_partial_order_post_status']);
		add_filter('woocommerce_valid_order_statuses_for_payment_complete', [$this, 'append_partial_order_post_status']);
		add_action('woocommerce_order_details_after_order_table_items', [$this, 'due_amount_on_order_details']);
		add_action('woocommerce_admin_order_totals_after_tax', [$this, 'due_amount_on_order_details']);
		add_action( 'woocommerce_order_details_after_order_table', [$this,'add_partial_payment_input_field']);
		add_action( 'wp_enqueue_scripts', [$this,'wepos_partial_payment_enqueue_scripts'] );
		
		add_action( 'woocommerce_order_details_after_customer_details', [$this,'order_partial_payment_stats'] );

	}


	public function order_partial_payment_stats( $order ) {
		$order_id = $order->get_id();
		$partial_payment_stats = get_partial_payment_stats( $order_id );
        $total_paid = get_total_paid($order_id);
        $total_amount = $order->get_total();
        ?>
        <fieldset>
            <legend>Order Partial Payment Statistics</legend>
			<table class="woocommerce-table shop_table partial-payment-stats">
				<thead>
				<tr>
					<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'ID', 'wepos' ); ?></th>
					<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Paid Amount', 'wepos' ); ?></th>
					<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Total Due', 'wepos' ); ?></th>
					<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Created Date', 'wepos' ); ?></th>
				</tr>
				</thead>
				<tbody>
		<?php
		if ( ! empty( $partial_payment_stats ) ) {
			?>

				<?php foreach ( $partial_payment_stats as $stat ) :
                $due = $total_amount - $total_paid;
                ?>
					<tr>
						<td class="woocommerce-table__product-name product-name"><?php echo esc_html( $stat->ID ); ?></td>
						<td class="woocommerce-table__product-name product-name"><?php echo wc_price( $stat->paid ); ?></td>
						<td class="woocommerce-table__product-name product-name"><?php echo wc_price( $due > 0 ? $due : 0 ); ?></td>
						<td class="woocommerce-table__product-name product-name"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $stat->date_created ) ) ); ?></td>
					</tr>
				<?php
                $total_paid -= $stat->paid;
            endforeach; ?>

			<?php
		}

        echo '</tbody></table></fieldset>';
	}



	/**
	 * Add order due amount, paid amount in order details
	 * @param $orderData
	 *
	 * @return void
	 */
	public function due_amount_on_order_details($orderData) {
		$order = wc_get_order($orderData);
		$paid = (int)get_total_paid($order->get_id());

		$due = $order->get_total() - $paid;

		$html = '<tfoot>';
		if ($due) {
			$html .= '<tr><th class="label" scope="row">Due:</th>' . (is_int($orderData) ? '<td width="1%"></td>' : '') . '<td class="total">' . wc_price($due) . '</td></tr>';
		}

		if ($paid) {
			$html .= '<tr><th class="label" scope="row">Paid:</th>' . (is_int($orderData) ? '<td width="1%"></td>' : '') . '<td class="total">' . wc_price($paid) . '</td></tr>';
		}

		$html .= '</tfoot>';
		echo $html;
	}

	/**
	 * Append partial order post status
	 * @param $statuses
	 *
	 * @return mixed
	 */
	public function append_partial_order_post_status($statuses) {
		$statuses[] = 'partial';
		return $statuses;
	}

	/**
	 * Add actions button on partial order status
	 * @param $actions
	 * @param $order
	 *
	 * @return mixed
	 */
	public function add_actions_button_on_partial_order_status($actions, $order) {
		if ($order->has_status('partial')) {
			$actions['processing'] = [
				'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woocommerce_mark_order_status&status=processing&order_id=' . $order->get_id()), 'woocommerce-mark-order-status'),
				'name' => __('Processing', 'woocommerce'),
				'action' => 'processing',
			];

			$actions['complete'] = [
				'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $order->get_id()), 'woocommerce-mark-order-status'),
				'name' => __('Complete', 'woocommerce'),
				'action' => 'complete',
			];
		}

		return $actions;
	}

	/**
	 * Add order due column in order list
	 * @param $defaults
	 *
	 * @return array
	 */
	public function add_order_due_amount_column($defaults) {
		$columns = [];
		foreach ($defaults as $column_name => $column_info) {
			$columns[$column_name] = $column_info;
			if ('order_total' === $column_name) {
				$columns['order_due_amount'] = apply_filters('wepos_order_due_amount_column_title', __('Due Amount', 'partial-payment'));
			}
		}

		return $columns;
	}

	/**
	 * Render order due amount in order list
	 * @param $column_name
	 * @param $post_id
	 *
	 * @return void
	 */
	public function render_order_due_amount_content($column_name, $post_id) {
		if ('order_due_amount' === $column_name) {
			$order = wc_get_order($post_id);
			$paid = (int)get_total_paid($order->get_id());
			$due = $order->get_total() - $paid;
			$currency = is_callable([$order, 'get_currency']) ? $order->get_currency() : $order->order_currency;

			echo wc_price($due, ['currency' => $currency]);
		}
	}

	/**
	 * Add style for order due amount column
	 * @return void
	 */
	public function add_order_due_amount_column_style() {
		$css = '.widefat .column-order_due_amount, .widefat .column-order_due_amount { width: 9%; text-align: center; }';
		wp_add_inline_style('woocommerce_admin_styles', $css);
	}

	/**
	 * Register partial payment order status in wooCommerce order status
	 * @return void
	 */
	public function register_partial_payment_order_status() {
		register_post_status('wc-partial', [
			'label' => _x('Partial Payment', 'Order status', 'woocommerce'),
			'public' => true,
			'exclude_from_search' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop('Partial Payment <span class="count">(%s)</span>', 'Partial Payment <span class="count">(%s)</span>'),
		]);
	}

	/**
	 * Add partial payment to order statuses
	 * @param $order_statuses
	 *
	 * @return array
	 */
	public function add_partial_payment_to_order_statuses($order_statuses) {
		$new_statuses = [];
		foreach ($order_statuses as $id => $label) {
			if ('wc-completed' === $id) {
				$new_statuses['wc-partial'] = _x('Partial Payment', 'Order status', 'woocommerce');
			}

			$new_statuses[$id] = $label;
		}

		return $new_statuses;
	}

	/**
	 * Add style for partial payment order status
	 * @return void
	 */
	public function add_partial_payment_status_styles() {
		echo '<style>
            .order-status.status-partial {
                background: #ffeb3b;
                color: #000;
            }
        </style>';
	}

	/**
	 * Add partial payment bulk action
	 * @param $bulk_actions
	 *
	 * @return mixed
	 */
	public function add_partial_payment_bulk_action($bulk_actions) {
		$bulk_actions['mark_partial_payment'] = 'Change Status to partial payment';
		return $bulk_actions;
	}

	/**
	 * Handle partial payment bulk action
	 * @param $redirect_to
	 * @param $do_action
	 * @param $post_ids
	 *
	 * @return mixed|string
	 */
	public function handle_partial_payment_bulk_action($redirect_to, $do_action, $post_ids) {
		if ('mark_partial_payment' !== $do_action) {
			return $redirect_to;
		}

		foreach ($post_ids as $post_id) {
			$order = wc_get_order($post_id);
			$order->update_status('partial');
		}

		return add_query_arg('bulk_mark_partial_payment', count($post_ids), $redirect_to);
	}

	/**
	 * Partial payment bulk action on admin notice
	 * @return void
	 */
	public function partial_payment_bulk_action_admin_notice() {
		if (!empty($_REQUEST['bulk_mark_partial_payment'])) {
			$count = (int)$_REQUEST['bulk_mark_partial_payment'];
			printf(
				'<div id="message" class="updated fade">' .
				_n('%s order status changed to Partial Payment.', '%s order statuses changed to Partial Payment.', $count, 'woocommerce') . '</div>',
				$count
			);
		}
	}

	/**
	 * Add partial payment input field
	 * @param $order
	 *
	 * @return void
	 */
	public function add_partial_payment_input_field( $order ) {
		// Get order ID
		$order_id = $order->get_id();
		if ($order->get_status() !== 'partial') {
			return;
		}
		$paid = (int)get_total_paid($order_id);
		$due = $order->get_total() - $paid;
		// Output the input field
		echo  '<form method="post" class="woocommerce-form woocommerce-form-partial-payment">
        <fieldset>
        <legend>Pay Your Rest Payment</legend>
          <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
             
                <label for="partial_payment_amount">Partial Payment Amount</label>
                <input class="woocommerce-Input woocommerce-Input--number input-number" type="number" step="0.01" min="1" max="'.$due.'" id="partial_payment_amount" name="partial_amount" value="'.$due.'" />
            </p>
            
            <p class="form-row form-row-wide">
            <input type="hidden" id="partial_payment_id" name="order_id" value="'.$order_id.'" />
            <input type="hidden" id="partial-payment-nonce" name="partial-payment-nonce" value="'.wp_create_nonce('wp_rest').'">
                <button type="submit" id="update_partial_payment_button" class="woocommerce-Button button">Process Payment</button>
            </p>
</fieldset>
  </form>';
	}

	/**
	 * Enqueue partial payment script
	 * @return void
	 */
	public function wepos_partial_payment_enqueue_scripts() {

		// Enqueue your script
		wp_enqueue_script( 'wepos-partial-payment-script', plugins_url( '../assets/js/partial-payment.js', __FILE__ ), array( 'jquery' ), null, true );

		wp_localize_script('wepos-partial-payment-script', 'restApiSettings', array(
			'restUrl' => esc_url_raw(rest_url('wepos/v1/partial-payment/update')),
			'nonce'   => wp_create_nonce('wp_rest'),
		));

	}
}
