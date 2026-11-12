<?php
/**
 * Return items from a sale.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { sale: array, lines: array[], methods: array<string,string>, errors: array }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_sale   = $cmshop['sale'];
$cmshop_unpaid = Cmshop_Money::to_cents( (string) $cmshop_sale['amount_paid'] ) < Cmshop_Money::to_cents( (string) $cmshop_sale['total'] );
$cmshop_back   = Cmshop_View::url( 'cmshop-sales', array( 'action' => 'view', 'id' => (int) $cmshop_sale['id'] ) );
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline">
		<?php
		/* translators: %s: invoice number. */
		echo esc_html( sprintf( __( 'Return items from %s', 'codereyes-mobile-shop' ), (string) $cmshop_sale['invoice_no'] ) );
		?>
	</h1>
	<a class="page-title-action" href="<?php echo esc_url( $cmshop_back ); ?>"><?php esc_html_e( 'Back to sale', 'codereyes-mobile-shop' ); ?></a>
	<hr class="wp-header-end">
	<?php Cmshop_View::error_summary( $cmshop['errors'] ); ?>

	<?php if ( Cmshop_Sale_Repository::STATUS_COMPLETED !== $cmshop_sale['status'] ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'This sale is void or fully returned. Nothing more can come back.', 'codereyes-mobile-shop' ); ?></p></div>
	<?php elseif ( $cmshop_unpaid ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'This sale is not fully paid. Collect the balance under Dues first, or void the sale.', 'codereyes-mobile-shop' ); ?></p></div>
	<?php else : ?>
		<?php Cmshop_View::form_open( 'return_items' ); ?>
			<input type="hidden" name="sale_id" value="<?php echo esc_attr( (string) (int) $cmshop_sale['id'] ); ?>">
			<section class="cmshop-card">
				<h2><?php esc_html_e( 'What is coming back?', 'codereyes-mobile-shop' ); ?></h2>
				<table class="widefat cmshop-return-lines">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Item', 'codereyes-mobile-shop' ); ?></th>
							<th class="num"><?php esc_html_e( 'Paid', 'codereyes-mobile-shop' ); ?></th>
							<th><?php esc_html_e( 'Return', 'codereyes-mobile-shop' ); ?></th>
							<th><?php esc_html_e( 'Back in stock', 'codereyes-mobile-shop' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cmshop['lines'] as $cmshop_line ) : ?>
							<?php $cmshop_left = (int) $cmshop_line['quantity'] - (int) $cmshop_line['returned_qty']; ?>
							<tr>
								<td>
									<strong><?php echo esc_html( (string) $cmshop_line['description'] ); ?></strong>
									<?php if ( '' !== (string) $cmshop_line['imei'] ) : ?>
										<br><code>IMEI <?php echo esc_html( (string) $cmshop_line['imei'] ); ?></code>
									<?php endif; ?>
									<?php if ( (int) $cmshop_line['returned_qty'] > 0 ) : ?>
										<br><small>
											<?php
											/* translators: %d: quantity already returned. */
											echo esc_html( sprintf( __( '%d already returned', 'codereyes-mobile-shop' ), (int) $cmshop_line['returned_qty'] ) );
											?>
										</small>
									<?php endif; ?>
								</td>
								<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_line['line_total'] ) ); ?></td>
								<td>
									<?php if ( $cmshop_left > 0 ) : ?>
										<label class="screen-reader-text" for="cmshop-qty-<?php echo esc_attr( (string) (int) $cmshop_line['id'] ); ?>"><?php esc_html_e( 'Quantity to return', 'codereyes-mobile-shop' ); ?></label>
										<input type="number" class="small-text" id="cmshop-qty-<?php echo esc_attr( (string) (int) $cmshop_line['id'] ); ?>" name="qty[<?php echo esc_attr( (string) (int) $cmshop_line['id'] ); ?>]" min="0" max="<?php echo esc_attr( (string) $cmshop_left ); ?>" value="0">
										<small>
											<?php
											/* translators: %d: quantity that can be returned. */
											echo esc_html( sprintf( __( 'of %d', 'codereyes-mobile-shop' ), $cmshop_left ) );
											?>
										</small>
									<?php else : ?>
										<?php esc_html_e( 'Returned', 'codereyes-mobile-shop' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $cmshop_left > 0 ) : ?>
										<label><input type="checkbox" name="restock[]" value="<?php echo esc_attr( (string) (int) $cmshop_line['id'] ); ?>" checked> <?php esc_html_e( 'Yes', 'codereyes-mobile-shop' ); ?></label>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Untick "Back in stock" for a faulty item you will send back to the supplier. The refund is the item\'s share of what was paid, after any discount.', 'codereyes-mobile-shop' ); ?></p>
			</section>

			<section class="cmshop-card">
				<h2><?php esc_html_e( 'Money back', 'codereyes-mobile-shop' ); ?></h2>
				<div class="cmshop-row">
					<?php Cmshop_View::select( 'method', __( 'Give it back as', 'codereyes-mobile-shop' ), $cmshop['methods'], array(), array() ); ?>
					<?php Cmshop_View::input( 'reason', __( 'Reason', 'codereyes-mobile-shop' ), array(), array(), array( 'placeholder' => __( 'Changed mind, faulty, exchange…', 'codereyes-mobile-shop' ), 'class' => '' ) ); ?>
				</div>
				<?php if ( isset( $cmshop['methods'][ Cmshop_Returns_Service::STORE_CREDIT ] ) ) : ?>
					<p class="description"><?php esc_html_e( 'For an exchange, choose store credit: the customer can spend it on their next sale at the POS.', 'codereyes-mobile-shop' ); ?></p>
				<?php endif; ?>
				<?php submit_button( __( 'Record return', 'codereyes-mobile-shop' ), 'primary large', 'submit', false ); ?>
			</section>
		</form>
	<?php endif; ?>
</div>
