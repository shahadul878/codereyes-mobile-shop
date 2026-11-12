<?php
/**
 * [cmshop_stock] — the phones (or accessories) in the shop right now.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Grid of what is in stock with photo, price, condition and options, plus
 * brand and condition filters. Works with or without WooCommerce.
 *
 * Attributes:
 *   type      phone (default), accessory or all
 *   brand     show one brand only, e.g. brand="Samsung"
 *   condition all (default), new or used
 *   per_page  cards per page, 1-48 (default 12)
 *   filters   yes (default) or no
 */
final class Cmshop_Stock_Shortcode {

	public const SHORTCODE = 'cmshop_stock';

	private const MAX_PER_PAGE     = 48;
	private const DEFAULT_PER_PAGE = 12;
	private const TYPES            = array( 'phone', 'accessory', 'all' );
	private const CONDITIONS       = array( 'all', 'new', 'used' );

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
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Shortcode output.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		wp_enqueue_style( 'cmshop-frontend' );

		$options   = $this->options( is_array( $atts ) ? $atts : array() );
		$condition = $options['filters'] ? $this->query_choice( 'cmshop_condition', self::CONDITIONS, $options['condition'] ) : $options['condition'];
		$rows      = $this->items->public_stock( 'all' === $options['type'] ? '' : $options['type'], 'all' === $condition ? '' : $condition );
		$brands    = $this->brands( $rows );
		$brand     = '' !== $options['brand'] ? $options['brand'] : $this->query_brand( $brands );
		$rows      = '' === $brand ? $rows : $this->only_brand( $rows, $brand );
		$page      = Cmshop_Stock_Cards::page( Cmshop_Stock_Cards::build( $rows ), $this->query_page(), $options['per_page'] );

		$view = array(
			'cards'       => $page['cards'],
			'page'        => $page['page'],
			'pages'       => $page['pages'],
			'brands'      => $brands,
			'brand'       => $brand,
			'condition'   => $condition,
			'show_brand'  => $options['filters'] && '' === $options['brand'] && count( $brands ) > 1,
			'show_cond'   => $options['filters'] && 'accessory' !== $options['type'],
			'shop_phone'  => (string) Cmshop_Settings::get( 'shop_phone' ),
			'type'        => $options['type'],
			'form_action' => remove_query_arg( array( 'cmshop_brand', 'cmshop_condition', 'cmshop_pg' ) ),
		);

		ob_start();
		include CMSHOP_DIR . 'views/frontend/stock.php';

		return (string) ob_get_clean();
	}

	/**
	 * Shortcode attributes, checked.
	 *
	 * @param array $atts Raw attributes.
	 * @return array{type: string, brand: string, condition: string, per_page: int, filters: bool}
	 */
	private function options( array $atts ): array {
		$atts = shortcode_atts(
			array(
				'type'      => 'phone',
				'brand'     => '',
				'condition' => 'all',
				'per_page'  => (string) self::DEFAULT_PER_PAGE,
				'filters'   => 'yes',
			),
			$atts,
			self::SHORTCODE
		);

		return array(
			'type'      => in_array( $atts['type'], self::TYPES, true ) ? $atts['type'] : 'phone',
			'brand'     => trim( sanitize_text_field( (string) $atts['brand'] ) ),
			'condition' => in_array( $atts['condition'], self::CONDITIONS, true ) ? $atts['condition'] : 'all',
			'per_page'  => max( 1, min( self::MAX_PER_PAGE, absint( $atts['per_page'] ) ) ),
			'filters'   => 'no' !== strtolower( (string) $atts['filters'] ),
		);
	}

	/**
	 * Brands among the rows, A to Z.
	 *
	 * @param array[] $rows Stock rows.
	 * @return string[]
	 */
	private function brands( array $rows ): array {
		$brands = array();

		foreach ( $rows as $row ) {
			$name = trim( (string) $row['brand'] );

			if ( '' !== $name ) {
				$brands[ mb_strtolower( $name ) ] = $brands[ mb_strtolower( $name ) ] ?? $name;
			}
		}

		natcasesort( $brands );

		return array_values( $brands );
	}

	/**
	 * Rows of one brand (ignoring case).
	 *
	 * @param array[] $rows  Stock rows.
	 * @param string  $brand Brand.
	 * @return array[]
	 */
	private function only_brand( array $rows, string $brand ): array {
		return array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $brand ): bool {
					return 0 === strcasecmp( trim( (string) $row['brand'] ), $brand );
				}
			)
		);
	}

	/**
	 * Brand picked in the filter, if it is one of the brands in stock.
	 *
	 * Read-only listing filters; no state changes, so no nonce.
	 *
	 * @param string[] $brands Brands in stock.
	 * @return string
	 */
	private function query_brand( array $brands ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$picked = isset( $_GET['cmshop_brand'] ) ? sanitize_text_field( wp_unslash( $_GET['cmshop_brand'] ) ) : '';

		foreach ( $brands as $brand ) {
			if ( 0 === strcasecmp( $brand, $picked ) ) {
				return $brand;
			}
		}

		return '';
	}

	/**
	 * A filter value from the URL, if allowed.
	 *
	 * @param string   $name     Query arg.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Value when missing or not allowed.
	 * @return string
	 */
	private function query_choice( string $name, array $allowed, string $fallback ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$value = isset( $_GET[ $name ] ) ? sanitize_key( wp_unslash( $_GET[ $name ] ) ) : '';

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Page number from the URL.
	 *
	 * @return int
	 */
	private function query_page(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		return isset( $_GET['cmshop_pg'] ) ? max( 1, absint( $_GET['cmshop_pg'] ) ) : 1;
	}
}
