<?php
/**
 * Logs page.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-geo-optimizer' ) );
}

$log_manager      = new AI_SEO_GEO_Log_Manager();
$revision_manager = new AI_SEO_GEO_Revision_Manager();
$notice           = '';
$notice_type      = 'success';

if ( isset( $_POST['ai_seo_geo_rollback_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_logs_rollback', 'ai_seo_geo_logs_nonce' );
	$snapshot_id = absint( $_POST['snapshot_id'] ?? 0 );
	$result      = $revision_manager->rollback_snapshot( $snapshot_id );
	$notice      = $result['message'];
	$notice_type = ! empty( $result['success'] ) ? 'success' : 'error';
}

$logs      = $log_manager->get_logs_with_context( 100 );
$snapshots = $revision_manager->get_snapshots_by_post( absint( $_GET['post_id'] ?? 0 ) );
?>
<div class="wrap ai-seo-geo-wrap">
	<h1><?php esc_html_e( 'Logs', 'ai-seo-geo-optimizer' ); ?></h1>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Job & Action Logs', 'ai-seo-geo-optimizer' ); ?></h2>
	<table class="widefat striped">
		<thead><tr>
			<th><?php esc_html_e( 'ID', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Post Title', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Action', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Status', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Created By', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Created At', 'ai-seo-geo-optimizer' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if ( empty( $logs ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No logs found.', 'ai-seo-geo-optimizer' ); ?></td></tr>
		<?php else : foreach ( $logs as $row ) : ?>
			<tr>
				<td><?php echo esc_html( (string) $row['id'] ); ?></td>
				<td><?php echo esc_html( $row['post_title'] ? $row['post_title'] : '-' ); ?></td>
				<td><?php echo esc_html( $row['action'] ); ?></td>
				<td><?php echo esc_html( $row['job_status'] ? $row['job_status'] : '-' ); ?></td>
				<td><?php echo esc_html( (string) $row['created_by'] ); ?></td>
				<td><?php echo esc_html( $row['created_at'] ); ?></td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $snapshots ) ) : ?>
	<h2><?php esc_html_e( 'Rollback Snapshots', 'ai-seo-geo-optimizer' ); ?></h2>
	<table class="widefat striped"><thead><tr><th>ID</th><th>Post ID</th><th>Job ID</th><th><?php esc_html_e( 'Created At', 'ai-seo-geo-optimizer' ); ?></th><th><?php esc_html_e( 'Action', 'ai-seo-geo-optimizer' ); ?></th></tr></thead><tbody>
	<?php foreach ( $snapshots as $snapshot ) : ?>
	<tr>
		<td><?php echo esc_html( (string) $snapshot['id'] ); ?></td>
		<td><?php echo esc_html( (string) $snapshot['post_id'] ); ?></td>
		<td><?php echo esc_html( (string) $snapshot['job_id'] ); ?></td>
		<td><?php echo esc_html( $snapshot['created_at'] ); ?></td>
		<td>
			<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'Rollback to this snapshot?', 'ai-seo-geo-optimizer' ) ); ?>');">
				<?php wp_nonce_field( 'ai_seo_geo_logs_rollback', 'ai_seo_geo_logs_nonce' ); ?>
				<input type="hidden" name="ai_seo_geo_rollback_action" value="rollback" />
				<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $snapshot['id'] ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Rollback', 'ai-seo-geo-optimizer' ); ?></button>
			</form>
		</td>
	</tr>
	<?php endforeach; ?>
	</tbody></table>
	<?php endif; ?>
</div>
