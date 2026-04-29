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
	 * Gets provider name.
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
	 * Generates completion data.
	 *
	 * @param array $messages Chat messages.
	 * @param array $options  Request options.
	 *
	 * @return array
	 */
	public function generate( $messages, $options = array() );

	/**
	 * Runs provider connection test.
	 *
	 * @return array
	 */
	public function test_connection();
}
