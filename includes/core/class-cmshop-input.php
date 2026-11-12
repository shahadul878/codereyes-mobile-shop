<?php
/**
 * Field-by-field reader for submitted form data.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Reads, sanitises and validates fields, collecting one error per field.
 *
 * Validators create one of these per submission, read every field, then
 * return `result()`. Callers never see a half-validated array.
 */
final class Cmshop_Input {

	/**
	 * Raw submitted values (already unslashed).
	 *
	 * @var array
	 */
	private $raw;

	/**
	 * Errors keyed by field name.
	 *
	 * @var array<string,string>
	 */
	private $errors = array();

	/**
	 * Constructor.
	 *
	 * @param array $raw Submitted values.
	 */
	public function __construct( array $raw ) {
		$this->raw = $raw;
	}

	/**
	 * Raw string value of a field ('' when missing or not a string).
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	public function raw( string $field ): string {
		$value = $this->raw[ $field ] ?? '';

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Single-line text.
	 *
	 * @param string $field      Field name.
	 * @param int    $max_length Maximum characters.
	 * @param string $required   Error message when empty, or '' when optional.
	 * @return string
	 */
	public function text( string $field, int $max_length, string $required = '' ): string {
		return $this->limit( $field, sanitize_text_field( $this->raw( $field ) ), $max_length, $required );
	}

	/**
	 * Multi-line text.
	 *
	 * @param string $field      Field name.
	 * @param int    $max_length Maximum characters.
	 * @param string $required   Error message when empty, or '' when optional.
	 * @return string
	 */
	public function textarea( string $field, int $max_length, string $required = '' ): string {
		return $this->limit( $field, sanitize_textarea_field( $this->raw( $field ) ), $max_length, $required );
	}

	/**
	 * One of a fixed set of keys.
	 *
	 * @param string   $field    Field name.
	 * @param string[] $allowed  Allowed keys.
	 * @param string   $fallback Value when empty or invalid.
	 * @return string
	 */
	public function choice( string $field, array $allowed, string $fallback ): string {
		$value = sanitize_key( $this->raw( $field ) );

		if ( '' === $value ) {
			return $fallback;
		}

		if ( ! in_array( $value, $allowed, true ) ) {
			$this->error( $field, __( 'Choose one of the listed options.', 'codereyes-mobile-shop' ) );
			return $fallback;
		}

		return $value;
	}

	/**
	 * Amount of money; empty means zero.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	public function money( string $field ): string {
		$value = Cmshop_Money::parse_or_zero( $this->raw( $field ) );

		if ( null === $value ) {
			$this->error( $field, __( 'Enter an amount such as 12500 or 12,500.50.', 'codereyes-mobile-shop' ) );
			return '0.00';
		}

		return $value;
	}

	/**
	 * Optional amount of money; empty means "not set".
	 *
	 * @param string $field Field name.
	 * @return string|null
	 */
	public function optional_money( string $field ): ?string {
		return '' === trim( $this->raw( $field ) ) ? null : $this->money( $field );
	}

	/**
	 * Whole number within a range; empty gives the default.
	 *
	 * @param string   $field    Field name.
	 * @param int      $min      Minimum.
	 * @param int      $max      Maximum.
	 * @param int|null $fallback Value when empty.
	 * @return int|null
	 */
	public function integer( string $field, int $min, int $max, ?int $fallback ): ?int {
		$value = trim( Cmshop_Digits::to_ascii( $this->raw( $field ) ) );

		if ( '' === $value ) {
			return $fallback;
		}

		if ( 1 !== preg_match( '/^\d+$/', $value ) || (int) $value < $min || (int) $value > $max ) {
			/* translators: 1: minimum, 2: maximum. */
			$this->error( $field, sprintf( __( 'Enter a whole number from %1$d to %2$d.', 'codereyes-mobile-shop' ), $min, $max ) );
			return $fallback;
		}

		return (int) $value;
	}

	/**
	 * IMEI number.
	 *
	 * @param string $field    Field name.
	 * @param bool   $required Whether it must be filled in.
	 * @return string Normalised IMEI or ''.
	 */
	public function imei( string $field, bool $required ): string {
		$value = Cmshop_Imei::normalize( $this->raw( $field ) );

		if ( '' === $value ) {
			if ( $required ) {
				$this->error( $field, __( 'Enter the 15-digit IMEI (dial *#06# on the phone).', 'codereyes-mobile-shop' ) );
			}
			return '';
		}

		if ( ! Cmshop_Imei::is_valid( $value ) ) {
			$this->error( $field, __( 'This is not a valid IMEI. Check the 15 digits.', 'codereyes-mobile-shop' ) );
		}

		return $value;
	}

	/**
	 * Customer phone number.
	 *
	 * @param string $field    Field name.
	 * @param bool   $required Whether it must be filled in.
	 * @return string Normalised number or ''.
	 */
	public function phone( string $field, bool $required ): string {
		$value = Cmshop_Phone_Number::normalize( $this->raw( $field ) );

		if ( '' === ltrim( $value, '+' ) ) {
			if ( $required ) {
				$this->error( $field, __( 'Enter a phone number.', 'codereyes-mobile-shop' ) );
			}
			return '';
		}

		if ( ! Cmshop_Phone_Number::is_valid( $value ) ) {
			$this->error( $field, __( 'Enter a phone number of 6 to 15 digits.', 'codereyes-mobile-shop' ) );
		}

		return $value;
	}

	/**
	 * Email address (optional).
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	public function email( string $field ): string {
		$value = sanitize_email( $this->raw( $field ) );

		if ( '' !== trim( $this->raw( $field ) ) && ! is_email( $value ) ) {
			$this->error( $field, __( 'Enter a valid email address.', 'codereyes-mobile-shop' ) );
		}

		return $value;
	}

	/**
	 * Calendar date in Y-m-d (optional).
	 *
	 * @param string $field Field name.
	 * @return string|null
	 */
	public function date( string $field ): ?string {
		$value = trim( $this->raw( $field ) );

		if ( '' === $value ) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			$this->error( $field, __( 'Enter a date as YYYY-MM-DD.', 'codereyes-mobile-shop' ) );
			return null;
		}

		return $value;
	}

	/**
	 * Record an error for a field (the first error per field wins).
	 *
	 * @param string $field   Field name.
	 * @param string $message Message.
	 */
	public function error( string $field, string $message ): void {
		if ( ! isset( $this->errors[ $field ] ) ) {
			$this->errors[ $field ] = $message;
		}
	}

	/**
	 * Build the result.
	 *
	 * @param array $data Validated data.
	 * @return Cmshop_Validation_Result
	 */
	public function result( array $data ): Cmshop_Validation_Result {
		return new Cmshop_Validation_Result( $data, $this->errors );
	}

	/**
	 * Apply required and length rules.
	 *
	 * @param string $field      Field name.
	 * @param string $value      Sanitised value.
	 * @param int    $max_length Maximum characters.
	 * @param string $required   Error when empty, or ''.
	 * @return string
	 */
	private function limit( string $field, string $value, int $max_length, string $required ): string {
		if ( '' === $value && '' !== $required ) {
			$this->error( $field, $required );
		}

		if ( mb_strlen( $value ) > $max_length ) {
			/* translators: %d: maximum number of characters. */
			$this->error( $field, sprintf( __( 'Keep this under %d characters.', 'codereyes-mobile-shop' ), $max_length ) );
		}

		return $value;
	}
}
