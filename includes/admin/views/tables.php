<?php
/**
 * Tables list screen.
 *
 * @package GlocalSainoActionsAutomation
 *
 * @var GSAAT_Tables_List_Table $list
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Tables', 'glocalsaino-actions-automation-based-on-trigger' ); ?></h1>
	<a href="<?php echo esc_url( GSAAT_Admin_Tables::url( array( 'edit' => 0 ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'New Table', 'glocalsaino-actions-automation-based-on-trigger' ); ?></a>
	<hr class="wp-header-end">

	<?php GSAAT_Admin::render_notice(); ?>

	<p class="description"><?php esc_html_e( 'Tables collect rows from your automations (action "Add rows to a table") and show them on your site with their shortcode.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>

	<?php $list->display(); ?>
</div>
