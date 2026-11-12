<?php
/**
 * Staff commission.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * A percentage of each staff member's sales (or of the profit on them),
 * after returns. Rates are basis points: 150 = 1.5%.
 */
final class Cmshop_Commission {

	public const ON_SALES  = 'sales';
	public const ON_PROFIT = 'profit';

	private const FULL = 10000;

	/**
	 * Rate typed as a percentage ("1.5", "১০", "2.5 %") in basis points; 0 when invalid.
	 *
	 * @param string $raw Typed rate.
	 * @return int
	 */
	public static function rate( string $raw ): int {
		$clean = trim( str_replace( '%', '', Cmshop_Digits::to_ascii( $raw ) ) );

		if ( 1 !== preg_match( '/^(\d{1,3})(?:\.(\d{1,2}))?$/', $clean, $parts ) ) {
			return 0;
		}

		$points = (int) $parts[1] * 100 + (int) str_pad( $parts[2] ?? '', 2, '0' );

		return $points <= self::FULL ? $points : 0;
	}

	/**
	 * Basis points back as a short percentage: 150 → "1.5".
	 *
	 * @param int $points Basis points.
	 * @return string
	 */
	public static function rate_text( int $points ): string {
		return rtrim( rtrim( sprintf( '%d.%02d', intdiv( $points, 100 ), $points % 100 ), '0' ), '.' );
	}

	/**
	 * Commission in cents, rounded half up; nothing on a loss.
	 *
	 * @param int    $sales  Net sales (cents).
	 * @param int    $profit Net profit (cents).
	 * @param int    $points Rate in basis points.
	 * @param string $base   ON_SALES or ON_PROFIT.
	 * @return int
	 */
	public static function amount( int $sales, int $profit, int $points, string $base ): int {
		$on = self::ON_PROFIT === $base ? $profit : $sales;

		return $on > 0 && $points > 0 ? intdiv( $on * $points + intdiv( self::FULL, 2 ), self::FULL ) : 0;
	}

	/**
	 * Report rows (best seller first) and totals.
	 *
	 * @param array[] $rows   Rows of user_id (0 = website), sales (count), total, profit (amounts).
	 * @param int     $points Rate in basis points.
	 * @param string  $base   ON_SALES or ON_PROFIT.
	 * @return array{rows: array[], totals: array{sales: int, total: int, profit: int, commission: int}} Amounts in cents.
	 */
	public static function report( array $rows, int $points, string $base ): array {
		$out = array_map(
			static function ( array $row ) use ( $points, $base ): array {
				$total  = Cmshop_Money::to_cents( (string) $row['total'] );
				$profit = Cmshop_Money::to_cents( (string) $row['profit'] );

				return array(
					'user_id'    => (int) $row['user_id'],
					'sales'      => (int) $row['sales'],
					'total'      => $total,
					'profit'     => $profit,
					// Website orders (no staff member) earn no commission.
					'commission' => 0 === (int) $row['user_id'] ? 0 : self::amount( $total, $profit, $points, $base ),
				);
			},
			$rows
		);

		usort(
			$out,
			static function ( array $a, array $b ): int {
				return $b['total'] <=> $a['total'];
			}
		);

		return array(
			'rows'   => $out,
			'totals' => array(
				'sales'      => (int) array_sum( array_column( $out, 'sales' ) ),
				'total'      => (int) array_sum( array_column( $out, 'total' ) ),
				'profit'     => (int) array_sum( array_column( $out, 'profit' ) ),
				'commission' => (int) array_sum( array_column( $out, 'commission' ) ),
			),
		);
	}
}
