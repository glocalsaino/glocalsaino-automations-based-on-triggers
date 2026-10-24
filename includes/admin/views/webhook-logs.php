<?php
/**
 * Webhook logs screen.
 *
 * @package GlocalSainoAutomations
 *
 * @var GSAAT_Webhook_Logs_Table $table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Webhook Logs', 'glocalsaino-automations-based-on-triggers' ); ?></h1>
	<hr class="wp-header-end">

	<?php GSAAT_Admin::render_notice(); ?>

	<p class="description">
		<?php esc_html_e( 'Calls received by the webhook URLs and the embeddable widgets of your automations, including the rejected ones. Click a row to see the details and the received data.', 'glocalsaino-automations-based-on-triggers' ); ?>
	</p>

	<?php $table->views(); ?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( GSAAT_Admin::PAGE_WEBHOOK ); ?>">
		<?php $table->display(); ?>
	</form>
</div>
