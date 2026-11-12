<?php
/**
 * Sales and their lines.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom tables; nothing to cache across requests.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the sales and sale_items tables.
 */
final class Cmshop_Sale_Repository extends Cmshop_Repository {

	public const STATUS_COMPLETED = 'completed';
	public const STATUS_VOID      = 'void';

	public const CHANNEL_STORE  = 'store';
	public const CHANNEL_ONLINE = 'online';

	/**
	 * Lines table name.
	 *
	 * @var string
	 */
	private $lines_table;

	/**
	 * Constructor.
	 *
	 * @param wpdb         $db          Database handle.
	 * @param string       $table       Sales table.
	 * @param string       $lines_table Sale lines table.
	 * @param Cmshop_Query $query       Query builder.
	 */
	public function __construct( wpdb $db, string $table, string $lines_table, Cmshop_Query $query ) {
		parent::__construct( $db, $table, $query );
		$this->lines_table = $lines_table;
	}

	/**
	 * Insert a sale and its lines. Call inside a transaction.
	 *
	 * @param array $sale  Sale columns.
	 * @param array $lines Priced lines from Cmshop_Cart.
	 * @return int Sale ID.
	 * @throws RuntimeException When a write fails.
	 */
	public function insert_with_lines( array $sale, array $lines ): int {
		$sale_id = $this->insert( $sale );

		foreach ( $lines as $line ) {
			$row = array_merge( $line, array( 'sale_id' => $sale_id ) );

			if ( false === $this->db->insert( $this->lines_table, $row ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Logged, never displayed.
				throw new RuntimeException( 'Sale line insert failed: ' . $this->db->last_error );
			}
		}

		return $sale_id;
	}

	/**
	 * Lines of a sale.
	 *
	 * @param int $sale_id Sale ID.
	 * @return array[]
	 */
	public function lines( int $sale_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM %i WHERE sale_id = %d ORDER BY id ASC', $this->lines_table, $sale_id ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Sale recorded for a WooCommerce order, if any.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public function find_by_order( int $order_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE wc_order_id = %d ORDER BY id DESC LIMIT 1', $this->table, $order_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * The latest completed sale of a phone, by either of its IMEIs.
	 *
	 * Returns only what a warranty check needs: never the customer or price.
	 *
	 * @param string $imei      Normalised IMEI.
	 * @param string $items     Items table (for IMEI 2 and the condition).
	 * @return array{sold_at: string, description: string, grade: string}|null
	 */
	public function find_phone_sale( string $imei, string $items ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT s.created_at AS sold_at, l.description, COALESCE( i.grade, %s ) AS grade
				FROM %i l
				INNER JOIN %i s ON s.id = l.sale_id
				LEFT JOIN %i i ON i.id = l.item_id
				WHERE s.status = %s AND l.item_type = %s AND ( l.imei = %s OR i.imei1 = %s OR i.imei2 = %s )
				ORDER BY s.id DESC LIMIT 1',
				'good',
				$this->lines_table,
				$this->table,
				$items,
				self::STATUS_COMPLETED,
				Cmshop_Item::TYPE_PHONE,
				$imei,
				$imei,
				$imei
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Lock a sale row for the rest of the transaction.
	 *
	 * @param int $sale_id Sale ID.
	 * @return array|null
	 */
	public function lock_sale( int $sale_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE id = %d FOR UPDATE', $this->table, $sale_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Completed-sale totals between two local datetimes.
	 *
	 * @param string $from Start (inclusive), "Y-m-d H:i:s".
	 * @param string $to   End (exclusive), "Y-m-d H:i:s".
	 * @return array{count: int, total: string, profit: string}
	 */
	public function totals( string $from, string $to ): array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT COUNT(*) AS sales, COALESCE( SUM( total ), 0 ) AS total, COALESCE( SUM( profit ), 0 ) AS profit
				FROM %i WHERE status = %s AND created_at >= %s AND created_at < %s',
				$this->table,
				self::STATUS_COMPLETED,
				$from,
				$to
			),
			ARRAY_A
		);

		return array(
			'count'  => (int) ( $row['sales'] ?? 0 ),
			'total'  => (string) ( $row['total'] ?? '0.00' ),
			'profit' => (string) ( $row['profit'] ?? '0.00' ),
		);
	}

	/**
	 * Completed sales per day.
	 *
	 * @param string $from Start (inclusive).
	 * @param string $to   End (exclusive).
	 * @return array[] Rows of day, total, profit, sales.
	 */
	public function daily_totals( string $from, string $to ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT DATE( created_at ) AS day, SUM( total ) AS total, SUM( profit ) AS profit, COUNT(*) AS sales
				FROM %i WHERE status = %s AND created_at >= %s AND created_at < %s GROUP BY DATE( created_at )',
				$this->table,
				self::STATUS_COMPLETED,
				$from,
				$to
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Best sellers: phones by brand and model, other items by name.
	 *
	 * @param string $from  Start (inclusive).
	 * @param string $to    End (exclusive).
	 * @param string $items Items table.
	 * @param int    $limit How many.
	 * @return array[] Rows of label, quantity, amount.
	 */
	public function top_sellers( string $from, string $to, string $items, int $limit ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT CASE WHEN i.item_type = %s AND i.model <> '' THEN CONCAT_WS( ' ', i.brand, i.model ) ELSE l.description END AS label,
					SUM( l.quantity ) AS quantity, SUM( l.line_total ) AS amount
				FROM %i l
				INNER JOIN %i s ON s.id = l.sale_id
				LEFT JOIN %i i ON i.id = l.item_id
				WHERE s.status = %s AND s.created_at >= %s AND s.created_at < %s
				GROUP BY label ORDER BY quantity DESC, amount DESC LIMIT %d",
				Cmshop_Item::TYPE_PHONE,
				$this->lines_table,
				$this->table,
				$items,
				self::STATUS_COMPLETED,
				$from,
				$to,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Money customers still owe on completed sales.
	 *
	 * @return array{amount: string, sales: int, customers: int}
	 */
	public function dues_summary(): array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT COALESCE( SUM( total - amount_paid ), 0 ) AS amount, COUNT(*) AS sales, COUNT( DISTINCT customer_id ) AS customers
				FROM %i WHERE status = %s AND amount_paid < total',
				$this->table,
				self::STATUS_COMPLETED
			),
			ARRAY_A
		);

		return array(
			'amount'    => (string) ( $row['amount'] ?? '0.00' ),
			'sales'     => (int) ( $row['sales'] ?? 0 ),
			'customers' => (int) ( $row['customers'] ?? 0 ),
		);
	}

	/**
	 * A customer's history at a glance: sales, money spent, last visit, money owed.
	 *
	 * @param int $customer_id Customer.
	 * @return array{sales: int, spent: string, last: string, due: string}
	 */
	public function customer_stats( int $customer_id ): array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT COUNT(*) AS sales, COALESCE( SUM( total ), 0 ) AS spent, MAX( created_at ) AS last,
					COALESCE( SUM( GREATEST( total - amount_paid, 0 ) ), 0 ) AS due
				FROM %i WHERE customer_id = %d AND status = %s',
				$this->table,
				$customer_id,
				self::STATUS_COMPLETED
			),
			ARRAY_A
		);

		return array(
			'sales' => (int) ( $row['sales'] ?? 0 ),
			'spent' => (string) ( $row['spent'] ?? '0.00' ),
			'last'  => (string) ( $row['last'] ?? '' ),
			'due'   => (string) ( $row['due'] ?? '0.00' ),
		);
	}

	/**
	 * Most recent sales.
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

	/**
	 * Sales for a customer, newest first.
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

	/**
	 * Sales that included a stock item, newest first.
	 *
	 * @param int $item_id Item ID.
	 * @return array[]
	 */
	public function for_item( int $item_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT s.* FROM %i s INNER JOIN %i l ON l.sale_id = s.id WHERE l.item_id = %d GROUP BY s.id ORDER BY s.id DESC LIMIT 50',
				$this->table,
				$this->lines_table,
				$item_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
