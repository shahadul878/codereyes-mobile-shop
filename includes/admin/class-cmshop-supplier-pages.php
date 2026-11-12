<?php
/**
 * Suppliers screens: list, form, account, new purchase, purchase receipt.
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended -- Pages only read which record to show; changes go through Cmshop_Supplier_Actions.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Suppliers menu page for each ?action=.
 */
final class Cmshop_Supplier_Pages {

	public const SLUG = 'cmshop-suppliers';

	/**
	 * Menu callback.
	 */
	public function render(): void {
		if ( ! Cmshop_Staff::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'codereyes-mobile-shop' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		switch ( $action ) {
			case 'new':
			case 'edit':
				$this->form( 'edit' === $action ? $id : 0 );
				break;
			case 'view':
				$this->account( $id );
				break;
			case 'purchase':
				$this->purchase_form();
				break;
			case 'receipt':
				$this->receipt( $id );
				break;
			default:
				$this->view(
					'suppliers',
					array(
						'suppliers' => Cmshop_Plugin::suppliers()->with_balances( Cmshop_Installer::table( 'purchases' ) ),
						'owed'      => Cmshop_Plugin::purchases()->owed_total(),
					)
				);
		}
	}

	/**
	 * Add or edit a supplier.
	 *
	 * @param int $id Supplier ID (0 for new).
	 */
	private function form( int $id ): void {
		$supplier = $id > 0 ? Cmshop_Plugin::suppliers()->find( $id ) : null;

		if ( $id > 0 && null === $supplier ) {
			$this->view( 'not-found', array() );
			return;
		}

		$state = Cmshop_Form_State::take( 'supplier' );

		$this->view(
			'supplier-form',
			array(
				'supplier' => $supplier,
				'values'   => $state['values'] ?? ( $supplier ?? array() ),
				'errors'   => $state['errors'] ?? array(),
			)
		);
	}

	/**
	 * A supplier's account: deliveries, payments, pay form.
	 *
	 * @param int $id Supplier ID.
	 */
	private function account( int $id ): void {
		$supplier = Cmshop_Plugin::suppliers()->find( $id );

		if ( null === $supplier ) {
			$this->view( 'not-found', array() );
			return;
		}

		$state     = Cmshop_Form_State::take( 'supplier_payment' );
		$purchases = Cmshop_Plugin::purchases()->for_supplier( $id );
		$owed      = 0;

		foreach ( $purchases as $purchase ) {
			$owed += Cmshop_Money::to_cents( (string) $purchase['total'] ) - Cmshop_Money::to_cents( (string) $purchase['paid'] );
		}

		$this->view(
			'supplier-view',
			array(
				'supplier'  => $supplier,
				'purchases' => $purchases,
				'payments'  => Cmshop_Plugin::payments()->for_supplier( $id, 50 ),
				'owed'      => $owed,
				'values'    => $state['values'] ?? array(),
				'errors'    => $state['errors'] ?? array(),
			)
		);
	}

	/**
	 * New purchase (delivery) form.
	 */
	private function purchase_form(): void {
		$state = Cmshop_Form_State::take( 'purchase' );

		$this->view(
			'purchase-form',
			array(
				'values'      => $state['values'] ?? array(
					'supplier_id'  => isset( $_GET['supplier'] ) ? absint( $_GET['supplier'] ) : 0,
					'purchased_on' => current_time( 'Y-m-d' ),
					'lines'        => array(),
				),
				'errors'      => $state['errors'] ?? array(),
				'suppliers'   => Cmshop_Plugin::suppliers()->choices(),
				'accessories' => Cmshop_Plugin::items()->restock_choices(),
			)
		);
	}

	/**
	 * What came in with a purchase, with sticker printing.
	 *
	 * @param int $id Purchase ID.
	 */
	private function receipt( int $id ): void {
		$purchase = Cmshop_Plugin::purchases()->find( $id );

		if ( null === $purchase ) {
			$this->view( 'not-found', array() );
			return;
		}

		$this->view(
			'purchase-view',
			array(
				'purchase' => $purchase,
				'supplier' => Cmshop_Plugin::suppliers()->find( (int) $purchase['supplier_id'] ),
				'items'    => Cmshop_Plugin::items()->for_purchase( $id ),
			)
		);
	}

	/**
	 * Include a view; its variables are available to it as $cmshop.
	 *
	 * @param string $view View name in views/admin.
	 * @param array  $vars Variables.
	 */
	private function view( string $view, array $vars ): void {
		$cmshop = $vars;
		include CMSHOP_DIR . 'views/admin/' . $view . '.php';
	}
}
