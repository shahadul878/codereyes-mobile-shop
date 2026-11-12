<?php
/**
 * Admin-post handlers: save, delete, publish, void.
 *
 * Every handler checks the capability and nonce first, then redirects back
 * with a notice code (post/redirect/get).
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Form and link actions in wp-admin.
 */
final class Cmshop_Actions extends Cmshop_Handler {

	/**
	 * Action name => method.
	 */
	protected const ACTIONS = array(
		'save_item'       => 'save_item',
		'delete_item'     => 'delete_item',
		'publish_item'    => 'publish_item',
		'unpublish_item'  => 'unpublish_item',
		'save_customer'   => 'save_customer',
		'delete_customer' => 'delete_customer',
		'save_repair'     => 'save_repair',
		'delete_repair'   => 'delete_repair',
		'void_sale'       => 'void_sale',
		'collect_due'     => 'collect_due',
		'return_items'    => 'return_items',
		'open_shift'      => 'open_shift',
		'petty_cash'      => 'petty_cash',
		'close_shift'     => 'close_shift',
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
	 * Save a stock item.
	 */
	private function save_item(): void {
		$id     = $this->posted_id();
		$items  = Cmshop_Plugin::items();
		$item   = $id > 0 ? $items->find( $id ) : null;
		$result = Cmshop_Validator::item( $this->posted() );
		$data   = $this->checked_photos( $result->data(), $item );

		if ( $id > 0 && null === $item ) {
			$this->redirect( 'cmshop-items', 'not_found' );
		}

		if ( Cmshop_Item::TYPE_PHONE === $data['item_type'] && $result->is_valid() ) {
			$result = $this->check_duplicate_imei( $result, $id );
		}

		// A sold phone stays sold when edited; stock changes go through sales.
		if ( null !== $item && Cmshop_Item::TYPE_PHONE === $item['item_type'] && Cmshop_Item::STATUS_SOLD === $item['status'] ) {
			$data = array_merge(
				$data,
				array(
					'status'   => Cmshop_Item::STATUS_SOLD,
					'quantity' => 0,
				)
			);
		}

		if ( ! $result->is_valid() ) {
			Cmshop_Form_State::save( 'item', array_merge( $this->posted(), array( 'id' => $id ) ), $result->errors() );
			$this->redirect(
				'cmshop-items',
				'',
				$id > 0 ? array(
					'action' => 'edit',
					'id'     => $id,
				) : array(
					'action' => 'new',
					'type'   => $data['item_type'],
				)
			);
		}

		try {
			if ( $id > 0 ) {
				$items->update( $id, $data );
			} else {
				$id = $items->insert( array_merge( $data, array( 'created_by' => get_current_user_id() ) ) );
			}

			$this->refresh_product( $item, $id );
		} catch ( RuntimeException $error ) {
			$this->log( $error );
			$this->redirect( 'cmshop-items', 'db_error' );
		}

		$this->redirect(
			'cmshop-items',
			'item_saved',
			array(
				'action' => 'edit',
				'id'     => $id,
			)
		);
	}

	/**
	 * Delete a stock item that has never been sold.
	 */
	private function delete_item(): void {
		$item = Cmshop_Plugin::items()->find( $this->query_id() );

		if ( null === $item ) {
			$this->redirect( 'cmshop-items', 'not_found' );
		}

		if ( array() !== Cmshop_Plugin::sales()->for_item( (int) $item['id'] ) ) {
			$this->redirect( 'cmshop-items', 'item_has_sales' );
		}

		Cmshop_Plugin::items()->delete( (int) $item['id'] );

		$woo = Cmshop_Plugin::woo_products();

		try {
			if ( null !== $woo ) {
				$woo->item_deleted( $item );
			}
		} catch ( Exception $error ) {
			$this->log( $error );
		}

		$this->redirect( 'cmshop-items', 'item_deleted' );
	}

	/**
	 * Publish an item to WooCommerce.
	 */
	private function publish_item(): void {
		$item = Cmshop_Plugin::items()->find( $this->query_id() );
		$woo  = Cmshop_Plugin::woo_products();

		if ( null === $item ) {
			$this->redirect( 'cmshop-items', 'not_found' );
		}

		$back = array(
			'action' => 'edit',
			'id'     => (int) $item['id'],
		);

		if ( null === $woo ) {
			$this->redirect( 'cmshop-items', 'publish_failed', $back );
		}

		$listing = $woo->website_listing( $item );

		try {
			$woo->publish( $item );
		} catch ( Exception $error ) {
			$this->log( $error );
			$this->redirect( 'cmshop-items', 'publish_failed', $back );
		}

		$this->redirect( 'cmshop-items', 'model_unlisted' === ( $listing['kind'] ?? '' ) ? 'model_published' : 'published', $back );
	}

	/**
	 * Remove an item from WooCommerce.
	 */
	private function unpublish_item(): void {
		$item = Cmshop_Plugin::items()->find( $this->query_id() );
		$woo  = Cmshop_Plugin::woo_products();

		if ( null === $item ) {
			$this->redirect( 'cmshop-items', 'not_found' );
		}

		$listing = null === $woo ? null : $woo->website_listing( $item );

		if ( null !== $woo ) {
			$woo->unpublish( $item );
		}

		$this->redirect(
			'cmshop-items',
			'model' === ( $listing['kind'] ?? '' ) ? 'model_removed' : 'unpublished',
			array(
				'action' => 'edit',
				'id'     => (int) $item['id'],
			)
		);
	}

	/**
	 * Save a customer.
	 */
	private function save_customer(): void {
		$id        = $this->posted_id();
		$customers = Cmshop_Plugin::customers();
		$result    = Cmshop_Validator::customer( $this->posted() );

		if ( $result->is_valid() ) {
			$other = $customers->find_by_phone( $result->data()['phone'] );

			if ( null !== $other && (int) $other['id'] !== $id ) {
				$result = $result->with_error( 'phone', __( 'Another customer already has this phone number.', 'codereyes-mobile-shop' ) );
			}
		}

		$back = $id > 0 ? array(
			'action' => 'edit',
			'id'     => $id,
		) : array( 'action' => 'new' );

		if ( ! $result->is_valid() ) {
			Cmshop_Form_State::save( 'customer', $this->posted(), $result->errors() );
			$this->redirect( 'cmshop-customers', '', $back );
		}

		try {
			if ( $id > 0 ) {
				$customers->update( $id, $result->data() );
			} else {
				$id = $customers->insert( $result->data() );
			}
		} catch ( RuntimeException $error ) {
			$this->log( $error );
			$this->redirect( 'cmshop-customers', 'db_error', $back );
		}

		$this->redirect(
			'cmshop-customers',
			'customer_saved',
			array(
				'action' => 'edit',
				'id'     => $id,
			)
		);
	}

	/**
	 * Delete a customer with no history.
	 */
	private function delete_customer(): void {
		$id = $this->query_id();

		if ( array() !== Cmshop_Plugin::sales()->for_customer( $id ) || array() !== Cmshop_Plugin::repairs()->for_customer( $id ) ) {
			$this->redirect( 'cmshop-customers', 'customer_in_use' );
		}

		Cmshop_Plugin::customers()->delete( $id );
		$this->redirect( 'cmshop-customers', 'customer_deleted' );
	}

	/**
	 * Save a repair job (finds or creates the customer by phone).
	 */
	private function save_repair(): void {
		$id      = $this->posted_id();
		$repairs = Cmshop_Plugin::repairs();
		$repair  = $id > 0 ? $repairs->find( $id ) : null;
		$result  = Cmshop_Validator::repair( $this->posted() );
		$back    = $id > 0 ? array(
			'action' => 'edit',
			'id'     => $id,
		) : array( 'action' => 'new' );

		if ( $id > 0 && null === $repair ) {
			$this->redirect( 'cmshop-repairs', 'not_found' );
		}

		if ( ! $result->is_valid() ) {
			Cmshop_Form_State::save( 'repair', $this->posted(), $result->errors() );
			$this->redirect( 'cmshop-repairs', '', $back );
		}

		$data = $result->data();

		try {
			$customer = Cmshop_Plugin::customers()->find_or_create( $data['customer_name'], $data['customer_phone'] );
			$data     = array_merge( $data, array( 'customer_id' => (int) $customer['id'] ) );

			if ( Cmshop_Repair_Status::COLLECTED === $data['status'] && ( null === $repair || empty( $repair['collected_at'] ) ) ) {
				$data['collected_at'] = current_time( 'mysql' );
			}

			if ( null === $repair ) {
				$id = $repairs->insert_with_ticket( array_merge( $data, array( 'created_by' => get_current_user_id() ) ) );
			} else {
				$repairs->update( $id, $data );
			}
		} catch ( RuntimeException $error ) {
			$this->log( $error );
			$this->redirect( 'cmshop-repairs', 'db_error', $back );
		}

		$this->redirect(
			'cmshop-repairs',
			'repair_saved',
			array(
				'action' => 'edit',
				'id'     => $id,
			)
		);
	}

	/**
	 * Delete a repair job.
	 */
	private function delete_repair(): void {
		Cmshop_Plugin::repairs()->delete( $this->query_id() );
		$this->redirect( 'cmshop-repairs', 'repair_deleted' );
	}

	/**
	 * Open the cash register with the float in the drawer.
	 */
	private function open_shift(): void {
		$posted = $this->posted();

		$this->shift_step(
			static function () use ( $posted ): void {
				Cmshop_Plugin::shifts()->open( get_current_user_id(), sanitize_text_field( (string) ( $posted['opening_float'] ?? '' ) ) );
			},
			'shift_opened',
			$posted
		);
	}

	/**
	 * Cash in or out of the drawer during a shift.
	 */
	private function petty_cash(): void {
		$posted = $this->posted();

		$this->shift_step(
			static function () use ( $posted ): void {
				Cmshop_Plugin::shifts()->petty( get_current_user_id(), sanitize_key( (string) ( $posted['direction'] ?? 'out' ) ), sanitize_text_field( (string) ( $posted['amount'] ?? '' ) ), sanitize_text_field( (string) ( $posted['reason'] ?? '' ) ) );
			},
			'petty_saved',
			$posted
		);
	}

	/**
	 * Close the shift with the counted cash, then show its report.
	 */
	private function close_shift(): void {
		$posted = $this->posted();
		$counts = array_map( 'sanitize_text_field', array_map( 'strval', (array) ( $posted['notes'] ?? array() ) ) );

		try {
			$shift = Cmshop_Plugin::shifts()->close( get_current_user_id(), $counts, sanitize_text_field( (string) ( $posted['note'] ?? '' ) ) );
		} catch ( InvalidArgumentException $error ) {
			Cmshop_Form_State::save( 'shift', $posted, array( 'shift' => $error->getMessage() ) );
			$this->redirect( 'cmshop-shifts', '' );
		}

		$this->redirect(
			'cmshop-shifts',
			'shift_closed',
			array(
				'action' => 'report',
				'id'     => (int) $shift['id'],
			)
		);
	}

	/**
	 * Run a shift change, then come back to the cash register.
	 *
	 * @param callable $step   The change.
	 * @param string   $notice Notice on success.
	 * @param array    $posted Submitted values (shown again on error).
	 */
	private function shift_step( callable $step, string $notice, array $posted ): void {
		try {
			$step();
		} catch ( InvalidArgumentException $error ) {
			Cmshop_Form_State::save( 'shift', $posted, array( 'shift' => $error->getMessage() ) );
			$this->redirect( 'cmshop-shifts', '' );
		} catch ( RuntimeException $error ) {
			$this->log( $error );
			$this->redirect( 'cmshop-shifts', 'db_error' );
		}

		$this->redirect( 'cmshop-shifts', $notice );
	}

	/**
	 * Return items from a sale: stock back, money or store credit back.
	 */
	private function return_items(): void {
		$posted  = $this->posted();
		$sale_id = absint( $posted['sale_id'] ?? 0 );
		$wanted  = array();

		foreach ( (array) ( $posted['qty'] ?? array() ) as $line_id => $quantity ) {
			$wanted[ absint( $line_id ) ] = absint( $quantity );
		}

		try {
			$result = Cmshop_Plugin::returns()->process(
				$sale_id,
				$wanted,
				array_map( 'absint', (array) ( $posted['restock'] ?? array() ) ),
				sanitize_key( (string) ( $posted['method'] ?? '' ) ),
				sanitize_text_field( (string) ( $posted['reason'] ?? '' ) ),
				get_current_user_id()
			);
		} catch ( InvalidArgumentException $error ) {
			Cmshop_Form_State::save( 'return', $posted, array( 'qty' => $error->getMessage() ) );
			$this->redirect(
				'cmshop-sales',
				'',
				array(
					'action' => 'return',
					'id'     => $sale_id,
				)
			);
		} catch ( RuntimeException $error ) {
			$this->log( $error );
			$this->redirect( 'cmshop-sales', 'db_error' );
		}

		$this->redirect(
			'cmshop-sales',
			'items_returned',
			array(
				'action' => 'view',
				'id'     => $sale_id,
			)
		);
	}

	/**
	 * Record money received from a customer against their dues.
	 */
	private function collect_due(): void {
		$posted   = $this->posted();
		$customer = absint( $posted['customer_id'] ?? 0 );
		$back     = array(
			'action'   => 'collect',
			'customer' => $customer,
		);

		try {
			$parts = Cmshop_Plugin::dues()->collect(
				$customer,
				sanitize_text_field( (string) ( $posted['amount'] ?? '' ) ),
				sanitize_key( (string) ( $posted['method'] ?? '' ) ),
				sanitize_text_field( (string) ( $posted['reference'] ?? '' ) ),
				get_current_user_id()
			);
		} catch ( InvalidArgumentException $error ) {
			Cmshop_Form_State::save( 'due', $posted, array( 'amount' => $error->getMessage() ) );
			$this->redirect( 'cmshop-dues', '', $back );
		} catch ( RuntimeException $error ) {
			$this->log( $error );
			$this->redirect( 'cmshop-dues', 'db_error', $back );
		}

		$this->redirect( 'cmshop-dues', 'due_collected', array_merge( $back, array( 'sales' => count( $parts ) ) ) );
	}

	/**
	 * Void a sale and put its stock back.
	 */
	private function void_sale(): void {
		$id = $this->query_id();

		try {
			$voided = Cmshop_Plugin::sales_service()->void( $id );
		} catch ( RuntimeException $error ) {
			$this->log( $error );
			$this->redirect(
				'cmshop-sales',
				'db_error',
				array(
					'action' => 'view',
					'id'     => $id,
				)
			);
		}

		$this->redirect(
			'cmshop-sales',
			$voided ? 'sale_voided' : 'sale_not_voided',
			array(
				'action' => 'view',
				'id'     => $id,
			)
		);
	}

	/**
	 * Add an error when another unsold phone has the same IMEI.
	 *
	 * @param Cmshop_Validation_Result $result Result.
	 * @param int                      $id     Item being edited.
	 * @return Cmshop_Validation_Result
	 */
	private function check_duplicate_imei( Cmshop_Validation_Result $result, int $id ): Cmshop_Validation_Result {
		$data       = $result->data();
		$duplicates = Cmshop_Plugin::items()->find_unsold_by_imeis( array( $data['imei1'], $data['imei2'] ), $id );

		if ( array() === $duplicates ) {
			return $result;
		}

		$other = $duplicates[0];
		/* translators: 1: phone name, 2: stock item ID. */
		$message = sprintf( __( 'This IMEI is already in stock: %1$s (item #%2$d).', 'codereyes-mobile-shop' ), Cmshop_Item::display_name( $other ), (int) $other['id'] );

		return $result->with_error( in_array( $data['imei1'], array( $other['imei1'], $other['imei2'] ), true ) ? 'imei1' : 'imei2', $message );
	}

	/**
	 * Keep only photos that are images in the Media Library.
	 *
	 * Someone who cannot upload files never sees the photo picker, so their
	 * edit keeps the item's existing photos.
	 *
	 * @param array      $data Validated item.
	 * @param array|null $item Item before the edit.
	 * @return array
	 */
	private function checked_photos( array $data, ?array $item ): array {
		if ( ! current_user_can( 'upload_files' ) ) {
			return array_merge(
				$data,
				array(
					'image_id'    => (int) ( $item['image_id'] ?? 0 ),
					'gallery_ids' => (string) ( $item['gallery_ids'] ?? '' ),
				)
			);
		}

		$image   = (int) $data['image_id'];
		$gallery = array_filter( Cmshop_Item::gallery_ids( (string) $data['gallery_ids'] ), 'wp_attachment_is_image' );

		return array_merge(
			$data,
			array(
				'image_id'    => $image > 0 && wp_attachment_is_image( $image ) ? $image : 0,
				'gallery_ids' => Cmshop_Item::gallery_csv( $gallery ),
			)
		);
	}

	/**
	 * Update the website after an item is added or edited.
	 *
	 * @param array|null $before Row before the edit (null when added).
	 * @param int        $id     Item ID.
	 */
	private function refresh_product( ?array $before, int $id ): void {
		$woo   = Cmshop_Plugin::woo_products();
		$after = Cmshop_Plugin::items()->find( $id );

		if ( null === $woo || null === $after ) {
			return;
		}

		try {
			$woo->item_saved( $before, $after );
		} catch ( Exception $error ) {
			$this->log( $error );
		}
	}
}
