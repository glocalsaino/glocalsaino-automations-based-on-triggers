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
