<?php
/**
 * Cash register shifts.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * One open shift per staff member at a time.
 */
final class Cmshop_Shift_Repository extends Cmshop_Repository {

	/**
	 * The staff member's open shift, if any.
	 *
	 * @param int  $user_id Staff member.
	 * @param bool $lock    Lock the row (inside a transaction).
	 * @return array|null
	 */
	public function open_for( int $user_id, bool $lock = false ): ?array {
		$sql = 'SELECT * FROM %i WHERE user_id = %d AND closed_at IS NULL ORDER BY id DESC LIMIT 1' . ( $lock ? ' FOR UPDATE' : '' );
		$row = $this->db->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql only adds a fixed FOR UPDATE.
			$this->db->prepare( $sql, $this->table, $user_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Most recent shifts, newest first.
	 *
	 * @param int $limit How many.
	 * @return array[]
	 */
	public function recent( int $limit ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $this->table, $limit ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
