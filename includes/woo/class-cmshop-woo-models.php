<?php
/**
 * Lists every new phone of a model as one WooCommerce variable product,
 * with an option per storage and colour.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * One model ↔ one variable product; one storage/colour ↔ one variation whose
 * stock is the number of matching phones in the shop.
 */
final class Cmshop_Woo_Models {

	public const META_MODEL   = '_cmshop_model_key';
	public const META_VARIANT = '_cmshop_variant_key';

	private const LIVE_STATUSES = array( 'publish', 'draft', 'pending', 'private' );

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
	 * Whether a product is a model listing made by this plugin.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function is_model_product( WC_Product $product ): bool {
		return $product->is_type( 'variable' ) && '' !== (string) $product->get_meta( self::META_MODEL );
	}

	/**
	 * Create or update the model's product from all its new phones.
	 *
	 * @param array $item Any new phone of the model.
	 * @return int Product ID.
	 * @throws RuntimeException When the product cannot be saved.
	 */
	public function publish( array $item ): int {
		$units    = $this->items->model_units( $item );
		$variants = Cmshop_Model_Listing::variants( $units );
		$used     = Cmshop_Model_Listing::attributes( $variants );
		$parent   = $this->find_parent( $item );
		$parent   = null === $parent ? new WC_Product_Variable() : $parent;

		$parent->set_name( Cmshop_Model_Listing::title( $item ) );
		$parent->set_manage_stock( false );
		$parent->set_attributes( $this->parent_attributes( $item, $variants, $used ) );
		$parent->set_default_attributes( $this->single_choices( $variants, $used ) );
		$parent->set_category_ids( Cmshop_Woo_Products::category_ids( Cmshop_Item::TYPE_PHONE ) );
		$parent->update_meta_data( self::META_MODEL, Cmshop_Model_Listing::model_key( $item ) );

		if ( 0 === $parent->get_id() ) {
			$parent->set_status( 'publish' );
		}

		$parent_id = (int) $parent->save();

		if ( $parent_id <= 0 ) {
			throw new RuntimeException( 'Model product could not be saved.' );
		}

		$this->save_variations( $parent_id, $variants, $used );
		WC_Product_Variable::sync( $parent_id );
		wc_delete_product_transients( $parent_id );

		$unlinked = array_filter(
			$units,
			static function ( array $unit ) use ( $parent_id ): bool {
				return (int) $unit['wc_product_id'] !== $parent_id;
			}
		);
		$this->items->link_product( array_column( $unlinked, 'id' ), $parent_id );

		return $parent_id;
	}

	/**
	 * Bring a listed model up to date (stock, prices, options).
	 *
	 * Does nothing when the model is not on the website. A listing whose
	 * phones have all been deleted or moved to another model goes to the bin.
	 *
	 * @param array $item Any phone of the model (as it was, for a phone that moved).
	 * @throws RuntimeException When the product cannot be saved.
	 */
	public function refresh( array $item ): void {
		$parent = $this->find_parent( $item );

		if ( null === $parent ) {
			return;
		}

		if ( array() === $this->items->model_units( $item ) ) {
			$parent->delete( false );
			return;
		}

		$this->publish( $item );
	}

	/**
	 * Take the model off the website and unlink its phones.
	 *
	 * @param array $item Any phone of the model.
	 */
	public function unpublish( array $item ): void {
		$parent     = $this->find_parent( $item );
		$product_id = null === $parent ? (int) $item['wc_product_id'] : $parent->get_id();

		if ( null !== $parent ) {
			$parent->delete( false );
		}

		$this->items->link_product( $this->items->ids_for_product( $product_id ), 0 );
	}

	/**
	 * Phones for an order of a variation: the oldest in stock first.
	 *
	 * @param WC_Product $variation Ordered variation.
	 * @param int        $quantity  Phones ordered.
	 * @return int[]|null Item IDs, or null when the variation is not a model option.
	 */
	public function allocate( WC_Product $variation, int $quantity ): ?array {
		$key  = (string) $variation->get_meta( self::META_VARIANT );
		$unit = $this->items->find_by_product( (int) $variation->get_parent_id() );

		if ( '' === $key || null === $unit ) {
			return null;
		}

		return Cmshop_Model_Listing::allocate( $this->items->model_units( $unit ), $key, $quantity );
	}

	/**
	 * Create, update or remove variations so they match the options.
	 *
	 * @param int     $parent_id Parent product.
	 * @param array[] $variants  From Cmshop_Model_Listing::variants().
	 * @param array   $used      Attributes in use.
	 */
	private function save_variations( int $parent_id, array $variants, array $used ): void {
		$existing = $this->existing_variations( $parent_id );

		foreach ( $variants as $variant ) {
			$variation = $existing[ $variant['key'] ] ?? new WC_Product_Variation();
			unset( $existing[ $variant['key'] ] );

			$variation->set_parent_id( $parent_id );
			$variation->set_attributes( $this->variation_attributes( $variant, $used ) );
			$variation->set_regular_price( $variant['price'] );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( $variant['stock'] );
			$variation->set_status( 'publish' );
			$variation->update_meta_data( self::META_VARIANT, $variant['key'] );
			$variation->save();
		}

		// Options with no phones left (edited or deleted) disappear.
		foreach ( $existing as $variation ) {
			$variation->delete( true );
		}
	}

	/**
	 * Current variations keyed by option.
	 *
	 * @param int $parent_id Parent product.
	 * @return array<string, WC_Product_Variation>
	 */
	private function existing_variations( int $parent_id ): array {
		$parent = wc_get_product( $parent_id );
		$found  = array();

		foreach ( $parent ? $parent->get_children() : array() as $child_id ) {
			$variation = wc_get_product( $child_id );

			if ( $variation instanceof WC_Product_Variation ) {
				$found[ (string) $variation->get_meta( self::META_VARIANT ) ] = $variation;
			}
		}

		return $found;
	}

	/**
	 * Brand and condition (shown), plus storage/colour (options).
	 *
	 * @param array   $item     Any phone of the model.
	 * @param array[] $variants Options.
	 * @param array   $used     Attributes in use.
	 * @return WC_Product_Attribute[]
	 */
	private function parent_attributes( array $item, array $variants, array $used ): array {
		$attributes = array(
			$this->attribute( __( 'Brand', 'codereyes-mobile-shop' ), array( trim( (string) $item['brand'] ) ), false, 0 ),
			$this->attribute( __( 'Condition', 'codereyes-mobile-shop' ), array( Cmshop_Item::label( Cmshop_Item::grades(), 'new' ) ), false, 1 ),
		);
		$position   = 2;

		foreach ( $used as $name ) {
			$options      = array_values( array_unique( array_map( array( 'Cmshop_Model_Listing', 'option' ), array_column( $variants, $name ) ) ) );
			$attributes[] = $this->attribute( $this->label( $name ), $options, true, $position++ );
		}

		return array_values(
			array_filter(
				$attributes,
				static function ( WC_Product_Attribute $attribute ): bool {
					return array() !== array_filter( $attribute->get_options(), 'strlen' );
				}
			)
		);
	}

	/**
	 * Pre-select any attribute with only one value (e.g. every phone is 128GB),
	 * so customers only choose what actually differs.
	 *
	 * @param array[] $variants Options.
	 * @param array   $used     Attributes in use.
	 * @return array<string,string>
	 */
	private function single_choices( array $variants, array $used ): array {
		$defaults = array();

		foreach ( $used as $name ) {
			$options = array_values( array_unique( array_map( array( 'Cmshop_Model_Listing', 'option' ), array_column( $variants, $name ) ) ) );

			if ( 1 === count( $options ) ) {
				$defaults[ sanitize_title( $this->label( $name ) ) ] = $options[0];
			}
		}

		return $defaults;
	}

	/**
	 * The option values a variation stands for, keyed as WooCommerce expects.
	 *
	 * @param array $variant Option.
	 * @param array $used    Attributes in use.
	 * @return array<string,string>
	 */
	private function variation_attributes( array $variant, array $used ): array {
		$values = array();

		foreach ( $used as $name ) {
			$values[ sanitize_title( $this->label( $name ) ) ] = Cmshop_Model_Listing::option( (string) $variant[ $name ] );
		}

		return $values;
	}

	/**
	 * One custom (non-taxonomy) product attribute.
	 *
	 * @param string   $name         Label.
	 * @param string[] $options      Values.
	 * @param bool     $is_variation Whether customers choose from it.
	 * @param int      $position     Display order.
	 * @return WC_Product_Attribute
	 */
	private function attribute( string $name, array $options, bool $is_variation, int $position ): WC_Product_Attribute {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( $name );
		$attribute->set_options( $options );
		$attribute->set_position( $position );
		$attribute->set_visible( true );
		$attribute->set_variation( $is_variation );

		return $attribute;
	}

	/**
	 * Customer-facing name of an option attribute.
	 *
	 * @param string $name storage|color.
	 * @return string
	 */
	private function label( string $name ): string {
		return Cmshop_Model_Listing::ATTRIBUTE_STORAGE === $name ? __( 'Storage', 'codereyes-mobile-shop' ) : __( 'Colour', 'codereyes-mobile-shop' );
	}

	/**
	 * The model's live product (not in the bin), if listed.
	 *
	 * @param array $item Any phone of the model.
	 * @return WC_Product_Variable|null
	 */
	private function find_parent( array $item ): ?WC_Product_Variable {
		$ids = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => self::LIVE_STATUSES,
				'fields'      => 'ids',
				'numberposts' => 1,
				'meta_key'    => self::META_MODEL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One lookup when a model's stock changes.
				'meta_value'  => Cmshop_Model_Listing::model_key( $item ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
			)
		);

		$product = array() === $ids ? null : wc_get_product( (int) $ids[0] );

		return $product instanceof WC_Product_Variable ? $product : null;
	}
}
