<?php
/**
 * Sales list table.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Sales list.
 */
final class Cmshop_Sales_Table extends Cmshop_List_Table {

	/**
	 * Customer whose sales to show (0 for all).
	 *
	 * @var int
	 */
	public $customer_id = 0;

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		$columns = array(
			'invoice_no'     => __( 'Invoice', 'codereyes-mobile-shop' ),
			'created_at'     => __( 'Date', 'codereyes-mobile-shop' ),
			'customer_name'  => __( 'Customer', 'codereyes-mobile-shop' ),
			'channel'        => __( 'Channel', 'codereyes-mobile-shop' ),
			'payment_method' => __( 'Payment', 'codereyes-mobile-shop' ),
			'total'          => __( 'Total', 'codereyes-mobile-shop' ),
			'profit'         => __( 'Profit', 'codereyes-mobile-shop' ),
			'status'         => __( 'Status', 'codereyes-mobile-shop' ),
		);

		// Cashiers never see profit.
		return Cmshop_Staff::sees_costs() ? $columns : array_diff_key( $columns, array( 'profit' => true ) );
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns(): array {
		return array(
			'created_at' => array( 'created_at', true ),
			'total'      => array( 'total', false ),
			'profit'     => array( 'profit', false ),
		);
	}

	/**
	 * Filters.
	 *
	 * @return array
	 */
	protected function filter_choices(): array {
		return array(
			'channel'        => array(
				__( 'Channel', 'codereyes-mobile-shop' ),
				array(
					Cmshop_Sale_Repository::CHANNEL_STORE  => __( 'In store', 'codereyes-mobile-shop' ),
					Cmshop_Sale_Repository::CHANNEL_ONLINE => __( 'Online', 'codereyes-mobile-shop' ),
				),
			),
			'payment_method' => array( __( 'Payment', 'codereyes-mobile-shop' ), Cmshop_Settings::payment_labels() ),
			'status'         => array(
				__( 'Status', 'codereyes-mobile-shop' ),
				array(
					Cmshop_Sale_Repository::STATUS_COMPLETED => __( 'Completed', 'codereyes-mobile-shop' ),
					Cmshop_Sale_Repository::STATUS_VOID => __( 'Void', 'codereyes-mobile-shop' ),
					Cmshop_Sale_Repository::STATUS_RETURNED => __( 'Returned', 'codereyes-mobile-shop' ),
				),
			),
		);
	}

	/**
	 * Customer filter for the customer screen.
	 *
	 * @return array
	 */
	protected function fixed_filters(): array {
		return $this->customer_id > 0 ? array( 'customer_id' => (string) $this->customer_id ) : array();
	}

	/**
	 * Invoice link.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_invoice_no( $item ): string {
		return $this->link(
			(string) $item['invoice_no'],
			array(
				'action' => 'view',
				'id'     => (int) $item['id'],
			)
		);
	}

	/**
	 * Date.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_created_at( $item ): string {
		return esc_html( Cmshop_View::date( (string) $item['created_at'] ) );
	}

	/**
	 * Customer.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_customer_name( $item ): string {
		return esc_html( (string) $item['customer_name'] ) . ( '' === (string) $item['customer_phone'] ? '' : '<br><small>' . esc_html( (string) $item['customer_phone'] ) . '</small>' );
	}

	/**
	 * Channel.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_channel( $item ): string {
		$online = Cmshop_Sale_Repository::CHANNEL_ONLINE === $item['channel'];

		return Cmshop_View::badge( $online ? __( 'Online', 'codereyes-mobile-shop' ) : __( 'In store', 'codereyes-mobile-shop' ), (string) $item['channel'] );
	}

	/**
	 * Payment method.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_payment_method( $item ): string {
		return esc_html( Cmshop_Item::label( Cmshop_Settings::payment_labels(), (string) $item['payment_method'] ) );
	}

	/**
	 * Total.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_total( $item ): string {
		return $this->money( (string) $item['total'] );
	}

	/**
	 * Profit.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_profit( $item ): string {
		return $this->money( (string) $item['profit'] );
	}

	/**
	 * Status.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_status( $item ): string {
		$labels = array(
			Cmshop_Sale_Repository::STATUS_VOID     => __( 'Void', 'codereyes-mobile-shop' ),
			Cmshop_Sale_Repository::STATUS_RETURNED => __( 'Returned', 'codereyes-mobile-shop' ),
		);

		$label = $labels[ $item['status'] ] ?? ( Cmshop_Money::to_cents( (string) $item['refunded'] ) > 0 ? __( 'Part returned', 'codereyes-mobile-shop' ) : __( 'Completed', 'codereyes-mobile-shop' ) );

		return Cmshop_View::badge( $label, (string) $item['status'] );
	}
}
