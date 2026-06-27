<?php
/**
 * Gravity Forms spam integration.
 *
 * @package WPSimpleAntiSpam
 */

namespace WPSimpleAntiSpam;

/**
 * Handles Gravity Forms entry spam validation.
 */
class GravityForms {

	/**
	 * Website field URLs for current entry.
	 *
	 * @var array<int, string>
	 */
	private array $urls = array();

	/**
	 * Register Gravity Forms spam hooks when available.
	 */
	public function __construct() {

		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		add_filter( 'gform_entry_is_spam', array( $this, 'entry_spam_check' ), 10, 3 );
	}

	/**
	 * Check whether a Gravity Forms entry looks like spam.
	 *
	 * @param bool  $is_spam Whether the entry is already marked as spam.
	 * @param array $form    Form object.
	 * @param array $entry   Entry data.
	 */
	public function entry_spam_check( $is_spam, $form, $entry ) {

		if ( $is_spam ) {
			return $is_spam;
		}

		$this->urls = $this->extract_urls( $form, $entry );

		$field_types_to_check = array(
			'hidden',
			'text',
			'textarea',
			'email',
			'website',
		);

		$check = new Check();

		foreach ( $form['fields'] as $field ) {
			// Skipping fields which are administrative or the wrong type.
			if ( $field->is_administrative() || ! in_array( $field->get_input_type(), $field_types_to_check, true ) ) {
				continue;
			}

			$check = $this->field_is_spam_check( $field, $entry );

			if ( $check ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a single form field value looks like spam.
	 *
	 * @param object $field Field object.
	 * @param array  $entry Entry data.
	 */
	private function field_is_spam_check( $field, $entry ) {

		$check = new Check();

		$value = $field->get_value_export( $entry );
		$type  = $field->get_input_type();

		if ( empty( $value ) ) {
			return false;
		}

		$string_value = $this->value_to_string( $value );

		if ( empty( $string_value ) ) {
			return false;
		}

		// Single Line Text, Paragraph and hidden fields.
		if ( 'text' === $type || 'textarea' === $type || 'hidden' === $type ) {
			if ( $check->text( $string_value ) ) {
				return true;
			}
		}

		if ( 'textarea' === $type && $this->textarea_contains_identical_url( $string_value ) ) {
			return true;
		}

		if ( 'website' === $type && $check->url( $string_value ) ) {
			return true;
		}

		if ( 'email' === $type && $check->email( $string_value ) ) {
			return true;
		}

		// If name consists of only digits.
		if ( 'name' === $type && $check->is_only_digits( $string_value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Normalize a field value to a string.
	 *
	 * @param string|array $value Field value.
	 */
	private function value_to_string( $value ): string {
		if ( is_array( $value ) ) {
			return trim( implode( ' ', array_filter( $value ) ) );
		}

		return trim( (string) $value );
	}

	/**
	 * Extract website field URLs from current entry.
	 *
	 * @param array $form  Form object.
	 * @param array $entry Entry data.
	 * @return array<int, string>
	 */
	private function extract_urls( array $form, array $entry ): array {
		$urls = array();

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $urls;
		}

		foreach ( $form['fields'] as $field ) {
			if ( $field->is_administrative() || 'website' !== $field->get_input_type() ) {
				continue;
			}

			$url = $this->value_to_string( $field->get_value_export( $entry ) );

			if ( '' === $url ) {
				continue;
			}

			$urls[] = $url;
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Check whether stored website URL appears in textarea content.
	 * Because most of the time when a spammer wants to link to spam, add the url in 1. the website field and also in the textarea. 
	 *
	 * @param string $text Textarea content.
	 */
	private function textarea_contains_identical_url( string $text ): bool {
		if ( empty( $this->urls ) ) {
			return false;
		}

		foreach ( $this->urls as $url ) {
			if ( Check::url_is_present_in_text( $url, $text ) ) {
				return true;
			}
		}

		return false;
	}
}
