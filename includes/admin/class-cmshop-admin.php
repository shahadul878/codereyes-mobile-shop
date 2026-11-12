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

	public const SLUG = 'cmshop';

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CMSHOP_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Menu and submenus.
	 */
	public function menu(): void {
		$cap = Cmshop_Installer::CAPABILITY;

		add_menu_page( __( 'Mobile Shop', 'codereyes-mobile-shop' ), __( 'Mobile Shop', 'codereyes-mobile-shop' ), $cap, self::SLUG, array( $this, 'dashboard' ), 'dashicons-smartphone', 56 );

		$pages = array(
			self::SLUG         => array( __( 'Dashboard', 'codereyes-mobile-shop' ), 'dashboard' ),
			'cmshop-pos'       => array( __( 'New sale (POS)', 'codereyes-mobile-shop' ), 'pos' ),
			'cmshop-items'     => array( __( 'Stock', 'codereyes-mobile-shop' ), 'items' ),
			'cmshop-sales'     => array( __( 'Sales', 'codereyes-mobile-shop' ), 'sales' ),
			'cmshop-repairs'   => array( __( 'Repairs', 'codereyes-mobile-shop' ), 'repairs' ),
			'cmshop-customers' => array( __( 'Customers', 'codereyes-mobile-shop' ), 'customers' ),
		);

		foreach ( $pages as $slug => $page ) {
			add_submenu_page( self::SLUG, $page[0], $page[0], $cap, $slug, array( $this, $page[1] ) );
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
		wp_enqueue_script( 'cmshop-admin', CMSHOP_URL . 'assets/js/admin.js', array(), CMSHOP_VERSION, true );

		if ( false === strpos( $hook, 'cmshop-pos' ) ) {
			return;
		}

		wp_enqueue_script( 'cmshop-pos', CMSHOP_URL . 'assets/js/pos.js', array( 'wp-api-fetch', 'wp-i18n' ), CMSHOP_VERSION, true );
		wp_set_script_translations( 'cmshop-pos', 'codereyes-mobile-shop' );
		wp_localize_script(
			'cmshop-pos',
			'cmshopPos',
			array(
				'namespace' => Cmshop_Rest::NAMESPACE,
				'symbol'    => Cmshop_Settings::currency_symbol(),
				'preselect' => $this->preselected_item(),
			)
		);
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
		$today = current_time( 'Y-m-d' );
		$month = current_time( 'Y-m' ) . '-01';

		$this->render(
			'dashboard',
			array(
				'stock'   => Cmshop_Plugin::items()->stock_summary( (int) Cmshop_Settings::get( 'low_stock' ) ),
				'today'   => Cmshop_Plugin::sales()->totals( $today . ' 00:00:00', gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) ) . ' 00:00:00' ),
				'month'   => Cmshop_Plugin::sales()->totals( $month . ' 00:00:00', gmdate( 'Y-m-d', strtotime( $month . ' +1 month' ) ) . ' 00:00:00' ),
				'repairs' => Cmshop_Plugin::repairs()->open_counts(),
				'recent'  => Cmshop_Plugin::sales()->recent( 8 ),
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
				)
			);
			return;
		}

		$this->list_page( new Cmshop_Items_Table( Cmshop_Plugin::items(), 'cmshop-items', 'items' ), 'items' );
	}

	/**
	 * Sales list and receipt.
	 */
	public function sales(): void {
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
		if ( ! current_user_can( 'settings' === $view ? 'manage_options' : Cmshop_Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'codereyes-mobile-shop' ) );
		}

		$cmshop = $vars;
		include CMSHOP_DIR . 'views/admin/' . $view . '.php';
	}
}
