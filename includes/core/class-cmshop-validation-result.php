<?php
/**
 * Immutable outcome of validating a form.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Validated data plus any errors keyed by field.
 */
final class Cmshop_Validation_Result {

	/**
	 * Validated data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Errors keyed by field.
	 *
	 * @var array<string,string>
	 */
	private $errors;

	/**
	 * Constructor.
	 *
	 * @param array                $data   Validated data.
	 * @param array<string,string> $errors Errors.
	 */
	public function __construct( array $data, array $errors ) {
		$this->data   = $data;
		$this->errors = $errors;
	}

	/**
	 * Validated data.
	 *
	 * @return array
	 */
	public function data(): array {
		return $this->data;
	}

	/**
	 * Errors keyed by field.
	 *
	 * @return array<string,string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Whether there were no errors.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return array() === $this->errors;
	}

	/**
	 * Copy with one more error.
	 *
	 * @param string $field   Field.
	 * @param string $message Message.
	 * @return self
	 */
	public function with_error( string $field, string $message ): self {
		return new self( $this->data, array_merge( array( $field => $message ), $this->errors ) );
	}
}
