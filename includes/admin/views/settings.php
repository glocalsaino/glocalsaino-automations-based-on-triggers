<?php
/**
 * Settings screen.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$glocalsaino_aat_settings = GSAAT_Plugin::settings();
$glocalsaino_aat_option   = GSAAT_Plugin::SETTINGS_OPTION;
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1><?php esc_html_e( 'Settings', 'glocalsaino-automations-based-on-triggers' ); ?></h1>

	<?php GSAAT_Admin::render_notice(); ?>
	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'glocalsaino_aat_settings_group' ); ?>

		<h2><?php esc_html_e( 'Log retention', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Older entries are deleted automatically once a day. Use 0 to keep them forever.', 'glocalsaino-automations-based-on-triggers' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="glocalsaino-aat-logs-days"><?php esc_html_e( 'Keep automation logs for', 'glocalsaino-automations-based-on-triggers' ); ?></label></th>
				<td>
					<input type="number" min="0" max="3650" step="1" class="small-text" id="glocalsaino-aat-logs-days"
						name="<?php echo esc_attr( $glocalsaino_aat_option ); ?>[logs_retention_days]"
						value="<?php echo esc_attr( (string) $glocalsaino_aat_settings['logs_retention_days'] ); ?>">
					<?php esc_html_e( 'days', 'glocalsaino-automations-based-on-triggers' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="glocalsaino-aat-webhook-days"><?php esc_html_e( 'Keep webhook logs for', 'glocalsaino-automations-based-on-triggers' ); ?></label></th>
				<td>
					<input type="number" min="0" max="3650" step="1" class="small-text" id="glocalsaino-aat-webhook-days"
						name="<?php echo esc_attr( $glocalsaino_aat_option ); ?>[webhook_logs_retention_days]"
						value="<?php echo esc_attr( (string) $glocalsaino_aat_settings['webhook_logs_retention_days'] ); ?>">
					<?php esc_html_e( 'days', 'glocalsaino-automations-based-on-triggers' ); ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Failure notifications', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Email when an automation fails', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" value="1" name="<?php echo esc_attr( $glocalsaino_aat_option ); ?>[notify_failures]" <?php checked( ! empty( $glocalsaino_aat_settings['notify_failures'] ) ); ?>>
							<?php esc_html_e( 'Send an email when all the actions of an automation fail', 'glocalsaino-automations-based-on-triggers' ); ?>
						</label><br>
						<label>
							<input type="checkbox" value="1" name="<?php echo esc_attr( $glocalsaino_aat_option ); ?>[notify_partial]" <?php checked( ! empty( $glocalsaino_aat_settings['notify_partial'] ) ); ?>>
							<?php esc_html_e( 'Also when only some of its actions fail', 'glocalsaino-automations-based-on-triggers' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="glocalsaino-aat-failure-to"><?php esc_html_e( 'Send to', 'glocalsaino-automations-based-on-triggers' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="glocalsaino-aat-failure-to"
						name="<?php echo esc_attr( $glocalsaino_aat_option ); ?>[failure_email_to]"
						value="<?php echo esc_attr( $glocalsaino_aat_settings['failure_email_to'] ); ?>"
						placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					<p class="description"><?php esc_html_e( 'One or more addresses separated by commas. Leave empty to use the site administration email address.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="glocalsaino-aat-failure-throttle"><?php esc_html_e( 'At most one email per automation every', 'glocalsaino-automations-based-on-triggers' ); ?></label></th>
				<td>
					<input type="number" min="0" max="1440" step="1" class="small-text" id="glocalsaino-aat-failure-throttle"
						name="<?php echo esc_attr( $glocalsaino_aat_option ); ?>[failure_email_throttle]"
						value="<?php echo esc_attr( (string) $glocalsaino_aat_settings['failure_email_throttle'] ); ?>">
					<?php esc_html_e( 'minutes', 'glocalsaino-automations-based-on-triggers' ); ?>
					<p class="description"><?php esc_html_e( 'Avoids a flood of emails when an external service is down. Use 0 to send one email per failure. Every failure is always recorded in the logs.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Public triggers', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="glocalsaino-aat-public-limit"><?php esc_html_e( 'Maximum runs per hour of each automation for anonymous visitors', 'glocalsaino-automations-based-on-triggers' ); ?></label></th>
				<td>
					<input type="number" min="0" max="100000" step="1" class="small-text" id="glocalsaino-aat-public-limit"
						name="<?php echo esc_attr( $glocalsaino_aat_option ); ?>[public_hourly_limit]"
						value="<?php echo esc_attr( (string) $glocalsaino_aat_settings['public_hourly_limit'] ); ?>">
					<p class="description"><?php esc_html_e( 'Links, buttons, upload forms and widgets can be used by anyone who knows their address, also without visiting your pages. Besides the limits per connection, this caps how many times one automation can run in an hour for visitors who are not logged in, all of them together, so a flood of requests cannot send unlimited emails or webhooks. Use 0 for no limit.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Webhooks', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="glocalsaino-aat-ip-source"><?php esc_html_e( 'Detect the caller IP from', 'glocalsaino-automations-based-on-triggers' ); ?></label></th>
				<td>
					<select id="glocalsaino-aat-ip-source" name="<?php echo esc_attr( $glocalsaino_aat_option ); ?>[ip_source]">
						<?php foreach ( GSAAT_Webhook_Security::ip_sources() as $glocalsaino_aat_key => $glocalsaino_aat_label ) : ?>
							<option value="<?php echo esc_attr( $glocalsaino_aat_key ); ?>" <?php selected( $glocalsaino_aat_key, $glocalsaino_aat_settings['ip_source'] ); ?>><?php echo esc_html( $glocalsaino_aat_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Used by the IP restriction of incoming webhooks and shown in the webhook logs. Only choose a header if your site really is behind that proxy: otherwise callers could fake their IP by sending the header themselves.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Widget captcha (optional)', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Embeddable widgets can show an hCaptcha challenge. Get free keys at hcaptcha.com and choose the captcha in each widget. Nothing is sent to hCaptcha unless a widget uses it.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
		<table class="form-table" role="presentation">
			<?php
			$glocalsaino_aat_captcha_fields = array(
				'hcaptcha_site_key'   => __( 'hCaptcha site key', 'glocalsaino-automations-based-on-triggers' ),
				'hcaptcha_secret_key' => __( 'hCaptcha secret key', 'glocalsaino-automations-based-on-triggers' ),
			);
			foreach ( $glocalsaino_aat_captcha_fields as $glocalsaino_aat_key => $glocalsaino_aat_label ) :
				?>
				<tr>
					<th scope="row"><label for="glocalsaino-aat-<?php echo esc_attr( $glocalsaino_aat_key ); ?>"><?php echo esc_html( $glocalsaino_aat_label ); ?></label></th>
					<td><input type="text" class="regular-text code" autocomplete="off" id="glocalsaino-aat-<?php echo esc_attr( $glocalsaino_aat_key ); ?>" name="<?php echo esc_attr( $glocalsaino_aat_option ); ?>[<?php echo esc_attr( $glocalsaino_aat_key ); ?>]" value="<?php echo esc_attr( $glocalsaino_aat_settings[ $glocalsaino_aat_key ] ); ?>"></td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h2><?php esc_html_e( 'Uninstall', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Plugin data', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td>
					<label>
						<input type="checkbox" value="1"
							name="<?php echo esc_attr( $glocalsaino_aat_option ); ?>[delete_data_on_uninstall]"
							<?php checked( ! empty( $glocalsaino_aat_settings['delete_data_on_uninstall'] ) ); ?>>
						<?php esc_html_e( 'Delete all automations, logs and settings when the plugin is deleted', 'glocalsaino-automations-based-on-triggers' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php
		/**
		 * Prints add-on settings inside the settings form. Use field names like
		 * glocalsaino_aat_settings[your_key] and sanitize them with glocalsaino_aat_sanitize_settings.
		 *
		 * @param array $settings Current settings.
		 */
		do_action( 'glocalsaino_aat_settings_fields', $glocalsaino_aat_settings );
		?>

		<?php submit_button(); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Clear logs now', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
	<div class="glocalsaino-aat-inline-forms">
		<?php
		$glocalsaino_aat_clear = array(
			'logs'         => __( 'Clear automation logs', 'glocalsaino-automations-based-on-triggers' ),
			'webhook_logs' => __( 'Clear webhook logs', 'glocalsaino-automations-based-on-triggers' ),
		);
		foreach ( $glocalsaino_aat_clear as $glocalsaino_aat_table => $glocalsaino_aat_label ) :
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="glocalsaino_aat_clear_logs">
				<input type="hidden" name="table" value="<?php echo esc_attr( $glocalsaino_aat_table ); ?>">
				<?php wp_nonce_field( 'glocalsaino_aat_clear_logs' ); ?>
				<button type="submit" class="button glocalsaino-aat-confirm" data-confirm="<?php esc_attr_e( 'Delete all entries of this log? This cannot be undone.', 'glocalsaino-automations-based-on-triggers' ); ?>"><?php echo esc_html( $glocalsaino_aat_label ); ?></button>
			</form>
		<?php endforeach; ?>
	</div>
</div>
