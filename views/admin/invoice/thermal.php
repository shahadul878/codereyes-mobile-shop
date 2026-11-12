<?php
/**
 * Thermal receipt (80 mm or 58 mm roll). Included from sale-view.php.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop          { sale, lines }
 * @var array $cmshop_sale     Sale row.
 * @var array $cmshop_settings Shop settings.
 * @var bool  $cmshop_is_void  Whether the sale is void.
 * @var string $cmshop_payment Payment method label.
 * @var array $cmshop_totals   Rows of [label, amount, class].
 */

defined( 'ABSPATH' ) || exit;

$cmshop_width = Cmshop_Invoice_Format::thermal_width( (string) $cmshop_settings['thermal_width'] );
$cmshop_class = 'cmshop-receipt cmshop-receipt--' . $cmshop_width . ( $cmshop_is_void ? ' is-void' : '' );
?>
<article class="<?php echo esc_attr( $cmshop_class ); ?>">
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
		<dt><?php esc_html_e( 'Payment', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( $cmshop_payment ); ?></dd>
	</dl>

	<?php if ( $cmshop_is_void ) : ?>
		<p class="cmshop-receipt__void"><?php esc_html_e( 'VOID', 'codereyes-mobile-shop' ); ?></p>
	<?php endif; ?>

	<table class="cmshop-receipt__lines">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Item', 'codereyes-mobile-shop' ); ?></th>
				<th class="num"><?php esc_html_e( 'Total', 'codereyes-mobile-shop' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cmshop['lines'] as $cmshop_line ) : ?>
				<tr class="cmshop-receipt__item">
					<td colspan="2">
						<?php echo esc_html( (string) $cmshop_line['description'] ); ?>
						<?php if ( '' !== (string) $cmshop_line['imei'] ) : ?>
							<br><small>IMEI <?php echo esc_html( (string) $cmshop_line['imei'] ); ?></small>
						<?php endif; ?>
					</td>
				</tr>
				<tr class="cmshop-receipt__qty">
					<td><?php echo esc_html( (int) $cmshop_line['quantity'] . ' × ' . Cmshop_Settings::money( (string) $cmshop_line['unit_price'] ) ); ?></td>
					<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_line['line_total'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<?php foreach ( $cmshop_totals as $cmshop_row ) : ?>
				<tr class="<?php echo esc_attr( $cmshop_row[2] ); ?>"><th><?php echo esc_html( $cmshop_row[0] ); ?></th><td class="num"><?php echo esc_html( $cmshop_row[1] ); ?></td></tr>
			<?php endforeach; ?>
		</tfoot>
	</table>

	<?php if ( '' !== (string) $cmshop_settings['receipt_footer'] ) : ?>
		<footer class="cmshop-receipt__foot"><?php echo nl2br( esc_html( (string) $cmshop_settings['receipt_footer'] ) ); ?></footer>
	<?php endif; ?>
</article>
