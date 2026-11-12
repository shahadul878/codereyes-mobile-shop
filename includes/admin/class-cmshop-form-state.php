<?php
/**
 * Keeps submitted values and errors across the post/redirect/get round trip.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * One short-lived transient per user and form.
 */
final class Cmshop_Form_State {

	private const TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Store values and errors for the next page load.
	 *
	 * @param string $form   Form name.
	 * @param array  $values Submitted values (sanitised).
	 * @param array  $errors Errors keyed by field.
	 */
	public static function save( string $form, array $values, array $errors ): void {
		set_transient(
			self::key( $form ),
			array(
				'values' => $values,
				'errors' => $errors,
			),
			self::TTL
		);
	}

	/**
	 * Read and clear the stored state.
	 *
	 * @param string $form Form name.
	 * @return array{values: array, errors: array}|null
	 */
	public static function take( string $form ): ?array {
		$key   = self::key( $form );
		$state = get_transient( $key );

		if ( ! is_array( $state ) ) {
			return null;
		}

		delete_transient( $key );

		return array(
			'values' => (array) ( $state['values'] ?? array() ),
			'errors' => (array) ( $state['errors'] ?? array() ),
		);
	}

	/**
	 * Transient key.
	 *
	 * @param string $form Form name.
	 * @return string
	 */
	private static function key( string $form ): string {
		return 'cmshop_form_' . get_current_user_id() . '_' . sanitize_key( $form );
	}
}
