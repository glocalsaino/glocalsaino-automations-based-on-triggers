<?php
/**
 * New / edit table screen. The columns editor is rendered by assets/js/admin-tables.js.
 *
 * @package GlocalSainoAutomations
 *
 * @var array $table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$glocalsaino_aat_settings = $table['settings'];
$glocalsaino_aat_is_new   = ! $table['id'];
?>
<div class="wrap glocalsaino-aat-wrap">
	<h1 class="wp-heading-inline"><?php echo $glocalsaino_aat_is_new ? esc_html__( 'New Table', 'glocalsaino-automations-based-on-triggers' ) : esc_html__( 'Edit Table', 'glocalsaino-automations-based-on-triggers' ); ?></h1>
	<?php if ( ! $glocalsaino_aat_is_new ) : ?>
		<a href="<?php echo esc_url( GSAAT_Admin_Tables::url( array( 'rows' => $table['id'] ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'View rows', 'glocalsaino-automations-based-on-triggers' ); ?></a>
	<?php endif; ?>
	<a href="<?php echo esc_url( GSAAT_Admin_Tables::url() ); ?>" class="page-title-action"><?php esc_html_e( 'All tables', 'glocalsaino-automations-based-on-triggers' ); ?></a>
	<hr class="wp-header-end">

	<?php GSAAT_Admin::render_notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="glocalsaino-aat-table-form">
		<input type="hidden" name="action" value="glocalsaino_aat_save_table">
		<input type="hidden" name="id" value="<?php echo esc_attr( (string) $table['id'] ); ?>">
		<input type="hidden" name="columns_json" id="glocalsaino-aat-columns-json" value="">
		<?php wp_nonce_field( 'glocalsaino_aat_save_table' ); ?>

		<div class="glocalsaino-aat-card">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="glocalsaino-aat-table-name"><?php esc_html_e( 'Table name', 'glocalsaino-automations-based-on-triggers' ); ?></label></th>
					<td><input type="text" class="regular-text" id="glocalsaino-aat-table-name" name="name" required value="<?php echo esc_attr( $table['name'] ); ?>"></td>
				</tr>
				<?php if ( ! $glocalsaino_aat_is_new ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Shortcode', 'glocalsaino-automations-based-on-triggers' ); ?></th>
						<td>
							<div class="glocalsaino-aat-copy-row">
								<code id="glocalsaino-aat-table-shortcode">[<?php echo esc_html( GSAAT_Tables::SHORTCODE ); ?> id="<?php echo esc_html( (string) $table['id'] ); ?>"]</code>
								<button type="button" class="button button-small glocalsaino-aat-copy" data-copy-target="glocalsaino-aat-table-shortcode"><?php esc_html_e( 'Copy', 'glocalsaino-automations-based-on-triggers' ); ?></button>
							</div>
							<p class="description"><?php esc_html_e( 'Paste it in any page or post to show the table.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
			</table>
		</div>

		<div class="glocalsaino-aat-card">
			<h2><?php esc_html_e( 'Columns', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
			<p class="description"><?php esc_html_e( 'The field name identifies the column in the automations; the title is what visitors see. The field name of a saved column cannot change, so the values already stored keep showing.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
			<div id="glocalsaino-aat-columns"></div>
			<p>
				<button type="button" class="button" id="glocalsaino-aat-add-column"><?php esc_html_e( 'Add column', 'glocalsaino-automations-based-on-triggers' ); ?></button>
				<label class="button" for="glocalsaino-aat-columns-file"><?php esc_html_e( 'Add columns from a CSV / Excel file…', 'glocalsaino-automations-based-on-triggers' ); ?></label>
				<input type="file" id="glocalsaino-aat-columns-file" class="screen-reader-text" accept=".csv,.xlsx,.txt">
				<span id="glocalsaino-aat-columns-status" class="glocalsaino-aat-muted" role="status"></span>
			</p>
		</div>

		<div class="glocalsaino-aat-card">
			<h2><?php esc_html_e( 'Display', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Visible to', 'glocalsaino-automations-based-on-triggers' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( GSAAT_Admin_Tables::visibility_labels() as $glocalsaino_aat_key => $glocalsaino_aat_label ) : ?>
								<label><input type="radio" name="visibility" value="<?php echo esc_attr( $glocalsaino_aat_key ); ?>" <?php checked( $glocalsaino_aat_key, $glocalsaino_aat_settings['visibility'] ); ?>> <?php echo esc_html( $glocalsaino_aat_label ); ?></label><br>
							<?php endforeach; ?>
						</fieldset>
						<p class="glocalsaino-aat-top-gap">
							<?php foreach ( wp_roles()->get_names() as $glocalsaino_aat_role => $glocalsaino_aat_role_name ) : ?>
								<label><input type="checkbox" name="roles[]" value="<?php echo esc_attr( $glocalsaino_aat_role ); ?>" <?php checked( in_array( $glocalsaino_aat_role, (array) $glocalsaino_aat_settings['roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $glocalsaino_aat_role_name ) ); ?></label>&nbsp;&nbsp;
							<?php endforeach; ?>
						</p>
						<p class="description"><?php esc_html_e( 'The roles only apply to "Users with these roles". If the table holds personal data (names, emails…), do not make it visible to everyone.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Features', 'glocalsaino-automations-based-on-triggers' ); ?></th>
					<td>
						<fieldset>
							<label><input type="checkbox" name="search" value="1" <?php checked( $glocalsaino_aat_settings['search'] ); ?>> <?php esc_html_e( 'Search box', 'glocalsaino-automations-based-on-triggers' ); ?></label><br>
							<label><input type="checkbox" name="sort" value="1" <?php checked( $glocalsaino_aat_settings['sort'] ); ?>> <?php esc_html_e( 'Sort by clicking the column titles', 'glocalsaino-automations-based-on-triggers' ); ?></label><br>
							<label><input type="checkbox" name="show_date" value="1" <?php checked( $glocalsaino_aat_settings['show_date'] ); ?>> <?php esc_html_e( 'Show the date each row was added', 'glocalsaino-automations-based-on-triggers' ); ?></label><br>
							<label><input type="checkbox" name="download" value="1" <?php checked( $glocalsaino_aat_settings['download'] ); ?>> <?php esc_html_e( 'Let visitors download the table as CSV', 'glocalsaino-automations-based-on-triggers' ); ?></label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rows', 'glocalsaino-automations-based-on-triggers' ); ?></th>
					<td>
						<p>
							<label><?php esc_html_e( 'Rows per page', 'glocalsaino-automations-based-on-triggers' ); ?> <input type="number" min="0" max="500" class="small-text" name="per_page" value="<?php echo esc_attr( (string) $glocalsaino_aat_settings['per_page'] ); ?>"></label>
							<span class="description"><?php esc_html_e( '(0 = all on one page)', 'glocalsaino-automations-based-on-triggers' ); ?></span>
						</p>
						<p>
							<label><?php esc_html_e( 'Show at most', 'glocalsaino-automations-based-on-triggers' ); ?> <input type="number" min="1" max="<?php echo esc_attr( (string) GSAAT_Tables::MAX_INSERT ); ?>" class="small-text" name="max_rows" value="<?php echo esc_attr( (string) $glocalsaino_aat_settings['max_rows'] ); ?>"> <?php esc_html_e( 'rows', 'glocalsaino-automations-based-on-triggers' ); ?></label>
						</p>
						<p>
							<label><?php esc_html_e( 'Order', 'glocalsaino-automations-based-on-triggers' ); ?>
								<select name="order">
									<option value="newest" <?php selected( 'newest', $glocalsaino_aat_settings['order'] ); ?>><?php esc_html_e( 'Newest rows first', 'glocalsaino-automations-based-on-triggers' ); ?></option>
									<option value="oldest" <?php selected( 'oldest', $glocalsaino_aat_settings['order'] ); ?>><?php esc_html_e( 'Oldest rows first (as in the file)', 'glocalsaino-automations-based-on-triggers' ); ?></option>
								</select>
							</label>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="glocalsaino-aat-table-class"><?php esc_html_e( 'CSS classes (optional)', 'glocalsaino-automations-based-on-triggers' ); ?></label></th>
					<td><input type="text" class="regular-text code" id="glocalsaino-aat-table-class" name="css_class" value="<?php echo esc_attr( $glocalsaino_aat_settings['css_class'] ); ?>"></td>
				</tr>
			</table>
		</div>

		<?php submit_button( $glocalsaino_aat_is_new ? __( 'Create table', 'glocalsaino-automations-based-on-triggers' ) : __( 'Save changes', 'glocalsaino-automations-based-on-triggers' ) ); ?>
	</form>
</div>
