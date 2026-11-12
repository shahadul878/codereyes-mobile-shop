<?php
/**
 * Installment plans for credit sales, and applying collected money to dues.
 *
 * @package CodereyesMobileShop
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for staff and are escaped where they are displayed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Only the schedule is stored. How much of each installment is paid comes
 * from what the customer has paid since the sale, applied in order, so the
 * plan and the payments can never disagree. Money is in cents.
 */
final class Cmshop_Installments {

	public const MIN_COUNT = 2;
	public const MAX_COUNT = 12;
	public const INTERVALS = array( 'week', 'month' );

	/**
	 * Split an amount into equal installments; the last takes the remainder.
	 *
	 * @param int    $due      Amount owed after the down payment.
	 * @param int    $count    Number of installments (2-12).
	 * @param string $first    First due date, Y-m-d.
	 * @param string $interval week|month.
	 * @return array<int, array{seq: int, due_date: string, amount: int}>
	 * @throws InvalidArgumentException With a message for the cashier.
	 */
	public static function plan( int $due, int $count, string $first, string $interval ): array {
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $first );

		if ( $due <= 0 || $count < self::MIN_COUNT || $count > self::MAX_COUNT || ! in_array( $interval, self::INTERVALS, true ) ) {
			throw new InvalidArgumentException( __( 'Choose 2 to 12 weekly or monthly installments.', 'codereyes-mobile-shop' ) );
		}

		if ( false === $start || $start->format( 'Y-m-d' ) !== $first ) {
			throw new InvalidArgumentException( __( 'Enter the first installment date as YYYY-MM-DD.', 'codereyes-mobile-shop' ) );
		}

		$each = intdiv( $due, $count );
		$plan = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$plan[] = array(
				'seq'      => $i + 1,
				'due_date' => self::step( $start, $i, $interval ),
				'amount'   => $count - 1 === $i ? $due - ( $each * ( $count - 1 ) ) : $each,
			);
		}

		return $plan;
	}

	/**
	 * Each installment's state after applying what was paid since the sale.
	 *
	 * @param array[] $plan  Rows of seq, due_date, amount (cents).
	 * @param int     $paid  Paid since the sale (cents).
	 * @param string  $today Y-m-d.
	 * @return array[] Plan rows with paid, remaining, state (paid|partial|upcoming) and overdue.
	 */
	public static function status( array $plan, int $paid, string $today ): array {
		$left = max( 0, $paid );

		return array_map(
			static function ( array $row ) use ( &$left, $today ): array {
				$amount    = (int) $row['amount'];
				$applied   = min( $left, $amount );
				$left     -= $applied;
				$remaining = $amount - $applied;

				return array_merge(
					$row,
					array(
						'paid'      => $applied,
						'remaining' => $remaining,
						'state'     => 0 === $remaining ? 'paid' : ( $applied > 0 ? 'partial' : 'upcoming' ),
						'overdue'   => $remaining > 0 && (string) $row['due_date'] < $today,
					)
				);
			},
			$plan
		);
	}

	/**
	 * Overdue amount and count, and the next date still owed.
	 *
	 * @param array[] $status From status().
	 * @return array{overdue: int, overdue_count: int, next_date: string, next_amount: int}
	 */
	public static function summary( array $status ): array {
		$overdue = array_filter(
			$status,
			static function ( array $row ): bool {
				return $row['overdue'];
			}
		);
		$open    = array_values(
			array_filter(
				$status,
				static function ( array $row ): bool {
					return $row['remaining'] > 0;
				}
			)
		);

		return array(
			'overdue'       => (int) array_sum( array_column( $overdue, 'remaining' ) ),
			'overdue_count' => count( $overdue ),
			'next_date'     => (string) ( $open[0]['due_date'] ?? '' ),
			'next_amount'   => (int) ( $open[0]['remaining'] ?? 0 ),
		);
	}

	/**
	 * Spread a collected amount over unpaid sales, oldest first.
	 *
	 * @param int     $amount Collected (cents).
	 * @param array[] $sales  Rows of id and due (cents), oldest first.
	 * @return array<int, array{sale_id: int, amount: int}>
	 * @throws InvalidArgumentException When nothing or too much is collected.
	 */
	public static function allocate( int $amount, array $sales ): array {
		$owed = (int) array_sum( array_column( $sales, 'due' ) );

		if ( $amount <= 0 ) {
			throw new InvalidArgumentException( __( 'Enter the amount received.', 'codereyes-mobile-shop' ) );
		}

		if ( $amount > $owed ) {
			throw new InvalidArgumentException( __( 'That is more than the customer owes.', 'codereyes-mobile-shop' ) );
		}

		$parts = array();
		$left  = $amount;

		foreach ( $sales as $sale ) {
			$take = min( $left, (int) $sale['due'] );

			if ( $take > 0 ) {
				$parts[] = array(
					'sale_id' => (int) $sale['id'],
					'amount'  => $take,
				);
				$left   -= $take;
			}
		}

		return $parts;
	}

	/**
	 * The i-th date after the start. Month steps keep month ends (31 Jan → 28 Feb → 31 Mar).
	 *
	 * @param DateTimeImmutable $start    First date.
	 * @param int               $i        Steps.
	 * @param string            $interval week|month.
	 * @return string Y-m-d.
	 */
	private static function step( DateTimeImmutable $start, int $i, string $interval ): string {
		if ( 'week' === $interval ) {
			return $start->modify( '+' . ( 7 * $i ) . ' days' )->format( 'Y-m-d' );
		}

		$month = $start->modify( 'first day of +' . $i . ' month' );
		$day   = min( (int) $start->format( 'j' ), (int) $month->format( 't' ) );

		return $month->setDate( (int) $month->format( 'Y' ), (int) $month->format( 'n' ), $day )->format( 'Y-m-d' );
	}
}
