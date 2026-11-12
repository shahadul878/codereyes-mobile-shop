<?php
/**
 * REST endpoints used by the point-of-sale screen.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Routes: cmshop/v1/pos/items, cmshop/v1/pos/customers, cmshop/v1/pos/checkout.
 */
final class Cmshop_Rest {

	public const NAMESPACE = 'cmshop/v1';

	private const MIN_SEARCH = 2;
	private const MAX_SEARCH = 64;

	/**
	 * Items.
	 *
	 * @var Cmshop_Item_Repository
	 */
	private $items;

	/**
	 * Customers.
	 *
	 * @var Cmshop_Customer_Repository
	 */
	private $customers;

	/**
	 * Sales service.
	 *
	 * @var Cmshop_Sales_Service
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Item_Repository     $items     Items.
	 * @param Cmshop_Customer_Repository $customers Customers.
	 * @param Cmshop_Sales_Service       $service   Sales service.
	 */
	public function __construct( Cmshop_Item_Repository $items, Cmshop_Customer_Repository $customers, Cmshop_Sales_Service $service ) {
		$this->items     = $items;
		$this->customers = $customers;
		$this->service   = $service;
	}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function routes(): void {
		$search_args = array(
			'search' => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		register_rest_route(
			self::NAMESPACE,
			'/pos/items',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_items' ),
				'permission_callback' => array( self::class, 'can_sell' ),
				'args'                => $search_args,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/pos/customers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_customers' ),
				'permission_callback' => array( self::class, 'can_sell' ),
				'args'                => $search_args,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/pos/checkout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkout' ),
				'permission_callback' => array( self::class, 'can_sell' ),
				'args'                => array(
					'lines'    => array(
						'type'     => 'array',
						'required' => true,
						'maxItems' => Cmshop_Cart::MAX_LINES,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'item_id'    => array(
									'type'     => 'integer',
									'minimum'  => 1,
									'required' => true,
								),
								'quantity'   => array(
									'type'     => 'integer',
									'minimum'  => 1,
									'required' => true,
								),
								'unit_price' => array( 'type' => 'string' ),
							),
						),
					),
					'payments' => array(
						'type'     => 'array',
						'maxItems' => 5,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'method'    => array( 'type' => 'string' ),
								'amount'    => array( 'type' => 'string' ),
								'reference' => array( 'type' => 'string' ),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public static function can_sell(): bool {
		return current_user_can( Cmshop_Installer::CAPABILITY );
	}

	/**
	 * Sellable items matching a term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function search_items( WP_REST_Request $request ): WP_REST_Response {
		$term = $this->term( $request );

		if ( null === $term ) {
			return self::envelope( array() );
		}

		// A scanned IMEI may include separators; search by its digits.
		$imei = Cmshop_Imei::normalize( $term );
		$rows = $this->items->search_sellable( Cmshop_Imei::is_valid( $imei ) ? $imei : $term );

		return self::envelope( array_map( array( $this, 'item_json' ), $rows ) );
	}

	/**
	 * Customers matching a term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function search_customers( WP_REST_Request $request ): WP_REST_Response {
		$term = $this->term( $request );

		return self::envelope( null === $term ? array() : $this->customers->search( $term ) );
	}

	/**
	 * Record a counter sale.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function checkout( WP_REST_Request $request ): WP_REST_Response {
		$text = static function ( string $key ) use ( $request ): string {
			$value = $request->get_param( $key );

			return is_scalar( $value ) ? (string) $value : '';
		};

		try {
			$sale_id = $this->service->checkout(
				array(
					'lines'          => (array) $request->get_param( 'lines' ),
					'customer_name'  => $text( 'customer_name' ),
					'customer_phone' => $text( 'customer_phone' ),
					'discount'       => $text( 'discount' ),
					'amount_paid'    => $text( 'amount_paid' ),
					'payment_method' => sanitize_key( $text( 'payment_method' ) ),
					'payments'       => $this->payments( $request ),
					'notes'          => $text( 'notes' ),
					'channel'        => Cmshop_Sale_Repository::CHANNEL_STORE,
				),
				get_current_user_id()
			);
		} catch ( InvalidArgumentException $error ) {
			return self::envelope( null, $error->getMessage(), 422 );
		} catch ( RuntimeException $error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side log of a database failure.
			error_log( 'Codereyes Mobile Shop checkout failed: ' . $error->getMessage() );

			return self::envelope( null, __( 'The sale could not be saved. Nothing was charged to stock. Please try again.', 'codereyes-mobile-shop' ), 500 );
		}

		return self::envelope(
			array(
				'sale_id'     => $sale_id,
				'receipt_url' => admin_url( 'admin.php?page=cmshop-sales&action=view&id=' . $sale_id ),
			),
			null,
			201
		);
	}

	/**
	 * Split payment rows as plain strings, or null for a single-method sale.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array[]|null
	 */
	private function payments( WP_REST_Request $request ): ?array {
		$rows = $request->get_param( 'payments' );

		if ( ! is_array( $rows ) ) {
			return null;
		}

		return array_map(
			static function ( $row ): array {
				$row = is_array( $row ) ? $row : array();

				return array(
					'method'    => sanitize_key( (string) ( $row['method'] ?? '' ) ),
					'amount'    => sanitize_text_field( (string) ( $row['amount'] ?? '' ) ),
					'reference' => sanitize_text_field( (string) ( $row['reference'] ?? '' ) ),
				);
			},
			$rows
		);
	}

	/**
	 * Search term, or null when too short.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string|null
	 */
	private function term( WP_REST_Request $request ): ?string {
		$term = mb_substr( trim( (string) $request->get_param( 'search' ) ), 0, self::MAX_SEARCH );

		return mb_strlen( $term ) < self::MIN_SEARCH ? null : $term;
	}

	/**
	 * Item as the POS needs it (no cost price).
	 *
	 * @param array $row Item row.
	 * @return array
	 */
	private function item_json( array $row ): array {
		return array(
			'id'         => (int) $row['id'],
			'type'       => (string) $row['item_type'],
			'name'       => Cmshop_Item::display_name( $row ),
			'imei'       => (string) $row['imei1'],
			'sku'        => (string) $row['sku'],
			'grade'      => Cmshop_Item::label( Cmshop_Item::grades(), (string) $row['grade'] ),
			'quantity'   => (int) $row['quantity'],
			'status'     => (string) $row['status'],
			'sale_price' => (string) $row['sale_price'],
			'price_text' => Cmshop_Settings::money( (string) $row['sale_price'] ),
		);
	}

	/**
	 * Consistent response envelope.
	 *
	 * @param mixed       $data   Payload.
	 * @param string|null $error  Error message.
	 * @param int         $status HTTP status.
	 * @return WP_REST_Response
	 */
	public static function envelope( $data, ?string $error = null, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => null === $error,
				'data'    => $data,
				'error'   => $error,
			),
			$status
		);
	}
}
