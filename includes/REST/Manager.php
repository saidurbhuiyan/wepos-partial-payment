<?php

namespace WePOS\PartialPayment\REST;

class Manager extends \WeDevs\WePOS\REST\Manager {
	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		foreach ( $this->class_map as $file_name => $controller ) {
			if ( !in_array($controller, array('\WeDevs\WePOS\REST\PaymentController', '\WeDevs\WePOS\REST\SettingController')) ) {
				require_once $file_name;
				$controller = new $controller();
				$controller->register_routes();
			}

		}

		// Register modified payment and setting routes
		$controller = new PaymentController();
		$controller->register_routes();

		$controller = new SettingController();
		$controller->register_routes();

		// Register partial payment routes
		$controller = new PartialPaymentController();
		$controller->register_routes();
	}

}