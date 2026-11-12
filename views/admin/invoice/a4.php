<?php
/**
 * A4 invoice for office printers. Included from sale-view.php.
 *
 * @package CodereyesMobileShop
 *
 * @var array      $cmshop          { sale, lines, customer }
 * @var array      $cmshop_sale     Sale row.
 * @var array      $cmshop_settings Shop settings.
 * @var bool       $cmshop_is_void  Whether the sale is void.
 * @var string     $cmshop_payment  Payment method label.
 * @var array      $cmshop_totals   Rows of [label, amount, class].
 * @var mixed|null $cmshop_order    WooCommerce order, if the sale came from the website.
 */

defined( 'ABSPATH' ) || exit;

$cmshop_customer = is_array( $cmshop['customer'] ) ? $cmshop['customer'] : array();
$cmshop_bill_to  = trim( (string) $cmshop_sale['customer_name'] );
$cmshop_channel  = 'online' === $cmshop_sale['channel'] ? __( 'Website', 'codereyes-mobile-shop' ) : __( 'In store', 'codereyes-mobile-shop' );
$cmshop_number   = 0;
?>
<article class="cmshop-invoice <?php echo $cmshop_is_void ? 'is-void' : ''; ?>">
	<header class="cmshop-invoice__head">
		<div class="cmshop-invoice__shop">
			<h2><?php echo esc_html( (string) $cmshop_settings['shop_name'] ); ?></h2>
			<?php if ( '' !== $cmshop_settings['shop_address'] ) : ?>
				<p><?php echo nl2br( esc_html( (string) $cmshop_settings['shop_address'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $cmshop_settings['shop_phone'] ) : ?>
				<p><?php echo esc_html( (string) $cmshop_settings['shop_phone'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="cmshop-invoice__title">
			<p class="cmshop-invoice__kicker"><?php esc_html_e( 'Invoice', 'codereyes-mobile-shop' ); ?></p>
			<p class="cmshop-invoice__number"><?php echo esc_html( (string) $cmshop_sale['invoice_no'] ); ?></p>
		</div>
	</header>

	<div class="cmshop-invoice__parties">
		<section>
			<h3><?php esc_html_e( 'Bill to', 'codereyes-mobile-shop' ); ?></h3>
			<p class="cmshop-invoice__name"><?php echo esc_html( '' !== $cmshop_bill_to ? $cmshop_bill_to : __( 'Walk-in customer', 'codereyes-mobile-shop' ) ); ?></p>
			<?php if ( '' !== (string) $cmshop_sale['customer_phone'] ) : ?>
				<p><?php echo esc_html( (string) $cmshop_sale['customer_phone'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== (string) ( $cmshop_customer['email'] ?? '' ) ) : ?>
				<p><?php echo esc_html( (string) $cmshop_customer['email'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== (string) ( $cmshop_customer['address'] ?? '' ) ) : ?>
				<p><?php echo nl2br( esc_html( (string) $cmshop_customer['address'] ) ); ?></p>
			<?php endif; ?>
		</section>
		<dl class="cmshop-invoice__meta">
			<dt><?php esc_html_e( 'Date', 'codereyes-mobile-shop' ); ?></dt>
			<dd><?php echo esc_html( Cmshop_View::date( (string) $cmshop_sale['created_at'] ) ); ?></dd>
			<dt><?php esc_html_e( 'Payment', 'codereyes-mobile-shop' ); ?></dt>
			<dd><?php echo esc_html( $cmshop_payment ); ?></dd>
			<dt><?php esc_html_e( 'Sold', 'codereyes-mobile-shop' ); ?></dt>
			<dd>
				<?php echo esc_html( $cmshop_channel ); ?>
				<?php if ( null !== $cmshop_order ) : ?>
					<?php echo esc_html( '· #' . $cmshop_order->get_order_number() ); ?>
				<?php endif; ?>
			</dd>
		</dl>
	</div>

	<?php if ( $cmshop_is_void ) : ?>
		<p class="cmshop-invoice__void"><?php esc_html_e( 'VOID', 'codereyes-mobile-shop' ); ?></p>
	<?php endif; ?>

	<table class="cmshop-invoice__lines">
		<thead>
			<tr>
				<th class="cmshop-invoice__no">#</th>
				<th><?php esc_html_e( 'Description', 'codereyes-mobile-shop' ); ?></th>
				<th class="num"><?php esc_html_e( 'Qty', 'codereyes-mobile-shop' ); ?></th>
				<th class="num"><?php esc_html_e( 'Unit price', 'codereyes-mobile-shop' ); ?></th>
				<th class="num"><?php esc_html_e( 'Amount', 'codereyes-mobile-shop' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cmshop['lines'] as $cmshop_line ) : ?>
				<?php ++$cmshop_number; ?>
				<tr>
					<td class="cmshop-invoice__no"><?php echo esc_html( (string) $cmshop_number ); ?></td>
					<td>
						<?php echo esc_html( (string) $cmshop_line['description'] ); ?>
						<?php if ( '' !== (string) $cmshop_line['imei'] ) : ?>
							<span class="cmshop-invoice__imei">IMEI <?php echo esc_html( (string) $cmshop_line['imei'] ); ?></span>
						<?php endif; ?>
					</td>
					<td class="num"><?php echo esc_html( (string) (int) $cmshop_line['quantity'] ); ?></td>
					<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_line['unit_price'] ) ); ?></td>
					<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_line['line_total'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div class="cmshop-invoice__summary">
		<div class="cmshop-invoice__note">
			<?php if ( '' !== (string) $cmshop_settings['receipt_footer'] ) : ?>
				<h3><?php esc_html_e( 'Terms', 'codereyes-mobile-shop' ); ?></h3>
				<p><?php echo nl2br( esc_html( (string) $cmshop_settings['receipt_footer'] ) ); ?></p>
			<?php endif; ?>
		</div>
		<table class="cmshop-invoice__totals">
			<?php foreach ( $cmshop_totals as $cmshop_row ) : ?>
				<tr class="<?php echo esc_attr( $cmshop_row[2] ); ?>"><th scope="row"><?php echo esc_html( $cmshop_row[0] ); ?></th><td class="num"><?php echo esc_html( $cmshop_row[1] ); ?></td></tr>
			<?php endforeach; ?>
		</table>
	</div>

	<footer class="cmshop-invoice__sign">
		<p><?php esc_html_e( 'Customer signature', 'codereyes-mobile-shop' ); ?></p>
		<p><?php esc_html_e( 'Authorised signature', 'codereyes-mobile-shop' ); ?></p>
	</footer>
</article>
