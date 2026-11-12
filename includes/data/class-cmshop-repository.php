<?php
/**
 * Shared database access for one plugin table.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom tables; nothing to cache across requests.
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- WHERE / ORDER BY come from Cmshop_Query (whitelisted columns, placeholder values).
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Find, insert, update, delete and paginate rows of one table.
 */
abstract class Cmshop_Repository {

	/**
	 * Rows read per query while exporting.
	 */
	private const EXPORT_CHUNK = 500;

	/**
	 * Database handle.
	 *
	 * @var wpdb
	 */
	protected $db;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Query builder for this table.
	 *
	 * @var Cmshop_Query
	 */
	protected $query;

	/**
	 * Constructor.
	 *
	 * @param wpdb         $db    Database handle.
	 * @param string       $table Table name.
	 * @param Cmshop_Query $query Query builder.
	 */
	public function __construct( wpdb $db, string $table, Cmshop_Query $query ) {
		$this->db    = $db;
		$this->table = $table;
		$this->query = $query;
	}

	/**
	 * One row by ID.
	 *
	 * @param int $id Row ID.
	 * @return array|null
	 */
	public function find( int $id ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a row with timestamps.
	 *
	 * @param array $data Column values.
	 * @return int New ID.
	 * @throws RuntimeException When the insert fails.
	 */
	public function insert( array $data ): int {
		$now = current_time( 'mysql' );
		$row = array_merge(
			array(
				'created_at' => $now,
				'updated_at' => $now,
			),
			$data
		);

		if ( false === $this->db->insert( $this->table, $row ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Logged, never displayed.
			throw new RuntimeException( 'Insert failed: ' . $this->db->last_error );
		}

		return (int) $this->db->insert_id;
	}

	/**
	 * Update a row and its updated_at.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Column values.
	 * @throws RuntimeException When the update fails.
	 */
	public function update( int $id, array $data ): void {
		$row = array_merge( $data, array( 'updated_at' => current_time( 'mysql' ) ) );

		if ( false === $this->db->update( $this->table, $row, array( 'id' => $id ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Logged, never displayed.
			throw new RuntimeException( 'Update failed: ' . $this->db->last_error );
		}
	}

	/**
	 * Delete a row.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return false !== $this->db->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * One page of rows plus the total count.
	 *
	 * @param array $args search, filters, from, to, orderby, order, per_page, page.
	 * @return array{rows: array[], total: int}
	 */
	public function paginate( array $args ): array {
		list( $where, $params ) = $this->query->where( $args );
		list( $limit, $offset ) = Cmshop_Query::limit( $args );

		$order = $this->query->order_by( $args );
		$total = (int) $this->db->get_var(
			$this->db->prepare( "SELECT COUNT(*) FROM %i {$where}", array_merge( array( $this->table ), $params ) )
		);
		$rows  = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM %i {$where} {$order} LIMIT %d OFFSET %d",
				array_merge( array( $this->table ), $params, array( $limit, $offset ) )
			),
			ARRAY_A
		);

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Call $callback with every matching row, in chunks, oldest first.
	 *
	 * @param array    $args     Filters as for paginate().
	 * @param callable $callback Receives one row.
	 */
	public function each( array $args, callable $callback ): void {
		list( $where, $params ) = $this->query->where( $args );

		$last_id = 0;

		do {
			$rows = $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM %i {$where} AND id > %d ORDER BY id ASC LIMIT %d",
					array_merge( array( $this->table ), $params, array( $last_id, self::EXPORT_CHUNK ) )
				),
				ARRAY_A
			);
			$rows = is_array( $rows ) ? $rows : array();

			foreach ( $rows as $row ) {
				$callback( $row );
				$last_id = (int) $row['id'];
			}

			$fetched = count( $rows );
		} while ( self::EXPORT_CHUNK === $fetched );
	}

	/**
	 * Run $work inside a database transaction.
	 *
	 * @param callable $work Work; its return value is returned.
	 * @return mixed
	 * @throws Throwable Re-thrown after rolling back.
	 */
	public function transaction( callable $work ) {
		$this->db->query( 'START TRANSACTION' );

		try {
			$result = $work();
			$this->db->query( 'COMMIT' );

			return $result;
		} catch ( Throwable $error ) {
			$this->db->query( 'ROLLBACK' );
			throw $error;
		}
	}
}
