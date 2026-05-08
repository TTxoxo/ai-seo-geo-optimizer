<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'No permission.', 'ai-seo-geo-optimizer' ) ); }
$manager = new AI_SEO_GEO_Internal_Link_Manager();
$log_manager = new AI_SEO_GEO_Log_Manager();
$manager->ensure_table_columns();
$notice = '';
$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
$link_id = absint($_GET['link_id'] ?? 0);
$edit_row = ( 'edit' === $action && $link_id ) ? $manager->get_link_by_id($link_id) : array();

if ( isset( $_POST['ai_seo_geo_save_internal_link'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_save_internal_link', 'ai_seo_geo_internal_link_nonce' );
	$result = $manager->save_link( wp_unslash( $_POST ) );
	if ( ! empty( $result['success'] ) ) {
		$saved = $manager->get_link_by_id( absint( $result['id'] ?? 0 ) );
		$log_manager->add_log(array('action'=>'internal_link_updated','message'=>'Internal link updated','context_json'=>array('link_id'=>absint($result['id']??0),'anchor_text'=>$saved['anchor_text']??'','target_url'=>$saved['target_url']??'','target_post_id'=>absint($saved['target_post_id']??0),'link_type'=>$saved['link_type']??'','priority'=>intval($saved['priority']??0),'status'=>$saved['status']??'')));
		wp_safe_redirect( admin_url( 'admin.php?page=ai-seo-geo-internal-links&updated=1' ) ); exit;
	}
	$notice = __( 'Save failed. Please check fields.', 'ai-seo-geo-optimizer' );
	if ( ! empty( $result['message'] ) && 'link_not_found' === $result['message'] ) {
		$notice = __( 'Link record not found.', 'ai-seo-geo-optimizer' );
	}
}
if ( isset($_GET['updated']) ) { $notice = __( 'Internal link updated successfully.', 'ai-seo-geo-optimizer' ); }
$rows = $manager->get_all_links();
$link_types = array( 'product_hub', 'product_category', 'product_detail', 'spare_parts', 'solution', 'safety', 'article', 'contact', 'company', 'media', 'custom' );
$current = !empty($edit_row) ? $edit_row : array('id'=>0,'anchor_text'=>'','target_url'=>'','link_type'=>'custom','priority'=>50,'status'=>'active','related_keywords'=>'','recommended_context'=>'','notes'=>'');
?>
<div class="wrap"><h1><?php esc_html_e( 'Internal Link Library', 'ai-seo-geo-optimizer' ); ?></h1>
<?php if ( $notice ) : ?><div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
<form method="post"><?php wp_nonce_field( 'ai_seo_geo_save_internal_link', 'ai_seo_geo_internal_link_nonce' ); ?>
<input type="hidden" name="ai_seo_geo_save_internal_link" value="1" />
<?php if ( ! empty( $current['id'] ) ) : ?><input type="hidden" name="id" value="<?php echo esc_attr((string)absint($current['id'])); ?>" /><?php endif; ?>
<table class="form-table"><tbody>
<tr><th>Anchor Text *</th><td><input type="text" name="anchor_text" class="regular-text" value="<?php echo esc_attr((string)$current['anchor_text']); ?>" required /></td></tr>
<tr><th>Target URL *</th><td><input type="text" name="target_url" class="regular-text" value="<?php echo esc_attr((string)$current['target_url']); ?>" required /><p class="description">Target Post ID auto-detected from URL.</p></td></tr>
<tr><th>Link Type *</th><td><select name="link_type" required><?php foreach ( $link_types as $t ) : ?><option value="<?php echo esc_attr( $t ); ?>" <?php selected($current['link_type'],$t); ?>><?php echo esc_html( $t ); ?></option><?php endforeach; ?></select></td></tr>
<tr><th>Related Keywords</th><td><textarea name="related_keywords" rows="2" class="large-text"><?php echo esc_textarea((string)$current['related_keywords']); ?></textarea></td></tr>
<tr><th>Recommended Context</th><td><textarea name="recommended_context" rows="2" class="large-text"><?php echo esc_textarea((string)$current['recommended_context']); ?></textarea></td></tr>
<tr><th>Priority</th><td><select name="priority"><option value="100" <?php selected((int)$current['priority'],100); ?>>High</option><option value="50" <?php selected((int)$current['priority'],50); ?>>Medium</option><option value="10" <?php selected((int)$current['priority'],10); ?>>Low</option></select></td></tr>
<tr><th>Status</th><td><select name="status"><option value="active" <?php selected($current['status'],'active'); ?>>Active</option><option value="inactive" <?php selected($current['status'],'inactive'); ?>>Inactive</option></select></td></tr>
<tr><th>Notes</th><td><textarea name="notes" rows="3" class="large-text"><?php echo esc_textarea((string)$current['notes']); ?></textarea></td></tr>
</tbody></table>
<?php submit_button( !empty($current['id']) ? __( 'Update Internal Link', 'ai-seo-geo-optimizer' ) : __( 'Save Internal Link', 'ai-seo-geo-optimizer' ) ); ?>
<?php if ( ! empty($current['id']) ) : ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-geo-internal-links')); ?>"><?php esc_html_e('Cancel','ai-seo-geo-optimizer'); ?></a><?php endif; ?>
</form>
<h2><?php esc_html_e( 'Internal Links', 'ai-seo-geo-optimizer' ); ?></h2>
<table class="widefat striped"><thead><tr><th>ID</th><th>Anchor Text</th><th>Target URL</th><th>Target Post ID</th><th>Link Type</th><th>Related Keywords</th><th>Recommended Context</th><th>Priority</th><th>Status</th><th>Updated At</th><th>Actions</th></tr></thead><tbody>
<?php foreach ( $rows as $r ) : $edit_url=admin_url('admin.php?page=ai-seo-geo-internal-links&action=edit&link_id=' . absint($r['id'])); ?>
<tr><td><?php echo esc_html((string)$r['id']); ?></td><td><?php echo esc_html($r['anchor_text']); ?></td><td><a href="<?php echo esc_url($r['target_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($r['target_url']); ?></a></td><td><?php echo esc_html((string)absint($r['target_post_id'])); ?></td><td><?php echo esc_html($r['link_type']); ?></td><td><?php echo esc_html(mb_substr((string)($r['related_keywords']??''),0,80)); ?></td><td><?php echo esc_html(mb_substr((string)($r['recommended_context']??''),0,100)); ?></td><td><?php echo esc_html((int)$r['priority']===100?'High':((int)$r['priority']===10?'Low':'Medium')); ?></td><td><?php echo esc_html($r['status']); ?></td><td><?php echo esc_html((string)($r['updated_at']??'')); ?></td><td><?php if(current_user_can('manage_options')||current_user_can('edit_posts')): ?><a class="button button-small" href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit','ai-seo-geo-optimizer'); ?></a><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
