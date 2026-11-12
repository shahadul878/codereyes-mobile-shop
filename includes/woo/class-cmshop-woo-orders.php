<?php
/**
 * Records paid WooCommerce orders as online sales.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Paid order → sale (stock taken off the counter); cancelled/refunded → sale voided.
 */
final class Cmshop_Woo_Orders {

	/**
	 * Order statuses that mean the order is paid.
	 */
	private const PAID = array( 'processing', 'completed' );

	/**
	 * Order statuses that undo the sale.
	 */
	private const UNDONE = array( 'cancelled', 'refunded', 'failed' );

	/**
	 * Items.
	 *
	 * @var Cmshop_Item_Repository
	 */
	private $items;

	/**
	 * Sales.
	 *
	 * @var Cmshop_Sale_Repository
	 */
	private $sales;

	/**
	 * Sales service.
	 *
	 * @var Cmshop_Sales_Service
	 */
	private $service;

	/**
	 * Model listings (choose the phone for a colour/storage order).
	 *
	 * @var Cmshop_Woo_Models
	 */
	private $models;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Item_Repository $items   Items.
	 * @param Cmshop_Sale_Repository $sales   Sales.
	 * @param Cmshop_Sales_Service   $service Sales service.
	 * @param Cmshop_Woo_Models      $models  Model listings.
	 */
	public function __construct( Cmshop_Item_Repository $items, Cmshop_Sale_Repository $sales, Cmshop_Sales_Service $service, Cmshop_Woo_Models $models ) {
		$this->items   = $items;
		$this->sales   = $sales;
		$this->service = $service;
		$this->models  = $models;
	}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
	}

	/**
	 * React to an order status change.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $from     Old status.
	 * @param string $to       New status.
	 */
	public function on_status_changed( $order_id, $from, $to ): void {
		unset( $from );

		if ( ! Cmshop_Settings::woo_enabled() ) {
			return;
		}

		$order = wc_get_order( (int) $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( in_array( $to, self::PAID, true ) ) {
			$this->record( $order );
		} elseif ( in_array( $to, self::UNDONE, true ) ) {
			$this->undo( $order );
		}
	}

	/**
	 * Record the order once.
	 *
	 * @param WC_Order $order Order.
	 */
	private function record( WC_Order $order ): void {
		$existing = $this->sales->find_by_order( (int) $order->get_id() );

		if ( null !== $existing && Cmshop_Sale_Repository::STATUS_COMPLETED === $existing['status'] ) {
			return;
		}

		try {
			$lines = $this->lines( $order );

			if ( array() === $lines ) {
				return;
			}

			$sale_id = $this->service->checkout(
				array(
					'lines'          => $lines,
					'customer_name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'customer_phone' => Cmshop_Phone_Number::normalize( (string) $order->get_billing_phone() ),
					'customer_email' => (string) $order->get_billing_email(),
					'payment_method' => 'online',
					'channel'        => Cmshop_Sale_Repository::CHANNEL_ONLINE,
					'wc_order_id'    => (int) $order->get_id(),
					/* translators: %d: WooCommerce order number. */
					'notes'          => sprintf( __( 'Website order #%d', 'codereyes-mobile-shop' ), (int) $order->get_id() ),
				),
				0
			);
			$sale    = $this->sales->find( $sale_id );
			/* translators: %s: invoice number. */
			$note = sprintf( __( 'Mobile Shop: recorded as sale %s and removed from shop stock.', 'codereyes-mobile-shop' ), (string) ( $sale['invoice_no'] ?? $sale_id ) );
			$order->add_order_note( $note . $this->imei_note( $sale_id ) );
		} catch ( InvalidArgumentException $error ) {
			// Usually: the phone was sold at the counter before the website stock updated.
			/* translators: %s: reason. */
			$order->add_order_note( sprintf( __( 'Mobile Shop: could not take this order from shop stock — %s Check the stock before shipping.', 'codereyes-mobile-shop' ), $error->getMessage() ) );
		} catch ( RuntimeException $error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side log of a database failure.
			error_log( 'Codereyes Mobile Shop: order ' . $order->get_id() . ' not recorded: ' . $error->getMessage() );
			$order->add_order_note( __( 'Mobile Shop: a database error stopped this order being recorded. Record it at the counter.', 'codereyes-mobile-shop' ) );
		}
	}

	/**
	 * Void the sale of a cancelled or refunded order.
	 *
	 * @param WC_Order $order Order.
	 */
	private function undo( WC_Order $order ): void {
		$sale = $this->sales->find_by_order( (int) $order->get_id() );

		// Push shop stock back to the website: WooCommerce restocks a cancelled
		// order itself but not a refunded or failed one. Stock is set to the
		// shop's count, so a cancelled order is not counted twice.
		if ( null !== $sale && $this->service->void( (int) $sale['id'], Cmshop_Sale_Repository::CHANNEL_STORE ) ) {
			$order->add_order_note( __( 'Mobile Shop: sale voided and items returned to shop stock.', 'codereyes-mobile-shop' ) );
		}
	}

	/**
	 * Sale lines for order items linked to stock, priced at what the customer paid.
	 *
	 * @param WC_Order $order Order.
	 * @return array[]
	 * @throws InvalidArgumentException When a colour/storage option has too few phones in the shop.
	 */
	private function lines( WC_Order $order ): array {
		$lines = array();

		foreach ( $order->get_items() as $order_item ) {
			$quantity = $order_item instanceof WC_Order_Item_Product ? (int) $order_item->get_quantity() : 0;

			if ( $quantity < 1 ) {
				continue;
			}

			$unit_price = Cmshop_Money::from_cents( intdiv( max( 0, (int) round( (float) $order_item->get_total() * 100 ) ), $quantity ) );
			$model_ids  = $this->model_phones( $order_item, $quantity );

			if ( null !== $model_ids ) {
				foreach ( $model_ids as $item_id ) {
					$lines[] = array(
						'item_id'    => $item_id,
						'quantity'   => 1,
						'unit_price' => $unit_price,
					);
				}
				continue;
			}

			$item = $this->items->find_by_product( (int) $order_item->get_product_id() );

			if ( null !== $item ) {
				$lines[] = array(
					'item_id'    => (int) $item['id'],
					'quantity'   => $quantity,
					'unit_price' => $unit_price,
				);
			}
		}

		return $lines;
	}

	/**
	 * Phones for a colour/storage option of a model listing.
	 *
	 * @param WC_Order_Item_Product $order_item Order line.
	 * @param int                   $quantity   Phones ordered.
	 * @return int[]|null Item IDs, or null when the line is not a model option.
	 * @throws InvalidArgumentException When the shop has too few of that option.
	 */
	private function model_phones( WC_Order_Item_Product $order_item, int $quantity ): ?array {
		$variation = $order_item->get_variation_id() > 0 ? wc_get_product( (int) $order_item->get_variation_id() ) : null;
		$ids       = $variation instanceof WC_Product ? $this->models->allocate( $variation, $quantity ) : null;

		if ( null !== $ids && count( $ids ) < $quantity ) {
			throw new InvalidArgumentException(
				esc_html(
					sprintf(
						/* translators: 1: phones in stock, 2: product and option, e.g. "Galaxy A15 - Black, 128GB". */
						__( 'only %1$d of %2$s left in the shop.', 'codereyes-mobile-shop' ),
						count( $ids ),
						$order_item->get_name()
					)
				)
			);
		}

		return $ids;
	}

	/**
	 * " IMEI: …" listing the phones a sale took, for the private order note.
	 *
	 * @param int $sale_id Sale.
	 * @return string
	 */
	private function imei_note( int $sale_id ): string {
		$imeis = array_filter( array_map( 'strval', array_column( $this->sales->lines( $sale_id ), 'imei' ) ), 'strlen' );

		return array() === $imeis ? '' : ' IMEI: ' . implode( ', ', $imeis );
	}
}
