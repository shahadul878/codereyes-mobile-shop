<?php
/**
 * Lines of a bulk stock purchase.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Turns the purchase form into checked lines: a phone line becomes one unit
 * per scanned IMEI, an accessory line restocks an existing item or adds a new
 * one. Errors are keyed "lines-{index}-{field}" so the form can mark them.
 */
final class Cmshop_Purchase_Lines {

	public const MAX_LINES    = 50;
	public const MAX_UNITS    = 1000;
	public const MAX_QUANTITY = 9999;

	private const TEXT_LIMIT = 100;
	private const SEPARATORS = '/[\r\n,;]+/';

	/**
	 * Check every line.
	 *
	 * @param array[] $raw Submitted lines.
	 * @return array{lines: array[], errors: array<string,string>, units: int, total: int} Totals in cents.
	 */
	public static function parse( array $raw ): array {
		$lines  = array();
		$errors = array();
		$seen   = array();

		foreach ( array_values( array_filter( $raw, 'is_array' ) ) as $index => $row ) {
			if ( self::is_blank( $row ) ) {
				continue;
			}

			$line   = 'phone' === ( $row['type'] ?? '' ) ? self::phone( $row, $index, $seen ) : self::accessory( $row, $index );
			$errors = array_merge( $errors, $line['errors'] );
			// Union, not array_merge: IMEIs are numeric keys and must not be renumbered.
			$seen = $seen + array_fill_keys( $line['imeis'], true );

			unset( $line['errors'] );
			$lines[] = $line;
		}

		$units = (int) array_sum( array_column( $lines, 'quantity' ) );

		if ( array() === $lines ) {
			$errors['lines'] = __( 'Add at least one phone or accessory.', 'codereyes-mobile-shop' );
		} elseif ( count( $lines ) > self::MAX_LINES || $units > self::MAX_UNITS ) {
			/* translators: 1: maximum lines, 2: maximum units. */
			$errors['lines'] = sprintf( __( 'Split this into smaller purchases: up to %1$d lines and %2$d units each.', 'codereyes-mobile-shop' ), self::MAX_LINES, self::MAX_UNITS );
		}

		return array(
			'lines'  => $lines,
			'errors' => $errors,
			'units'  => $units,
			'total'  => (int) array_sum( array_column( $lines, 'total' ) ),
		);
	}

	/**
	 * Phones of one model, one per IMEI.
	 *
	 * @param array               $row   Submitted line.
	 * @param int                 $index Line index.
	 * @param array<string, bool> $seen  IMEIs on earlier lines.
	 * @return array
	 */
	private static function phone( array $row, int $index, array $seen ): array {
		$errors = array();
		$key    = static function ( string $field ) use ( $index ): string {
			return 'lines-' . $index . '-' . $field;
		};

		foreach ( array( 'brand', 'model' ) as $field ) {
			if ( '' === self::text( $row, $field ) ) {
				/* translators: %d: line number. */
				$errors[ $key( $field ) ] = sprintf( 'brand' === $field ? __( 'Line %d: enter the brand.', 'codereyes-mobile-shop' ) : __( 'Line %d: enter the model.', 'codereyes-mobile-shop' ), $index + 1 );
			}
		}

		list( $imeis, $imei_error ) = self::imeis( (string) ( $row['imeis'] ?? '' ), $seen, $index );

		if ( '' !== $imei_error ) {
			$errors[ $key( 'imeis' ) ] = $imei_error;
		}

		$prices = self::prices( $row, $index );
		$grade  = in_array( $row['grade'] ?? '', array_keys( Cmshop_Item::grades() ), true ) ? (string) $row['grade'] : 'new';

		return array(
			'type'     => Cmshop_Item::TYPE_PHONE,
			'item_id'  => 0,
			'imeis'    => $imeis,
			'quantity' => count( $imeis ),
			'cost'     => $prices['cost'],
			'total'    => $prices['cost'] * count( $imeis ),
			'errors'   => array_merge( $errors, $prices['errors'] ),
			'fields'   => array(
				'item_type'  => Cmshop_Item::TYPE_PHONE,
				'brand'      => self::text( $row, 'brand' ),
				'model'      => self::text( $row, 'model' ),
				'storage'    => self::text( $row, 'storage' ),
				'color'      => self::text( $row, 'color' ),
				'grade'      => $grade,
				'cost_price' => Cmshop_Money::from_cents( $prices['cost'] ),
				'sale_price' => Cmshop_Money::from_cents( $prices['sale'] ),
			),
		);
	}

	/**
	 * Accessories: restock an existing item or add a new one.
	 *
	 * @param array $row   Submitted line.
	 * @param int   $index Line index.
	 * @return array
	 */
	private static function accessory( array $row, int $index ): array {
		$errors   = array();
		$item_id  = absint( $row['item_id'] ?? 0 );
		$quantity = trim( Cmshop_Digits::to_ascii( (string) ( $row['quantity'] ?? '' ) ) );
		$count    = 1 === preg_match( '/^\d{1,5}$/', $quantity ) ? (int) $quantity : 0;

		if ( 0 === $item_id && '' === self::text( $row, 'name' ) ) {
			/* translators: %d: line number. */
			$errors[ 'lines-' . $index . '-name' ] = sprintf( __( 'Line %d: enter the accessory name or pick one from stock.', 'codereyes-mobile-shop' ), $index + 1 );
		}

		if ( $count < 1 || $count > self::MAX_QUANTITY ) {
			/* translators: 1: line number, 2: maximum quantity. */
			$errors[ 'lines-' . $index . '-quantity' ] = sprintf( __( 'Line %1$d: enter how many, from 1 to %2$d.', 'codereyes-mobile-shop' ), $index + 1, self::MAX_QUANTITY );
			$count                                     = 0;
		}

		$prices = self::prices( $row, $index );

		return array(
			'type'     => Cmshop_Item::TYPE_ACCESSORY,
			'item_id'  => $item_id,
			'imeis'    => array(),
			'quantity' => $count,
			'cost'     => $prices['cost'],
			'total'    => $prices['cost'] * $count,
			'errors'   => array_merge( $errors, $prices['errors'] ),
			'fields'   => array(
				'item_type'  => Cmshop_Item::TYPE_ACCESSORY,
				'name'       => self::text( $row, 'name' ),
				'sku'        => self::text( $row, 'sku' ),
				'quantity'   => (string) $count,
				'cost_price' => Cmshop_Money::from_cents( $prices['cost'] ),
				'sale_price' => Cmshop_Money::from_cents( $prices['sale'] ),
			),
		);
	}

	/**
	 * Scanned IMEIs: one per line (or comma), checked and not repeated.
	 *
	 * @param string              $text  Textarea contents.
	 * @param array<string, bool> $seen  IMEIs on earlier lines.
	 * @param int                 $index Line index.
	 * @return array{0: string[], 1: string} IMEIs and an error ('' when fine).
	 */
	private static function imeis( string $text, array $seen, int $index ): array {
		$imeis = array();
		$bad   = array();
		$twice = array();

		foreach ( preg_split( self::SEPARATORS, $text ) as $part ) {
			$imei = Cmshop_Imei::normalize( $part );

			if ( '' === $imei ) {
				continue;
			}

			if ( ! Cmshop_Imei::is_valid( $imei ) ) {
				$bad[] = $imei;
			} elseif ( isset( $seen[ $imei ] ) || in_array( $imei, $imeis, true ) ) {
				$twice[] = $imei;
			} else {
				$imeis[] = $imei;
			}
		}

		if ( array() !== $bad ) {
			/* translators: 1: line number, 2: list of IMEIs. */
			return array( $imeis, sprintf( __( 'Line %1$d: not valid IMEIs: %2$s. Check the 15 digits.', 'codereyes-mobile-shop' ), $index + 1, implode( ', ', $bad ) ) );
		}

		if ( array() !== $twice ) {
			/* translators: 1: line number, 2: list of IMEIs. */
			return array( $imeis, sprintf( __( 'Line %1$d: scanned twice: %2$s.', 'codereyes-mobile-shop' ), $index + 1, implode( ', ', $twice ) ) );
		}

		if ( array() === $imeis ) {
			/* translators: %d: line number. */
			return array( $imeis, sprintf( __( 'Line %d: scan or type at least one IMEI, one per line.', 'codereyes-mobile-shop' ), $index + 1 ) );
		}

		return array( $imeis, '' );
	}

	/**
	 * Cost and selling price, both required.
	 *
	 * @param array $row   Submitted line.
	 * @param int   $index Line index.
	 * @return array{cost: int, sale: int, errors: array<string,string>}
	 */
	private static function prices( array $row, int $index ): array {
		$errors = array();
		$cents  = array();

		foreach ( array( 'cost_price', 'sale_price' ) as $field ) {
			$parsed = Cmshop_Money::parse( (string) ( $row[ $field ] ?? '' ) );

			if ( null === $parsed ) {
				/* translators: %d: line number. */
				$errors[ 'lines-' . $index . '-' . $field ] = sprintf( 'cost_price' === $field ? __( 'Line %d: enter what you paid for each, e.g. 15000.', 'codereyes-mobile-shop' ) : __( 'Line %d: enter the selling price, e.g. 18500.', 'codereyes-mobile-shop' ), $index + 1 );
			}

			$cents[ $field ] = null === $parsed ? 0 : Cmshop_Money::to_cents( $parsed );
		}

		return array(
			'cost'   => $cents['cost_price'],
			'sale'   => $cents['sale_price'],
			'errors' => $errors,
		);
	}

	/**
	 * Whether a line was left empty.
	 *
	 * @param array $row Submitted line.
	 * @return bool
	 */
	private static function is_blank( array $row ): bool {
		$filled = array_diff_key( $row, array_flip( array( 'type', 'grade', 'item_id' ) ) );

		return 0 === absint( $row['item_id'] ?? 0 ) && '' === trim( implode( '', array_map( 'strval', $filled ) ) );
	}

	/**
	 * Trimmed, single-line text.
	 *
	 * @param array  $row   Submitted line.
	 * @param string $field Field.
	 * @return string
	 */
	private static function text( array $row, string $field ): string {
		return mb_substr( trim( sanitize_text_field( (string) ( $row[ $field ] ?? '' ) ) ), 0, self::TEXT_LIMIT );
	}
}
