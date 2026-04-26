<?php
/**
 * SEO meta adapter class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles SEO meta compatibility for Yoast/RankMath/fallback.
 */
class AI_SEO_GEO_SEO_Meta_Adapter {

	/**
	 * Gets current SEO meta fields.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	public function get_current_meta( $post_id ) {
		return array(
			'yoast_title'        => (string) get_post_meta( $post_id, '_yoast_wpseo_title', true ),
			'yoast_description'  => (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
			'rank_title'         => (string) get_post_meta( $post_id, 'rank_math_title', true ),
			'rank_description'   => (string) get_post_meta( $post_id, 'rank_math_description', true ),
			'rank_focus_keyword' => (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
			'ai_title'           => (string) get_post_meta( $post_id, '_ai_seo_geo_title', true ),
			'ai_description'     => (string) get_post_meta( $post_id, '_ai_seo_geo_description', true ),
			'ai_focus_keyword'   => (string) get_post_meta( $post_id, '_ai_seo_geo_focus_keyword', true ),
		);
	}

	/**
	 * Applies SEO title/description/focus keyword.
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $seo_title        SEO title.
	 * @param string $meta_description Meta description.
	 * @param string $focus_keyword    Focus keyword.
	 *
	 * @return void
	 */
	public function apply_meta( $post_id, $seo_title, $meta_description, $focus_keyword = '' ) {
		$seo_title        = sanitize_text_field( $seo_title );
		$meta_description = sanitize_textarea_field( $meta_description );
		$focus_keyword    = sanitize_text_field( $focus_keyword );

		if ( defined( 'WPSEO_VERSION' ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
		}

		if ( defined( 'RANK_MATH_VERSION' ) ) {
			update_post_meta( $post_id, 'rank_math_title', $seo_title );
			update_post_meta( $post_id, 'rank_math_description', $meta_description );
			if ( '' !== $focus_keyword ) {
				update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_keyword );
			}
		}

		update_post_meta( $post_id, '_ai_seo_geo_title', $seo_title );
		update_post_meta( $post_id, '_ai_seo_geo_description', $meta_description );
		if ( '' !== $focus_keyword ) {
			update_post_meta( $post_id, '_ai_seo_geo_focus_keyword', $focus_keyword );
		}
	}

	/**
	 * Restores meta from snapshot json payload.
	 *
	 * @param int   $post_id    Post ID.
	 * @param array $meta_array Snapshot meta array.
	 *
	 * @return void
	 */
	public function restore_meta( $post_id, $meta_array ) {
		foreach ( $meta_array as $meta_key => $meta_value ) {
			update_post_meta( $post_id, sanitize_key( $meta_key ), sanitize_textarea_field( (string) $meta_value ) );
		}
	}
}
