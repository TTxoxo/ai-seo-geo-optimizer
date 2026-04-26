<?php
/**
 * Plugin Name: AI SEO GEO Optimizer
 * Plugin URI:  https://example.com/ai-seo-geo-optimizer
 * Description: AI powered SEO and GEO optimizer for posts, pages, and WooCommerce products with a review-first workflow.
 * Version:     0.1.0
 * Author:      AI SEO GEO Team
 * Text Domain: ai-seo-geo-optimizer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_SEO_GEO_VERSION', '0.1.0' );
define( 'AI_SEO_GEO_FILE', __FILE__ );
define( 'AI_SEO_GEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_SEO_GEO_URL', plugin_dir_url( __FILE__ ) );

require_once AI_SEO_GEO_PATH . 'includes/class-installer.php';
require_once AI_SEO_GEO_PATH . 'includes/class-security.php';
require_once AI_SEO_GEO_PATH . 'includes/class-log-manager.php';
require_once AI_SEO_GEO_PATH . 'includes/class-ai-provider-interface.php';
require_once AI_SEO_GEO_PATH . 'includes/providers/class-openai-provider.php';
require_once AI_SEO_GEO_PATH . 'includes/providers/class-deepseek-provider.php';
require_once AI_SEO_GEO_PATH . 'includes/providers/class-qwen-provider.php';
require_once AI_SEO_GEO_PATH . 'includes/providers/class-custom-provider.php';
require_once AI_SEO_GEO_PATH . 'includes/class-ai-provider-manager.php';
require_once AI_SEO_GEO_PATH . 'includes/class-content-query.php';
require_once AI_SEO_GEO_PATH . 'includes/class-admin-menu.php';
require_once AI_SEO_GEO_PATH . 'includes/class-plugin.php';

/**
 * Runs activation tasks.
 *
 * @return void
 */
function ai_seo_geo_activate_plugin() {
	AI_SEO_GEO_Installer::activate();
}

register_activation_hook( AI_SEO_GEO_FILE, 'ai_seo_geo_activate_plugin' );

/**
 * Boots the plugin.
 *
 * @return AI_SEO_GEO_Plugin
 */
function ai_seo_geo_plugin() {
	return AI_SEO_GEO_Plugin::get_instance();
}

ai_seo_geo_plugin();
