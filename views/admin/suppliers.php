<?php
/**
 * Suppliers with what the shop owes each one.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { suppliers: array[], owed: int }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_format = (string) get_option( 'date_format' );
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Suppliers', 'codereyes-mobile-shop' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG, array( 'action' => 'purchase' ) ) ); ?>"><?php esc_html_e( 'New purchase', 'codereyes-mobile-shop' ); ?></a>
	<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG, array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Add supplier', 'codereyes-mobile-shop' ); ?></a>
	<hr class="wp-header-end">
	<?php Cmshop_View::notice(); ?>

	<div class="cmshop-dues-summary">
		<div class="cmshop-card<?php echo $cmshop['owed'] > 0 ? ' is-alert' : ''; ?>">
			<h2><?php esc_html_e( 'You owe suppliers', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-dues-summary__value"><?php echo esc_html( Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop['owed'] ) ) ); ?></p>
			<p><?php esc_html_e( 'Unpaid deliveries. Pay a supplier from their account.', 'codereyes-mobile-shop' ); ?></p>
		</div>
		<div class="cmshop-card">
			<h2><?php esc_html_e( 'Stock in fast', 'codereyes-mobile-shop' ); ?></h2>
			<p><?php esc_html_e( 'A new purchase adds a whole delivery at once: scan the IMEIs of every phone of a model, add accessories by quantity, then print price stickers for all of it.', 'codereyes-mobile-shop' ); ?></p>
		</div>
	</div>

	<?php if ( array() === $cmshop['suppliers'] ) : ?>
		<p class="cmshop-empty"><?php esc_html_e( 'No suppliers yet. Record a purchase and type the supplier name, or add one now.', 'codereyes-mobile-shop' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Supplier', 'codereyes-mobile-shop' ); ?></th>
					<th class="num"><?php esc_html_e( 'Deliveries', 'codereyes-mobile-shop' ); ?></th>
					<th><?php esc_html_e( 'Last delivery', 'codereyes-mobile-shop' ); ?></th>
					<th class="num"><?php esc_html_e( 'You owe', 'codereyes-mobile-shop' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cmshop['suppliers'] as $cmshop_row ) : ?>
					<?php
					$cmshop_url  = Cmshop_View::url( Cmshop_Supplier_Pages::SLUG, array( 'action' => 'view', 'id' => (int) $cmshop_row['id'] ) );
					$cmshop_owes = Cmshop_Money::to_cents( (string) $cmshop_row['owed'] );
					?>
					<tr>
						<td>
							<a class="row-title" href="<?php echo esc_url( $cmshop_url ); ?>"><?php echo esc_html( (string) $cmshop_row['name'] ); ?></a>
							<?php if ( '' !== (string) $cmshop_row['phone'] ) : ?>
								<br><small><?php echo esc_html( (string) $cmshop_row['phone'] ); ?></small>
							<?php endif; ?>
						</td>
						<td class="num"><?php echo esc_html( number_format_i18n( (int) $cmshop_row['purchases'] ) ); ?></td>
						<td><?php echo esc_html( null === $cmshop_row['last_purchase'] ? '—' : (string) mysql2date( $cmshop_format, (string) $cmshop_row['last_purchase'] ) ); ?></td>
						<td class="num">
							<?php if ( $cmshop_owes > 0 ) : ?>
								<strong><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_row['owed'] ) ); ?></strong>
							<?php else : ?>
								<span class="cmshop-badge cmshop-badge--completed"><?php esc_html_e( 'Settled', 'codereyes-mobile-shop' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="cmshop-row-buttons">
							<a class="button" href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG, array( 'action' => 'purchase', 'supplier' => (int) $cmshop_row['id'] ) ) ); ?>"><?php esc_html_e( 'New purchase', 'codereyes-mobile-shop' ); ?></a>
							<a class="button button-primary" href="<?php echo esc_url( $cmshop_url ); ?>"><?php echo esc_html( $cmshop_owes > 0 ? __( 'Pay', 'codereyes-mobile-shop' ) : __( 'Open', 'codereyes-mobile-shop' ) ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
