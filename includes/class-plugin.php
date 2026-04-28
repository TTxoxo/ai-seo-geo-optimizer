<?php
/**
 * Main plugin bootstrap class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class AI_SEO_GEO_Plugin {

	/**
	 * Class instance.
	 *
	 * @var AI_SEO_GEO_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin menu manager.
	 *
	 * @var AI_SEO_GEO_Admin_Menu
	 */
	private $admin_menu;

	/**
	 * Gets singleton instance.
	 *
	 * @return AI_SEO_GEO_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Registers plugin hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'init', array( $this, 'init_components' ) );
	}

	/**
	 * Initializes plugin components.
	 *
	 * @return void
	 */
	public function init_components() {
		if ( is_admin() ) {
			$this->admin_menu = new AI_SEO_GEO_Admin_Menu();
		}
	}

	/**
	 * Loads text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'ai-seo-geo-optimizer', false, dirname( plugin_basename( AI_SEO_GEO_FILE ) ) . '/languages' );
	}

	/**
	 * Handles plugin upgrades.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		$installed_version = get_option( 'ai_seo_geo_version', '' );

		if ( version_compare( (string) $installed_version, AI_SEO_GEO_VERSION, '<' ) ) {
			AI_SEO_GEO_Installer::activate();
		}
	}

	/**
	 * Enqueues admin assets.
	 *
	 * @param string $hook_suffix Admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'ai-seo-geo' ) ) {
			return;
		}

		wp_enqueue_style(
			'ai-seo-geo-admin',
			AI_SEO_GEO_URL . 'admin/assets/admin.css',
			array(),
			AI_SEO_GEO_VERSION
		);

		wp_enqueue_script(
			'ai-seo-geo-admin',
			AI_SEO_GEO_URL . 'admin/assets/admin.js',
			array(),
			AI_SEO_GEO_VERSION,
			true
		);
	}
}
