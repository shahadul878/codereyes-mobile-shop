<?php
/**
 * A supplier's account: what is owed, deliveries, payments and the pay form.
 *
 * @package MobileShopManager
 *
 * @var array $cmshop { supplier: array, purchases: array[], payments: array[], owed: int, values: array, errors: array }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_supplier = $cmshop['supplier'];
$cmshop_format   = (string) get_option( 'date_format' );
$cmshop_labels   = Cmshop_Settings::payment_labels();
$cmshop_values   = array_merge( array( 'amount' => Cmshop_Money::from_cents( $cmshop['owed'] ) ), $cmshop['values'] );
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( (string) $cmshop_supplier['name'] ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG, array( 'action' => 'purchase', 'supplier' => (int) $cmshop_supplier['id'] ) ) ); ?>"><?php esc_html_e( 'New purchase', 'mobile-shop-manager' ); ?></a>
	<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG, array( 'action' => 'edit', 'id' => (int) $cmshop_supplier['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'mobile-shop-manager' ); ?></a>
	<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG ) ); ?>"><?php esc_html_e( 'All suppliers', 'mobile-shop-manager' ); ?></a>
	<hr class="wp-header-end">
	<?php Cmshop_View::notice(); ?>
	<?php Cmshop_View::error_summary( $cmshop['errors'] ); ?>

	<?php if ( '' !== (string) $cmshop_supplier['phone'] || '' !== (string) $cmshop_supplier['address'] ) : ?>
		<p class="cmshop-shift-meta"><?php echo esc_html( implode( ' · ', array_filter( array( (string) $cmshop_supplier['phone'], (string) $cmshop_supplier['address'] ) ) ) ); ?></p>
	<?php endif; ?>

	<div class="cmshop-form-grid">
		<section class="cmshop-card">
			<h2><?php esc_html_e( 'You owe', 'mobile-shop-manager' ); ?></h2>
			<p class="cmshop-shift-big"><?php echo esc_html( Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop['owed'] ) ) ); ?></p>
			<?php if ( $cmshop['owed'] > 0 ) : ?>
				<?php Cmshop_View::form_open( 'pay_supplier' ); ?>
					<input type="hidden" name="supplier_id" value="<?php echo esc_attr( (string) (int) $cmshop_supplier['id'] ); ?>">
					<div class="cmshop-row">
						<?php
						Cmshop_View::input(
							'amount',
							__( 'Amount paid', 'mobile-shop-manager' ),
							$cmshop_values,
							$cmshop['errors'],
							array(
								'inputmode' => 'decimal',
								'class'     => '',
								'required'  => true,
							)
						);
						Cmshop_View::select( 'method', __( 'Paid by', 'mobile-shop-manager' ), Cmshop_Purchase_Service::methods(), $cmshop_values, $cmshop['errors'] );
						?>
					</div>
					<?php Cmshop_View::input( 'reference', __( 'Reference (bank, bKash…)', 'mobile-shop-manager' ), $cmshop_values, $cmshop['errors'] ); ?>
					<p class="description"><?php esc_html_e( 'Settles the oldest deliveries first. Cash paid comes out of your cash register.', 'mobile-shop-manager' ); ?></p>
					<?php submit_button( __( 'Record payment', 'mobile-shop-manager' ), 'primary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<p class="cmshop-empty"><?php esc_html_e( 'Nothing owed. Every delivery is paid.', 'mobile-shop-manager' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="cmshop-card">
			<h2><?php esc_html_e( 'Payments made', 'mobile-shop-manager' ); ?></h2>
			<?php if ( array() === $cmshop['payments'] ) : ?>
				<p class="cmshop-empty"><?php esc_html_e( 'No payments yet.', 'mobile-shop-manager' ); ?></p>
			<?php else : ?>
				<ul class="cmshop-list cmshop-shift-petty">
					<?php foreach ( $cmshop['payments'] as $cmshop_payment ) : ?>
						<li>
							<span>
								<?php echo esc_html( Cmshop_View::date( (string) $cmshop_payment['created_at'], false ) . ' · ' . Cmshop_Item::label( $cmshop_labels, (string) $cmshop_payment['method'] ) ); ?>
								<?php if ( '' !== (string) $cmshop_payment['reference'] ) : ?>
									<small>(<?php echo esc_html( (string) $cmshop_payment['reference'] ); ?>)</small>
								<?php endif; ?>
							</span>
							<strong><?php echo esc_html( Cmshop_Settings::money( Cmshop_Money::from_cents( -Cmshop_Money::to_cents( (string) $cmshop_payment['amount'] ) ) ) ); ?></strong>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>

	<section class="cmshop-card">
		<h2><?php esc_html_e( 'Deliveries', 'mobile-shop-manager' ); ?></h2>
		<?php if ( array() === $cmshop['purchases'] ) : ?>
			<p class="cmshop-empty"><?php esc_html_e( 'No purchases from this supplier yet.', 'mobile-shop-manager' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'mobile-shop-manager' ); ?></th>
						<th><?php esc_html_e( 'Their invoice', 'mobile-shop-manager' ); ?></th>
						<th class="num"><?php esc_html_e( 'Units', 'mobile-shop-manager' ); ?></th>
						<th class="num"><?php esc_html_e( 'Total', 'mobile-shop-manager' ); ?></th>
						<th class="num"><?php esc_html_e( 'Paid', 'mobile-shop-manager' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cmshop['purchases'] as $cmshop_row ) : ?>
						<?php $cmshop_left = Cmshop_Money::to_cents( (string) $cmshop_row['total'] ) - Cmshop_Money::to_cents( (string) $cmshop_row['paid'] ); ?>
						<tr>
							<td><a href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG, array( 'action' => 'receipt', 'id' => (int) $cmshop_row['id'] ) ) ); ?>"><?php echo esc_html( (string) mysql2date( $cmshop_format, (string) $cmshop_row['purchased_on'] ) ); ?></a></td>
							<td><?php echo esc_html( '' === (string) $cmshop_row['ref_no'] ? '—' : (string) $cmshop_row['ref_no'] ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (int) $cmshop_row['units'] ) ); ?></td>
							<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_row['total'] ) ); ?></td>
							<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_row['paid'] ) ); ?></td>
							<td>
								<?php if ( $cmshop_left > 0 ) : ?>
									<span class="cmshop-badge cmshop-badge--reserved">
										<?php
										/* translators: %s: amount still owed on a delivery. */
										echo esc_html( sprintf( __( 'Owe %s', 'mobile-shop-manager' ), Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_left ) ) ) );
										?>
									</span>
								<?php else : ?>
									<span class="cmshop-badge cmshop-badge--completed"><?php esc_html_e( 'Paid', 'mobile-shop-manager' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>
</div>
