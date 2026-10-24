<?php
/**
 * Automations list screen.
 *
 * @package GlocalSainoAutomations
 *
 * @var GSAAT_Automations_Table $table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Automations', 'glocalsaino-automations-based-on-triggers' ); ?></h1>
	<a href="<?php echo esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_EDIT ) ); ?>" class="page-title-action"><?php esc_html_e( 'New Automation', 'glocalsaino-automations-based-on-triggers' ); ?></a>
	<hr class="wp-header-end">

	<?php GSAAT_Admin::render_notice(); ?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( GSAAT_Admin::PAGE_LIST ); ?>">
		<?php
		$table->search_box( __( 'Search automations', 'glocalsaino-automations-based-on-triggers' ), 'glocalsaino-aat-search' );
		$table->display();
		?>
	</form>
</div>
