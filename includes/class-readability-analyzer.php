<?php
/**
 * Readability analyzer class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_GEO_Readability_Analyzer {
	private $ai_phrases = array( "In today's competitive market", 'It is important to note that', 'Whether you are looking for', 'In conclusion', 'Furthermore', 'Moreover', 'In addition' );
	private $marketing_phrases = array( 'high-quality solution', 'cutting-edge technology', 'revolutionary product', 'best in the industry', 'unmatched quality', 'robust solution', 'state-of-the-art', 'game-changing', 'perfect solution', 'excellent performance', 'advanced technology', 'top-notch', 'industry-leading' );
	private $forbidden_phrases = array( 'No.1', '100% guaranteed', 'lowest price', 'best manufacturer', 'world-leading', 'fastest delivery', 'guaranteed quality' );

	public function analyze_content( $content, $target_keyword = '' ) {
		$content = (string) $content;
		$score   = 100;
		$forbidden = $this->detect_forbidden_phrases( $content );
		$marketing = $this->detect_generic_marketing_phrases( $content );
		$transitions = $this->detect_overused_transitions( $content );
		$keyword_warn = $this->detect_keyword_stuffing( $content, $target_keyword );

		$score -= count( $forbidden ) * 8;
		$score -= count( $marketing ) * 5;
		$score -= count( $transitions ) > 0 ? min( 15, count( $transitions ) * 5 ) : 0;
		$score -= count( $keyword_warn ) > 0 ? 15 : 0;
		if ( preg_match( '/\n\n?[^\n]{500,}/', $content ) ) {
			$score -= 8;
		}
		$score = max( 0, min( 100, $score ) );
		$risk  = $score >= 80 ? 'low' : ( $score >= 60 ? 'medium' : 'high' );

		$edits = array();
		if ( ! empty( $forbidden ) ) { $edits[] = __( 'Remove forbidden claims and replace with verifiable facts.', 'ai-seo-geo-optimizer' ); }
		if ( ! empty( $marketing ) ) { $edits[] = __( 'Replace generic marketing phrases with specific product facts.', 'ai-seo-geo-optimizer' ); }
		if ( ! empty( $keyword_warn ) ) { $edits[] = __( 'Reduce keyword repetition and use natural variants.', 'ai-seo-geo-optimizer' ); }
		if ( ! empty( $transitions ) ) { $edits[] = __( 'Reduce repeated transition words for more natural human tone.', 'ai-seo-geo-optimizer' ); }

		return array(
			'human_readability_score'   => $score,
			'ai_style_risk'             => $risk,
			'forbidden_phrases_found'   => $forbidden,
			'generic_marketing_phrases' => $marketing,
			'overused_transitions'      => $transitions,
			'keyword_stuffing_warnings' => $keyword_warn,
			'recommended_human_edits'   => $edits,
		);
	}

	public function detect_forbidden_phrases( $content ) { return $this->match_phrases( $content, $this->forbidden_phrases ); }
	public function detect_generic_marketing_phrases( $content ) { return array_merge( $this->match_phrases( $content, $this->marketing_phrases ), $this->match_phrases( $content, $this->ai_phrases ) ); }
	public function detect_overused_transitions( $content ) {
		$items = array();
		foreach ( array( 'Furthermore', 'Moreover', 'In addition' ) as $phrase ) {
			$count = preg_match_all( '/\b' . preg_quote( $phrase, '/' ) . '\b/i', (string) $content );
			if ( $count >= 3 ) { $items[] = $phrase . ' x' . $count; }
		}
		return $items;
	}
	public function detect_keyword_stuffing( $content, $target_keyword ) {
		$target_keyword = trim( (string) $target_keyword );
		if ( '' === $target_keyword ) { return array(); }
		$plain = strtolower( wp_strip_all_tags( (string) $content ) );
		$total = max( 1, str_word_count( $plain ) );
		$kw_count = preg_match_all( '/\b' . preg_quote( strtolower( $target_keyword ), '/' ) . '\b/', $plain );
		$density = ( $kw_count / $total ) * 100;
		return $density > 3.5 ? array( sprintf( 'Keyword density %.2f%% is high for "%s".', $density, $target_keyword ) ) : array();
	}
	public function calculate_human_readability_score( $content ) { $r = $this->analyze_content( $content ); return (int) $r['human_readability_score']; }
	private function match_phrases( $content, $phrases ) { $f=array(); foreach($phrases as $p){ if(preg_match('/\b'.preg_quote($p,'/').'\b/i',(string)$content)){ $f[]=$p; } } return $f; }
}
