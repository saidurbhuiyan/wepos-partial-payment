<?php

namespace WePOS\PartialPayment\REST;

class PartialPaymentController extends \WP_REST_Controller {

	/**
	 * Endpoint namespace
	 *
	 * @var string
	 */
	protected $namespace = 'wepos/v1';

	/**
	 * Route name
	 *
	 * @var string
	 */
	protected $base = 'partial-payment';

	/**
	 * Register all routes related with payment
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->base . '/update', [
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'update_partial_payment' ],
			'permission_callback' => [ $this, 'partial_permissions_check' ],
			'args'                => $this->get_endpoint_args(),
		] );

	}

	/**
	 * Process payment permission callback
	 *
	 * @return \WP_Error | bool
	 *
	 */
	public function partial_permissions_check() {

		if ( ! ( current_user_can( 'manage_woocommerce' ) || apply_filters( 'wepos_rest_manager_permissions', false ) ) ) {
			return new \WP_Error( 'wepos_rest_cannot_batch', __( 'Sorry, you are not allowed view this resource.', 'wepos' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return true;
	}

	/**
	 * Get endpoint args
	 * @return array[]
	 */
	private function get_endpoint_args() {
		return [
			'order_id'       => [
				'description' => __( 'Unique identifier for the order.', 'wepos' ),
				'type'        => 'integer',
				'required'    => true,
			],
			'partial_amount' => [
				'description' => __( 'Partial payment amount.', 'wepos' ),
				'type'        => 'number',
				'required'    => true,
			],
		];
	}

	/**
	 * Update partial payment
	 *
	 * @param $request
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function update_partial_payment( $request ) {
		$order_id       = $request->get_param( 'order_id' );
		$partial_amount = $request->get_param( 'partial_amount' );

		if ( empty( $order_id ) || empty( $partial_amount ) ) {
			return new \WP_Error( 'missing_data', 'Order ID or Partial Payment amount is missing', [ 'status' => 422 ] );
		}

		// Get the order
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'invalid_order', 'Order not found', [ 'status' => 404 ] );
		}

		// Assuming you have a custom order meta for partial payments

		if ( $order->get_status() !== 'partial' ) {

			return new \WP_Error( 'invalid_order', 'Order is not allowed to pay in partial', [ 'status' => 422 ] );
		}


		insert_partial_payment_stat( $order_id, $partial_amount );

		$total_paid = get_total_paid( $order_id );
		update_post_meta( $order_id, '_wepos_cash_paid_amount', $total_paid );

		if ( $total_paid >= $order->get_total() ) {
			$order->update_status( 'completed', __( 'Partial Payment collected via cash', 'wepos' ) );
		}

		return rest_ensure_response( [
			'result'  => 'success',
			'message' => 'Partial payment updated successfully',
		] );
	}

}