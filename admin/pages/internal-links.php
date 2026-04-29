<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'No permission.', 'ai-seo-geo-optimizer' ) ); }
$manager = new AI_SEO_GEO_Internal_Link_Manager();
$notice  = '';
if ( isset( $_POST['ai_seo_geo_save_internal_link'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_save_internal_link', 'ai_seo_geo_internal_link_nonce' );
	$ok     = $manager->save_link( wp_unslash( $_POST ) );
	$notice = $ok ? __( 'Saved.', 'ai-seo-geo-optimizer' ) : __( 'Save failed.', 'ai-seo-geo-optimizer' );
}
$rows = $manager->get_active_links();
$link_types = array( 'product_hub', 'product_category', 'product_detail', 'spare_parts', 'solution', 'safety', 'article', 'contact', 'company', 'media', 'custom' );
?>
<div class="wrap"><h1><?php esc_html_e( 'Internal Link Library', 'ai-seo-geo-optimizer' ); ?></h1>
<?php if ( $notice ) : ?><div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
<form method="post"><?php wp_nonce_field( 'ai_seo_geo_save_internal_link', 'ai_seo_geo_internal_link_nonce' ); ?>
<input type="hidden" name="ai_seo_geo_save_internal_link" value="1" />
<table class="form-table"><tbody>
<tr><th>Anchor Text *</th><td><input type="text" name="anchor_text" class="regular-text" required /></td></tr>
<tr><th>Target URL *</th><td><input type="url" name="target_url" class="regular-text" required /><p class="description">Target Post ID is auto-detected from URL.</p></td></tr>
<tr><th>Link Type *</th><td><select name="link_type" required><?php foreach ( $link_types as $t ) : ?><option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $t ); ?></option><?php endforeach; ?></select></td></tr>
<tr><th>Priority</th><td><select name="priority"><option value="100">High</option><option value="50" selected>Medium</option><option value="10">Low</option></select></td></tr>
<tr><th>Status</th><td><select name="status"><option value="active" selected>Active</option><option value="inactive">Inactive</option></select></td></tr>
<tr><th>Related Keywords</th><td><textarea name="related_keywords" rows="2" class="large-text"></textarea></td></tr>
<tr><th>Recommended Context</th><td><textarea name="recommended_context" rows="2" class="large-text"></textarea></td></tr>
<tr><th>Notes</th><td><textarea name="notes" rows="3" class="large-text"></textarea></td></tr>
</tbody></table><?php submit_button( __( 'Save Internal Link', 'ai-seo-geo-optimizer' ) ); ?></form>
<h2><?php esc_html_e( 'Active Links', 'ai-seo-geo-optimizer' ); ?></h2>
<table class="widefat striped"><thead><tr><th>Anchor Text</th><th>Target URL</th><th>Auto Post ID</th><th>Link Type</th><th>Priority</th><th>Status</th><th>Related Keywords</th><th>Recommended Context</th></tr></thead><tbody>
<?php foreach ( $rows as $r ) : ?><tr>
<td><?php echo esc_html( $r['anchor_text'] ); ?></td>
<td><a href="<?php echo esc_url( $r['target_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['target_url'] ); ?></a></td>
<td><?php echo esc_html( (string) absint( $r['target_post_id'] ) ); ?></td>
<td><?php echo esc_html( $r['link_type'] ); ?></td>
<td><?php echo esc_html( (string) $r['priority'] ); ?></td>
<td><?php echo esc_html( $r['status'] ); ?></td>
<td><?php echo esc_html( (string) ( $r['related_keywords'] ?? '' ) ); ?></td>
<td><?php echo esc_html( (string) ( $r['recommended_context'] ?? '' ) ); ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
