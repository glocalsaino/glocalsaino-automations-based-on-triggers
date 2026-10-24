<?php
/**
 * Rows of one table.
 *
 * @package GlocalSainoActionsAutomation
 *
 * @var array                       $table
 * @var GSAAT_Table_Rows_List_Table $list
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline">
		<?php
		/* translators: %s: table name. */
		printf( esc_html__( 'Rows of "%s"', 'glocalsaino-actions-automation-based-on-trigger' ), esc_html( $table['name'] ) );
		?>
	</h1>
	<a href="<?php echo esc_url( GSAAT_Admin_Tables::url( array( 'edit' => $table['id'] ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Edit table', 'glocalsaino-actions-automation-based-on-trigger' ); ?></a>
	<a href="<?php echo esc_url( GSAAT_Admin_Tables::action_url( 'export', $table['id'] ) ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'glocalsaino-actions-automation-based-on-trigger' ); ?></a>
	<a href="<?php echo esc_url( GSAAT_Admin_Tables::action_url( 'empty', $table['id'] ) ); ?>" class="page-title-action glocalsaino-aat-confirm" data-confirm="<?php esc_attr_e( 'Delete all the rows of this table? This cannot be undone.', 'glocalsaino-actions-automation-based-on-trigger' ); ?>"><?php esc_html_e( 'Delete all rows', 'glocalsaino-actions-automation-based-on-trigger' ); ?></a>
	<a href="<?php echo esc_url( GSAAT_Admin_Tables::url() ); ?>" class="page-title-action"><?php esc_html_e( 'All tables', 'glocalsaino-actions-automation-based-on-trigger' ); ?></a>
	<hr class="wp-header-end">

	<?php GSAAT_Admin::render_notice(); ?>

	<?php if ( count( $table['columns'] ) > GSAAT_Table_Rows_List_Table::SHOWN_COLUMNS ) : ?>
		<p class="description"><?php esc_html_e( 'Only the first columns are shown here; the export includes all of them.', 'glocalsaino-actions-automation-based-on-trigger' ); ?></p>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( GSAAT_Admin_Tables::PAGE ); ?>">
		<input type="hidden" name="rows" value="<?php echo esc_attr( (string) $table['id'] ); ?>">
		<?php $list->display(); ?>
	</form>
</div>
