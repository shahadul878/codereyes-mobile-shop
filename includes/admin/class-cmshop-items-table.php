<?php
/**
 * Items list table.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Stock list.
 */
final class Cmshop_Items_Table extends Cmshop_List_Table {

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox">',
			'name'       => __( 'Item', 'codereyes-mobile-shop' ),
			'imei1'      => __( 'IMEI / SKU', 'codereyes-mobile-shop' ),
			'grade'      => __( 'Condition', 'codereyes-mobile-shop' ),
			'quantity'   => __( 'Qty', 'codereyes-mobile-shop' ),
			'cost_price' => __( 'Cost', 'codereyes-mobile-shop' ),
			'sale_price' => __( 'Price', 'codereyes-mobile-shop' ),
			'status'     => __( 'Status', 'codereyes-mobile-shop' ),
			'online'     => __( 'Online', 'codereyes-mobile-shop' ),
			'created_at' => __( 'Added', 'codereyes-mobile-shop' ),
		);
	}

	/**
	 * Bulk actions: only printing, nothing destructive.
	 *
	 * @return array
	 */
	protected function get_bulk_actions(): array {
		return array( 'stickers' => __( 'Print price stickers', 'codereyes-mobile-shop' ) );
	}

	/**
	 * Row checkbox.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<label class="screen-reader-text" for="cmshop-item-%1$d">%2$s</label><input type="checkbox" id="cmshop-item-%1$d" name="item[]" value="%1$d">',
			(int) $item['id'],
			/* translators: %s: item name. */
			esc_html( sprintf( __( 'Select %s', 'codereyes-mobile-shop' ), Cmshop_Item::display_name( $item ) ) )
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
			'quantity'   => array( 'quantity', false ),
			'sale_price' => array( 'sale_price', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Filters.
	 *
	 * @return array
	 */
	protected function filter_choices(): array {
		return array(
			'item_type' => array( __( 'Type', 'codereyes-mobile-shop' ), Cmshop_Item::types() ),
			'status'    => array( __( 'Status', 'codereyes-mobile-shop' ), Cmshop_Item::statuses() ),
			'grade'     => array( __( 'Condition', 'codereyes-mobile-shop' ), Cmshop_Item::grades() ),
		);
	}

	/**
	 * Name cell with row actions.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_name( $item ): string {
		$id      = (int) $item['id'];
		$actions = array(
			'edit'    => sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					Cmshop_View::url(
						'cmshop-items',
						array(
							'action' => 'edit',
							'id'     => $id,
						)
					)
				),
				esc_html__( 'Edit', 'codereyes-mobile-shop' )
			),
			'sell'    => sprintf( '<a href="%s">%s</a>', esc_url( Cmshop_View::url( 'cmshop-pos', array( 'add' => $id ) ) ), esc_html__( 'Sell', 'codereyes-mobile-shop' ) ),
			'sticker' => sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					Cmshop_View::url(
						'cmshop-items',
						array(
							'action' => 'stickers',
							'item'   => array( $id ),
						)
					)
				),
				esc_html__( 'Sticker', 'codereyes-mobile-shop' )
			),
			'delete'  => sprintf(
				'<a href="%s" class="cmshop-confirm" data-confirm="%s">%s</a>',
				esc_url( Cmshop_View::action_url( 'delete_item', array( 'id' => $id ) ) ),
				esc_attr__( 'Delete this stock item?', 'codereyes-mobile-shop' ),
				esc_html__( 'Delete', 'codereyes-mobile-shop' )
			),
		);

		if ( Cmshop_Item::STATUS_SOLD === $item['status'] ) {
			unset( $actions['sell'], $actions['sticker'] );
		}

		$type  = Cmshop_Item::label( Cmshop_Item::types(), (string) $item['item_type'] );
		$thumb = (int) ( $item['image_id'] ?? 0 ) > 0 ? wp_get_attachment_image( (int) $item['image_id'], array( 40, 40 ), false, array( 'alt' => '' ) ) : '';

		return ( '' === $thumb ? '' : '<span class="cmshop-thumb">' . $thumb . '</span>' ) . $this->link(
			Cmshop_Item::display_name( $item ),
			array(
				'action' => 'edit',
				'id'     => $id,
			)
		)
			. '<br><small>' . esc_html( $type ) . '</small>' . $this->row_actions( $actions );
	}

	/**
	 * IMEI or SKU.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_imei1( $item ): string {
		$value = Cmshop_Item::TYPE_PHONE === $item['item_type'] ? (string) $item['imei1'] : (string) $item['sku'];

		return '<code>' . esc_html( '' === $value ? '—' : $value ) . '</code>';
	}

	/**
	 * Condition.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_grade( $item ): string {
		$label = Cmshop_Item::label( Cmshop_Item::grades(), (string) $item['grade'] );

		if ( null !== $item['battery_health'] && '' !== $item['battery_health'] ) {
			$label .= ' · ' . (int) $item['battery_health'] . '%';
		}

		return esc_html( $label );
	}

	/**
	 * Cost.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_cost_price( $item ): string {
		return $this->money( (string) $item['cost_price'] );
	}

	/**
	 * Price.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_sale_price( $item ): string {
		return $this->money( (string) $item['sale_price'] );
	}

	/**
	 * Status badge.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_status( $item ): string {
		return Cmshop_View::badge( Cmshop_Item::label( Cmshop_Item::statuses(), (string) $item['status'] ), (string) $item['status'] );
	}

	/**
	 * Online product link.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_online( $item ): string {
		$product_id = (int) $item['wc_product_id'];

		if ( $product_id <= 0 ) {
			return '—';
		}

		return sprintf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $product_id ) ), esc_html__( 'Product', 'codereyes-mobile-shop' ) );
	}

	/**
	 * Date added.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_created_at( $item ): string {
		return esc_html( Cmshop_View::date( (string) $item['created_at'], false ) );
	}
}
