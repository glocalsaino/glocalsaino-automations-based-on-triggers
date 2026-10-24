<?php
/**
 * Webhook log entry detail.
 *
 * @package GlocalSainoAutomations
 *
 * @var array $log
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$glocalsaino_aat_is_error = (int) $log['status_code'] >= 300;
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline"><?php echo 'widget' === $log['source'] ? esc_html__( 'Widget submission', 'glocalsaino-automations-based-on-triggers' ) : esc_html__( 'Webhook call', 'glocalsaino-automations-based-on-triggers' ); ?></h1>
	<a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_WEBHOOK ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to webhook logs', 'glocalsaino-automations-based-on-triggers' ); ?></a>
	<hr class="wp-header-end">

	<div class="glocalsaino-aat-card">
		<table class="glocalsaino-aat-details">
			<tr>
				<th scope="row"><?php esc_html_e( 'Date', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td><?php echo esc_html( GSAAT_Admin::format_date( $log['created_at'] ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Automation', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td><?php echo esc_html( $log['automation_name'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo 'widget' === $log['source'] ? esc_html__( 'Page with the widget', 'glocalsaino-automations-based-on-triggers' ) : esc_html__( 'Webhook URL', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td><code class="glocalsaino-aat-break"><?php echo esc_html( $log['webhook_url'] ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Method', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td><code><?php echo esc_html( $log['method'] ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Caller IP', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td><code><?php echo esc_html( '' !== $log['remote_ip'] ? $log['remote_ip'] : '—' ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Result', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td>
					<?php if ( $glocalsaino_aat_is_error ) : ?>
						<span class="glocalsaino-aat-badge glocalsaino-aat-badge--error"><?php echo esc_html( (string) $log['status_code'] ); ?></span>
					<?php else : ?>
						<span class="glocalsaino-aat-badge glocalsaino-aat-badge--success"><?php echo esc_html( (string) $log['status_code'] ); ?></span>
						<?php echo '' !== $log['error_message'] ? esc_html( $log['error_message'] ) : esc_html__( 'Data received.', 'glocalsaino-automations-based-on-triggers' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<?php if ( $glocalsaino_aat_is_error ) : ?>
		<div class="notice notice-error inline">
			<p>
				<strong>
					<?php
					/* translators: 1: HTTP status code, 2: error code. */
					printf( esc_html__( 'Error %1$s (%2$s)', 'glocalsaino-automations-based-on-triggers' ), esc_html( (string) $log['status_code'] ), esc_html( $log['error_code'] ) );
					?>
				</strong><br>
				<?php echo esc_html( $log['error_message'] ); ?>
			</p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Received data', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
	<?php GSAAT_Admin::pairs_table( $log['received_data'] ); ?>
</div>
