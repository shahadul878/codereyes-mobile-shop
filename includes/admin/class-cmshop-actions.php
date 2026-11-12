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
final class Cmshop_Actions {

	/**
	 * Action name => method.
	 */
	private const ACTIONS = array(
		'save_item'       => 'save_item',
		'delete_item'     => 'delete_item',
		'publish_item'    => 'publish_item',
		'unpublish_item'  => 'unpublish_item',
		'save_customer'   => 'save_customer',
		'delete_customer' => 'delete_customer',
		'save_repair'     => 'save_repair',
		'delete_repair'   => 'delete_repair',
		'void_sale'       => 'void_sale',
	);

	/**
	 * Hook in.
	 */
	public function register(): void {
		foreach ( self::ACTIONS as $action => $method ) {
			add_action(
				'admin_post_cmshop_' . $action,
				function () use ( $action, $method ) {
					$this->guard( $action );
					$this->$method();
				}
			);
		}
	}

	/**
	 * Save a stock item.
	 */
	private function save_item(): void {
		$id     = $this->posted_id();
		$items  = Cmshop_Plugin::items();
		$item   = $id > 0 ? $items->find( $id ) : null;
		$result = Cmshop_Validator::item( $this->posted() );
		$data   = $result->data();

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
	 * Void a sale and restock its items.
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

	/**
	 * Stop unless the user may act and the nonce is valid.
	 *
	 * @param string $action Action name.
	 */
	private function guard( string $action ): void {
		if ( ! current_user_can( Cmshop_Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'codereyes-mobile-shop' ), 403 );
		}

		check_admin_referer( 'cmshop_' . $action );
	}

	/**
	 * Submitted form fields, unslashed.
	 *
	 * @return array
	 */
	private function posted(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in guard(); every field is sanitised by Cmshop_Input.
		$posted = wp_unslash( $_POST );

		unset( $posted['_wpnonce'], $posted['_wp_http_referer'], $posted['action'] );

		return is_array( $posted ) ? $posted : array();
	}

	/**
	 * ID from the form.
	 *
	 * @return int
	 */
	private function posted_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked in guard().
		return isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	}

	/**
	 * ID from a link.
	 *
	 * @return int
	 */
	private function query_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked in guard().
		return isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	}

	/**
	 * Server-side log of an unexpected failure.
	 *
	 * @param Throwable $error Error.
	 */
	private function log( Throwable $error ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side log; users see a generic notice.
		error_log( 'Codereyes Mobile Shop: ' . $error->getMessage() );
	}

	/**
	 * Redirect to a plugin page and stop.
	 *
	 * @param string $page   Page slug.
	 * @param string $notice Notice code ('' for none).
	 * @param array  $args   Extra query args.
	 */
	private function redirect( string $page, string $notice, array $args = array() ): void {
		$args = '' === $notice ? $args : array_merge( $args, array( 'cmshop_notice' => $notice ) );

		wp_safe_redirect( Cmshop_View::url( $page, $args ) );
		exit;
	}
}
