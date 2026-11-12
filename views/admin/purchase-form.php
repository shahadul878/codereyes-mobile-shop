<?php
/**
 * New purchase: a whole delivery into stock at once.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { values: array, errors: array, suppliers: array<int,string>, accessories: array[] }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_values = $cmshop['values'];
$cmshop_errors = $cmshop['errors'];
$cmshop_lines  = array_values( array_filter( (array) ( $cmshop_values['lines'] ?? array() ), 'is_array' ) );
$cmshop_lines  = array() === $cmshop_lines ? array( array( 'type' => 'phone' ) ) : $cmshop_lines;
$cmshop_value  = static function ( array $row, string $field ): string {
	return (string) ( $row[ $field ] ?? '' );
};

/**
 * One line of the delivery. $index is a number, or "__i__" inside templates.
 */
$cmshop_line = static function ( string $type, string $index, array $row, array $errors ) use ( $cmshop_value, $cmshop ): void {
	$name   = static function ( string $field ) use ( $index ): string {
		return 'lines[' . $index . '][' . $field . ']';
	};
	$id     = static function ( string $field ) use ( $index ): string {
		return 'cmshop-lines-' . $index . '-' . $field;
	};
	$field  = static function ( string $field, string $label, string $input ) use ( $index, $errors, $id ): void {
		$error = $errors[ 'lines-' . $index . '-' . $field ] ?? '';
		printf( '<div class="cmshop-field%s"><label for="%s">%s</label>%s', '' === $error ? '' : ' has-error', esc_attr( $id( $field ) ), esc_html( $label ), $input ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $input is built from escaped parts below.
		if ( '' !== $error ) {
			printf( '<p class="cmshop-field-error">%s</p>', esc_html( $error ) );
		}
		echo '</div>';
	};
	$text   = static function ( string $f, string $mode = 'text', string $placeholder = '' ) use ( $name, $id, $row, $cmshop_value ): string {
		return sprintf( '<input type="text" id="%s" name="%s" value="%s" inputmode="%s" placeholder="%s" data-field="%s">', esc_attr( $id( $f ) ), esc_attr( $name( $f ) ), esc_attr( $cmshop_value( $row, $f ) ), esc_attr( $mode ), esc_attr( $placeholder ), esc_attr( $f ) );
	};
	$is_new = 'phone' === $type;
	?>
	<section class="cmshop-card cmshop-pline cmshop-pline--<?php echo esc_attr( $type ); ?>" data-line data-type="<?php echo esc_attr( $type ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $name( 'type' ) ); ?>" value="<?php echo esc_attr( $type ); ?>">
		<header class="cmshop-pline__head">
			<h2><?php echo esc_html( $is_new ? __( 'Phones', 'codereyes-mobile-shop' ) : __( 'Accessories', 'codereyes-mobile-shop' ) ); ?></h2>
			<span class="cmshop-pline__total" data-line-total></span>
			<button type="button" class="button-link cmshop-danger" data-remove-line><?php esc_html_e( 'Remove', 'codereyes-mobile-shop' ); ?></button>
		</header>

		<?php if ( $is_new ) : ?>
			<div class="cmshop-pline__grid">
				<?php
				$field( 'brand', __( 'Brand', 'codereyes-mobile-shop' ), $text( 'brand', 'text', 'Samsung' ) );
				$field( 'model', __( 'Model', 'codereyes-mobile-shop' ), $text( 'model', 'text', 'Galaxy A15' ) );
				$field( 'storage', __( 'Storage', 'codereyes-mobile-shop' ), $text( 'storage', 'text', '128GB' ) );
				$field( 'color', __( 'Colour', 'codereyes-mobile-shop' ), $text( 'color', 'text', 'Black' ) );
				$grades = '';
				foreach ( Cmshop_Item::grades() as $key => $label ) {
					$grades .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( '' === $cmshop_value( $row, 'grade' ) ? 'new' : $cmshop_value( $row, 'grade' ), $key, false ), esc_html( $label ) );
				}
				$field( 'grade', __( 'Condition', 'codereyes-mobile-shop' ), sprintf( '<select id="%s" name="%s">%s</select>', esc_attr( $id( 'grade' ) ), esc_attr( $name( 'grade' ) ), $grades ) );
				$field( 'cost_price', __( 'You paid (each)', 'codereyes-mobile-shop' ), $text( 'cost_price', 'decimal' ) );
				$field( 'sale_price', __( 'Selling price', 'codereyes-mobile-shop' ), $text( 'sale_price', 'decimal' ) );
				?>
			</div>
			<?php
			$field(
				'imeis',
				__( 'IMEIs: scan each phone, one per line', 'codereyes-mobile-shop' ),
				sprintf( '<textarea id="%s" name="%s" rows="5" class="cmshop-imei-box" data-imeis spellcheck="false" placeholder="356938035643809">%s</textarea>', esc_attr( $id( 'imeis' ) ), esc_attr( $name( 'imeis' ) ), esc_textarea( $cmshop_value( $row, 'imeis' ) ) )
			);
			?>
			<p class="cmshop-imei-count" data-imei-count aria-live="polite"></p>
		<?php else : ?>
			<div class="cmshop-pline__grid">
				<?php
				$options = '<option value="0">' . esc_html__( '+ New accessory', 'codereyes-mobile-shop' ) . '</option>';
				foreach ( $cmshop['accessories'] as $item ) {
					/* translators: 1: accessory name, 2: units in stock. */
					$label    = sprintf( __( '%1$s · %2$d in stock', 'codereyes-mobile-shop' ), $item['name'] . ( '' === (string) $item['sku'] ? '' : ' (' . $item['sku'] . ')' ), (int) $item['quantity'] );
					$options .= sprintf( '<option value="%s" data-cost="%s" data-price="%s"%s>%s</option>', esc_attr( (string) $item['id'] ), esc_attr( (string) $item['cost_price'] ), esc_attr( (string) $item['sale_price'] ), selected( $cmshop_value( $row, 'item_id' ), (string) $item['id'], false ), esc_html( $label ) );
				}
				$field( 'item_id', __( 'Item', 'codereyes-mobile-shop' ), sprintf( '<select id="%s" name="%s" data-restock>%s</select>', esc_attr( $id( 'item_id' ) ), esc_attr( $name( 'item_id' ) ), $options ) );
				$field( 'name', __( 'New accessory name', 'codereyes-mobile-shop' ), $text( 'name', 'text', __( 'USB-C cable 1m', 'codereyes-mobile-shop' ) ) );
				$field( 'sku', __( 'SKU / barcode', 'codereyes-mobile-shop' ), $text( 'sku' ) );
				$field( 'quantity', __( 'How many', 'codereyes-mobile-shop' ), $text( 'quantity', 'numeric' ) );
				$field( 'cost_price', __( 'You paid (each)', 'codereyes-mobile-shop' ), $text( 'cost_price', 'decimal' ) );
				$field( 'sale_price', __( 'Selling price', 'codereyes-mobile-shop' ), $text( 'sale_price', 'decimal' ) );
				?>
			</div>
		<?php endif; ?>
	</section>
	<?php
};
?>
<div class="wrap cmshop-wrap cmshop-purchase">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'New purchase', 'codereyes-mobile-shop' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG ) ); ?>"><?php esc_html_e( 'Suppliers', 'codereyes-mobile-shop' ); ?></a>
	<hr class="wp-header-end">
	<?php Cmshop_View::notice(); ?>
	<?php Cmshop_View::error_summary( $cmshop_errors ); ?>

	<?php Cmshop_View::form_open( 'save_purchase' ); ?>
		<section class="cmshop-card">
			<h2><?php esc_html_e( 'Supplier and invoice', 'codereyes-mobile-shop' ); ?></h2>
			<div class="cmshop-row cmshop-row--3">
				<?php Cmshop_View::select( 'supplier_id', __( 'Supplier', 'codereyes-mobile-shop' ), array( '0' => __( '+ New supplier', 'codereyes-mobile-shop' ) ) + array_map( 'strval', $cmshop['suppliers'] ), $cmshop_values, $cmshop_errors ); ?>
				<?php Cmshop_View::input( 'ref_no', __( 'Their invoice no.', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors ); ?>
				<?php Cmshop_View::input( 'purchased_on', __( 'Date received', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors, array( 'type' => 'date', 'class' => '' ) ); ?>
			</div>
			<div class="cmshop-row" data-new-supplier>
				<?php Cmshop_View::input( 'supplier_name', __( 'New supplier name', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors ); ?>
				<?php Cmshop_View::input( 'supplier_phone', __( 'Supplier phone', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors, array( 'type' => 'tel', 'inputmode' => 'tel' ) ); ?>
			</div>
		</section>

		<div class="cmshop-plines" data-lines>
			<?php foreach ( $cmshop_lines as $cmshop_index => $cmshop_row ) : ?>
				<?php $cmshop_line( 'phone' === ( $cmshop_row['type'] ?? 'phone' ) ? 'phone' : 'accessory', (string) $cmshop_index, $cmshop_row, $cmshop_errors ); ?>
			<?php endforeach; ?>
		</div>
		<?php if ( isset( $cmshop_errors['lines'] ) ) : ?>
			<p class="cmshop-field-error" id="cmshop-lines"><?php echo esc_html( $cmshop_errors['lines'] ); ?></p>
		<?php endif; ?>

		<p class="cmshop-plines__add">
			<button type="button" class="button button-large" data-add-line="phone"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span> <?php esc_html_e( 'Add phones (scan IMEIs)', 'codereyes-mobile-shop' ); ?></button>
			<button type="button" class="button button-large" data-add-line="accessory"><span class="dashicons dashicons-archive" aria-hidden="true"></span> <?php esc_html_e( 'Add accessories', 'codereyes-mobile-shop' ); ?></button>
		</p>

		<section class="cmshop-card">
			<h2><?php esc_html_e( 'Payment to the supplier', 'codereyes-mobile-shop' ); ?></h2>
			<div class="cmshop-row cmshop-row--3">
				<?php Cmshop_View::input( 'paid', __( 'Paid now', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors, array( 'inputmode' => 'decimal', 'class' => '', 'placeholder' => '0', 'help' => __( 'Leave 0 to owe the supplier the whole amount.', 'codereyes-mobile-shop' ) ) ); ?>
				<?php Cmshop_View::select( 'method', __( 'Paid by', 'codereyes-mobile-shop' ), Cmshop_Purchase_Service::methods(), $cmshop_values, $cmshop_errors ); ?>
				<?php Cmshop_View::input( 'reference', __( 'Reference', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors ); ?>
			</div>
			<p class="cmshop-purchase__quick">
				<button type="button" class="button" data-pay="all"><?php esc_html_e( 'Paid in full', 'codereyes-mobile-shop' ); ?></button>
				<button type="button" class="button" data-pay="none"><?php esc_html_e( 'On credit', 'codereyes-mobile-shop' ); ?></button>
				<span class="cmshop-purchase__owe" data-owe aria-live="polite"></span>
			</p>
		</section>

		<div class="cmshop-purchase__bar">
			<span><?php esc_html_e( 'Units', 'codereyes-mobile-shop' ); ?> <strong data-units>0</strong></span>
			<span><?php esc_html_e( 'Total', 'codereyes-mobile-shop' ); ?> <strong class="cmshop-purchase__total" data-total>0</strong></span>
			<?php submit_button( __( 'Add everything to stock', 'codereyes-mobile-shop' ), 'primary large', 'submit', false ); ?>
		</div>
	</form>

	<template id="cmshop-pline-phone"><?php $cmshop_line( 'phone', '__i__', array(), array() ); ?></template>
	<template id="cmshop-pline-accessory"><?php $cmshop_line( 'accessory', '__i__', array(), array() ); ?></template>
</div>
