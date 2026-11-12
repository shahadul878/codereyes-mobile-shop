<?php
/**
 * Collecting money customers owe, and their installment schedules.
 *
 * @package CodereyesMobileShop
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for staff and are escaped where they are displayed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * A payment from a customer goes to their oldest unpaid sale first. Each part
 * is recorded in the payments ledger as a "due" payment on that sale.
 */
final class Cmshop_Dues_Service {

	/**
	 * Installments and dues.
	 *
	 * @var Cmshop_Installment_Repository
	 */
	private $installments;

	/**
	 * Sales.
	 *
	 * @var Cmshop_Sale_Repository
	 */
	private $sales;

	/**
	 * Payments ledger.
	 *
	 * @var Cmshop_Payment_Repository
	 */
	private $payments;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Installment_Repository $installments Installments and dues.
	 * @param Cmshop_Sale_Repository        $sales        Sales.
	 * @param Cmshop_Payment_Repository     $payments     Payments ledger.
	 */
	public function __construct( Cmshop_Installment_Repository $installments, Cmshop_Sale_Repository $sales, Cmshop_Payment_Repository $payments ) {
		$this->installments = $installments;
		$this->sales        = $sales;
		$this->payments     = $payments;
	}

	/**
	 * Record money received from a customer against what they owe.
	 *
	 * @param int    $customer_id Customer.
	 * @param string $amount      Amount typed by staff.
	 * @param string $method      Payment method key.
	 * @param string $reference   Transaction ID (mobile banking, bank).
	 * @param int    $user_id     Staff member.
	 * @return array[] Parts of sale_id and amount (cents).
	 * @throws InvalidArgumentException With a message for staff.
	 */
	public function collect( int $customer_id, string $amount, string $method, string $reference, int $user_id ): array {
		$parsed = Cmshop_Money::parse( $amount );

		if ( null === $parsed ) {
			throw new InvalidArgumentException( __( 'Enter the amount received.', 'codereyes-mobile-shop' ) );
		}

		if ( ! array_key_exists( $method, Cmshop_Settings::payment_methods() ) ) {
			throw new InvalidArgumentException( __( 'Choose how the customer paid.', 'codereyes-mobile-shop' ) );
		}

		$cents = Cmshop_Money::to_cents( $parsed );

		return $this->sales->transaction(
			function () use ( $customer_id, $cents, $method, $reference, $user_id ) {
				$unpaid = $this->installments->unpaid_sales( $customer_id, true );
				$parts  = Cmshop_Installments::allocate(
					$cents,
					array_map(
						static function ( array $sale ): array {
							return array(
								'id'  => (int) $sale['id'],
								'due' => Cmshop_Money::to_cents( (string) $sale['total'] ) - Cmshop_Money::to_cents( (string) $sale['amount_paid'] ),
							);
						},
						$unpaid
					)
				);
				$paid   = array_column( $unpaid, 'amount_paid', 'id' );

				foreach ( $parts as $part ) {
					$this->sales->update( $part['sale_id'], array( 'amount_paid' => Cmshop_Money::from_cents( Cmshop_Money::to_cents( (string) $paid[ $part['sale_id'] ] ) + $part['amount'] ) ) );
					$this->payments->record(
						array(
							array(
								'method'    => $method,
								'amount'    => Cmshop_Money::from_cents( $part['amount'] ),
								'reference' => mb_substr( sanitize_text_field( $reference ), 0, 100 ),
							),
						),
						array(
							'sale_id'     => $part['sale_id'],
							'customer_id' => $customer_id,
							'kind'        => Cmshop_Payment_Repository::KIND_DUE,
							'created_by'  => $user_id,
						)
					);
				}

				return $parts;
			}
		);
	}

	/**
	 * A sale's installments with what is paid, still owed and overdue.
	 *
	 * @param array $sale Sale row.
	 * @return array{rows: array[], summary: array}|null Null when the sale has no plan.
	 */
	public function schedule( array $sale ): ?array {
		$plan = $this->installments->plan_for_sale( (int) $sale['id'] );

		if ( array() === $plan ) {
			return null;
		}

		$rows = Cmshop_Installments::status( $plan, $this->installments->collected( (int) $sale['id'] ), current_time( 'Y-m-d' ) );

		return array(
			'rows'    => $rows,
			'summary' => Cmshop_Installments::summary( $rows ),
		);
	}

	/**
	 * Everyone who owes money, with their overdue installments.
	 *
	 * @return array[] Rows of customer_id, name, phone, owed, sales, oldest, overdue (cents), next_date.
	 */
	public function debtors(): array {
		return array_map(
			function ( array $row ): array {
				return array_merge( $row, $this->standing( (int) $row['customer_id'] ) );
			},
			$this->installments->debtors( Cmshop_Installer::table( 'customers' ) )
		);
	}

	/**
	 * What a customer has overdue and when their next installment falls.
	 *
	 * @param int $customer_id Customer.
	 * @return array{overdue: int, next_date: string} Overdue in cents; next date Y-m-d or ''.
	 */
	public function standing( int $customer_id ): array {
		$overdue = 0;
		$next    = '';

		foreach ( $this->installments->unpaid_sales( $customer_id ) as $sale ) {
			$schedule = $this->schedule( $sale );

			if ( null !== $schedule ) {
				$overdue += $schedule['summary']['overdue'];
				$next     = '' === $next || ( '' !== $schedule['summary']['next_date'] && $schedule['summary']['next_date'] < $next ) ? $schedule['summary']['next_date'] : $next;
			}
		}

		return array(
			'overdue'   => $overdue,
			'next_date' => $next,
		);
	}
}
