<?php
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
		]
	];

	return apply_filters( 'wepos_settings_fields', $settings_fields );
}
