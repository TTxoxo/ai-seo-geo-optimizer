<?php
/**
 * Editor detector class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_GEO_Editor_Detector {
	public function detect_editor_type( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post ) {
			return 'unknown';
		}

		if ( $this->is_woocommerce_product( $post_id ) ) {
			return 'woocommerce_product';
		}

		if ( $this->is_elementor_page( $post_id ) ) {
			return 'elementor';
		}

		if ( $this->is_gutenberg_content( (string) $post->post_content ) ) {
			return 'gutenberg';
		}

		return 'classic';
	}

	public function is_gutenberg_content( $post_content ) {
		return false !== strpos( (string) $post_content, '<!-- wp:' );
	}

	public function is_elementor_page( $post_id ) {
		$edit_mode = get_post_meta( absint( $post_id ), '_elementor_edit_mode', true );
		if ( 'builder' === $edit_mode ) {
			return true;
		}

		$elementor_data = get_post_meta( absint( $post_id ), '_elementor_data', true );
		return ! empty( $elementor_data );
	}

	public function is_woocommerce_product( $post_id ) {
		return 'product' === get_post_type( absint( $post_id ) );
	}

	public function get_recommended_output_format( $post_id ) {
		$editor = $this->detect_editor_type( $post_id );
		$map    = array(
			'classic'             => 'clean_html',
			'gutenberg'           => 'gutenberg_blocks',
			'elementor'           => 'elementor_sections',
			'woocommerce_product' => 'woocommerce_html',
			'unknown'             => 'clean_html',
		);

		return isset( $map[ $editor ] ) ? $map[ $editor ] : 'clean_html';
	}
}
