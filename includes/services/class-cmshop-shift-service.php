<?php
/**
 * Opening and closing the cash register, and petty cash.
 *
 * @package CodereyesMobileShop
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for staff and are escaped where they are displayed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * A shift starts with a cash float. Everything the cashier takes or pays out
 * in cash is already in the payments ledger, so the expected drawer at close
 * is the float plus net cash. Counting the notes shows any shortage.
 */
final class Cmshop_Shift_Service {

	private const MAX_REASON = 200;

	/**
	 * Shifts.
	 *
	 * @var Cmshop_Shift_Repository
	 */
	private $shifts;

	/**
	 * Payments ledger.
	 *
	 * @var Cmshop_Payment_Repository
	 */
	private $payments;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Shift_Repository   $shifts   Shifts.
	 * @param Cmshop_Payment_Repository $payments Payments ledger.
	 */
	public function __construct( Cmshop_Shift_Repository $shifts, Cmshop_Payment_Repository $payments ) {
		$this->shifts   = $shifts;
		$this->payments = $payments;
	}

	/**
	 * The staff member's open shift with its live summary, or null.
	 *
	 * @param int $user_id Staff member.
	 * @return array|null Shift row plus summary.
	 */
	public function current( int $user_id ): ?array {
		$shift = $this->shifts->open_for( $user_id );

		return null === $shift ? null : $this->with_summary( $shift );
	}

	/**
	 * A shift with what it took (closed shifts use their close time).
	 *
	 * @param array $shift Shift row.
	 * @return array
	 */
	public function with_summary( array $shift ): array {
		$to   = null === $shift['closed_at'] ? gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . ' +1 second' ) ) : (string) $shift['closed_at'];
		$rows = $this->payments->for_user_between( (int) $shift['user_id'], (string) $shift['opened_at'], $to );

		return array_merge(
			$shift,
			array(
				'summary' => Cmshop_Shift_Math::summary( $rows, Cmshop_Money::to_cents( (string) $shift['opening_float'] ) ),
				'petty'   => array_values(
					array_filter(
						$rows,
						static function ( array $row ): bool {
							return in_array( $row['kind'], array( Cmshop_Payment_Repository::KIND_CASH_IN, Cmshop_Payment_Repository::KIND_CASH_OUT ), true );
						}
					)
				),
			)
		);
	}

	/**
	 * Open a shift with the cash already in the drawer.
	 *
	 * @param int    $user_id Staff member.
	 * @param string $opening   Opening float typed by staff.
	 * @return int Shift ID.
	 * @throws InvalidArgumentException With a message for staff.
	 */
	public function open( int $user_id, string $opening ): int {
		$amount = Cmshop_Money::parse_or_zero( $opening );

		if ( null === $amount ) {
			throw new InvalidArgumentException( __( 'Enter the cash in the drawer, e.g. 2000.', 'codereyes-mobile-shop' ) );
		}

		if ( null !== $this->shifts->open_for( $user_id ) ) {
			throw new InvalidArgumentException( __( 'You already have an open shift. Close it first.', 'codereyes-mobile-shop' ) );
		}

		return $this->shifts->insert(
			array(
				'user_id'       => $user_id,
				'opened_at'     => current_time( 'mysql' ),
				'opening_float' => $amount,
			)
		);
	}

	/**
	 * Cash put into or taken out of the drawer (change top-up, tea, transport...).
	 *
	 * @param int    $user_id   Staff member.
	 * @param string $direction in|out.
	 * @param string $amount    Amount typed by staff.
	 * @param string $reason    Why.
	 * @throws InvalidArgumentException With a message for staff.
	 */
	public function petty( int $user_id, string $direction, string $amount, string $reason ): void {
		$parsed = Cmshop_Money::parse( $amount );
		$reason = mb_substr( trim( sanitize_text_field( $reason ) ), 0, self::MAX_REASON );

		if ( null === $this->shifts->open_for( $user_id ) ) {
			throw new InvalidArgumentException( __( 'Open a shift first.', 'codereyes-mobile-shop' ) );
		}

		if ( null === $parsed || Cmshop_Money::to_cents( $parsed ) <= 0 ) {
			throw new InvalidArgumentException( __( 'Enter the amount.', 'codereyes-mobile-shop' ) );
		}

		if ( '' === $reason ) {
			throw new InvalidArgumentException( __( 'Say what the cash was for.', 'codereyes-mobile-shop' ) );
		}

		$out = 'out' === $direction;

		$this->payments->record(
			array(
				array(
					'method' => 'cash',
					'amount' => Cmshop_Money::from_cents( ( $out ? -1 : 1 ) * Cmshop_Money::to_cents( $parsed ) ),
				),
			),
			array(
				'kind'       => $out ? Cmshop_Payment_Repository::KIND_CASH_OUT : Cmshop_Payment_Repository::KIND_CASH_IN,
				'note'       => $reason,
				'created_by' => $user_id,
			)
		);
	}

	/**
	 * Close the shift with the cash counted in the drawer.
	 *
	 * @param int                  $user_id Staff member.
	 * @param array<string,string> $counts  Note value => how many.
	 * @param string               $note    Note for the owner.
	 * @return array Closed shift with summary.
	 * @throws InvalidArgumentException When no shift is open.
	 */
	public function close( int $user_id, array $counts, string $note ): array {
		$shift = $this->shifts->open_for( $user_id );

		if ( null === $shift ) {
			throw new InvalidArgumentException( __( 'There is no open shift to close.', 'codereyes-mobile-shop' ) );
		}

		$live = $this->with_summary( $shift );
		$now  = current_time( 'mysql' );

		$this->shifts->update(
			(int) $shift['id'],
			array(
				'closed_at'     => $now,
				'expected_cash' => Cmshop_Money::from_cents( $live['summary']['expected'] ),
				'counted_cash'  => Cmshop_Money::from_cents( Cmshop_Shift_Math::count( $counts ) ),
				'note'          => mb_substr( sanitize_text_field( $note ), 0, 255 ),
			)
		);

		return $this->with_summary( (array) $this->shifts->find( (int) $shift['id'] ) );
	}
}
