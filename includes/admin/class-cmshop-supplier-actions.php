<?php
/**
 * Admin-post handlers for suppliers and purchases.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Save a supplier, record a delivery, pay a supplier.
 */
final class Cmshop_Supplier_Actions extends Cmshop_Handler {

	/**
	 * Action name => method.
	 */
	protected const ACTIONS = array(
		'save_supplier' => 'save_supplier',
		'save_purchase' => 'save_purchase',
		'pay_supplier'  => 'pay_supplier',
	);

	/**
	 * Run one handler.
	 *
	 * @param string $method Handler method from ACTIONS.
	 */
	protected function handle( string $method ): void {
		$this->$method();
	}

	/**
	 * Add or update a supplier.
	 */
	private function save_supplier(): void {
		$id     = $this->posted_id();
		$posted = $this->posted();
		$repo   = Cmshop_Plugin::suppliers();
		$in     = new Cmshop_Input( $posted );
		$result = $in->result(
			array(
				'name'    => $in->text( 'name', 150, __( 'Enter the supplier name.', 'codereyes-mobile-shop' ) ),
				'phone'   => $in->phone( 'phone', false ),
				'address' => $in->textarea( 'address', 500 ),
				'notes'   => $in->textarea( 'notes', 2000 ),
			)
		);

		if ( $id > 0 && null === $repo->find( $id ) ) {
			$this->redirect( Cmshop_Supplier_Pages::SLUG, 'not_found' );
		}

		$same = $result->is_valid() ? $repo->find_by_name( (string) $result->data()['name'] ) : null;

		if ( null !== $same && (int) $same['id'] !== $id ) {
			$result = $result->with_error( 'name', __( 'A supplier with this name already exists.', 'codereyes-mobile-shop' ) );
		}

		$back = $id > 0 ? array(
			'action' => 'edit',
			'id'     => $id,
		) : array( 'action' => 'new' );

		if ( ! $result->is_valid() ) {
			Cmshop_Form_State::save( 'supplier', $posted, $result->errors() );
			$this->redirect( Cmshop_Supplier_Pages::SLUG, '', $back );
		}

		try {
			if ( $id > 0 ) {
				$repo->update( $id, $result->data() );
			} else {
				$id = $repo->insert( $result->data() );
			}
		} catch ( RuntimeException $error ) {
			$this->log( $error );
			$this->redirect( Cmshop_Supplier_Pages::SLUG, 'db_error', $back );
		}

		$this->redirect(
			Cmshop_Supplier_Pages::SLUG,
			'supplier_saved',
			array(
				'action' => 'view',
				'id'     => $id,
			)
		);
	}

	/**
	 * Record a delivery of stock.
	 */
	private function save_purchase(): void {
		$posted = $this->posted();

		try {
			$id = Cmshop_Plugin::purchasing()->record( $posted, get_current_user_id() );
		} catch ( Cmshop_Form_Errors $error ) {
			Cmshop_Form_State::save( 'purchase', $posted, $error->errors() );
			$this->redirect( Cmshop_Supplier_Pages::SLUG, '', array( 'action' => 'purchase' ) );
		} catch ( RuntimeException $error ) {
			$this->log( $error );
			Cmshop_Form_State::save( 'purchase', $posted, array() );
			$this->redirect( Cmshop_Supplier_Pages::SLUG, 'db_error', array( 'action' => 'purchase' ) );
		}

		$this->sync_website( $id );

		$this->redirect(
			Cmshop_Supplier_Pages::SLUG,
			'purchase_saved',
			array(
				'action' => 'receipt',
				'id'     => $id,
			)
		);
	}

	/**
	 * Record money paid to a supplier.
	 */
	private function pay_supplier(): void {
		$posted   = $this->posted();
		$supplier = absint( $posted['supplier_id'] ?? 0 );
		$back     = array(
			'action' => 'view',
			'id'     => $supplier,
		);

		try {
			Cmshop_Plugin::purchasing()->pay(
				$supplier,
				sanitize_text_field( (string) ( $posted['amount'] ?? '' ) ),
				sanitize_key( (string) ( $posted['method'] ?? '' ) ),
				sanitize_text_field( (string) ( $posted['reference'] ?? '' ) ),
				get_current_user_id()
			);
		} catch ( InvalidArgumentException $error ) {
			Cmshop_Form_State::save( 'supplier_payment', $posted, array( 'amount' => $error->getMessage() ) );
			$this->redirect( Cmshop_Supplier_Pages::SLUG, '', $back );
		} catch ( RuntimeException $error ) {
			$this->log( $error );
			$this->redirect( Cmshop_Supplier_Pages::SLUG, 'db_error', $back );
		}

		$this->redirect( Cmshop_Supplier_Pages::SLUG, 'supplier_paid', $back );
	}

	/**
	 * New phones join their model's website listing (once per model).
	 *
	 * @param int $purchase_id Purchase.
	 */
	private function sync_website( int $purchase_id ): void {
		$woo = Cmshop_Plugin::woo_products();

		if ( null === $woo ) {
			return;
		}

		$done = array();

		foreach ( Cmshop_Plugin::items()->for_purchase( $purchase_id ) as $item ) {
			$key = Cmshop_Model_Listing::is_grouped( $item ) ? Cmshop_Model_Listing::model_key( $item ) : 'item:' . $item['id'];

			if ( isset( $done[ $key ] ) ) {
				continue;
			}

			$done[ $key ] = true;

			try {
				$woo->item_saved( null, $item );
			} catch ( Exception $error ) {
				$this->log( $error );
			}
		}
	}
}
