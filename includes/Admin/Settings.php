<?php
namespace WePOS\PartialPayment\Admin;

class Settings extends \WeDevs\WePOS\Admin\Settings {
	/**
	 * Returns all the settings fields
	 *
	 * @since 1.0.0
	 *
	 * @return array settings fields
	 */
	public function get_settings_fields() {
		return wepos_get_partial_payment_settings();
	}

}