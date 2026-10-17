<?php
/**
 * Email notification to the site administrator when an automation fails.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Notifier {

	public static function init() {
		add_action( 'glocalsaino_aat_automation_failed', array( __CLASS__, 'notify' ), 10, 4 );
	}

	/**
	 * Send the failure email, unless disabled or throttled.
	 *
	 * @param array  $automation Automation.
	 * @param string $status     error|partial.
	 * @param array  $results    Action results.
	 * @param int    $log_id     Log entry ID.
	 */
	public static function notify( $automation, $status, $results, $log_id ) {
		$settings = GSAAT_Plugin::settings();
		if ( empty( $settings['notify_failures'] ) || ( 'partial' === $status && empty( $settings['notify_partial'] ) ) ) {
			return;
		}

		$throttle = (int) $settings['failure_email_throttle'];
		$lock     = 'glocalsaino_aat_fail_mail_' . (int) $automation['id'];
		if ( $throttle > 0 ) {
			if ( get_transient( $lock ) ) {
				return;
			}
			set_transient( $lock, 1, $throttle * MINUTE_IN_SECONDS );
		}

		$recipients = self::recipients( $settings['failure_email_to'] );
		if ( ! $recipients ) {
			return;
		}

		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$log  = GSAAT_Logs::get( $log_id );

		/* translators: 1: site name, 2: automation name. */
		$subject = sprintf( __( '[%1$s] Automation failed: %2$s', 'glocalsaino-actions-automation-based-on-trigger' ), $site, $automation['name'] );

		$lines = array(
			'partial' === $status
				/* translators: 1: automation name, 2: date. */
				? sprintf( __( 'Some actions of the automation "%1$s" failed on %2$s.', 'glocalsaino-actions-automation-based-on-trigger' ), $automation['name'], wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
				/* translators: 1: automation name, 2: date. */
				: sprintf( __( 'The automation "%1$s" failed on %2$s.', 'glocalsaino-actions-automation-based-on-trigger' ), $automation['name'], wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			'',
		);
		if ( $log ) {
			/* translators: %s: event description. */
			$lines[] = sprintf( __( 'Event: %s', 'glocalsaino-actions-automation-based-on-trigger' ), $log['event'] );
			$lines[] = '';
		}

		$lines[] = __( 'Failed actions:', 'glocalsaino-actions-automation-based-on-trigger' );
		foreach ( $results as $result ) {
			if ( empty( $result['success'] ) ) {
				$lines[] = '- ' . GSAAT_Actions::result_label( $result ) . ': ' . $result['message'];
			}
		}
		if ( ! $results ) {
			$lines[] = '- ' . __( 'This automation has no actions configured.', 'glocalsaino-actions-automation-based-on-trigger' );
		}

		$lines[] = '';
		$lines[] = __( 'See the details and retry the failed actions here:', 'glocalsaino-actions-automation-based-on-trigger' );
		$lines[] = add_query_arg(
			array(
				'page' => 'glocalsaino-aat-logs',
				'view' => (int) $log_id,
			),
			admin_url( 'admin.php' )
		);
		$lines[] = '';
		$lines[] = '—';
		$lines[] = $throttle > 0
			/* translators: %d: number of minutes. */
			? sprintf( __( 'Further failures of this automation in the next %d minutes will not be emailed; they are still recorded in the logs.', 'glocalsaino-actions-automation-based-on-trigger' ), $throttle )
			: __( 'You receive one email per failure.', 'glocalsaino-actions-automation-based-on-trigger' );
		$lines[] = __( 'You can change these notifications in Automations → Settings.', 'glocalsaino-actions-automation-based-on-trigger' );

		/**
		 * Filters the failure notification before it is sent.
		 *
		 * @param array $email      { to, subject, body }.
		 * @param array $automation Automation.
		 * @param array $results    Action results.
		 * @param int   $log_id     Log entry ID.
		 */
		$email = apply_filters(
			'glocalsaino_aat_failure_email',
			array(
				'to'      => $recipients,
				'subject' => $subject,
				'body'    => implode( "\n", $lines ),
			),
			$automation,
			$results,
			$log_id
		);

		if ( $email ) {
			wp_mail( $email['to'], $email['subject'], $email['body'], array( 'Content-Type: text/plain; charset=UTF-8' ) );
		}
	}

	/**
	 * Valid addresses from the setting, or the site admin email.
	 *
	 * @param string $setting Comma-separated addresses.
	 * @return string[]
	 */
	public static function recipients( $setting ) {
		$valid = array();
		foreach ( preg_split( '/[,;\s]+/', (string) $setting ) as $address ) {
			$address = sanitize_email( $address );
			if ( is_email( $address ) ) {
				$valid[] = $address;
			}
		}
		if ( ! $valid && is_email( get_option( 'admin_email' ) ) ) {
			$valid[] = get_option( 'admin_email' );
		}
		return array_values( array_unique( $valid ) );
	}
}
