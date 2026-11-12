<?php
/**
 * Base list table: search, filters, sorting and pagination from a repository.
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended -- List screens only read filters from the URL.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Subclasses define columns, filters and cell rendering.
 */
abstract class Cmshop_List_Table extends WP_List_Table {

	/**
	 * Repository.
	 *
	 * @var Cmshop_Repository
	 */
	protected $repository;

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	protected $page;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Repository $repository Repository.
	 * @param string            $page       Admin page slug.
	 * @param string            $plural     Plural name.
	 */
	public function __construct( Cmshop_Repository $repository, string $page, string $plural ) {
		parent::__construct(
			array(
				'plural'   => $plural,
				'singular' => rtrim( $plural, 's' ),
				'ajax'     => false,
			)
		);
		$this->repository = $repository;
		$this->page       = $page;
	}

	/**
	 * Filter dropdowns: query arg => [ label, choices ].
	 *
	 * @return array<string,array{0: string, 1: array<string,string>}>
	 */
	protected function filter_choices(): array {
		return array();
	}

	/**
	 * Fixed filters always applied (e.g. a customer's history).
	 *
	 * @return array
	 */
	protected function fixed_filters(): array {
		return array();
	}

	/**
	 * Load the current page of rows.
	 */
	public function prepare_items(): void {
		$per_page = $this->get_items_per_page( 'cmshop_per_page', Cmshop_Query::DEFAULT_PER_PAGE );
		$result   = $this->repository->paginate( array_merge( self::request_args(), array( 'per_page' => $per_page ), array( 'filters' => $this->current_filters() ) ) );

		$this->items           = $result['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), $this->get_primary_column_name() );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Search, sort, page and dates from the URL.
	 *
	 * @return array
	 */
	public static function request_args(): array {
		$get = static function ( string $key ): string {
			return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		};

		return array(
			'search'  => $get( 's' ),
			'orderby' => sanitize_key( $get( 'orderby' ) ),
			'order'   => sanitize_key( $get( 'order' ) ),
			'page'    => max( 1, absint( $get( 'paged' ) ) ),
			'from'    => $get( 'from' ),
			'to'      => $get( 'to' ),
		);
	}

	/**
	 * Filters chosen in the dropdowns plus fixed filters.
	 *
	 * @return array
	 */
	public function current_filters(): array {
		$filters = $this->fixed_filters();

		foreach ( array_keys( $this->filter_choices() ) as $key ) {
			$value = isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';

			if ( '' !== $value ) {
				$filters[ $key ] = $value;
			}
		}

		return $filters;
	}

	/**
	 * Filter dropdowns above the table.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ): void {
		$choices = $this->filter_choices();

		if ( 'top' !== $which || array() === $choices ) {
			return;
		}

		$current = $this->current_filters();

		echo '<div class="alignleft actions">';

		foreach ( $choices as $key => $filter ) {
			list( $label, $options ) = $filter;

			printf( '<label class="screen-reader-text" for="cmshop-filter-%s">%s</label>', esc_attr( $key ), esc_html( $label ) );
			printf( '<select name="%1$s" id="cmshop-filter-%1$s">', esc_attr( $key ) );
			/* translators: %s: filter name, e.g. Status. */
			printf( '<option value="">%s</option>', esc_html( sprintf( __( 'All: %s', 'codereyes-mobile-shop' ), $label ) ) );

			foreach ( $options as $value => $text ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( (string) ( $current[ $key ] ?? '' ), (string) $value, false ), esc_html( $text ) );
			}

			echo '</select>';
		}

		submit_button( __( 'Filter', 'codereyes-mobile-shop' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Fallback cell: escaped text.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * Money cell.
	 *
	 * @param string|null $amount Amount.
	 * @return string
	 */
	protected function money( ?string $amount ): string {
		return esc_html( Cmshop_Settings::money( $amount ) );
	}

	/**
	 * Row link.
	 *
	 * @param string $text Text.
	 * @param array  $args Query args.
	 * @return string
	 */
	protected function link( string $text, array $args ): string {
		return sprintf( '<a class="row-title" href="%s">%s</a>', esc_url( Cmshop_View::url( $this->page, $args ) ), esc_html( '' === $text ? '—' : $text ) );
	}
}
