<?php
/**
 * Installment schedules and the dues customers owe.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Stores schedules; dues come from sales (total minus amount paid).
 */
final class Cmshop_Installment_Repository extends Cmshop_Repository {

	/**
	 * Sales table.
	 *
	 * @var string
	 */
	private $sales_table;

	/**
	 * Payments table.
	 *
	 * @var string
	 */
	private $payments_table;

	/**
	 * Constructor.
	 *
	 * @param wpdb         $db             Database.
	 * @param string       $table          Installments table.
	 * @param string       $sales_table    Sales table.
	 * @param string       $payments_table Payments table.
	 * @param Cmshop_Query $query          Query rules.
	 */
	public function __construct( wpdb $db, string $table, string $sales_table, string $payments_table, Cmshop_Query $query ) {
		parent::__construct( $db, $table, $query );
		$this->sales_table    = $sales_table;
		$this->payments_table = $payments_table;
	}

	/**
	 * Save a schedule for a sale. Call inside the sale transaction.
	 *
	 * @param int     $sale_id     Sale.
	 * @param int     $customer_id Customer.
	 * @param array[] $plan        From Cmshop_Installments::plan() (cents).
	 * @throws RuntimeException When a row cannot be saved.
	 */
	public function save_plan( int $sale_id, int $customer_id, array $plan ): void {
		foreach ( $plan as $row ) {
			$this->insert(
				array(
					'sale_id'     => $sale_id,
					'customer_id' => $customer_id,
					'seq'         => (int) $row['seq'],
					'due_date'    => (string) $row['due_date'],
					'amount'      => Cmshop_Money::from_cents( (int) $row['amount'] ),
				)
			);
		}
	}

	/**
	 * Schedule of a sale (cents), in order.
	 *
	 * @param int $sale_id Sale.
	 * @return array[] Rows of seq, due_date, amount.
	 */
	public function plan_for_sale( int $sale_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT seq, due_date, amount FROM %i WHERE sale_id = %d ORDER BY seq ASC', $this->table, $sale_id ),
			ARRAY_A
		);

		return array_map(
			static function ( array $row ): array {
				return array(
					'seq'      => (int) $row['seq'],
					'due_date' => (string) $row['due_date'],
					'amount'   => Cmshop_Money::to_cents( (string) $row['amount'] ),
				);
			},
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Money collected on a sale after the day of sale (cents).
	 *
	 * @param int $sale_id Sale.
	 * @return int
	 */
	public function collected( int $sale_id ): int {
		$sum = $this->db->get_var(
			$this->db->prepare( 'SELECT COALESCE( SUM( amount ), 0 ) FROM %i WHERE sale_id = %d AND kind = %s', $this->payments_table, $sale_id, Cmshop_Payment_Repository::KIND_DUE )
		);

		return Cmshop_Money::to_cents( (string) $sum );
	}

	/**
	 * Customers who owe money, most owed first.
	 *
	 * @param string $customers Customers table.
	 * @return array[] Rows of customer_id, name, phone, owed, sales, oldest.
	 */
	public function debtors( string $customers ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT s.customer_id, c.name, c.phone, SUM( s.total - s.amount_paid ) AS owed, COUNT(*) AS sales, MIN( s.created_at ) AS oldest
				FROM %i s INNER JOIN %i c ON c.id = s.customer_id
				WHERE s.status = %s AND s.amount_paid < s.total
				GROUP BY s.customer_id, c.name, c.phone ORDER BY owed DESC LIMIT 500',
				$this->sales_table,
				$customers,
				Cmshop_Sale_Repository::STATUS_COMPLETED
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * A customer's unpaid sales, oldest first, locked for the rest of the transaction.
	 *
	 * @param int  $customer_id Customer.
	 * @param bool $lock        Lock the rows (inside a transaction).
	 * @return array[] Sale rows.
	 */
	public function unpaid_sales( int $customer_id, bool $lock = false ): array {
		$sql  = 'SELECT * FROM %i WHERE customer_id = %d AND status = %s AND amount_paid < total ORDER BY created_at ASC, id ASC' . ( $lock ? ' FOR UPDATE' : '' );
		$rows = $this->db->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql only adds a fixed FOR UPDATE.
			$this->db->prepare( $sql, $this->sales_table, $customer_id, Cmshop_Sale_Repository::STATUS_COMPLETED ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
