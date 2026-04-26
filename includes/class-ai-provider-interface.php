<?php
/**
 * AI Provider interface.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines required methods for all AI providers.
 */
interface AI_SEO_GEO_AI_Provider_Interface {

	/**
	 * Gets provider key.
	 *
	 * @return string
	 */
	public function get_provider_key();

	/**
	 * Gets provider label.
	 *
	 * @return string
	 */
	public function get_provider_name();

	/**
	 * Gets provider default base URL.
	 *
	 * @return string
	 */
	public function get_default_base_url();

	/**
	 * Gets provider default model.
	 *
	 * @return string
	 */
	public function get_default_model();

	/**
	 * Gets test endpoint path.
	 *
	 * @return string
	 */
	public function get_test_endpoint_path();

	/**
	 * Builds test request body.
	 *
	 * @param string $model Model name.
	 *
	 * @return array
	 */
	public function build_test_request_body( $model );
}
