<?php
/**
 * Builds safe WHERE / ORDER BY clauses for list screens and exports.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Only whitelisted columns ever reach SQL; every value is a placeholder.
 */
final class Cmshop_Query {

	public const DEFAULT_PER_PAGE = 20;
	public const MAX_PER_PAGE     = 200;

	/**
	 * Table configuration.
	 *
	 * @var array{search: string[], filters: string[], sortable: string[], default_orderby: string}
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param string[] $search          Columns matched by the search box.
	 * @param string[] $filters         Columns that accept an exact-match filter.
	 * @param string[] $sortable        Columns that can be sorted on.
	 * @param string   $default_orderby Column used when no valid sort is given.
	 */
	public function __construct( array $search, array $filters, array $sortable, string $default_orderby = 'id' ) {
		$this->config = array(
			'search'          => $search,
			'filters'         => $filters,
			'sortable'        => $sortable,
			'default_orderby' => $default_orderby,
		);
	}

	/**
	 * Build the WHERE clause and its values.
	 *
	 * Args: search, filters (column => value or list of values), from, to (Y-m-d on created_at).
	 *
	 * @param array $args Arguments.
	 * @return array{0: string, 1: array} SQL fragment starting with "WHERE 1=1" and its values.
	 */
	public function where( array $args ): array {
		$sql    = array( '1=1' );
		$params = array();

		$search = trim( (string) ( $args['search'] ?? '' ) );

		if ( '' !== $search && array() !== $this->config['search'] ) {
			$like  = '%' . self::escape_like( $search ) . '%';
			$parts = array();

			foreach ( $this->config['search'] as $column ) {
				$parts[]  = "{$column} LIKE %s";
				$params[] = $like;
			}

			$sql[] = '( ' . implode( ' OR ', $parts ) . ' )';
		}

		foreach ( (array) ( $args['filters'] ?? array() ) as $column => $value ) {
			if ( ! in_array( $column, $this->config['filters'], true ) || '' === $value || array() === $value ) {
				continue;
			}

			$values = array_values( array_map( 'strval', (array) $value ) );
			$sql[]  = "{$column} IN ( " . implode( ', ', array_fill( 0, count( $values ), '%s' ) ) . ' )';
			$params = array_merge( $params, $values );
		}

		foreach ( array(
			'from' => '>=',
			'to'   => '<',
		) as $key => $operator ) {
			$date = self::valid_date( (string) ( $args[ $key ] ?? '' ) );

			if ( null !== $date ) {
				$sql[]    = "created_at {$operator} %s";
				$params[] = 'to' === $key ? $date->modify( '+1 day' )->format( 'Y-m-d' ) : $date->format( 'Y-m-d' );
			}
		}

		return array( 'WHERE ' . implode( ' AND ', $sql ), $params );
	}

	/**
	 * Build the ORDER BY clause.
	 *
	 * @param array $args orderby, order.
	 * @return string
	 */
	public function order_by( array $args ): string {
		$orderby = (string) ( $args['orderby'] ?? '' );
		$orderby = in_array( $orderby, $this->config['sortable'], true ) ? $orderby : $this->config['default_orderby'];
		$order   = 'asc' === strtolower( (string) ( $args['order'] ?? '' ) ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}, id {$order}";
	}

	/**
	 * Page size and offset.
	 *
	 * @param array $args per_page, page.
	 * @return array{0: int, 1: int} Limit and offset.
	 */
	public static function limit( array $args ): array {
		$per_page = (int) ( $args['per_page'] ?? self::DEFAULT_PER_PAGE );
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );

		return array( $per_page, ( $page - 1 ) * $per_page );
	}

	/**
	 * Escape LIKE wildcards (same as wpdb::esc_like, usable without WordPress).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function escape_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Parse a strict Y-m-d date.
	 *
	 * @param string $value Value.
	 * @return DateTimeImmutable|null
	 */
	private static function valid_date( string $value ): ?DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		return false !== $date && $date->format( 'Y-m-d' ) === $value ? $date : null;
	}
}
