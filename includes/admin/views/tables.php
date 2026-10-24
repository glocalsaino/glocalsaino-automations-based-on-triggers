<?php
/**
 * Tables list screen.
 *
 * @package GlocalSainoAutomations
 *
 * @var GSAAT_Tables_List_Table $list
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Tables', 'glocalsaino-automations-based-on-triggers' ); ?></h1>
	<a href="<?php echo esc_url( GSAAT_Admin_Tables::url( array( 'edit' => 0 ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'New Table', 'glocalsaino-automations-based-on-triggers' ); ?></a>
	<hr class="wp-header-end">

	<?php GSAAT_Admin::render_notice(); ?>

	<p class="description"><?php esc_html_e( 'Tables collect rows from your automations (action "Add rows to a table") and show them on your site with their shortcode.', 'glocalsaino-automations-based-on-triggers' ); ?></p>

	<?php $list->display(); ?>
</div>
