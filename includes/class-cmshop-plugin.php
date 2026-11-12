<?php
/**
 * Loads the plugin and builds its services.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Service container and boot sequence.
 */
final class Cmshop_Plugin {

	/**
	 * Files loaded on every request, relative to includes/.
	 */
	private const CORE_FILES = array(
		'core/class-cmshop-digits.php',
		'core/class-cmshop-imei.php',
		'core/class-cmshop-money.php',
		'core/class-cmshop-phone-number.php',
		'core/class-cmshop-code.php',
		'core/class-cmshop-item.php',
		'core/class-cmshop-repair-status.php',
		'core/class-cmshop-validation-result.php',
		'core/class-cmshop-input.php',
		'core/class-cmshop-validator.php',
		'core/class-cmshop-cart.php',
		'core/class-cmshop-csv.php',
		'core/class-cmshop-query.php',
		'core/class-cmshop-invoice-format.php',
		'core/class-cmshop-model-listing.php',
		'core/class-cmshop-stock-cards.php',
		'core/class-cmshop-warranty.php',
		'core/class-cmshop-style.php',
		'core/class-cmshop-block-atts.php',
		'core/class-cmshop-payments.php',
		'core/class-cmshop-dashboard-math.php',
		'core/class-cmshop-pos-catalog.php',
		'core/class-cmshop-installments.php',
		'data/class-cmshop-installer.php',
		'data/class-cmshop-settings.php',
		'data/class-cmshop-repository.php',
		'data/class-cmshop-item-repository.php',
		'data/class-cmshop-customer-repository.php',
		'data/class-cmshop-sale-repository.php',
		'data/class-cmshop-repair-repository.php',
		'data/class-cmshop-payment-repository.php',
		'data/class-cmshop-migrations.php',
		'data/class-cmshop-held-sales.php',
		'data/class-cmshop-installment-repository.php',
		'services/class-cmshop-sales-service.php',
		'services/class-cmshop-dues-service.php',
		'class-cmshop-rest.php',
		'frontend/class-cmshop-rate-limit.php',
		'frontend/class-cmshop-repair-tracker.php',
		'frontend/class-cmshop-stock-shortcode.php',
		'frontend/class-cmshop-warranty-check.php',
		'frontend/class-cmshop-repair-request.php',
		'class-cmshop-pos-rest.php',
		'integrations/class-cmshop-blocks.php',
		'integrations/class-cmshop-elementor.php',
	);

	/**
	 * Files loaded in wp-admin only.
	 */
	private const ADMIN_FILES = array(
		'admin/class-cmshop-view.php',
		'admin/class-cmshop-form-state.php',
		'admin/class-cmshop-list-table.php',
		'admin/class-cmshop-items-table.php',
		'admin/class-cmshop-sales-table.php',
		'admin/class-cmshop-repairs-table.php',
		'admin/class-cmshop-customers-table.php',
		'admin/class-cmshop-admin.php',
		'admin/class-cmshop-actions.php',
		'admin/class-cmshop-export.php',
		'admin/class-cmshop-dashboard.php',
	);

	/**
	 * Built services, by name.
	 *
	 * @var array<string,object>
	 */
	private static $services = array();

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		self::load( self::CORE_FILES );
		Cmshop_Installer::activate();
	}

	/**
	 * Boot on plugins_loaded.
	 */
	public static function boot(): void {
		self::load( self::CORE_FILES );
		Cmshop_Installer::maybe_upgrade();

		( new Cmshop_Rest( self::items(), self::customers(), self::sales_service() ) )->register();
		( new Cmshop_Pos_Rest( self::items(), self::customers(), self::sales() ) )->register();
		add_action( 'init', array( self::class, 'register_frontend_style' ), 5 );

		foreach ( self::website_parts() as $part ) {
			$part->register();
		}

		( new Cmshop_Blocks() )->register();
		( new Cmshop_Elementor() )->register();

		if ( class_exists( 'WooCommerce' ) ) {
			self::load( array( 'woo/class-cmshop-woo-brands.php', 'woo/class-cmshop-woo-models.php', 'woo/class-cmshop-woo-products.php', 'woo/class-cmshop-woo-orders.php' ) );
			self::woo_products()->register();
			( new Cmshop_Woo_Orders( self::items(), self::sales(), self::sales_service(), self::woo_models() ) )->register();
		}

		if ( is_admin() ) {
			self::load( self::ADMIN_FILES );
			add_action( 'admin_init', array( 'Cmshop_Installer', 'ensure_capability' ) );
			( new Cmshop_Admin() )->register();
			( new Cmshop_Actions() )->register();
			( new Cmshop_Export() )->register();
		}
	}

	/**
	 * Stock items.
	 *
	 * @return Cmshop_Item_Repository
	 */
	public static function items(): Cmshop_Item_Repository {
		return self::service(
			'items',
			static function ( wpdb $db ) {
				return new Cmshop_Item_Repository(
					$db,
					Cmshop_Installer::table( 'items' ),
					new Cmshop_Query(
						array( 'name', 'imei1', 'imei2', 'sku', 'serial', 'supplier' ),
						array( 'item_type', 'status', 'grade', 'brand' ),
						array( 'name', 'quantity', 'cost_price', 'sale_price', 'created_at', 'status' ),
						'created_at'
					)
				);
			}
		);
	}

	/**
	 * Customers.
	 *
	 * @return Cmshop_Customer_Repository
	 */
	public static function customers(): Cmshop_Customer_Repository {
		return self::service(
			'customers',
			static function ( wpdb $db ) {
				return new Cmshop_Customer_Repository(
					$db,
					Cmshop_Installer::table( 'customers' ),
					new Cmshop_Query( array( 'name', 'phone', 'email' ), array(), array( 'name', 'created_at' ), 'created_at' )
				);
			}
		);
	}

	/**
	 * Sales.
	 *
	 * @return Cmshop_Sale_Repository
	 */
	public static function sales(): Cmshop_Sale_Repository {
		return self::service(
			'sales',
			static function ( wpdb $db ) {
				return new Cmshop_Sale_Repository(
					$db,
					Cmshop_Installer::table( 'sales' ),
					Cmshop_Installer::table( 'sale_items' ),
					new Cmshop_Query(
						array( 'invoice_no', 'customer_name', 'customer_phone' ),
						array( 'channel', 'status', 'payment_method', 'customer_id' ),
						array( 'created_at', 'total', 'profit' ),
						'created_at'
					)
				);
			}
		);
	}

	/**
	 * Repairs.
	 *
	 * @return Cmshop_Repair_Repository
	 */
	public static function repairs(): Cmshop_Repair_Repository {
		return self::service(
			'repairs',
			static function ( wpdb $db ) {
				return new Cmshop_Repair_Repository(
					$db,
					Cmshop_Installer::table( 'repairs' ),
					new Cmshop_Query(
						array( 'ticket_code', 'customer_name', 'customer_phone', 'device', 'imei' ),
						array( 'status', 'customer_id' ),
						array( 'created_at', 'due_date', 'status' ),
						'created_at'
					)
				);
			}
		);
	}

	/**
	 * Sales service.
	 *
	 * @return Cmshop_Sales_Service
	 */
	public static function sales_service(): Cmshop_Sales_Service {
		return self::service(
			'sales_service',
			static function () {
				return new Cmshop_Sales_Service( self::items(), self::sales(), self::customers(), self::payments(), self::installments() );
			}
		);
	}

	/**
	 * WooCommerce product sync, or null without WooCommerce.
	 *
	 * @return Cmshop_Woo_Products|null
	 */
	public static function woo_products(): ?Cmshop_Woo_Products {
		if ( ! class_exists( 'Cmshop_Woo_Products' ) ) {
			return null;
		}

		return self::service(
			'woo_products',
			static function () {
				return new Cmshop_Woo_Products( self::items(), self::woo_models() );
			}
		);
	}

	/**
	 * Payments ledger.
	 *
	 * @return Cmshop_Payment_Repository
	 */
	public static function payments(): Cmshop_Payment_Repository {
		return self::service(
			'payments',
			static function ( wpdb $db ) {
				return new Cmshop_Payment_Repository(
					$db,
					Cmshop_Installer::table( 'payments' ),
					new Cmshop_Query( array( 'reference', 'note' ), array( 'kind', 'method', 'sale_id', 'customer_id', 'shift_id' ), array( 'created_at', 'amount' ), 'created_at' )
				);
			}
		);
	}

	/**
	 * Installment schedules and dues.
	 *
	 * @return Cmshop_Installment_Repository
	 */
	public static function installments(): Cmshop_Installment_Repository {
		return self::service(
			'installments',
			static function ( wpdb $db ) {
				return new Cmshop_Installment_Repository(
					$db,
					Cmshop_Installer::table( 'installments' ),
					Cmshop_Installer::table( 'sales' ),
					Cmshop_Installer::table( 'payments' ),
					new Cmshop_Query( array(), array( 'sale_id', 'customer_id' ), array( 'due_date' ), 'due_date' )
				);
			}
		);
	}

	/**
	 * Collecting dues.
	 *
	 * @return Cmshop_Dues_Service
	 */
	public static function dues(): Cmshop_Dues_Service {
		return self::service(
			'dues',
			static function () {
				return new Cmshop_Dues_Service( self::installments(), self::sales(), self::payments() );
			}
		);
	}

	/**
	 * The website parts (shortcodes, also used by blocks and Elementor widgets), keyed by name.
	 *
	 * @return array{stock: Cmshop_Stock_Shortcode, warranty: Cmshop_Warranty_Check, repair_request: Cmshop_Repair_Request, repair_status: Cmshop_Repair_Tracker}
	 */
	public static function website_parts(): array {
		return self::service(
			'website_parts',
			static function () {
				return array(
					'stock'          => new Cmshop_Stock_Shortcode( self::items() ),
					'warranty'       => new Cmshop_Warranty_Check( self::sales() ),
					'repair_request' => new Cmshop_Repair_Request( self::repairs(), self::customers() ),
					'repair_status'  => new Cmshop_Repair_Tracker( self::repairs() ),
				);
			}
		);
	}

	/**
	 * Output of one website part.
	 *
	 * @param string $name  stock|warranty|repair_request|repair_status.
	 * @param array  $atts  Shortcode-style attributes.
	 * @return string
	 */
	public static function render_part( string $name, array $atts ): string {
		$parts = self::website_parts();

		return isset( $parts[ $name ] ) ? $parts[ $name ]->render( $atts ) : '';
	}

	/**
	 * Register the website stylesheet early, so blocks and widgets can depend on it.
	 */
	public static function register_frontend_style(): void {
		wp_register_style( 'cmshop-frontend', CMSHOP_URL . 'assets/css/frontend.css', array(), CMSHOP_VERSION );
	}

	/**
	 * WooCommerce model listings (new phones), or null without WooCommerce.
	 *
	 * @return Cmshop_Woo_Models|null
	 */
	public static function woo_models(): ?Cmshop_Woo_Models {
		if ( ! class_exists( 'Cmshop_Woo_Models' ) ) {
			return null;
		}

		return self::service(
			'woo_models',
			static function () {
				return new Cmshop_Woo_Models( self::items() );
			}
		);
	}

	/**
	 * Build a service once.
	 *
	 * @param string   $name    Service name.
	 * @param callable $factory Receives $wpdb.
	 * @return object
	 */
	private static function service( string $name, callable $factory ) {
		if ( ! isset( self::$services[ $name ] ) ) {
			global $wpdb;
			self::$services[ $name ] = $factory( $wpdb );
		}

		return self::$services[ $name ];
	}

	/**
	 * Require files from includes/.
	 *
	 * @param string[] $files Relative paths.
	 */
	private static function load( array $files ): void {
		foreach ( $files as $file ) {
			require_once CMSHOP_DIR . 'includes/' . $file;
		}
	}
}
