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

$filters = array(
	'post_id'   => absint( $_GET['post_id'] ?? 0 ),
	'action'    => AI_SEO_GEO_Security::sanitize_text( $_GET['action'] ?? '' ),
	'date_from' => AI_SEO_GEO_Security::sanitize_text( $_GET['date_from'] ?? '' ),
	'date_to'   => AI_SEO_GEO_Security::sanitize_text( $_GET['date_to'] ?? '' ),
	'paged'     => absint( $_GET['paged'] ?? 1 ),
	'per_page'  => absint( $_GET['per_page'] ?? 20 ),
);

$log_result       = $log_manager->get_logs_filtered( $filters );
$logs             = $log_result['items'];
$available_actions = $log_manager->get_distinct_actions();
?>
<div class="wrap ai-seo-geo-wrap">
	<h1><?php esc_html_e( 'Logs', 'ai-seo-geo-optimizer' ); ?></h1>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<form method="get" action="">
		<input type="hidden" name="page" value="ai-seo-geo-logs" />
		<table class="ai-seo-geo-filters">
			<tr>
				<td>
					<label for="post_id"><?php esc_html_e( 'Post ID', 'ai-seo-geo-optimizer' ); ?></label><br />
					<input id="post_id" name="post_id" type="number" min="0" value="<?php echo esc_attr( (string) $filters['post_id'] ); ?>" />
				</td>
				<td>
					<label for="action"><?php esc_html_e( 'Action', 'ai-seo-geo-optimizer' ); ?></label><br />
					<select id="action" name="action">
						<option value=""><?php esc_html_e( 'All Actions', 'ai-seo-geo-optimizer' ); ?></option>
						<?php foreach ( $available_actions as $action_name ) : ?>
							<option value="<?php echo esc_attr( $action_name ); ?>" <?php selected( $filters['action'], $action_name ); ?>><?php echo esc_html( $action_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<label for="date_from"><?php esc_html_e( 'Date From', 'ai-seo-geo-optimizer' ); ?></label><br />
					<input id="date_from" name="date_from" type="date" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
				</td>
				<td>
					<label for="date_to"><?php esc_html_e( 'Date To', 'ai-seo-geo-optimizer' ); ?></label><br />
					<input id="date_to" name="date_to" type="date" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
				</td>
				<td>
					<label for="per_page"><?php esc_html_e( 'Per Page', 'ai-seo-geo-optimizer' ); ?></label><br />
					<select id="per_page" name="per_page">
						<?php foreach ( array( 10, 20, 50, 100 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( (int) $filters['per_page'], $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<br />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'ai-seo-geo-optimizer' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-geo-logs' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'ai-seo-geo-optimizer' ); ?></a>
				</td>
			</tr>
		</table>
	</form>

	<h2><?php esc_html_e( 'Job & Action Logs', 'ai-seo-geo-optimizer' ); ?></h2>
	<table class="widefat striped">
		<thead><tr>
			<th><?php esc_html_e( 'ID', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Job ID', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Post Title', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Action', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Status', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Error Summary', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Created By', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Created At', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Rollback', 'ai-seo-geo-optimizer' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if ( empty( $logs ) ) : ?>
			<tr><td colspan="9"><?php esc_html_e( 'No logs found.', 'ai-seo-geo-optimizer' ); ?></td></tr>
		<?php else : foreach ( $logs as $row ) : ?>
			<tr>
				<td><?php echo esc_html( (string) $row['id'] ); ?></td>
				<td><?php echo esc_html( (string) $row['job_id'] ); ?></td>
				<td><?php echo esc_html( $row['post_title'] ? $row['post_title'] : '-' ); ?></td>
				<td><?php echo esc_html( $row['action'] ); ?></td>
				<td><?php echo esc_html( $row['job_status'] ? $row['job_status'] : '-' ); ?></td>
				<td>
					<?php
					$error_summary = '';
					if ( ! empty( $row['message'] ) ) {
						$error_summary = sanitize_text_field( (string) $row['message'] );
					}
					if ( '' === $error_summary && ! empty( $row['context_json'] ) ) {
						$context = json_decode( (string) $row['context_json'], true );
						if ( is_array( $context ) && ! empty( $context['error'] ) ) {
							$error_summary = sanitize_text_field( (string) $context['error'] );
						}
					}
					echo esc_html( '' !== $error_summary ? wp_trim_words( $error_summary, 14, '...' ) : '-' );
					?>
				</td>
				<td><?php echo esc_html( (string) $row['created_by'] ); ?></td>
				<td><?php echo esc_html( $row['created_at'] ); ?></td>
				<td>
					<?php
					$post_snapshots = $revision_manager->get_snapshots_by_post( absint( $row['post_id'] ) );
					$latest_snapshot = ! empty( $post_snapshots ) ? $post_snapshots[0] : null;
					?>
					<?php if ( ! empty( $latest_snapshot['id'] ) ) : ?>
						<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'Rollback to latest snapshot for this post?', 'ai-seo-geo-optimizer' ) ); ?>');">
							<?php wp_nonce_field( 'ai_seo_geo_logs_rollback', 'ai_seo_geo_logs_nonce' ); ?>
							<input type="hidden" name="ai_seo_geo_rollback_action" value="rollback" />
							<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (string) $latest_snapshot['id'] ); ?>" />
							<button type="submit" class="button button-small"><?php esc_html_e( 'Rollback', 'ai-seo-geo-optimizer' ); ?></button>
						</form>
					<?php else : ?>
						<?php esc_html_e( 'N/A', 'ai-seo-geo-optimizer' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>

	<?php
	$pagination_args = array(
		'page'      => 'ai-seo-geo-logs',
		'post_id'   => $filters['post_id'],
		'action'    => $filters['action'],
		'date_from' => $filters['date_from'],
		'date_to'   => $filters['date_to'],
		'per_page'  => $filters['per_page'],
	);
	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo wp_kses_post(
		paginate_links(
			array(
				'base'      => esc_url_raw( add_query_arg( array_merge( $pagination_args, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ) ),
				'format'    => '',
				'current'   => max( 1, (int) $log_result['paged'] ),
				'total'     => max( 1, (int) $log_result['total_pages'] ),
				'prev_text' => __( '&laquo;', 'ai-seo-geo-optimizer' ),
				'next_text' => __( '&raquo;', 'ai-seo-geo-optimizer' ),
			)
		)
	);
	echo '</div></div>';
	?>
</div>
