<?php
/**
 * Content optimizer page.
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
	<h1><?php esc_html_e( 'Content Optimizer', 'ai-seo-geo-optimizer' ); ?></h1>
	<p><?php esc_html_e( 'This page will list posts, pages, and products for SEO/GEO optimization.', 'ai-seo-geo-optimizer' ); ?></p>
</div>
