<?php
/**
 * Buying stock from suppliers and paying them.
 *
 * @package CodereyesMobileShop
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for staff and are escaped where they are displayed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * A purchase adds every scanned phone and accessory to stock in one go and
 * records what the shop owes the supplier. Paying a supplier settles their
 * oldest deliveries first and leaves the cash drawer as a "supplier" payment.
 */
final class Cmshop_Purchase_Service {

	private const PAY_METHODS = array( 'cash', 'card', 'mobile_banking', 'bank_transfer' );

	/**
	 * Purchases.
	 *
	 * @var Cmshop_Purchase_Repository
	 */
	private $purchases;

	/**
	 * Suppliers.
	 *
	 * @var Cmshop_Supplier_Repository
	 */
	private $suppliers;

	/**
	 * Stock.
	 *
	 * @var Cmshop_Item_Repository
	 */
	private $items;

	/**
	 * Payments ledger.
	 *
	 * @var Cmshop_Payment_Repository
	 */
	private $payments;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Purchase_Repository $purchases Purchases.
	 * @param Cmshop_Supplier_Repository $suppliers Suppliers.
	 * @param Cmshop_Item_Repository     $items     Stock.
	 * @param Cmshop_Payment_Repository  $payments  Payments ledger.
	 */
	public function __construct( Cmshop_Purchase_Repository $purchases, Cmshop_Supplier_Repository $suppliers, Cmshop_Item_Repository $items, Cmshop_Payment_Repository $payments ) {
		$this->purchases = $purchases;
		$this->suppliers = $suppliers;
		$this->items     = $items;
		$this->payments  = $payments;
	}

	/**
	 * Payment methods for paying suppliers.
	 *
	 * @return array<string,string>
	 */
	public static function methods(): array {
		return array_intersect_key( Cmshop_Settings::payment_methods(), array_flip( self::PAY_METHODS ) );
	}

	/**
	 * Record a delivery: stock in, money owed, optional payment now.
	 *
	 * @param array $input   supplier_id or supplier_name/supplier_phone, ref_no, purchased_on, note, lines, paid, method, reference.
	 * @param int   $user_id Staff member.
	 * @return int Purchase ID.
	 * @throws Cmshop_Form_Errors With field errors (RuntimeException when the database fails; nothing is saved).
	 */
	public function record( array $input, int $user_id ): int {
		$parsed   = Cmshop_Purchase_Lines::parse( (array) ( $input['lines'] ?? array() ) );
		$errors   = $parsed['errors'];
		$supplier = $this->supplier_for( $input, $errors );
		$paid     = $this->paid( $input, $parsed['total'], $errors );
		$errors   = array_merge( $errors, $this->in_stock_errors( $parsed['lines'] ), $this->restock_errors( $parsed['lines'] ) );

		if ( array() !== $errors ) {
			throw new Cmshop_Form_Errors( $errors );
		}

		$restocked = array();
		$id        = $this->items->transaction(
			function () use ( $input, $parsed, $supplier, $paid, $user_id, &$restocked ): int {
				$supplier_id = $this->save_supplier( $supplier );
				$purchase_id = $this->purchases->insert(
					array(
						'supplier_id'  => $supplier_id,
						'ref_no'       => mb_substr( sanitize_text_field( (string) ( $input['ref_no'] ?? '' ) ), 0, 60 ),
						'purchased_on' => $this->date( (string) ( $input['purchased_on'] ?? '' ) ),
						'total'        => Cmshop_Money::from_cents( $parsed['total'] ),
						'paid'         => Cmshop_Money::from_cents( $paid ),
						'units'        => $parsed['units'],
						'note'         => mb_substr( sanitize_text_field( (string) ( $input['note'] ?? '' ) ), 0, 255 ),
						'created_by'   => $user_id,
					)
				);

				foreach ( $parsed['lines'] as $line ) {
					$restocked = array_merge( $restocked, $this->stock_in( $line, $purchase_id, $supplier['name'], $user_id ) );
				}

				$this->pay_out( $supplier_id, $paid, $input, $user_id, sprintf( '#%d', $purchase_id ) );

				return $purchase_id;
			}
		);

		if ( array() !== $restocked ) {
			do_action( Cmshop_Sales_Service::STOCK_CHANGED, $restocked, Cmshop_Sale_Repository::CHANNEL_STORE );
		}

		return $id;
	}

	/**
	 * Pay a supplier: oldest deliveries are settled first.
	 *
	 * @param int    $supplier_id Supplier.
	 * @param string $amount      Amount typed by staff.
	 * @param string $method      Payment method.
	 * @param string $reference   Bank or bKash reference.
	 * @param int    $user_id     Staff member.
	 * @return int Purchases the payment went to.
	 * @throws InvalidArgumentException With a message for staff (RuntimeException when the database fails).
	 */
	public function pay( int $supplier_id, string $amount, string $method, string $reference, int $user_id ): int {
		$parsed = Cmshop_Money::parse( $amount );
		$cents  = null === $parsed ? 0 : Cmshop_Money::to_cents( $parsed );

		if ( null === $this->suppliers->find( $supplier_id ) ) {
			throw new InvalidArgumentException( __( 'That supplier no longer exists.', 'codereyes-mobile-shop' ) );
		}

		if ( $cents <= 0 ) {
			throw new InvalidArgumentException( __( 'Enter the amount you paid.', 'codereyes-mobile-shop' ) );
		}

		return $this->purchases->transaction(
			function () use ( $supplier_id, $cents, $method, $reference, $user_id ): int {
				$open = $this->purchases->unpaid( $supplier_id, true );
				$owed = 0;
				$dues = array();

				foreach ( $open as $row ) {
					$due    = Cmshop_Money::to_cents( (string) $row['total'] ) - Cmshop_Money::to_cents( (string) $row['paid'] );
					$owed  += $due;
					$dues[] = array(
						'id'   => (int) $row['id'],
						'due'  => $due,
						'paid' => Cmshop_Money::to_cents( (string) $row['paid'] ),
					);
				}

				if ( $cents > $owed ) {
					/* translators: %s: amount owed to the supplier. */
					throw new InvalidArgumentException( sprintf( __( 'That is more than you owe this supplier (%s).', 'codereyes-mobile-shop' ), Cmshop_Settings::money( Cmshop_Money::from_cents( $owed ) ) ) );
				}

				$paid_now = array_column( $dues, 'paid', 'id' );
				$parts    = Cmshop_Installments::allocate( $cents, $dues );

				foreach ( $parts as $part ) {
					$this->purchases->update( $part['sale_id'], array( 'paid' => Cmshop_Money::from_cents( $paid_now[ $part['sale_id'] ] + $part['amount'] ) ) );
				}

				$numbers = array_map(
					static function ( array $part ): string {
						return '#' . $part['sale_id'];
					},
					$parts
				);

				$this->pay_out(
					$supplier_id,
					$cents,
					array(
						'method'    => $method,
						'reference' => $reference,
					),
					$user_id,
					implode( ', ', $numbers )
				);

				return count( $parts );
			}
		);
	}

	/**
	 * The chosen supplier, or a new one from name and phone.
	 *
	 * @param array                $input  Form input.
	 * @param array<string,string> $errors Errors so far (added to).
	 * @return array{id: int, name: string, phone: string}
	 */
	private function supplier_for( array $input, array &$errors ): array {
		$id = absint( $input['supplier_id'] ?? 0 );

		if ( $id > 0 ) {
			$row = $this->suppliers->find( $id );

			if ( null === $row ) {
				$errors['supplier_id'] = __( 'That supplier no longer exists. Pick another.', 'codereyes-mobile-shop' );
				return array(
					'id'    => 0,
					'name'  => '',
					'phone' => '',
				);
			}

			return array(
				'id'    => $id,
				'name'  => (string) $row['name'],
				'phone' => (string) $row['phone'],
			);
		}

		$name = mb_substr( trim( sanitize_text_field( (string) ( $input['supplier_name'] ?? '' ) ) ), 0, 150 );

		if ( '' === $name ) {
			$errors['supplier_name'] = __( 'Pick a supplier or type a new supplier name.', 'codereyes-mobile-shop' );
		}

		$existing = '' === $name ? null : $this->suppliers->find_by_name( $name );

		return array(
			'id'    => null === $existing ? 0 : (int) $existing['id'],
			'name'  => null === $existing ? $name : (string) $existing['name'],
			'phone' => Cmshop_Phone_Number::normalize( (string) ( $input['supplier_phone'] ?? '' ) ),
		);
	}

	/**
	 * Amount paid now, between zero and the purchase total (cents).
	 *
	 * @param array                $input  Form input.
	 * @param int                  $total  Purchase total (cents).
	 * @param array<string,string> $errors Errors so far (added to).
	 * @return int
	 */
	private function paid( array $input, int $total, array &$errors ): int {
		$parsed = Cmshop_Money::parse_or_zero( (string) ( $input['paid'] ?? '' ) );
		$cents  = null === $parsed ? 0 : Cmshop_Money::to_cents( $parsed );

		if ( null === $parsed || $cents > $total ) {
			$errors['paid'] = __( 'Paid now must be between 0 and the purchase total.', 'codereyes-mobile-shop' );
		}

		if ( $cents > 0 && ! array_key_exists( (string) ( $input['method'] ?? '' ), self::methods() ) ) {
			$errors['method'] = __( 'Choose how you paid the supplier.', 'codereyes-mobile-shop' );
		}

		return $cents;
	}

	/**
	 * Errors for IMEIs that are already in stock.
	 *
	 * @param array[] $lines Parsed lines.
	 * @return array<string,string>
	 */
	private function in_stock_errors( array $lines ): array {
		$errors = array();

		foreach ( $lines as $index => $line ) {
			$found = array_column( $this->items->find_unsold_by_imeis( $line['imeis'] ), 'imei1' );

			if ( array() !== $found ) {
				/* translators: 1: line number, 2: IMEIs. */
				$errors[ 'lines-' . $index . '-imeis' ] = sprintf( __( 'Line %1$d: already in stock: %2$s.', 'codereyes-mobile-shop' ), $index + 1, implode( ', ', $found ) );
			}
		}

		return $errors;
	}

	/**
	 * Errors for restock lines whose item is not an accessory in the shop.
	 *
	 * @param array[] $lines Parsed lines.
	 * @return array<string,string>
	 */
	private function restock_errors( array $lines ): array {
		$errors = array();

		foreach ( $lines as $index => $line ) {
			if ( $line['item_id'] <= 0 ) {
				continue;
			}

			$item = $this->items->find( $line['item_id'] );

			if ( null === $item || Cmshop_Item::TYPE_ACCESSORY !== $item['item_type'] ) {
				/* translators: %d: line number. */
				$errors[ 'lines-' . $index . '-item_id' ] = sprintf( __( 'Line %d: that accessory is no longer in stock records. Pick it again.', 'codereyes-mobile-shop' ), $index + 1 );
			}
		}

		return $errors;
	}

	/**
	 * The supplier's ID, creating them when new.
	 *
	 * @param array $supplier id, name, phone.
	 * @return int
	 */
	private function save_supplier( array $supplier ): int {
		if ( $supplier['id'] > 0 ) {
			return $supplier['id'];
		}

		return $this->suppliers->insert(
			array(
				'name'  => $supplier['name'],
				'phone' => $supplier['phone'],
			)
		);
	}

	/**
	 * Put one line into stock.
	 *
	 * @param array  $line        Parsed line.
	 * @param int    $purchase_id Purchase.
	 * @param string $supplier    Supplier name (kept on each item).
	 * @param int    $user_id     Staff member.
	 * @return int[] Restocked item IDs (new items need no stock push).
	 * @throws RuntimeException When an item fails validation or saving.
	 */
	private function stock_in( array $line, int $purchase_id, string $supplier, int $user_id ): array {
		$extra = array(
			'supplier'    => $supplier,
			'purchase_id' => $purchase_id,
			'created_by'  => $user_id,
		);

		if ( $line['item_id'] > 0 ) {
			$item = $this->items->lock( array( $line['item_id'] ) )[ $line['item_id'] ] ?? null;

			if ( null === $item ) {
				throw new RuntimeException( 'Restock item disappeared: ' . $line['item_id'] );
			}

			$moved = $this->items->adjust_quantity( $item, $line['quantity'] );
			$this->items->update(
				(int) $moved['id'],
				array_merge(
					array_intersect_key( $line['fields'], array_flip( array( 'cost_price', 'sale_price' ) ) ),
					array_intersect_key( $extra, array_flip( array( 'supplier', 'purchase_id' ) ) )
				)
			);

			return array( (int) $moved['id'] );
		}

		foreach ( Cmshop_Item::TYPE_PHONE === $line['type'] ? $line['imeis'] : array( '' ) as $imei ) {
			$result = Cmshop_Validator::item( array_merge( $line['fields'], array( 'imei1' => $imei ) ) );

			if ( ! $result->is_valid() ) {
				throw new RuntimeException( 'Purchase line failed item validation: ' . wp_json_encode( $result->errors() ) );
			}

			$this->items->insert( array_merge( $result->data(), $extra ) );
		}

		return array();
	}

	/**
	 * Money out to a supplier in the ledger (counts against the cash drawer).
	 *
	 * @param int    $supplier_id Supplier.
	 * @param int    $cents       Amount paid.
	 * @param array  $input       method, reference.
	 * @param int    $user_id     Staff member.
	 * @param string $purchases   Purchase numbers for the note.
	 */
	private function pay_out( int $supplier_id, int $cents, array $input, int $user_id, string $purchases ): void {
		if ( $cents <= 0 ) {
			return;
		}

		$method = array_key_exists( (string) ( $input['method'] ?? '' ), self::methods() ) ? (string) $input['method'] : 'cash';

		$this->payments->record(
			array(
				array(
					'method'    => $method,
					'amount'    => Cmshop_Money::from_cents( -$cents ),
					'reference' => mb_substr( sanitize_text_field( (string) ( $input['reference'] ?? '' ) ), 0, 100 ),
				),
			),
			array(
				'kind'        => Cmshop_Payment_Repository::KIND_SUPPLIER,
				'supplier_id' => $supplier_id,
				/* translators: %s: purchase numbers. */
				'note'        => sprintf( __( 'Supplier payment for purchase %s', 'codereyes-mobile-shop' ), $purchases ),
				'created_by'  => $user_id,
			)
		);
	}

	/**
	 * Purchase date: a past or present Y-m-d, otherwise today.
	 *
	 * @param string $raw Submitted date.
	 * @return string
	 */
	private function date( string $raw ): string {
		$today = current_time( 'Y-m-d' );
		$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $raw );

		return false !== $date && $date->format( 'Y-m-d' ) === $raw && $raw <= $today ? $raw : $today;
	}
}
