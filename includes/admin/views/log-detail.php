<?php
/**
 * Automation log entry detail.
 *
 * @package GlocalSainoActionsAutomation
 *
 * @var array $log
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$glocalsaino_aat_triggers = GSAAT_Automations::trigger_types();
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Log entry', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h1>
	<a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to logs', 'glocalsaino-actions-automation-based-on-trigger' ); ?></a>
	<hr class="wp-header-end">

	<div class="glocalsaino-aat-card">
		<table class="glocalsaino-aat-details">
			<tr>
				<th scope="row"><?php esc_html_e( 'Date', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
				<td><?php echo esc_html( GSAAT_Admin::format_date( $log['created_at'] ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Automation', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
				<td><?php echo esc_html( $log['automation_name'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Trigger', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
				<td><?php echo esc_html( isset( $glocalsaino_aat_triggers[ $log['trigger_type'] ] ) ? $glocalsaino_aat_triggers[ $log['trigger_type'] ] : $log['trigger_type'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Event', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
				<td><?php echo esc_html( $log['event'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Result', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
				<td>
					<?php echo esc_html( GSAAT_Admin::log_status_label( $log['status'] ) ); ?>
					<?php if ( $log['message'] ) : ?>
						— <?php echo esc_html( $log['message'] ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<h2><?php esc_html_e( 'Executed actions', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h2>
	<?php if ( ! $log['results'] ) : ?>
		<p class="glocalsaino-aat-muted"><?php esc_html_e( 'No action was executed.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
	<?php else : ?>
		<?php foreach ( $log['results'] as $glocalsaino_aat_result ) : ?>
			<div class="glocalsaino-aat-card glocalsaino-aat-result glocalsaino-aat-result--<?php echo ! empty( $glocalsaino_aat_result['success'] ) ? 'ok' : 'ko'; ?>">
				<p>
					<strong><?php echo esc_html( GSAAT_Admin::result_label( $glocalsaino_aat_result ) ); ?></strong><br>
					<?php echo ! empty( $glocalsaino_aat_result['success'] ) ? esc_html__( 'Executed successfully.', 'glocalsaino-actions-automation-based-on-trigger' ) : esc_html__( 'Failed.', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
					<?php echo esc_html( $glocalsaino_aat_result['message'] ); ?>
				</p>
				<?php if ( ! empty( $glocalsaino_aat_result['details']['sent'] ) ) : ?>
					<h4><?php esc_html_e( 'Data sent', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h4>
					<?php GSAAT_Admin::pairs_table( $glocalsaino_aat_result['details']['sent'] ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $glocalsaino_aat_result['details']['subject'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Subject', 'glocalsaino-actions-automation-based-on-trigger' ); ?>:</strong> <?php echo esc_html( $glocalsaino_aat_result['details']['subject'] ); ?></p>
				<?php endif; ?>
				<?php if ( isset( $glocalsaino_aat_result['details']['response'] ) && '' !== $glocalsaino_aat_result['details']['response'] ) : ?>
					<h4><?php esc_html_e( 'Response', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h4>
					<pre class="glocalsaino-aat-pre"><?php echo esc_html( $glocalsaino_aat_result['details']['response'] ); ?></pre>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Event data', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h2>
	<?php GSAAT_Admin::pairs_table( $log['trigger_data'] ); ?>
</div>
