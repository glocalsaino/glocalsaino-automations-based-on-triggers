<?php
/**
 * Trigger: Contact Form 7 submission.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Trigger_CF7 {

	public static function init() {
		add_action( 'wpcf7_submit', array( __CLASS__, 'on_submit' ), 10, 2 );
		add_filter( 'wpcf7_form_hidden_fields', array( __CLASS__, 'add_rest_nonce' ) );
	}

	/**
	 * Runs after Contact Form 7 processes a submission.
	 *
	 * @param WPCF7_ContactForm $contact_form Submitted form.
	 * @param array             $result       Submission result.
	 */
	public static function on_submit( $contact_form, $result ) {
		// Only submissions that passed validation and spam checks.
		$status = isset( $result['status'] ) ? $result['status'] : '';
		if ( ! in_array( $status, array( 'mail_sent', 'mail_failed' ), true ) || ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$form_id = (int) $contact_form->id();
		$posted  = array();
		foreach ( (array) $submission->get_posted_data() as $name => $value ) {
			if ( 0 !== strpos( (string) $name, '_' ) ) {
				$posted[ $name ] = $value;
			}
		}
		/* translators: 1: form title, 2: form ID. */
		$event = sprintf( __( 'Contact Form 7 form "%1$s" (#%2$d) submitted', 'glocalsaino-automations-based-on-triggers' ), $contact_form->title(), $form_id );

		GSAAT_Engine::fire(
			'cf7',
			$posted,
			$event,
			array(
				'extra_tokens' => array(
					'_form_id'    => (string) $form_id,
					'_form_title' => $contact_form->title(),
				),
				'context'      => array( 'user_id' => get_current_user_id() ),
				'matches'      => static function ( $automation ) use ( $posted, $form_id ) {
					$config = self::config( $automation['trigger_config'] );
					if ( (int) $config['form_id'] !== $form_id ) {
						return false;
					}
					return 'field' !== $config['mode'] || self::field_matches( $posted, $config['field'], $config['value'] );
				},
			)
		);
	}

	/**
	 * Trigger settings merged with defaults.
	 *
	 * @param array $config Saved trigger config.
	 * @return array
	 */
	public static function config( array $config ) {
		return wp_parse_args(
			$config,
			array(
				'form_id' => 0,
				'mode'    => 'form',
				'field'   => '',
				'value'   => '',
			)
		);
	}

	/**
	 * Sanitize the trigger settings posted by the automation editor.
	 *
	 * @param array $post    Unslashed POST data.
	 * @param array $notices Notice codes, appended to.
	 * @return array
	 */
	public static function sanitize_config( array $post, array &$notices ) {
		$config = array(
			'form_id' => isset( $post['cf7_form_id'] ) ? absint( $post['cf7_form_id'] ) : 0,
			'mode'    => ( isset( $post['cf7_mode'] ) && 'field' === $post['cf7_mode'] ) ? 'field' : 'form',
			'field'   => isset( $post['cf7_field'] ) ? GSAAT_Engine::sanitize_field_name( (string) $post['cf7_field'] ) : '',
			'value'   => isset( $post['cf7_value'] ) ? sanitize_text_field( (string) $post['cf7_value'] ) : '',
		);
		if ( ! $config['form_id'] || ( 'field' === $config['mode'] && '' === $config['field'] ) ) {
			$config['_incomplete'] = true;
		}
		return $config;
	}

	/**
	 * Description for the automations list.
	 *
	 * @param array $automation Automation.
	 * @return string Plain text.
	 */
	public static function describe( array $automation ) {
		$config = self::config( $automation['trigger_config'] );
		$form_id = (int) $config['form_id'];
		$title   = $form_id ? get_the_title( $form_id ) : '';
		/* translators: %s: form title or ID. */
		$text = sprintf( __( 'Contact Form 7: "%s" submitted', 'glocalsaino-automations-based-on-triggers' ), '' !== $title ? $title : '#' . $form_id );
		if ( 'field' === $config['mode'] ) {
			$text .= '' !== $config['value']
				/* translators: 1: field name, 2: expected value. */
				? ' — ' . sprintf( __( 'field "%1$s" = "%2$s"', 'glocalsaino-automations-based-on-triggers' ), $config['field'], $config['value'] )
				/* translators: %s: field name. */
				: ' — ' . sprintf( __( 'field "%s" filled in', 'glocalsaino-automations-based-on-triggers' ), $config['field'] );
		}
		return $text;
	}

	/**
	 * Contact Form 7 submits forms through the REST API without a nonce, so WordPress
	 * treats every visitor as logged out. For forms whose automations check the
	 * logged-in visitor, add the REST nonce so the session is recognised.
	 *
	 * @param array $fields Hidden fields.
	 * @return array
	 */
	public static function add_rest_nonce( $fields ) {
		if ( ! is_user_logged_in() || ! class_exists( 'WPCF7_ContactForm' ) ) {
			return $fields;
		}
		$form = WPCF7_ContactForm::get_current();
		if ( $form && in_array( (int) $form->id(), self::forms_checking_visitor(), true ) ) {
			$fields['_wpnonce'] = wp_create_nonce( 'wp_rest' );
		}
		return $fields;
	}

	/**
	 * IDs of the forms used by active automations with a condition on the logged-in visitor.
	 *
	 * @return int[]
	 */
	private static function forms_checking_visitor() {
		static $ids = null;
		if ( null !== $ids ) {
			return $ids;
		}
		$ids = array();
		foreach ( GSAAT_Automations::get_active_by_trigger( 'cf7' ) as $automation ) {
			foreach ( $automation['conditions']['rules'] as $rule ) {
				if ( isset( $rule['type'] ) && 'user' === $rule['type'] && ( empty( $rule['user_field'] ) || in_array( $rule['operator'], array( 'logged_in', 'not_logged_in' ), true ) ) ) {
					$ids[] = isset( $automation['trigger_config']['form_id'] ) ? (int) $automation['trigger_config']['form_id'] : 0;
					break;
				}
			}
		}
		return $ids;
	}

	/**
	 * Whether a submitted field has the configured value (any non-empty value when none is configured).
	 *
	 * @param array  $posted   Posted data.
	 * @param string $field    Field name.
	 * @param string $expected Expected value.
	 * @return bool
	 */
	public static function field_matches( array $posted, $field, $expected ) {
		if ( '' === $field || ! isset( $posted[ $field ] ) ) {
			return false;
		}
		$values = array_filter( array_map( 'strval', (array) $posted[ $field ] ), 'strlen' );
		if ( '' === trim( $expected ) ) {
			return (bool) $values;
		}
		foreach ( $values as $value ) {
			if ( GSAAT_Engine::compare( $value, 'equals', $expected ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Contact Form 7 forms with their fields, for the automation editor.
	 *
	 * @return array[] { id, title, fields: { name, type, options } }
	 */
	public static function forms() {
		if ( ! GSAAT_Plugin::cf7_active() ) {
			return array();
		}

		$forms = array();
		foreach ( WPCF7_ContactForm::find( array( 'posts_per_page' => -1 ) ) as $form ) {
			$fields = array();
			foreach ( $form->scan_form_tags() as $tag ) {
				if ( '' === $tag->name || isset( $fields[ $tag->name ] ) ) {
					continue;
				}
				$fields[ $tag->name ] = array(
					'name'    => $tag->name,
					'type'    => $tag->basetype,
					'options' => array_values( array_map( 'strval', (array) $tag->values ) ),
				);
			}
			$forms[] = array(
				'id'     => (int) $form->id(),
				'title'  => $form->title(),
				'fields' => array_values( $fields ),
			);
		}
		return $forms;
	}
}
