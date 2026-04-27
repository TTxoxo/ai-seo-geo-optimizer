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

$review_manager    = new AI_SEO_GEO_Review_Manager();
$optimizer         = new AI_SEO_GEO_Optimizer();
$provider_manager  = new AI_SEO_GEO_AI_Provider_Manager();
$revision_manager  = new AI_SEO_GEO_Revision_Manager();
$original_data     = $review_manager->get_original_content_data( $post_id );
$active_providers  = $provider_manager->get_active_providers();
$fields_options    = array( 'title', 'seo_title', 'meta_description', 'tags', 'excerpt', 'content', 'faq', 'internal_links', 'image_alt', 'schema' );
$notice            = '';
$notice_type       = 'success';
$ai_result         = null;
$current_job_id    = 0;
$json_debug_info   = array();

if ( ! $original_data ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Unable to load original content.', 'ai-seo-geo-optimizer' ) . '</p></div>';
	return;
}

$content_length = mb_strlen( wp_strip_all_tags( (string) $original_data['content'] ) );

if ( isset( $_POST['ai_seo_geo_generate_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_generate_suggestions', 'ai_seo_geo_nonce' );
	$result = $optimizer->generate_suggestions(
		array(
			'post_id'        => $post_id,
			'provider_id'    => absint( $_POST['provider_id'] ?? 0 ),
			'model'          => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
			'target_keyword' => sanitize_text_field( wp_unslash( $_POST['target_keyword'] ?? '' ) ),
			'language'       => sanitize_text_field( wp_unslash( $_POST['language'] ?? 'en' ) ),
			'brand_tone'     => sanitize_text_field( wp_unslash( $_POST['brand_tone'] ?? 'professional' ) ),
			'max_tokens'     => absint( $_POST['max_tokens'] ?? 0 ),
			'fields'         => isset( $_POST['fields'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['fields'] ) ) : array(),
		)
	);
	$notice         = $result['message'];
	$notice_type    = ! empty( $result['success'] ) ? 'success' : 'error';
	$current_job_id = absint( $result['job_id'] ?? 0 );
	$json_debug_info = isset( $result['debug'] ) && is_array( $result['debug'] ) ? $result['debug'] : array();
	if ( ! empty( $result['success'] ) ) {
		$ai_result = $result['result'];
	}
}

if ( isset( $_POST['ai_seo_geo_apply_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_apply_changes', 'ai_seo_geo_apply_nonce' );
	$job_id          = absint( $_POST['job_id'] ?? 0 );
	$selected_fields = isset( $_POST['apply_fields'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['apply_fields'] ) ) : array();
	$apply_result    = $revision_manager->apply_selected_changes( $job_id, $post_id, $selected_fields );
	$notice          = $apply_result['message'];
	$notice_type     = ! empty( $apply_result['success'] ) ? 'success' : 'error';
}

if ( isset( $_POST['ai_seo_geo_rollback_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_rollback_snapshot', 'ai_seo_geo_rollback_nonce' );
	$rollback_result = $revision_manager->rollback_snapshot( absint( $_POST['snapshot_id'] ?? 0 ) );
	$notice          = $rollback_result['message'];
	$notice_type     = ! empty( $rollback_result['success'] ) ? 'success' : 'error';
	$original_data   = $review_manager->get_original_content_data( $post_id );
}

if ( null === $ai_result ) {
	$latest = $review_manager->get_latest_job_result( $post_id );
	if ( ! empty( $latest['result'] ) ) {
		$ai_result      = $latest['result'];
		$current_job_id = absint( $latest['job']['id'] ?? 0 );
	}
}

$snapshots = $revision_manager->get_snapshots_by_post( $post_id );
?>
<div class="wrap ai-seo-geo-wrap">
	<h1><?php esc_html_e( 'Review & Apply (Single Content)', 'ai-seo-geo-optimizer' ); ?></h1>
	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $json_debug_info['is_json_parse_failed'] ) ) : ?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'JSON Parse Failed - Debug Info', 'ai-seo-geo-optimizer' ); ?></strong></p>
			<ul style="margin-left: 18px;">
				<li><strong><?php esc_html_e( 'Provider Key:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) ( $json_debug_info['provider_key'] ?? '' ) ); ?></li>
				<li><strong><?php esc_html_e( 'Model:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) ( $json_debug_info['model'] ?? '' ) ); ?></li>
				<li><strong><?php esc_html_e( 'HTTP Status:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) absint( $json_debug_info['http_status'] ?? 0 ) ); ?></li>
				<li><strong><?php esc_html_e( 'json_last_error_msg():', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) ( $json_debug_info['json_error'] ?? '' ) ); ?></li>
				<li><strong><?php esc_html_e( 'Looks like Markdown code block:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( ! empty( $json_debug_info['looks_like_markdown'] ) ? __( 'Yes', 'ai-seo-geo-optimizer' ) : __( 'No', 'ai-seo-geo-optimizer' ) ); ?></li>
				<li><strong><?php esc_html_e( 'Looks truncated:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( ! empty( $json_debug_info['looks_truncated'] ) ? __( 'Yes', 'ai-seo-geo-optimizer' ) : __( 'No', 'ai-seo-geo-optimizer' ) ); ?></li>
			</ul>
			<p><strong><?php esc_html_e( 'Raw Response Preview (max 500 chars):', 'ai-seo-geo-optimizer' ); ?></strong></p>
			<pre style="white-space:pre-wrap;max-height:260px;overflow:auto;"><?php echo esc_html( (string) mb_substr( (string) ( $json_debug_info['raw_response_preview'] ?? '' ), 0, 500 ) ); ?></pre>
			<?php
			$raw_preview_tail = trim( (string) ( $json_debug_info['raw_response_preview'] ?? '' ) );
			$last_char        = '' === $raw_preview_tail ? '' : (string) mb_substr( $raw_preview_tail, -1 );
			?>
			<?php if ( ! empty( $json_debug_info['likely_truncated'] ) || '}' !== $last_char ) : ?>
				<p><strong><?php esc_html_e( 'AI response may be truncated. Please reduce selected fields or increase max_tokens.', 'ai-seo-geo-optimizer' ); ?></strong></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( $content_length > 8000 ) : ?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( '内容较长，建议先只优化标题、Meta Description、标签，或分段优化正文。', 'ai-seo-geo-optimizer' ); ?></strong></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Original Content', 'ai-seo-geo-optimizer' ); ?></h2>
	<table class="widefat striped"><tbody>
	<tr><th><?php esc_html_e( 'Post ID', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( (string) $original_data['post_id'] ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Post Type', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['post_type'] ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Original Title', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['title'] ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Original Excerpt', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['excerpt'] ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Original Content', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo wp_kses_post( wpautop( $original_data['content'] ) ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Original Categories', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['categories'] ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Original Tags', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['tags'] ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Current SEO Title', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['current_seo_title'] ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Current Meta Description', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( $original_data['current_meta_desc'] ); ?></td></tr>
	</tbody></table>

	<h2><?php esc_html_e( 'AI Suggestion Form', 'ai-seo-geo-optimizer' ); ?></h2>
	<?php if ( empty( $active_providers ) ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'No active provider found. Please configure AI Providers first.', 'ai-seo-geo-optimizer' ); ?></p></div>
	<?php else : ?>
	<form method="post" action="">
		<?php wp_nonce_field( 'ai_seo_geo_generate_suggestions', 'ai_seo_geo_nonce' ); ?>
		<input type="hidden" name="ai_seo_geo_generate_action" value="generate" />
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Provider', 'ai-seo-geo-optimizer' ); ?></th><td><select name="provider_id"><?php foreach ( $active_providers as $provider ) : ?><option value="<?php echo esc_attr( (string) $provider['id'] ); ?>"><?php echo esc_html( $provider['provider_name'] . ' (' . $provider['provider_key'] . ')' ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><?php esc_html_e( 'Model', 'ai-seo-geo-optimizer' ); ?></th><td><input class="regular-text" type="text" name="model" value="" /></td></tr>
			<tr><th><?php esc_html_e( 'Target Keyword', 'ai-seo-geo-optimizer' ); ?></th><td><input class="regular-text" type="text" name="target_keyword" value="" /></td></tr>
			<tr><th><?php esc_html_e( 'Language', 'ai-seo-geo-optimizer' ); ?></th><td><input class="regular-text" type="text" name="language" value="en" /></td></tr>
			<tr><th><?php esc_html_e( 'Brand Tone', 'ai-seo-geo-optimizer' ); ?></th><td><input class="regular-text" type="text" name="brand_tone" value="professional" /></td></tr>
			<tr><th><?php esc_html_e( 'max_tokens', 'ai-seo-geo-optimizer' ); ?></th><td><input class="small-text" type="number" min="3000" step="100" name="max_tokens" value="<?php echo esc_attr( (string) max( 3000, absint( $_POST['max_tokens'] ?? 3000 ) ) ); ?>" /><p class="description"><?php esc_html_e( 'Default minimum is 3000. If content/FAQ/schema/internal links/image alt is selected, system uses at least 6000 unless you set a higher value.', 'ai-seo-geo-optimizer' ); ?></p></td></tr>
			<tr><th><?php esc_html_e( 'Fields to Optimize', 'ai-seo-geo-optimizer' ); ?></th><td><?php foreach ( $fields_options as $field_name ) : ?><label style="display:inline-block;min-width:180px;"><input type="checkbox" name="fields[]" value="<?php echo esc_attr( $field_name ); ?>" checked /><?php echo esc_html( $field_name ); ?></label><?php endforeach; ?></td></tr>
		</table>
		<?php submit_button( __( 'Generate Suggestions', 'ai-seo-geo-optimizer' ) ); ?>
	</form>
	<?php endif; ?>

	<?php if ( ! empty( $ai_result ) && is_array( $ai_result ) ) : ?>
		<h2><?php esc_html_e( 'AI Suggestions', 'ai-seo-geo-optimizer' ); ?></h2>
		<?php if ( isset( $ai_result['risk_level'] ) && 'high' === strtolower( (string) $ai_result['risk_level'] ) ) : ?><div class="notice notice-warning"><p><strong><?php esc_html_e( 'Risk level is HIGH. Please review carefully before applying any changes.', 'ai-seo-geo-optimizer' ); ?></strong></p></div><?php endif; ?>
		<div style="display:flex;gap:16px;"><div style="flex:1;"><h3><?php esc_html_e( 'Original', 'ai-seo-geo-optimizer' ); ?></h3><div class="postbox" style="padding:12px;"><?php echo wp_kses_post( wpautop( $original_data['content'] ) ); ?></div></div><div style="flex:1;"><h3><?php esc_html_e( 'Optimized Content', 'ai-seo-geo-optimizer' ); ?></h3><div class="postbox" style="padding:12px;"><?php echo wp_kses_post( wpautop( (string) ( $ai_result['optimized_content'] ?? '' ) ) ); ?></div></div></div>
		<table class="widefat striped" style="margin-top:16px;"><tbody>
		<?php foreach ( array( 'search_intent', 'primary_keyword', 'secondary_keywords', 'seo_title', 'meta_description', 'suggested_tags', 'excerpt', 'faq', 'internal_link_suggestions', 'image_alt_suggestions', 'schema_suggestion', 'geo_summary', 'fact_check_notes', 'needs_human_review', 'unsupported_claims_removed', 'content_score', 'risk_level' ) as $display_key ) : $value = $ai_result[ $display_key ] ?? ''; $is_highlight = in_array( $display_key, array( 'fact_check_notes', 'needs_human_review' ), true ); ?>
			<tr <?php echo $is_highlight ? 'style="background:#fff7e6;"' : ''; ?>><th style="width:240px;"><?php echo esc_html( $display_key ); ?></th><td><pre style="white-space:pre-wrap;"><?php echo esc_html( is_array( $value ) ? wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : (string) $value ); ?></pre></td></tr>
		<?php endforeach; ?>
		</tbody></table>

		<h3><?php esc_html_e( 'Apply Selected Changes', 'ai-seo-geo-optimizer' ); ?></h3>
		<form method="post" action="">
			<?php wp_nonce_field( 'ai_seo_geo_apply_changes', 'ai_seo_geo_apply_nonce' ); ?>
			<input type="hidden" name="ai_seo_geo_apply_action" value="apply" />
			<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $current_job_id ); ?>" />
			<?php foreach ( $fields_options as $field_name ) : ?><label style="display:inline-block;min-width:180px;"><input type="checkbox" name="apply_fields[]" value="<?php echo esc_attr( $field_name ); ?>" checked /><?php echo esc_html( $field_name ); ?></label><?php endforeach; ?>
			<?php submit_button( __( 'Apply Selected Changes', 'ai-seo-geo-optimizer' ), 'primary', 'submit', false ); ?>
		</form>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Available Snapshots', 'ai-seo-geo-optimizer' ); ?></h2>
	<table class="widefat striped"><thead><tr><th>ID</th><th>Job ID</th><th><?php esc_html_e( 'Created At', 'ai-seo-geo-optimizer' ); ?></th><th><?php esc_html_e( 'Action', 'ai-seo-geo-optimizer' ); ?></th></tr></thead><tbody>
	<?php if ( empty( $snapshots ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'No snapshots available.', 'ai-seo-geo-optimizer' ); ?></td></tr><?php else : foreach ( $snapshots as $snapshot ) : ?>
		<tr><td><?php echo esc_html( (string) $snapshot['id'] ); ?></td><td><?php echo esc_html( (string) $snapshot['job_id'] ); ?></td><td><?php echo esc_html( $snapshot['created_at'] ); ?></td><td>
			<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'Rollback to this snapshot?', 'ai-seo-geo-optimizer' ) ); ?>');">
				<?php wp_nonce_field( 'ai_seo_geo_rollback_snapshot', 'ai_seo_geo_rollback_nonce' ); ?>
				<input type="hidden" name="ai_seo_geo_rollback_action" value="rollback" />
				<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot['id'] ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Rollback', 'ai-seo-geo-optimizer' ); ?></button>
			</form>
		</td></tr>
	<?php endforeach; endif; ?>
	</tbody></table>
</div>
