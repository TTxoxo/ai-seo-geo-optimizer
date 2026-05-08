<?php
/**
 * Style rules manager.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_GEO_Style_Rules_Manager {
	private $option_key = 'ai_seo_geo_style_rules';

	public function get_default_rules() {
		return array(
			'writing_person' => array(
				'post'    => 'third_person',
				'page'    => 'first_person_plural',
				'product' => 'third_person',
			),
			'tone' => array( 'professional', 'factual', 'buyer-focused', 'non-hype' ),
			'forbidden_ai_phrases' => array(
				"In today's competitive market", 'It is important to note that', 'Whether you are looking for', 'Designed to meet your needs',
				'High-quality solution', 'Cutting-edge technology', 'Revolutionary product', 'Best in the industry', 'In conclusion', 'Unmatched quality',
				'100% guaranteed', 'Seamless experience', 'Robust solution', 'State-of-the-art', 'Game-changing', 'Perfect solution',
				'Excellent performance', 'Advanced technology', 'Top-notch', 'Industry-leading', 'We pride ourselves', 'Your trusted partner',
			),
			'forbidden_claims' => array(
				'No.1', 'Best manufacturer', 'World-leading', 'CE certified', 'ISO certified', 'Exported to more than X countries',
				'Fast delivery', 'Large stock', 'Factory direct', 'Lowest price', '100% safe', 'Guaranteed quality',
			),
			'seo_boundaries' => array(
				'seo_title_min' => 50,
				'seo_title_max' => 65,
				'meta_desc_min' => 120,
				'meta_desc_max' => 155,
				'faq_min'       => 3,
				'faq_max'       => 5,
				'paragraph_sentences_min' => 2,
				'paragraph_sentences_max' => 4,
				'allow_tables'  => 1,
				'allow_faq'     => 1,
				'allow_quick_answer' => 1,
				'allow_cta'     => 1,
			),
		);
	}

	public function get_rules() {
		$saved = get_option( $this->option_key, array() );
		return $this->merge_rules( $saved, $this->get_default_rules() );
	}

	public function save_rules( $raw ) {
		$defaults = $this->get_default_rules();
		$data     = $defaults;
		$data['writing_person']['post']    = $this->sanitize_person( $raw['writing_person_post'] ?? $defaults['writing_person']['post'] );
		$data['writing_person']['page']    = $this->sanitize_person( $raw['writing_person_page'] ?? $defaults['writing_person']['page'] );
		$data['writing_person']['product'] = $this->sanitize_person( $raw['writing_person_product'] ?? $defaults['writing_person']['product'] );
		$data['tone'] = $this->sanitize_tones( $raw['tone'] ?? $defaults['tone'] );
		$data['forbidden_ai_phrases'] = $this->sanitize_lines( $raw['forbidden_ai_phrases'] ?? $defaults['forbidden_ai_phrases'] );
		$data['forbidden_claims']     = $this->sanitize_lines( $raw['forbidden_claims'] ?? $defaults['forbidden_claims'] );
		$data['seo_boundaries'] = $this->sanitize_boundaries( $raw );
		return update_option( $this->option_key, $data );
	}

	public function get_rules_for_post_type( $post_type ) {
		$rules = $this->get_rules();
		$rules['current_writing_person'] = $rules['writing_person'][ $post_type ] ?? $rules['writing_person']['post'];
		return $rules;
	}

	public function get_prompt_rules_text( $post_type ) {
		$rules = $this->get_rules_for_post_type( $post_type );
		$bound = $rules['seo_boundaries'];
		$page_type_rules = $this->get_page_type_rule_text( $post_type );

		$text  = "You are a careful human B2B SEO editor.\n";
		$text .= "Google SEO is the first priority.\n";
		$text .= "Write for real buyers first, not for search engines only.\n";
		$text .= "Use a professional, factual, buyer-focused, non-hype tone.\n";
		$text .= "Avoid AI-style marketing language.\nDo not use generic filler sentences.\n";
		$text .= "Do not use the forbidden phrases listed below.\n";
		$text .= "Do not invent product specifications, certifications, case studies, countries, prices, delivery times, factory scale, stock, warranty, or rankings.\n";
		$text .= "If the original content does not provide a fact, do not present it as a company fact.\n";
		$text .= "If information is uncertain, add it to needs_human_review.\n";
		$text .= "Every paragraph must provide useful information for a real buyer.\nDo not keyword stuff.\nDo not generate hidden content.\n";
		$text .= "Return valid JSON only.\n\n";
		$text .= 'Writing person for this content: ' . $rules['current_writing_person'] . "\n";
		$text .= 'Tone presets: ' . implode( ', ', $rules['tone'] ) . "\n";
		$text .= 'Forbidden AI phrases: ' . implode( ' | ', $rules['forbidden_ai_phrases'] ) . "\n";
		$text .= 'Forbidden claims: ' . implode( ' | ', $rules['forbidden_claims'] ) . "\n";
		$text .= 'SEO title target: ' . $bound['seo_title_min'] . '-' . $bound['seo_title_max'] . " chars.\n";
		$text .= 'Meta description target: ' . $bound['meta_desc_min'] . '-' . $bound['meta_desc_max'] . " chars.\n";
		$text .= 'FAQ count target: ' . $bound['faq_min'] . '-' . $bound['faq_max'] . ".\n";
		$text .= 'Paragraph length target: ' . $bound['paragraph_sentences_min'] . '-' . $bound['paragraph_sentences_max'] . " sentences.\n";
		$text .= 'Allow tables: ' . ( ! empty( $bound['allow_tables'] ) ? 'yes' : 'no' ) . ".\n";
		$text .= 'Allow FAQ: ' . ( ! empty( $bound['allow_faq'] ) ? 'yes' : 'no' ) . ".\n";
		$text .= 'Allow Quick Answer: ' . ( ! empty( $bound['allow_quick_answer'] ) ? 'yes' : 'no' ) . ".\n";
		$text .= 'Allow CTA: ' . ( ! empty( $bound['allow_cta'] ) ? 'yes, restrained' : 'no' ) . ".\n\n";
		$text .= $page_type_rules;
		return $text;
	}

	private function get_page_type_rule_text( $post_type ) {
		if ( 'product' === $post_type ) {
			return "Page type rules (product): product definition, applications, selection factors, specifications to confirm, short description, long description, FAQ, buyer inquiry information.";
		}
		if ( 'page' === $post_type ) {
			return "Page type rules (page): clear value, factual company info, trust without fake claims, restrained CTA.";
		}
		return "Page type rules (post): definition, explanation, buyer guidance, FAQ, internal links.";
	}

	private function sanitize_person( $value ) {
		$allowed = array( 'second_person', 'third_person', 'first_person_plural', 'neutral' );
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : 'neutral';
	}
	private function sanitize_tones( $values ) {
		$allowed = array( 'professional', 'factual', 'concise', 'buyer-focused', 'technical', 'non-hype', 'human-edited' );
		$values = is_array( $values ) ? $values : array();
		$out = array();
		foreach ( $values as $v ) {
			$v = sanitize_text_field( (string) $v );
			if ( in_array( $v, $allowed, true ) ) { $out[] = $v; }
		}
		return ! empty( $out ) ? array_values( array_unique( $out ) ) : array( 'professional', 'factual', 'buyer-focused', 'non-hype' );
	}
	private function sanitize_lines( $value ) {
		if ( is_array( $value ) ) {
			$lines = $value;
		} else {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		}
		$out = array();
		foreach ( $lines as $line ) {
			$line = sanitize_text_field( trim( (string) $line ) );
			if ( '' !== $line ) { $out[] = $line; }
		}
		return array_values( array_unique( $out ) );
	}
	private function sanitize_boundaries( $raw ) {
		return array(
			'seo_title_min' => max( 10, absint( $raw['seo_title_min'] ?? 50 ) ),
			'seo_title_max' => max( 20, absint( $raw['seo_title_max'] ?? 65 ) ),
			'meta_desc_min' => max( 50, absint( $raw['meta_desc_min'] ?? 120 ) ),
			'meta_desc_max' => max( 80, absint( $raw['meta_desc_max'] ?? 155 ) ),
			'faq_min'       => max( 0, absint( $raw['faq_min'] ?? 3 ) ),
			'faq_max'       => max( 1, absint( $raw['faq_max'] ?? 5 ) ),
			'paragraph_sentences_min' => max( 1, absint( $raw['paragraph_sentences_min'] ?? 2 ) ),
			'paragraph_sentences_max' => max( 1, absint( $raw['paragraph_sentences_max'] ?? 4 ) ),
			'allow_tables'  => ! empty( $raw['allow_tables'] ) ? 1 : 0,
			'allow_faq'     => ! empty( $raw['allow_faq'] ) ? 1 : 0,
			'allow_quick_answer' => ! empty( $raw['allow_quick_answer'] ) ? 1 : 0,
			'allow_cta'     => ! empty( $raw['allow_cta'] ) ? 1 : 0,
		);
	}
	private function merge_rules( $saved, $defaults ) {
		if ( ! is_array( $saved ) ) { return $defaults; }
		return array_replace_recursive( $defaults, $saved );
	}
}
