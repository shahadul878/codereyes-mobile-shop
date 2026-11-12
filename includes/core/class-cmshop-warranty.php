<?php
/**
 * Warranty cover from the date a phone was sold.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Whether a sale is still under warranty, and until when.
 */
final class Cmshop_Warranty {

	public const ACTIVE  = 'active';
	public const EXPIRED = 'expired';
	public const NONE    = 'none';

	/**
	 * Warranty status on a given day.
	 *
	 * Cover runs to the end of the day $days after the sale.
	 *
	 * @param string $sold_at Sale time (Y-m-d H:i:s, site time).
	 * @param int    $days    Warranty length; 0 means no warranty.
	 * @param string $today   Today (Y-m-d, site time).
	 * @return array{state: string, ends: string, days_left: int}
	 */
	public static function status( string $sold_at, int $days, string $today ): array {
		if ( $days <= 0 ) {
			return array(
				'state'     => self::NONE,
				'ends'      => '',
				'days_left' => 0,
			);
		}

		$ends = ( new DateTimeImmutable( substr( $sold_at, 0, 10 ) ) )->modify( '+' . $days . ' days' );
		$left = (int) ( new DateTimeImmutable( $today ) )->diff( $ends )->format( '%r%a' );

		return array(
			'state'     => $left >= 0 ? self::ACTIVE : self::EXPIRED,
			'ends'      => $ends->format( 'Y-m-d' ),
			'days_left' => max( 0, $left ),
		);
	}

	/**
	 * Warranty length for a phone's condition.
	 *
	 * @param string $grade     Condition grade.
	 * @param int    $new_days  Days for new phones.
	 * @param int    $used_days Days for used phones.
	 * @return int
	 */
	public static function days_for( string $grade, int $new_days, int $used_days ): int {
		return 'new' === $grade ? $new_days : $used_days;
	}
}
