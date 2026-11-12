<?php
/**
 * Figures for the dashboard: daily series, bar heights, changes, shares.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Pure calculations. Money is in cents.
 */
final class Cmshop_Dashboard_Math {

	/**
	 * One entry per day from $start, with days that had no sales as zero.
	 *
	 * @param array[] $rows  Rows of day (Y-m-d), total, profit, sales.
	 * @param string  $start First day, Y-m-d.
	 * @param int     $days  Number of days.
	 * @return array<int, array{day: string, total: int, profit: int, sales: int}>
	 */
	public static function fill_days( array $rows, string $start, int $days ): array {
		$by_day = array();

		foreach ( $rows as $row ) {
			$by_day[ (string) $row['day'] ] = $row;
		}

		$series = array();
		$day    = new DateTimeImmutable( $start );

		for ( $i = 0; $i < $days; $i++ ) {
			$key      = $day->format( 'Y-m-d' );
			$row      = $by_day[ $key ] ?? array();
			$series[] = array(
				'day'    => $key,
				'total'  => Cmshop_Money::to_cents( (string) ( $row['total'] ?? '0.00' ) ),
				'profit' => Cmshop_Money::to_cents( (string) ( $row['profit'] ?? '0.00' ) ),
				'sales'  => (int) ( $row['sales'] ?? 0 ),
			);
			$day      = $day->modify( '+1 day' );
		}

		return $series;
	}

	/**
	 * Bar heights as a percent of the best day's total.
	 *
	 * @param array[] $series From fill_days().
	 * @return array[] The series with total_pct and profit_pct added.
	 */
	public static function bar_heights( array $series ): array {
		$max = max( 0, ...array_map( 'intval', array_column( $series, 'total' ) ) );

		return array_map(
			static function ( array $day ) use ( $max ): array {
				return array_merge(
					$day,
					array(
						'total_pct'  => $max > 0 ? (int) round( $day['total'] * 100 / $max ) : 0,
						'profit_pct' => $max > 0 ? (int) round( max( 0, $day['profit'] ) * 100 / $max ) : 0,
					)
				);
			},
			$series
		);
	}

	/**
	 * Percent change from yesterday, or null when there is nothing to compare.
	 *
	 * @param int $today     Today's amount.
	 * @param int $yesterday Yesterday's amount.
	 * @return int|null
	 */
	public static function change( int $today, int $yesterday ): ?int {
		return $yesterday > 0 ? (int) round( ( $today - $yesterday ) * 100 / $yesterday ) : null;
	}

	/**
	 * Profit as a percent of sales.
	 *
	 * @param int $profit Profit.
	 * @param int $total  Sales.
	 * @return int
	 */
	public static function margin( int $profit, int $total ): int {
		return $total > 0 ? (int) round( $profit * 100 / $total ) : 0;
	}

	/**
	 * Donut segments for the positive totals, largest first, percents adding up to 100.
	 *
	 * @param array<string,string> $totals key => amount.
	 * @return array<int, array{key: string, cents: int, percent: int, start: int}>
	 */
	public static function donut( array $totals ): array {
		$positive = array();

		foreach ( $totals as $key => $amount ) {
			$cents = Cmshop_Money::to_cents( (string) $amount );

			if ( $cents > 0 ) {
				$positive[ (string) $key ] = $cents;
			}
		}

		arsort( $positive );

		$sum      = array_sum( $positive );
		$segments = array();
		$start    = 0;
		$keys     = array_keys( $positive );

		foreach ( $keys as $i => $key ) {
			// The last segment takes what is left, so rounding never leaves a gap.
			$percent    = count( $keys ) - 1 === $i ? 100 - $start : (int) round( $positive[ $key ] * 100 / $sum );
			$segments[] = array(
				'key'     => $key,
				'cents'   => $positive[ $key ],
				'percent' => $percent,
				'start'   => $start,
			);
			$start     += $percent;
		}

		return $segments;
	}
}
