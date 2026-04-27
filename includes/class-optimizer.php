<?php
/**
 * Optimizer class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles single content optimization generation workflow.
 */
class AI_SEO_GEO_Optimizer {

	/**
	 * Provider manager.
	 *
	 * @var AI_SEO_GEO_AI_Provider_Manager
	 */
	private $provider_manager;

	/**
	 * Prompt builder.
	 *
	 * @var AI_SEO_GEO_Prompt_Builder
	 */
	private $prompt_builder;

	/**
	 * Log manager.
	 *
	 * @var AI_SEO_GEO_Log_Manager
	 */
	private $log_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->provider_manager = new AI_SEO_GEO_AI_Provider_Manager();
		$this->prompt_builder   = new AI_SEO_GEO_Prompt_Builder();
		$this->log_manager      = new AI_SEO_GEO_Log_Manager();
	}

	/**
	 * Generates AI suggestions and stores job.
	 *
	 * @param array $input Input payload.
	 *
	 * @return array
	 */
	public function generate_suggestions( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid post_id.', 'ai-seo-geo-optimizer' ) );
		}

		$provider_id = absint( $input['provider_id'] ?? 0 );
		$provider    = $this->provider_manager->get_provider( $provider_id );
		if ( ! $provider ) {
			return array( 'success' => false, 'message' => __( 'Provider not found.', 'ai-seo-geo-optimizer' ) );
		}
		if ( 'active' !== $provider['status'] ) {
			return array( 'success' => false, 'message' => __( 'Provider is not enabled.', 'ai-seo-geo-optimizer' ) );
		}
		if ( empty( $provider['api_key_encrypted'] ) ) {
			return array( 'success' => false, 'message' => __( 'Provider API key is missing.', 'ai-seo-geo-optimizer' ) );
		}

		$target_keyword = sanitize_text_field( $input['target_keyword'] ?? '' );
		$language       = sanitize_text_field( $input['language'] ?? 'en' );
		$brand_tone     = sanitize_text_field( $input['brand_tone'] ?? 'professional' );
		$model          = sanitize_text_field( $input['model'] ?? '' );
		$fields         = isset( $input['fields'] ) && is_array( $input['fields'] )
			? array_map( 'sanitize_text_field', wp_unslash( $input['fields'] ) )
			: array();
		$requested_max_tokens = absint( $input['max_tokens'] ?? 0 );
		$max_tokens           = $this->resolve_max_tokens( $fields, $requested_max_tokens );

		$prompt_result = $this->prompt_builder->build_messages(
			$post_id,
			array(
				'target_keyword' => $target_keyword,
				'language'       => $language,
				'brand_tone'     => $brand_tone,
			)
		);

		if ( empty( $prompt_result['success'] ) ) {
			return array( 'success' => false, 'message' => $prompt_result['error'] );
		}

		$provider_result = $this->provider_manager->call_provider_by_id(
			$provider_id,
			$prompt_result['messages'],
			array(
				'model'        => $model,
				'require_json' => true,
				'max_tokens'   => $max_tokens,
			)
		);

		$estimated_input_length = $this->estimate_messages_length( $prompt_result['messages'] );
		$provider_debug         = isset( $provider_result['debug'] ) && is_array( $provider_result['debug'] ) ? $provider_result['debug'] : array();
		$is_json_parse_failed   = ! empty( $provider_debug['is_json_parse_failed'] );
		$likely_truncated       = ! empty( $provider_debug['likely_truncated'] ) || ! empty( $provider_debug['looks_truncated'] );
		if ( $is_json_parse_failed && $this->is_raw_response_tail_incomplete( $provider_debug['raw_response_preview'] ?? '' ) ) {
			$likely_truncated = true;
		}
		$provider_debug['likely_truncated'] = $likely_truncated;

		$job_id = $this->create_job(
			array(
				'post_id'        => $post_id,
				'post_type'      => get_post_type( $post_id ),
				'provider_key'   => $provider['provider_key'],
				'model'          => ! empty( $provider_result['model'] ) ? $provider_result['model'] : $provider['default_model'],
				'status'         => ! empty( $provider_result['success'] ) ? 'completed' : 'failed',
				'target_keyword' => $target_keyword,
				'language'       => $language,
				'fields_json'    => wp_json_encode( $fields ),
				'result_json'    => wp_json_encode( $provider_result['data'] ?? array() ),
				'error_message'  => $provider_result['error'] ?? '',
			)
		);

		$this->log_manager->add_log(
			array(
				'job_id'       => $job_id,
				'post_id'      => $post_id,
				'action'       => ! empty( $provider_result['success'] ) ? 'generate_suggestions_success' : 'generate_suggestions_failed',
				'message'      => ! empty( $provider_result['success'] ) ? __( 'AI suggestions generated.', 'ai-seo-geo-optimizer' ) : __( 'AI suggestions failed.', 'ai-seo-geo-optimizer' ),
				'context_json' => array(
					'provider_key' => $provider['provider_key'],
					'model'        => $provider_result['model'] ?? $provider['default_model'],
					'selected_fields' => $fields,
					'max_tokens'   => $max_tokens,
					'estimated_input_length' => $estimated_input_length,
					'likely_truncated' => $likely_truncated,
					'error'        => sanitize_text_field( (string) ( $provider_result['error'] ?? '' ) ),
					'http_status'  => isset( $provider_debug['http_status'] ) ? absint( $provider_debug['http_status'] ) : 0,
					'json_error'   => sanitize_text_field( (string) ( $provider_debug['json_error'] ?? '' ) ),
					'raw_response_preview' => sanitize_textarea_field( mb_substr( (string) ( $provider_debug['raw_response_preview'] ?? ( $provider_result['raw_response'] ?? '' ) ), 0, 1000 ) ),
				),
			)
		);

		if ( empty( $provider_result['success'] ) ) {
			$debug = $provider_debug;
			$debug['provider_key'] = $provider_result['provider_key'] ?? $provider['provider_key'];
			$debug['model']        = $provider_result['model'] ?? $provider['default_model'];
			$debug['selected_fields']        = $fields;
			$debug['max_tokens']             = $max_tokens;
			$debug['estimated_input_length'] = $estimated_input_length;
			$debug['likely_truncated']       = $likely_truncated;
			$error_message = ! empty( $provider_result['error'] ) ? $provider_result['error'] : __( 'AI generation failed.', 'ai-seo-geo-optimizer' );
			if ( $is_json_parse_failed && $likely_truncated ) {
				$error_message .= ' ' . __( 'AI response may be truncated. Please reduce selected fields or increase max_tokens.', 'ai-seo-geo-optimizer' );
			}

			return array(
				'success' => false,
				'message' => $error_message,
				'job_id'  => $job_id,
				'debug'   => $debug,
			);
		}

		return array(
			'success' => true,
			'message' => __( 'AI suggestions generated successfully.', 'ai-seo-geo-optimizer' ),
			'job_id'  => $job_id,
			'result'  => is_array( $provider_result['data'] ) ? $provider_result['data'] : array(),
			'debug'   => array(
				'selected_fields'        => $fields,
				'max_tokens'             => $max_tokens,
				'estimated_input_length' => $estimated_input_length,
				'likely_truncated'       => $likely_truncated,
			),
		);
	}

	/**
	 * Resolves request max_tokens with long-content safety defaults.
	 *
	 * @param array $fields               Selected fields.
	 * @param int   $requested_max_tokens User requested max_tokens.
	 *
	 * @return int
	 */
	private function resolve_max_tokens( $fields, $requested_max_tokens ) {
		$base_min_tokens = 3000;
		$high_detail_min = 6000;
		$heavy_fields    = array( 'optimized_content', 'content', 'faq', 'schema', 'internal_links', 'image_alt' );
		$has_heavy_field = count( array_intersect( $fields, $heavy_fields ) ) > 0;
		$minimum_tokens  = $has_heavy_field ? $high_detail_min : $base_min_tokens;

		return max( $minimum_tokens, $requested_max_tokens );
	}

	/**
	 * Estimates input length sent to provider.
	 *
	 * @param array $messages Prompt messages.
	 *
	 * @return int
	 */
	private function estimate_messages_length( $messages ) {
		$total = 0;
		foreach ( $messages as $message ) {
			$total += mb_strlen( (string) ( $message['content'] ?? '' ) );
		}

		return $total;
	}

	/**
	 * Detects incomplete JSON tail by raw response preview.
	 *
	 * @param string $raw_response_preview Raw response preview.
	 *
	 * @return bool
	 */
	private function is_raw_response_tail_incomplete( $raw_response_preview ) {
		$tail = trim( (string) $raw_response_preview );
		if ( '' === $tail ) {
			return false;
		}

		$last_char = mb_substr( $tail, -1 );
		return '}' !== $last_char;
	}

	/**
	 * Creates batch draft jobs only (no AI calls).
	 *
	 * @param array $input Batch payload.
	 *
	 * @return array
	 */
	public function create_batch_draft_jobs( $input ) {
		$post_ids = isset( $input['post_ids'] ) && is_array( $input['post_ids'] ) ? array_map( 'absint', $input['post_ids'] ) : array();
		$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );

		if ( empty( $post_ids ) ) {
			return array( 'success' => false, 'message' => __( 'Please select at least one post.', 'ai-seo-geo-optimizer' ) );
		}
		if ( count( $post_ids ) > 10 ) {
			return array( 'success' => false, 'message' => __( 'Maximum 10 items are allowed per batch draft.', 'ai-seo-geo-optimizer' ) );
		}

		$provider_id = absint( $input['provider_id'] ?? 0 );
		$provider    = $this->provider_manager->get_provider( $provider_id );
		if ( ! $provider ) {
			return array( 'success' => false, 'message' => __( 'Provider not found.', 'ai-seo-geo-optimizer' ) );
		}
		if ( 'active' !== $provider['status'] ) {
			return array( 'success' => false, 'message' => __( 'Provider is not enabled.', 'ai-seo-geo-optimizer' ) );
		}

		$model          = sanitize_text_field( $input['model'] ?? '' );
		$target_keyword = sanitize_text_field( $input['target_keyword'] ?? '' );
		$language       = sanitize_text_field( $input['language'] ?? 'en' );
		$brand_tone     = sanitize_text_field( $input['brand_tone'] ?? 'professional' );
		$fields         = isset( $input['fields'] ) && is_array( $input['fields'] )
			? array_values( array_unique( array_map( 'sanitize_text_field', wp_unslash( $input['fields'] ) ) ) )
			: array();

		$batch_group_id = 'batch_' . gmdate( 'YmdHis' ) . '_' . wp_generate_password( 6, false, false );
		$created_jobs   = array();
		$skipped_posts  = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				$skipped_posts[] = $post_id;
				continue;
			}

			$job_id = $this->create_job(
				array(
					'post_id'        => $post_id,
					'post_type'      => $post->post_type,
					'provider_key'   => $provider['provider_key'],
					'model'          => '' !== $model ? $model : $provider['default_model'],
					'status'         => 'batch_draft',
					'target_keyword' => $target_keyword,
					'language'       => $language,
					'fields_json'    => wp_json_encode(
						array(
							'batch_group_id' => $batch_group_id,
							'brand_tone'     => $brand_tone,
							'selected_fields' => $fields,
							'mode'           => 'draft_only',
						)
					),
					'result_json'    => wp_json_encode( array() ),
					'error_message'  => '',
				)
			);

			if ( $job_id <= 0 ) {
				$skipped_posts[] = $post_id;
				continue;
			}

			$created_jobs[] = $job_id;

			$this->log_manager->add_log(
				array(
					'job_id'       => $job_id,
					'post_id'      => $post_id,
					'action'       => 'batch_draft_created',
					'message'      => __( 'Batch draft job created. Review is required before applying any changes.', 'ai-seo-geo-optimizer' ),
					'context_json' => array(
						'batch_group_id' => $batch_group_id,
						'provider_key'   => $provider['provider_key'],
						'model'          => '' !== $model ? $model : $provider['default_model'],
					),
				)
			);
		}

		if ( empty( $created_jobs ) ) {
			return array( 'success' => false, 'message' => __( 'No batch draft jobs were created.', 'ai-seo-geo-optimizer' ) );
		}

		return array(
			'success'        => true,
			'message'        => __( 'Batch draft jobs created successfully. Bulk queue execution is not enabled in this version.', 'ai-seo-geo-optimizer' ),
			'batch_group_id' => $batch_group_id,
			'created_jobs'   => $created_jobs,
			'skipped_posts'  => $skipped_posts,
		);
	}

	/**
	 * Creates ai_seo_jobs record.
	 *
	 * @param array $data Job data.
	 *
	 * @return int
	 */
	private function create_job( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_seo_jobs';

		$wpdb->insert(
			$table,
			array(
				'post_id'        => absint( $data['post_id'] ),
				'post_type'      => sanitize_text_field( $data['post_type'] ),
				'provider_key'   => sanitize_text_field( $data['provider_key'] ),
				'model'          => sanitize_text_field( $data['model'] ),
				'status'         => sanitize_text_field( $data['status'] ),
				'target_keyword' => sanitize_text_field( $data['target_keyword'] ),
				'language'       => sanitize_text_field( $data['language'] ),
				'fields_json'    => (string) $data['fields_json'],
				'result_json'    => (string) $data['result_json'],
				'error_message'  => sanitize_textarea_field( (string) $data['error_message'] ),
				'created_by'     => get_current_user_id(),
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}
}
