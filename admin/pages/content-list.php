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
$optimizer        = new AI_SEO_GEO_Optimizer();
$provider_manager = new AI_SEO_GEO_AI_Provider_Manager();
$filter_options   = $content_query->get_filter_options();
$filters          = $content_query->sanitize_filters( $_GET );
$list_result      = $content_query->query_contents( $filters );
$active_providers = $provider_manager->get_active_providers();
$bulk_notice      = '';
$bulk_notice_type = 'info';
$bulk_preview     = null;
$max_bulk_items   = 10;
$bulk_fields_map  = array(
	'title'            => __( 'Title', 'ai-seo-geo-optimizer' ),
	'seo_title'        => __( 'SEO Title', 'ai-seo-geo-optimizer' ),
	'meta_description' => __( 'Meta Description', 'ai-seo-geo-optimizer' ),
	'excerpt'          => __( 'Excerpt', 'ai-seo-geo-optimizer' ),
	'optimized_content'=> __( 'Main Content', 'ai-seo-geo-optimizer' ),
	'suggested_tags'   => __( 'Tags', 'ai-seo-geo-optimizer' ),
);

if ( isset( $_POST['ai_seo_geo_bulk_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_bulk_optimize_action', 'ai_seo_geo_nonce' );
	$action = AI_SEO_GEO_Security::sanitize_text( wp_unslash( $_POST['ai_seo_geo_bulk_action'] ) );

	$post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) ) : array();
	$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );

	if ( empty( $post_ids ) ) {
		$bulk_notice      = __( 'Please select at least one content item for bulk draft creation.', 'ai-seo-geo-optimizer' );
		$bulk_notice_type = 'warning';
	} elseif ( count( $post_ids ) > $max_bulk_items ) {
		$bulk_notice      = sprintf( __( 'You selected %1$d items. Maximum %2$d items per batch draft. Please split into smaller batches.', 'ai-seo-geo-optimizer' ), count( $post_ids ), $max_bulk_items );
		$bulk_notice_type = 'error';
	} elseif ( 'prepare_bulk_optimize' === $action ) {
		if ( empty( $active_providers ) ) {
			$bulk_notice      = __( 'No active provider available. Please enable at least one provider before creating batch drafts.', 'ai-seo-geo-optimizer' );
			$bulk_notice_type = 'error';
		}

		$type_counts = array();
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$type_key = sanitize_key( $post->post_type );
			if ( ! isset( $type_counts[ $type_key ] ) ) {
				$type_counts[ $type_key ] = 0;
			}
			++$type_counts[ $type_key ];
		}

		$default_provider = ! empty( $active_providers ) ? $active_providers[0] : null;
		if ( ! empty( $default_provider ) ) {
			$bulk_preview = array(
				'post_ids'        => $post_ids,
				'selected_count'  => count( $post_ids ),
				'type_counts'     => $type_counts,
				'provider_id'     => (int) $default_provider['id'],
				'model'           => $default_provider['default_model'],
				'target_keyword'  => '',
				'language'        => 'en',
				'brand_tone'      => 'professional',
				'selected_fields' => array( 'title', 'seo_title', 'meta_description', 'optimized_content' ),
			);
		}
	} elseif ( 'create_bulk_draft' === $action ) {
		$selected_fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fields'] ) ) : array();
		$allowed_fields  = array_keys( $bulk_fields_map );
		$selected_fields = array_values( array_intersect( $selected_fields, $allowed_fields ) );

		$result = $optimizer->create_batch_draft_jobs(
			array(
				'post_ids'        => $post_ids,
				'provider_id'     => absint( $_POST['provider_id'] ?? 0 ),
				'model'           => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
				'target_keyword'  => sanitize_text_field( wp_unslash( $_POST['target_keyword'] ?? '' ) ),
				'language'        => sanitize_text_field( wp_unslash( $_POST['language'] ?? 'en' ) ),
				'brand_tone'      => sanitize_text_field( wp_unslash( $_POST['brand_tone'] ?? 'professional' ) ),
				'fields'          => $selected_fields,
			)
		);

		$bulk_notice      = $result['message'];
		$bulk_notice_type = ! empty( $result['success'] ) ? 'success' : 'error';

		if ( ! empty( $result['success'] ) ) {
			$bulk_notice .= ' ' . __( 'Batch queue execution will be opened in a future version. Every item still requires manual review before apply.', 'ai-seo-geo-optimizer' );
		}
	}
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
		<input type="hidden" name="ai_seo_geo_bulk_action" value="prepare_bulk_optimize" />
		<p>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Bulk Optimize', 'ai-seo-geo-optimizer' ); ?></button>
			<span class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: max items per batch */
						__( 'Draft only: max %d items each batch. No auto apply, no auto publish.', 'ai-seo-geo-optimizer' ),
						$max_bulk_items
					)
				);
				?>
			</span>
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

	<?php if ( is_array( $bulk_preview ) ) : ?>
		<div class="ai-seo-geo-bulk-confirm card">
			<h2><?php esc_html_e( 'Bulk Optimization Draft Confirmation', 'ai-seo-geo-optimizer' ); ?></h2>
			<p class="description"><?php esc_html_e( 'This step only creates draft jobs. It does not run bulk AI requests and does not modify live content.', 'ai-seo-geo-optimizer' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Selected items:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) $bulk_preview['selected_count'] ); ?></li>
				<li>
					<strong><?php esc_html_e( 'Content type statistics:', 'ai-seo-geo-optimizer' ); ?></strong>
					<?php
					$type_parts = array();
					foreach ( $bulk_preview['type_counts'] as $type_key => $type_count ) {
						$type_parts[] = sprintf( '%1$s: %2$d', $type_key, (int) $type_count );
					}
					echo esc_html( empty( $type_parts ) ? __( 'N/A', 'ai-seo-geo-optimizer' ) : implode( ', ', $type_parts ) );
					?>
				</li>
			</ul>

			<form method="post" action="">
				<?php wp_nonce_field( 'ai_seo_geo_bulk_optimize_action', 'ai_seo_geo_nonce' ); ?>
				<input type="hidden" name="ai_seo_geo_bulk_action" value="create_bulk_draft" />
				<?php foreach ( $bulk_preview['post_ids'] as $post_id ) : ?>
					<input type="hidden" name="post_ids[]" value="<?php echo esc_attr( (string) $post_id ); ?>" />
				<?php endforeach; ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="provider_id"><?php esc_html_e( 'Provider', 'ai-seo-geo-optimizer' ); ?></label></th>
						<td>
							<select id="provider_id" name="provider_id" required>
								<?php foreach ( $active_providers as $provider ) : ?>
									<option value="<?php echo esc_attr( (string) $provider['id'] ); ?>" <?php selected( (int) $bulk_preview['provider_id'], (int) $provider['id'] ); ?>>
										<?php echo esc_html( $provider['provider_name'] . ' (' . $provider['provider_key'] . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="model"><?php esc_html_e( 'Model', 'ai-seo-geo-optimizer' ); ?></label></th>
						<td><input id="model" name="model" type="text" class="regular-text" value="<?php echo esc_attr( $bulk_preview['model'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="target_keyword"><?php esc_html_e( 'Target Keyword', 'ai-seo-geo-optimizer' ); ?></label></th>
						<td><input id="target_keyword" name="target_keyword" type="text" class="regular-text" value="<?php echo esc_attr( $bulk_preview['target_keyword'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="language"><?php esc_html_e( 'Language', 'ai-seo-geo-optimizer' ); ?></label></th>
						<td><input id="language" name="language" type="text" class="regular-text" value="<?php echo esc_attr( $bulk_preview['language'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="brand_tone"><?php esc_html_e( 'Brand Tone', 'ai-seo-geo-optimizer' ); ?></label></th>
						<td><input id="brand_tone" name="brand_tone" type="text" class="regular-text" value="<?php echo esc_attr( $bulk_preview['brand_tone'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Estimated optimization fields', 'ai-seo-geo-optimizer' ); ?></th>
						<td>
							<?php foreach ( $bulk_fields_map as $field_key => $field_label ) : ?>
								<label>
									<input type="checkbox" name="fields[]" value="<?php echo esc_attr( $field_key ); ?>" <?php checked( in_array( $field_key, $bulk_preview['selected_fields'], true ) ); ?> />
									<?php echo esc_html( $field_label ); ?>
								</label><br />
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Bulk drafts are always review-first. Selected fields are only metadata for future queue versions.', 'ai-seo-geo-optimizer' ); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Batch Draft Jobs', 'ai-seo-geo-optimizer' ); ?></button>
				</p>
				<p class="description">
					<?php esc_html_e( 'Safety guardrails: no auto queue, no auto publish, no direct online overwrite. Each result must be reviewed per post.', 'ai-seo-geo-optimizer' ); ?>
				</p>
			</form>
		</div>
	<?php endif; ?>

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
