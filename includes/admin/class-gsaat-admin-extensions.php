<?php
/**
 * "Extensions" submenu: the paid add-on and the other GlocalSaino plugins it connects with.
 *
 * Always the last submenu (PHP_INT_MAX). A page of its own, never a redirect.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Admin_Extensions {

	const PAGE = 'glocalsaino-aat-extensions';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), PHP_INT_MAX );
	}

	public static function menu() {
		add_submenu_page(
			GSAAT_Admin::PAGE_LIST,
			__( 'Extensions', 'glocalsaino-automations-based-on-triggers' ),
			__( 'Extensions', 'glocalsaino-automations-based-on-triggers' ),
			GSAAT_Admin::CAP,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * One card: icon, name, a sentence and its status or a link. Same compact
	 * format as the Extensions pages of the other GlocalSaino plugins.
	 *
	 * @param string $name        Name.
	 * @param string $description Description.
	 * @param bool   $active      Whether it is active on this site.
	 * @param string $url         Page of the plugin.
	 * @param string $icon        Icon file in assets/img/.
	 * @param string $active_note Where to find it once active.
	 */
	private static function card( $name, $description, $active, $url, $icon, $active_note = '' ) {
		?>
		<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;max-width:640px;margin-top:16px;display:flex;gap:16px;align-items:flex-start;">
			<img src="<?php echo esc_url( GSAAT_URL . 'assets/img/' . $icon ); ?>" alt="<?php echo esc_attr( $name ); ?>" width="48" height="48" style="width:48px;height:48px;border-radius:8px;flex-shrink:0;">
			<div>
				<h2 style="margin-top:0;"><?php echo esc_html( $name ); ?></h2>
				<p><?php echo esc_html( $description ); ?></p>
				<?php if ( $active ) : ?>
					<p><strong><?php esc_html_e( 'Active', 'glocalsaino-automations-based-on-triggers' ); ?></strong><?php echo $active_note ? ' — ' . esc_html( $active_note ) : ''; ?></p>
				<?php else : ?>
					<p><a class="button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'glocalsaino-automations-based-on-triggers' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function render() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Extensions', 'glocalsaino-automations-based-on-triggers' ); ?></h1>
			<?php
			self::card(
				__( 'GlocalSaino Automations Bridge', 'glocalsaino-automations-based-on-triggers' ),
				__( 'Triggers and actions for the other GlocalSaino plugins: a table from every map layer uploaded, points on a map from a form, alerts when an external layer gets new items, magic links to protected pages, booking and SES Hospedajes events, reminders in the booking chat room, and bids and auction endings sent to any webhook.', 'glocalsaino-automations-based-on-triggers' ),
				defined( 'GSAATB_VERSION' ),
				'https://glocalsaino.com/automationsbridge/',
				'icon-automations-bridge.png',
				__( 'see Automations → Bridge.', 'glocalsaino-automations-based-on-triggers' )
			);
			?>

			<h2 style="margin-top:32px;"><?php esc_html_e( 'Other GlocalSaino plugins', 'glocalsaino-automations-based-on-triggers' ); ?></h2>
			<p style="max-width:760px;font-size:14px;"><?php esc_html_e( 'Free plugins that Automations Bridge can connect with your automations.', 'glocalsaino-automations-based-on-triggers' ); ?></p>
			<?php
			self::card(
				__( 'GlocalSaino Layer Map Viewer', 'glocalsaino-automations-based-on-triggers' ),
				__( 'Interactive maps from KML files, with colored layers, filters and transparency.', 'glocalsaino-automations-based-on-triggers' ),
				defined( 'KML_MAP_VERSION' ),
				'https://glocalsaino.com/layermapviewer/',
				'icon-glocalsaino-layer-map-viewer.png'
			);
			self::card(
				__( 'GlocalSaino Booking Calendars displayed by shortcodes', 'glocalsaino-automations-based-on-triggers' ),
				__( 'Accommodation booking calendars with prices, iCal sync and scheduled guest emails.', 'glocalsaino-automations-based-on-triggers' ),
				class_exists( 'GSBCDS_Bookings' ),
				'https://glocalsaino.com/bookingcalendarsdisplayedbyshortcodes/',
				'icon-booking-calendars-displayed-by-shortcodes.png'
			);
			self::card(
				__( 'GlocalSaino Auctions Displayed by Shortcodes', 'glocalsaino-automations-based-on-triggers' ),
				__( 'Direct and reverse auctions in any page, with a countdown and an automatic winner.', 'glocalsaino-automations-based-on-triggers' ),
				class_exists( 'GSADS_Settings' ),
				'https://glocalsaino.com/auctionsdisplayedbyshortcodes/',
				'icon-auctions-displayed-by-shortcodes.png'
			);
			self::card(
				__( 'GlocalSaino WebPagesPassworded', 'glocalsaino-automations-based-on-triggers' ),
				__( 'One access page for groups of password-protected pages.', 'glocalsaino-automations-based-on-triggers' ),
				class_exists( 'GlocalSaino_Wppw_Core' ),
				'https://glocalsaino.com/webpagespassworded/',
				'icon-webpagespassworded.png'
			);
			?>
		</div>
		<?php
	}
}
