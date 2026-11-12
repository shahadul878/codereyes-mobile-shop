<?php
/**
 * Repair jobs.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table; nothing to cache across requests.
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- Only placeholder lists are interpolated.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the repairs table.
 */
final class Cmshop_Repair_Repository extends Cmshop_Repository {

	/**
	 * Attempts at a unique ticket code before giving up.
	 */
	private const CODE_ATTEMPTS = 5;

	/**
	 * Insert a repair with a fresh, unique ticket code.
	 *
	 * @param array $data Repair columns.
	 * @return int New ID.
	 * @throws RuntimeException When no unique code could be stored.
	 */
	public function insert_with_ticket( array $data ): int {
		for ( $attempt = 0; $attempt < self::CODE_ATTEMPTS; $attempt++ ) {
			$code = Cmshop_Code::ticket();

			if ( null === $this->find_by_ticket( $code ) ) {
				return $this->insert( array_merge( $data, array( 'ticket_code' => $code ) ) );
			}
		}

		throw new RuntimeException( 'Could not generate a unique ticket code.' );
	}

	/**
	 * Repair by ticket code.
	 *
	 * @param string $code Normalised ticket code.
	 * @return array|null
	 */
	public function find_by_ticket( string $code ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE ticket_code = %s LIMIT 1', $this->table, $code ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Number of repairs in each open status.
	 *
	 * @return array<string,int>
	 */
	public function open_counts(): array {
		$open   = Cmshop_Repair_Status::open();
		$in     = implode( ', ', array_fill( 0, count( $open ), '%s' ) );
		$rows   = $this->db->get_results(
			$this->db->prepare(
				"SELECT status, COUNT(*) AS jobs FROM %i WHERE status IN ( {$in} ) GROUP BY status",
				array_merge( array( $this->table ), $open )
			),
			ARRAY_A
		);
		$counts = array_fill_keys( $open, 0 );

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$counts[ $row['status'] ] = (int) $row['jobs'];
		}

		return $counts;
	}

	/**
	 * Repairs for a customer, newest first.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array[]
	 */
	public function for_customer( int $customer_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM %i WHERE customer_id = %d ORDER BY id DESC LIMIT 100', $this->table, $customer_id ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
