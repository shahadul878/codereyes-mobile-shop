<?php
/**
 * Repairs list table.
 *
 * @package CodereyesMobileShop
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
			'ticket_code'   => __( 'Ticket', 'codereyes-mobile-shop' ),
			'device'        => __( 'Device', 'codereyes-mobile-shop' ),
			'customer_name' => __( 'Customer', 'codereyes-mobile-shop' ),
			'status'        => __( 'Status', 'codereyes-mobile-shop' ),
			'estimate'      => __( 'Estimate', 'codereyes-mobile-shop' ),
			'due_date'      => __( 'Due', 'codereyes-mobile-shop' ),
			'created_at'    => __( 'Booked in', 'codereyes-mobile-shop' ),
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
		return array( 'status' => array( __( 'Status', 'codereyes-mobile-shop' ), Cmshop_Repair_Status::all() ) );
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
				esc_html__( 'Edit', 'codereyes-mobile-shop' )
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
				esc_html__( 'Print ticket', 'codereyes-mobile-shop' )
			),
			'delete' => sprintf(
				'<a href="%s" class="cmshop-confirm" data-confirm="%s">%s</a>',
				esc_url( Cmshop_View::action_url( 'delete_repair', array( 'id' => $id ) ) ),
				esc_attr__( 'Delete this repair job?', 'codereyes-mobile-shop' ),
				esc_html__( 'Delete', 'codereyes-mobile-shop' )
			),
		);

		if ( in_array( $item['status'], Cmshop_Repair_Status::open(), true ) && '' !== (string) $item['customer_phone'] ) {
			$actions['whatsapp'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( Cmshop_Messages::repair( $item ), array( 'https' ) ),
				Cmshop_Repair_Status::READY === $item['status'] ? esc_html__( 'WhatsApp: ready', 'codereyes-mobile-shop' ) : esc_html__( 'WhatsApp update', 'codereyes-mobile-shop' )
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
