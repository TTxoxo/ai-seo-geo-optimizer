<?php
/**
 * Installer class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles installation and upgrades.
 */
class AI_SEO_GEO_Installer {

	/**
	 * Executes activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::create_default_options();
		update_option( 'ai_seo_geo_version', AI_SEO_GEO_VERSION );
	}

	/**
	 * Creates plugin database tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$sql_providers = "CREATE TABLE {$prefix}ai_seo_providers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			provider_key VARCHAR(100) NOT NULL,
			provider_name VARCHAR(200) NOT NULL,
			base_url TEXT NOT NULL,
			api_key_encrypted TEXT NOT NULL,
			default_model VARCHAR(200) NOT NULL,
			structured_output_mode VARCHAR(20) NOT NULL DEFAULT 'auto',
			timeout INT NOT NULL DEFAULT 60,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY provider_key (provider_key),
			KEY status (status)
		) {$charset_collate};";

		$sql_jobs = "CREATE TABLE {$prefix}ai_seo_jobs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			post_type VARCHAR(50) NOT NULL,
			provider_key VARCHAR(100) NOT NULL,
			model VARCHAR(200) NOT NULL,
			status VARCHAR(50) NOT NULL,
			target_keyword TEXT NULL,
			language VARCHAR(50) NOT NULL,
			fields_json LONGTEXT NULL,
			result_json LONGTEXT NULL,
			error_message LONGTEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY status (status),
			KEY provider_key (provider_key)
		) {$charset_collate};";

		$sql_snapshots = "CREATE TABLE {$prefix}ai_seo_snapshots (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			job_id BIGINT UNSIGNED NOT NULL,
			old_title LONGTEXT NULL,
			old_content LONGTEXT NULL,
			old_excerpt LONGTEXT NULL,
			old_meta_json LONGTEXT NULL,
			old_terms_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY job_id (job_id)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$prefix}ai_seo_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id BIGINT UNSIGNED NULL,
			post_id BIGINT UNSIGNED NULL,
			action VARCHAR(100) NOT NULL,
			message LONGTEXT NULL,
			context_json LONGTEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY action (action),
			KEY post_id (post_id),
			KEY job_id (job_id)
		) {$charset_collate};";

		dbDelta( $sql_providers );
		dbDelta( $sql_jobs );
		dbDelta( $sql_snapshots );
		dbDelta( $sql_logs );
	}

	/**
	 * Creates plugin default options.
	 *
	 * @return void
	 */
	public static function create_default_options() {
		$default_settings = array(
			'default_provider_key' => '',
			'default_language'     => 'en',
			'content_types'        => array( 'post', 'page' ),
			'log_retention_days'   => 180,
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$default_settings['content_types'][] = 'product';
		}

		add_option( 'ai_seo_geo_settings', $default_settings );
	}
}
