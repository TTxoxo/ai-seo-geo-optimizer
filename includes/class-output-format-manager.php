<?php
/**
 * Output format manager class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_GEO_Output_Format_Manager {
	public function get_format_options() {
		return array(
			'auto'               => __( 'Auto Detect', 'ai-seo-geo-optimizer' ),
			'clean_html'         => __( 'Clean WordPress HTML', 'ai-seo-geo-optimizer' ),
			'gutenberg_blocks'   => __( 'Gutenberg Blocks', 'ai-seo-geo-optimizer' ),
			'elementor_sections' => __( 'Elementor Copy-ready Sections', 'ai-seo-geo-optimizer' ),
			'woocommerce_html'   => __( 'WooCommerce Product HTML', 'ai-seo-geo-optimizer' ),
			'plain_text_brief'   => __( 'Plain Text Brief Only', 'ai-seo-geo-optimizer' ),
		);
	}

	public function get_format_instruction( $format ) {
		$rules = array(
			'clean_html' => "Return clean WordPress-safe HTML.\nDo not use Markdown.\nDo not wrap content in code fences.\nDo not use div, section, script, style, iframe.\nDo not use inline CSS.\nUse only: h2, h3, p, ul, ol, li, strong, em, table, thead, tbody, tr, th, td, a.\nParagraphs should be readable and not too long.",
			'gutenberg_blocks' => "Return valid WordPress Gutenberg block markup.\nUse core blocks only.\nUse wp:paragraph, wp:heading, wp:list, wp:table where appropriate.\nDo not use custom blocks.\nDo not use layout blocks unless necessary.\nEnsure all block comments are properly opened and closed.\nDo not output Markdown or code fences.",
			'elementor_sections' => "Do not return full page HTML.\nDo not attempt to recreate Elementor layout.\nReturn copy-ready sections for Elementor widgets.\nEach section must include:\n- section_name\n- recommended_widget\n- heading\n- body_html\n\nbody_html must be safe HTML for Elementor Text Editor widget.\nDo not use inline CSS.\nDo not use script, iframe, style.\nDo not assume Elementor layout structure.",
			'woocommerce_html' => "Return product content split into:\n- product_short_description\n- product_long_description\n\nproduct_short_description should be concise and conversion-focused.\nproduct_long_description can include H2/H3, bullet lists, buyer guidance, FAQ, and safe tables.",
			'plain_text_brief' => "Return concise plain_text_brief only. Do not return HTML content.",
		);
		return isset( $rules[ $format ] ) ? $rules[ $format ] : $rules['clean_html'];
	}
}
