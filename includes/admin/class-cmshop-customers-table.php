<?php
/**
 * Customers list table.
 *
 * @package CodereyesMobileShopManager
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Customers list.
 */
final class Cmshop_Customers_Table extends Cmshop_List_Table {

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'name'       => __( 'Name', 'codereyes-mobile-shop-manager' ),
			'phone'      => __( 'Phone', 'codereyes-mobile-shop-manager' ),
			'email'      => __( 'Email', 'codereyes-mobile-shop-manager' ),
			'created_at' => __( 'Added', 'codereyes-mobile-shop-manager' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns(): array {
		return array(
			'name'       => array( 'name', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Name with actions.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_name( $item ): string {
		$id      = (int) $item['id'];
		$actions = array(
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					Cmshop_View::url(
						'cmshop-customers',
						array(
							'action' => 'edit',
							'id'     => $id,
						)
					)
				),
				esc_html__( 'Edit & history', 'codereyes-mobile-shop-manager' )
			),
			'delete' => sprintf(
				'<a href="%s" class="cmshop-confirm" data-confirm="%s">%s</a>',
				esc_url( Cmshop_View::action_url( 'delete_customer', array( 'id' => $id ) ) ),
				esc_attr__( 'Delete this customer?', 'codereyes-mobile-shop-manager' ),
				esc_html__( 'Delete', 'codereyes-mobile-shop-manager' )
			),
		);

		return $this->link(
			(string) $item['name'],
			array(
				'action' => 'edit',
				'id'     => $id,
			)
		) . $this->row_actions( $actions );
	}

	/**
	 * Added.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_created_at( $item ): string {
		return esc_html( Cmshop_View::date( (string) $item['created_at'], false ) );
	}
}
