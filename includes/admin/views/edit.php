<?php
/**
 * New / edit automation screen. The repeatable parts (webhook keys, conditions,
 * actions) are rendered by assets/js/admin-editor.js.
 *
 * @package GlocalSainoActionsAutomation
 *
 * @var array $automation
 * @var array $cf7_forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$glocalsaino_aat_is_new  = ! $automation['id'];
$glocalsaino_aat_cfg     = wp_parse_args(
	$automation['trigger_config'],
	array(
		'form_id' => 0,
		'mode'    => 'form',
		'field'   => '',
		'value'   => '',
	)
);
$glocalsaino_aat_cf7     = GSAAT_Plugin::cf7_active();
$glocalsaino_aat_webhook = GSAAT_Automations::webhook_url( $automation['webhook_key'] );

$glocalsaino_aat_security = GSAAT_Webhook_Security::config( 'webhook' === $automation['trigger_type'] ? $automation['trigger_config'] : array() );
$glocalsaino_aat_last_ip  = $automation['id'] ? GSAAT_Logs::last_webhook_ip( $automation['id'] ) : '';
$glocalsaino_aat_link     = GSAAT_Trigger_Click::link_config( 'link' === $automation['trigger_type'] ? $automation['trigger_config'] : array() );
$glocalsaino_aat_button   = GSAAT_Trigger_Click::button_config( 'button' === $automation['trigger_type'] ? $automation['trigger_config'] : array() );
$glocalsaino_aat_widget   = GSAAT_Trigger_Widget::config( 'widget' === $automation['trigger_type'] ? $automation['trigger_config'] : array() );
$glocalsaino_aat_user     = GSAAT_Trigger_User::config( 'wp_user' === $automation['trigger_type'] ? $automation['trigger_config'] : array() );
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline">
		<?php echo $glocalsaino_aat_is_new ? esc_html__( 'New Automation', 'glocalsaino-actions-automation-based-on-trigger' ) : esc_html__( 'Edit Automation', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
	</h1>
	<a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LIST ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to automations', 'glocalsaino-actions-automation-based-on-trigger' ); ?></a>
	<hr class="wp-header-end">

	<?php GSAAT_Admin::render_notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="glocalsaino-aat-form" class="glocalsaino-aat-editor">
		<input type="hidden" name="action" value="glocalsaino_aat_save">
		<input type="hidden" name="id" value="<?php echo esc_attr( (string) $automation['id'] ); ?>">
		<input type="hidden" name="webhook_key" id="glocalsaino-aat-webhook-key" value="<?php echo esc_attr( $automation['webhook_key'] ); ?>">
		<input type="hidden" name="webhook_keys_json" id="glocalsaino-aat-webhook-keys-json" value="">
		<input type="hidden" name="conditions_json" id="glocalsaino-aat-conditions-json" value="">
		<input type="hidden" name="actions_json" id="glocalsaino-aat-actions-json" value="">
		<?php wp_nonce_field( 'glocalsaino_aat_save' ); ?>

		<!-- General -->
		<div class="glocalsaino-aat-card">
			<h2><?php esc_html_e( 'General', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="glocalsaino-aat-name"><?php esc_html_e( 'Automation name', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
					<td><input type="text" class="regular-text" id="glocalsaino-aat-name" name="name" required maxlength="191" value="<?php echo esc_attr( $automation['name'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
					<td>
						<fieldset>
							<label><input type="radio" name="status" value="active" <?php checked( 'active', $automation['status'] ); ?>> <?php esc_html_e( 'Active', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label>
							&nbsp;&nbsp;
							<label><input type="radio" name="status" value="inactive" <?php checked( 'inactive', $automation['status'] ); ?>> <?php esc_html_e( 'Inactive', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label>
						</fieldset>
						<?php if ( ! $glocalsaino_aat_is_new ) : ?>
							<p class="description">
								<?php
								/* translators: 1: number of completed runs, 2: creation date. */
								printf( esc_html__( 'Completed runs: %1$s · Created: %2$s', 'glocalsaino-actions-automation-based-on-trigger' ), esc_html( number_format_i18n( $automation['run_count'] ) ), esc_html( GSAAT_Admin::format_date( $automation['created_at'] ) ) );
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

		<!-- Trigger -->
		<div class="glocalsaino-aat-card">
			<h2><?php esc_html_e( 'Trigger', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h2>
			<p class="description"><?php esc_html_e( 'The event that starts the automation.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="glocalsaino-aat-trigger"><?php esc_html_e( 'Trigger event', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
					<td>
						<select id="glocalsaino-aat-trigger" name="trigger_type">
							<option value=""><?php esc_html_e( '— Choose a trigger —', 'glocalsaino-actions-automation-based-on-trigger' ); ?></option>
							<?php foreach ( GSAAT_Automations::trigger_types() as $glocalsaino_aat_type => $glocalsaino_aat_label ) : ?>
								<option value="<?php echo esc_attr( $glocalsaino_aat_type ); ?>" <?php selected( $glocalsaino_aat_type, $automation['trigger_type'] ); ?>><?php echo esc_html( $glocalsaino_aat_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<div data-glocalsaino-aat-trigger="cf7" hidden>
				<?php if ( ! $glocalsaino_aat_cf7 ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Contact Form 7 is not active. Install and activate it to use this trigger.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p></div>
				<?php elseif ( ! $cf7_forms ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'There are no Contact Form 7 forms yet. Create one first.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p></div>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="glocalsaino-aat-cf7-form"><?php esc_html_e( 'Form', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td>
							<select id="glocalsaino-aat-cf7-form" name="cf7_form_id">
								<option value="0"><?php esc_html_e( '— Choose a form —', 'glocalsaino-actions-automation-based-on-trigger' ); ?></option>
								<?php foreach ( $cf7_forms as $glocalsaino_aat_form ) : ?>
									<option value="<?php echo esc_attr( (string) $glocalsaino_aat_form['id'] ); ?>" <?php selected( (int) $glocalsaino_aat_form['id'], (int) $glocalsaino_aat_cfg['form_id'] ); ?>><?php echo esc_html( $glocalsaino_aat_form['title'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fire when', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="cf7_mode" value="form" <?php checked( 'field' !== $glocalsaino_aat_cfg['mode'] ); ?>> <?php esc_html_e( 'The form is submitted', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label><br>
								<label><input type="radio" name="cf7_mode" value="field" <?php checked( 'field', $glocalsaino_aat_cfg['mode'] ); ?>> <?php esc_html_e( 'The form is submitted with a specific value in a field', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<tr id="glocalsaino-aat-cf7-field-row">
						<th scope="row"><label for="glocalsaino-aat-cf7-field"><?php esc_html_e( 'Field and value', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td>
							<select id="glocalsaino-aat-cf7-field" name="cf7_field" data-selected="<?php echo esc_attr( $glocalsaino_aat_cfg['field'] ); ?>"></select>
							<span class="glocalsaino-aat-equals">=</span>
							<input type="text" class="regular-text" id="glocalsaino-aat-cf7-value" name="cf7_value" list="glocalsaino-aat-cf7-values" value="<?php echo esc_attr( $glocalsaino_aat_cfg['value'] ); ?>" placeholder="<?php esc_attr_e( 'Expected value', 'glocalsaino-actions-automation-based-on-trigger' ); ?>">
							<datalist id="glocalsaino-aat-cf7-values"></datalist>
							<p class="description"><?php esc_html_e( 'Not case-sensitive. Leave the value empty to fire whenever the field is filled in with any value.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div data-glocalsaino-aat-trigger="webhook" hidden>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook URL', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<div class="glocalsaino-aat-copy-row">
								<code id="glocalsaino-aat-webhook-url" class="glocalsaino-aat-break"><?php echo esc_html( $glocalsaino_aat_webhook ); ?></code>
								<button type="button" class="button button-small glocalsaino-aat-copy" data-copy-target="glocalsaino-aat-webhook-url"><?php esc_html_e( 'Copy', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Paste this URL in the external system. It accepts GET, POST and PUT requests; the data can be sent as query string parameters, as a form body or as a JSON body.', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
								<?php if ( $glocalsaino_aat_is_new ) : ?>
									<strong><?php esc_html_e( 'The URL starts working once the automation is saved.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></strong>
								<?php endif; ?>
							</p>
							<?php if ( ! $glocalsaino_aat_is_new ) : ?>
								<label class="glocalsaino-aat-top-gap">
									<input type="checkbox" name="regenerate_webhook_key" value="1">
									<?php esc_html_e( 'Generate a new URL when saving (the current one will stop working)', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
								</label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Keys to receive', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<div id="glocalsaino-aat-webhook-keys" class="glocalsaino-aat-repeater"></div>
							<button type="button" class="button" id="glocalsaino-aat-add-key"><?php esc_html_e( 'Add key', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
							<p class="description"><?php esc_html_e( 'The values received under these keys can be used in the conditions and actions. Nested JSON keys use dots, e.g. customer.email.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
							<p class="glocalsaino-aat-top-gap"><strong><?php esc_html_e( 'Example call:', 'glocalsaino-actions-automation-based-on-trigger' ); ?></strong></p>
							<div class="glocalsaino-aat-copy-row">
								<code id="glocalsaino-aat-webhook-example" class="glocalsaino-aat-break"></code>
								<button type="button" class="button button-small glocalsaino-aat-copy" data-copy-target="glocalsaino-aat-webhook-example"><?php esc_html_e( 'Copy', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
							</div>
							<p id="glocalsaino-aat-webhook-example-header" class="glocalsaino-aat-muted" hidden></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Security', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td class="glocalsaino-aat-security">
							<p class="description"><?php esc_html_e( 'Optional. Without any protection, whoever knows the URL can call it. Each option you enable must also be configured in the sending system.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>

							<!-- Token -->
							<div class="glocalsaino-aat-security__layer">
								<label class="glocalsaino-aat-security__toggle">
									<input type="checkbox" name="sec_token_enabled" value="1" data-glocalsaino-aat-toggle="glocalsaino-aat-sec-token" <?php checked( $glocalsaino_aat_security['token']['enabled'] ); ?>>
									<?php esc_html_e( 'Require an authentication token in a header', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
								</label>
								<div id="glocalsaino-aat-sec-token" class="glocalsaino-aat-security__body">
									<div class="glocalsaino-aat-field">
										<label><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Header name', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span>
											<input type="text" class="regular-text code" id="glocalsaino-aat-sec-token-header" name="sec_token_header" value="<?php echo esc_attr( $glocalsaino_aat_security['token']['header'] ); ?>"></label>
									</div>
									<div class="glocalsaino-aat-field">
										<label><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Token', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span></label>
										<div class="glocalsaino-aat-copy-row">
											<input type="text" class="regular-text code" id="glocalsaino-aat-sec-token-value" name="sec_token_value" value="<?php echo esc_attr( $glocalsaino_aat_security['token']['value'] ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Token', 'glocalsaino-actions-automation-based-on-trigger' ); ?>">
											<button type="button" class="button button-small" data-glocalsaino-aat-generate="glocalsaino-aat-sec-token-value"><?php esc_html_e( 'Generate', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
										</div>
									</div>
									<p class="description"><?php esc_html_e( 'You create the token and give it to the sender together with the URL; their system must send it in this header on every call. With the Authorization header, "Bearer <token>" is accepted too.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
								</div>
							</div>

							<!-- IP list -->
							<div class="glocalsaino-aat-security__layer">
								<label class="glocalsaino-aat-security__toggle">
									<input type="checkbox" name="sec_ip_enabled" value="1" data-glocalsaino-aat-toggle="glocalsaino-aat-sec-ip" <?php checked( $glocalsaino_aat_security['ip']['enabled'] ); ?>>
									<?php esc_html_e( 'Only accept calls from these IP addresses', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
								</label>
								<div id="glocalsaino-aat-sec-ip" class="glocalsaino-aat-security__body">
									<textarea name="sec_ip_list" rows="4" class="large-text code" placeholder="203.0.113.10&#10;198.51.100.0/24&#10;2001:db8::/32" aria-label="<?php esc_attr_e( 'Allowed IP addresses', 'glocalsaino-actions-automation-based-on-trigger' ); ?>"><?php echo esc_textarea( implode( "\n", (array) $glocalsaino_aat_security['ip']['list'] ) ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'One per line. IPv4 or IPv6, single addresses or CIDR ranges. Ask the sender which IP addresses their servers call from.', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
										<?php if ( '' !== $glocalsaino_aat_last_ip ) : ?>
											<br>
											<?php
											/* translators: %s: IP address. */
											printf( esc_html__( 'The last call to this webhook came from %s.', 'glocalsaino-actions-automation-based-on-trigger' ), '<code>' . esc_html( $glocalsaino_aat_last_ip ) . '</code>' );
											?>
										<?php endif; ?>
										<br>
										<?php
										printf(
											/* translators: %s: link to the Settings screen. */
											esc_html__( 'If your site is behind a proxy or Cloudflare, choose how the caller IP is detected in %s.', 'glocalsaino-actions-automation-based-on-trigger' ),
											'<a href="' . esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_SETTING ) ) . '">' . esc_html__( 'Settings', 'glocalsaino-actions-automation-based-on-trigger' ) . '</a>'
										);
										?>
									</p>
								</div>
							</div>

							<!-- Signature -->
							<div class="glocalsaino-aat-security__layer">
								<label class="glocalsaino-aat-security__toggle">
									<input type="checkbox" name="sec_sig_enabled" value="1" data-glocalsaino-aat-toggle="glocalsaino-aat-sec-sig" <?php checked( $glocalsaino_aat_security['signature']['enabled'] ); ?>>
									<?php esc_html_e( 'Verify an HMAC signature', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
								</label>
								<div id="glocalsaino-aat-sec-sig" class="glocalsaino-aat-security__body">
									<div class="glocalsaino-aat-field">
										<label><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Sending service', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span>
											<select name="sec_sig_preset" id="glocalsaino-aat-sec-sig-preset">
												<?php foreach ( GSAAT_Webhook_Security::presets() as $glocalsaino_aat_key => $glocalsaino_aat_label ) : ?>
													<option value="<?php echo esc_attr( $glocalsaino_aat_key ); ?>" <?php selected( $glocalsaino_aat_key, $glocalsaino_aat_security['signature']['preset'] ); ?>><?php echo esc_html( $glocalsaino_aat_label ); ?></option>
												<?php endforeach; ?>
											</select></label>
									</div>
									<?php
									$glocalsaino_aat_preset_help = array(
										'generic'     => __( 'The sender computes an HMAC of the raw request body (or of the query string exactly as sent, when there is no body) with the shared secret and sends it in the signature header, optionally prefixed with the algorithm (e.g. sha256=…). If you set a timestamp header, the signed content is "timestamp.body" and old calls are rejected.', 'glocalsaino-actions-automation-based-on-trigger' ),
										'stripe'      => __( 'In Stripe, go to Developers → Webhooks, add this URL as an endpoint and paste here its signing secret (whsec_…). Calls older than the maximum age are rejected.', 'glocalsaino-actions-automation-based-on-trigger' ),
										'shopify'     => __( 'Paste here the secret Shopify signs your webhooks with (Settings → Notifications → Webhooks), or your app\'s client secret if the webhook is created by an app.', 'glocalsaino-actions-automation-based-on-trigger' ),
										'woocommerce' => __( 'In WooCommerce → Settings → Advanced → Webhooks, use this URL as the Delivery URL and the same Secret as here (you can generate it here and paste it there).', 'glocalsaino-actions-automation-based-on-trigger' ),
										'github'      => __( 'In the repository, go to Settings → Webhooks, use this URL as the Payload URL, "application/json" as the content type and the same Secret as here.', 'glocalsaino-actions-automation-based-on-trigger' ),
									);
									foreach ( $glocalsaino_aat_preset_help as $glocalsaino_aat_key => $glocalsaino_aat_help ) :
										?>
										<p class="description" data-glocalsaino-aat-preset-help="<?php echo esc_attr( $glocalsaino_aat_key ); ?>"><?php echo esc_html( $glocalsaino_aat_help ); ?></p>
									<?php endforeach; ?>
									<div class="glocalsaino-aat-field glocalsaino-aat-top-gap">
										<label><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Signing secret', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span></label>
										<div class="glocalsaino-aat-copy-row">
											<input type="text" class="regular-text code" id="glocalsaino-aat-sec-sig-secret" name="sec_sig_secret" value="<?php echo esc_attr( $glocalsaino_aat_security['signature']['secret'] ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Signing secret', 'glocalsaino-actions-automation-based-on-trigger' ); ?>">
											<button type="button" class="button button-small" data-glocalsaino-aat-generate="glocalsaino-aat-sec-sig-secret"><?php esc_html_e( 'Generate', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
										</div>
									</div>
									<div class="glocalsaino-aat-inline" data-glocalsaino-aat-preset-only="generic">
										<div class="glocalsaino-aat-field">
											<label><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Signature header', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span>
												<input type="text" class="code" name="sec_sig_header" value="<?php echo esc_attr( $glocalsaino_aat_security['signature']['header'] ); ?>"></label>
										</div>
										<div class="glocalsaino-aat-field">
											<label><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Algorithm', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span>
												<select name="sec_sig_algo">
													<?php foreach ( GSAAT_Webhook_Security::algorithms() as $glocalsaino_aat_algo ) : ?>
														<option value="<?php echo esc_attr( $glocalsaino_aat_algo ); ?>" <?php selected( $glocalsaino_aat_algo, $glocalsaino_aat_security['signature']['algo'] ); ?>><?php echo esc_html( strtoupper( $glocalsaino_aat_algo ) ); ?></option>
													<?php endforeach; ?>
												</select></label>
										</div>
										<div class="glocalsaino-aat-field">
											<label><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Encoding', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span>
												<select name="sec_sig_encoding">
													<option value="hex" <?php selected( 'hex', $glocalsaino_aat_security['signature']['encoding'] ); ?>><?php esc_html_e( 'Hexadecimal', 'glocalsaino-actions-automation-based-on-trigger' ); ?></option>
													<option value="base64" <?php selected( 'base64', $glocalsaino_aat_security['signature']['encoding'] ); ?>>Base64</option>
												</select></label>
										</div>
										<div class="glocalsaino-aat-field">
											<label><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Timestamp header (optional)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span>
												<input type="text" class="code" name="sec_sig_timestamp_header" placeholder="X-Timestamp" value="<?php echo esc_attr( $glocalsaino_aat_security['signature']['timestamp_header'] ); ?>"></label>
										</div>
									</div>
									<div class="glocalsaino-aat-field" data-glocalsaino-aat-preset-only="generic stripe">
										<label><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Maximum age of a call', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span>
											<input type="number" min="30" max="86400" step="1" class="small-text" name="sec_sig_tolerance" value="<?php echo esc_attr( (string) $glocalsaino_aat_security['signature']['tolerance'] ); ?>">
											<?php esc_html_e( 'seconds', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label>
										<p class="description"><?php esc_html_e( 'Protects against replayed calls. For the custom HMAC it only applies when a timestamp header is set.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
									</div>
								</div>
							</div>
						</td>
					</tr>
				</table>
			</div>

			<!-- Link -->
			<div data-glocalsaino-aat-trigger="link" hidden>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="glocalsaino-aat-link-destination"><?php esc_html_e( 'Destination', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td>
							<input type="url" class="large-text" id="glocalsaino-aat-link-destination" name="link_destination" placeholder="https://" value="<?php echo esc_attr( $glocalsaino_aat_link['destination'] ); ?>">
							<p class="description"><?php esc_html_e( 'Where the visitor lands after clicking. A "Redirect the user" action can send them somewhere else instead.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Link to use', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<div class="glocalsaino-aat-copy-row">
								<code id="glocalsaino-aat-link-url" class="glocalsaino-aat-break"><?php echo esc_html( GSAAT_Trigger_Click::link_url( $automation['webhook_key'] ) ); ?></code>
								<button type="button" class="button button-small glocalsaino-aat-copy" data-copy-target="glocalsaino-aat-link-url"><?php esc_html_e( 'Copy', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
							</div>
							<p class="description"><?php esc_html_e( 'Use this address in any link, menu, button or email. Each click runs the automation and then takes the visitor to the destination.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
							<?php if ( $glocalsaino_aat_is_new ) : ?>
								<p class="description"><strong><?php esc_html_e( 'The link starts working once the automation is saved.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></strong></p>
							<?php else : ?>
								<p class="glocalsaino-aat-top-gap"><?php esc_html_e( 'Or insert it in a page or post with this shortcode:', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
								<div class="glocalsaino-aat-copy-row">
									<code id="glocalsaino-aat-link-shortcode">[glocalsaino_aat_link id="<?php echo esc_attr( (string) $automation['id'] ); ?>"]<?php esc_html_e( 'Link text', 'glocalsaino-actions-automation-based-on-trigger' ); ?>[/glocalsaino_aat_link]</code>
									<button type="button" class="button button-small glocalsaino-aat-copy" data-copy-target="glocalsaino-aat-link-shortcode"><?php esc_html_e( 'Copy', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
								</div>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="glocalsaino-aat-link-params"><?php esc_html_e( 'Parameters (optional)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="glocalsaino-aat-link-params" name="link_params" placeholder="email, campaign" value="<?php echo esc_attr( implode( ', ', (array) $glocalsaino_aat_link['params'] ) ); ?>">
							<p class="description"><?php esc_html_e( 'Names of the values you add to the link, e.g. …&email=ana@example.com. They become fields you can use in the conditions and actions. Separate them with commas.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
							<label class="glocalsaino-aat-top-gap"><input type="checkbox" name="link_pass_params" value="1" <?php checked( $glocalsaino_aat_link['pass_params'] ); ?>> <?php esc_html_e( 'Pass these values on to the destination URL', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Robots', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<label><input type="checkbox" name="link_ignore_bots" value="1" <?php checked( $glocalsaino_aat_link['ignore_bots'] ); ?>> <?php esc_html_e( 'Ignore visits from search engines and link previews (recommended)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label>
							<p class="description"><?php esc_html_e( 'Repeated clicks of the same visitor within 10 seconds count once.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Button -->
			<div data-glocalsaino-aat-trigger="button" hidden>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="glocalsaino-aat-button-label"><?php esc_html_e( 'Button text', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td><input type="text" class="regular-text" id="glocalsaino-aat-button-label" name="button_label" value="<?php echo esc_attr( $glocalsaino_aat_button['label'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="glocalsaino-aat-button-class"><?php esc_html_e( 'CSS classes (optional)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="glocalsaino-aat-button-class" name="button_class" value="<?php echo esc_attr( $glocalsaino_aat_button['class'] ); ?>">
							<p class="description"><?php esc_html_e( 'The button already uses your theme\'s button style. Add classes to change it.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="glocalsaino-aat-button-message"><?php esc_html_e( 'Message after the click (optional)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td><input type="text" class="large-text" id="glocalsaino-aat-button-message" name="button_message" value="<?php echo esc_attr( $glocalsaino_aat_button['message'] ); ?>" placeholder="<?php esc_attr_e( 'Thank you!', 'glocalsaino-actions-automation-based-on-trigger' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="glocalsaino-aat-button-redirect"><?php esc_html_e( 'Go to after the click (optional)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td>
							<input type="url" class="large-text" id="glocalsaino-aat-button-redirect" name="button_redirect" placeholder="https://" value="<?php echo esc_attr( $glocalsaino_aat_button['redirect'] ); ?>">
							<p class="description"><?php esc_html_e( 'Leave empty to stay on the same page and show the message.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Shortcode', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<?php if ( $glocalsaino_aat_is_new ) : ?>
								<p class="description"><strong><?php esc_html_e( 'The shortcode is shown once the automation is saved.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></strong></p>
							<?php else : ?>
								<div class="glocalsaino-aat-copy-row">
									<code id="glocalsaino-aat-button-shortcode">[glocalsaino_aat_button id="<?php echo esc_attr( (string) $automation['id'] ); ?>"]</code>
									<button type="button" class="button button-small glocalsaino-aat-copy" data-copy-target="glocalsaino-aat-button-shortcode"><?php esc_html_e( 'Copy', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
								</div>
								<p class="description"><?php esc_html_e( 'Paste it in any page or post (in the block editor, use a Shortcode block).', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<!-- Embeddable widget -->
			<div data-glocalsaino-aat-trigger="widget" hidden>
				<input type="hidden" name="widget_fields_json" id="glocalsaino-aat-widget-fields-json" value="">
				<p class="description"><?php esc_html_e( 'A button, link or form that other websites show with a small piece of code. When a visitor uses it there, this automation runs here.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Widget type', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( GSAAT_Trigger_Widget::kinds() as $glocalsaino_aat_kind => $glocalsaino_aat_label ) : ?>
									<label><input type="radio" name="widget_kind" value="<?php echo esc_attr( $glocalsaino_aat_kind ); ?>" <?php checked( $glocalsaino_aat_kind, $glocalsaino_aat_widget['kind'] ); ?>> <?php echo esc_html( $glocalsaino_aat_label ); ?></label>&nbsp;&nbsp;
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="glocalsaino-aat-widget-title"><?php esc_html_e( 'Heading and text (optional)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="glocalsaino-aat-widget-title" name="widget_title" placeholder="<?php esc_attr_e( 'Heading', 'glocalsaino-actions-automation-based-on-trigger' ); ?>" value="<?php echo esc_attr( $glocalsaino_aat_widget['title'] ); ?>">
							<textarea class="large-text glocalsaino-aat-top-gap" rows="2" name="widget_intro" aria-label="<?php esc_attr_e( 'Text above the widget', 'glocalsaino-actions-automation-based-on-trigger' ); ?>" placeholder="<?php esc_attr_e( 'Text above the widget', 'glocalsaino-actions-automation-based-on-trigger' ); ?>"><?php echo esc_textarea( $glocalsaino_aat_widget['intro'] ); ?></textarea>
						</td>
					</tr>
					<tr data-glocalsaino-aat-widget-kind="form">
						<th scope="row"><?php esc_html_e( 'Form fields', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<div id="glocalsaino-aat-widget-fields"></div>
							<button type="button" class="button" id="glocalsaino-aat-add-widget-field"><?php esc_html_e( 'Add field', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
							<p class="description"><?php esc_html_e( 'The field name is how you use the value in conditions and actions, e.g. {{email}}. Only these fields are accepted; anything else sent is ignored.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="glocalsaino-aat-widget-text"><?php esc_html_e( 'Button / link text', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td><input type="text" class="regular-text" id="glocalsaino-aat-widget-text" name="widget_text" value="<?php echo esc_attr( $glocalsaino_aat_widget['text'] ); ?>"></td>
					</tr>
					<tr data-glocalsaino-aat-widget-kind="link">
						<th scope="row"><label for="glocalsaino-aat-widget-destination"><?php esc_html_e( 'Link destination', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td>
							<input type="url" class="large-text" id="glocalsaino-aat-widget-destination" name="widget_destination" placeholder="https://" value="<?php echo esc_attr( $glocalsaino_aat_widget['destination'] ); ?>">
							<p class="description"><?php esc_html_e( 'The page the visitor goes to after clicking (a "Redirect the user" action can choose another one).', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
					<tr data-glocalsaino-aat-widget-kind="form button">
						<th scope="row"><label for="glocalsaino-aat-widget-message"><?php esc_html_e( 'After sending', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="glocalsaino-aat-widget-message" name="widget_message" value="<?php echo esc_attr( $glocalsaino_aat_widget['message'] ); ?>" placeholder="<?php esc_attr_e( 'Message shown in the widget', 'glocalsaino-actions-automation-based-on-trigger' ); ?>">
							<input type="url" class="large-text glocalsaino-aat-top-gap" name="widget_redirect" aria-label="<?php esc_attr_e( 'Or go to this page (optional)', 'glocalsaino-actions-automation-based-on-trigger' ); ?>" placeholder="<?php esc_attr_e( 'Or go to this page (optional): https://', 'glocalsaino-actions-automation-based-on-trigger' ); ?>" value="<?php echo esc_attr( $glocalsaino_aat_widget['redirect'] ); ?>">
							<p class="description"><?php esc_html_e( 'Going to another page works best with the script embed code; with the plain iframe the visitor may get a "Continue" link instead.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Design', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<div class="glocalsaino-aat-inline">
								<label class="glocalsaino-aat-field"><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Main color', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span><input type="color" name="widget_primary" value="<?php echo esc_attr( $glocalsaino_aat_widget['design']['primary'] ); ?>"></label>
								<label class="glocalsaino-aat-field"><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Text color', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span><input type="color" name="widget_text_color" value="<?php echo esc_attr( $glocalsaino_aat_widget['design']['text'] ); ?>"></label>
								<label class="glocalsaino-aat-field"><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Background', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span><input type="color" name="widget_background" value="<?php echo esc_attr( $glocalsaino_aat_widget['design']['background'] ); ?>"></label>
								<label class="glocalsaino-aat-field"><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Corner radius (px)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span><input type="number" min="0" max="40" class="small-text" name="widget_radius" value="<?php echo esc_attr( (string) $glocalsaino_aat_widget['design']['radius'] ); ?>"></label>
								<label class="glocalsaino-aat-field"><span class="glocalsaino-aat-field__label"><?php esc_html_e( 'Font', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span>
									<select name="widget_font">
										<?php foreach ( GSAAT_Trigger_Widget::fonts() as $glocalsaino_aat_font => $glocalsaino_aat_font_def ) : ?>
											<option value="<?php echo esc_attr( $glocalsaino_aat_font ); ?>" <?php selected( $glocalsaino_aat_font, $glocalsaino_aat_widget['design']['font'] ); ?>><?php echo esc_html( $glocalsaino_aat_font_def[0] ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							</div>
							<details class="glocalsaino-aat-details-toggle" <?php echo '' !== $glocalsaino_aat_widget['design']['custom_css'] ? 'open' : ''; ?>>
								<summary><?php esc_html_e( 'Custom CSS (optional)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></summary>
								<textarea class="large-text code" rows="5" name="widget_custom_css" aria-label="<?php esc_attr_e( 'Custom CSS', 'glocalsaino-actions-automation-based-on-trigger' ); ?>" placeholder=".gsaat-w__button { text-transform: uppercase; }"><?php echo esc_textarea( $glocalsaino_aat_widget['design']['custom_css'] ); ?></textarea>
							</details>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="glocalsaino-aat-widget-domains"><?php esc_html_e( 'Allowed websites', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="3" id="glocalsaino-aat-widget-domains" name="widget_domains" placeholder="example.com&#10;*.example.org"><?php echo esc_textarea( implode( "\n", (array) $glocalsaino_aat_widget['domains'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Only these websites can show the widget (one per line; *.example.org includes its subdomains). Recommended. Leave empty to allow any website.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Anti-spam', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<label>
								<?php esc_html_e( 'At most', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
								<input type="number" min="0" max="1000" class="small-text" name="widget_rate_limit" value="<?php echo esc_attr( (string) $glocalsaino_aat_widget['rate_limit'] ); ?>">
								<?php esc_html_e( 'submissions per hour from the same connection (0 = no limit)', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
							</label>
							<p class="glocalsaino-aat-top-gap">
								<label><?php esc_html_e( 'Captcha', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
									<select name="widget_captcha">
										<option value=""><?php esc_html_e( 'None', 'glocalsaino-actions-automation-based-on-trigger' ); ?></option>
										<?php foreach ( GSAAT_Captcha::providers() as $glocalsaino_aat_provider => $glocalsaino_aat_provider_def ) : ?>
											<option value="<?php echo esc_attr( $glocalsaino_aat_provider ); ?>" <?php selected( $glocalsaino_aat_provider, $glocalsaino_aat_widget['captcha'] ); ?>>
												<?php
												echo esc_html( $glocalsaino_aat_provider_def['label'] );
												if ( ! GSAAT_Captcha::configured( $glocalsaino_aat_provider ) ) {
													echo ' ' . esc_html__( '(add its keys in Settings)', 'glocalsaino-actions-automation-based-on-trigger' );
												}
												?>
											</option>
										<?php endforeach; ?>
									</select>
								</label>
							</p>
							<p class="description"><?php esc_html_e( 'Always active: a single-use code issued when the widget loads, a minimum time to send and a hidden trap field for bots.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Code for the other website', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<?php if ( $glocalsaino_aat_is_new ) : ?>
								<p class="description"><strong><?php esc_html_e( 'The code is shown once the automation is saved.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></strong></p>
							<?php else : ?>
								<?php $glocalsaino_aat_codes = GSAAT_Trigger_Widget::embed_codes( $automation ); ?>
								<p><strong><?php esc_html_e( 'Recommended (adjusts its height and can take the visitor to another page):', 'glocalsaino-actions-automation-based-on-trigger' ); ?></strong></p>
								<div class="glocalsaino-aat-copy-row">
									<code id="glocalsaino-aat-widget-script" class="glocalsaino-aat-break"><?php echo esc_html( $glocalsaino_aat_codes['script'] ); ?></code>
									<button type="button" class="button button-small glocalsaino-aat-copy" data-copy-target="glocalsaino-aat-widget-script"><?php esc_html_e( 'Copy', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
								</div>
								<p class="glocalsaino-aat-top-gap"><strong><?php esc_html_e( 'Or, where scripts are not allowed, a plain iframe:', 'glocalsaino-actions-automation-based-on-trigger' ); ?></strong></p>
								<div class="glocalsaino-aat-copy-row">
									<code id="glocalsaino-aat-widget-iframe" class="glocalsaino-aat-break"><?php echo esc_html( $glocalsaino_aat_codes['iframe'] ); ?></code>
									<button type="button" class="button button-small glocalsaino-aat-copy" data-copy-target="glocalsaino-aat-widget-iframe"><?php esc_html_e( 'Copy', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
								</div>
								<p class="description"><?php esc_html_e( 'If the widget does not appear on the other website, check that it is in "Allowed websites" and that no security plugin on this site adds an X-Frame-Options header to every page.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( ! $glocalsaino_aat_is_new && 'widget' === $automation['trigger_type'] ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Preview', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
							<td>
								<iframe class="glocalsaino-aat-widget-preview" src="<?php echo esc_url( GSAAT_Trigger_Widget::widget_url( $automation['webhook_key'] ) ); ?>" title="<?php esc_attr_e( 'Widget preview', 'glocalsaino-actions-automation-based-on-trigger' ); ?>"></iframe>
								<p class="description"><?php esc_html_e( 'Shows the saved version. Sending it here really runs the automation.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Statistics (last 30 days)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
							<td>
								<?php $glocalsaino_aat_stats = GSAAT_Widget_Stats::summary( $automation['id'] ); ?>
								<?php if ( ! $glocalsaino_aat_stats ) : ?>
									<p class="glocalsaino-aat-muted"><?php esc_html_e( 'No activity yet.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
								<?php else : ?>
									<table class="widefat striped glocalsaino-aat-stats">
										<thead>
											<tr>
												<th scope="col"><?php esc_html_e( 'Website', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
												<th scope="col"><?php esc_html_e( 'Views', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
												<th scope="col"><?php esc_html_e( 'Submissions', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
												<th scope="col"><?php esc_html_e( 'Conversion', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
												<th scope="col"><?php esc_html_e( 'Rejected', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $glocalsaino_aat_stats as $glocalsaino_aat_row ) : ?>
												<tr>
													<td><?php echo esc_html( '' !== $glocalsaino_aat_row['domain'] ? $glocalsaino_aat_row['domain'] : __( '(unknown)', 'glocalsaino-actions-automation-based-on-trigger' ) ); ?></td>
													<td><?php echo esc_html( number_format_i18n( (int) $glocalsaino_aat_row['views'] ) ); ?></td>
													<td><?php echo esc_html( number_format_i18n( (int) $glocalsaino_aat_row['submissions'] ) ); ?></td>
													<td><?php echo esc_html( (int) $glocalsaino_aat_row['views'] > 0 ? number_format_i18n( 100 * (int) $glocalsaino_aat_row['submissions'] / (int) $glocalsaino_aat_row['views'], 1 ) . ' %' : '—' ); ?></td>
													<td><?php echo esc_html( number_format_i18n( (int) $glocalsaino_aat_row['rejected'] ) ); ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
									<p class="description"><?php esc_html_e( 'A view is counted each time the widget loads; rejected includes spam, expired forms and validation errors (details in Webhook Logs → Widgets).', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<!-- WordPress user events -->
			<div data-glocalsaino-aat-trigger="wp_user" hidden>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Fire when', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( GSAAT_Trigger_User::events() as $glocalsaino_aat_event => $glocalsaino_aat_label ) : ?>
									<label><input type="checkbox" name="user_events[]" value="<?php echo esc_attr( $glocalsaino_aat_event ); ?>" <?php checked( in_array( $glocalsaino_aat_event, (array) $glocalsaino_aat_user['events'], true ) ); ?>> <?php echo esc_html( $glocalsaino_aat_label ); ?></label><br>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Available fields: user_id, user_login, user_email, display_name, first_name, last_name, roles, registered and event. Passwords are never included.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<?php
			/**
			 * Prints the settings of add-on trigger types. Wrap each one in
			 * <div data-glocalsaino-aat-trigger="your-type" hidden> so it is shown only for that trigger.
			 *
			 * @param array $automation Automation being edited.
			 */
			do_action( 'glocalsaino_aat_trigger_settings', $automation );
			?>
		</div>

		<!-- Conditions -->
		<div class="glocalsaino-aat-card">
			<h2><?php esc_html_e( 'Conditions (optional)', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Extra requirements the event data must meet for the actions to run.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
			<p>
				<label for="glocalsaino-aat-condition-logic"><?php esc_html_e( 'Run the actions when', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label>
				<select id="glocalsaino-aat-condition-logic">
					<option value="all"><?php esc_html_e( 'all the conditions are met', 'glocalsaino-actions-automation-based-on-trigger' ); ?></option>
					<option value="any"><?php esc_html_e( 'any of the conditions is met', 'glocalsaino-actions-automation-based-on-trigger' ); ?></option>
				</select>
			</p>
			<div id="glocalsaino-aat-conditions" class="glocalsaino-aat-repeater"></div>
			<button type="button" class="button" id="glocalsaino-aat-add-condition"><?php esc_html_e( 'Add field condition', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
			<button type="button" class="button" id="glocalsaino-aat-add-user-condition"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span> <?php esc_html_e( 'Add user condition', 'glocalsaino-actions-automation-based-on-trigger' ); ?></button>
		</div>

		<!-- Actions -->
		<div class="glocalsaino-aat-card">
			<h2><?php esc_html_e( 'Actions', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h2>
			<p class="description"><?php esc_html_e( 'What happens when the trigger fires. Actions run in order. Use "Insert field…" to place values from the trigger, written as {{field_name}}.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
			<div id="glocalsaino-aat-actions"></div>
			<p>
				<?php foreach ( GSAAT_Registry::action_types() as $glocalsaino_aat_type => $glocalsaino_aat_def ) : ?>
					<button type="button" class="button" data-add-action="<?php echo esc_attr( $glocalsaino_aat_type ); ?>"><span class="dashicons <?php echo esc_attr( $glocalsaino_aat_def['icon'] ); ?>" aria-hidden="true"></span>
						<?php
						/* translators: %s: action type label. */
						printf( esc_html__( 'Add: %s', 'glocalsaino-actions-automation-based-on-trigger' ), esc_html( $glocalsaino_aat_def['label'] ) );
						?>
					</button>
				<?php endforeach; ?>
			</p>
		</div>

		<!-- Limits -->
		<div class="glocalsaino-aat-card">
			<h2><?php esc_html_e( 'Runs and dates', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="glocalsaino-aat-max-runs"><?php esc_html_e( 'Number of runs', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="glocalsaino-aat-unlimited-runs" name="unlimited_runs" value="1" <?php checked( 0, $automation['max_runs'] ); ?>>
							<?php esc_html_e( 'Unlimited (never expires)', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
						</label>
						<p class="glocalsaino-aat-top-gap">
							<?php esc_html_e( 'Stop after', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
							<input type="number" min="1" step="1" class="small-text" id="glocalsaino-aat-max-runs" name="max_runs" value="<?php echo esc_attr( $automation['max_runs'] > 0 ? (string) $automation['max_runs'] : '' ); ?>">
							<?php esc_html_e( 'runs', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="glocalsaino-aat-start-date"><?php esc_html_e( 'Start date', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
					<td>
						<input type="datetime-local" id="glocalsaino-aat-start-date" name="start_date" value="<?php echo esc_attr( GSAAT_Admin::gmt_to_local_input( $automation['start_date'] ) ); ?>">
						<p class="description"><?php esc_html_e( 'Leave empty to start right away.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="glocalsaino-aat-end-date"><?php esc_html_e( 'End date', 'glocalsaino-actions-automation-based-on-trigger' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="glocalsaino-aat-never-expires" name="never_expires" value="1" <?php checked( empty( $automation['end_date'] ) ); ?>>
							<?php esc_html_e( 'Never expires', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
						</label>
						<p class="glocalsaino-aat-top-gap">
							<input type="datetime-local" id="glocalsaino-aat-end-date" name="end_date" value="<?php echo esc_attr( GSAAT_Admin::gmt_to_local_input( $automation['end_date'] ) ); ?>">
						</p>
						<p class="description">
							<?php
							/* translators: %s: timezone name. */
							printf( esc_html__( 'Dates use the site timezone (%s).', 'glocalsaino-actions-automation-based-on-trigger' ), esc_html( wp_timezone_string() ) );
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<?php
		/**
		 * Prints extra editor cards (e.g. add-on options for the whole automation). Store their
		 * values with the glocalsaino_aat_sanitize_automation_meta filter.
		 *
		 * @param array $automation Automation being edited.
		 */
		do_action( 'glocalsaino_aat_editor_cards', $automation );
		?>

		<?php submit_button( $glocalsaino_aat_is_new ? __( 'Create automation', 'glocalsaino-actions-automation-based-on-trigger' ) : __( 'Save changes', 'glocalsaino-actions-automation-based-on-trigger' ) ); ?>
	</form>
</div>
