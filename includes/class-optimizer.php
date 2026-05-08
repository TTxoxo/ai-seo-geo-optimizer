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

		$output_format = sanitize_text_field( $input['output_format'] ?? 'auto' );
		$detector      = new AI_SEO_GEO_Editor_Detector();
		$detected      = $detector->detect_editor_type( $post_id );
		$final_format  = 'auto' === $output_format ? $detector->get_recommended_output_format( $post_id ) : $output_format;

		$prompt_result = $this->prompt_builder->build_messages(
			$post_id,
			array(
				'target_keyword' => $target_keyword,
				'language'       => $language,
				'brand_tone'     => $brand_tone,
				'fields'         => $fields,
				'output_format'  => $final_format,
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
		$raw_response_preview = mb_substr( (string) ( $provider_debug['raw_response_preview'] ?? ( $provider_result['raw_response'] ?? '' ) ), 0, 1000 );
		$prompt_debug         = isset( $prompt_result['debug'] ) && is_array( $prompt_result['debug'] ) ? $prompt_result['debug'] : array();
		$content_selected     = $this->is_content_field_selected( $fields );
		$retry_attempted      = false;
		$retry_success        = false;

		if ( ! isset( $provider_result['data'] ) || ! is_array( $provider_result['data'] ) ) {
			$provider_result['data'] = array();
		}

		$optimized_content = $this->get_optimized_content_from_result( $provider_result['data'] );
		if ( ! empty( $provider_result['success'] ) && $content_selected && '' === $optimized_content ) {
			$retry_attempted = true;
			$retry_prompt    = $this->prompt_builder->build_empty_content_retry_messages(
				$post_id,
				array(
					'target_keyword' => $target_keyword,
					'language'       => $language,
					'brand_tone'     => $brand_tone,
					'fields'         => $fields,
					'output_format'  => $final_format,
				)
			);

			if ( ! empty( $retry_prompt['success'] ) ) {
				$retry_result = $this->provider_manager->call_provider_by_id(
					$provider_id,
					$retry_prompt['messages'],
					array(
						'model'        => $model,
						'require_json' => true,
						'max_tokens'   => $max_tokens,
					)
				);
				$retry_data = isset( $retry_result['data'] ) && is_array( $retry_result['data'] ) ? $retry_result['data'] : array();
				$retry_content = $this->get_optimized_content_from_result( $retry_data );
				if ( ! empty( $retry_result['success'] ) && '' !== $retry_content ) {
					$provider_result = $retry_result;
					$optimized_content = $retry_content;
					$provider_debug = isset( $provider_result['debug'] ) && is_array( $provider_result['debug'] ) ? $provider_result['debug'] : array();
					$raw_response_preview = mb_substr( (string) ( $provider_debug['raw_response_preview'] ?? ( $provider_result['raw_response'] ?? '' ) ), 0, 1000 );
					$retry_success = true;
				}
			}
		}

		if ( ! isset( $provider_result['data'] ) || ! is_array( $provider_result['data'] ) ) {
			$provider_result['data'] = array();
		}
		$optimized_content = $this->get_optimized_content_from_result( $provider_result['data'] );
		$has_empty_optimized_content = $content_selected && '' === $optimized_content;
		$readability_analyzer = new AI_SEO_GEO_Readability_Analyzer();
		$readability_result   = $readability_analyzer->analyze_content( $optimized_content, $target_keyword );
		$provider_result['data'] = array_merge( $provider_result['data'], $readability_result );
		$internal_link_manager = new AI_SEO_GEO_Internal_Link_Manager();
		$provider_result['data']['internal_link_suggestions'] = $internal_link_manager->validate_suggestions( $provider_result['data']['internal_link_suggestions'] ?? array(), $post_id );
		$provider_result['data']['detected_editor'] = $detected;
		$provider_result['data']['output_format'] = $final_format;
		$provider_result['data']['content_field_selected'] = $content_selected ? 'yes' : 'no';
		$provider_result['data']['normalized_result_preview'] = mb_substr( (string) wp_json_encode( $provider_result['data'] ), 0, 1000 );

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
					'content_field_selected' => $content_selected ? 'yes' : 'no',
					'output_format' => $final_format,
					'original_title_length' => absint( $prompt_debug['original_title_length'] ?? 0 ),
					'original_excerpt_length' => absint( $prompt_debug['original_excerpt_length'] ?? 0 ),
					'original_content_length' => absint( $prompt_debug['original_content_length'] ?? 0 ),
					'optimized_content_length' => mb_strlen( $optimized_content ),
					'optimized_content_mapped_from' => sanitize_text_field( (string) ( $provider_result['data']['optimized_content_mapped_from'] ?? '' ) ),
					'retry_attempted' => $retry_attempted ? 'yes' : 'no',
					'retry_success' => $retry_success ? 'yes' : 'no',
					'retry_reason' => $retry_attempted ? 'empty_optimized_content' : '',
					'normalized_result_preview' => sanitize_textarea_field( mb_substr( (string) wp_json_encode( $provider_result['data'] ), 0, 1000 ) ),
					'max_tokens'   => $max_tokens,
					'estimated_input_length' => $estimated_input_length,
					'likely_truncated' => $likely_truncated,
					'error'        => sanitize_text_field( (string) ( $provider_result['error'] ?? '' ) ),
					'http_status'  => isset( $provider_debug['http_status'] ) ? absint( $provider_debug['http_status'] ) : 0,
					'json_error'   => sanitize_text_field( (string) ( $provider_debug['json_error'] ?? '' ) ),
					'raw_response_preview' => sanitize_textarea_field( $raw_response_preview ),
				),
			)
		);

		if ( empty( $provider_result['success'] ) ) {
			$debug = $provider_debug;
			$debug['provider_key'] = $provider_result['provider_key'] ?? $provider['provider_key'];
			$debug['model']        = $provider_result['model'] ?? $provider['default_model'];
			$debug['selected_fields']        = $fields;
			$debug['content_field_selected'] = $content_selected ? 'yes' : 'no';
			$debug['output_format'] = $final_format;
			$debug['max_tokens']             = $max_tokens;
			$debug['estimated_input_length'] = $estimated_input_length;
			$debug['likely_truncated']       = $likely_truncated;
			$debug['raw_response_preview']   = $raw_response_preview;
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

		$success_message = __( 'AI suggestions generated. Please review each field before applying.', 'ai-seo-geo-optimizer' );
		if ( $has_empty_optimized_content ) {
			$success_message = 0 === absint( $prompt_debug['original_content_length'] ?? 0 )
				? __( 'The original post content is empty. AI attempted to generate optimized_content based on title, excerpt, categories, tags, and target keyword, but the result was empty.', 'ai-seo-geo-optimizer' )
				: __( 'AI returned valid JSON, but optimized_content is still empty after retry. Please check whether the original content was passed to the AI prompt and review the raw AI response preview.', 'ai-seo-geo-optimizer' );
		}

		return array(
			'success' => true,
			'message' => $success_message,
			'notice_type' => $has_empty_optimized_content ? 'warning' : 'success',
			'job_id'  => $job_id,
			'result'  => is_array( $provider_result['data'] ) ? $provider_result['data'] : array(),
			'debug'   => array(
				'selected_fields'        => $fields,
				'content_field_selected' => $content_selected ? 'yes' : 'no',
				'output_format'          => $final_format,
				'original_title_length'  => absint( $prompt_debug['original_title_length'] ?? 0 ),
				'original_excerpt_length'=> absint( $prompt_debug['original_excerpt_length'] ?? 0 ),
				'original_content_length'=> absint( $prompt_debug['original_content_length'] ?? 0 ),
				'optimized_content_length' => mb_strlen( $optimized_content ),
				'optimized_content_mapped_from' => sanitize_text_field( (string) ( $provider_result['data']['optimized_content_mapped_from'] ?? '' ) ),
				'retry_attempted'        => $retry_attempted ? 'yes' : 'no',
				'retry_success'          => $retry_success ? 'yes' : 'no',
				'normalized_result_preview' => mb_substr( (string) wp_json_encode( $provider_result['data'] ), 0, 1000 ),
				'max_tokens'             => $max_tokens,
				'estimated_input_length' => $estimated_input_length,
				'likely_truncated'       => $likely_truncated,
				'raw_response_preview'   => $raw_response_preview,
				'is_json_parse_failed'   => $is_json_parse_failed,
				'has_empty_optimized_content' => $has_empty_optimized_content,
			),
		);
	}


	/**
	 * Checks whether selected fields request body content optimization.
	 *
	 * @param array $selected_fields Selected fields.
	 *
	 * @return bool
	 */
	public function is_content_field_selected( $selected_fields ) {
		$selected_fields = is_array( $selected_fields ) ? array_map( 'sanitize_text_field', $selected_fields ) : array();
		$content_fields  = array( 'content', 'optimized_content', 'post_content', 'body', 'article_content', 'long_description', 'product_long_description' );
		return count( array_intersect( $content_fields, $selected_fields ) ) > 0;
	}

	/**
	 * Reads normalized optimized content from provider data.
	 *
	 * @param array $data Provider data.
	 *
	 * @return string
	 */
	private function get_optimized_content_from_result( $data ) {
		return isset( $data['optimized_content'] ) && is_scalar( $data['optimized_content'] ) ? trim( (string) $data['optimized_content'] ) : '';
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

			if ( ! isset( $provider_result['data'] ) || ! is_array( $provider_result['data'] ) ) {
			$provider_result['data'] = array();
		}
		$provider_result['data']['detected_editor'] = $detected;
		$provider_result['data']['output_format'] = $final_format;

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
