<?php
/**
 * Page shown inside the embeddable widget iframe.
 *
 * @package GlocalSainoAutomations
 *
 * @var array $glocalsaino_aat_widget { automation, config, available, captcha }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$glocalsaino_aat_config = $glocalsaino_aat_widget['config'];
$glocalsaino_aat_title  = '' !== $glocalsaino_aat_config['title'] ? $glocalsaino_aat_config['title'] : $glocalsaino_aat_widget['automation']['name'];
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $glocalsaino_aat_title ); ?></title>
	<?php wp_print_styles( array( 'glocalsaino-aat-widget' ) ); ?>
</head>
<body class="gsaat-w-body">
<main class="gsaat-w gsaat-w--<?php echo esc_attr( $glocalsaino_aat_config['kind'] ); ?>" id="gsaat-widget">
	<?php if ( ! $glocalsaino_aat_widget['available'] ) : ?>
		<p class="gsaat-w__notice"><?php esc_html_e( 'This form is not available right now.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
	<?php else : ?>
		<?php if ( '' !== $glocalsaino_aat_config['title'] ) : ?>
			<h2 class="gsaat-w__title"><?php echo esc_html( $glocalsaino_aat_config['title'] ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $glocalsaino_aat_config['intro'] ) : ?>
			<p class="gsaat-w__intro"><?php echo nl2br( esc_html( $glocalsaino_aat_config['intro'] ) ); ?></p>
		<?php endif; ?>

		<?php if ( 'form' === $glocalsaino_aat_config['kind'] ) : ?>
			<form class="gsaat-w__form" novalidate>
				<?php foreach ( $glocalsaino_aat_config['fields'] as $glocalsaino_aat_field ) : ?>
					<?php
					$glocalsaino_aat_id       = 'gsaat-f-' . $glocalsaino_aat_field['key'];
					$glocalsaino_aat_required = ! empty( $glocalsaino_aat_field['required'] );
					?>
					<div class="gsaat-w__field gsaat-w__field--<?php echo esc_attr( $glocalsaino_aat_field['type'] ); ?>">
						<?php if ( in_array( $glocalsaino_aat_field['type'], array( 'checkbox', 'consent' ), true ) ) : ?>
							<label class="gsaat-w__check">
								<input type="checkbox" id="<?php echo esc_attr( $glocalsaino_aat_id ); ?>" name="<?php echo esc_attr( $glocalsaino_aat_field['key'] ); ?>" value="yes" <?php echo $glocalsaino_aat_required ? 'required' : ''; ?>>
								<span>
									<?php echo esc_html( $glocalsaino_aat_field['label'] ); ?>
									<?php if ( 'consent' === $glocalsaino_aat_field['type'] && '' !== $glocalsaino_aat_field['privacy_url'] ) : ?>
										<a href="<?php echo esc_url( $glocalsaino_aat_field['privacy_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy', 'glocalsaino-automations-based-on-triggers' ); ?></a>
									<?php endif; ?>
								</span>
							</label>
						<?php else : ?>
							<label for="<?php echo esc_attr( $glocalsaino_aat_id ); ?>">
								<?php echo esc_html( $glocalsaino_aat_field['label'] ); ?>
								<?php if ( $glocalsaino_aat_required ) : ?>
									<span class="gsaat-w__req" aria-hidden="true">*</span>
								<?php endif; ?>
							</label>
							<?php if ( 'textarea' === $glocalsaino_aat_field['type'] ) : ?>
								<textarea id="<?php echo esc_attr( $glocalsaino_aat_id ); ?>" name="<?php echo esc_attr( $glocalsaino_aat_field['key'] ); ?>" rows="4" maxlength="5000" placeholder="<?php echo esc_attr( $glocalsaino_aat_field['placeholder'] ); ?>" <?php echo $glocalsaino_aat_required ? 'required' : ''; ?>></textarea>
							<?php elseif ( 'select' === $glocalsaino_aat_field['type'] ) : ?>
								<select id="<?php echo esc_attr( $glocalsaino_aat_id ); ?>" name="<?php echo esc_attr( $glocalsaino_aat_field['key'] ); ?>" <?php echo $glocalsaino_aat_required ? 'required' : ''; ?>>
									<option value=""><?php echo esc_html( '' !== $glocalsaino_aat_field['placeholder'] ? $glocalsaino_aat_field['placeholder'] : '—' ); ?></option>
									<?php foreach ( $glocalsaino_aat_field['options'] as $glocalsaino_aat_option ) : ?>
										<option value="<?php echo esc_attr( $glocalsaino_aat_option ); ?>"><?php echo esc_html( $glocalsaino_aat_option ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input type="<?php echo esc_attr( 'number' === $glocalsaino_aat_field['type'] ? 'text' : $glocalsaino_aat_field['type'] ); ?>" <?php echo 'number' === $glocalsaino_aat_field['type'] ? 'inputmode="decimal"' : ''; ?> id="<?php echo esc_attr( $glocalsaino_aat_id ); ?>" name="<?php echo esc_attr( $glocalsaino_aat_field['key'] ); ?>" maxlength="500" placeholder="<?php echo esc_attr( $glocalsaino_aat_field['placeholder'] ); ?>" <?php echo $glocalsaino_aat_required ? 'required' : ''; ?> autocomplete="<?php echo esc_attr( 'email' === $glocalsaino_aat_field['type'] ? 'email' : ( 'tel' === $glocalsaino_aat_field['type'] ? 'tel' : 'on' ) ); ?>">
							<?php endif; ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<div class="gsaat-w__hp" aria-hidden="true">
					<label><?php esc_html_e( 'Leave this field empty', 'glocalsaino-automations-based-on-triggers' ); ?> <input type="text" name="<?php echo esc_attr( GSAAT_Trigger_Widget::HONEYPOT ); ?>" tabindex="-1" autocomplete="off"></label>
				</div>
				<?php if ( $glocalsaino_aat_widget['captcha'] ) : ?>
					<div class="gsaat-w__captcha"></div>
				<?php endif; ?>
				<button type="submit" class="gsaat-w__button"><?php echo esc_html( $glocalsaino_aat_config['text'] ); ?></button>
			</form>
		<?php else : ?>
			<?php if ( $glocalsaino_aat_widget['captcha'] ) : ?>
				<div class="gsaat-w__captcha"></div>
			<?php endif; ?>
			<?php if ( 'link' === $glocalsaino_aat_config['kind'] ) : ?>
				<a class="gsaat-w__link" href="<?php echo esc_url( $glocalsaino_aat_config['destination'] ); ?>" target="_top" data-gsaat-trigger><?php echo esc_html( $glocalsaino_aat_config['text'] ); ?></a>
			<?php else : ?>
				<button type="button" class="gsaat-w__button" data-gsaat-trigger><?php echo esc_html( $glocalsaino_aat_config['text'] ); ?></button>
			<?php endif; ?>
		<?php endif; ?>

		<p class="gsaat-w__status" role="status" aria-live="polite"></p>
	<?php endif; ?>
</main>
<?php
if ( $glocalsaino_aat_widget['available'] ) {
	wp_print_scripts( array( 'glocalsaino-aat-widget' ) );
}
?>
</body>
</html>
