<?php
/**
 * Dashboard page.
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
	<h1><?php esc_html_e( 'AI SEO Optimizer - Dashboard', 'ai-seo-geo-optimizer' ); ?></h1>
	<p><?php esc_html_e( 'Welcome to AI SEO GEO Optimizer. Plugin foundation is active.', 'ai-seo-geo-optimizer' ); ?></p>
</div>
