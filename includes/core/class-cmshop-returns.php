<?php
/**
 * Pricing a return: what comes back, what it refunds, what it cost.
 *
 * @package CodereyesMobileShop
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for staff and are escaped where they are displayed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * A return refunds what the customer paid for the items: their line price
 * less their share of the sale discount. Money is in cents.
 */
final class Cmshop_Returns {

	/**
	 * Refund and cost of returning some items.
	 *
	 * @param array[]         $lines    Sale lines (id, quantity, returned_qty, line_total, unit_cost).
	 * @param array<int, int> $wanted   Line ID => quantity to return.
	 * @param int             $subtotal Sale subtotal (cents).
	 * @param int             $discount Sale discount (cents).
	 * @return array{refund: int, cost: int, quantities: array<int,int>}
	 * @throws InvalidArgumentException With a message for staff.
	 */
	public static function price( array $lines, array $wanted, int $subtotal, int $discount ): array {
		$by_id      = array_column( $lines, null, 'id' );
		$quantities = array_filter( array_map( 'intval', $wanted ) );
		$gross      = 0;
		$cost       = 0;

		if ( array() === $quantities ) {
			throw new InvalidArgumentException( __( 'Choose at least one item to return.', 'codereyes-mobile-shop' ) );
		}

		foreach ( $quantities as $line_id => $quantity ) {
			$line = $by_id[ $line_id ] ?? null;

			if ( null === $line ) {
				throw new InvalidArgumentException( __( 'That item is not on this sale.', 'codereyes-mobile-shop' ) );
			}

			$left = (int) $line['quantity'] - (int) $line['returned_qty'];

			if ( $quantity < 0 || $quantity > $left ) {
				/* translators: %d: quantity that can still be returned. */
				throw new InvalidArgumentException( sprintf( __( 'Only %d of that item can still be returned.', 'codereyes-mobile-shop' ), $left ) );
			}

			$gross += intdiv( Cmshop_Money::to_cents( (string) $line['line_total'] ) * $quantity, max( 1, (int) $line['quantity'] ) );
			$cost  += Cmshop_Money::to_cents( (string) $line['unit_cost'] ) * $quantity;
		}

		// Each item's share of the sale discount comes off its refund.
		$share = $subtotal > 0 ? intdiv( $gross * $discount, $subtotal ) : 0;

		return array(
			'refund'     => $gross - $share,
			'cost'       => $cost,
			'quantities' => $quantities,
		);
	}

	/**
	 * Whether this return leaves nothing on the sale.
	 *
	 * @param array[]         $lines      Sale lines.
	 * @param array<int, int> $quantities Line ID => quantity returned now.
	 * @return bool
	 */
	public static function all_returned( array $lines, array $quantities ): bool {
		foreach ( $lines as $line ) {
			if ( (int) $line['quantity'] - (int) $line['returned_qty'] - (int) ( $quantities[ (int) $line['id'] ] ?? 0 ) > 0 ) {
				return false;
			}
		}

		return true;
	}
}
