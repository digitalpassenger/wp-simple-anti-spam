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
	 * Register Gravity Forms spam hooks when available.
	 */
	public function __construct() {

		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		add_filter( 'gform_entry_is_spam', array( $this, 'entry_spam_check' ), 10, 2 );
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

		// Single Line Text and Paragraph fields.
		if ( 'text' === $type || 'textarea' === $type || 'hidden' === $type ) {
			if ( $check->text( $string_value ) ) {
				return true;
			}
		}

		if ( 'website' === $type && $check->url( $string_value ) ) {
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
}
