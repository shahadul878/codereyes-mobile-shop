<?php
/**
 * Returning items from a sale: stock back, money back (or store credit).
 *
 * @package CodereyesMobileShop
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for staff and are escaped where they are displayed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * A return refunds the items' share of what was paid. Items can go back into
 * stock or stay out (faulty). The refund is paid in cash, mobile banking,
 * card or bank transfer, or kept as store credit for an exchange.
 */
final class Cmshop_Returns_Service {

	public const STORE_CREDIT = 'store_credit';

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
	 * Payments ledger.
	 *
	 * @var Cmshop_Payment_Repository
	 */
	private $payments;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Item_Repository     $items     Items.
	 * @param Cmshop_Sale_Repository     $sales     Sales.
	 * @param Cmshop_Customer_Repository $customers Customers.
	 * @param Cmshop_Payment_Repository  $payments  Payments ledger.
	 */
	public function __construct( Cmshop_Item_Repository $items, Cmshop_Sale_Repository $sales, Cmshop_Customer_Repository $customers, Cmshop_Payment_Repository $payments ) {
		$this->items     = $items;
		$this->sales     = $sales;
		$this->customers = $customers;
		$this->payments  = $payments;
	}

	/**
	 * Ways to give the money back.
	 *
	 * @param bool $has_customer Whether the sale has a customer (store credit needs one).
	 * @return array<string,string>
	 */
	public static function refund_methods( bool $has_customer ): array {
		$methods = Cmshop_Settings::payment_methods();
		unset( $methods['online'] );

		return $has_customer ? array_merge( $methods, array( self::STORE_CREDIT => __( 'Store credit (for an exchange)', 'codereyes-mobile-shop' ) ) ) : $methods;
	}

	/**
	 * Return items from a sale.
	 *
	 * @param int             $sale_id Sale.
	 * @param array<int, int> $wanted  Line ID => quantity to return.
	 * @param array<int, int> $restock Line IDs to put back into stock.
	 * @param string          $method  Refund method (payment method or store_credit).
	 * @param string          $reason  Why (kept on the ledger).
	 * @param int             $user_id Staff member.
	 * @return array{refund: int, all: bool}
	 * @throws InvalidArgumentException With a message for staff.
	 */
	public function process( int $sale_id, array $wanted, array $restock, string $method, string $reason, int $user_id ): array {
		$result = $this->sales->transaction(
			function () use ( $sale_id, $wanted, $restock, $method, $reason, $user_id ) {
				$sale = $this->sales->lock_sale( $sale_id );

				if ( null === $sale || Cmshop_Sale_Repository::STATUS_COMPLETED !== $sale['status'] ) {
					throw new InvalidArgumentException( __( 'Only completed sales can have returns.', 'codereyes-mobile-shop' ) );
				}

				if ( Cmshop_Money::to_cents( (string) $sale['amount_paid'] ) < Cmshop_Money::to_cents( (string) $sale['total'] ) ) {
					throw new InvalidArgumentException( __( 'This sale is not fully paid. Collect the balance first, or void the sale.', 'codereyes-mobile-shop' ) );
				}

				if ( ! array_key_exists( $method, self::refund_methods( (int) $sale['customer_id'] > 0 ) ) ) {
					throw new InvalidArgumentException( __( 'Choose how to give the money back.', 'codereyes-mobile-shop' ) );
				}

				$lines  = $this->sales->lines( $sale_id );
				$priced = Cmshop_Returns::price( $lines, $wanted, Cmshop_Money::to_cents( (string) $sale['subtotal'] ), Cmshop_Money::to_cents( (string) $sale['discount'] ) );
				$all    = Cmshop_Returns::all_returned( $lines, $priced['quantities'] );
				$moved  = $this->move_lines( $lines, $priced['quantities'], $restock );

				$this->sales->update(
					$sale_id,
					array(
						'refunded'      => Cmshop_Money::from_cents( Cmshop_Money::to_cents( (string) $sale['refunded'] ) + $priced['refund'] ),
						'refunded_cost' => Cmshop_Money::from_cents( Cmshop_Money::to_cents( (string) $sale['refunded_cost'] ) + $priced['cost'] ),
						'status'        => $all ? Cmshop_Sale_Repository::STATUS_RETURNED : Cmshop_Sale_Repository::STATUS_COMPLETED,
					)
				);

				$this->payments->record(
					array(
						array(
							'method' => $method,
							'amount' => Cmshop_Money::from_cents( -$priced['refund'] ),
						),
					),
					array(
						'sale_id'     => $sale_id,
						'customer_id' => (int) $sale['customer_id'],
						'kind'        => Cmshop_Payment_Repository::KIND_REFUND,
						'note'        => sanitize_text_field( $reason ),
						'created_by'  => $user_id,
					)
				);

				if ( self::STORE_CREDIT === $method ) {
					$customer = $this->customers->lock( (int) $sale['customer_id'] );

					if ( null === $customer ) {
						throw new InvalidArgumentException( __( 'Store credit needs a customer on the sale.', 'codereyes-mobile-shop' ) );
					}

					$this->customers->adjust_credit( $customer, $priced['refund'] );
				}

				return array(
					'refund' => $priced['refund'],
					'all'    => $all,
					'moved'  => $moved,
				);
			}
		);

		if ( array() !== $result['moved'] ) {
			do_action( Cmshop_Sales_Service::STOCK_CHANGED, $result['moved'], Cmshop_Sale_Repository::CHANNEL_STORE );
		}

		return array(
			'refund' => $result['refund'],
			'all'    => $result['all'],
		);
	}

	/**
	 * Mark lines returned and put chosen items back into stock.
	 *
	 * @param array[]         $lines      Sale lines.
	 * @param array<int, int> $quantities Line ID => quantity returned.
	 * @param array<int, int> $restock    Line IDs to restock.
	 * @return int[] Item IDs whose stock changed.
	 */
	private function move_lines( array $lines, array $quantities, array $restock ): array {
		$by_id   = array_column( $lines, null, 'id' );
		$restock = array_map( 'intval', $restock );
		$back    = array();

		foreach ( $quantities as $line_id => $quantity ) {
			$line = $by_id[ $line_id ];

			$this->sales->update_line( (int) $line_id, array( 'returned_qty' => (int) $line['returned_qty'] + $quantity ) );

			if ( in_array( (int) $line_id, $restock, true ) ) {
				$back[ (int) $line['item_id'] ] = ( $back[ (int) $line['item_id'] ] ?? 0 ) + $quantity;
			}
		}

		$locked = $this->items->lock( array_keys( $back ) );

		foreach ( $back as $item_id => $quantity ) {
			if ( isset( $locked[ $item_id ] ) ) {
				$this->items->adjust_quantity( $locked[ $item_id ], $quantity );
			}
		}

		return array_keys( $back );
	}
}
