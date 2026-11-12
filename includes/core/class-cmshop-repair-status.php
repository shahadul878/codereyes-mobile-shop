<?php
/**
 * Repair job statuses.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * A repair moves from received to collected (or cancelled).
 */
final class Cmshop_Repair_Status {

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
			self::RECEIVED      => __( 'Received', 'codereyes-mobile-shop' ),
			self::DIAGNOSING    => __( 'Diagnosing', 'codereyes-mobile-shop' ),
			self::WAITING_PARTS => __( 'Waiting for parts', 'codereyes-mobile-shop' ),
			self::REPAIRING     => __( 'Repairing', 'codereyes-mobile-shop' ),
			self::READY         => __( 'Ready to collect', 'codereyes-mobile-shop' ),
			self::COLLECTED     => __( 'Collected', 'codereyes-mobile-shop' ),
			self::CANCELLED     => __( 'Cancelled', 'codereyes-mobile-shop' ),
		);
	}

	/**
	 * Statuses that still need work or collection.
	 *
	 * @return string[]
	 */
	public static function open(): array {
		return array( self::RECEIVED, self::DIAGNOSING, self::WAITING_PARTS, self::REPAIRING, self::READY );
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
