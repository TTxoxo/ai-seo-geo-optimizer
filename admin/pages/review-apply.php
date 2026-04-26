<?php
/**
 * Review & apply page.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-geo-optimizer' ) );
}

$post_id = absint( $_GET['post_id'] ?? 0 );
if ( $post_id <= 0 || ! get_post( $post_id ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid post_id.', 'ai-seo-geo-optimizer' ) . '</p></div>';
	return;
}

if ( ! current_user_can( 'edit_post', $post_id ) ) {
	wp_die( esc_html__( 'You cannot edit this content.', 'ai-seo-geo-optimizer' ) );
}

$review_manager   = new AI_SEO_GEO_Review_Manager();
$optimizer        = new AI_SEO_GEO_Optimizer();
$provider_manager = new AI_SEO_GEO_AI_Provider_Manager();

$original_data = $review_manager->get_original_content_data( $post_id );
if ( ! $original_data ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Unable to load original content.', 'ai-seo-geo-optimizer' ) . '</p></div>';
	return;
}

$active_providers = $provider_manager->get_active_providers();
$notice           = '';
$notice_type      = 'success';
$ai_result        = null;

if ( isset( $_POST['ai_seo_geo_generate_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_generate_suggestions', 'ai_seo_geo_nonce' );

	$request_post_id = absint( $_POST['post_id'] ?? 0 );
	if ( $request_post_id !== $post_id ) {
		$notice      = __( 'post_id verification failed.', 'ai-seo-geo-optimizer' );
		$notice_type = 'error';
	} elseif ( empty( $active_providers ) ) {
		$notice      = __( 'No active provider available. Please enable a provider first.', 'ai-seo-geo-optimizer' );
		$notice_type = 'error';
	} else {
		$result = $optimizer->generate_suggestions(
			array(
				'post_id'        => $post_id,
				'provider_id'    => absint( $_POST['provider_id'] ?? 0 ),
				'model'          => sanitize_text_field( $_POST['model'] ?? '' ),
				'target_keyword' => sanitize_text_field( $_POST['target_keyword'] ?? '' ),
				'language'       => sanitize_text_field( $_POST['language'] ?? 'en' ),
				'brand_tone'     => sanitize_text_field( $_POST['brand_tone'] ?? 'professional' ),
				'fields'         => isset( $_POST['fields'] ) ? (array) $_POST['fields'] : array(),
			)
		);

		$notice      = $result['message'];
		$notice_type = ! empty( $result['success'] ) ? 'success' : 'error';
		if ( ! empty( $result['success'] ) ) {
			$ai_result = $result['result'];
		}
	}
}

if ( null === $ai_result ) {
	$latest = $review_manager->get_latest_job_result( $post_id );
	if ( ! empty( $latest['result'] ) ) {
		$ai_result = $latest['result'];
	}
}

$default_provider_id = ! empty( $active_providers ) ? (int) $active_providers[0]['id'] : 0;
$fields_options      = array( 'title', 'seo_title', 'meta_description', 'tags', 'excerpt', 'content', 'faq', 'internal_links', 'image_alt', 'schema' );
?>
<div class="wrap ai-seo-geo-wrap">
	<h1><?php esc_html_e( 'Review & Apply (Single Content)', 'ai-seo-geo-optimizer' ); ?></h1>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Original Content', 'ai-seo-geo-optimizer' ); ?></h2>
	<table class="widefat striped">
		<tbody>
		<tr><th><?php esc_html_e( 'Post ID', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( (string) $original_data['post_id'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Post Type', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['post_type'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Original Title', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['title'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Original Excerpt', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['excerpt'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Original Content', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo wp_kses_post( wpautop( $original_data['content'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Original Categories', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['categories'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Original Tags', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['tags'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Current SEO Title', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['current_seo_title'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Current Meta Description', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['current_meta_desc'] ); ?></td></tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'AI Suggestion Form', 'ai-seo-geo-optimizer' ); ?></h2>
	<?php if ( empty( $active_providers ) ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'No active provider found. Please configure AI Providers first.', 'ai-seo-geo-optimizer' ); ?></p></div>
	<?php else : ?>
	<form method="post" action="">
		<?php wp_nonce_field( 'ai_seo_geo_generate_suggestions', 'ai_seo_geo_nonce' ); ?>
		<input type="hidden" name="ai_seo_geo_generate_action" value="generate" />
		<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />

		<table class="form-table">
			<tr>
				<th><label for="provider_id"><?php esc_html_e( 'Provider', 'ai-seo-geo-optimizer' ); ?></label></th>
				<td>
					<select id="provider_id" name="provider_id">
						<?php foreach ( $active_providers as $provider ) : ?>
							<option value="<?php echo esc_attr( (string) $provider['id'] ); ?>" <?php selected( $default_provider_id, (int) $provider['id'] ); ?>>
								<?php echo esc_html( $provider['provider_name'] . ' (' . $provider['provider_key'] . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr><th><label for="model"><?php esc_html_e( 'Model', 'ai-seo-geo-optimizer' ); ?></label></th><td><input class="regular-text" type="text" id="model" name="model" value="" placeholder="gpt-4.1-mini" /></td></tr>
			<tr><th><label for="target_keyword"><?php esc_html_e( 'Target Keyword', 'ai-seo-geo-optimizer' ); ?></label></th><td><input class="regular-text" type="text" id="target_keyword" name="target_keyword" value="" /></td></tr>
			<tr><th><label for="language"><?php esc_html_e( 'Language', 'ai-seo-geo-optimizer' ); ?></label></th><td><input class="regular-text" type="text" id="language" name="language" value="en" /></td></tr>
			<tr><th><label for="brand_tone"><?php esc_html_e( 'Brand Tone', 'ai-seo-geo-optimizer' ); ?></label></th><td><input class="regular-text" type="text" id="brand_tone" name="brand_tone" value="professional" /></td></tr>
			<tr>
				<th><?php esc_html_e( 'Fields to Optimize', 'ai-seo-geo-optimizer' ); ?></th>
				<td>
					<?php foreach ( $fields_options as $field_name ) : ?>
						<label style="display:inline-block;min-width:180px;margin-bottom:4px;">
							<input type="checkbox" name="fields[]" value="<?php echo esc_attr( $field_name ); ?>" checked />
							<?php echo esc_html( $field_name ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Generate Suggestions', 'ai-seo-geo-optimizer' ) ); ?>
	</form>
	<?php endif; ?>

	<?php if ( ! empty( $ai_result ) && is_array( $ai_result ) ) : ?>
		<h2><?php esc_html_e( 'AI Suggestions', 'ai-seo-geo-optimizer' ); ?></h2>

		<?php if ( isset( $ai_result['risk_level'] ) && 'high' === strtolower( (string) $ai_result['risk_level'] ) ) : ?>
			<div class="notice notice-warning"><p><strong><?php esc_html_e( 'Risk level is HIGH. Please review carefully before applying any changes.', 'ai-seo-geo-optimizer' ); ?></strong></p></div>
		<?php endif; ?>

		<div style="display:flex;gap:16px;align-items:flex-start;">
			<div style="flex:1;min-width:0;">
				<h3><?php esc_html_e( 'Original', 'ai-seo-geo-optimizer' ); ?></h3>
				<div class="postbox" style="padding:12px;"><?php echo wp_kses_post( wpautop( $original_data['content'] ) ); ?></div>
			</div>
			<div style="flex:1;min-width:0;">
				<h3><?php esc_html_e( 'Optimized Content', 'ai-seo-geo-optimizer' ); ?></h3>
				<div class="postbox" style="padding:12px;"><?php echo wp_kses_post( wpautop( (string) ( $ai_result['optimized_content'] ?? '' ) ) ); ?></div>
			</div>
		</div>

		<table class="widefat striped" style="margin-top:16px;">
			<tbody>
			<?php
			$display_fields = array(
				'search_intent', 'primary_keyword', 'secondary_keywords', 'seo_title', 'meta_description', 'suggested_tags',
				'excerpt', 'faq', 'internal_link_suggestions', 'image_alt_suggestions', 'schema_suggestion', 'geo_summary',
				'fact_check_notes', 'needs_human_review', 'unsupported_claims_removed', 'content_score', 'risk_level',
			);
			foreach ( $display_fields as $display_key ) :
				$value = $ai_result[ $display_key ] ?? '';
				$is_highlight = in_array( $display_key, array( 'fact_check_notes', 'needs_human_review' ), true );
				?>
				<tr <?php echo $is_highlight ? 'style="background:#fff7e6;"' : ''; ?>>
					<th style="width:240px;"><?php echo esc_html( $display_key ); ?></th>
					<td><pre style="white-space:pre-wrap;"><?php echo esc_html( is_array( $value ) ? wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : (string) $value ); ?></pre></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p><em><?php esc_html_e( 'Auto-apply is disabled. Review and apply workflow will be completed in next phase.', 'ai-seo-geo-optimizer' ); ?></em></p>
	<?php endif; ?>
</div>
