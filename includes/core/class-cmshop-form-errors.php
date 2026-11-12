<?php
/**
 * Several field errors at once.
 *
 * @package CodereyesMobileShop
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for staff and are escaped where they are displayed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Thrown by services when a form has problems in more than one field.
 */
final class Cmshop_Form_Errors extends InvalidArgumentException {

	/**
	 * Field => message.
	 *
	 * @var array<string,string>
	 */
	private $errors;

	/**
	 * Constructor.
	 *
	 * @param array<string,string> $errors Field => message.
	 */
	public function __construct( array $errors ) {
		parent::__construct( (string) reset( $errors ) );
		$this->errors = $errors;
	}

	/**
	 * Field => message.
	 *
	 * @return array<string,string>
	 */
	public function errors(): array {
		return $this->errors;
	}
}
