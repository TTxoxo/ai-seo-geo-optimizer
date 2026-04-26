<?php
/**
 * Revision manager class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles snapshots, apply changes, and rollback.
 */
class AI_SEO_GEO_Revision_Manager {

	/** @var AI_SEO_GEO_SEO_Meta_Adapter */
	private $seo_adapter;

	/** @var AI_SEO_GEO_Log_Manager */
	private $log_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->seo_adapter = new AI_SEO_GEO_SEO_Meta_Adapter();
		$this->log_manager = new AI_SEO_GEO_Log_Manager();
	}

	/**
	 * Applies selected changes from a job result.
	 *
	 * @param int   $job_id          Job ID.
	 * @param int   $post_id         Post ID.
	 * @param array $selected_fields Selected fields.
	 *
	 * @return array
	 */
	public function apply_selected_changes( $job_id, $post_id, $selected_fields ) {
		global $wpdb;

		$post_id = absint( $post_id );
		$job_id  = absint( $job_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'success' => false, 'message' => __( 'Permission denied for this post.', 'ai-seo-geo-optimizer' ) );
		}

		$jobs_table = $wpdb->prefix . 'ai_seo_jobs';
		$query      = $wpdb->prepare( "SELECT * FROM {$jobs_table} WHERE id = %d AND post_id = %d LIMIT 1", $job_id, $post_id );
		$job        = $wpdb->get_row( $query, ARRAY_A );
		if ( ! $job ) {
			return array( 'success' => false, 'message' => __( 'Invalid job_id for this post.', 'ai-seo-geo-optimizer' ) );
		}

		$result_json = json_decode( (string) $job['result_json'], true );
		if ( ! is_array( $result_json ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid result_json. Cannot apply changes.', 'ai-seo-geo-optimizer' ) );
		}

		$snapshot_id = $this->save_snapshot( $post_id, $job_id );
		if ( ! $snapshot_id ) {
			return array( 'success' => false, 'message' => __( 'Failed to save snapshot before applying changes.', 'ai-seo-geo-optimizer' ) );
		}

		$post_data_update = array( 'ID' => $post_id );
		$post_type        = get_post_type( $post_id );

		if ( in_array( 'title', $selected_fields, true ) && ! empty( $result_json['h1'] ) ) {
			$post_data_update['post_title'] = sanitize_text_field( $result_json['h1'] );
		}

		if ( in_array( 'excerpt', $selected_fields, true ) && isset( $result_json['excerpt'] ) ) {
			$post_data_update['post_excerpt'] = sanitize_textarea_field( $result_json['excerpt'] );
		}

		if ( in_array( 'content', $selected_fields, true ) && ! empty( $result_json['optimized_content'] ) ) {
			$post_data_update['post_content'] = wp_kses_post( $result_json['optimized_content'] );
		}

		if ( in_array( 'faq', $selected_fields, true ) && ! empty( $result_json['faq'] ) && is_array( $result_json['faq'] ) ) {
			$faq_html = "\n\n<h2>FAQ</h2>\n";
			foreach ( $result_json['faq'] as $faq ) {
				$q = isset( $faq['question'] ) ? sanitize_text_field( $faq['question'] ) : '';
				$a = isset( $faq['answer'] ) ? sanitize_textarea_field( $faq['answer'] ) : '';
				if ( '' !== $q || '' !== $a ) {
					$faq_html .= '<h3>' . esc_html( $q ) . '</h3><p>' . esc_html( $a ) . '</p>';
				}
			}

			if ( ! empty( $post_data_update['post_content'] ) ) {
				$post_data_update['post_content'] .= wp_kses_post( $faq_html );
			} else {
				$current_post                  = get_post( $post_id );
				$post_data_update['post_content'] = wp_kses_post( (string) $current_post->post_content . $faq_html );
			}
		}

		if ( count( $post_data_update ) > 1 ) {
			wp_update_post( $post_data_update );
		}

		$focus_keyword = isset( $result_json['primary_keyword'] ) ? sanitize_text_field( $result_json['primary_keyword'] ) : '';
		if ( in_array( 'seo_title', $selected_fields, true ) || in_array( 'meta_description', $selected_fields, true ) ) {
			$seo_title = in_array( 'seo_title', $selected_fields, true ) ? sanitize_text_field( $result_json['seo_title'] ?? '' ) : '';
			$meta_desc = in_array( 'meta_description', $selected_fields, true ) ? sanitize_textarea_field( $result_json['meta_description'] ?? '' ) : '';
			$this->seo_adapter->apply_meta( $post_id, $seo_title, $meta_desc, $focus_keyword );
		}

		if ( in_array( 'tags', $selected_fields, true ) && ! empty( $result_json['suggested_tags'] ) && is_array( $result_json['suggested_tags'] ) ) {
			$this->merge_tags( $post_id, $post_type, $result_json['suggested_tags'] );
		}

		if ( in_array( 'image_alt', $selected_fields, true ) && isset( $result_json['image_alt_suggestions'] ) ) {
			update_post_meta( $post_id, '_ai_seo_geo_image_alt_suggestions', wp_json_encode( $result_json['image_alt_suggestions'] ) );
		}

		if ( in_array( 'schema', $selected_fields, true ) && isset( $result_json['schema_suggestion'] ) ) {
			update_post_meta( $post_id, '_ai_seo_geo_schema_suggestion', wp_json_encode( $result_json['schema_suggestion'] ) );
		}

		update_post_meta( $post_id, '_ai_seo_geo_optimized', current_time( 'mysql' ) );

		$this->log_manager->add_log(
			array(
				'job_id'       => $job_id,
				'post_id'      => $post_id,
				'action'       => 'apply_selected_changes',
				'message'      => __( 'Selected AI suggestions were applied.', 'ai-seo-geo-optimizer' ),
				'context_json' => array(
					'fields'      => $selected_fields,
					'snapshot_id' => $snapshot_id,
				),
			)
		);

		return array( 'success' => true, 'message' => __( 'Changes applied successfully.', 'ai-seo-geo-optimizer' ), 'snapshot_id' => $snapshot_id );
	}

	/**
	 * Saves pre-apply snapshot.
	 *
	 * @param int $post_id Post ID.
	 * @param int $job_id  Job ID.
	 *
	 * @return int
	 */
	public function save_snapshot( $post_id, $job_id ) {
		global $wpdb;

		$post = get_post( absint( $post_id ) );
		if ( ! $post ) {
			return 0;
		}

		$meta_json  = wp_json_encode( $this->seo_adapter->get_current_meta( $post_id ) );
		$terms_json = wp_json_encode(
			array(
				'post_tag'    => wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) ),
				'product_tag' => taxonomy_exists( 'product_tag' ) ? wp_get_post_terms( $post_id, 'product_tag', array( 'fields' => 'names' ) ) : array(),
			)
		);

		$table = $wpdb->prefix . 'ai_seo_snapshots';
		$wpdb->insert(
			$table,
			array(
				'post_id'       => absint( $post_id ),
				'job_id'        => absint( $job_id ),
				'old_title'     => (string) $post->post_title,
				'old_content'   => (string) $post->post_content,
				'old_excerpt'   => (string) $post->post_excerpt,
				'old_meta_json' => (string) $meta_json,
				'old_terms_json'=> (string) $terms_json,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Rolls back post using snapshot.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 *
	 * @return array
	 */
	public function rollback_snapshot( $snapshot_id ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'ai_seo_snapshots';
		$query    = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $snapshot_id ) );
		$snapshot = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $snapshot ) {
			return array( 'success' => false, 'message' => __( 'Snapshot not found.', 'ai-seo-geo-optimizer' ) );
		}

		$post_id = absint( $snapshot['post_id'] );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'success' => false, 'message' => __( 'Permission denied for rollback.', 'ai-seo-geo-optimizer' ) );
		}

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => (string) $snapshot['old_title'],
				'post_content' => (string) $snapshot['old_content'],
				'post_excerpt' => (string) $snapshot['old_excerpt'],
			)
		);

		$meta  = json_decode( (string) $snapshot['old_meta_json'], true );
		$terms = json_decode( (string) $snapshot['old_terms_json'], true );
		if ( is_array( $meta ) ) {
			$this->seo_adapter->restore_meta( $post_id, $meta );
		}
		if ( is_array( $terms ) ) {
			if ( ! empty( $terms['post_tag'] ) && taxonomy_exists( 'post_tag' ) ) {
				wp_set_post_terms( $post_id, array_map( 'sanitize_text_field', $terms['post_tag'] ), 'post_tag', false );
			}
			if ( ! empty( $terms['product_tag'] ) && taxonomy_exists( 'product_tag' ) ) {
				wp_set_post_terms( $post_id, array_map( 'sanitize_text_field', $terms['product_tag'] ), 'product_tag', false );
			}
		}

		$this->log_manager->add_log(
			array(
				'job_id'       => absint( $snapshot['job_id'] ),
				'post_id'      => $post_id,
				'action'       => 'rollback_snapshot',
				'message'      => __( 'Snapshot rollback completed.', 'ai-seo-geo-optimizer' ),
				'context_json' => array( 'snapshot_id' => absint( $snapshot_id ) ),
			)
		);

		return array( 'success' => true, 'message' => __( 'Rollback successful.', 'ai-seo-geo-optimizer' ) );
	}

	/**
	 * Gets snapshots for one post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	public function get_snapshots_by_post( $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_seo_snapshots';
		$query = $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d ORDER BY id DESC LIMIT %d", absint( $post_id ), 20 );
		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Merges suggested tags with existing terms.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $post_type  Post type.
	 * @param array  $new_tags   Suggested tags.
	 *
	 * @return void
	 */
	private function merge_tags( $post_id, $post_type, $new_tags ) {
		$taxonomy = 'post';
		if ( 'product' === $post_type ) {
			$taxonomy = 'product_tag';
		} elseif ( 'post' === $post_type ) {
			$taxonomy = 'post_tag';
		} elseif ( taxonomy_exists( 'post_tag' ) ) {
			$taxonomy = 'post_tag';
		} else {
			return;
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$current = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		if ( is_wp_error( $current ) ) {
			$current = array();
		}

		$sanitized_new = array();
		foreach ( $new_tags as $tag ) {
			$tag = sanitize_text_field( (string) $tag );
			if ( '' !== $tag ) {
				$sanitized_new[] = $tag;
			}
		}

		$merged = array_unique( array_merge( $current, $sanitized_new ) );
		wp_set_post_terms( $post_id, $merged, $taxonomy, false );
	}
}
