<?php
/**
 * Admin menu, assets and page routing.
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended -- Pages only read which record to show; changes go through Cmshop_Actions.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Mobile Shop" menu and renders each screen.
 */
final class Cmshop_Admin {

	/**
	 * Screens only managers may open: stock, costs, refunds, staff.
	 */
	private const MANAGER_VIEWS = array( 'item-form', 'stickers', 'sale-return', 'staff' );

	public const SLUG = 'cmshop';

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CMSHOP_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Menu and submenus.
	 */
	public function menu(): void {
		$sell   = Cmshop_Installer::CAP_SELL;
		$manage = Cmshop_Installer::CAPABILITY;

		// Managers also hold the sell capability, so the menu shows for both.
		add_menu_page( __( 'Mobile Shop', 'codereyes-mobile-shop' ), __( 'Mobile Shop', 'codereyes-mobile-shop' ), $sell, self::SLUG, array( $this, 'dashboard' ), 'dashicons-smartphone', 56 );

		$pages = array(
			self::SLUG         => array( __( 'Dashboard', 'codereyes-mobile-shop' ), 'dashboard', $sell ),
			'cmshop-pos'       => array( __( 'New sale (POS)', 'codereyes-mobile-shop' ), 'pos', $sell ),
			'cmshop-items'     => array( __( 'Stock', 'codereyes-mobile-shop' ), 'items', $manage ),
			'cmshop-suppliers' => array( __( 'Suppliers', 'codereyes-mobile-shop' ), array( new Cmshop_Supplier_Pages(), 'render' ), $manage ),
			'cmshop-sales'     => array( __( 'Sales', 'codereyes-mobile-shop' ), 'sales', $sell ),
			'cmshop-repairs'   => array( __( 'Repairs', 'codereyes-mobile-shop' ), 'repairs', $sell ),
			'cmshop-customers' => array( __( 'Customers', 'codereyes-mobile-shop' ), 'customers', $sell ),
			'cmshop-dues'      => array( __( 'Dues', 'codereyes-mobile-shop' ), 'dues', $sell ),
			'cmshop-shifts'    => array( __( 'Cash register', 'codereyes-mobile-shop' ), 'shifts', $sell ),
			'cmshop-staff'     => array( __( 'Staff and commission', 'codereyes-mobile-shop' ), 'staff', $manage ),
		);

		foreach ( $pages as $slug => $page ) {
			add_submenu_page( self::SLUG, $page[0], $page[0], $page[2], $slug, is_array( $page[1] ) ? $page[1] : array( $this, $page[1] ) );
		}

		add_submenu_page( self::SLUG, __( 'Settings', 'codereyes-mobile-shop' ), __( 'Settings', 'codereyes-mobile-shop' ), 'manage_options', 'cmshop-settings', array( $this, 'settings' ) );
	}

	/**
	 * Settings API registration.
	 */
	public function register_settings(): void {
		register_setting(
			'cmshop_settings',
			Cmshop_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Cmshop_Settings', 'sanitize' ),
				'default'           => Cmshop_Settings::defaults(),
			)
		);
	}

	/**
	 * CSS and JS on plugin screens only.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( string $hook ): void {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'cmshop-admin', CMSHOP_URL . 'assets/css/admin.css', array(), CMSHOP_VERSION );
		wp_enqueue_style( 'cmshop-print', CMSHOP_URL . 'assets/css/print.css', array( 'cmshop-admin' ), CMSHOP_VERSION );
		wp_enqueue_style( 'cmshop-money', CMSHOP_URL . 'assets/css/admin-money.css', array( 'cmshop-admin' ), CMSHOP_VERSION );

		if ( 'toplevel_page_' . self::SLUG === $hook ) {
			wp_enqueue_style( 'cmshop-dashboard', CMSHOP_URL . 'assets/css/dashboard.css', array( 'cmshop-admin' ), CMSHOP_VERSION );
		}
		wp_enqueue_script( 'cmshop-admin', CMSHOP_URL . 'assets/js/admin.js', array(), CMSHOP_VERSION, true );

		if ( false !== strpos( $hook, 'cmshop-items' ) && in_array( 'stickers', array( $this->action(), $this->query_key( 'action2' ) ), true ) ) {
			$size = Cmshop_Stickers::sizes()[ self::sticker_size() ];
			$page = 'a4' === self::sticker_size() ? 'size: A4; margin: 15.1mm 7.2mm;' : sprintf( 'size: %smm %smm; margin: 0;', $size[1], $size[2] );

			wp_enqueue_style( 'cmshop-stickers', CMSHOP_URL . 'assets/css/stickers.css', array( 'cmshop-admin', 'cmshop-print' ), CMSHOP_VERSION );
			wp_add_inline_style( 'cmshop-stickers', '@page cmshop-stickers { ' . $page . ' }' );
		}

		if ( false !== strpos( $hook, 'cmshop-items' ) && in_array( $this->action(), array( 'new', 'edit' ), true ) && current_user_can( 'upload_files' ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'cmshop-item-photos', CMSHOP_URL . 'assets/js/item-photos.js', array( 'media-editor', 'wp-i18n' ), CMSHOP_VERSION, true );
			wp_set_script_translations( 'cmshop-item-photos', 'codereyes-mobile-shop' );
		}

		if ( false !== strpos( $hook, Cmshop_Supplier_Pages::SLUG ) && 'purchase' === $this->action() ) {
			wp_enqueue_script( 'cmshop-purchase', CMSHOP_URL . 'assets/js/purchase.js', array( 'wp-i18n' ), CMSHOP_VERSION, true );
			wp_set_script_translations( 'cmshop-purchase', 'codereyes-mobile-shop' );
			wp_localize_script( 'cmshop-purchase', 'cmshopPurchase', array( 'symbol' => Cmshop_Settings::currency_symbol() ) );
		}

		if ( false === strpos( $hook, 'cmshop-pos' ) ) {
			return;
		}

		$user = wp_get_current_user();

		wp_enqueue_style( 'cmshop-pos', CMSHOP_URL . 'assets/css/pos.css', array( 'cmshop-admin' ), CMSHOP_VERSION );
		wp_enqueue_script( 'cmshop-pos-core', CMSHOP_URL . 'assets/js/pos-core.js', array(), CMSHOP_VERSION, true );
		wp_enqueue_script( 'cmshop-pos-dom', CMSHOP_URL . 'assets/js/pos-dom.js', array( 'wp-i18n' ), CMSHOP_VERSION, true );
		wp_set_script_translations( 'cmshop-pos-dom', 'codereyes-mobile-shop' );
		wp_enqueue_script( 'cmshop-pos', CMSHOP_URL . 'assets/js/pos.js', array( 'cmshop-pos-core', 'cmshop-pos-dom', 'wp-api-fetch', 'wp-i18n' ), CMSHOP_VERSION, true );
		wp_set_script_translations( 'cmshop-pos', 'codereyes-mobile-shop' );
		wp_localize_script(
			'cmshop-pos',
			'cmshopPos',
			array(
				'namespace' => Cmshop_Rest::NAMESPACE,
				'symbol'    => Cmshop_Settings::currency_symbol(),
				'preselect' => $this->preselected_item(),
				'methods'   => Cmshop_Settings::payment_methods(),
				'receipt'   => Cmshop_View::url( 'cmshop-sales', array( 'action' => 'view' ) ),
				'cashier'   => $user->display_name,
			)
		);
	}

	/**
	 * Full-screen point of sale: the admin menu and toolbar are hidden.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public function body_class( string $classes ): string {
		return 'cmshop-pos' === $this->query_key( 'page' ) ? $classes . ' cmshop-pos-screen' : $classes;
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$link = sprintf( '<a href="%s">%s</a>', esc_url( Cmshop_View::url( 'cmshop-settings' ) ), esc_html__( 'Settings', 'codereyes-mobile-shop' ) );

		return array_merge( array( $link ), $links );
	}

	/**
	 * Dashboard.
	 */
	public function dashboard(): void {
		if ( Cmshop_Staff::can_manage() ) {
			$this->render( 'dashboard', Cmshop_Dashboard::data() );
			return;
		}

		// Cashiers get their own day and commission, never shop-wide money or profit.
		$this->render( 'dashboard-staff', Cmshop_Dashboard::staff_data( get_current_user_id() ) );
	}

	/**
	 * Sales and commission per staff member.
	 */
	public function staff(): void {
		$periods = Cmshop_Dashboard::periods();
		$period  = array_key_exists( $this->query_key( 'period' ), $periods ) ? $this->query_key( 'period' ) : 'month';
		$range   = $periods[ $period ];

		$this->render(
			'staff',
			array(
				'period'   => $period,
				'periods'  => $periods,
				'report'   => Cmshop_Commission::report(
					Cmshop_Plugin::sales()->by_staff( $range[1], $range[2] ),
					Cmshop_Commission::rate( (string) Cmshop_Settings::get( 'commission_rate' ) ),
					(string) Cmshop_Settings::get( 'commission_base' )
				),
				'cashiers' => get_users(
					array(
						'role'   => Cmshop_Installer::CASHIER_ROLE,
						'fields' => array( 'ID', 'display_name' ),
					)
				),
			)
		);
	}

	/**
	 * Point of sale.
	 */
	public function pos(): void {
		$this->render( 'pos', array() );
	}

	/**
	 * Stock list and form.
	 */
	public function items(): void {
		$action = $this->action();

		if ( 'stickers' === $action || 'stickers' === $this->query_key( 'action2' ) ) {
			$this->stickers();
			return;
		}

		if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
			$item = 'edit' === $action ? Cmshop_Plugin::items()->find( $this->id() ) : null;

			if ( 'edit' === $action && null === $item ) {
				$this->render( 'not-found', array() );
				return;
			}

			$state = Cmshop_Form_State::take( 'item' );
			$type  = isset( $_GET['type'] ) && Cmshop_Item::TYPE_ACCESSORY === $_GET['type'] ? Cmshop_Item::TYPE_ACCESSORY : Cmshop_Item::TYPE_PHONE;
			$woo   = Cmshop_Settings::woo_enabled() ? Cmshop_Plugin::woo_products() : null;

			$this->render(
				'item-form',
				array(
					'item'    => $item,
					'values'  => $state['values'] ?? ( $item ?? $this->new_item_values( $type ) ),
					'errors'  => $state['errors'] ?? array(),
					'sales'   => null === $item ? array() : Cmshop_Plugin::sales()->for_item( (int) $item['id'] ),
					'listing' => null === $item || null === $woo ? null : $woo->website_listing( $item ),
					'brands'  => $this->brand_suggestions(),
				)
			);
			return;
		}

		$this->list_page( new Cmshop_Items_Table( Cmshop_Plugin::items(), 'cmshop-items', 'items' ), 'items' );
	}

	/**
	 * Price stickers for chosen items, or for stock that arrived recently.
	 */
	private function stickers(): void {
		$ids    = isset( $_GET['item'] ) ? array_map( 'absint', (array) wp_unslash( $_GET['item'] ) ) : array();
		$ranges = self::sticker_ranges();
		$added  = array_key_exists( $this->query_key( 'added' ), $ranges ) ? $this->query_key( 'added' ) : 'today';
		$copies = '' === $this->query_key( 'copies' ) ? '1' : $this->query_key( 'copies' );
		$rows   = Cmshop_Plugin::items()->for_stickers( $ids, $ranges[ $added ][1] );

		$this->render(
			'stickers',
			array(
				'labels' => Cmshop_Stickers::sheet( $rows, $copies ),
				'ids'    => array_values( array_filter( $ids ) ),
				'added'  => $added,
				'ranges' => $ranges,
				'copies' => $copies,
				'size'   => self::sticker_size(),
				'price'  => '0' !== $this->query_key( 'price' ),
				'shop'   => '0' !== $this->query_key( 'shop' ),
			)
		);
	}

	/**
	 * "Arrived" ranges for the sticker page: key => [label, since].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function sticker_ranges(): array {
		$today = current_time( 'Y-m-d' ) . ' 00:00:00';

		return array(
			'today' => array( __( 'Added today', 'codereyes-mobile-shop' ), $today ),
			'week'  => array( __( 'Added in the last 7 days', 'codereyes-mobile-shop' ), gmdate( 'Y-m-d H:i:s', strtotime( $today . ' -6 days' ) ) ),
			'month' => array( __( 'Added in the last 30 days', 'codereyes-mobile-shop' ), gmdate( 'Y-m-d H:i:s', strtotime( $today . ' -29 days' ) ) ),
		);
	}

	/**
	 * Sticker size chosen on the page.
	 *
	 * @return string
	 */
	private static function sticker_size(): string {
		return Cmshop_Stickers::size( isset( $_GET['size'] ) ? sanitize_key( wp_unslash( $_GET['size'] ) ) : '' );
	}

	/**
	 * Sales list and receipt.
	 */
	public function sales(): void {
		if ( 'return' === $this->action() ) {
			$sale = Cmshop_Plugin::sales()->find( $this->id() );

			if ( null === $sale ) {
				$this->render( 'not-found', array() );
				return;
			}

			$state = Cmshop_Form_State::take( 'return' );

			$this->render(
				'sale-return',
				array(
					'sale'    => $sale,
					'lines'   => Cmshop_Plugin::sales()->lines( (int) $sale['id'] ),
					'methods' => Cmshop_Returns_Service::refund_methods( (int) $sale['customer_id'] > 0 ),
					'errors'  => $state['errors'] ?? array(),
				)
			);
			return;
		}

		if ( 'view' === $this->action() ) {
			$sale = Cmshop_Plugin::sales()->find( $this->id() );

			if ( null === $sale ) {
				$this->render( 'not-found', array() );
				return;
			}

			$customer_id = (int) $sale['customer_id'];

			$this->render(
				'sale-view',
				array(
					'sale'     => $sale,
					'lines'    => Cmshop_Plugin::sales()->lines( (int) $sale['id'] ),
					'customer' => $customer_id > 0 ? Cmshop_Plugin::customers()->find( $customer_id ) : null,
					'format'   => Cmshop_Invoice_Format::resolve( $this->query_key( 'format' ), (string) Cmshop_Settings::get( 'invoice_format' ) ),
				)
			);
			return;
		}

		$this->list_page( new Cmshop_Sales_Table( Cmshop_Plugin::sales(), 'cmshop-sales', 'sales' ), 'sales' );
	}

	/**
	 * Repairs list, form and printable ticket.
	 */
	public function repairs(): void {
		$action = $this->action();

		if ( ! in_array( $action, array( 'new', 'edit', 'ticket' ), true ) ) {
			$this->list_page( new Cmshop_Repairs_Table( Cmshop_Plugin::repairs(), 'cmshop-repairs', 'repairs' ), 'repairs' );
			return;
		}

		$repair = 'new' === $action ? null : Cmshop_Plugin::repairs()->find( $this->id() );

		if ( 'new' !== $action && null === $repair ) {
			$this->render( 'not-found', array() );
			return;
		}

		if ( 'ticket' === $action ) {
			$this->render( 'repair-ticket', array( 'repair' => $repair ) );
			return;
		}

		$state = Cmshop_Form_State::take( 'repair' );

		$this->render(
			'repair-form',
			array(
				'repair' => $repair,
				'values' => $state['values'] ?? ( $repair ?? array( 'status' => Cmshop_Repair_Status::RECEIVED ) ),
				'errors' => $state['errors'] ?? array(),
			)
		);
	}

	/**
	 * Customers list and form with history.
	 */
	public function customers(): void {
		$action = $this->action();

		if ( ! in_array( $action, array( 'new', 'edit' ), true ) ) {
			$this->list_page( new Cmshop_Customers_Table( Cmshop_Plugin::customers(), 'cmshop-customers', 'customers' ), 'customers' );
			return;
		}

		$customer = 'edit' === $action ? Cmshop_Plugin::customers()->find( $this->id() ) : null;

		if ( 'edit' === $action && null === $customer ) {
			$this->render( 'not-found', array() );
			return;
		}

		$state = Cmshop_Form_State::take( 'customer' );
		$id    = null === $customer ? 0 : (int) $customer['id'];

		$this->render(
			'customer-form',
			array(
				'customer' => $customer,
				'values'   => $state['values'] ?? ( $customer ?? array() ),
				'errors'   => $state['errors'] ?? array(),
				'sales'    => $id > 0 ? Cmshop_Plugin::sales()->for_customer( $id ) : array(),
				'repairs'  => $id > 0 ? Cmshop_Plugin::repairs()->for_customer( $id ) : array(),
			)
		);
	}

	/**
	 * Dues: everyone who owes money, or one customer's account to collect from.
	 */
	public function dues(): void {
		$customer_id = isset( $_GET['customer'] ) ? absint( $_GET['customer'] ) : 0;
		$customer    = 'collect' === $this->action() && $customer_id > 0 ? Cmshop_Plugin::customers()->find( $customer_id ) : null;

		if ( null === $customer ) {
			$this->render( 'dues', array( 'debtors' => Cmshop_Plugin::dues()->debtors() ) );
			return;
		}

		$state = Cmshop_Form_State::take( 'due' );

		$this->render(
			'dues-customer',
			array(
				'customer' => $customer,
				'sales'    => array_map(
					static function ( array $sale ): array {
						return array_merge( $sale, array( 'schedule' => Cmshop_Plugin::dues()->schedule( $sale ) ) );
					},
					Cmshop_Plugin::installments()->unpaid_sales( $customer_id )
				),
				'values'   => $state['values'] ?? array(),
				'errors'   => $state['errors'] ?? array(),
			)
		);
	}

	/**
	 * Cash register: open/close a shift, petty cash, history and end-of-day reports.
	 */
	public function shifts(): void {
		$own = Cmshop_Staff::can_manage() ? 0 : get_current_user_id();

		if ( 'report' === $this->action() ) {
			$shift = Cmshop_Plugin::shift_history()->find( $this->id() );

			// Cashiers only see their own shifts.
			if ( null === $shift || ( $own > 0 && (int) $shift['user_id'] !== $own ) ) {
				$this->render( 'not-found', array() );
				return;
			}

			$this->render( 'shift-report', array( 'shift' => Cmshop_Plugin::shifts()->with_summary( $shift ) ) );
			return;
		}

		$state = Cmshop_Form_State::take( 'shift' );

		$this->render(
			'shifts',
			array(
				'current' => Cmshop_Plugin::shifts()->current( get_current_user_id() ),
				'history' => Cmshop_Plugin::shift_history()->recent( 30, $own ),
				'errors'  => $state['errors'] ?? array(),
				'values'  => $state['values'] ?? array(),
			)
		);
	}

	/**
	 * Settings.
	 */
	public function settings(): void {
		$this->render( 'settings', array( 'settings' => Cmshop_Settings::all() ) );
	}

	/**
	 * List screen wrapper.
	 *
	 * @param Cmshop_List_Table $table   Table.
	 * @param string            $dataset items|sales|repairs|customers.
	 */
	private function list_page( Cmshop_List_Table $table, string $dataset ): void {
		$table->prepare_items();
		$this->render(
			'list',
			array(
				'table'   => $table,
				'dataset' => $dataset,
				'args'    => Cmshop_List_Table::request_args(),
			)
		);
	}

	/**
	 * Item preloaded into the POS cart from a "Sell" link.
	 *
	 * @return array|null
	 */
	private function preselected_item(): ?array {
		$id   = isset( $_GET['add'] ) ? absint( $_GET['add'] ) : 0;
		$item = $id > 0 ? Cmshop_Plugin::items()->find( $id ) : null;

		if ( null === $item || Cmshop_Item::STATUS_SOLD === $item['status'] || (int) $item['quantity'] < 1 ) {
			return null;
		}

		return array(
			'id'         => (int) $item['id'],
			'type'       => (string) $item['item_type'],
			'name'       => Cmshop_Item::display_name( $item ),
			'imei'       => (string) $item['imei1'],
			'sku'        => (string) $item['sku'],
			'quantity'   => (int) $item['quantity'],
			'sale_price' => (string) $item['sale_price'],
			'price_text' => Cmshop_Settings::money( (string) $item['sale_price'] ),
		);
	}

	/**
	 * Brands to suggest: WooCommerce brands plus brands already in stock, ignoring case.
	 *
	 * @return string[]
	 */
	private function brand_suggestions(): array {
		$woo   = class_exists( 'Cmshop_Woo_Brands' ) ? Cmshop_Woo_Brands::names() : array();
		$names = array();

		foreach ( array_merge( $woo, Cmshop_Plugin::items()->brands() ) as $name ) {
			$names[ mb_strtolower( trim( $name ) ) ] = $names[ mb_strtolower( trim( $name ) ) ] ?? trim( $name );
		}

		natcasesort( $names );

		return array_values( $names );
	}

	/**
	 * Starting values for a new item: blank, or copied from ?copy= ("Add another like this").
	 *
	 * @param string $type Item type.
	 * @return array
	 */
	private function new_item_values( string $type ): array {
		$copy   = isset( $_GET['copy'] ) ? absint( $_GET['copy'] ) : 0;
		$source = $copy > 0 ? Cmshop_Plugin::items()->find( $copy ) : null;

		return null === $source ? array( 'item_type' => $type ) : Cmshop_Item::copy_fields( $source );
	}

	/**
	 * Current ?action=.
	 *
	 * @return string
	 */
	private function action(): string {
		return $this->query_key( 'action' );
	}

	/**
	 * A ?key= value from the URL, as a sanitised key.
	 *
	 * @param string $name Query arg.
	 * @return string
	 */
	private function query_key( string $name ): string {
		return isset( $_GET[ $name ] ) ? sanitize_key( wp_unslash( $_GET[ $name ] ) ) : '';
	}

	/**
	 * Current ?id=.
	 *
	 * @return int
	 */
	private function id(): int {
		return isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	}

	/**
	 * Include a view. Its variables are available to it as $cmshop.
	 *
	 * @param string $view View name in views/admin.
	 * @param array  $vars Variables.
	 */
	private function render( string $view, array $vars ): void {
		$allowed = Cmshop_Staff::can_sell();

		if ( 'settings' === $view ) {
			$allowed = current_user_can( 'manage_options' );
		} elseif ( in_array( $view, self::MANAGER_VIEWS, true ) || ( 'list' === $view && 'items' === ( $vars['dataset'] ?? '' ) ) ) {
			$allowed = Cmshop_Staff::can_manage();
		}

		if ( ! $allowed ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'codereyes-mobile-shop' ), 403 );
		}

		$cmshop = $vars;
		include CMSHOP_DIR . 'views/admin/' . $view . '.php';
	}
}
