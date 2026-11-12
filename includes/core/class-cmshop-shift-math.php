<?php
/**
 * Cash drawer maths: counting notes, what should be in the drawer.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Pure calculations for shifts. Money is in cents.
 */
final class Cmshop_Shift_Math {

	/**
	 * Bangladeshi taka notes and coins, largest first.
	 */
	public const DENOMINATIONS = array( 1000, 500, 200, 100, 50, 20, 10, 5, 2, 1 );

	/**
	 * Total of a note count. Unknown notes and negative counts are ignored.
	 *
	 * @param array<string,string> $counts Note value => how many (Bengali digits allowed).
	 * @return int Cents.
	 */
	public static function count( array $counts ): int {
		$total = 0;

		foreach ( self::DENOMINATIONS as $note ) {
			$raw    = trim( Cmshop_Digits::to_ascii( (string) ( $counts[ (string) $note ] ?? '' ) ) );
			$pieces = 1 === preg_match( '/^\d{1,6}$/', $raw ) ? (int) $raw : 0;
			$total += $note * 100 * $pieces;
		}

		return $total;
	}

	/**
	 * What a shift took, by method and kind, and the cash that should be in the drawer.
	 *
	 * @param array[] $payments Ledger rows (kind, method, amount) made during the shift.
	 * @param int     $opening  Opening float (cents).
	 * @return array{by_method: array<string,int>, cash_net: int, expected: int, refunds: int, cash_in: int, cash_out: int, dues: int, sales: int, suppliers: int}
	 */
	public static function summary( array $payments, int $opening ): array {
		$by_method = array();
		$totals    = array(
			'refunds'   => 0,
			'cash_in'   => 0,
			'cash_out'  => 0,
			'dues'      => 0,
			'sales'     => 0,
			'suppliers' => 0,
		);

		foreach ( $payments as $payment ) {
			$cents  = Cmshop_Money::to_cents( (string) $payment['amount'] );
			$method = (string) $payment['method'];
			$kind   = (string) $payment['kind'];

			$by_method[ $method ] = ( $by_method[ $method ] ?? 0 ) + $cents;

			if ( 'refund' === $kind ) {
				$totals['refunds'] -= $cents;
			} elseif ( 'cash_in' === $kind ) {
				$totals['cash_in'] += $cents;
			} elseif ( 'cash_out' === $kind ) {
				$totals['cash_out'] -= $cents;
			} elseif ( 'due' === $kind ) {
				$totals['dues'] += $cents;
			} elseif ( 'supplier' === $kind ) {
				$totals['suppliers'] -= $cents;
			} else {
				$totals['sales'] += $cents;
			}
		}

		$cash = $by_method['cash'] ?? 0;

		return array_merge(
			$totals,
			array(
				'by_method' => $by_method,
				'cash_net'  => $cash,
				'expected'  => $opening + $cash,
			)
		);
	}

	/**
	 * Counted minus expected: negative is a shortage, positive is extra.
	 *
	 * @param int $counted  Counted cash.
	 * @param int $expected Expected cash.
	 * @return int
	 */
	public static function difference( int $counted, int $expected ): int {
		return $counted - $expected;
	}
}
