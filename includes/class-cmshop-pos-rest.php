<?php
/**
 * REST routes for the full-screen POS: product grid, customer lookup, held sales.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * GET  cmshop/v1/pos/catalog           Brands and product tiles (no cost prices).
 * GET  cmshop/v1/pos/customer?phone=   A customer and their history, by phone.
 * GET  cmshop/v1/pos/held              Sales this cashier has on hold.
 * POST cmshop/v1/pos/held              Hold a sale.
 * DELETE cmshop/v1/pos/held/{id}       Resume or discard a held sale.
 */
final class Cmshop_Pos_Rest {

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
	 * Sales.
	 *
	 * @var Cmshop_Sale_Repository
	 */
	private $sales;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Item_Repository     $items     Items.
	 * @param Cmshop_Customer_Repository $customers Customers.
	 * @param Cmshop_Sale_Repository     $sales     Sales.
	 */
	public function __construct( Cmshop_Item_Repository $items, Cmshop_Customer_Repository $customers, Cmshop_Sale_Repository $sales ) {
		$this->items     = $items;
		$this->customers = $customers;
		$this->sales     = $sales;
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
		$sell = array( Cmshop_Rest::class, 'can_sell' );

		register_rest_route(
			Cmshop_Rest::NAMESPACE,
			'/pos/catalog',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'catalog' ),
				'permission_callback' => $sell,
			)
		);

		register_rest_route(
			Cmshop_Rest::NAMESPACE,
			'/pos/customer',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'customer' ),
				'permission_callback' => $sell,
				'args'                => array(
					'phone' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			Cmshop_Rest::NAMESPACE,
			'/pos/held',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'held' ),
					'permission_callback' => $sell,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'hold' ),
					'permission_callback' => $sell,
					'args'                => array(
						'label' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'cart'  => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			Cmshop_Rest::NAMESPACE,
			'/pos/held/(?P<id>[A-Za-z0-9]{12})',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unhold' ),
				'permission_callback' => $sell,
			)
		);
	}

	/**
	 * Brands and tiles for everything sellable.
	 *
	 * @return WP_REST_Response
	 */
	public function catalog(): WP_REST_Response {
		$tiles = Cmshop_Pos_Catalog::build( $this->items->sellable() );

		return Cmshop_Rest::envelope(
			array(
				'brands' => Cmshop_Pos_Catalog::brands( $tiles ),
				'tiles'  => array_map( array( $this, 'with_image' ), $tiles ),
			)
		);
	}

	/**
	 * A customer and their history, by exact phone number.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function customer( WP_REST_Request $request ): WP_REST_Response {
		$phone    = Cmshop_Phone_Number::normalize( (string) $request->get_param( 'phone' ) );
		$customer = Cmshop_Phone_Number::is_valid( $phone ) ? $this->customers->find_by_phone( $phone ) : null;

		if ( null === $customer ) {
			return Cmshop_Rest::envelope( null );
		}

		$stats = $this->sales->customer_stats( (int) $customer['id'] );

		return Cmshop_Rest::envelope(
			array(
				'id'         => (int) $customer['id'],
				'name'       => (string) $customer['name'],
				'phone'      => (string) $customer['phone'],
				'sales'      => $stats['sales'],
				'spent_text' => Cmshop_Settings::money( $stats['spent'] ),
				'last_text'  => '' === $stats['last'] ? '' : (string) mysql2date( (string) get_option( 'date_format' ), $stats['last'] ),
				'due'        => $stats['due'],
				'due_text'   => Cmshop_Settings::money( $stats['due'] ),
			)
		);
	}

	/**
	 * This cashier's held sales.
	 *
	 * @return WP_REST_Response
	 */
	public function held(): WP_REST_Response {
		return Cmshop_Rest::envelope( Cmshop_Held_Sales::all( get_current_user_id() ) );
	}

	/**
	 * Hold a sale.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function hold( WP_REST_Request $request ): WP_REST_Response {
		try {
			$entry = Cmshop_Held_Sales::hold( get_current_user_id(), (string) $request->get_param( 'label' ), (array) $request->get_param( 'cart' ) );
		} catch ( InvalidArgumentException $error ) {
			return Cmshop_Rest::envelope( null, $error->getMessage(), 422 );
		}

		return Cmshop_Rest::envelope( $entry, null, 201 );
	}

	/**
	 * Resume or discard a held sale.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function unhold( WP_REST_Request $request ): WP_REST_Response {
		$removed = Cmshop_Held_Sales::remove( get_current_user_id(), (string) $request->get_param( 'id' ) );

		return $removed ? Cmshop_Rest::envelope( true ) : Cmshop_Rest::envelope( null, __( 'That held sale was not found.', 'codereyes-mobile-shop' ), 404 );
	}

	/**
	 * A tile with its photo URL and formatted prices.
	 *
	 * @param array $tile Tile.
	 * @return array
	 */
	private function with_image( array $tile ): array {
		$image = $tile['image_id'] > 0 ? wp_get_attachment_image_url( $tile['image_id'], 'thumbnail' ) : false;
		$price = 'model' === $tile['kind'] ? $tile['price_min'] : $tile['price'];

		return array_merge(
			$tile,
			array(
				'image'      => false === $image ? '' : $image,
				'price_text' => 'model' === $tile['kind'] && $tile['price_min'] !== $tile['price_max']
					/* translators: %s: lowest price. */
					? sprintf( __( 'From %s', 'codereyes-mobile-shop' ), Cmshop_Settings::money( $price ) )
					: Cmshop_Settings::money( $price ),
			)
		);
	}
}
