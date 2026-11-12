<?php
/**
 * Stock purchases from suppliers.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * One row per delivery (supplier invoice), with what was paid so far.
 */
final class Cmshop_Purchase_Repository extends Cmshop_Repository {

	private const HISTORY_LIMIT = 200;

	/**
	 * A supplier's purchases, newest first.
	 *
	 * @param int $supplier_id Supplier.
	 * @return array[]
	 */
	public function for_supplier( int $supplier_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM %i WHERE supplier_id = %d ORDER BY purchased_on DESC, id DESC LIMIT %d', $this->table, $supplier_id, self::HISTORY_LIMIT ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Purchases not fully paid, oldest first, optionally locked.
	 *
	 * @param int  $supplier_id Supplier.
	 * @param bool $lock        Lock the rows (inside a transaction).
	 * @return array[]
	 */
	public function unpaid( int $supplier_id, bool $lock = false ): array {
		$sql  = 'SELECT * FROM %i WHERE supplier_id = %d AND total > paid ORDER BY purchased_on ASC, id ASC' . ( $lock ? ' FOR UPDATE' : '' );
		$rows = $this->db->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql only adds a fixed FOR UPDATE.
			$this->db->prepare( $sql, $this->table, $supplier_id ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * What the shop owes all suppliers together (cents).
	 *
	 * @return int
	 */
	public function owed_total(): int {
		$owed = $this->db->get_var( $this->db->prepare( 'SELECT COALESCE(SUM(total - paid), 0) FROM %i', $this->table ) );

		return Cmshop_Money::to_cents( (string) $owed );
	}
}
