<?php
/**
 * Contact Form 7 spam checks.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Frontend;

use SimpleHoneypotCF7\Reporting\Reporter;
use SimpleHoneypotCF7\Rules\Rules;
use SimpleHoneypotCF7\Settings;
use SimpleHoneypotCF7\Support\String_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates honeypot tokens, timing, field values, and user-defined rules.
 */
final class Spam_Checker {
	use String_Helper;

	/**
	 * Reporting service (lazy-initialized).
	 *
	 * @var Reporter|null
	 */
	private $reporter;

	/**
	 * Check spam conditions.
	 *
	 * @param bool  $spam       Existing Contact Form 7 spam status.
	 * @param mixed $submission Contact Form 7 submission.
	 * @return bool
	 */
	public function check( $spam, $submission = null ) {
		$contact_form = \WPCF7_ContactForm::get_current();
		if ( ! $contact_form || ! class_exists( '\WPCF7_ContactForm' ) ) {
			return $spam;
		}

		$honeypot_tags = $this->honeypot_tags( $contact_form );
		if ( empty( $honeypot_tags ) ) {
			return $spam;
		}

		$settings     = Settings::get_settings();
		$form_id      = method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
		$posted_data  = $this->submission_posted_data( $submission );
		$email_fields = $this->email_type_fields( $contact_form );
		$reasons      = array();

		if ( ! $spam ) {
			$tokens = Token::posted_tokens( $form_id );

			if ( count( $tokens ) < count( $honeypot_tags ) ) {
				$reasons[] = $this->reason( 'missing_token', __( 'Honeypot validation token was missing.', 'simple-honeypot-cf7' ) );
			}

			foreach ( $tokens as $token ) {
				$data = Token::validate( $token, $form_id );

				if ( empty( $data ) ) {
					$reasons[] = $this->reason( 'invalid_token', __( 'Honeypot validation token was invalid or expired.', 'simple-honeypot-cf7' ) );
					continue;
				}

				$this->check_submission_time( $reasons, $form_id, $data, $settings );
				$this->check_honeypot_value( $reasons, $data );
			}
		}

		if ( ! empty( $settings['pow_enabled'] ) && is_ssl() && ! $this->check_pow( $form_id ) ) {
			$reasons[] = $this->reason( 'pow_failed', __( 'Proof-of-Work validation failed.', 'simple-honeypot-cf7' ) );
		}

		foreach ( Rules::check( $settings, $posted_data, $this->remote_ip(), $email_fields ) as $rule_reason ) {
			$reasons[] = $rule_reason;
		}

		if ( empty( $reasons ) ) {
			return $spam;
		}

		$this->add_spam_logs( $submission, $reasons );

		if ( null === $this->reporter ) {
			$this->reporter = new Reporter();
		}

		$this->reporter->record_spam_attempt( $contact_form, $reasons );

		return true;
	}

	/**
	 * Validate submission timing.
	 *
	 * @param array $reasons  Reasons passed by reference.
	 * @param int   $form_id  Contact Form 7 form ID.
	 * @param array $data     Token data.
	 * @param array $settings Plugin settings.
	 * @return void
	 */
	private function check_submission_time( array &$reasons, $form_id, array $data, array $settings ) {
		if ( ! Settings::is_time_check_enabled( $form_id ) ) {
			return;
		}

		$created_at = isset( $data['created_at'] ) ? (int) $data['created_at'] : 0;

		if ( $created_at <= 0 ) {
			$reasons[] = $this->reason( 'missing_time', __( 'Honeypot timing data was missing.', 'simple-honeypot-cf7' ), $data );
			return;
		}

		$now      = time();
		$elapsed  = $now - $created_at;
		$min_time = Settings::get_min_submission_time( $form_id );
		$max_age  = max( 10, absint( $settings['max_age_minutes'] ) ) * MINUTE_IN_SECONDS;

		if ( $created_at > $now ) {
			$reasons[] = $this->reason( 'future_time', __( 'Honeypot timing data was in the future.', 'simple-honeypot-cf7' ), $data );
			return;
		}

		if ( $elapsed > $max_age ) {
			$reasons[] = $this->reason(
				'stale_time',
				sprintf(
					/* translators: %d: elapsed seconds. */
					__( 'Form was submitted with stale honeypot timing data after %d seconds.', 'simple-honeypot-cf7' ),
					$elapsed
				),
				$data
			);
			return;
		}

		if ( $min_time > 0 && $elapsed < $min_time ) {
			$reasons[] = $this->reason(
				'too_fast',
				sprintf(
					/* translators: 1: elapsed seconds, 2: required seconds. */
					__( 'Form was submitted too quickly: %1$d seconds elapsed, %2$d seconds required.', 'simple-honeypot-cf7' ),
					$elapsed,
					$min_time
				),
				$data
			);
		}
	}

	/**
	 * Validate the dynamic honeypot field value.
	 *
	 * @param array $reasons Reasons passed by reference.
	 * @param array $data    Token data.
	 * @return void
	 */
	private function check_honeypot_value( array &$reasons, array $data ) {
		$dynamic_name = empty( $data['dynamic_name'] ) ? '' : sanitize_key( $data['dynamic_name'] );
		$field_name   = empty( $data['field_name'] ) ? __( 'unknown field', 'simple-honeypot-cf7' ) : sanitize_key( $data['field_name'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading Contact Form 7 submission data.
		if ( '' === $dynamic_name || ! array_key_exists( $dynamic_name, $_POST ) ) {
			$reasons[] = $this->reason(
				'honeypot_missing',
				sprintf(
					/* translators: %s: honeypot field name. */
					__( 'Expected honeypot field was missing for "%s".', 'simple-honeypot-cf7' ),
					$field_name
				),
				$data
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading Contact Form 7 submission data.
		$raw = isset( $_POST[ $dynamic_name ] ) ? wp_unslash( $_POST[ $dynamic_name ] ) : '';

		if ( is_array( $raw ) ) {
			$value = '';
		} else {
			$value = sanitize_textarea_field( (string) $raw );
		}

		if ( '' !== $value ) {
			$reasons[] = $this->reason(
				'honeypot_filled',
				sprintf(
					/* translators: 1: field name, 2: submitted value. */
					__( 'Honeypot field "%1$s" was filled with "%2$s".', 'simple-honeypot-cf7' ),
					$field_name,
					$this->short_value( $value )
				),
				array_merge( $data, array( 'value' => $this->short_value( $value ) ) )
			);
		}
	}

	/**
	 * Add Contact Form 7 spam log entries.
	 *
	 * @param mixed $submission Contact Form 7 submission.
	 * @param array $reasons    Spam reasons.
	 * @return void
	 */
	private function add_spam_logs( $submission, array $reasons ) {
		if ( ! $submission || ! method_exists( $submission, 'add_spam_log' ) ) {
			return;
		}

		$reason = implode( ' | ', array_filter( wp_list_pluck( $reasons, 'message' ) ) );

		if ( '' !== $reason ) {
			$submission->add_spam_log(
				array(
					'agent'  => 'simple-honeypot-cf7',
					'reason' => $reason,
				)
			);
		}
	}

	/**
	 * Build a reason entry.
	 *
	 * @param string $type    Reason type.
	 * @param string $message Human-readable message.
	 * @param array  $data    Context data.
	 * @return array
	 */
	private function reason( $type, $message, array $data = array() ) {
		return array(
			'type'    => sanitize_key( $type ),
			'message' => wp_strip_all_tags( $message ),
			'field'   => empty( $data['field_name'] ) ? '' : sanitize_key( $data['field_name'] ),
			'value'   => empty( $data['value'] ) ? '' : $this->short_value( $data['value'] ),
		);
	}

	/**
	 * Get honeypot tags for a Contact Form 7 form.
	 *
	 * @param mixed $contact_form Contact Form 7 form.
	 * @return array
	 */
	private function honeypot_tags( $contact_form ) {
		if ( ! $contact_form || ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$contact_form->scan_form_tags(),
				static function ( $tag ) {
					return isset( $tag->type ) && 'honeypot' === $tag->type;
				}
			)
		);
	}

	/**
	 * Get email-type field names from a Contact Form 7 form.
	 *
	 * @param mixed $contact_form Contact Form 7 form.
	 * @return array
	 */
	private function email_type_fields( $contact_form ) {
		if ( ! $contact_form || ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return array();
		}

		$tags   = $contact_form->scan_form_tags();
		$fields = array();

		foreach ( $tags as $tag ) {
			if ( isset( $tag->type ) && 0 === strpos( $tag->type, 'email' ) ) {
				$fields[] = $tag->name;
			}
		}

		return $fields;
	}

	/**
	 * Get posted data from a submission object.
	 *
	 * @param mixed $submission Contact Form 7 submission.
	 * @return array
	 */
	private function submission_posted_data( $submission ) {
		if ( $submission && method_exists( $submission, 'get_posted_data' ) ) {
			$posted = $submission->get_posted_data();
			return is_array( $posted ) ? $posted : array();
		}

		return array_map(
			static function ( $value ) {
				return is_array( $value ) ? array_map( 'sanitize_text_field', wp_unslash( $value ) ) : sanitize_text_field( wp_unslash( $value ) );
			},
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading Contact Form 7 submission data.
			$_POST
		);
	}

	/**
	 * Validate the Proof-of-Work challenge from the submission.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return bool
	 */
	private function check_pow( $form_id ) {
		return Token::check_pow( $form_id );
	}

	/**
	 * Get the remote IP address.
	 *
	 * @return string
	 */
	private function remote_ip() {
		return empty( $_SERVER['REMOTE_ADDR'] ) ? '' : sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
}
