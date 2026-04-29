<?php
/**
 * Prompt builder class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds SEO/GEO prompts and manages template sources.
 */
class AI_SEO_GEO_Prompt_Builder {

	/**
	 * Option key for custom templates.
	 *
	 * @var string
	 */
	private $option_key = 'ai_seo_geo_prompt_templates';

	/**
	 * Maximum content length for prompt context.
	 *
	 * @var int
	 */
	private $max_content_length = 12000;

	/**
	 * Gets supported templates map.
	 *
	 * @return array
	 */
	public function get_template_map() {
		return array(
			'post_seo'   => array(
				'label'    => __( 'Post SEO Template', 'ai-seo-geo-optimizer' ),
				'file'     => 'prompt-post-seo.txt',
				'posttype' => 'post',
			),
			'product_seo' => array(
				'label'    => __( 'Product SEO Template', 'ai-seo-geo-optimizer' ),
				'file'     => 'prompt-product-seo.txt',
				'posttype' => 'product',
			),
			'meta_only' => array(
				'label'    => __( 'Meta Only Template', 'ai-seo-geo-optimizer' ),
				'file'     => 'prompt-meta-only.txt',
				'posttype' => 'any',
			),
			'geo'       => array(
				'label'    => __( 'GEO Template', 'ai-seo-geo-optimizer' ),
				'file'     => 'prompt-geo.txt',
				'posttype' => 'any',
			),
			'faq'       => array(
				'label'    => __( 'FAQ Template', 'ai-seo-geo-optimizer' ),
				'file'     => 'prompt-faq.txt',
				'posttype' => 'any',
			),
			'image_alt' => array(
				'label'    => __( 'Image Alt Template', 'ai-seo-geo-optimizer' ),
				'file'     => 'prompt-image-alt.txt',
				'posttype' => 'any',
			),
		);
	}

	/**
	 * Gets available template variables.
	 *
	 * @return array
	 */
	public function get_available_variables() {
		return array(
			'{{site_name}}'          => __( 'Site name', 'ai-seo-geo-optimizer' ),
			'{{site_url}}'           => __( 'Site URL', 'ai-seo-geo-optimizer' ),
			'{{post_id}}'            => __( 'Current post ID', 'ai-seo-geo-optimizer' ),
			'{{post_type}}'          => __( 'Post type', 'ai-seo-geo-optimizer' ),
			'{{post_title}}'         => __( 'Post title', 'ai-seo-geo-optimizer' ),
			'{{post_content}}'       => __( 'Post content (trimmed)', 'ai-seo-geo-optimizer' ),
			'{{post_excerpt}}'       => __( 'Post excerpt', 'ai-seo-geo-optimizer' ),
			'{{post_categories}}'    => __( 'Comma separated categories', 'ai-seo-geo-optimizer' ),
			'{{post_tags}}'          => __( 'Comma separated tags', 'ai-seo-geo-optimizer' ),
			'{{product_attributes}}' => __( 'Product attributes (if product)', 'ai-seo-geo-optimizer' ),
			'{{target_keyword}}'     => __( 'Target keyword passed by user', 'ai-seo-geo-optimizer' ),
			'{{brand_tone}}'         => __( 'Brand tone (default: professional)', 'ai-seo-geo-optimizer' ),
			'{{internal_links}}'     => __( 'Suggested internal links', 'ai-seo-geo-optimizer' ),
			'{{language}}'           => __( 'Language code/name', 'ai-seo-geo-optimizer' ),
		);
	}

	/**
	 * Gets effective template by key.
	 *
	 * @param string $template_key Template key.
	 *
	 * @return string
	 */
	public function get_template( $template_key ) {
		$templates = get_option( $this->option_key, array() );
		if ( isset( $templates[ $template_key ] ) && '' !== trim( $templates[ $template_key ] ) ) {
			return (string) $templates[ $template_key ];
		}

		return $this->get_default_template( $template_key );
	}

	/**
	 * Saves custom template into options.
	 *
	 * @param string $template_key Template key.
	 * @param string $content      Template content.
	 *
	 * @return bool
	 */
	public function save_template( $template_key, $content ) {
		$map = $this->get_template_map();
		if ( ! isset( $map[ $template_key ] ) ) {
			return false;
		}

		$templates                 = get_option( $this->option_key, array() );
		$templates[ $template_key ] = wp_kses_post( wp_unslash( $content ) );

		return update_option( $this->option_key, $templates );
	}

	/**
	 * Resets custom template to default.
	 *
	 * @param string $template_key Template key.
	 *
	 * @return bool
	 */
	public function reset_template( $template_key ) {
		$templates = get_option( $this->option_key, array() );
		if ( isset( $templates[ $template_key ] ) ) {
			unset( $templates[ $template_key ] );
		}

		return update_option( $this->option_key, $templates );
	}

	/**
	 * Builds system and user messages for model invocation.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Build options.
	 *
	 * @return array
	 */
	public function build_messages( $post_id, $args = array() ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post ) {
			return array(
				'success' => false,
				'error'   => __( 'Post not found for prompt building.', 'ai-seo-geo-optimizer' ),
				'messages'=> array(),
			);
		}

		$template_key = ! empty( $args['template_key'] )
			? sanitize_text_field( $args['template_key'] )
			: $this->get_template_key_by_post_type( $post->post_type );

		$template = $this->get_template( $template_key );
		$vars     = $this->build_template_variables( $post, $args );
		$user     = strtr( $template, $vars );
		$user     = rtrim( $user ) . "\n\nReturn JSON only. No Markdown. No explanation.";

		$output_format = isset( $args['output_format'] ) ? sanitize_text_field( $args['output_format'] ) : 'auto';
		$detector      = new AI_SEO_GEO_Editor_Detector();
		$detected      = $detector->detect_editor_type( $post_id );
		$recommended   = $detector->get_recommended_output_format( $post_id );
		$final_format  = 'auto' === $output_format || '' === $output_format ? $recommended : $output_format;
		$format_mgr    = new AI_SEO_GEO_Output_Format_Manager();
		$user         .= "\n\nDetected editor: {$detected}.";
		$user         .= "\nOutput format: {$final_format}.";
		$user         .= "\n" . $format_mgr->get_format_instruction( $final_format );
		$link_manager = new AI_SEO_GEO_Internal_Link_Manager();
		$link_library = $link_manager->get_library_prompt_payload();
		if ( ! empty( $link_library ) ) {
			$user .= "\n\nInternal Link Library (JSON):\n" . wp_json_encode( $link_library );
		}


		if ( in_array( 'schema', isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array(), true ) ) {
			$schema_mgr = new AI_SEO_GEO_Schema_Review_Manager();
			$schema_context = $schema_mgr->build_schema_context( $post_id );
			$user .= "\n\nSchema Context (JSON):\n" . wp_json_encode( $schema_context );
			$user .= "\nGenerate schema suggestions only from visible page content and provided site settings.\nDo not invent price, availability, ratings, reviews, certifications, SKU, brand, or organization details.\nIf required information is missing, omit that field and add it to schema_suggestion.needs_human_review.\nFAQPage schema can only use FAQ content that is visible on the page or included in the optimized content.\nReturn schema as a suggestion only.";
		}

		if ( in_array( 'image_alt', isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array(), true ) ) {
			$image_alt_manager = new AI_SEO_GEO_Image_Alt_Manager();
			$image_context     = $image_alt_manager->build_image_context_for_ai( $post_id );
			$user             .= "\n\nImages Context (JSON):\n" . wp_json_encode( $image_context );
			$user             .= "\nGenerate image alt text suggestions for the provided images.\nAlt text must describe the visible image and its relationship to the page.\nDo not keyword stuff.\nDo not use best, top, cheap, No.1, 100% guaranteed.\nDo not describe things that are not visible or not provided.\nDo not invent project locations, countries, certifications, or product specifications.\nKeep alt text concise and descriptive.\nUse 8-18 English words when possible.";
		}

		$user .= "\nYou may only suggest internal links from the provided Internal Link Library.\nDo not invent URLs.\nDo not create new target URLs.\nIf no suitable internal link exists, return an empty internal_link_suggestions array.\nUse natural anchor text.\nDo not force links into irrelevant sections.\nSuggest 2-5 internal links only when relevant.";

		if ( $this->should_require_optimized_content( $args ) ) {
			$user .= "\n\nYou must return a non-empty optimized_content field.\n";
			$user .= "The optimized_content field must contain the improved WordPress post body in valid HTML.\n";
			$user .= "Use only safe HTML tags such as h2, h3, p, ul, ol, li, strong, table, thead, tbody, tr, th, td.\n";
			$user .= "Do not leave optimized_content empty if content optimization is selected.\n";
			$user .= "If you cannot rewrite the full content, return an improved outline and rewritten first section in optimized_content, and add the limitation to needs_human_review.\n";
		}

		$style_rules_manager = new AI_SEO_GEO_Style_Rules_Manager();
		$style_rules_text    = $style_rules_manager->get_prompt_rules_text( (string) $post->post_type );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $this->get_system_instruction() . "\n\n" . $style_rules_text,
			),
			array(
				'role'    => 'user',
				'content' => $user,
			),
		);

		return array(
			'success'      => true,
			'error'        => '',
			'template_key' => $template_key,
			'messages'     => $messages,
		);
	}

	/**
	 * Whether current generation requires non-empty optimized_content.
	 *
	 * @param array $args Build arguments.
	 *
	 * @return bool
	 */
	private function should_require_optimized_content( $args ) {
		$fields = isset( $args['fields'] ) && is_array( $args['fields'] ) ? array_map( 'sanitize_text_field', $args['fields'] ) : array();
		return in_array( 'content', $fields, true ) || in_array( 'optimized_content', $fields, true );
	}

	/**
	 * Resolves template key by post type.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return string
	 */
	private function get_template_key_by_post_type( $post_type ) {
		return 'product' === $post_type ? 'product_seo' : 'post_seo';
	}

	/**
	 * Loads default template file.
	 *
	 * @param string $template_key Template key.
	 *
	 * @return string
	 */
	private function get_default_template( $template_key ) {
		$map = $this->get_template_map();
		if ( ! isset( $map[ $template_key ] ) ) {
			return '';
		}

		$file_path = trailingslashit( AI_SEO_GEO_PATH . 'templates' ) . $map[ $template_key ]['file'];
		if ( ! file_exists( $file_path ) ) {
			return '';
		}

		$content = file_get_contents( $file_path );
		return false === $content ? '' : (string) $content;
	}

	/**
	 * Builds replacements map.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args Build options.
	 *
	 * @return array
	 */
	private function build_template_variables( $post, $args ) {
		$target_keyword = isset( $args['target_keyword'] ) ? sanitize_text_field( $args['target_keyword'] ) : '';
		$brand_tone     = isset( $args['brand_tone'] ) ? sanitize_text_field( $args['brand_tone'] ) : 'professional';
		$internal_links = isset( $args['internal_links'] ) ? sanitize_textarea_field( $args['internal_links'] ) : '';
		$language       = isset( $args['language'] ) ? sanitize_text_field( $args['language'] ) : 'en';

		$content = $this->trim_content_preserve_core( (string) $post->post_content );
		$excerpt = $post->post_excerpt ? (string) $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '...' );

		return array(
			'{{site_name}}'          => get_bloginfo( 'name' ),
			'{{site_url}}'           => home_url(),
			'{{post_id}}'            => (string) $post->ID,
			'{{post_type}}'          => (string) $post->post_type,
			'{{post_title}}'         => (string) get_the_title( $post->ID ),
			'{{post_content}}'       => $content,
			'{{post_excerpt}}'       => $excerpt,
			'{{post_categories}}'    => $this->join_terms( $post->ID, 'category' ),
			'{{post_tags}}'          => $this->join_terms( $post->ID, 'post_tag' ),
			'{{product_attributes}}' => $this->get_product_attributes_text( $post ),
			'{{target_keyword}}'     => $target_keyword,
			'{{brand_tone}}'         => $brand_tone,
			'{{internal_links}}'     => $internal_links,
			'{{language}}'           => $language,
		);
	}

	/**
	 * Trims large content while preserving beginning and ending context.
	 *
	 * @param string $content Content string.
	 *
	 * @return string
	 */
	private function trim_content_preserve_core( $content ) {
		$content = wp_strip_all_tags( $content );
		$content = trim( preg_replace( '/\s+/', ' ', $content ) );

		if ( mb_strlen( $content ) <= $this->max_content_length ) {
			return $content;
		}

		$head = mb_substr( $content, 0, 8000 );
		$tail = mb_substr( $content, -3000 );

		return $head . "\n\n[...content truncated for token safety...]\n\n" . $tail;
	}

	/**
	 * Joins taxonomy terms by comma.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return string
	 */
	private function join_terms( $post_id, $taxonomy ) {
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
	 * Extracts product attributes as text when available.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return string
	 */
	private function get_product_attributes_text( $post ) {
		if ( 'product' !== $post->post_type || ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return '';
		}

		$attributes = $product->get_attributes();
		if ( empty( $attributes ) ) {
			return '';
		}

		$lines = array();
		foreach ( $attributes as $attribute ) {
			if ( $attribute->is_taxonomy() ) {
				$terms = wc_get_product_terms( $post->ID, $attribute->get_name(), array( 'fields' => 'names' ) );
				$lines[] = $attribute->get_name() . ': ' . implode( ', ', $terms );
			} else {
				$lines[] = $attribute->get_name() . ': ' . implode( ', ', $attribute->get_options() );
			}
		}

		return implode( '; ', $lines );
	}

	/**
	 * Builds strict system prompt instruction.
	 *
	 * @return string
	 */
	private function get_system_instruction() {
		return "You are an experienced SEO/GEO editor.\nYou must return valid JSON only.\nDo not use Markdown.\nDo not wrap the response in ```json.\nDo not wrap the response in triple backticks.\nDo not add explanations before or after the JSON.\nThe entire response must be a single valid JSON object.\nAll property names must use double quotes.\nAll string values must use double quotes.\nDo not use trailing commas.\nDo not include comments.\nDo not include undefined or NaN values.\nIf a field has no value, use an empty string, empty array, empty object, or null.\nReturn JSON only.";
	}
}
