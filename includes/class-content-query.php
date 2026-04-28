<?php
/**
 * Content query class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles content listing and filters for optimizer page.
 */
class AI_SEO_GEO_Content_Query {

	/**
	 * Checks whether WooCommerce product content is available.
	 *
	 * @return bool
	 */
	public function is_woocommerce_enabled() {
		return class_exists( 'WooCommerce' ) || post_type_exists( 'product' );
	}

	/**
	 * Gets supported post types.
	 *
	 * @return array
	 */
	public function get_supported_post_types() {
		$post_types = array(
			'post' => __( 'Post', 'ai-seo-geo-optimizer' ),
			'page' => __( 'Page', 'ai-seo-geo-optimizer' ),
		);

		if ( $this->is_woocommerce_enabled() ) {
			$post_types['product'] = __( 'Product', 'ai-seo-geo-optimizer' );
		}

		return $post_types;
	}

	/**
	 * Gets list filters options.
	 *
	 * @return array
	 */
	public function get_filter_options() {
		$options = array(
			'post_types'    => $this->get_supported_post_types(),
			'categories'    => $this->get_taxonomy_terms( 'category' ),
			'post_tags'     => $this->get_taxonomy_terms( 'post_tag' ),
			'product_cats'  => array(),
			'product_tags'  => array(),
			'has_products'  => $this->is_woocommerce_enabled(),
			'per_page_list' => array( 10, 20, 50, 100 ),
		);

		if ( $options['has_products'] ) {
			$options['product_cats'] = $this->get_taxonomy_terms( 'product_cat' );
			$options['product_tags'] = $this->get_taxonomy_terms( 'product_tag' );
		}

		return $options;
	}

	/**
	 * Sanitizes and normalizes incoming filters.
	 *
	 * @param array $input Raw request input.
	 *
	 * @return array
	 */
	public function sanitize_filters( $input ) {
		$supported_types = array_keys( $this->get_supported_post_types() );
		$per_page_values = array( 10, 20, 50, 100 );

		$post_type = AI_SEO_GEO_Security::sanitize_text( $input['post_type'] ?? 'all' );
		if ( 'all' !== $post_type && ! in_array( $post_type, $supported_types, true ) ) {
			$post_type = 'all';
		}

		$optimization_status = AI_SEO_GEO_Security::sanitize_text( $input['optimization_status'] ?? 'all' );
		if ( ! in_array( $optimization_status, array( 'all', 'optimized', 'not_optimized' ), true ) ) {
			$optimization_status = 'all';
		}

		$keyword = AI_SEO_GEO_Security::sanitize_text( $input['keyword'] ?? '' );
		$keyword = mb_substr( $keyword, 0, 200 );

		$per_page = absint( $input['per_page'] ?? 20 );
		if ( ! in_array( $per_page, $per_page_values, true ) ) {
			$per_page = 20;
		}

		$paged = absint( $input['paged'] ?? 1 );
		if ( $paged < 1 ) {
			$paged = 1;
		}
		if ( $paged > 9999 ) {
			$paged = 9999;
		}

		$filters = array(
			'post_type'           => $post_type,
			'category'            => absint( $input['category'] ?? 0 ),
			'post_tag'            => absint( $input['post_tag'] ?? 0 ),
			'product_cat'         => absint( $input['product_cat'] ?? 0 ),
			'product_tag'         => absint( $input['product_tag'] ?? 0 ),
			'keyword'             => $keyword,
			'optimization_status' => $optimization_status,
			'per_page'            => $per_page,
			'paged'               => $paged,
		);

		if ( ! $this->is_woocommerce_enabled() ) {
			$filters['product_cat'] = 0;
			$filters['product_tag'] = 0;
		}

		return $filters;
	}

	/**
	 * Queries content list using filters.
	 *
	 * @param array $filters Sanitized filters.
	 *
	 * @return array
	 */
	public function query_contents( $filters ) {
		$args = array(
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'post_type'      => 'all' === $filters['post_type'] ? array_keys( $this->get_supported_post_types() ) : $filters['post_type'],
			'posts_per_page' => $filters['per_page'],
			'paged'          => $filters['paged'],
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => $filters['keyword'],
			'meta_query'     => array(),
			'tax_query'      => array(),
		);

		if ( 'optimized' === $filters['optimization_status'] ) {
			$args['meta_query'][] = array(
				'key'     => '_ai_seo_geo_optimized',
				'compare' => 'EXISTS',
			);
		}

		if ( 'not_optimized' === $filters['optimization_status'] ) {
			$args['meta_query'][] = array(
				'key'     => '_ai_seo_geo_optimized',
				'compare' => 'NOT EXISTS',
			);
		}

		if ( $filters['category'] > 0 ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $filters['category'],
			);
		}

		if ( $filters['post_tag'] > 0 ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $filters['post_tag'],
			);
		}

		if ( $this->is_woocommerce_enabled() && $filters['product_cat'] > 0 ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $filters['product_cat'],
			);
		}

		if ( $this->is_woocommerce_enabled() && $filters['product_tag'] > 0 ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'term_id',
				'terms'    => $filters['product_tag'],
			);
		}

		if ( count( $args['tax_query'] ) > 1 ) {
			$args['tax_query']['relation'] = 'AND';
		}

		if ( empty( $args['tax_query'] ) ) {
			unset( $args['tax_query'] );
		}

		if ( empty( $args['meta_query'] ) ) {
			unset( $args['meta_query'] );
		}

		$query = new WP_Query( $args );

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->format_row_item( $post );
		}

		return array(
			'items'        => $items,
			'total_items'  => (int) $query->found_posts,
			'total_pages'  => (int) $query->max_num_pages,
			'current_page' => (int) $filters['paged'],
			'per_page'     => (int) $filters['per_page'],
			'query_args'   => $args,
		);
	}

	/**
	 * Formats row data for list table output.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return array
	 */
	private function format_row_item( $post ) {
		$post_id   = (int) $post->ID;
		$post_type = (string) $post->post_type;
		$title     = get_the_title( $post_id );

		if ( '' === $title ) {
			$title = __( '(no title)', 'ai-seo-geo-optimizer' );
		}

		return array(
			'id'                 => $post_id,
			'title'              => $title,
			'post_type'          => $post_type,
			'categories'         => $this->get_term_names_for_post( $post_id, $post_type, 'category', 'product_cat' ),
			'tags'               => $this->get_term_names_for_post( $post_id, $post_type, 'post_tag', 'product_tag' ),
			'published_at'       => get_the_date( 'Y-m-d H:i', $post_id ),
			'updated_at'         => get_the_modified_date( 'Y-m-d H:i', $post_id ),
			'seo_status'         => $this->get_seo_status( $post_id ),
			'optimization_status'=> $this->get_optimization_status( $post_id ),
			'view_link'          => get_permalink( $post_id ),
			'edit_link'          => get_edit_post_link( $post_id, '' ),
			'optimize_link'      => add_query_arg(
				array(
					'page'    => 'ai-seo-geo-review-apply',
					'post_id' => $post_id,
				),
				admin_url( 'admin.php' )
			),
		);
	}

	/**
	 * Gets taxonomy terms list for filters.
	 *
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array
	 */
	private function get_taxonomy_terms( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$output = array();
		foreach ( $terms as $term ) {
			$output[ (int) $term->term_id ] = $term->name;
		}

		return $output;
	}

	/**
	 * Gets readable term names by post and taxonomy.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $post_type    Post type.
	 * @param string $default_tax  Non-product taxonomy.
	 * @param string $product_tax  Product taxonomy.
	 *
	 * @return string
	 */
	private function get_term_names_for_post( $post_id, $post_type, $default_tax, $product_tax ) {
		$taxonomy = 'product' === $post_type ? $product_tax : $default_tax;

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '—';
		}

		$terms = get_the_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '—';
		}

		$names = wp_list_pluck( $terms, 'name' );
		return implode( ', ', $names );
	}

	/**
	 * Gets AI optimization status.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function get_optimization_status( $post_id ) {
		$value = get_post_meta( $post_id, '_ai_seo_geo_optimized', true );

		if ( '' !== (string) $value ) {
			return __( 'Optimized', 'ai-seo-geo-optimizer' );
		}

		return __( 'Not Optimized', 'ai-seo-geo-optimizer' );
	}

	/**
	 * Gets SEO status.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function get_seo_status( $post_id ) {
		$yoast_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
		$yoast_desc  = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		$rank_title  = get_post_meta( $post_id, 'rank_math_title', true );
		$rank_desc   = get_post_meta( $post_id, 'rank_math_description', true );
		$ai_title    = get_post_meta( $post_id, '_ai_seo_geo_title', true );
		$ai_desc     = get_post_meta( $post_id, '_ai_seo_geo_description', true );

		if ( '' !== (string) $yoast_title || '' !== (string) $yoast_desc || '' !== (string) $rank_title || '' !== (string) $rank_desc || '' !== (string) $ai_title || '' !== (string) $ai_desc ) {
			return __( 'Configured', 'ai-seo-geo-optimizer' );
		}

		return __( 'Not Set', 'ai-seo-geo-optimizer' );
	}
}
