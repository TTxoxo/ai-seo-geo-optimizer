<?php
/**
 * Providers page.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-geo-optimizer' ) );
}

$provider_manager = new AI_SEO_GEO_AI_Provider_Manager();
$templates        = $provider_manager->get_provider_templates();
$message          = '';
$message_type     = 'success';
$editing_provider = null;

if ( isset( $_POST['ai_seo_geo_provider_action'] ) ) {
	$action = AI_SEO_GEO_Security::sanitize_text( $_POST['ai_seo_geo_provider_action'] );

	if ( in_array( $action, array( 'save_provider', 'delete_provider', 'toggle_status', 'test_connection' ), true ) ) {
		AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_providers_action', 'ai_seo_geo_nonce' );
	}

	if ( 'save_provider' === $action ) {
		$provider_id = absint( $_POST['provider_id'] ?? 0 );
		$result      = $provider_manager->save_provider( $_POST, $provider_id );
		$message     = $result['message'];
		$message_type = $result['success'] ? 'success' : 'error';
	}

	if ( 'delete_provider' === $action ) {
		$provider_id = absint( $_POST['provider_id'] ?? 0 );
		$result      = $provider_manager->delete_provider( $provider_id );
		$message     = $result['message'];
		$message_type = $result['success'] ? 'success' : 'error';
	}

	if ( 'toggle_status' === $action ) {
		$provider_id = absint( $_POST['provider_id'] ?? 0 );
		$status      = AI_SEO_GEO_Security::sanitize_text( $_POST['status'] ?? 'inactive' );
		$result      = $provider_manager->update_status( $provider_id, $status );
		$message     = $result['message'];
		$message_type = $result['success'] ? 'success' : 'error';
	}

	if ( 'test_connection' === $action ) {
		$provider_id = absint( $_POST['provider_id'] ?? 0 );
		$result      = $provider_manager->test_connection( $provider_id );
		$message     = $result['message'];
		$message_type = $result['success'] ? 'success' : 'error';
	}
}

if ( isset( $_GET['edit'] ) ) {
	$editing_provider = $provider_manager->get_provider( absint( $_GET['edit'] ) );
}

$providers = $provider_manager->get_providers();

$selected_template_key = 'openai';
if ( $editing_provider ) {
	$selected_template_key = $editing_provider['provider_key'];
} elseif ( isset( $_POST['provider_key'] ) ) {
	$selected_template_key = AI_SEO_GEO_Security::sanitize_text( $_POST['provider_key'] );
}

$template = $templates[ $selected_template_key ] ?? $templates['custom'];

$provider_form = array(
	'id'            => $editing_provider['id'] ?? 0,
	'provider_key'  => $editing_provider['provider_key'] ?? $template['provider_key'],
	'provider_name' => $editing_provider['provider_name'] ?? $template['provider_name'],
	'base_url'      => $editing_provider['base_url'] ?? $template['base_url'],
	'default_model' => $editing_provider['default_model'] ?? $template['default_model'],
	'structured_output_mode' => $editing_provider['structured_output_mode'] ?? ( $template['structured_output_mode'] ?? 'auto' ),
	'timeout'       => $editing_provider['timeout'] ?? 60,
	'status'        => $editing_provider['status'] ?? 'active',
	'api_key_mask'  => $editing_provider['api_key_masked'] ?? '—',
);
?>
<div class="wrap ai-seo-geo-wrap">
	<h1><?php esc_html_e( 'AI Providers', 'ai-seo-geo-optimizer' ); ?></h1>

	<?php if ( ! empty( $message ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $message_type ? 'error' : 'success' ); ?> is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php echo $provider_form['id'] ? esc_html__( 'Edit Provider', 'ai-seo-geo-optimizer' ) : esc_html__( 'Add Provider', 'ai-seo-geo-optimizer' ); ?></h2>
	<form method="post" action="">
		<?php wp_nonce_field( 'ai_seo_geo_providers_action', 'ai_seo_geo_nonce' ); ?>
		<input type="hidden" name="ai_seo_geo_provider_action" value="save_provider" />
		<input type="hidden" name="provider_id" value="<?php echo esc_attr( (string) $provider_form['id'] ); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><label for="provider_key"><?php esc_html_e( 'Provider Key', 'ai-seo-geo-optimizer' ); ?></label></th>
				<td>
					<select id="provider_key" name="provider_key" <?php disabled( (int) $provider_form['id'] > 0 ); ?>>
						<?php foreach ( $templates as $template_item ) : ?>
							<option value="<?php echo esc_attr( $template_item['provider_key'] ); ?>" <?php selected( $provider_form['provider_key'], $template_item['provider_key'] ); ?>>
								<?php echo esc_html( $template_item['provider_name'] . ' (' . $template_item['provider_key'] . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Provider key cannot be changed after creation.', 'ai-seo-geo-optimizer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="provider_name"><?php esc_html_e( 'Provider Name', 'ai-seo-geo-optimizer' ); ?></label></th>
				<td><input class="regular-text" type="text" id="provider_name" name="provider_name" value="<?php echo esc_attr( $provider_form['provider_name'] ); ?>" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="base_url"><?php esc_html_e( 'Base URL', 'ai-seo-geo-optimizer' ); ?></label></th>
				<td><input class="regular-text" type="url" id="base_url" name="base_url" value="<?php echo esc_attr( $provider_form['base_url'] ); ?>" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="default_model"><?php esc_html_e( 'Default Model', 'ai-seo-geo-optimizer' ); ?></label></th>
				<td><input class="regular-text" type="text" id="default_model" name="default_model" value="<?php echo esc_attr( $provider_form['default_model'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="structured_output_mode"><?php esc_html_e( 'Structured Output Mode', 'ai-seo-geo-optimizer' ); ?></label></th>
				<td>
					<select id="structured_output_mode" name="structured_output_mode">
						<option value="auto" <?php selected( $provider_form['structured_output_mode'], 'auto' ); ?>><?php esc_html_e( 'Auto', 'ai-seo-geo-optimizer' ); ?></option>
						<option value="json_object" <?php selected( $provider_form['structured_output_mode'], 'json_object' ); ?>><?php esc_html_e( 'JSON Object', 'ai-seo-geo-optimizer' ); ?></option>
						<option value="prompt_only" <?php selected( $provider_form['structured_output_mode'], 'prompt_only' ); ?>><?php esc_html_e( 'Prompt Only', 'ai-seo-geo-optimizer' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="timeout"><?php esc_html_e( 'Timeout (seconds)', 'ai-seo-geo-optimizer' ); ?></label></th>
				<td><input class="small-text" type="number" min="10" max="300" id="timeout" name="timeout" value="<?php echo esc_attr( (string) $provider_form['timeout'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="status"><?php esc_html_e( 'Status', 'ai-seo-geo-optimizer' ); ?></label></th>
				<td>
					<select id="status" name="status">
						<option value="active" <?php selected( $provider_form['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'ai-seo-geo-optimizer' ); ?></option>
						<option value="inactive" <?php selected( $provider_form['status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'ai-seo-geo-optimizer' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="api_key"><?php esc_html_e( 'API Key', 'ai-seo-geo-optimizer' ); ?></label></th>
				<td>
					<input class="regular-text" type="password" id="api_key" name="api_key" value="" autocomplete="new-password" />
					<p class="description">
						<?php
						printf(
							/* translators: %s masked API key. */
							esc_html__( 'Current key: %s. Leave blank to keep existing key.', 'ai-seo-geo-optimizer' ),
							esc_html( $provider_form['api_key_mask'] )
						);
						?>
					</p>
				</td>
			</tr>
			</tbody>
		</table>

		<?php submit_button( $provider_form['id'] ? __( 'Update Provider', 'ai-seo-geo-optimizer' ) : __( 'Add Provider', 'ai-seo-geo-optimizer' ) ); ?>
	</form>

	<hr />
	<h2><?php esc_html_e( 'Provider List', 'ai-seo-geo-optimizer' ); ?></h2>

	<table class="widefat striped">
		<thead>
		<tr>
			<th><?php esc_html_e( 'ID', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Key', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Name', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Base URL', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Model', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Timeout', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'API Key', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Status', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'ai-seo-geo-optimizer' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php if ( empty( $providers ) ) : ?>
			<tr><td colspan="9"><?php esc_html_e( 'No providers found.', 'ai-seo-geo-optimizer' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $providers as $provider ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $provider['id'] ); ?></td>
					<td><?php echo esc_html( $provider['provider_key'] ); ?></td>
					<td><?php echo esc_html( $provider['provider_name'] ); ?></td>
					<td><?php echo esc_html( $provider['base_url'] ); ?></td>
					<td><?php echo esc_html( $provider['default_model'] ); ?></td>
					<td><?php echo esc_html( (string) $provider['timeout'] ); ?></td>
					<td><?php echo esc_html( $provider['api_key_masked'] ); ?></td>
					<td><?php echo esc_html( $provider['status'] ); ?></td>
					<td>
						<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ai-seo-geo-providers', 'edit' => (int) $provider['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'ai-seo-geo-optimizer' ); ?></a>

						<form style="display:inline-block" method="post" action="">
							<?php wp_nonce_field( 'ai_seo_geo_providers_action', 'ai_seo_geo_nonce' ); ?>
							<input type="hidden" name="ai_seo_geo_provider_action" value="toggle_status" />
							<input type="hidden" name="provider_id" value="<?php echo esc_attr( (string) $provider['id'] ); ?>" />
							<input type="hidden" name="status" value="<?php echo esc_attr( 'active' === $provider['status'] ? 'inactive' : 'active' ); ?>" />
							<button type="submit" class="button button-small">
								<?php echo esc_html( 'active' === $provider['status'] ? __( 'Deactivate', 'ai-seo-geo-optimizer' ) : __( 'Activate', 'ai-seo-geo-optimizer' ) ); ?>
							</button>
						</form>

						<form style="display:inline-block" method="post" action="">
							<?php wp_nonce_field( 'ai_seo_geo_providers_action', 'ai_seo_geo_nonce' ); ?>
							<input type="hidden" name="ai_seo_geo_provider_action" value="test_connection" />
							<input type="hidden" name="provider_id" value="<?php echo esc_attr( (string) $provider['id'] ); ?>" />
							<button type="submit" class="button button-small"><?php esc_html_e( 'Test Connection', 'ai-seo-geo-optimizer' ); ?></button>
						</form>

						<form style="display:inline-block" method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this provider?', 'ai-seo-geo-optimizer' ) ); ?>');">
							<?php wp_nonce_field( 'ai_seo_geo_providers_action', 'ai_seo_geo_nonce' ); ?>
							<input type="hidden" name="ai_seo_geo_provider_action" value="delete_provider" />
							<input type="hidden" name="provider_id" value="<?php echo esc_attr( (string) $provider['id'] ); ?>" />
							<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'ai-seo-geo-optimizer' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
