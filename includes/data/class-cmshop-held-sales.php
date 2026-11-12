<?php
/**
 * Sales a cashier has put on hold, kept per user on the server.
 *
 * @package CodereyesMobileShop
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are returned as JSON and escaped where they are displayed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * A cashier parks a cart (customer still deciding), serves the next one, then
 * resumes it. Stored in user meta so it survives a browser restart. The cart
 * is only a draft: checkout re-checks stock and prices on the server.
 */
final class Cmshop_Held_Sales {

	private const META      = 'cmshop_held_sales';
	private const MAX_HELD  = 10;
	private const MAX_BYTES = 20000;
	private const MAX_LABEL = 80;

	/**
	 * Held sales for a user, newest first.
	 *
	 * @param int $user_id User.
	 * @return array[] Entries of id, label, saved_at, cart.
	 */
	public static function all( int $user_id ): array {
		$held = get_user_meta( $user_id, self::META, true );

		return is_array( $held ) ? array_values( $held ) : array();
	}

	/**
	 * Hold a cart.
	 *
	 * @param int    $user_id User.
	 * @param string $label   Short name, e.g. the customer.
	 * @param array  $cart    Cart draft from the POS.
	 * @return array The held entry.
	 * @throws InvalidArgumentException When the cart is too large or too many are held.
	 */
	public static function hold( int $user_id, string $label, array $cart ): array {
		$held = self::all( $user_id );

		if ( count( $held ) >= self::MAX_HELD ) {
			throw new InvalidArgumentException( __( 'You can hold up to 10 sales. Finish or remove one first.', 'codereyes-mobile-shop' ) );
		}

		if ( strlen( (string) wp_json_encode( $cart ) ) > self::MAX_BYTES ) {
			throw new InvalidArgumentException( __( 'This sale is too large to hold.', 'codereyes-mobile-shop' ) );
		}

		$entry = array(
			'id'       => wp_generate_password( 12, false ),
			'label'    => mb_substr( sanitize_text_field( $label ), 0, self::MAX_LABEL ),
			'saved_at' => current_time( 'mysql' ),
			'cart'     => $cart,
		);

		update_user_meta( $user_id, self::META, array_merge( array( $entry ), $held ) );

		return $entry;
	}

	/**
	 * Remove a held sale (resumed or discarded).
	 *
	 * @param int    $user_id User.
	 * @param string $id      Held sale ID.
	 * @return bool Whether it existed.
	 */
	public static function remove( int $user_id, string $id ): bool {
		$held = self::all( $user_id );
		$kept = array_values(
			array_filter(
				$held,
				static function ( array $entry ) use ( $id ): bool {
					return (string) $entry['id'] !== $id;
				}
			)
		);

		update_user_meta( $user_id, self::META, $kept );

		return count( $kept ) < count( $held );
	}
}
