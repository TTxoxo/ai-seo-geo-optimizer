<?php
/**
 * Prompt templates page.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-geo-optimizer' ) );
}

$prompt_builder = new AI_SEO_GEO_Prompt_Builder();
$style_rules_manager = new AI_SEO_GEO_Style_Rules_Manager();
$template_map   = $prompt_builder->get_template_map();
$variables      = $prompt_builder->get_available_variables();
$style_rules    = $style_rules_manager->get_rules();

$active_template = isset( $_GET['template'] ) ? AI_SEO_GEO_Security::sanitize_text( $_GET['template'] ) : 'post_seo';
if ( ! isset( $template_map[ $active_template ] ) ) {
	$active_template = 'post_seo';
}

$notice      = '';
$notice_type = 'success';

if ( isset( $_POST['ai_seo_geo_prompt_action'] ) ) {
	AI_SEO_GEO_Security::verify_nonce_or_die( 'ai_seo_geo_prompt_templates_action', 'ai_seo_geo_nonce' );
	$action          = AI_SEO_GEO_Security::sanitize_text( $_POST['ai_seo_geo_prompt_action'] );
	$template_key    = AI_SEO_GEO_Security::sanitize_text( $_POST['template_key'] ?? '' );
	$active_template = isset( $template_map[ $template_key ] ) ? $template_key : $active_template;

	if ( 'save_template' === $action && isset( $template_map[ $template_key ] ) ) {
		$content = wp_kses_post( wp_unslash( $_POST['template_content'] ?? '' ) );
		if ( $prompt_builder->save_template( $template_key, $content ) ) {
			$notice = __( 'Template saved successfully.', 'ai-seo-geo-optimizer' );
		} else {
			$notice      = __( 'Failed to save template.', 'ai-seo-geo-optimizer' );
			$notice_type = 'error';
		}
	}

	if ( 'reset_template' === $action && isset( $template_map[ $template_key ] ) ) {
		if ( $prompt_builder->reset_template( $template_key ) ) {
			$notice = __( 'Template restored to default.', 'ai-seo-geo-optimizer' );
		} else {
			$notice      = __( 'Failed to reset template.', 'ai-seo-geo-optimizer' );
			$notice_type = 'error';
		}
	}

	if ( 'save_style_rules' === $action ) {
		if ( $style_rules_manager->save_rules( wp_unslash( $_POST ) ) ) {
			$notice = __( 'Content style rules saved successfully.', 'ai-seo-geo-optimizer' );
			$style_rules = $style_rules_manager->get_rules();
		} else {
			$notice      = __( 'Failed to save content style rules.', 'ai-seo-geo-optimizer' );
			$notice_type = 'error';
		}
	}
}

$current_template_content = $prompt_builder->get_template( $active_template );
?>
<div class="wrap ai-seo-geo-wrap">
	<h1><?php esc_html_e( 'Prompt Templates', 'ai-seo-geo-optimizer' ); ?></h1>
	<p><?php esc_html_e( 'Edit templates used by Prompt Builder. If no custom template exists, the plugin uses default templates from /templates.', 'ai-seo-geo-optimizer' ); ?></p>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<form method="get" action="">
		<input type="hidden" name="page" value="ai-seo-geo-prompts" />
		<label for="template"><?php esc_html_e( 'Select Template', 'ai-seo-geo-optimizer' ); ?></label>
		<select id="template" name="template">
			<?php foreach ( $template_map as $key => $template_info ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $active_template, $key ); ?>>
					<?php echo esc_html( $template_info['label'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button class="button" type="submit"><?php esc_html_e( 'Load', 'ai-seo-geo-optimizer' ); ?></button>
	</form>

	<hr />

	<form method="post" action="">
		<?php wp_nonce_field( 'ai_seo_geo_prompt_templates_action', 'ai_seo_geo_nonce' ); ?>
		<input type="hidden" name="template_key" value="<?php echo esc_attr( $active_template ); ?>" />

		<p>
			<label for="template_content"><strong><?php esc_html_e( 'Template Content', 'ai-seo-geo-optimizer' ); ?></strong></label>
		</p>
		<p>
			<textarea id="template_content" name="template_content" rows="24" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $current_template_content ); ?></textarea>
		</p>

		<p>
			<button type="submit" class="button button-primary" name="ai_seo_geo_prompt_action" value="save_template"><?php esc_html_e( 'Save Template', 'ai-seo-geo-optimizer' ); ?></button>
			<button type="submit" class="button" name="ai_seo_geo_prompt_action" value="reset_template" onclick="return confirm('<?php echo esc_js( __( 'Restore this template to plugin default?', 'ai-seo-geo-optimizer' ) ); ?>');"><?php esc_html_e( 'Restore Default', 'ai-seo-geo-optimizer' ); ?></button>
		</p>
	</form>

	<hr />
	<h2><?php esc_html_e( 'Content Style & SEO Boundary Rules', 'ai-seo-geo-optimizer' ); ?></h2>
	<form method="post" action="">
		<?php wp_nonce_field( 'ai_seo_geo_prompt_templates_action', 'ai_seo_geo_nonce' ); ?>
		<input type="hidden" name="ai_seo_geo_prompt_action" value="save_style_rules" />
		<table class="form-table"><tbody>
		<tr><th><?php esc_html_e( 'Writing Person - Post', 'ai-seo-geo-optimizer' ); ?></th><td><select name="writing_person_post"><?php foreach ( array( 'second_person','third_person','first_person_plural','neutral' ) as $p ) : ?><option value="<?php echo esc_attr( $p ); ?>" <?php selected( $style_rules['writing_person']['post'], $p ); ?>><?php echo esc_html( $p ); ?></option><?php endforeach; ?></select></td></tr>
		<tr><th><?php esc_html_e( 'Writing Person - Page', 'ai-seo-geo-optimizer' ); ?></th><td><select name="writing_person_page"><?php foreach ( array( 'second_person','third_person','first_person_plural','neutral' ) as $p ) : ?><option value="<?php echo esc_attr( $p ); ?>" <?php selected( $style_rules['writing_person']['page'], $p ); ?>><?php echo esc_html( $p ); ?></option><?php endforeach; ?></select></td></tr>
		<tr><th><?php esc_html_e( 'Writing Person - Product', 'ai-seo-geo-optimizer' ); ?></th><td><select name="writing_person_product"><?php foreach ( array( 'second_person','third_person','first_person_plural','neutral' ) as $p ) : ?><option value="<?php echo esc_attr( $p ); ?>" <?php selected( $style_rules['writing_person']['product'], $p ); ?>><?php echo esc_html( $p ); ?></option><?php endforeach; ?></select></td></tr>
		<tr><th><?php esc_html_e( 'Tone', 'ai-seo-geo-optimizer' ); ?></th><td><?php foreach ( array( 'professional','factual','concise','buyer-focused','technical','non-hype','human-edited' ) as $tone ) : ?><label style="margin-right:12px;"><input type="checkbox" name="tone[]" value="<?php echo esc_attr( $tone ); ?>" <?php checked( in_array( $tone, (array) $style_rules['tone'], true ) ); ?> /> <?php echo esc_html( $tone ); ?></label><?php endforeach; ?></td></tr>
		<tr><th><?php esc_html_e( 'Forbidden AI Phrases', 'ai-seo-geo-optimizer' ); ?></th><td><textarea name="forbidden_ai_phrases" rows="8" style="width:100%;"><?php echo esc_textarea( implode( "\n", (array) $style_rules['forbidden_ai_phrases'] ) ); ?></textarea></td></tr>
		<tr><th><?php esc_html_e( 'Forbidden Claims', 'ai-seo-geo-optimizer' ); ?></th><td><textarea name="forbidden_claims" rows="6" style="width:100%;"><?php echo esc_textarea( implode( "\n", (array) $style_rules['forbidden_claims'] ) ); ?></textarea></td></tr>
		<tr><th><?php esc_html_e( 'SEO Boundaries', 'ai-seo-geo-optimizer' ); ?></th><td>
			<input type="number" name="seo_title_min" value="<?php echo esc_attr( (string) $style_rules['seo_boundaries']['seo_title_min'] ); ?>" /> -
			<input type="number" name="seo_title_max" value="<?php echo esc_attr( (string) $style_rules['seo_boundaries']['seo_title_max'] ); ?>" /> <?php esc_html_e( 'SEO title chars', 'ai-seo-geo-optimizer' ); ?><br />
			<input type="number" name="meta_desc_min" value="<?php echo esc_attr( (string) $style_rules['seo_boundaries']['meta_desc_min'] ); ?>" /> -
			<input type="number" name="meta_desc_max" value="<?php echo esc_attr( (string) $style_rules['seo_boundaries']['meta_desc_max'] ); ?>" /> <?php esc_html_e( 'Meta chars', 'ai-seo-geo-optimizer' ); ?><br />
			<input type="number" name="faq_min" value="<?php echo esc_attr( (string) $style_rules['seo_boundaries']['faq_min'] ); ?>" /> -
			<input type="number" name="faq_max" value="<?php echo esc_attr( (string) $style_rules['seo_boundaries']['faq_max'] ); ?>" /> <?php esc_html_e( 'FAQ count', 'ai-seo-geo-optimizer' ); ?><br />
			<input type="number" name="paragraph_sentences_min" value="<?php echo esc_attr( (string) $style_rules['seo_boundaries']['paragraph_sentences_min'] ); ?>" /> -
			<input type="number" name="paragraph_sentences_max" value="<?php echo esc_attr( (string) $style_rules['seo_boundaries']['paragraph_sentences_max'] ); ?>" /> <?php esc_html_e( 'sentences per paragraph', 'ai-seo-geo-optimizer' ); ?><br />
			<label><input type="checkbox" name="allow_tables" value="1" <?php checked( ! empty( $style_rules['seo_boundaries']['allow_tables'] ) ); ?> /> <?php esc_html_e( 'Allow tables', 'ai-seo-geo-optimizer' ); ?></label>
			<label><input type="checkbox" name="allow_faq" value="1" <?php checked( ! empty( $style_rules['seo_boundaries']['allow_faq'] ) ); ?> /> <?php esc_html_e( 'Allow FAQ', 'ai-seo-geo-optimizer' ); ?></label>
			<label><input type="checkbox" name="allow_quick_answer" value="1" <?php checked( ! empty( $style_rules['seo_boundaries']['allow_quick_answer'] ) ); ?> /> <?php esc_html_e( 'Allow Quick Answer', 'ai-seo-geo-optimizer' ); ?></label>
			<label><input type="checkbox" name="allow_cta" value="1" <?php checked( ! empty( $style_rules['seo_boundaries']['allow_cta'] ) ); ?> /> <?php esc_html_e( 'Allow CTA', 'ai-seo-geo-optimizer' ); ?></label>
		</td></tr>
		</tbody></table>
		<?php submit_button( __( 'Save Content Rules', 'ai-seo-geo-optimizer' ) ); ?>
	</form>

	<hr />
	<h2><?php esc_html_e( 'Available Variables', 'ai-seo-geo-optimizer' ); ?></h2>
	<table class="widefat striped">
		<thead>
		<tr>
			<th><?php esc_html_e( 'Variable', 'ai-seo-geo-optimizer' ); ?></th>
			<th><?php esc_html_e( 'Description', 'ai-seo-geo-optimizer' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( $variables as $var_key => $var_desc ) : ?>
			<tr>
				<td><code><?php echo esc_html( $var_key ); ?></code></td>
				<td><?php echo esc_html( $var_desc ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
