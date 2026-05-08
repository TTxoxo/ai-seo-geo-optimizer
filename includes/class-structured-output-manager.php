<?php
/**
 * Structured output manager class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AI JSON cleanup, parsing and result normalization.
 */
class AI_SEO_GEO_Structured_Output_Manager {

	/**
	 * Parses raw AI content into normalized array output.
	 *
	 * @param mixed $content Raw content.
	 *
	 * @return array
	 */
	public function parse_json_response( $content ) {
		if ( is_array( $content ) || is_object( $content ) ) {
			$content = wp_json_encode( $content );
		} else {
			$content = (string) $content;
		}

		$content = trim( $content );
		$content = preg_replace( '/^\xEF\xBB\xBF/', '', $content );
		$content = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content );
		$content = trim( (string) $content );

		$markdown_wrapped = $this->is_markdown_wrapped( $content );
		$stripped         = preg_replace( '/^\s*```json\s*/i', '', $content );
		$stripped         = preg_replace( '/\s*```\s*$/i', '', (string) $stripped );
		$stripped         = preg_replace( '/^\s*```\s*/i', '', (string) $stripped );
		$stripped         = trim( (string) $stripped );

		$decoded = json_decode( $stripped, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			return array(
				'success'           => true,
				'data'              => $this->normalize_ai_result( $decoded ),
				'error'             => '',
				'json_error'        => '',
				'raw_preview'       => '',
				'likely_truncated'  => false,
				'markdown_wrapped'  => $markdown_wrapped,
			);
		}

		$extracted = $this->extract_json_from_text( $stripped );
		if ( '' !== $extracted ) {
			$decoded = json_decode( $extracted, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return array(
					'success'           => true,
					'data'              => $this->normalize_ai_result( $decoded ),
					'error'             => '',
					'json_error'        => '',
					'raw_preview'       => '',
					'likely_truncated'  => false,
					'markdown_wrapped'  => $markdown_wrapped,
				);
			}

			$without_trailing_commas = preg_replace( '/,\s*([}\]])/', '$1', $extracted );
			$decoded                 = json_decode( (string) $without_trailing_commas, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return array(
					'success'           => true,
					'data'              => $this->normalize_ai_result( $decoded ),
					'error'             => '',
					'json_error'        => '',
					'raw_preview'       => '',
					'likely_truncated'  => false,
					'markdown_wrapped'  => $markdown_wrapped,
				);
			}
		}

		return array(
			'success'           => false,
			'data'              => array(),
			'error'             => __( 'JSON parse failed from AI response.', 'ai-seo-geo-optimizer' ),
			'json_error'        => json_last_error_msg(),
			'raw_preview'       => mb_substr( $stripped, 0, 500 ),
			'likely_truncated'  => $this->is_likely_truncated( $stripped ),
			'markdown_wrapped'  => $markdown_wrapped,
		);
	}

	/**
	 * Extracts the broadest JSON object block from text.
	 *
	 * @param string $content Content string.
	 *
	 * @return string
	 */
	public function extract_json_from_text( $content ) {
		$content = (string) $content;
		$start   = strpos( $content, '{' );
		$end     = strrpos( $content, '}' );

		if ( false === $start || false === $end || $end <= $start ) {
			return '';
		}

		return substr( $content, $start, ( $end - $start + 1 ) );
	}

	/**
	 * Normalizes AI result fields with safe defaults.
	 *
	 * @param array $decoded Decoded payload.
	 *
	 * @return array
	 */
	public function normalize_ai_result( $decoded ) {
		$defaults = $this->get_default_result();
		$decoded  = is_array( $decoded ) ? $decoded : array();
		$result   = array_merge( $defaults, $decoded );

		$string_fields = array(
			'search_intent',
			'primary_keyword',
			'seo_title',
			'meta_description',
			'slug_suggestion',
			'h1',
			'excerpt',
			'detected_editor',
			'output_format',
			'optimized_content',
			'optimized_content_mapped_from',
			'gutenberg_content',
			'product_short_description',
			'product_long_description',
			'geo_summary',
			'writing_person',
			'tone',
			'ai_style_risk',
		);

		foreach ( $string_fields as $field ) {
			$result[ $field ] = is_scalar( $result[ $field ] ) ? (string) $result[ $field ] : '';
		}

		if ( '' === trim( $result['optimized_content'] ) ) {
			$content_aliases = array(
				'content',
				'body',
				'article',
				'article_content',
				'html_content',
				'final_content',
				'revised_content',
				'improved_content',
				'rewritten_content',
				'post_content',
				'body_html',
				'long_description',
				'product_description',
				'product_long_description',
				'sections',
			);

			foreach ( $content_aliases as $alias ) {
				if ( ! isset( $decoded[ $alias ] ) ) {
					continue;
				}

				$alias_value = '';
				if ( is_scalar( $decoded[ $alias ] ) ) {
					$alias_value = trim( (string) $decoded[ $alias ] );
				} elseif ( is_array( $decoded[ $alias ] ) ) {
					$alias_value = $this->convert_content_array_to_html( $decoded[ $alias ] );
				}

				if ( '' !== $alias_value ) {
					$result['optimized_content']             = $alias_value;
					$result['optimized_content_mapped_from'] = $alias;
					break;
				}
			}
		}

		$array_fields = array(
			'secondary_keywords',
			'suggested_tags',
			'outline',
			'faq',
			'internal_link_suggestions',
			'image_alt_suggestions',
			'fact_check_notes',
			'needs_human_review',
			'unsupported_claims_removed',
			'forbidden_phrases_found',
			'generic_marketing_phrases',
			'recommended_human_edits',
			'format_warnings',
			'elementor_sections',
		);
		foreach ( $array_fields as $field ) {
			$result[ $field ] = is_array( $result[ $field ] ) ? $result[ $field ] : array();
		}

		$result['schema_suggestion'] = is_array( $result['schema_suggestion'] ) ? $result['schema_suggestion'] : array();
		$result['safe_to_apply_content'] = isset( $result['safe_to_apply_content'] ) ? (bool) $result['safe_to_apply_content'] : true;
		$result['content_score']     = is_array( $result['content_score'] ) ? $result['content_score'] : array();
		$result['content_score']     = array_merge( $defaults['content_score'], $result['content_score'] );

		foreach ( $result['content_score'] as $score_key => $score_value ) {
			$result['content_score'][ $score_key ] = is_numeric( $score_value ) ? (int) $score_value : 0;
		}

		$risk_level = strtolower( (string) $result['risk_level'] );
		$result['risk_level'] = in_array( $risk_level, array( 'low', 'medium', 'high' ), true ) ? $risk_level : 'medium';
		$ai_style_risk = strtolower( (string) $result['ai_style_risk'] );
		$result['ai_style_risk'] = in_array( $ai_style_risk, array( 'low', 'medium', 'high' ), true ) ? $ai_style_risk : 'medium';
		$result['human_readability_score'] = isset( $result['human_readability_score'] ) && is_numeric( $result['human_readability_score'] ) ? (int) $result['human_readability_score'] : 0;

		return $result;
	}


	/**
	 * Converts structured content arrays into safe HTML snippets.
	 *
	 * @param array $items Content sections.
	 *
	 * @return string
	 */
	private function convert_content_array_to_html( $items ) {
		$html = '';
		foreach ( $items as $item ) {
			if ( is_scalar( $item ) ) {
				$text = trim( (string) $item );
				if ( '' !== $text ) {
					$html .= '<p>' . esc_html( $text ) . '</p>';
				}
				continue;
			}

			if ( ! is_array( $item ) ) {
				continue;
			}

			$heading = isset( $item['heading'] ) && is_scalar( $item['heading'] ) ? trim( (string) $item['heading'] ) : '';
			$body    = isset( $item['body'] ) && is_scalar( $item['body'] ) ? trim( (string) $item['body'] ) : '';
			if ( '' === $body && isset( $item['content'] ) && is_scalar( $item['content'] ) ) {
				$body = trim( (string) $item['content'] );
			}

			if ( '' !== $heading ) {
				$html .= '<h2>' . esc_html( $heading ) . '</h2>';
			}
			if ( '' !== $body ) {
				$html .= '<p>' . esc_html( $body ) . '</p>';
			}
		}

		return trim( $html );
	}

	/**
	 * Gets default normalized AI result.
	 *
	 * @return array
	 */
	public function get_default_result() {
		return array(
			'search_intent'             => '',
			'primary_keyword'           => '',
			'secondary_keywords'        => array(),
			'seo_title'                 => '',
			'meta_description'          => '',
			'slug_suggestion'           => '',
			'h1'                        => '',
			'suggested_tags'            => array(),
			'excerpt'                   => '',
			'outline'                   => array(),
			'detected_editor'          => '',
			'output_format'             => '',
			'format_warnings'           => array(),
			'optimized_content'         => '',
			'optimized_content_mapped_from' => '',
			'gutenberg_content'         => '',
			'elementor_sections'        => array(),
			'product_short_description' => '',
			'product_long_description'  => '',
			'safe_to_apply_content'     => true,
			'faq'                       => array(),
			'internal_link_suggestions' => array(),
			'image_alt_suggestions'     => array(),
			'schema_suggestion'         => array(),
			'geo_summary'               => '',
			'writing_person'            => '',
			'tone'                      => '',
			'fact_check_notes'          => array(),
			'needs_human_review'        => array(),
			'unsupported_claims_removed'=> array(),
			'forbidden_phrases_found'   => array(),
			'generic_marketing_phrases' => array(),
			'human_readability_score'   => 0,
			'ai_style_risk'             => 'medium',
			'recommended_human_edits'   => array(),
			'content_score'             => array(
				'seo_score'         => 0,
				'geo_score'         => 0,
				'readability_score' => 0,
				'trust_score'       => 0,
				'risk_score'        => 0,
			),
			'risk_level'                => 'medium',
		);
	}

	/**
	 * Gets OpenAI json_schema payload for seo_geo_suggestion.
	 *
	 * @return array
	 */
	public function get_seo_geo_suggestion_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array(
				'search_intent',
				'primary_keyword',
				'secondary_keywords',
				'seo_title',
				'meta_description',
				'slug_suggestion',
				'h1',
				'suggested_tags',
				'excerpt',
				'outline',
				'detected_editor',
			'output_format',
			'optimized_content',
			'gutenberg_content',
			'product_short_description',
			'product_long_description',
				'faq',
				'internal_link_suggestions',
				'image_alt_suggestions',
				'schema_suggestion',
				'geo_summary',
				'fact_check_notes',
				'needs_human_review',
				'unsupported_claims_removed',
				'content_score',
				'risk_level',
			),
			'properties'           => array(
				'search_intent'             => array( 'type' => 'string' ),
				'primary_keyword'           => array( 'type' => 'string' ),
				'secondary_keywords'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'seo_title'                 => array( 'type' => 'string' ),
				'meta_description'          => array( 'type' => 'string' ),
				'slug_suggestion'           => array( 'type' => 'string' ),
				'h1'                        => array( 'type' => 'string' ),
				'suggested_tags'            => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'excerpt'                   => array( 'type' => 'string' ),
				'outline'                   => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'heading', 'purpose' ),
						'properties'           => array(
							'heading' => array( 'type' => 'string' ),
							'purpose' => array( 'type' => 'string' ),
						),
					),
				),
				'optimized_content'         => array( 'type' => 'string' ),
				'faq'                       => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'question', 'answer' ),
						'properties'           => array(
							'question' => array( 'type' => 'string' ),
							'answer'   => array( 'type' => 'string' ),
						),
					),
				),
				'internal_link_suggestions' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'anchor', 'target_url', 'reason' ),
						'properties'           => array(
							'anchor'     => array( 'type' => 'string' ),
							'target_url' => array( 'type' => 'string' ),
							'reason'     => array( 'type' => 'string' ),
						),
					),
				),
				'image_alt_suggestions'     => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'image_id', 'alt_text', 'reason' ),
						'properties'           => array(
							'image_id' => array( 'type' => 'string' ),
							'alt_text' => array( 'type' => 'string' ),
							'reason'   => array( 'type' => 'string' ),
						),
					),
				),
				'schema_suggestion'         => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'required'             => array( '@type', 'fields' ),
					'properties'           => array(
						'@type'  => array( 'type' => 'string' ),
						'fields' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),
				'geo_summary'               => array( 'type' => 'string' ),
				'fact_check_notes'          => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'needs_human_review'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'unsupported_claims_removed'=> array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'content_score'             => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array(
						'seo_score',
						'geo_score',
						'readability_score',
						'trust_score',
						'risk_score',
					),
					'properties'           => array(
						'seo_score'         => array( 'type' => 'integer' ),
						'geo_score'         => array( 'type' => 'integer' ),
						'readability_score' => array( 'type' => 'integer' ),
						'trust_score'       => array( 'type' => 'integer' ),
						'risk_score'        => array( 'type' => 'integer' ),
					),
				),
				'risk_level'                => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Determines if content looks truncated.
	 *
	 * @param string $content Content string.
	 *
	 * @return bool
	 */
	public function is_likely_truncated( $content ) {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return false;
		}

		$open_curly  = substr_count( $content, '{' );
		$close_curly = substr_count( $content, '}' );
		$open_square = substr_count( $content, '[' );
		$close_square = substr_count( $content, ']' );

		if ( $open_curly !== $close_curly || $open_square !== $close_square ) {
			return true;
		}

		$last_char = mb_substr( $content, -1 );
		return ! in_array( $last_char, array( '}', ']', '"' ), true );
	}

	/**
	 * Determines if content is wrapped by markdown code fences.
	 *
	 * @param string $content Content string.
	 *
	 * @return bool
	 */
	public function is_markdown_wrapped( $content ) {
		$content = trim( (string) $content );
		return 1 === preg_match( '/^```(?:json)?[\s\S]*```$/i', $content );
	}
}
