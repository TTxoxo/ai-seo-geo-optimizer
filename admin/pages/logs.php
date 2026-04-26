<?php
/**
 * Logs page.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-geo-optimizer' ) );
}
?>
<div class="wrap ai-seo-geo-wrap">
	<h1><?php esc_html_e( 'Logs', 'ai-seo-geo-optimizer' ); ?></h1>
	<p><?php esc_html_e( 'Optimization and audit logs will be shown here.', 'ai-seo-geo-optimizer' ); ?></p>
</div>
