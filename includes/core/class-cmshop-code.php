<?php
/**
 * Invoice numbers and repair ticket codes.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Human-friendly codes printed on receipts and job tickets.
 */
final class Cmshop_Code {

	/**
	 * No 0/O or 1/I/L, so codes read back over the phone without mistakes.
	 */
	private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	public const TICKET_LENGTH = 8;

	private const INVOICE_PAD = 6;

	/**
	 * Invoice number for a sale ID: "INV-000042".
	 *
	 * @param string $prefix  Prefix from settings.
	 * @param int    $sale_id Sale ID.
	 * @return string
	 */
	public static function invoice( string $prefix, int $sale_id ): string {
		return $prefix . str_pad( (string) $sale_id, self::INVOICE_PAD, '0', STR_PAD_LEFT );
	}

	/**
	 * Random repair ticket code such as "K7MQ2XRT".
	 *
	 * @return string
	 */
	public static function ticket(): string {
		$code = '';
		$max  = strlen( self::ALPHABET ) - 1;

		for ( $i = 0; $i < self::TICKET_LENGTH; $i++ ) {
			$code .= self::ALPHABET[ random_int( 0, $max ) ];
		}

		return $code;
	}

	/**
	 * Normalise a typed ticket code: uppercase, separators removed.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function normalize_ticket( string $raw ): string {
		return strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', $raw ) );
	}

	/**
	 * Whether a normalised value could be a ticket code.
	 *
	 * @param string $code Normalised code.
	 * @return bool
	 */
	public static function is_ticket( string $code ): bool {
		return 1 === preg_match( '/^[' . self::ALPHABET . ']{' . self::TICKET_LENGTH . '}$/', $code );
	}
}
