<?php
/**
 * Sale receipt (printable) with void action.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { sale: array, lines: array[] }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_sale     = $cmshop['sale'];
$cmshop_settings = Cmshop_Settings::all();
$cmshop_is_void  = 'void' === $cmshop_sale['status'];
$cmshop_balance  = Cmshop_Money::to_cents( (string) $cmshop_sale['total'] ) - Cmshop_Money::to_cents( (string) $cmshop_sale['amount_paid'] );
?>
<div class="wrap cmshop-wrap">
	<div class="cmshop-no-print">
		<h1 class="wp-heading-inline">
			<?php
			/* translators: %s: invoice number. */
			echo esc_html( sprintf( __( 'Sale %s', 'codereyes-mobile-shop' ), (string) $cmshop_sale['invoice_no'] ) );
			?>
		</h1>
		<button type="button" class="page-title-action cmshop-print"><?php esc_html_e( 'Print receipt', 'codereyes-mobile-shop' ); ?></button>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-pos' ) ); ?>"><?php esc_html_e( 'New sale', 'codereyes-mobile-shop' ); ?></a>
		<?php if ( ! $cmshop_is_void ) : ?>
			<a class="page-title-action cmshop-danger cmshop-confirm" data-confirm="<?php esc_attr_e( 'Void this sale and put its items back in stock?', 'codereyes-mobile-shop' ); ?>" href="<?php echo esc_url( Cmshop_View::action_url( 'void_sale', array( 'id' => (int) $cmshop_sale['id'] ) ) ); ?>"><?php esc_html_e( 'Void / return', 'codereyes-mobile-shop' ); ?></a>
		<?php endif; ?>
		<hr class="wp-header-end">
		<?php Cmshop_View::notice(); ?>
		<?php if ( (int) $cmshop_sale['wc_order_id'] > 0 && function_exists( 'wc_get_order' ) ) : ?>
			<?php $cmshop_order = wc_get_order( (int) $cmshop_sale['wc_order_id'] ); ?>
			<?php if ( $cmshop_order ) : ?>
				<p>
					<?php esc_html_e( 'From website order', 'codereyes-mobile-shop' ); ?>
					<a href="<?php echo esc_url( $cmshop_order->get_edit_order_url() ); ?>">#<?php echo esc_html( (string) $cmshop_order->get_order_number() ); ?></a>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<article class="cmshop-receipt <?php echo $cmshop_is_void ? 'is-void' : ''; ?>">
		<header class="cmshop-receipt__head">
			<h2><?php echo esc_html( (string) $cmshop_settings['shop_name'] ); ?></h2>
			<?php if ( '' !== $cmshop_settings['shop_address'] ) : ?>
				<p><?php echo nl2br( esc_html( (string) $cmshop_settings['shop_address'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $cmshop_settings['shop_phone'] ) : ?>
				<p><?php echo esc_html( (string) $cmshop_settings['shop_phone'] ); ?></p>
			<?php endif; ?>
		</header>

		<dl class="cmshop-receipt__meta">
			<dt><?php esc_html_e( 'Invoice', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( (string) $cmshop_sale['invoice_no'] ); ?></dd>
			<dt><?php esc_html_e( 'Date', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( Cmshop_View::date( (string) $cmshop_sale['created_at'] ) ); ?></dd>
			<dt><?php esc_html_e( 'Customer', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( trim( $cmshop_sale['customer_name'] . ' ' . $cmshop_sale['customer_phone'] ) ); ?></dd>
			<dt><?php esc_html_e( 'Payment', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( Cmshop_Item::label( Cmshop_Settings::payment_methods(), (string) $cmshop_sale['payment_method'] ) ); ?></dd>
		</dl>

		<?php if ( $cmshop_is_void ) : ?>
			<p class="cmshop-receipt__void"><?php esc_html_e( 'VOID', 'codereyes-mobile-shop' ); ?></p>
		<?php endif; ?>

		<table class="cmshop-receipt__lines">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Item', 'codereyes-mobile-shop' ); ?></th>
					<th class="num"><?php esc_html_e( 'Qty', 'codereyes-mobile-shop' ); ?></th>
					<th class="num"><?php esc_html_e( 'Price', 'codereyes-mobile-shop' ); ?></th>
					<th class="num"><?php esc_html_e( 'Total', 'codereyes-mobile-shop' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cmshop['lines'] as $cmshop_line ) : ?>
					<tr>
						<td>
							<?php echo esc_html( (string) $cmshop_line['description'] ); ?>
							<?php if ( '' !== (string) $cmshop_line['imei'] ) : ?>
								<br><small>IMEI <?php echo esc_html( (string) $cmshop_line['imei'] ); ?></small>
							<?php endif; ?>
						</td>
						<td class="num"><?php echo esc_html( (string) (int) $cmshop_line['quantity'] ); ?></td>
						<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_line['unit_price'] ) ); ?></td>
						<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_line['line_total'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr><th colspan="3"><?php esc_html_e( 'Subtotal', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_sale['subtotal'] ) ); ?></td></tr>
				<?php if ( Cmshop_Money::to_cents( (string) $cmshop_sale['discount'] ) > 0 ) : ?>
					<tr><th colspan="3"><?php esc_html_e( 'Discount', 'codereyes-mobile-shop' ); ?></th><td class="num">-<?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_sale['discount'] ) ); ?></td></tr>
				<?php endif; ?>
				<tr class="cmshop-receipt__total"><th colspan="3"><?php esc_html_e( 'Total', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_sale['total'] ) ); ?></td></tr>
				<tr><th colspan="3"><?php esc_html_e( 'Paid', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_sale['amount_paid'] ) ); ?></td></tr>
				<?php if ( $cmshop_balance > 0 ) : ?>
					<tr class="cmshop-receipt__due"><th colspan="3"><?php esc_html_e( 'Balance due', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_balance ) ) ); ?></td></tr>
				<?php elseif ( $cmshop_balance < 0 ) : ?>
					<tr><th colspan="3"><?php esc_html_e( 'Change', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( Cmshop_Settings::money( Cmshop_Money::from_cents( -$cmshop_balance ) ) ); ?></td></tr>
				<?php endif; ?>
			</tfoot>
		</table>

		<?php if ( '' !== (string) $cmshop_settings['receipt_footer'] ) : ?>
			<footer class="cmshop-receipt__foot"><?php echo nl2br( esc_html( (string) $cmshop_settings['receipt_footer'] ) ); ?></footer>
		<?php endif; ?>
	</article>

	<p class="cmshop-no-print cmshop-profit">
		<?php
		/* translators: 1: cost, 2: profit. */
		echo esc_html( sprintf( __( 'Cost %1$s · Profit %2$s (not printed)', 'codereyes-mobile-shop' ), Cmshop_Settings::money( (string) $cmshop_sale['cost_total'] ), Cmshop_Settings::money( (string) $cmshop_sale['profit'] ) ) );
		?>
	</p>
</div>
