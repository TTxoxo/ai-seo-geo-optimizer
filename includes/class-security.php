<?php
/**
 * Security helper class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security helpers.
 */
class AI_SEO_GEO_Security {

	/**
	 * Blocks access for users without manage_options capability.
	 *
	 * @return void
	 */
	public static function require_manage_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-geo-optimizer' ) );
		}
	}

	/**
	 * Verifies nonce and exits on failure.
	 *
	 * @param string $action Nonce action.
	 * @param string $name   Nonce field name.
	 *
	 * @return void
	 */
	public static function verify_nonce_or_die( $action, $name = '_wpnonce' ) {
		check_admin_referer( $action, $name );
	}

	/**
	 * Sanitizes text field.
	 *
	 * @param string $value Input value.
	 *
	 * @return string
	 */
	public static function sanitize_text( $value ) {
		return sanitize_text_field( wp_unslash( $value ) );
	}

	/**
	 * Sanitizes textarea field.
	 *
	 * @param string $value Input value.
	 *
	 * @return string
	 */
	public static function sanitize_textarea( $value ) {
		return sanitize_textarea_field( wp_unslash( $value ) );
	}

	/**
	 * Sanitizes URL field.
	 *
	 * @param string $value Input value.
	 *
	 * @return string
	 */
	public static function sanitize_url( $value ) {
		return esc_url_raw( wp_unslash( $value ) );
	}
}
