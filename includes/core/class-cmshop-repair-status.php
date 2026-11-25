<?php
/**
 * Repair job statuses.
 *
 * @package MobileShopManager
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * A repair moves from received to collected (or cancelled). Jobs booked on
 * the website start as "requested" until the phone is brought in.
 */
final class Cmshop_Repair_Status {

	public const REQUESTED     = 'requested';
	public const RECEIVED      = 'received';
	public const DIAGNOSING    = 'diagnosing';
	public const WAITING_PARTS = 'waiting_parts';
	public const REPAIRING     = 'repairing';
	public const READY         = 'ready';
	public const COLLECTED     = 'collected';
	public const CANCELLED     = 'cancelled';

	/**
	 * Statuses in workflow order, with labels.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		return array(
			self::REQUESTED     => __( 'Booked online', 'mobile-shop-manager' ),
			self::RECEIVED      => __( 'Received', 'mobile-shop-manager' ),
			self::DIAGNOSING    => __( 'Diagnosing', 'mobile-shop-manager' ),
			self::WAITING_PARTS => __( 'Waiting for parts', 'mobile-shop-manager' ),
			self::REPAIRING     => __( 'Repairing', 'mobile-shop-manager' ),
			self::READY         => __( 'Ready to collect', 'mobile-shop-manager' ),
			self::COLLECTED     => __( 'Collected', 'mobile-shop-manager' ),
			self::CANCELLED     => __( 'Cancelled', 'mobile-shop-manager' ),
		);
	}

	/**
	 * Statuses that still need work or collection.
	 *
	 * @return string[]
	 */
	public static function open(): array {
		return array( self::REQUESTED, self::RECEIVED, self::DIAGNOSING, self::WAITING_PARTS, self::REPAIRING, self::READY );
	}

	/**
	 * Whether a status key exists.
	 *
	 * @param string $status Status key.
	 * @return bool
	 */
	public static function is_valid( string $status ): bool {
		return array_key_exists( $status, self::all() );
	}

	/**
	 * Label for a status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function label( string $status ): string {
		return self::all()[ $status ] ?? $status;
	}

	/**
	 * Balance the customer still owes: final cost (or estimate) minus deposit.
	 *
	 * @param array $repair Repair row.
	 * @return string Never negative.
	 */
	public static function balance_due( array $repair ): string {
		$final  = (string) ( $repair['final_cost'] ?? '' );
		$charge = '' !== $final ? $final : (string) ( $repair['estimate'] ?? '0.00' );
		$due    = Cmshop_Money::to_cents( $charge ) - Cmshop_Money::to_cents( (string) ( $repair['deposit'] ?? '0.00' ) );

		return Cmshop_Money::from_cents( max( 0, $due ) );
	}
}
