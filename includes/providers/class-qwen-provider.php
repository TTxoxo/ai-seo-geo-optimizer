<?php
/**
 * Qwen provider class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Qwen provider implementation.
 */
class AI_SEO_GEO_Qwen_Provider implements AI_SEO_GEO_AI_Provider_Interface {

	/**
	 * Gets provider key.
	 *
	 * @return string
	 */
	public function get_provider_key() {
		return 'qwen';
	}

	/**
	 * Gets provider label.
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'Qwen';
	}

	/**
	 * Gets default base URL.
	 *
	 * @return string
	 */
	public function get_default_base_url() {
		return '';
	}

	/**
	 * Gets default model.
	 *
	 * @return string
	 */
	public function get_default_model() {
		return 'qwen-plus';
	}

	/**
	 * Gets test endpoint path.
	 *
	 * @return string
	 */
	public function get_test_endpoint_path() {
		return '/chat/completions';
	}

	/**
	 * Builds test request body.
	 *
	 * @param string $model Model name.
	 *
	 * @return array
	 */
	public function build_test_request_body( $model ) {
		return array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => 'Reply with: ok',
				),
			),
			'max_tokens'  => 5,
			'temperature' => 0,
		);
	}
}
