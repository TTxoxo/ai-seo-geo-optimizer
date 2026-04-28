<?php
/**
 * Admin menu class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders admin menu pages.
 */
class AI_SEO_GEO_Admin_Menu {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
	}

	/**
	 * Registers plugin admin menus.
	 *
	 * @return void
	 */
	public function register_menus() {
		$capability = 'manage_options';
		$slug       = 'ai-seo-geo-dashboard';

		add_menu_page(
			__( 'AI SEO Optimizer', 'ai-seo-geo-optimizer' ),
			__( 'AI SEO Optimizer', 'ai-seo-geo-optimizer' ),
			$capability,
			$slug,
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-area',
			56
		);

		add_submenu_page(
			$slug,
			__( 'Dashboard', 'ai-seo-geo-optimizer' ),
			__( 'Dashboard', 'ai-seo-geo-optimizer' ),
			$capability,
			$slug,
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			$slug,
			__( 'Content Optimizer', 'ai-seo-geo-optimizer' ),
			__( 'Content Optimizer', 'ai-seo-geo-optimizer' ),
			$capability,
			'ai-seo-geo-content',
			array( $this, 'render_content_list_page' )
		);

		add_submenu_page(
			$slug,
			__( 'AI Providers', 'ai-seo-geo-optimizer' ),
			__( 'AI Providers', 'ai-seo-geo-optimizer' ),
			$capability,
			'ai-seo-geo-providers',
			array( $this, 'render_providers_page' )
		);

		add_submenu_page(
			$slug,
			__( 'Prompt Templates', 'ai-seo-geo-optimizer' ),
			__( 'Prompt Templates', 'ai-seo-geo-optimizer' ),
			$capability,
			'ai-seo-geo-prompts',
			array( $this, 'render_prompt_templates_page' )
		);

		add_submenu_page(
			null,
			__( 'Review & Apply', 'ai-seo-geo-optimizer' ),
			__( 'Review & Apply', 'ai-seo-geo-optimizer' ),
			$capability,
			'ai-seo-geo-review-apply',
			array( $this, 'render_review_apply_page' )
		);

		add_submenu_page(
			$slug,
			__( 'Logs', 'ai-seo-geo-optimizer' ),
			__( 'Logs', 'ai-seo-geo-optimizer' ),
			$capability,
			'ai-seo-geo-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Renders dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		AI_SEO_GEO_Security::require_manage_options();
		require AI_SEO_GEO_PATH . 'admin/pages/dashboard.php';
	}

	/**
	 * Renders content list page.
	 *
	 * @return void
	 */
	public function render_content_list_page() {
		AI_SEO_GEO_Security::require_manage_options();
		require AI_SEO_GEO_PATH . 'admin/pages/content-list.php';
	}

	/**
	 * Renders providers page.
	 *
	 * @return void
	 */
	public function render_providers_page() {
		AI_SEO_GEO_Security::require_manage_options();
		require AI_SEO_GEO_PATH . 'admin/pages/providers.php';
	}

	/**
	 * Renders prompt templates page.
	 *
	 * @return void
	 */
	public function render_prompt_templates_page() {
		AI_SEO_GEO_Security::require_manage_options();
		require AI_SEO_GEO_PATH . 'admin/pages/prompt-templates.php';
	}

	/**
	 * Renders logs page.
	 *
	 * @return void
	 */
	public function render_logs_page() {
		AI_SEO_GEO_Security::require_manage_options();
		require AI_SEO_GEO_PATH . 'admin/pages/logs.php';
	}

	/**
	 * Renders review/apply page.
	 *
	 * @return void
	 */
	public function render_review_apply_page() {
		AI_SEO_GEO_Security::require_manage_options();
		require AI_SEO_GEO_PATH . 'admin/pages/review-apply.php';
	}
}
