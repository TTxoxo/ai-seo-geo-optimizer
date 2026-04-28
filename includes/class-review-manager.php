<?php
/**
 * Review manager class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles single-post review data preparation.
 */
class AI_SEO_GEO_Review_Manager {

	/**
	 * Gets structured original post data for review page.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array|null
	 */
	public function get_original_content_data( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post ) {
			return null;
		}

		return array(
			'post_id'            => (int) $post->ID,
			'post_type'          => (string) $post->post_type,
			'title'              => (string) get_the_title( $post->ID ),
			'excerpt'            => (string) $post->post_excerpt,
			'content'            => (string) $post->post_content,
			'categories'         => $this->get_term_list( $post->ID, 'category' ),
			'tags'               => $this->get_term_list( $post->ID, 'post_tag' ),
			'current_seo_title'  => $this->get_current_seo_title( $post->ID ),
			'current_meta_desc'  => $this->get_current_meta_description( $post->ID ),
		);
	}

	/**
	 * Gets the latest job result for post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array|null
	 */
	public function get_latest_job_result( $post_id ) {
		return $this->get_latest_successful_job_result( $post_id );
	}

	/**
	 * Gets job result by explicit job ID and post ID.
	 *
	 * @param int $post_id Post ID.
	 * @param int $job_id  Job ID.
	 *
	 * @return array|null
	 */
	public function get_job_result_by_id( $post_id, $job_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_seo_jobs';
		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d AND post_id = %d LIMIT 1",
			absint( $job_id ),
			absint( $post_id )
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );
		if ( ! $row ) {
			return null;
		}

		return $this->build_job_result_payload( $row );
	}

	/**
	 * Gets the latest successful job result for post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array|null
	 */
	public function get_latest_successful_job_result( $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_seo_jobs';

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE post_id = %d AND status = %s ORDER BY id DESC LIMIT 1",
			absint( $post_id ),
			'completed'
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );
		if ( ! $row ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d ORDER BY id DESC LIMIT 1",
				absint( $post_id )
			);
			$row   = $wpdb->get_row( $query, ARRAY_A );
		}

		if ( ! $row ) {
			return null;
		}

		return $this->build_job_result_payload( $row );
	}

	/**
	 * Builds unified job payload with debug context.
	 *
	 * @param array $row Job row.
	 *
	 * @return array
	 */
	private function build_job_result_payload( $row ) {
		$decoded = json_decode( (string) $row['result_json'], true );
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		return array(
			'job'          => $row,
			'result'       => $decoded,
			'debug'        => $this->get_job_debug_context( (int) $row['id'], (int) $row['post_id'] ),
			'result_empty' => '' === trim( (string) $row['result_json'] ) || '[]' === trim( (string) $row['result_json'] ) || '{}' === trim( (string) $row['result_json'] ),
		);
	}

	/**
	 * Gets latest generation log context for a job.
	 *
	 * @param int $job_id  Job ID.
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	private function get_job_debug_context( $job_id, $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_seo_logs';
		$query = $wpdb->prepare(
			"SELECT context_json FROM {$table} WHERE job_id = %d AND post_id = %d AND action IN (%s,%s) ORDER BY id DESC LIMIT 1",
			$job_id,
			absint( $post_id ),
			'generate_suggestions_success',
			'generate_suggestions_failed'
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );
		if ( ! $row || empty( $row['context_json'] ) ) {
			return array();
		}

		$context = json_decode( (string) $row['context_json'], true );
		return is_array( $context ) ? $context : array();
	}

	/**
	 * Gets comma-separated term list.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return string
	 */
	private function get_term_list( $post_id, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		$terms = get_the_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}

	/**
	 * Gets current SEO title based on plugin availability.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function get_current_seo_title( $post_id ) {
		$yoast = get_post_meta( $post_id, '_yoast_wpseo_title', true );
		if ( '' !== (string) $yoast ) {
			return (string) $yoast;
		}

		$rank = get_post_meta( $post_id, 'rank_math_title', true );
		if ( '' !== (string) $rank ) {
			return (string) $rank;
		}

		return (string) get_post_meta( $post_id, '_ai_seo_geo_title', true );
	}

	/**
	 * Gets current meta description.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function get_current_meta_description( $post_id ) {
		$yoast = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		if ( '' !== (string) $yoast ) {
			return (string) $yoast;
		}

		$rank = get_post_meta( $post_id, 'rank_math_description', true );
		if ( '' !== (string) $rank ) {
			return (string) $rank;
		}

		return (string) get_post_meta( $post_id, '_ai_seo_geo_description', true );
	}
}
