<?php
/**
 * Suppliers (wholesalers and distributors the shop buys stock from).
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Supplier rows with what the shop still owes each one.
 */
final class Cmshop_Supplier_Repository extends Cmshop_Repository {

	private const LIST_LIMIT = 500;

	/**
	 * Every supplier with purchases count, last purchase and amount owed, A to Z.
	 *
	 * @param string $purchases Purchases table.
	 * @return array[] Rows of supplier columns plus purchases, last_purchase, owed (amount).
	 */
	public function with_balances( string $purchases ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT s.*, COUNT(p.id) AS purchases, MAX(p.purchased_on) AS last_purchase,
					COALESCE(SUM(p.total - p.paid), 0) AS owed
				FROM %i s LEFT JOIN %i p ON p.supplier_id = s.id
				GROUP BY s.id ORDER BY s.name ASC LIMIT %d',
				$this->table,
				$purchases,
				self::LIST_LIMIT
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Supplier by exact name (case-insensitive), to avoid duplicates.
	 *
	 * @param string $name Name.
	 * @return array|null
	 */
	public function find_by_name( string $name ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE LOWER(name) = LOWER(%s) LIMIT 1', $this->table, $name ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Suppliers for a picker: id => name, A to Z.
	 *
	 * @return array<int,string>
	 */
	public function choices(): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT id, name FROM %i ORDER BY name ASC LIMIT %d', $this->table, self::LIST_LIMIT ),
			ARRAY_A
		);

		return array_column( is_array( $rows ) ? $rows : array(), 'name', 'id' );
	}
}
