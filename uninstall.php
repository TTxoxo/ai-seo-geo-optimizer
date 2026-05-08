<?php
/**
 * Uninstall script.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$delete_all_data = get_option( 'ai_seo_geo_delete_data_on_uninstall', 0 );

delete_option( 'ai_seo_geo_version' );
delete_option( 'ai_seo_geo_settings' );

if ( (int) $delete_all_data !== 1 ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'ai_seo_providers',
	$wpdb->prefix . 'ai_seo_jobs',
	$wpdb->prefix . 'ai_seo_snapshots',
	$wpdb->prefix . 'ai_seo_logs',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
