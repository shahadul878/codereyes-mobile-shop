<?php
/**
 * Records and voids sales, keeping stock in step.
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for the cashier and are escaped where they are displayed.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * The only place that creates sales or changes stock because of one.
 *
 * Everything happens in one transaction with the stock rows locked, so two
 * tills (or the till and the website) can never sell the same phone.
 */
final class Cmshop_Sales_Service {

	/**
	 * Action fired after stock changes: ( int[] $item_ids, string $source ).
	 */
	public const STOCK_CHANGED = 'cmshop_stock_changed';

	private const MAX_TRADE_INS = 3;

	/**
	 * Payments ledger.
	 *
	 * @var Cmshop_Payment_Repository
	 */
	private $payments;

	/**
	 * Installment schedules.
	 *
	 * @var Cmshop_Installment_Repository
	 */
	private $installments;

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
	 * Customers.
	 *
	 * @var Cmshop_Customer_Repository
	 */
	private $customers;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Item_Repository        $items     Items.
	 * @param Cmshop_Sale_Repository        $sales     Sales.
	 * @param Cmshop_Customer_Repository    $customers Customers.
	 * @param Cmshop_Payment_Repository     $payments  Payments ledger.
	 * @param Cmshop_Installment_Repository $installments Installment schedules.
	 */
	public function __construct( Cmshop_Item_Repository $items, Cmshop_Sale_Repository $sales, Cmshop_Customer_Repository $customers, Cmshop_Payment_Repository $payments, Cmshop_Installment_Repository $installments ) {
		$this->installments = $installments;
		$this->payments     = $payments;
		$this->items        = $items;
		$this->sales        = $sales;
		$this->customers    = $customers;
	}

	/**
	 * Record a sale.
	 *
	 * @param array $order {
	 *     Sale details.
	 *
	 *     @type array  $lines          [ item_id, quantity, unit_price ].
	 *     @type string $customer_name  Optional.
	 *     @type string $customer_phone Optional, normalised.
	 *     @type string $customer_email Optional.
	 *     @type string $discount       Raw amount.
	 *     @type string $amount_paid    Raw amount; empty = paid in full (single-method sales).
	 *     @type string $payment_method Key from Cmshop_Settings::payment_methods() (single-method sales).
	 *     @type array  $payments       Split payment rows [ method, amount, reference ]; overrides the two above.
	 *     @type array  $installments   Optional plan for what is still owed [ count, interval, first_date ].
	 *     @type string $notes          Notes.
	 *     @type string $channel        store|online.
	 *     @type int    $wc_order_id    WooCommerce order.
	 * }
	 * @param int   $user_id Cashier.
	 * @return int Sale ID.
	 * @throws InvalidArgumentException When the sale is not possible (message is for the cashier).
	 */
	public function checkout( array $order, int $user_id ): int {
		$channel = Cmshop_Sale_Repository::CHANNEL_ONLINE === ( $order['channel'] ?? '' ) ? Cmshop_Sale_Repository::CHANNEL_ONLINE : Cmshop_Sale_Repository::CHANNEL_STORE;
		$buyer   = $this->buyer( $order );
		$lines   = (array) ( $order['lines'] ?? array() );
		$trades  = $this->trade_ins( (array) ( $order['trade_ins'] ?? array() ) );

		$result = $this->sales->transaction(
			function () use ( $order, $lines, $buyer, $channel, $user_id, $trades ) {
				$items   = $this->items->lock( array_column( $lines, 'item_id' ) );
				$sale    = Cmshop_Cart::price( $lines, $items, (string) ( $order['discount'] ?? '' ), '' );
				$credit  = (int) array_sum( array_map( 'Cmshop_Money::to_cents', array_column( $trades, 'value' ) ) );
				$methods = array_merge( array_keys( Cmshop_Settings::payment_methods() ), array( Cmshop_Payments::TRADE_IN ) );
				$paid    = Cmshop_Payments::allocate( array_merge( $this->payment_rows( $order, $sale['total'], $credit ), $this->trade_rows( $trades ) ), Cmshop_Money::to_cents( $sale['total'] ), $methods );

				if ( $paid['due'] > 0 && ! isset( $buyer['phone'] ) ) {
					throw new InvalidArgumentException( __( 'Add the customer\'s phone number to sell on credit.', 'codereyes-mobile-shop' ) );
				}

				$customer = isset( $buyer['phone'] ) ? $this->customers->find_or_create( $buyer['name'], $buyer['phone'], $buyer['email'] ) : $buyer;

				$sale_id = $this->sales->insert_with_lines(
					array(
						'channel'        => $channel,
						'status'         => Cmshop_Sale_Repository::STATUS_COMPLETED,
						'customer_id'    => (int) ( $customer['id'] ?? 0 ),
						'customer_name'  => (string) ( $customer['name'] ?? '' ),
						'customer_phone' => (string) ( $customer['phone'] ?? '' ),
						'payment_method' => Cmshop_Payments::summary_method( $paid['entries'], $this->payment_method( (string) ( $order['payment_method'] ?? '' ) ) ),
						'subtotal'       => $sale['subtotal'],
						'discount'       => $sale['discount'],
						'total'          => $sale['total'],
						// What the customer handed over (receipts show change); the ledger keeps what stayed.
						'amount_paid'    => Cmshop_Money::from_cents( $paid['paid'] + $paid['change'] ),
						'cost_total'     => $sale['cost_total'],
						'profit'         => $sale['profit'],
						'wc_order_id'    => (int) ( $order['wc_order_id'] ?? 0 ),
						'notes'          => sanitize_textarea_field( (string) ( $order['notes'] ?? '' ) ),
						'created_by'     => $user_id,
					),
					$sale['lines']
				);

				$invoice = Cmshop_Code::invoice( (string) Cmshop_Settings::get( 'invoice_prefix' ), $sale_id );
				$this->sales->update( $sale_id, array( 'invoice_no' => $invoice ) );
				$this->stock_trade_ins( $trades, $invoice, (string) ( $customer['name'] ?? '' ), $user_id );
				$this->payments->record(
					$paid['entries'],
					array(
						'sale_id'     => $sale_id,
						'customer_id' => (int) ( $customer['id'] ?? 0 ),
						'kind'        => Cmshop_Payment_Repository::KIND_SALE,
						'created_by'  => $user_id,
					)
				);

				$plan = (array) ( $order['installments'] ?? array() );

				if ( $paid['due'] > 0 && (int) ( $plan['count'] ?? 0 ) > 0 ) {
					$this->installments->save_plan(
						$sale_id,
						(int) ( $customer['id'] ?? 0 ),
						Cmshop_Installments::plan( $paid['due'], (int) $plan['count'], (string) ( $plan['first_date'] ?? '' ), (string) ( $plan['interval'] ?? 'month' ) )
					);
				}

				foreach ( $sale['lines'] as $line ) {
					$this->items->adjust_quantity( $items[ $line['item_id'] ], -$line['quantity'] );
				}

				return array( $sale_id, array_column( $sale['lines'], 'item_id' ) );
			}
		);

		do_action( 'cmshop_stock_changed', $result[1], $channel );

		return (int) $result[0];
	}

	/**
	 * Void a completed sale and put its stock back.
	 *
	 * @param int    $sale_id Sale ID.
	 * @param string $source  store|online (online skips pushing stock to WooCommerce).
	 * @return bool False when the sale was not completed.
	 */
	public function void( int $sale_id, string $source = Cmshop_Sale_Repository::CHANNEL_STORE ): bool {
		$item_ids = $this->sales->transaction(
			function () use ( $sale_id ) {
				$sale = $this->sales->lock_sale( $sale_id );

				if ( null === $sale || Cmshop_Sale_Repository::STATUS_COMPLETED !== $sale['status'] ) {
					return null;
				}

				$lines = $this->sales->lines( $sale_id );
				$items = $this->items->lock( array_column( $lines, 'item_id' ) );

				foreach ( $lines as $line ) {
					if ( isset( $items[ (int) $line['item_id'] ] ) ) {
						$this->items->adjust_quantity( $items[ (int) $line['item_id'] ], (int) $line['quantity'] );
					}
				}

				$this->sales->update( $sale_id, array( 'status' => Cmshop_Sale_Repository::STATUS_VOID ) );
				$this->refund_all( $sale );

				return array_map( 'intval', array_column( $lines, 'item_id' ) );
			}
		);

		if ( null === $item_ids ) {
			return false;
		}

		do_action( 'cmshop_stock_changed', $item_ids, $source );

		return true;
	}

	/**
	 * Who is buying: name only for a walk-in, or name, phone and email.
	 *
	 * The customer row is created inside the sale transaction, so a failed
	 * sale leaves no stray customer behind.
	 *
	 * @param array $order Order.
	 * @return array
	 * @throws InvalidArgumentException When the phone number is invalid.
	 */
	private function buyer( array $order ): array {
		$name  = mb_substr( sanitize_text_field( (string) ( $order['customer_name'] ?? '' ) ), 0, 150 );
		$phone = Cmshop_Phone_Number::normalize( (string) ( $order['customer_phone'] ?? '' ) );
		$email = sanitize_email( (string) ( $order['customer_email'] ?? '' ) );

		if ( '' === ltrim( $phone, '+' ) ) {
			return array( 'name' => '' === $name ? __( 'Walk-in customer', 'codereyes-mobile-shop' ) : $name );
		}

		if ( ! Cmshop_Phone_Number::is_valid( $phone ) ) {
			throw new InvalidArgumentException( __( 'The customer phone number should have 6 to 15 digits.', 'codereyes-mobile-shop' ) );
		}

		return array(
			'name'  => '' === $name ? $phone : $name,
			'phone' => $phone,
			'email' => $email,
		);
	}

	/**
	 * Payment rows from the order: split rows, or one row for a single method.
	 *
	 * @param array  $order  Order.
	 * @param string $total  Sale total.
	 * @param int    $credit Trade-in value in cents (a single unfilled method pays the rest).
	 * @return array[]
	 */
	private function payment_rows( array $order, string $total, int $credit ): array {
		if ( isset( $order['payments'] ) && is_array( $order['payments'] ) ) {
			return array_values(
				array_filter(
					$order['payments'],
					static function ( $row ): bool {
						return is_array( $row ) && Cmshop_Payments::TRADE_IN !== ( $row['method'] ?? '' );
					}
				)
			);
		}

		$amount = trim( (string) ( $order['amount_paid'] ?? '' ) );

		return array(
			array(
				'method' => $this->payment_method( (string) ( $order['payment_method'] ?? '' ) ),
				'amount' => '' === $amount ? Cmshop_Money::from_cents( max( 0, Cmshop_Money::to_cents( $total ) - $credit ) ) : $amount,
			),
		);
	}

	/**
	 * Validate the phones a customer trades in (at most 3).
	 *
	 * @param array[] $raw Trade-in forms.
	 * @return array[] Validated trade-ins (item, value, checks).
	 * @throws InvalidArgumentException With the first problem, for the cashier.
	 */
	private function trade_ins( array $raw ): array {
		if ( count( $raw ) > self::MAX_TRADE_INS ) {
			throw new InvalidArgumentException( __( 'Take at most 3 phones in trade on one sale.', 'codereyes-mobile-shop' ) );
		}

		$trades = array();
		$imeis  = array();

		foreach ( $raw as $form ) {
			$result = Cmshop_Validator::trade_in( is_array( $form ) ? $form : array() );

			if ( ! $result->is_valid() ) {
				/* translators: %s: what is wrong with the traded-in phone. */
				throw new InvalidArgumentException( sprintf( __( 'Trade-in: %s', 'codereyes-mobile-shop' ), (string) current( $result->errors() ) ) );
			}

			$trades[] = $result->data();
			$imeis[]  = $result->data()['item']['imei1'];
		}

		if ( count( $imeis ) !== count( array_unique( $imeis ) ) ) {
			throw new InvalidArgumentException( __( 'Trade-in: the same IMEI was entered twice.', 'codereyes-mobile-shop' ) );
		}

		return $trades;
	}

	/**
	 * Ledger rows for trade-ins: they pay like cash, labelled with the phone.
	 *
	 * @param array[] $trades Validated trade-ins.
	 * @return array[]
	 */
	private function trade_rows( array $trades ): array {
		return array_map(
			static function ( array $trade ): array {
				return array(
					'method'    => Cmshop_Payments::TRADE_IN,
					'amount'    => $trade['value'],
					'reference' => mb_substr( $trade['item']['name'] . ' · IMEI ' . $trade['item']['imei1'], 0, 100 ),
				);
			},
			$trades
		);
	}

	/**
	 * Put traded-in phones into stock as used phones. Call inside the sale transaction.
	 *
	 * @param array[] $trades   Validated trade-ins.
	 * @param string  $invoice  Sale invoice number.
	 * @param string  $customer Customer name.
	 * @param int     $user_id  Cashier.
	 * @throws InvalidArgumentException When a phone is already in stock.
	 */
	private function stock_trade_ins( array $trades, string $invoice, string $customer, int $user_id ): void {
		$labels = Cmshop_Validator::trade_in_checks();

		foreach ( $trades as $trade ) {
			$item = $trade['item'];

			if ( array() !== $this->items->find_unsold_by_imeis( array_filter( array( $item['imei1'], $item['imei2'] ) ) ) ) {
				/* translators: %s: IMEI. */
				throw new InvalidArgumentException( sprintf( __( 'Trade-in: IMEI %s is already in your stock.', 'codereyes-mobile-shop' ), $item['imei1'] ) );
			}

			$checks = array_map(
				static function ( string $key ) use ( $labels ): string {
					return $labels[ $key ];
				},
				$trade['checks']
			);

			$this->items->insert(
				array_merge(
					$item,
					array(
						/* translators: %s: customer name. */
						'supplier'   => mb_substr( sprintf( __( 'Trade-in: %s', 'codereyes-mobile-shop' ), $customer ), 0, 150 ),
						/* translators: 1: invoice number, 2: checks done. */
						'notes'      => sprintf( __( 'Taken in trade on %1$s. Checked: %2$s', 'codereyes-mobile-shop' ), $invoice, array() === $checks ? '—' : implode( ', ', $checks ) ),
						'created_by' => $user_id,
					)
				)
			);
		}
	}

	/**
	 * Give back everything still held for a voided sale, method by method.
	 *
	 * @param array $sale Sale row.
	 */
	private function refund_all( array $sale ): void {
		$entries = array();

		foreach ( $this->payments->net_by_method( (int) $sale['id'] ) as $method => $amount ) {
			$cents = Cmshop_Money::to_cents( (string) $amount );

			if ( $cents > 0 ) {
				$entries[] = array(
					'method' => (string) $method,
					'amount' => Cmshop_Money::from_cents( -$cents ),
				);
			}
		}

		$this->payments->record(
			$entries,
			array(
				'sale_id'     => (int) $sale['id'],
				'customer_id' => (int) $sale['customer_id'],
				'kind'        => Cmshop_Payment_Repository::KIND_REFUND,
				'created_by'  => get_current_user_id(),
			)
		);
	}

	/**
	 * A known payment method, defaulting to cash.
	 *
	 * @param string $method Submitted method.
	 * @return string
	 */
	private function payment_method( string $method ): string {
		return array_key_exists( $method, Cmshop_Settings::payment_methods() ) ? $method : 'cash';
	}
}
