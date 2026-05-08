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
$output_format_manager = new AI_SEO_GEO_Output_Format_Manager();
$editor_detector = new AI_SEO_GEO_Editor_Detector();
$image_alt_manager = new AI_SEO_GEO_Image_Alt_Manager();
$log_manager = new AI_SEO_GEO_Log_Manager();
$schema_review_manager = new AI_SEO_GEO_Schema_Review_Manager();
$original_data     = $review_manager->get_original_content_data( $post_id );
$active_providers  = $provider_manager->get_active_providers();
$fields_options    = array( 'title', 'seo_title', 'meta_description', 'tags', 'excerpt', 'content', 'faq', 'internal_links', 'image_alt', 'schema' );
$notice            = '';
$notice_type       = 'success';
$ai_result         = null;
$current_job_id    = 0;
$json_debug_info   = array();
$requested_job_id  = absint( $_GET['job_id'] ?? 0 );

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
			'output_format'  => sanitize_text_field( wp_unslash( $_POST['output_format'] ?? 'auto' ) ),
		)
	);
	$notice         = $result['message'];
	$notice_type    = ! empty( $result['notice_type'] ) ? sanitize_text_field( $result['notice_type'] ) : ( ! empty( $result['success'] ) ? 'success' : 'error' );
	$current_job_id = absint( $result['job_id'] ?? 0 );
	$json_debug_info = isset( $result['debug'] ) && is_array( $result['debug'] ) ? $result['debug'] : array();
	if ( ! empty( $result['success'] ) ) {
		$ai_result = $review_manager->ensure_readability_fields( $result['result'] );
	}
}

if ( isset( $_POST['ai_seo_geo_apply_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_apply_changes', 'ai_seo_geo_apply_nonce' );
	$job_id          = absint( $_POST['job_id'] ?? 0 );
	$selected_fields = isset( $_POST['apply_fields'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['apply_fields'] ) ) : array();
	$job_payload     = $review_manager->get_job_result_by_id( $post_id, $job_id );
	$job_result      = is_array( $job_payload['result'] ?? null ) ? $job_payload['result'] : array();
	$ai_style_risk   = strtolower( (string) ( $job_result['ai_style_risk'] ?? '' ) );
	$strong_forbidden = array( '100% guaranteed', 'No.1', 'lowest price', 'world-leading', 'best manufacturer' );
	$has_strong_forbidden = count( array_intersect( $strong_forbidden, (array) ( $job_result['forbidden_phrases_found'] ?? array() ) ) ) > 0;
	if ( $has_strong_forbidden && empty( $_POST['force_apply_forbidden_claims'] ) && in_array( 'content', $selected_fields, true ) ) {
		$notice      = __( 'Strong forbidden claims detected. Confirm force apply to continue content apply.', 'ai-seo-geo-optimizer' );
		$notice_type = 'error';
	} elseif ( 'high' === $ai_style_risk && empty( $_POST['confirm_high_ai_style_risk'] ) && in_array( 'content', $selected_fields, true ) ) {
		$notice      = __( 'AI Style Risk is high. Please confirm before applying content.', 'ai-seo-geo-optimizer' );
		$notice_type = 'warning';
	} else {
		$apply_result    = $revision_manager->apply_selected_changes( $job_id, $post_id, $selected_fields );
		$notice          = $apply_result['message'];
		$notice_type     = ! empty( $apply_result['success'] ) ? 'success' : 'error';
	}
}


if ( isset( $_POST['ai_seo_geo_apply_image_alt_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_apply_image_alt', 'ai_seo_geo_apply_image_alt_nonce' );
	$attachment_id = absint( $_POST['attachment_id'] ?? 0 );
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You cannot edit this content.', 'ai-seo-geo-optimizer' ) );
	}
	$alt_text      = sanitize_text_field( wp_unslash( $_POST['suggested_alt'] ?? '' ) );
	$apply_alt     = $image_alt_manager->apply_image_alt( $attachment_id, $alt_text );
	$notice        = $apply_alt['message'] ?? ( ! empty( $apply_alt['success'] ) ? __( 'Alt applied.', 'ai-seo-geo-optimizer' ) : __( 'Alt apply failed.', 'ai-seo-geo-optimizer' ) );
	$notice_type   = ! empty( $apply_alt['success'] ) ? 'success' : 'error';
	if ( ! empty( $apply_alt['success'] ) ) {
		$log_manager->add_log(
			array(
				'post_id'  => $post_id,
				'action'   => 'apply_image_alt',
				'message'  => __( 'Image alt applied.', 'ai-seo-geo-optimizer' ),
				'context_json' => array(
					'image_id' => $attachment_id,
					'old_alt'  => $apply_alt['old_alt'] ?? '',
					'new_alt'  => $apply_alt['new_alt'] ?? '',
				),
			)
		);
	}
}


if ( isset( $_POST['ai_seo_geo_schema_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_schema_action', 'ai_seo_geo_schema_nonce' );
	$schema_action = sanitize_text_field( wp_unslash( $_POST['ai_seo_geo_schema_action'] ) );
	$schema_data   = isset( $ai_result['schema_suggestion'] ) ? $ai_result['schema_suggestion'] : array();
	if ( 'save' === $schema_action ) {
		$schema_review_manager->save_schema_suggestion( $post_id, $schema_data );
		$notice = __( 'Schema suggestion saved.', 'ai-seo-geo-optimizer' );
		$notice_type = 'success';
	} elseif ( 'reviewed' === $schema_action || 'rejected' === $schema_action || 'approved' === $schema_action ) {
		update_post_meta( $post_id, '_ai_seo_geo_schema_status', $schema_action );
		$notice = __( 'Schema status updated.', 'ai-seo-geo-optimizer' );
		$notice_type = 'success';
	}
}

if ( isset( $_POST['ai_seo_geo_rollback_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_rollback_snapshot', 'ai_seo_geo_rollback_nonce' );
	$rollback_result = $revision_manager->rollback_snapshot( absint( $_POST['snapshot_id'] ?? 0 ) );
	$notice          = $rollback_result['message'];
	$notice_type     = ! empty( $rollback_result['success'] ) ? 'success' : 'error';
	$original_data   = $review_manager->get_original_content_data( $post_id );
}

if ( null === $ai_result ) {
	$job_payload = null;
	if ( $requested_job_id > 0 ) {
		$job_payload = $review_manager->get_job_result_by_id( $post_id, $requested_job_id );
	}
	if ( ! $job_payload ) {
		$job_payload = $review_manager->get_latest_successful_job_result( $post_id );
	}
	if ( ! empty( $job_payload['job'] ) ) {
		$ai_result       = $review_manager->ensure_readability_fields( is_array( $job_payload['result'] ) ? $job_payload['result'] : array() );
		$current_job_id  = absint( $job_payload['job']['id'] ?? 0 );
		$json_debug_info = isset( $job_payload['debug'] ) && is_array( $job_payload['debug'] ) ? $job_payload['debug'] : array();
		if ( ! empty( $job_payload['result_empty'] ) ) {
			$notice      = __( 'AI returned successfully, but result_json is empty.', 'ai-seo-geo-optimizer' );
			$notice_type = 'warning';
		}
	}
}

$snapshots = $revision_manager->get_snapshots_by_post( $post_id );
$optimized_content_value = isset( $ai_result['optimized_content'] ) && is_scalar( $ai_result['optimized_content'] ) ? (string) $ai_result['optimized_content'] : '';
$raw_response_preview = isset( $json_debug_info['raw_response_preview'] ) ? (string) $json_debug_info['raw_response_preview'] : '';
$faq_count = isset( $ai_result['faq'] ) && is_array( $ai_result['faq'] ) ? count( $ai_result['faq'] ) : 0;
$tags_count = isset( $ai_result['suggested_tags'] ) && is_array( $ai_result['suggested_tags'] ) ? count( $ai_result['suggested_tags'] ) : 0;
$content_field_selected_debug = (string) ( $json_debug_info['content_field_selected'] ?? ( $ai_result['content_field_selected'] ?? 'no' ) );
$original_content_length_debug = absint( $json_debug_info['original_content_length'] ?? mb_strlen( wp_strip_all_tags( (string) $original_data['content'] ) ) );
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
	<?php if ( '' !== $raw_response_preview ) : ?>
		<details class="postbox" style="padding:12px;margin-top:12px;">
			<summary><strong><?php esc_html_e( 'Raw AI Response Preview', 'ai-seo-geo-optimizer' ); ?></strong></summary>
			<p class="description"><?php esc_html_e( 'For administrator debugging only. API keys and headers are never displayed here.', 'ai-seo-geo-optimizer' ); ?></p>
			<pre style="white-space:pre-wrap;max-height:320px;overflow:auto;"><?php echo esc_html( mb_substr( $raw_response_preview, 0, 1000 ) ); ?></pre>
		</details>
	<?php endif; ?>
	<?php if ( 0 === $original_content_length_debug && 'yes' === $content_field_selected_debug ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'Original post content is empty. AI will generate content based on title, excerpt, categories, tags, and target keyword.', 'ai-seo-geo-optimizer' ); ?></p></div>
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
			<tr><th><?php esc_html_e( 'Content Output Format', 'ai-seo-geo-optimizer' ); ?></th><td><select name="output_format"><?php foreach ( $output_format_manager->get_format_options() as $format_key => $format_label ) : ?><option value="<?php echo esc_attr( $format_key ); ?>"><?php echo esc_html( $format_label ); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e( 'Auto Detect uses post editor type.', 'ai-seo-geo-optimizer' ); ?></p></td></tr><tr><th><?php esc_html_e( 'Fields to Optimize', 'ai-seo-geo-optimizer' ); ?></th><td><?php foreach ( $fields_options as $field_name ) : ?><label style="display:inline-block;min-width:180px;"><input type="checkbox" name="fields[]" value="<?php echo esc_attr( $field_name ); ?>" checked /><?php echo esc_html( $field_name ); ?></label><?php endforeach; ?></td></tr>
		</table>
		<?php submit_button( __( 'Generate Suggestions', 'ai-seo-geo-optimizer' ) ); ?>
	</form>
	<?php endif; ?>

	<?php if ( ! empty( $ai_result ) && is_array( $ai_result ) ) : ?>
		<h2><?php esc_html_e( 'AI Suggestions', 'ai-seo-geo-optimizer' ); ?></h2>
		<?php if ( isset( $ai_result['risk_level'] ) && 'high' === strtolower( (string) $ai_result['risk_level'] ) ) : ?><div class="notice notice-warning"><p><strong><?php esc_html_e( 'Risk level is HIGH. Please review carefully before applying any changes.', 'ai-seo-geo-optimizer' ); ?></strong></p></div><?php endif; ?>
		<div style="display:flex;gap:16px;">
			<div style="flex:1;"><h3><?php esc_html_e( 'Original', 'ai-seo-geo-optimizer' ); ?></h3><div class="postbox" style="padding:12px;"><?php echo wp_kses_post( wpautop( $original_data['content'] ) ); ?></div></div>
			<div style="flex:1;">
				<h3><?php esc_html_e( 'Optimized Content', 'ai-seo-geo-optimizer' ); ?></h3>
				<div class="postbox" style="padding:12px;">
					<?php if ( '' !== trim( $optimized_content_value ) ) : ?>
						<?php echo wp_kses_post( wpautop( $optimized_content_value ) ); ?>
					<?php else : ?>
						<p><strong><?php esc_html_e( 'No optimized content was returned.', 'ai-seo-geo-optimizer' ); ?></strong></p>
						<ul style="margin-left:18px;list-style:disc;">
							<li><?php esc_html_e( 'Content field was not selected', 'ai-seo-geo-optimizer' ); ?></li>
							<li><?php esc_html_e( 'AI returned a different field name', 'ai-seo-geo-optimizer' ); ?></li>
							<li><?php esc_html_e( 'AI response was truncated', 'ai-seo-geo-optimizer' ); ?></li>
							<li><?php esc_html_e( 'Prompt did not require optimized_content', 'ai-seo-geo-optimizer' ); ?></li>
							<li><?php esc_html_e( 'Model returned only meta fields', 'ai-seo-geo-optimizer' ); ?></li>
						</ul>
					<?php endif; ?>
				</div>
				<h4><?php esc_html_e( 'Optimized Content (Raw HTML)', 'ai-seo-geo-optimizer' ); ?></h4>
				<textarea readonly rows="10" style="width:100%;"><?php echo esc_textarea( $optimized_content_value ); ?></textarea>
			</div>
		</div>
		<details class="postbox" style="padding:12px;margin-top:16px;">
			<summary><strong><?php esc_html_e( 'Parsed AI Result Debug', 'ai-seo-geo-optimizer' ); ?></strong></summary>
			<ul style="margin-left:18px;">
				<li><strong><?php esc_html_e( 'search_intent exists:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( isset( $ai_result['search_intent'] ) ? __( 'Yes', 'ai-seo-geo-optimizer' ) : __( 'No', 'ai-seo-geo-optimizer' ) ); ?></li>
				<li><strong><?php esc_html_e( 'seo_title length:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) mb_strlen( (string) ( $ai_result['seo_title'] ?? '' ) ) ); ?></li>
				<li><strong><?php esc_html_e( 'meta_description length:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) mb_strlen( (string) ( $ai_result['meta_description'] ?? '' ) ) ); ?></li>
				<li><strong><?php esc_html_e( 'selected_fields:', 'ai-seo-geo-optimizer' ); ?></strong> <pre style="display:inline;white-space:pre-wrap;"><?php echo esc_html( wp_json_encode( $json_debug_info['selected_fields'] ?? array(), JSON_UNESCAPED_UNICODE ) ); ?></pre></li>
				<li><strong><?php esc_html_e( 'content_field_selected:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( $content_field_selected_debug ); ?></li>
				<li><strong><?php esc_html_e( 'output_format:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) ( $json_debug_info['output_format'] ?? ( $ai_result['output_format'] ?? '' ) ) ); ?></li>
				<li><strong><?php esc_html_e( 'original_title length:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) absint( $json_debug_info['original_title_length'] ?? mb_strlen( (string) $original_data['title'] ) ) ); ?></li>
				<li><strong><?php esc_html_e( 'original_excerpt length:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) absint( $json_debug_info['original_excerpt_length'] ?? mb_strlen( (string) $original_data['excerpt'] ) ) ); ?></li>
				<li><strong><?php esc_html_e( 'original_content length:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) $original_content_length_debug ); ?></li>
				<li><strong><?php esc_html_e( 'optimized_content length:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) mb_strlen( $optimized_content_value ) ); ?></li>
				<li><strong><?php esc_html_e( 'optimized_content_mapped_from:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) ( $json_debug_info['optimized_content_mapped_from'] ?? ( $ai_result['optimized_content_mapped_from'] ?? '' ) ) ); ?></li>
				<li><strong><?php esc_html_e( 'retry_attempted:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) ( $json_debug_info['retry_attempted'] ?? 'no' ) ); ?></li>
				<li><strong><?php esc_html_e( 'retry_success:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) ( $json_debug_info['retry_success'] ?? 'no' ) ); ?></li>
				<li><strong><?php esc_html_e( 'faq count:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) $faq_count ); ?></li>
				<li><strong><?php esc_html_e( 'suggested_tags count:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) $tags_count ); ?></li>
				<li><strong><?php esc_html_e( 'risk_level:', 'ai-seo-geo-optimizer' ); ?></strong> <?php echo esc_html( (string) ( $ai_result['risk_level'] ?? '' ) ); ?></li>
			</ul>
			<p><strong><?php esc_html_e( 'raw_response_preview:', 'ai-seo-geo-optimizer' ); ?></strong></p>
			<pre style="white-space:pre-wrap;max-height:220px;overflow:auto;"><?php echo esc_html( mb_substr( (string) ( $json_debug_info['raw_response_preview'] ?? '' ), 0, 1000 ) ); ?></pre>
			<p><strong><?php esc_html_e( 'normalized_result_preview:', 'ai-seo-geo-optimizer' ); ?></strong></p>
			<pre style="white-space:pre-wrap;max-height:220px;overflow:auto;"><?php echo esc_html( mb_substr( (string) ( $json_debug_info['normalized_result_preview'] ?? wp_json_encode( $ai_result ) ), 0, 1000 ) ); ?></pre>
		</details>
		<h3><?php esc_html_e( 'Human Readability Check', 'ai-seo-geo-optimizer' ); ?></h3>
		<table class="widefat striped"><tbody>
		<tr><th><?php esc_html_e( 'Score', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( (string) intval( $ai_result['human_readability_score'] ?? 0 ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Risk', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( (string) ( $ai_result['ai_style_risk'] ?? '' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Found Phrases', 'ai-seo-geo-optimizer' ); ?></th><td><pre><?php echo esc_html( wp_json_encode( $ai_result['forbidden_phrases_found'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
		<tr><th><?php esc_html_e( 'Keyword Stuffing Warning', 'ai-seo-geo-optimizer' ); ?></th><td><pre><?php echo esc_html( wp_json_encode( $ai_result['keyword_stuffing_warnings'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
		<tr><th><?php esc_html_e( 'Recommended Human Edits', 'ai-seo-geo-optimizer' ); ?></th><td><pre><?php echo esc_html( wp_json_encode( $ai_result['recommended_human_edits'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
		</tbody></table>
		<h3><?php esc_html_e( 'Content Style & Human Readability', 'ai-seo-geo-optimizer' ); ?></h3>
		<table class="widefat striped" style="margin-top:12px;"><tbody>
			<tr><th><?php esc_html_e( 'Writing Person', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( (string) ( $ai_result['writing_person'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Tone', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( (string) ( $ai_result['tone'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Forbidden Phrases Found', 'ai-seo-geo-optimizer' ); ?></th><td><pre><?php echo esc_html( wp_json_encode( $ai_result['forbidden_phrases_found'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
			<tr><th><?php esc_html_e( 'Generic Marketing Phrases', 'ai-seo-geo-optimizer' ); ?></th><td><pre><?php echo esc_html( wp_json_encode( $ai_result['generic_marketing_phrases'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
			<tr><th><?php esc_html_e( 'Unsupported Claims Removed', 'ai-seo-geo-optimizer' ); ?></th><td><pre><?php echo esc_html( wp_json_encode( $ai_result['unsupported_claims_removed'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
			<tr><th><?php esc_html_e( 'Needs Human Review', 'ai-seo-geo-optimizer' ); ?></th><td><pre><?php echo esc_html( wp_json_encode( $ai_result['needs_human_review'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
			<tr><th><?php esc_html_e( 'Human Readability Score', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( (string) intval( $ai_result['human_readability_score'] ?? 0 ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'AI Style Risk', 'ai-seo-geo-optimizer' ); ?></th><td><?php echo esc_html( (string) ( $ai_result['ai_style_risk'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Recommended Human Edits', 'ai-seo-geo-optimizer' ); ?></th><td><pre><?php echo esc_html( wp_json_encode( $ai_result['recommended_human_edits'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
		</tbody></table>
		<table class="widefat striped" style="margin-top:16px;"><tbody>
		<?php foreach ( array( 'search_intent', 'primary_keyword', 'secondary_keywords', 'seo_title', 'meta_description', 'suggested_tags', 'excerpt', 'faq', 'internal_link_suggestions', 'image_alt_suggestions', 'schema_suggestion', 'geo_summary', 'fact_check_notes', 'needs_human_review', 'unsupported_claims_removed', 'content_score', 'risk_level' ) as $display_key ) : $value = $ai_result[ $display_key ] ?? ''; $is_highlight = in_array( $display_key, array( 'fact_check_notes', 'needs_human_review' ), true ); ?>
			<tr <?php echo $is_highlight ? 'style="background:#fff7e6;"' : ''; ?>><th style="width:240px;"><?php echo esc_html( $display_key ); ?></th><td><pre style="white-space:pre-wrap;"><?php echo esc_html( is_array( $value ) ? wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : (string) $value ); ?></pre></td></tr>
		<?php endforeach; ?>
		</tbody></table>

		<h3><?php esc_html_e( 'Schema Suggestions', 'ai-seo-geo-optimizer' ); ?></h3>
		<?php $schema_payload = is_array( $ai_result['schema_suggestion'] ?? null ) ? $ai_result['schema_suggestion'] : array(); ?>
		<table class="widefat striped"><tbody>
		<tr><th>Schema Type</th><td><?php echo esc_html( (string) ( $schema_payload['@type'] ?? '' ) ); ?></td></tr>
		<tr><th>Fields</th><td><pre><?php echo esc_html( wp_json_encode( $schema_payload['fields'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
		<tr><th>Visible Content Check</th><td><pre><?php echo esc_html( wp_json_encode( $schema_review_manager->check_visible_content_match( $schema_payload, $original_data['content'] ?? '' ), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
		<tr><th>Excluded Fields</th><td><pre><?php echo esc_html( wp_json_encode( $schema_payload['excluded_fields'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
		<tr><th>Needs Human Review</th><td><pre><?php echo esc_html( wp_json_encode( $schema_payload['needs_human_review'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
		<tr><th>Schema Risk</th><td><?php echo esc_html( (string) ( $schema_payload['schema_risk'] ?? '' ) ); ?></td></tr>
		</tbody></table>
		<form method="post" action=""><?php wp_nonce_field( 'ai_seo_geo_schema_action', 'ai_seo_geo_schema_nonce' ); ?>
		<button class="button" name="ai_seo_geo_schema_action" value="save" type="submit">Save Suggestion</button>
		<button class="button" name="ai_seo_geo_schema_action" value="reviewed" type="submit">Mark as Reviewed</button>
		<button class="button" name="ai_seo_geo_schema_action" value="rejected" type="submit">Reject</button>
		<button class="button" name="ai_seo_geo_schema_action" value="approved" type="submit">Approve</button>
		</form>
		<textarea readonly rows="8" style="width:100%;"><?php echo esc_textarea( wp_json_encode( $schema_payload, JSON_PRETTY_PRINT ) ); ?></textarea>
		<h3><?php esc_html_e( 'Image Alt Suggestions', 'ai-seo-geo-optimizer' ); ?></h3>
		<table class="widefat striped"><thead><tr><th>Image</th><th>Current Alt</th><th>Suggested Alt</th><th>Reason</th><th>Risk</th><th>Action</th></tr></thead><tbody>
		<?php foreach ( (array) ( $ai_result['image_alt_suggestions'] ?? array() ) as $alt_item ) : $validation = $image_alt_manager->validate_alt_suggestion( (string) ( $alt_item['suggested_alt'] ?? '' ) ); ?>
		<tr>
		<td><?php if ( ! empty( $alt_item['image_url'] ) ) : ?><img src="<?php echo esc_url( (string) $alt_item['image_url'] ); ?>" style="max-width:80px;height:auto;" /><?php endif; ?></td>
		<td><?php echo esc_html( (string) ( $alt_item['current_alt'] ?? '' ) ); ?></td>
		<td><?php echo esc_html( (string) ( $alt_item['suggested_alt'] ?? '' ) ); ?></td>
		<td><?php echo esc_html( (string) ( $alt_item['reason'] ?? '' ) ); ?></td>
		<td><?php echo esc_html( (string) ( $validation['risk'] ?? ( $alt_item['risk'] ?? '' ) ) ); ?></td>
		<td><form method="post" action="">
		<?php wp_nonce_field( 'ai_seo_geo_apply_image_alt', 'ai_seo_geo_apply_image_alt_nonce' ); ?>
		<input type="hidden" name="ai_seo_geo_apply_image_alt_action" value="1" />
		<input type="hidden" name="attachment_id" value="<?php echo esc_attr( (string) absint( $alt_item['image_id'] ?? 0 ) ); ?>" />
		<input type="hidden" name="suggested_alt" value="<?php echo esc_attr( (string) ( $alt_item['suggested_alt'] ?? '' ) ); ?>" />
		<button class="button button-small" type="submit" <?php disabled( 'high', (string) ( $validation['risk'] ?? '' ) ); ?>><?php esc_html_e( 'Apply Alt', 'ai-seo-geo-optimizer' ); ?></button>
		</form></td>
		</tr>
		<?php endforeach; ?>
		</tbody></table>
		<h3><?php esc_html_e( 'Internal Link Suggestions', 'ai-seo-geo-optimizer' ); ?></h3>
		<table class="widefat striped"><thead><tr><th>Anchor</th><th>Target URL</th><th>Link Type</th><th>Placement Suggestion</th><th>Reason</th><th>Confidence</th><th>Valid</th><th>Copy</th></tr></thead><tbody>
		<?php foreach ( (array) ( $ai_result['internal_link_suggestions'] ?? array() ) as $link_item ) : ?>
		<tr>
		<td><?php echo esc_html( (string) ( $link_item['anchor'] ?? '' ) ); ?></td>
		<td><?php echo esc_url( (string) ( $link_item['target_url'] ?? '' ) ); ?></td>
		<td><?php echo esc_html( (string) ( $link_item['link_type'] ?? '' ) ); ?></td>
		<td><?php echo esc_html( (string) ( $link_item['placement_suggestion'] ?? '' ) ); ?></td>
		<td><?php echo esc_html( (string) ( $link_item['reason'] ?? '' ) ); ?></td>
		<td><?php echo esc_html( (string) intval( $link_item['confidence'] ?? 0 ) ); ?></td>
		<td><?php echo esc_html( ! empty( $link_item['valid'] ) ? 'Valid' : 'Invalid' ); ?></td>
		<td><input type="text" readonly value="<?php echo esc_attr( '<a href="' . esc_url( (string) ( $link_item['target_url'] ?? '' ) ) . '">' . esc_html( (string) ( $link_item['anchor'] ?? '' ) ) . '</a>' ); ?>" /></td>
		</tr>
		<?php endforeach; ?>
		</tbody></table>
		<h3><?php esc_html_e( 'Apply Selected Changes', 'ai-seo-geo-optimizer' ); ?></h3>
		<form method="post" action="">
			<?php wp_nonce_field( 'ai_seo_geo_apply_changes', 'ai_seo_geo_apply_nonce' ); ?>
			<input type="hidden" name="ai_seo_geo_apply_action" value="apply" />
			<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $current_job_id ); ?>" />
			<?php if ( isset( $ai_result['ai_style_risk'] ) && 'high' === strtolower( (string) $ai_result['ai_style_risk'] ) ) : ?>
				<p><label><input type="checkbox" name="confirm_high_ai_style_risk" value="1" /> <?php esc_html_e( 'I confirm applying content with HIGH AI Style Risk.', 'ai-seo-geo-optimizer' ); ?></label></p>
			<?php endif; ?>
			<?php if ( ! empty( $ai_result['forbidden_phrases_found'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Forbidden phrases were detected. Please review carefully before applying.', 'ai-seo-geo-optimizer' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( array_intersect( array( '100% guaranteed', 'No.1', 'lowest price' ), (array) ( $ai_result['forbidden_phrases_found'] ?? array() ) ) ) ) : ?><p><label><input type="checkbox" name="force_apply_forbidden_claims" value="1" /> <?php esc_html_e( 'Force apply content even with strong forbidden claims.', 'ai-seo-geo-optimizer' ); ?></label></p><?php endif; ?>
			<?php if ( ! empty( $ai_result['unsupported_claims_removed'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Unsupported claims were removed. Manual confirmation is recommended.', 'ai-seo-geo-optimizer' ); ?></p></div>
			<?php endif; ?>
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
