<?php

namespace WePOS\PartialPayment\REST;

class PaymentController extends \WeDevs\WePOS\REST\PaymentController {

	/**
	 * Return calculate order data
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function process_payment( $request ) {
		$available_gateways = wepos()->gateways->available_gateway();
		$chosen_gateway = '';

		if ( empty( $request['id'] ) ) {
			return new \WP_Error( 'no-order-id', __( 'No order found', 'wepos' ), [ 'status' => 401 ] );
		}

		foreach ( $available_gateways as $class => $path ) {
			$gateway = new $class;

			if ( $gateway->id == $request['payment_method'] ) {
				$chosen_gateway = $gateway;
			}
		}

		if ( empty( $chosen_gateway->id ) ) {
			return new \WP_Error( 'no-payment-gateway', __( 'No payment gateway found for processing this payment', 'wepos' ), [ 'status' => 401 ] );
		}

		// partial payment
		$order = wc_get_order( $request['id'] );
		insert_partial_payment_stat($request['id'], $order->get_meta('_wepos_cash_paid_amount'));
		if ($order->get_meta('_wepos_cash_payment_type') === 'partial') {;
			$order->update_status( 'partial', __( 'Partial Payment collected via cash', 'wepos' ) );

			return rest_ensure_response(array(
				'result'   => 'success',
			));
		}

		$process_payment = $chosen_gateway->process_payment( $request['id'] );
		return rest_ensure_response( $process_payment );
	}

}