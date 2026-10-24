<?php
/**
 * Automation log entry detail.
 *
 * @package GlocalSainoAutomations
 *
 * @var array $log
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$glocalsaino_aat_triggers  = GSAAT_Automations::trigger_types();
$glocalsaino_aat_retries   = GSAAT_Logs::retries_for( array( $log['id'] ) );
$glocalsaino_aat_retried   = isset( $glocalsaino_aat_retries[ $log['id'] ] ) ? $glocalsaino_aat_retries[ $log['id'] ] : null;
$glocalsaino_aat_jobs      = GSAAT_Scheduler::for_log( $log['id'] );
$glocalsaino_aat_can_retry = in_array( $log['status'], array( 'error', 'partial' ), true ) && GSAAT_Automations::get( $log['automation_id'] );
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Log entry', 'glocalsaino-automations-based-on-triggers' ); ?></h1>
	<a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to logs', 'glocalsaino-automations-based-on-triggers' ); ?></a>
	<?php if ( $glocalsaino_aat_can_retry ) : ?>
		<a href="<?php echo esc_url( GSAAT_Admin::row_action_url( 'retry_log', $log['id'] ) ); ?>" class="page-title-action"><?php esc_html_e( 'Retry failed actions', 'glocalsaino-automations-based-on-triggers' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php GSAAT_Admin::render_notice(); ?>

	<?php if ( $glocalsaino_aat_can_retry ) : ?>
		<p class="description"><?php esc_html_e( 'Retrying runs again only the actions that failed, with the data of this event and the current settings of those actions. It does not count as a new run.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
	<?php endif; ?>

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
				<th scope="row"><?php esc_html_e( 'Trigger', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td><?php echo esc_html( isset( $glocalsaino_aat_triggers[ $log['trigger_type'] ] ) ? $glocalsaino_aat_triggers[ $log['trigger_type'] ] : $log['trigger_type'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Event', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td><?php echo esc_html( $log['event'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Result', 'glocalsaino-automations-based-on-triggers' ); ?></th>
				<td>
					<?php echo esc_html( GSAAT_Admin::log_status_label( $log['status'] ) ); ?>
					<?php if ( $log['message'] ) : ?>
						— <?php echo esc_html( $log['message'] ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( ! empty( $log['parent_id'] ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Scheduled by', 'glocalsaino-automations-based-on-triggers' ); ?></th>
					<td><a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS, array( 'view' => $log['parent_id'] ) ) ); ?>">#<?php echo esc_html( (string) $log['parent_id'] ); ?></a></td>
				</tr>
			<?php endif; ?>
			<?php foreach ( $glocalsaino_aat_jobs as $glocalsaino_aat_job ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Scheduled actions', 'glocalsaino-automations-based-on-triggers' ); ?></th>
					<td>
						<?php if ( 'pending' === $glocalsaino_aat_job['status'] ) : ?>
							<?php
							/* translators: %s: date. */
							echo esc_html( sprintf( __( 'Pending, scheduled for %s.', 'glocalsaino-automations-based-on-triggers' ), GSAAT_Admin::format_date( $glocalsaino_aat_job['run_at'] ) ) );
							?>
							<a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS, array( 'tab' => 'scheduled' ) ) ); ?>"><?php esc_html_e( 'Manage', 'glocalsaino-automations-based-on-triggers' ); ?></a>
						<?php elseif ( $glocalsaino_aat_job['result_log_id'] ) : ?>
							<?php esc_html_e( 'Ran in', 'glocalsaino-automations-based-on-triggers' ); ?>
							<a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS, array( 'view' => $glocalsaino_aat_job['result_log_id'] ) ) ); ?>">#<?php echo esc_html( (string) $glocalsaino_aat_job['result_log_id'] ); ?></a>
						<?php else : ?>
							<?php esc_html_e( 'Cancelled.', 'glocalsaino-automations-based-on-triggers' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! empty( $log['retry_of'] ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Retry of', 'glocalsaino-automations-based-on-triggers' ); ?></th>
					<td><a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS, array( 'view' => $log['retry_of'] ) ) ); ?>">#<?php echo esc_html( (string) $log['retry_of'] ); ?></a></td>
				</tr>
			<?php endif; ?>
			<?php if ( $glocalsaino_aat_retried ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Retried in', 'glocalsaino-automations-based-on-triggers' ); ?></th>
					<td><a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS, array( 'view' => $glocalsaino_aat_retried['id'] ) ) ); ?>">#<?php echo esc_html( (string) $glocalsaino_aat_retried['id'] ); ?></a> — <?php echo esc_html( GSAAT_Admin::log_status_label( $glocalsaino_aat_retried['status'] ) ); ?></td>
				</tr>
			<?php endif; ?>
		</table>
	</div>

	<h2><?php esc_html_e( 'Executed actions', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
	<?php if ( ! $log['results'] ) : ?>
		<p class="glocalsaino-aat-muted"><?php esc_html_e( 'No action was executed.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
	<?php else : ?>
		<?php foreach ( $log['results'] as $glocalsaino_aat_result ) : ?>
			<div class="glocalsaino-aat-card glocalsaino-aat-result glocalsaino-aat-result--<?php echo ! empty( $glocalsaino_aat_result['success'] ) ? 'ok' : 'ko'; ?>">
				<p>
					<strong><?php echo esc_html( GSAAT_Admin::result_label( $glocalsaino_aat_result ) ); ?></strong><br>
					<?php echo ! empty( $glocalsaino_aat_result['success'] ) ? esc_html__( 'Executed successfully.', 'glocalsaino-automations-based-on-triggers' ) : esc_html__( 'Failed.', 'glocalsaino-automations-based-on-triggers' ); ?>
					<?php echo esc_html( $glocalsaino_aat_result['message'] ); ?>
				</p>
				<?php if ( ! empty( $glocalsaino_aat_result['details']['sent'] ) ) : ?>
					<h4><?php esc_html_e( 'Data sent', 'glocalsaino-automations-based-on-triggers' ); ?></h4>
					<?php GSAAT_Admin::pairs_table( $glocalsaino_aat_result['details']['sent'] ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $glocalsaino_aat_result['details']['subject'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Subject', 'glocalsaino-automations-based-on-triggers' ); ?>:</strong> <?php echo esc_html( $glocalsaino_aat_result['details']['subject'] ); ?></p>
				<?php endif; ?>
				<?php if ( isset( $glocalsaino_aat_result['details']['response'] ) && '' !== $glocalsaino_aat_result['details']['response'] ) : ?>
					<h4><?php esc_html_e( 'Response', 'glocalsaino-automations-based-on-triggers' ); ?></h4>
					<pre class="glocalsaino-aat-pre"><?php echo esc_html( $glocalsaino_aat_result['details']['response'] ); ?></pre>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Event data', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
	<?php GSAAT_Admin::pairs_table( $log['trigger_data'] ); ?>
</div>
