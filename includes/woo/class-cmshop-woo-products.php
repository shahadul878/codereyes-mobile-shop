<?php
/**
 * Publishes stock items as WooCommerce products and keeps their stock in step.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * One stock item ↔ one simple WooCommerce product.
 */
final class Cmshop_Woo_Products {

	public const META_ITEM_ID = '_cmshop_item_id';

	/**
	 * Items.
	 *
	 * @var Cmshop_Item_Repository
	 */
	private $items;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Item_Repository $items Items.
	 */
	public function __construct( Cmshop_Item_Repository $items ) {
		$this->items = $items;
	}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( Cmshop_Sales_Service::STOCK_CHANGED, array( $this, 'on_stock_changed' ), 10, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_condition_badge' ), 25 );
	}

	/**
	 * Create or update the product for an item.
	 *
	 * @param array $item Item row.
	 * @return int Product ID.
	 * @throws RuntimeException When WooCommerce is missing or the product cannot be saved.
	 */
	public function publish( array $item ): int {
		if ( ! function_exists( 'wc_get_product' ) ) {
			throw new RuntimeException( 'WooCommerce is not active.' );
		}

		$product = $this->product_for( $item );

		$product->set_name( Cmshop_Item::display_name( $item ) );
		$product->set_regular_price( (string) $item['sale_price'] );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( Cmshop_Item::STATUS_SOLD === $item['status'] ? 0 : (int) $item['quantity'] );
		$product->set_sold_individually( Cmshop_Item::TYPE_PHONE === $item['item_type'] );
		$product->set_attributes( $this->attributes( $item ) );
		$product->set_category_ids( $this->category_ids( (string) $item['item_type'] ) );
		$this->set_sku( $product, $item );

		if ( 0 === $product->get_id() ) {
			$product->set_status( 'publish' );
		}

		$product->update_meta_data( self::META_ITEM_ID, (int) $item['id'] );
		$product_id = (int) $product->save();

		if ( $product_id <= 0 ) {
			throw new RuntimeException( 'Product could not be saved.' );
		}

		if ( (int) $item['wc_product_id'] !== $product_id ) {
			$this->items->update( (int) $item['id'], array( 'wc_product_id' => $product_id ) );
		}

		return $product_id;
	}

	/**
	 * Take an item off the website (the product goes to the bin).
	 *
	 * @param array $item Item row.
	 */
	public function unpublish( array $item ): void {
		$product_id = (int) $item['wc_product_id'];

		if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );

			if ( $product ) {
				$product->delete( false );
			}
		}

		$this->items->update( (int) $item['id'], array( 'wc_product_id' => 0 ) );
	}

	/**
	 * Push stock levels to WooCommerce after a counter sale or void.
	 *
	 * Online orders are skipped: WooCommerce already changed its own stock.
	 *
	 * @param int[]  $item_ids Items whose stock changed.
	 * @param string $source   store|online.
	 */
	public function on_stock_changed( array $item_ids, string $source ): void {
		if ( Cmshop_Sale_Repository::CHANNEL_ONLINE === $source || ! Cmshop_Settings::woo_enabled() ) {
			return;
		}

		foreach ( array_unique( array_map( 'intval', $item_ids ) ) as $item_id ) {
			$item = $this->items->find( $item_id );

			if ( null !== $item && (int) $item['wc_product_id'] > 0 ) {
				$this->push_stock( $item );
			}
		}
	}

	/**
	 * Condition and battery health under the price on a product page.
	 */
	public function render_condition_badge(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$item = $this->items->find_by_product( (int) $product->get_id() );

		if ( null === $item || Cmshop_Item::TYPE_PHONE !== $item['item_type'] ) {
			return;
		}

		$parts = array( Cmshop_Item::label( Cmshop_Item::grades(), (string) $item['grade'] ) );

		if ( null !== $item['battery_health'] && '' !== $item['battery_health'] ) {
			/* translators: %d: battery health percentage. */
			$parts[] = sprintf( __( 'Battery health %d%%', 'codereyes-mobile-shop' ), (int) $item['battery_health'] );
		}

		$parts[] = __( 'IMEI checked', 'codereyes-mobile-shop' );

		printf( '<p class="cmshop-condition">%s</p>', esc_html( implode( ' · ', $parts ) ) );
	}

	/**
	 * Set the product's stock to the item's quantity.
	 *
	 * @param array $item Item row.
	 */
	private function push_stock( array $item ): void {
		$product = wc_get_product( (int) $item['wc_product_id'] );

		if ( ! $product ) {
			return;
		}

		$quantity = Cmshop_Item::STATUS_SOLD === $item['status'] ? 0 : (int) $item['quantity'];

		wc_update_product_stock( $product, $quantity, 'set' );
	}

	/**
	 * Existing linked product or a new simple product.
	 *
	 * @param array $item Item row.
	 * @return WC_Product
	 */
	private function product_for( array $item ): WC_Product {
		$product = (int) $item['wc_product_id'] > 0 ? wc_get_product( (int) $item['wc_product_id'] ) : null;

		return $product instanceof WC_Product && 'trash' !== $product->get_status() ? $product : new WC_Product_Simple();
	}

	/**
	 * Visible product attributes (shown in "Additional information").
	 *
	 * The IMEI is never published.
	 *
	 * @param array $item Item row.
	 * @return WC_Product_Attribute[]
	 */
	private function attributes( array $item ): array {
		$values = array(
			__( 'Brand', 'codereyes-mobile-shop' )     => (string) $item['brand'],
			__( 'Storage', 'codereyes-mobile-shop' )   => (string) $item['storage'],
			__( 'Colour', 'codereyes-mobile-shop' )    => (string) $item['color'],
			__( 'Condition', 'codereyes-mobile-shop' ) => Cmshop_Item::TYPE_PHONE === $item['item_type'] ? Cmshop_Item::label( Cmshop_Item::grades(), (string) $item['grade'] ) : '',
		);

		if ( null !== $item['battery_health'] && '' !== $item['battery_health'] ) {
			$values[ __( 'Battery health', 'codereyes-mobile-shop' ) ] = (int) $item['battery_health'] . '%';
		}

		$attributes = array();
		$position   = 0;

		foreach ( array_filter( $values, 'strlen' ) as $name => $value ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_name( $name );
			$attribute->set_options( array( $value ) );
			$attribute->set_position( $position++ );
			$attribute->set_visible( true );
			$attributes[] = $attribute;
		}

		return $attributes;
	}

	/**
	 * Category "Phones" or "Accessories", created on first use.
	 *
	 * @param string $type Item type.
	 * @return int[]
	 */
	private function category_ids( string $type ): array {
		$name = Cmshop_Item::TYPE_PHONE === $type ? __( 'Phones', 'codereyes-mobile-shop' ) : __( 'Accessories', 'codereyes-mobile-shop' );
		$term = term_exists( $name, 'product_cat' );

		if ( ! $term ) {
			$term = wp_insert_term( $name, 'product_cat' );
		}

		return is_array( $term ) && isset( $term['term_id'] ) ? array( (int) $term['term_id'] ) : array();
	}

	/**
	 * Use the accessory SKU, or "MS-{id}"; skip when another product already uses it.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $item    Item row.
	 */
	private function set_sku( WC_Product $product, array $item ): void {
		$sku = '' !== (string) $item['sku'] ? (string) $item['sku'] : 'MS-' . (int) $item['id'];

		try {
			$product->set_sku( $sku );
		} catch ( WC_Data_Exception $error ) {
			// Duplicate SKU: leave the product without one rather than fail the publish.
			unset( $error );
		}
	}
}
