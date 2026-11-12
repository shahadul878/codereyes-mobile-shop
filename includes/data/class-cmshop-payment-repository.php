<?php
/**
 * Payments ledger: money in (sales, dues collected) and out (refunds).
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * One row per payment. Amounts are positive for money received and
 * negative for money given back.
 */
final class Cmshop_Payment_Repository extends Cmshop_Repository {

	public const KIND_SALE     = 'sale';
	public const KIND_DUE      = 'due';
	public const KIND_REFUND   = 'refund';
	public const KIND_CASH_IN  = 'cash_in';
	public const KIND_CASH_OUT = 'cash_out';

	/**
	 * Record ledger entries. Call inside the sale transaction.
	 *
	 * @param array[] $entries Entries of method, amount (positive or negative), reference.
	 * @param array   $context sale_id, customer_id, kind, created_by, note.
	 * @throws RuntimeException When a row cannot be saved.
	 */
	public function record( array $entries, array $context ): void {
		foreach ( $entries as $entry ) {
			$this->insert(
				array(
					'sale_id'     => (int) ( $context['sale_id'] ?? 0 ),
					'customer_id' => (int) ( $context['customer_id'] ?? 0 ),
					'shift_id'    => (int) ( $context['shift_id'] ?? 0 ),
					'kind'        => (string) ( $context['kind'] ?? self::KIND_SALE ),
					'method'      => (string) $entry['method'],
					'amount'      => (string) $entry['amount'],
					'reference'   => (string) ( $entry['reference'] ?? '' ),
					'note'        => mb_substr( (string) ( $context['note'] ?? '' ), 0, 255 ),
					'created_by'  => (int) ( $context['created_by'] ?? 0 ),
				)
			);
		}
	}

	/**
	 * Payments of a sale, oldest first.
	 *
	 * @param int $sale_id Sale ID.
	 * @return array[]
	 */
	public function for_sale( int $sale_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM %i WHERE sale_id = %d ORDER BY id ASC', $this->table, $sale_id ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Net amount per method still held for a sale (payments minus refunds).
	 *
	 * @param int $sale_id Sale ID.
	 * @return array<string,string> method => amount.
	 */
	public function net_by_method( int $sale_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT method, SUM(amount) AS amount FROM %i WHERE sale_id = %d GROUP BY method', $this->table, $sale_id ),
			ARRAY_A
		);

		return array_column( is_array( $rows ) ? $rows : array(), 'amount', 'method' );
	}

	/**
	 * Net money taken per method between two times (site time).
	 *
	 * @param string $from Start (inclusive), Y-m-d H:i:s.
	 * @param string $to   End (exclusive), Y-m-d H:i:s.
	 * @return array<string,string> method => amount.
	 */
	public function totals_by_method( string $from, string $to ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT method, SUM(amount) AS amount FROM %i WHERE created_at >= %s AND created_at < %s GROUP BY method',
				$this->table,
				$from,
				$to
			),
			ARRAY_A
		);

		return array_column( is_array( $rows ) ? $rows : array(), 'amount', 'method' );
	}

	/**
	 * Payments one staff member recorded in a time window (their shift).
	 *
	 * @param int    $user_id Staff member.
	 * @param string $from    Start (inclusive).
	 * @param string $to      End (exclusive).
	 * @return array[] Rows of kind, method, amount, note, created_at.
	 */
	public function for_user_between( int $user_id, string $from, string $to ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT kind, method, amount, note, created_at FROM %i WHERE created_by = %d AND created_at >= %s AND created_at < %s ORDER BY id ASC',
				$this->table,
				$user_id,
				$from,
				$to
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
