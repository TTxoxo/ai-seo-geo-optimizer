<?php
/**
 * Content optimizer page.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-geo-optimizer' ) );
}

$content_query    = new AI_SEO_GEO_Content_Query();
$filter_options   = $content_query->get_filter_options();
$filters          = $content_query->sanitize_filters( $_GET );
$list_result      = $content_query->query_contents( $filters );
$bulk_notice      = '';
$bulk_notice_type = 'info';

if ( isset( $_POST['ai_seo_geo_bulk_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_bulk_optimize_action', 'ai_seo_geo_nonce' );
	$bulk_notice = __( 'Bulk optimization will be available in the next phase.', 'ai-seo-geo-optimizer' );
}

$base_url = admin_url( 'admin.php?page=ai-seo-geo-content' );
?>
<div class="wrap ai-seo-geo-wrap">
	<h1><?php esc_html_e( 'Content Optimizer', 'ai-seo-geo-optimizer' ); ?></h1>

	<?php if ( ! empty( $bulk_notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $bulk_notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $bulk_notice ); ?></p>
		</div>
	<?php endif; ?>

	<form method="get" action="">
		<input type="hidden" name="page" value="ai-seo-geo-content" />
		<table class="ai-seo-geo-filters">
			<tr>
				<td>
					<label for="post_type"><?php esc_html_e( 'Content Type', 'ai-seo-geo-optimizer' ); ?></label><br />
					<select id="post_type" name="post_type">
						<option value="all"><?php esc_html_e( 'All', 'ai-seo-geo-optimizer' ); ?></option>
						<?php foreach ( $filter_options['post_types'] as $type_key => $type_label ) : ?>
							<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $filters['post_type'], $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<label for="category"><?php esc_html_e( 'Category', 'ai-seo-geo-optimizer' ); ?></label><br />
					<select id="category" name="category">
						<option value="0"><?php esc_html_e( 'All Categories', 'ai-seo-geo-optimizer' ); ?></option>
						<?php foreach ( $filter_options['categories'] as $term_id => $term_name ) : ?>
							<option value="<?php echo esc_attr( (string) $term_id ); ?>" <?php selected( $filters['category'], $term_id ); ?>><?php echo esc_html( $term_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<label for="post_tag"><?php esc_html_e( 'Tag', 'ai-seo-geo-optimizer' ); ?></label><br />
					<select id="post_tag" name="post_tag">
						<option value="0"><?php esc_html_e( 'All Tags', 'ai-seo-geo-optimizer' ); ?></option>
						<?php foreach ( $filter_options['post_tags'] as $term_id => $term_name ) : ?>
							<option value="<?php echo esc_attr( (string) $term_id ); ?>" <?php selected( $filters['post_tag'], $term_id ); ?>><?php echo esc_html( $term_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<?php if ( $filter_options['has_products'] ) : ?>
				<td>
					<label for="product_cat"><?php esc_html_e( 'Product Category', 'ai-seo-geo-optimizer' ); ?></label><br />
					<select id="product_cat" name="product_cat">
						<option value="0"><?php esc_html_e( 'All Product Categories', 'ai-seo-geo-optimizer' ); ?></option>
						<?php foreach ( $filter_options['product_cats'] as $term_id => $term_name ) : ?>
							<option value="<?php echo esc_attr( (string) $term_id ); ?>" <?php selected( $filters['product_cat'], $term_id ); ?>><?php echo esc_html( $term_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<label for="product_tag"><?php esc_html_e( 'Product Tag', 'ai-seo-geo-optimizer' ); ?></label><br />
					<select id="product_tag" name="product_tag">
						<option value="0"><?php esc_html_e( 'All Product Tags', 'ai-seo-geo-optimizer' ); ?></option>
						<?php foreach ( $filter_options['product_tags'] as $term_id => $term_name ) : ?>
							<option value="<?php echo esc_attr( (string) $term_id ); ?>" <?php selected( $filters['product_tag'], $term_id ); ?>><?php echo esc_html( $term_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<?php endif; ?>
			</tr>
			<tr>
				<td>
					<label for="optimization_status"><?php esc_html_e( 'AI Optimization Status', 'ai-seo-geo-optimizer' ); ?></label><br />
					<select id="optimization_status" name="optimization_status">
						<option value="all" <?php selected( $filters['optimization_status'], 'all' ); ?>><?php esc_html_e( 'All', 'ai-seo-geo-optimizer' ); ?></option>
						<option value="optimized" <?php selected( $filters['optimization_status'], 'optimized' ); ?>><?php esc_html_e( 'Optimized', 'ai-seo-geo-optimizer' ); ?></option>
						<option value="not_optimized" <?php selected( $filters['optimization_status'], 'not_optimized' ); ?>><?php esc_html_e( 'Not Optimized', 'ai-seo-geo-optimizer' ); ?></option>
					</select>
				</td>
				<td>
					<label for="keyword"><?php esc_html_e( 'Keyword', 'ai-seo-geo-optimizer' ); ?></label><br />
					<input id="keyword" type="search" name="keyword" value="<?php echo esc_attr( $filters['keyword'] ); ?>" />
				</td>
				<td>
					<label for="per_page"><?php esc_html_e( 'Per Page', 'ai-seo-geo-optimizer' ); ?></label><br />
					<select id="per_page" name="per_page">
						<?php foreach ( $filter_options['per_page_list'] as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( $filters['per_page'], $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td colspan="3">
					<br />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'ai-seo-geo-optimizer' ); ?></button>
					<a class="button" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Reset', 'ai-seo-geo-optimizer' ); ?></a>
				</td>
			</tr>
		</table>
	</form>

	<form method="post" action="">
		<?php wp_nonce_field( 'ai_seo_geo_bulk_optimize_action', 'ai_seo_geo_nonce' ); ?>
		<input type="hidden" name="ai_seo_geo_bulk_action" value="bulk_optimize" />
		<p>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Bulk Optimize', 'ai-seo-geo-optimizer' ); ?></button>
			<span class="description"><?php esc_html_e( 'Bulk optimization will be available in the next phase.', 'ai-seo-geo-optimizer' ); ?></span>
		</p>

		<table class="widefat striped">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" id="ai-seo-geo-check-all" /></td>
					<th><?php esc_html_e( 'ID', 'ai-seo-geo-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Title', 'ai-seo-geo-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Type', 'ai-seo-geo-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Categories', 'ai-seo-geo-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Tags', 'ai-seo-geo-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Published', 'ai-seo-geo-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'ai-seo-geo-optimizer' ); ?></th>
					<th><?php esc_html_e( 'SEO Status', 'ai-seo-geo-optimizer' ); ?></th>
					<th><?php esc_html_e( 'AI Status', 'ai-seo-geo-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ai-seo-geo-optimizer' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $list_result['items'] ) ) : ?>
				<tr>
					<td colspan="11"><?php esc_html_e( 'No content found for current filters.', 'ai-seo-geo-optimizer' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $list_result['items'] as $item ) : ?>
					<tr>
						<td><input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( (string) $item['id'] ); ?>" /></td>
						<td><?php echo esc_html( (string) $item['id'] ); ?></td>
						<td><?php echo esc_html( $item['title'] ); ?></td>
						<td><?php echo esc_html( $item['post_type'] ); ?></td>
						<td><?php echo esc_html( $item['categories'] ); ?></td>
						<td><?php echo esc_html( $item['tags'] ); ?></td>
						<td><?php echo esc_html( $item['published_at'] ); ?></td>
						<td><?php echo esc_html( $item['updated_at'] ); ?></td>
						<td><?php echo esc_html( $item['seo_status'] ); ?></td>
						<td><?php echo esc_html( $item['optimization_status'] ); ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( $item['view_link'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'ai-seo-geo-optimizer' ); ?></a>
							<a class="button button-small" href="<?php echo esc_url( $item['edit_link'] ); ?>"><?php esc_html_e( 'Edit', 'ai-seo-geo-optimizer' ); ?></a>
							<a class="button button-primary button-small" href="<?php echo esc_url( $item['optimize_link'] ); ?>"><?php esc_html_e( 'Optimize', 'ai-seo-geo-optimizer' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</form>

	<?php
	$total_pages = max( 1, (int) $list_result['total_pages'] );
	$current     = min( max( 1, (int) $list_result['current_page'] ), $total_pages );

	$pagination_base_args = array(
		'page'                => 'ai-seo-geo-content',
		'post_type'           => $filters['post_type'],
		'category'            => $filters['category'],
		'post_tag'            => $filters['post_tag'],
		'product_cat'         => $filters['product_cat'],
		'product_tag'         => $filters['product_tag'],
		'keyword'             => $filters['keyword'],
		'optimization_status' => $filters['optimization_status'],
		'per_page'            => $filters['per_page'],
	);

	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo wp_kses_post(
		paginate_links(
			array(
				'base'      => esc_url_raw( add_query_arg( array_merge( $pagination_base_args, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ) ),
				'format'    => '',
				'current'   => $current,
				'total'     => $total_pages,
				'prev_text' => __( '&laquo;', 'ai-seo-geo-optimizer' ),
				'next_text' => __( '&raquo;', 'ai-seo-geo-optimizer' ),
			)
		)
	);
	echo '</div></div>';
	?>
</div>
