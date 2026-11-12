<?php
/**
 * Prices a counter sale from the stock rows it sells.
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for the cashier and are escaped where they are displayed.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Checks each line against stock and works out totals, profit and change.
 *
 * Staff may change the unit price (phone shops haggle), but quantities,
 * costs and availability always come from the stock rows, never the browser.
 */
final class Cmshop_Cart {

	public const MAX_LINES = 100;

	/**
	 * Price a sale.
	 *
	 * @param array  $lines       Submitted lines: [ item_id, quantity, unit_price ].
	 * @param array  $items       Stock rows keyed by ID (locked by the caller).
	 * @param string $discount    Raw discount.
	 * @param string $amount_paid Raw amount paid; empty means paid in full.
	 * @return array Priced sale.
	 * @throws InvalidArgumentException With a message for the cashier.
	 */
	public static function price( array $lines, array $items, string $discount, string $amount_paid ): array {
		if ( array() === $lines ) {
			throw new InvalidArgumentException( __( 'Add at least one item to the sale.', 'codereyes-mobile-shop' ) );
		}

		if ( count( $lines ) > self::MAX_LINES ) {
			throw new InvalidArgumentException( __( 'A sale can have at most 100 lines.', 'codereyes-mobile-shop' ) );
		}

		$priced   = self::price_lines( $lines, $items );
		$subtotal = array_sum( array_column( $priced, 'line_cents' ) );
		$cost     = array_sum( array_column( $priced, 'cost_cents' ) );
		$discount = self::amount( $discount, __( 'The discount is not a valid amount.', 'codereyes-mobile-shop' ) );

		if ( $discount > $subtotal ) {
			throw new InvalidArgumentException( __( 'The discount is larger than the sale.', 'codereyes-mobile-shop' ) );
		}

		$total = $subtotal - $discount;
		$paid  = '' === trim( $amount_paid ) ? $total : self::amount( $amount_paid, __( 'The amount paid is not a valid amount.', 'codereyes-mobile-shop' ) );

		return array(
			'lines'       => array_map( array( self::class, 'public_line' ), $priced ),
			'subtotal'    => Cmshop_Money::from_cents( $subtotal ),
			'discount'    => Cmshop_Money::from_cents( $discount ),
			'total'       => Cmshop_Money::from_cents( $total ),
			'cost_total'  => Cmshop_Money::from_cents( $cost ),
			'profit'      => Cmshop_Money::from_cents( $total - $cost ),
			'amount_paid' => Cmshop_Money::from_cents( $paid ),
			'balance_due' => Cmshop_Money::from_cents( max( 0, $total - $paid ) ),
			'change'      => Cmshop_Money::from_cents( max( 0, $paid - $total ) ),
		);
	}

	/**
	 * Check and price every line.
	 *
	 * @param array $lines Submitted lines.
	 * @param array $items Stock rows keyed by ID.
	 * @return array[]
	 * @throws InvalidArgumentException When a line cannot be sold.
	 */
	private static function price_lines( array $lines, array $items ): array {
		$seen   = array();
		$priced = array();

		foreach ( $lines as $line ) {
			$item_id = (int) ( $line['item_id'] ?? 0 );

			if ( isset( $seen[ $item_id ] ) ) {
				throw new InvalidArgumentException( __( 'The same item is listed twice. Change its quantity instead.', 'codereyes-mobile-shop' ) );
			}

			$seen[ $item_id ] = true;
			$priced[]         = self::price_line( $line, $items[ $item_id ] ?? null );
		}

		return $priced;
	}

	/**
	 * Check and price one line.
	 *
	 * @param array      $line Submitted line.
	 * @param array|null $item Stock row.
	 * @return array
	 * @throws InvalidArgumentException When the line cannot be sold.
	 */
	private static function price_line( array $line, ?array $item ): array {
		if ( null === $item ) {
			throw new InvalidArgumentException( __( 'An item in the sale no longer exists. Remove it and try again.', 'codereyes-mobile-shop' ) );
		}

		$name     = Cmshop_Item::display_name( $item );
		$quantity = (int) ( $line['quantity'] ?? 0 );
		$in_stock = Cmshop_Item::STATUS_SOLD === $item['status'] ? 0 : (int) $item['quantity'];

		if ( $quantity < 1 || $quantity > $in_stock ) {
			/* translators: 1: item name, 2: quantity in stock. */
			throw new InvalidArgumentException( sprintf( __( '%1$s: only %2$d in stock.', 'codereyes-mobile-shop' ), $name, $in_stock ) );
		}

		$raw_price = isset( $line['unit_price'] ) ? (string) $line['unit_price'] : '';
		$price     = '' === trim( $raw_price )
			? Cmshop_Money::to_cents( (string) $item['sale_price'] )
			/* translators: %s: item name. */
			: self::amount( $raw_price, sprintf( __( '%s: the price is not a valid amount.', 'codereyes-mobile-shop' ), $name ) );
		$cost = Cmshop_Money::to_cents( (string) $item['cost_price'] );

		return array(
			'item_id'     => (int) $item['id'],
			'item_type'   => (string) $item['item_type'],
			'description' => $name,
			'imei'        => (string) $item['imei1'],
			'quantity'    => $quantity,
			'unit_cents'  => $price,
			'unit_cost'   => $cost,
			'line_cents'  => $price * $quantity,
			'cost_cents'  => $cost * $quantity,
		);
	}

	/**
	 * Convert an internal line to its stored form.
	 *
	 * @param array $line Internal line.
	 * @return array
	 */
	private static function public_line( array $line ): array {
		return array(
			'item_id'     => $line['item_id'],
			'item_type'   => $line['item_type'],
			'description' => $line['description'],
			'imei'        => $line['imei'],
			'quantity'    => $line['quantity'],
			'unit_price'  => Cmshop_Money::from_cents( $line['unit_cents'] ),
			'unit_cost'   => Cmshop_Money::from_cents( $line['unit_cost'] ),
			'line_total'  => Cmshop_Money::from_cents( $line['line_cents'] ),
		);
	}

	/**
	 * Parse an amount to cents; empty means zero.
	 *
	 * @param string $raw     Raw amount.
	 * @param string $message Error message.
	 * @return int
	 * @throws InvalidArgumentException When invalid.
	 */
	private static function amount( string $raw, string $message ): int {
		$value = Cmshop_Money::parse_or_zero( $raw );

		if ( null === $value ) {
			throw new InvalidArgumentException( $message );
		}

		return Cmshop_Money::to_cents( $value );
	}
}
