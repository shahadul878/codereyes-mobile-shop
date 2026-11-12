<?php
/**
 * Dashboard: today, 30-day chart, payment split, best sellers, slow stock, tasks.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop From Cmshop_Dashboard::data().
 */

defined( 'ABSPATH' ) || exit;

$cmshop_today    = $cmshop['today'];
$cmshop_change   = $cmshop['change'];
$cmshop_drawer   = $cmshop['drawer'];
$cmshop_methods  = Cmshop_Settings::payment_labels();
$cmshop_colors   = Cmshop_Dashboard::METHOD_COLORS;
$cmshop_open     = array_sum( $cmshop['repairs'] );
$cmshop_gradient = array();

foreach ( $cmshop['methods'] as $cmshop_segment ) {
	$cmshop_gradient[] = sprintf( '%s %d%% %d%%', $cmshop_colors[ $cmshop_segment['key'] ] ?? '#94a3b8', $cmshop_segment['start'], $cmshop_segment['start'] + $cmshop_segment['percent'] );
}
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

	<div class="cmshop-kpis">
		<section class="cmshop-kpi">
			<h2><span class="dashicons dashicons-cart" aria-hidden="true"></span><?php esc_html_e( 'Sales today', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-kpi__value"><?php echo esc_html( Cmshop_Settings::money( $cmshop_today['total'] ) ); ?></p>
			<p class="cmshop-kpi__meta">
				<?php if ( null === $cmshop_change ) : ?>
					<?php
					/* translators: %d: number of sales. */
					echo esc_html( sprintf( _n( '%d sale', '%d sales', $cmshop_today['count'], 'codereyes-mobile-shop' ), $cmshop_today['count'] ) );
					?>
				<?php else : ?>
					<span class="cmshop-delta cmshop-delta--<?php echo esc_attr( $cmshop_change >= 0 ? 'up' : 'down' ); ?>">
						<span class="dashicons dashicons-arrow-<?php echo esc_attr( $cmshop_change >= 0 ? 'up' : 'down' ); ?>-alt" aria-hidden="true"></span>
						<?php
						/* translators: %d: percent change from yesterday. */
						echo esc_html( sprintf( __( '%d%% vs yesterday', 'codereyes-mobile-shop' ), abs( $cmshop_change ) ) );
						?>
					</span>
				<?php endif; ?>
			</p>
		</section>
		<section class="cmshop-kpi">
			<h2><span class="dashicons dashicons-chart-line" aria-hidden="true"></span><?php esc_html_e( 'Profit today', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-kpi__value"><?php echo esc_html( Cmshop_Settings::money( $cmshop_today['profit'] ) ); ?></p>
			<p class="cmshop-kpi__meta">
				<?php
				/* translators: %d: profit margin percent. */
				echo esc_html( sprintf( __( '%d%% margin', 'codereyes-mobile-shop' ), $cmshop['margin'] ) );
				?>
			</p>
		</section>
		<section class="cmshop-kpi">
			<h2><span class="dashicons dashicons-money-alt" aria-hidden="true"></span><?php esc_html_e( 'Cash taken today', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-kpi__value"><?php echo esc_html( Cmshop_Settings::money( (string) ( $cmshop_drawer['cash'] ?? '0.00' ) ) ); ?></p>
			<p class="cmshop-kpi__meta">
				<?php
				/* translators: %s: amount received by mobile banking today. */
				echo esc_html( sprintf( __( 'Mobile banking %s', 'codereyes-mobile-shop' ), Cmshop_Settings::money( (string) ( $cmshop_drawer['mobile_banking'] ?? '0.00' ) ) ) );
				?>
			</p>
		</section>
		<section class="cmshop-kpi<?php echo $cmshop['dues']['sales'] > 0 ? ' cmshop-kpi--alert' : ''; ?>">
			<h2><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><?php esc_html_e( 'Owed to you', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-kpi__value"><a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-dues' ) ); ?>"><?php echo esc_html( Cmshop_Settings::money( $cmshop['dues']['amount'] ) ); ?></a></p>
			<p class="cmshop-kpi__meta">
				<?php
				/* translators: %d: sales not fully paid. */
				echo esc_html( sprintf( _n( '%d unpaid sale', '%d unpaid sales', $cmshop['dues']['sales'], 'codereyes-mobile-shop' ), $cmshop['dues']['sales'] ) );
				?>
			</p>
		</section>
	</div>

	<div class="cmshop-dash-grid">
		<section class="cmshop-panel cmshop-chart">
			<header class="cmshop-panel__head">
				<h2><?php esc_html_e( 'Sales and profit, last 30 days', 'codereyes-mobile-shop' ); ?></h2>
				<p>
					<?php
					/* translators: 1: total sales, 2: total profit. */
					echo esc_html( sprintf( __( '%1$s sales · %2$s profit', 'codereyes-mobile-shop' ), Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop['period']['total'] ) ), Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop['period']['profit'] ) ) ) );
					?>
				</p>
			</header>
			<?php if ( 0 === $cmshop['period']['sales'] ) : ?>
				<p class="cmshop-empty"><?php esc_html_e( 'Your first sales will show here as a daily chart.', 'codereyes-mobile-shop' ); ?></p>
			<?php else : ?>
				<ol class="cmshop-bars" aria-label="<?php esc_attr_e( 'Daily sales and profit', 'codereyes-mobile-shop' ); ?>">
					<?php foreach ( $cmshop['series'] as $cmshop_i => $cmshop_day ) : ?>
						<?php
						/* translators: 1: date, 2: sales, 3: profit. */
						$cmshop_tip = sprintf( __( '%1$s: %2$s sales, %3$s profit', 'codereyes-mobile-shop' ), mysql2date( 'j M', $cmshop_day['day'] ), Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_day['total'] ) ), Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_day['profit'] ) ) );
						?>
						<li title="<?php echo esc_attr( $cmshop_tip ); ?>">
							<span class="cmshop-bars__total" style="<?php echo esc_attr( 'height:' . $cmshop_day['total_pct'] . '%' ); ?>"></span>
							<span class="cmshop-bars__profit" style="<?php echo esc_attr( 'height:' . $cmshop_day['profit_pct'] . '%' ); ?>"></span>
							<?php if ( 0 === $cmshop_i % 5 || Cmshop_Dashboard::DAYS - 1 === $cmshop_i ) : ?>
								<span class="cmshop-bars__label"><?php echo esc_html( mysql2date( 'j M', $cmshop_day['day'] ) ); ?></span>
							<?php endif; ?>
							<span class="screen-reader-text"><?php echo esc_html( $cmshop_tip ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
				<p class="cmshop-legend">
					<span><i class="cmshop-legend__dot cmshop-legend__dot--total"></i><?php esc_html_e( 'Sales', 'codereyes-mobile-shop' ); ?></span>
					<span><i class="cmshop-legend__dot cmshop-legend__dot--profit"></i><?php esc_html_e( 'Profit', 'codereyes-mobile-shop' ); ?></span>
				</p>
			<?php endif; ?>
		</section>

		<section class="cmshop-panel cmshop-donut-panel">
			<header class="cmshop-panel__head"><h2><?php esc_html_e( 'How customers paid', 'codereyes-mobile-shop' ); ?></h2></header>
			<?php if ( array() === $cmshop['methods'] ) : ?>
				<p class="cmshop-empty"><?php esc_html_e( 'No payments in the last 30 days.', 'codereyes-mobile-shop' ); ?></p>
			<?php else : ?>
				<div class="cmshop-donut" style="<?php echo esc_attr( 'background:conic-gradient(' . implode( ',', $cmshop_gradient ) . ')' ); ?>" role="img" aria-label="<?php esc_attr_e( 'Share of payments by method', 'codereyes-mobile-shop' ); ?>">
					<span><?php echo esc_html( (string) $cmshop['period']['sales'] ); ?><small><?php esc_html_e( 'sales', 'codereyes-mobile-shop' ); ?></small></span>
				</div>
				<ul class="cmshop-legend cmshop-legend--list">
					<?php foreach ( $cmshop['methods'] as $cmshop_segment ) : ?>
						<li>
							<i class="cmshop-legend__dot" style="<?php echo esc_attr( 'background:' . ( $cmshop_colors[ $cmshop_segment['key'] ] ?? '#94a3b8' ) ); ?>"></i>
							<?php echo esc_html( Cmshop_Item::label( $cmshop_methods, $cmshop_segment['key'] ) ); ?>
							<strong><?php echo esc_html( $cmshop_segment['percent'] . '%' ); ?></strong>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>

	<div class="cmshop-dash-grid cmshop-dash-grid--three">
		<section class="cmshop-panel">
			<header class="cmshop-panel__head"><h2><?php esc_html_e( 'Best sellers, 30 days', 'codereyes-mobile-shop' ); ?></h2></header>
			<?php if ( array() === $cmshop['top'] ) : ?>
				<p class="cmshop-empty"><?php esc_html_e( 'Nothing sold yet.', 'codereyes-mobile-shop' ); ?></p>
			<?php else : ?>
				<?php $cmshop_best = max( 1, (int) $cmshop['top'][0]['quantity'] ); ?>
				<ol class="cmshop-rank">
					<?php foreach ( $cmshop['top'] as $cmshop_row ) : ?>
						<li>
							<span class="cmshop-rank__name"><?php echo esc_html( (string) $cmshop_row['label'] ); ?></span>
							<span class="cmshop-rank__qty"><?php echo esc_html( (string) (int) $cmshop_row['quantity'] ); ?></span>
							<span class="cmshop-rank__bar" style="<?php echo esc_attr( 'width:' . (int) round( (int) $cmshop_row['quantity'] * 100 / $cmshop_best ) . '%' ); ?>"></span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</section>

		<section class="cmshop-panel">
			<header class="cmshop-panel__head">
				<h2>
					<?php
					/* translators: %d: days in stock. */
					echo esc_html( sprintf( __( 'In stock over %d days', 'codereyes-mobile-shop' ), $cmshop['slow_days'] ) );
					?>
				</h2>
				<?php if ( $cmshop['slow']['count'] > 0 ) : ?>
					<span class="cmshop-badge cmshop-badge--reserved"><?php echo esc_html( (string) $cmshop['slow']['count'] ); ?></span>
				<?php endif; ?>
			</header>
			<?php if ( array() === $cmshop['slow']['rows'] ) : ?>
				<p class="cmshop-empty"><?php esc_html_e( 'Every phone is moving. Nothing has sat on the shelf too long.', 'codereyes-mobile-shop' ); ?></p>
			<?php else : ?>
				<ul class="cmshop-list">
					<?php foreach ( $cmshop['slow']['rows'] as $cmshop_row ) : ?>
						<li>
							<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items', array( 'action' => 'edit', 'id' => (int) $cmshop_row['id'] ) ) ); ?>"><?php echo esc_html( (string) $cmshop_row['name'] ); ?></a>
							<span><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_row['sale_price'] ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="cmshop-hint"><?php esc_html_e( 'Tip: a small price cut or a website listing often moves these.', 'codereyes-mobile-shop' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="cmshop-panel">
			<header class="cmshop-panel__head"><h2><?php esc_html_e( 'To do today', 'codereyes-mobile-shop' ); ?></h2></header>
			<ul class="cmshop-tasks">
				<?php foreach ( $cmshop['tasks'] as $cmshop_task ) : ?>
					<li class="cmshop-tasks__item cmshop-tasks__item--<?php echo esc_attr( $cmshop_task['tone'] ); ?>">
						<span class="dashicons dashicons-<?php echo esc_attr( $cmshop_task['icon'] ); ?>" aria-hidden="true"></span>
						<a href="<?php echo esc_url( $cmshop_task['url'] ); ?>"><?php echo esc_html( $cmshop_task['text'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	</div>

	<div class="cmshop-dash-grid">
		<section class="cmshop-panel cmshop-panel--wide">
			<header class="cmshop-panel__head">
				<h2><?php esc_html_e( 'Recent sales', 'codereyes-mobile-shop' ); ?></h2>
				<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-sales' ) ); ?>"><?php esc_html_e( 'All sales', 'codereyes-mobile-shop' ); ?></a>
			</header>
			<?php if ( array() === $cmshop['recent'] ) : ?>
				<p class="cmshop-empty"><?php esc_html_e( 'No sales yet. Add your stock, then open New sale to ring up the first one.', 'codereyes-mobile-shop' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
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
			<header class="cmshop-panel__head">
				<h2><?php esc_html_e( 'Repair queue', 'codereyes-mobile-shop' ); ?></h2>
				<span class="cmshop-badge"><?php echo esc_html( (string) $cmshop_open ); ?></span>
			</header>
			<ul class="cmshop-queue">
				<?php foreach ( $cmshop['repairs'] as $cmshop_status => $cmshop_count ) : ?>
					<li class="<?php echo esc_attr( $cmshop_count > 0 ? 'has-jobs' : '' ); ?>">
						<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-repairs', array( 'status' => $cmshop_status ) ) ); ?>"><?php echo esc_html( Cmshop_Repair_Status::label( $cmshop_status ) ); ?></a>
						<span><?php echo esc_html( (string) $cmshop_count ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	</div>
</div>
