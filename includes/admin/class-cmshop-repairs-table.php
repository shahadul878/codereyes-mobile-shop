<?php
/**
 * Repairs list table.
 *
 * @package MobileShopManager
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Repairs list.
 */
final class Cmshop_Repairs_Table extends Cmshop_List_Table {

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'ticket_code'   => __( 'Ticket', 'mobile-shop-manager' ),
			'device'        => __( 'Device', 'mobile-shop-manager' ),
			'customer_name' => __( 'Customer', 'mobile-shop-manager' ),
			'status'        => __( 'Status', 'mobile-shop-manager' ),
			'estimate'      => __( 'Estimate', 'mobile-shop-manager' ),
			'due_date'      => __( 'Due', 'mobile-shop-manager' ),
			'created_at'    => __( 'Booked in', 'mobile-shop-manager' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns(): array {
		return array(
			'created_at' => array( 'created_at', true ),
			'due_date'   => array( 'due_date', false ),
			'status'     => array( 'status', false ),
		);
	}

	/**
	 * Filters.
	 *
	 * @return array
	 */
	protected function filter_choices(): array {
		return array( 'status' => array( __( 'Status', 'mobile-shop-manager' ), Cmshop_Repair_Status::all() ) );
	}

	/**
	 * Ticket link with actions.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_ticket_code( $item ): string {
		$id      = (int) $item['id'];
		$actions = array(
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					Cmshop_View::url(
						'cmshop-repairs',
						array(
							'action' => 'edit',
							'id'     => $id,
						)
					)
				),
				esc_html__( 'Edit', 'mobile-shop-manager' )
			),
			'print'  => sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					Cmshop_View::url(
						'cmshop-repairs',
						array(
							'action' => 'ticket',
							'id'     => $id,
						)
					)
				),
				esc_html__( 'Print ticket', 'mobile-shop-manager' )
			),
			'delete' => sprintf(
				'<a href="%s" class="cmshop-confirm" data-confirm="%s">%s</a>',
				esc_url( Cmshop_View::action_url( 'delete_repair', array( 'id' => $id ) ) ),
				esc_attr__( 'Delete this repair job?', 'mobile-shop-manager' ),
				esc_html__( 'Delete', 'mobile-shop-manager' )
			),
		);

		if ( in_array( $item['status'], Cmshop_Repair_Status::open(), true ) && '' !== (string) $item['customer_phone'] ) {
			$actions['whatsapp'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( Cmshop_Messages::repair( $item ), array( 'https' ) ),
				Cmshop_Repair_Status::READY === $item['status'] ? esc_html__( 'WhatsApp: ready', 'mobile-shop-manager' ) : esc_html__( 'WhatsApp update', 'mobile-shop-manager' )
			);
		}

		return $this->link(
			(string) $item['ticket_code'],
			array(
				'action' => 'edit',
				'id'     => $id,
			)
		) . $this->row_actions( $actions );
	}

	/**
	 * Customer.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_customer_name( $item ): string {
		return esc_html( (string) $item['customer_name'] ) . '<br><small>' . esc_html( (string) $item['customer_phone'] ) . '</small>';
	}

	/**
	 * Status.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_status( $item ): string {
		return Cmshop_View::badge( Cmshop_Repair_Status::label( (string) $item['status'] ), (string) $item['status'] );
	}

	/**
	 * Estimate.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_estimate( $item ): string {
		return $this->money( (string) ( $item['final_cost'] ?? $item['estimate'] ) );
	}

	/**
	 * Due date, highlighted when overdue.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_due_date( $item ): string {
		$due = (string) ( $item['due_date'] ?? '' );

		if ( '' === $due ) {
			return '—';
		}

		$overdue = $due < current_time( 'Y-m-d' ) && in_array( $item['status'], Cmshop_Repair_Status::open(), true ) && Cmshop_Repair_Status::READY !== $item['status'];
		$text    = esc_html( Cmshop_View::date( $due, false ) );

		return $overdue ? '<strong class="cmshop-overdue">' . $text . '</strong>' : $text;
	}

	/**
	 * Booked in.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_created_at( $item ): string {
		return esc_html( Cmshop_View::date( (string) $item['created_at'], false ) );
	}
}
