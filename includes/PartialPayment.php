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


		add_action('woocommerce_order_details_after_order_table_items', [$this, 'due_amount_on_order_details']);
		add_action('woocommerce_admin_order_totals_after_tax', [$this, 'due_amount_on_order_details']);
		add_action( 'woocommerce_order_details_after_order_table', [$this,'add_partial_payment_input_field']);

		add_action( 'wp_enqueue_scripts', [$this,'wepos_partial_payment_enqueue_scripts'] );
        add_action('admin_enqueue_scripts', [$this,'wepos_partial_payment_enqueue_scripts']);
		
		add_action( 'woocommerce_order_details_after_order_table', [$this,'order_partial_payment_stats'], 11 );

        add_filter( 'woocommerce_my_account_my_orders_query', [$this,'customize_my_account_orders_query'] );
        add_action('init', [$this,'add_view_order_capability'], 11);
		add_action('add_meta_boxes', [$this,'add_partial_payment_meta_box']);
		add_filter( 'woocommerce_reports_order_statuses', [$this,'append_partial_order_post_status'], 20, 1 );
	}


	/**
	 * Register the partial payment meta box
	 */
	public function add_partial_payment_meta_box() {
		global $post;

		if (!is_current_user_admin() || (is_admin() && (get_current_screen()->base !== 'post' || get_current_screen()->post_type !== 'shop_order'))) {
			return;
		}

		$order = wc_get_order($post->ID);

		if ($order && $order->get_status() === 'partial') {
	        add_meta_box(
		        'partial_payment_meta_box',
		        'Pay The Due Amount',
		        array($this,'add_partial_payment_input_field'),
		        'shop_order',
		        'side',
	        );
        }

        add_meta_box(
            'partial_payment_stats_meta_box',
            'Order Partial Payment Statistics',
            array($this,'order_partial_payment_stats'),
            'shop_order',
            'normal',
        );
	}

    /**
     * Add order partial payment stats
     * @param $order
     * @return void
     */
	public function order_partial_payment_stats( $order ) {
		$order = wc_get_order( $order );
		$order_id = $order->get_id();
		$partial_payment_stats = get_partial_payment_stats( $order_id );
		$total_paid = get_total_paid($order_id);
		$total_amount = $order->get_total();
		?>
        <fieldset>
			<?php echo !is_admin() ? '<legend>Order Partial Payment Statistics</legend>' : ''; ?>
            <table class="wp-list-table widefat fixed striped partial-payment-stats">
                <thead>
                <tr>
                    <th class="manage-column column-id"><?php esc_html_e( 'ID', 'wepos' ); ?></th>
                    <th class="manage-column column-paid-amount"><?php esc_html_e( 'Paid Amount', 'wepos' ); ?></th>
                    <th class="manage-column column-total-due"><?php esc_html_e( 'Total Due', 'wepos' ); ?></th>
                    <th class="manage-column column-created-date"><?php esc_html_e( 'Created Date', 'wepos' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php if ( ! empty( $partial_payment_stats ) ) : ?>
					<?php foreach ( $partial_payment_stats as $stat ) :
						$due = $total_amount - $total_paid;
						?>
                        <tr>
                            <td class="column-id"><?php echo esc_html( $stat->ID ); ?></td>
                            <td class="column-paid-amount"><?php echo wc_price( $stat->paid ); ?></td>
                            <td class="column-total-due"><?php echo wc_price( $due > 0 ? $due : 0 ); ?></td>
                            <td class="column-created-date"><?php echo esc_html( date_i18n( get_option( 'date_format' )?? 'Y-m-d', strtotime( $stat->date_created ) ) ); ?></td>
                        </tr>
						<?php $total_paid -= $stat->paid; ?>
					<?php endforeach; ?>
				<?php else : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e( 'No partial payment records found.', 'wepos' ); ?></td>
                    </tr>
				<?php endif; ?>
                </tbody>
            </table>
        </fieldset>
		<?php
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
		if(!is_array($statuses)){
			return array('completed', 'processing', 'on-hold', 'partial');
		}
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
			'label' => _x('Partially Paid', 'Order status', 'woocommerce'),
			'public' => true,
			'exclude_from_search' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop('Partially Paid <span class="count">(%s)</span>', 'Partially Paid <span class="count">(%s)</span>'),
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
				$new_statuses['wc-partial'] = _x('Partially Paid', 'Order status', 'woocommerce');
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
		$bulk_actions['mark_partial_payment'] = 'Change Status to Partially Paid';
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
	public function add_partial_payment_input_field($order) {
	$order = wc_get_order($order);
	// Check if the order exists and is in 'partial' status
	if (!$order || $order->get_status() !== 'partial') {
		return;
	}

// Check if the current user is an admin
if (!current_user_can('manage_woocommerce')) {
	return;
}

// Get order ID
$order_id = $order->get_id();

// Calculate due amount
$paid = (int)get_total_paid($order_id); // Ensure this function exists and returns the correct amount
$due = $order->get_total() - $paid;

// Output the input field
echo '<div id="woocommerce-form-partial-payment">
        <fieldset>
        '.(!is_admin()? "<legend>Pay The Due Amount</legend>" : "").'
          <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="partial_payment_amount">Partial Payment Amount</label>
                <input class="woocommerce-Input woocommerce-Input--number input-number" type="number" step="0.01" min="1" max="' . esc_attr($due) . '" id="partial_payment_amount" name="partial_amount" value="' . esc_attr($due) . '" />
            </p>
            <p class="form-row form-row-wide">
                <input type="hidden" id="partial_payment_id" name="order_id" value="' . esc_attr($order_id) . '" />
                <input type="hidden" id="partial-payment-nonce" name="partial-payment-nonce" value="' . esc_attr(wp_create_nonce('wp_rest')) . '">
                <button id="update_partial_payment_button" class="woocommerce-Button button">Process Payment</button>
            </p>
        </fieldset>
    </div>';
}

	/**
	 * Enqueue partial payment script
	 * @return void
	 */
	public function wepos_partial_payment_enqueue_scripts() {

		if (!is_current_user_admin() || (is_admin() && (get_current_screen()->base !== 'post' || get_current_screen()->post_type !== 'shop_order'))) {
			return;
		}

		// Enqueue your script
		wp_enqueue_script( 'wepos-partial-payment-script', plugins_url( '../assets/js/partial-payment.js', __FILE__ ), array( 'jquery' ), null, true );

		wp_localize_script('wepos-partial-payment-script', 'restApiSettings', array(
			'restUrl' => esc_url_raw(rest_url('wepos/v1/partial-payment/update')),
			'nonce'   => wp_create_nonce('wp_rest'),
		));

	}


    /**
     * If the user is an admin, remove the customer parameter to get all orders
     * @param $query_args
     * @return mixed
     */
    public function customize_my_account_orders_query( $query_args ) {
        // Check if the current user is an admin
        if ( is_current_user_admin()) {
            unset( $query_args['customer'] );
        } else {
            $query_args['customer'] = get_current_user_id();
        }

        return $query_args;
    }

    /**
     * Add view order capability to administrator
     * @return void
     */
    public function add_view_order_capability()
    {
        $role = get_role('administrator');
        $role->add_cap('view_order', true);
    }



}
