<?php
/**
 * CSV export of stock, sales, repairs and customers.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Streams the rows matching the current list filters as a CSV download.
 */
final class Cmshop_Export {

	public const ACTION = 'cmshop_export';

	/**
	 * Filters accepted per dataset.
	 */
	private const FILTERS = array(
		'items'     => array( 'item_type', 'status', 'grade' ),
		'sales'     => array( 'channel', 'payment_method', 'status' ),
		'repairs'   => array( 'status' ),
		'customers' => array(),
	);

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'download' ) );
	}

	/**
	 * Export URL keeping the current list filters.
	 *
	 * @param string $dataset Dataset.
	 * @param array  $args    Current list args.
	 * @return string
	 */
	public static function url( string $dataset, array $args ): string {
		$query = array_filter(
			array(
				'action'  => self::ACTION,
				'dataset' => $dataset,
				's'       => $args['search'] ?? '',
				'from'    => $args['from'] ?? '',
				'to'      => $args['to'] ?? '',
			)
		);

		foreach ( self::FILTERS[ $dataset ] ?? array() as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Copies list filters into a link.
			$value = isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';

			if ( '' !== $value ) {
				$query[ $key ] = $value;
			}
		}

		return wp_nonce_url( add_query_arg( $query, admin_url( 'admin-post.php' ) ), self::ACTION );
	}

	/**
	 * Send the CSV.
	 */
	public function download(): void {
		if ( ! current_user_can( Cmshop_Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'codereyes-mobile-shop' ), 403 );
		}

		check_admin_referer( self::ACTION );

		$dataset    = isset( $_GET['dataset'] ) ? sanitize_key( wp_unslash( $_GET['dataset'] ) ) : '';
		$repository = $this->repository( $dataset );
		$columns    = Cmshop_Csv::columns( $dataset );

		if ( null === $repository || array() === $columns ) {
			wp_die( esc_html__( 'Unknown export.', 'codereyes-mobile-shop' ), 400 );
		}

		$args = array_merge( Cmshop_List_Table::request_args(), array( 'filters' => $this->filters( $dataset ) ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mobile-shop-' . $dataset . '-' . current_time( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );

		if ( false === $out ) {
			wp_die( esc_html__( 'The export could not be started.', 'codereyes-mobile-shop' ), 500 );
		}

		// UTF-8 byte order mark so Excel shows ৳ and Bengali names correctly.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming to php://output.
		fputcsv( $out, array_values( $columns ) );

		$repository->each(
			$args,
			static function ( array $row ) use ( $out, $columns ) {
				fputcsv( $out, Cmshop_Csv::row( $row, $columns ) );
			}
		);

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming to php://output.
		exit;
	}

	/**
	 * Repository for a dataset.
	 *
	 * @param string $dataset Dataset.
	 * @return Cmshop_Repository|null
	 */
	private function repository( string $dataset ): ?Cmshop_Repository {
		switch ( $dataset ) {
			case 'items':
				return Cmshop_Plugin::items();
			case 'sales':
				return Cmshop_Plugin::sales();
			case 'repairs':
				return Cmshop_Plugin::repairs();
			case 'customers':
				return Cmshop_Plugin::customers();
			default:
				return null;
		}
	}

	/**
	 * Filters from the URL for a dataset.
	 *
	 * @param string $dataset Dataset.
	 * @return array
	 */
	private function filters( string $dataset ): array {
		$filters = array();

		foreach ( self::FILTERS[ $dataset ] ?? array() as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked in download().
			$value = isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';

			if ( '' !== $value ) {
				$filters[ $key ] = $value;
			}
		}

		return $filters;
	}
}
