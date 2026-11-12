<?php
/**
 * Splitting a sale's payment across methods (cash, bKash, card...).
 *
 * @package CodereyesMobileShop
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for the cashier and are escaped where they are displayed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;


/**
 * Turns the payment rows a cashier enters into ledger entries.
 *
 * Change is only given in cash: the cash entry is reduced by it, so the
 * ledger records what stayed in the drawer. Card, mobile banking and bank
 * payments can't be more than the total. A trade-in (the customer's old
 * phone) pays like cash; when it is worth more than the sale, the shop pays
 * the difference out of the drawer (a negative cash entry).
 */
final class Cmshop_Payments {

	public const SPLIT    = 'split';
	public const TRADE_IN = 'trade_in';

	private const MAX_ROWS = 5;
	private const MAX_REF  = 100;
	private const CASH     = 'cash';

	/**
	 * Ledger entries, amount paid, change and balance due for a sale.
	 *
	 * @param array[]  $rows    Rows of method, amount (raw text) and optional reference.
	 * @param int      $total   Sale total in cents.
	 * @param string[] $methods Allowed method keys.
	 * @return array{entries: array[], paid: int, change: int, due: int}
	 * @throws InvalidArgumentException With a message for the cashier.
	 */
	public static function allocate( array $rows, int $total, array $methods ): array {
		if ( count( $rows ) > self::MAX_ROWS ) {
			throw new InvalidArgumentException( __( 'Use at most 5 payment methods in one sale.', 'codereyes-mobile-shop' ) );
		}

		$merged = self::merge( $rows, $methods );
		$sum    = array_sum( array_column( $merged, 'cents' ) );
		$cash   = (int) ( $merged[ self::CASH ]['cents'] ?? 0 );
		$trade  = (int) ( $merged[ self::TRADE_IN ]['cents'] ?? 0 );
		$change = max( 0, $sum - $total );

		if ( $change > $cash + $trade ) {
			throw new InvalidArgumentException( __( 'Card, mobile banking and bank payments can\'t be more than the total. Give change in cash only.', 'codereyes-mobile-shop' ) );
		}

		if ( $change > 0 ) {
			$merged[ self::CASH ] = array(
				'cents'     => $cash - $change,
				'reference' => (string) ( $merged[ self::CASH ]['reference'] ?? '' ),
			);
		}

		$entries = array();

		foreach ( $merged as $method => $row ) {
			if ( 0 !== $row['cents'] ) {
				$entries[] = array(
					'method'    => $method,
					'amount'    => Cmshop_Money::from_cents( $row['cents'] ),
					'reference' => $row['reference'],
				);
			}
		}

		$paid = $sum - $change;

		return array(
			'entries' => $entries,
			'paid'    => $paid,
			'change'  => $change,
			'due'     => max( 0, $total - $paid ),
		);
	}

	/**
	 * The method to show on a sale: its only method, "split", or the fallback.
	 *
	 * @param array[] $entries  Ledger entries.
	 * @param string  $fallback Method when nothing was paid.
	 * @return string
	 */
	public static function summary_method( array $entries, string $fallback ): string {
		$methods = array_values( array_unique( array_column( $entries, 'method' ) ) );

		if ( array() === $methods ) {
			return $fallback;
		}

		return 1 === count( $methods ) ? (string) $methods[0] : self::SPLIT;
	}

	/**
	 * Valid rows merged by method, in the order first entered.
	 *
	 * @param array[]  $rows    Raw rows.
	 * @param string[] $methods Allowed methods.
	 * @return array<string, array{cents: int, reference: string}>
	 * @throws InvalidArgumentException When a row is invalid.
	 */
	private static function merge( array $rows, array $methods ): array {
		$merged = array();

		foreach ( $rows as $row ) {
			$raw = trim( (string) ( $row['amount'] ?? '' ) );

			if ( '' === $raw ) {
				continue;
			}

			$amount = Cmshop_Money::parse( $raw );
			$method = (string) ( $row['method'] ?? '' );

			if ( null === $amount ) {
				throw new InvalidArgumentException( __( 'A payment amount is not valid.', 'codereyes-mobile-shop' ) );
			}

			if ( ! in_array( $method, $methods, true ) ) {
				throw new InvalidArgumentException( __( 'Choose a payment method for each amount.', 'codereyes-mobile-shop' ) );
			}

			$cents = Cmshop_Money::to_cents( $amount );

			if ( 0 === $cents ) {
				continue;
			}

			$reference         = mb_substr( trim( (string) ( $row['reference'] ?? '' ) ), 0, self::MAX_REF );
			$merged[ $method ] = array(
				'cents'     => ( $merged[ $method ]['cents'] ?? 0 ) + $cents,
				'reference' => '' !== $reference ? $reference : ( $merged[ $method ]['reference'] ?? '' ),
			);
		}

		return $merged;
	}
}
