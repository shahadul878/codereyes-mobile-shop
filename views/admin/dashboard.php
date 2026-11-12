<?php
/**
 * Dashboard: today, this month, stock and repairs at a glance.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { stock, today, month, repairs, recent }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_stock   = $cmshop['stock'];
$cmshop_repairs = $cmshop['repairs'];
$cmshop_open    = array_sum( $cmshop_repairs );
$cmshop_ready   = $cmshop_repairs[ Cmshop_Repair_Status::READY ] ?? 0;
?>
<div class="wrap cmshop-wrap cmshop-dashboard">
	<hr class="wp-header-end">
	<div class="cmshop-hero">
		<div>
			<h1><?php echo esc_html( (string) Cmshop_Settings::get( 'shop_name' ) ); ?></h1>
			<p class="cmshop-hero__date"><?php echo esc_html( wp_date( 'l, j F Y' ) ); ?></p>
		</div>
		<div class="cmshop-hero__actions">
			<a class="button button-primary button-hero" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-pos' ) ); ?>"><?php esc_html_e( 'New sale', 'codereyes-mobile-shop' ); ?></a>
			<a class="button button-hero" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-repairs', array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Book in repair', 'codereyes-mobile-shop' ); ?></a>
			<a class="button button-hero" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items', array( 'action' => 'new', 'type' => 'phone' ) ) ); ?>"><?php esc_html_e( 'Add phone', 'codereyes-mobile-shop' ); ?></a>
		</div>
	</div>

	<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'Install WooCommerce to sell your stock online. Counter sales, repairs and stock work without it.', 'codereyes-mobile-shop' ); ?></p></div>
	<?php endif; ?>

	<div class="cmshop-stats">
		<section class="cmshop-stat cmshop-stat--lead">
			<h2><?php esc_html_e( 'Today', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-stat__value"><?php echo esc_html( Cmshop_Settings::money( $cmshop['today']['total'] ) ); ?></p>
			<p class="cmshop-stat__meta">
				<?php
				/* translators: 1: number of sales, 2: profit. */
				echo esc_html( sprintf( _n( '%1$d sale · %2$s profit', '%1$d sales · %2$s profit', $cmshop['today']['count'], 'codereyes-mobile-shop' ), $cmshop['today']['count'], Cmshop_Settings::money( $cmshop['today']['profit'] ) ) );
				?>
			</p>
		</section>
		<section class="cmshop-stat">
			<h2><?php esc_html_e( 'This month', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-stat__value"><?php echo esc_html( Cmshop_Settings::money( $cmshop['month']['total'] ) ); ?></p>
			<p class="cmshop-stat__meta">
				<?php
				/* translators: 1: number of sales, 2: profit. */
				echo esc_html( sprintf( _n( '%1$d sale · %2$s profit', '%1$d sales · %2$s profit', $cmshop['month']['count'], 'codereyes-mobile-shop' ), $cmshop['month']['count'], Cmshop_Settings::money( $cmshop['month']['profit'] ) ) );
				?>
			</p>
		</section>
		<section class="cmshop-stat">
			<h2><?php esc_html_e( 'In stock', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-stat__value">
				<?php
				/* translators: %d: number of phones. */
				echo esc_html( sprintf( _n( '%d phone', '%d phones', $cmshop_stock['phones'], 'codereyes-mobile-shop' ), $cmshop_stock['phones'] ) );
				?>
			</p>
			<p class="cmshop-stat__meta">
				<?php
				/* translators: 1: accessory units, 2: stock value at cost. */
				echo esc_html( sprintf( __( '%1$d accessories · %2$s at cost', 'codereyes-mobile-shop' ), $cmshop_stock['accessories'], Cmshop_Settings::money( $cmshop_stock['value'] ) ) );
				?>
			</p>
		</section>
		<section class="cmshop-stat <?php echo $cmshop_ready > 0 ? 'cmshop-stat--alert' : ''; ?>">
			<h2><?php esc_html_e( 'Repairs', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-stat__value">
				<?php
				/* translators: %d: open repair jobs. */
				echo esc_html( sprintf( _n( '%d open', '%d open', $cmshop_open, 'codereyes-mobile-shop' ), $cmshop_open ) );
				?>
			</p>
			<p class="cmshop-stat__meta">
				<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-repairs', array( 'status' => Cmshop_Repair_Status::READY ) ) ); ?>">
					<?php
					/* translators: %d: repairs ready to collect. */
					echo esc_html( sprintf( _n( '%d ready to collect', '%d ready to collect', $cmshop_ready, 'codereyes-mobile-shop' ), $cmshop_ready ) );
					?>
				</a>
			</p>
		</section>
	</div>

	<div class="cmshop-columns">
		<section class="cmshop-panel cmshop-panel--wide">
			<h2><?php esc_html_e( 'Recent sales', 'codereyes-mobile-shop' ); ?></h2>
			<?php if ( array() === $cmshop['recent'] ) : ?>
				<p class="cmshop-empty"><?php esc_html_e( 'No sales yet. Add your stock, then open New sale to ring up the first one.', 'codereyes-mobile-shop' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Invoice', 'codereyes-mobile-shop' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'codereyes-mobile-shop' ); ?></th>
							<th><?php esc_html_e( 'Channel', 'codereyes-mobile-shop' ); ?></th>
							<th class="num"><?php esc_html_e( 'Total', 'codereyes-mobile-shop' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cmshop['recent'] as $cmshop_sale ) : ?>
							<tr class="<?php echo esc_attr( 'void' === $cmshop_sale['status'] ? 'cmshop-void' : '' ); ?>">
								<td><a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-sales', array( 'action' => 'view', 'id' => (int) $cmshop_sale['id'] ) ) ); ?>"><?php echo esc_html( (string) $cmshop_sale['invoice_no'] ); ?></a><br><small><?php echo esc_html( Cmshop_View::date( (string) $cmshop_sale['created_at'] ) ); ?></small></td>
								<td><?php echo esc_html( (string) $cmshop_sale['customer_name'] ); ?></td>
								<td><?php echo wp_kses_post( Cmshop_View::badge( 'online' === $cmshop_sale['channel'] ? __( 'Online', 'codereyes-mobile-shop' ) : __( 'In store', 'codereyes-mobile-shop' ), (string) $cmshop_sale['channel'] ) ); ?></td>
								<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_sale['total'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<section class="cmshop-panel">
			<h2><?php esc_html_e( 'Low stock accessories', 'codereyes-mobile-shop' ); ?></h2>
			<?php if ( array() === $cmshop_stock['low'] ) : ?>
				<p class="cmshop-empty"><?php esc_html_e( 'Nothing is running low.', 'codereyes-mobile-shop' ); ?></p>
			<?php else : ?>
				<ul class="cmshop-low">
					<?php foreach ( $cmshop_stock['low'] as $cmshop_low ) : ?>
						<li>
							<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items', array( 'action' => 'edit', 'id' => (int) $cmshop_low['id'] ) ) ); ?>"><?php echo esc_html( (string) $cmshop_low['name'] ); ?></a>
							<span class="cmshop-low__qty <?php echo 0 === (int) $cmshop_low['quantity'] ? 'is-out' : ''; ?>"><?php echo esc_html( (string) (int) $cmshop_low['quantity'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Repair queue', 'codereyes-mobile-shop' ); ?></h2>
			<ul class="cmshop-queue">
				<?php foreach ( $cmshop_repairs as $cmshop_status => $cmshop_count ) : ?>
					<li>
						<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-repairs', array( 'status' => $cmshop_status ) ) ); ?>"><?php echo esc_html( Cmshop_Repair_Status::label( $cmshop_status ) ); ?></a>
						<span><?php echo esc_html( (string) $cmshop_count ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	</div>
</div>
