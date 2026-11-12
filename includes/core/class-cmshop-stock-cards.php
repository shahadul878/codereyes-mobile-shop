<?php
/**
 * Cards for the public "phones in stock" listing.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Turns stock rows into what customers see: one card per new-phone model
 * (with its colours, storage sizes and price range) and one per used phone
 * or accessory. Cards never carry cost prices or IMEIs.
 */
final class Cmshop_Stock_Cards {

	/**
	 * Cards for items in stock, newest stock first.
	 *
	 * @param array[] $rows Stock rows.
	 * @return array[]
	 */
	public static function build( array $rows ): array {
		$cards = array();

		foreach ( self::oldest_first( $rows ) as $row ) {
			if ( ! self::in_stock( $row ) ) {
				continue;
			}

			$grouped = Cmshop_Model_Listing::is_grouped( $row );
			$key     = $grouped ? 'model:' . Cmshop_Model_Listing::model_key( $row ) : 'item:' . (int) $row['id'];

			$cards[ $key ] = isset( $cards[ $key ] ) ? self::add( $cards[ $key ], $row ) : self::start( $row, $grouped );
		}

		$cards = array_values( $cards );

		usort(
			$cards,
			static function ( array $a, array $b ): int {
				return $b['newest'] <=> $a['newest'];
			}
		);

		return $cards;
	}

	/**
	 * One page of cards.
	 *
	 * @param array[] $cards    All cards.
	 * @param int     $page     Page asked for (clamped to the pages there are).
	 * @param int     $per_page Cards per page.
	 * @return array{cards: array[], page: int, pages: int}
	 */
	public static function page( array $cards, int $page, int $per_page ): array {
		$per_page = max( 1, $per_page );
		$pages    = max( 1, (int) ceil( count( $cards ) / $per_page ) );
		$page     = min( max( 1, $page ), $pages );

		return array(
			'cards' => array_slice( $cards, ( $page - 1 ) * $per_page, $per_page ),
			'page'  => $page,
			'pages' => $pages,
		);
	}

	/**
	 * A new card from its first row.
	 *
	 * @param array $row     Stock row.
	 * @param bool  $grouped Whether the card stands for a new-phone model.
	 * @return array
	 */
	private static function start( array $row, bool $grouped ): array {
		$price    = (string) $row['sale_price'];
		$is_phone = Cmshop_Item::TYPE_PHONE === $row['item_type'] && '' !== trim( (string) $row['model'] );

		return array(
			'kind'           => $grouped ? 'model' : 'item',
			// Phones are titled by brand and model; storage and colour show as tags.
			'title'          => $is_phone ? Cmshop_Model_Listing::title( $row ) : (string) $row['name'],
			'brand'          => trim( (string) $row['brand'] ),
			'item_type'      => (string) $row['item_type'],
			'grade'          => (string) $row['grade'],
			'battery_health' => null === $row['battery_health'] || '' === $row['battery_health'] ? null : (int) $row['battery_health'],
			'stock'          => (int) $row['quantity'],
			'colors'         => self::values( array(), (string) $row['color'] ),
			'storages'       => self::values( array(), (string) $row['storage'] ),
			'price_min'      => $price,
			'price_max'      => $price,
			'image_id'       => (int) ( $row['image_id'] ?? 0 ),
			'product_id'     => (int) ( $row['wc_product_id'] ?? 0 ),
			'newest'         => (int) $row['id'],
		);
	}

	/**
	 * Add another phone of the same model to its card.
	 *
	 * @param array $card Card so far.
	 * @param array $row  Stock row, visited oldest first.
	 * @return array
	 */
	private static function add( array $card, array $row ): array {
		$price = (string) $row['sale_price'];
		$cents = Cmshop_Money::to_cents( $price );

		return array_merge(
			$card,
			array(
				'stock'      => $card['stock'] + (int) $row['quantity'],
				'colors'     => self::values( $card['colors'], (string) $row['color'] ),
				'storages'   => self::values( $card['storages'], (string) $row['storage'] ),
				'price_min'  => $cents < Cmshop_Money::to_cents( $card['price_min'] ) ? $price : $card['price_min'],
				'price_max'  => $cents > Cmshop_Money::to_cents( $card['price_max'] ) ? $price : $card['price_max'],
				'image_id'   => 0 === $card['image_id'] ? (int) ( $row['image_id'] ?? 0 ) : $card['image_id'],
				'product_id' => 0 === $card['product_id'] ? (int) ( $row['wc_product_id'] ?? 0 ) : $card['product_id'],
				'newest'     => max( $card['newest'], (int) $row['id'] ),
			)
		);
	}

	/**
	 * A list with one more distinct value (ignoring case); empty values are skipped.
	 *
	 * @param string[] $values Values so far.
	 * @param string   $value  Value to add.
	 * @return string[]
	 */
	private static function values( array $values, string $value ): array {
		$value = trim( $value );
		$known = array_map( 'mb_strtolower', $values );

		return '' === $value || in_array( mb_strtolower( $value ), $known, true ) ? $values : array_merge( $values, array( $value ) );
	}

	/**
	 * Whether a row can be bought now.
	 *
	 * @param array $row Stock row.
	 * @return bool
	 */
	private static function in_stock( array $row ): bool {
		return Cmshop_Item::STATUS_IN_STOCK === ( $row['status'] ?? '' ) && (int) ( $row['quantity'] ?? 0 ) > 0;
	}

	/**
	 * Rows sorted by ID, without changing the input.
	 *
	 * @param array[] $rows Rows.
	 * @return array[]
	 */
	private static function oldest_first( array $rows ): array {
		$sorted = array_values( $rows );

		usort(
			$sorted,
			static function ( array $a, array $b ): int {
				return (int) $a['id'] <=> (int) $b['id'];
			}
		);

		return $sorted;
	}
}
