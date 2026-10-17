<?php
/**
 * Automation logs screen.
 *
 * @package GlocalSainoActionsAutomation
 *
 * @var GSAAT_Logs_Table $table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
$glocalsaino_aat_filter_id = isset( $_GET['automation_id'] ) ? absint( wp_unslash( $_GET['automation_id'] ) ) : 0;
$glocalsaino_aat_filtered  = $glocalsaino_aat_filter_id ? GSAAT_Automations::get( $glocalsaino_aat_filter_id ) : null;
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Logs', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h1>
	<hr class="wp-header-end">

	<?php GSAAT_Admin::render_notice(); ?>

	<p class="description">
		<?php esc_html_e( 'Every time a trigger fires an automation, the event, the executed actions and their result are recorded here.', 'glocalsaino-actions-automation-based-on-trigger' ); ?>
	</p>

	<?php if ( $glocalsaino_aat_filtered ) : ?>
		<p>
			<?php
			/* translators: %s: automation name. */
			printf( esc_html__( 'Showing only the logs of "%s".', 'glocalsaino-actions-automation-based-on-trigger' ), esc_html( $glocalsaino_aat_filtered['name'] ) );
			?>
			<a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS ) ); ?>"><?php esc_html_e( 'Show all', 'glocalsaino-actions-automation-based-on-trigger' ); ?></a>
		</p>
	<?php endif; ?>

	<?php $table->views(); ?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( GSAAT_Admin::PAGE_LOGS ); ?>">
		<?php if ( $glocalsaino_aat_filter_id ) : ?>
			<input type="hidden" name="automation_id" value="<?php echo esc_attr( (string) $glocalsaino_aat_filter_id ); ?>">
		<?php endif; ?>
		<?php $table->display(); ?>
	</form>
</div>
