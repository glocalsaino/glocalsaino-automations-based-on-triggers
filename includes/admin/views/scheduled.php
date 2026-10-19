<?php
/**
 * Scheduled actions (pending "Wait" jobs).
 *
 * @package GlocalSainoActionsAutomation
 *
 * @var array[] $jobs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Logs', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h1>
	<hr class="wp-header-end">

	<?php GSAAT_Admin::render_notice(); ?>
	<?php GSAAT_Admin::logs_tabs( 'scheduled' ); ?>

	<p class="description">
		<?php esc_html_e( 'Actions waiting to run after a "Wait" step. WordPress runs them with its scheduler (WP-Cron), which depends on visits to the site: on low-traffic sites they may run a little later than planned.', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
	</p>

	<table class="widefat striped glocalsaino-aat-jobs">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Scheduled for', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Automation', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Started by', 'glocalsaino-actions-automation-based-on-trigger' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Manage', 'glocalsaino-actions-automation-based-on-trigger' ); ?></span></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $jobs ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'There are no scheduled actions.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $jobs as $glocalsaino_aat_job ) : ?>
				<?php $glocalsaino_aat_automation = GSAAT_Automations::get( $glocalsaino_aat_job['automation_id'] ); ?>
				<tr>
					<td><?php echo esc_html( GSAAT_Admin::format_date( $glocalsaino_aat_job['run_at'] ) ); ?></td>
					<td><?php echo esc_html( $glocalsaino_aat_automation ? $glocalsaino_aat_automation['name'] : '#' . $glocalsaino_aat_job['automation_id'] ); ?></td>
					<td>
						<ul class="glocalsaino-aat-plain-list">
							<?php foreach ( $glocalsaino_aat_job['items'] as $glocalsaino_aat_item ) : ?>
								<li><?php echo esc_html( GSAAT_Actions::describe( (array) $glocalsaino_aat_item[1] ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					</td>
					<td><a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS, array( 'view' => $glocalsaino_aat_job['log_id'] ) ) ); ?>">#<?php echo esc_html( (string) $glocalsaino_aat_job['log_id'] ); ?></a></td>
					<td class="glocalsaino-aat-nowrap">
						<a class="button button-small" href="<?php echo esc_url( GSAAT_Admin::row_action_url( 'run_job', $glocalsaino_aat_job['id'] ) ); ?>"><?php esc_html_e( 'Run now', 'glocalsaino-actions-automation-based-on-trigger' ); ?></a>
						<a class="button button-small glocalsaino-aat-confirm" data-confirm="<?php esc_attr_e( 'Cancel these scheduled actions?', 'glocalsaino-actions-automation-based-on-trigger' ); ?>" href="<?php echo esc_url( GSAAT_Admin::row_action_url( 'cancel_job', $glocalsaino_aat_job['id'] ) ); ?>"><?php esc_html_e( 'Cancel', 'glocalsaino-actions-automation-based-on-trigger' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
