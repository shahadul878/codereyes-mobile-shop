<?php
/**
 * Spreadsheet-safe CSV cells and rows.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Column definitions and cell escaping for exports.
 */
final class Cmshop_Csv {

	/**
	 * Characters that make a spreadsheet treat a cell as a formula.
	 */
	private const FORMULA_TRIGGERS = array( '=', '+', '-', '@', "\t", "\r" );

	/**
	 * Columns that hold long digit strings (kept as text in Excel).
	 */
	private const NUMBER_COLUMNS = array( 'imei1', 'imei2', 'imei', 'phone', 'customer_phone' );

	/**
	 * Columns that hold validated amounts (never escaped, may be negative).
	 */
	private const MONEY_COLUMNS = array( 'cost_price', 'sale_price', 'subtotal', 'discount', 'total', 'cost_total', 'profit', 'amount_paid', 'estimate', 'deposit', 'final_cost' );

	/**
	 * Export columns per dataset: key => heading.
	 *
	 * @param string $dataset items|sales|repairs|customers.
	 * @return array<string,string>
	 */
	public static function columns( string $dataset ): array {
		$columns = array(
			'items'     => array(
				'id'             => 'ID',
				'item_type'      => 'Type',
				'name'           => 'Name',
				'brand'          => 'Brand',
				'model'          => 'Model',
				'storage'        => 'Storage',
				'color'          => 'Colour',
				'grade'          => 'Condition',
				'imei1'          => 'IMEI 1',
				'imei2'          => 'IMEI 2',
				'sku'            => 'SKU',
				'battery_health' => 'Battery %',
				'quantity'       => 'Quantity',
				'status'         => 'Status',
				'cost_price'     => 'Cost price',
				'sale_price'     => 'Sale price',
				'supplier'       => 'Supplier',
				'created_at'     => 'Added',
			),
			'sales'     => array(
				'invoice_no'     => 'Invoice',
				'created_at'     => 'Date',
				'channel'        => 'Channel',
				'status'         => 'Status',
				'customer_name'  => 'Customer',
				'customer_phone' => 'Phone',
				'payment_method' => 'Payment',
				'subtotal'       => 'Subtotal',
				'discount'       => 'Discount',
				'total'          => 'Total',
				'amount_paid'    => 'Paid',
				'cost_total'     => 'Cost',
				'profit'         => 'Profit',
			),
			'repairs'   => array(
				'ticket_code'    => 'Ticket',
				'created_at'     => 'Booked in',
				'customer_name'  => 'Customer',
				'customer_phone' => 'Phone',
				'device'         => 'Device',
				'imei'           => 'IMEI',
				'fault'          => 'Fault',
				'status'         => 'Status',
				'estimate'       => 'Estimate',
				'deposit'        => 'Deposit',
				'final_cost'     => 'Final cost',
				'due_date'       => 'Due',
			),
			'customers' => array(
				'id'         => 'ID',
				'name'       => 'Name',
				'phone'      => 'Phone',
				'email'      => 'Email',
				'address'    => 'Address',
				'created_at' => 'Added',
			),
		);

		return $columns[ $dataset ] ?? array();
	}

	/**
	 * One CSV row for a database row, in column order.
	 *
	 * @param array                $row     Database row.
	 * @param array<string,string> $columns Columns.
	 * @return string[]
	 */
	public static function row( array $row, array $columns ): array {
		$cells = array();

		foreach ( array_keys( $columns ) as $key ) {
			$cells[] = self::cell( $key, isset( $row[ $key ] ) ? (string) $row[ $key ] : '' );
		}

		return $cells;
	}

	/**
	 * Make one cell safe.
	 *
	 * @param string $key   Column key.
	 * @param string $value Value.
	 * @return string
	 */
	public static function cell( string $key, string $value ): string {
		if ( '' === $value || in_array( $key, self::MONEY_COLUMNS, true ) ) {
			return $value;
		}

		// Validated numbers contain only digits and an optional leading "+".
		if ( in_array( $key, self::NUMBER_COLUMNS, true ) && 1 === preg_match( '/^\+?\d+$/', $value ) ) {
			return "\t" . $value;
		}

		return in_array( $value[0], self::FORMULA_TRIGGERS, true ) ? "'" . $value : $value;
	}
}
