<?php
/**
 * Providers page.
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
	<h1><?php esc_html_e( 'AI Providers', 'ai-seo-geo-optimizer' ); ?></h1>
	<p><?php esc_html_e( 'Configure OpenAI-compatible providers here.', 'ai-seo-geo-optimizer' ); ?></p>
</div>
