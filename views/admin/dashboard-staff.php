<?php
/**
 * Cashier dashboard: their own sales and commission, their shift, the counter queue.
 * Never shows cost, profit or shop-wide takings.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop From Cmshop_Dashboard::staff_data().
 */

defined( 'ABSPATH' ) || exit;

$cmshop_money = static function ( int $cents ): string {
	return Cmshop_Settings::money( Cmshop_Money::from_cents( $cents ) );
};
$cmshop_ready = (int) ( $cmshop['repairs'][ Cmshop_Repair_Status::READY ] ?? 0 );
$cmshop_shift = $cmshop['shift'];
?>
<div class="wrap cmshop-wrap cmshop-dashboard">
	<hr class="wp-header-end">
	<div class="cmshop-hero">
		<div>
			<h1>
				<?php
				/* translators: %s: staff member's name. */
				echo esc_html( sprintf( __( 'Hello, %s', 'codereyes-mobile-shop' ), wp_get_current_user()->display_name ) );
				?>
			</h1>
			<p class="cmshop-hero__date"><?php echo esc_html( (string) Cmshop_Settings::get( 'shop_name' ) . ' · ' . wp_date( 'l, j F Y' ) ); ?></p>
		</div>
		<div class="cmshop-hero__actions">
			<a class="button button-primary button-hero" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-pos' ) ); ?>"><?php esc_html_e( 'New sale', 'codereyes-mobile-shop' ); ?></a>
			<a class="button button-hero" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-repairs', array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Book in repair', 'codereyes-mobile-shop' ); ?></a>
		</div>
	</div>

	<div class="cmshop-kpis">
		<section class="cmshop-kpi">
			<h2><span class="dashicons dashicons-cart" aria-hidden="true"></span><?php esc_html_e( 'My sales today', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-kpi__value"><?php echo esc_html( $cmshop_money( $cmshop['today']['total'] ) ); ?></p>
			<p class="cmshop-kpi__meta">
				<?php
				/* translators: %d: number of sales. */
				echo esc_html( sprintf( _n( '%d sale', '%d sales', $cmshop['today']['sales'], 'codereyes-mobile-shop' ), $cmshop['today']['sales'] ) );
				?>
			</p>
		</section>
		<section class="cmshop-kpi">
			<h2><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><?php esc_html_e( 'My sales this month', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-kpi__value"><?php echo esc_html( $cmshop_money( $cmshop['month']['total'] ) ); ?></p>
			<p class="cmshop-kpi__meta">
				<?php
				/* translators: %d: number of sales. */
				echo esc_html( sprintf( _n( '%d sale', '%d sales', $cmshop['month']['sales'], 'codereyes-mobile-shop' ), $cmshop['month']['sales'] ) );
				?>
			</p>
		</section>
		<?php if ( $cmshop['commission'] ) : ?>
			<section class="cmshop-kpi">
				<h2><span class="dashicons dashicons-awards" aria-hidden="true"></span><?php esc_html_e( 'My commission this month', 'codereyes-mobile-shop' ); ?></h2>
				<p class="cmshop-kpi__value"><?php echo esc_html( $cmshop_money( $cmshop['month']['commission'] ) ); ?></p>
				<p class="cmshop-kpi__meta">
					<?php
					echo esc_html(
						Cmshop_Commission::ON_PROFIT === $cmshop['base']
							/* translators: %s: commission percentage. */
							? sprintf( __( '%s%% of the profit on your sales', 'codereyes-mobile-shop' ), $cmshop['rate'] )
							/* translators: %s: commission percentage. */
							: sprintf( __( '%s%% of your sales', 'codereyes-mobile-shop' ), $cmshop['rate'] )
					);
					?>
				</p>
			</section>
		<?php endif; ?>
		<section class="cmshop-kpi<?php echo null === $cmshop_shift ? ' cmshop-kpi--alert' : ''; ?>">
			<h2><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e( 'My cash register', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-kpi__value">
				<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-shifts' ) ); ?>">
					<?php echo esc_html( null === $cmshop_shift ? __( 'Open shift', 'codereyes-mobile-shop' ) : $cmshop_money( $cmshop_shift['summary']['expected'] ) ); ?>
				</a>
			</p>
			<p class="cmshop-kpi__meta"><?php echo esc_html( null === $cmshop_shift ? __( 'Count the float before your first sale.', 'codereyes-mobile-shop' ) : __( 'Cash that should be in your drawer', 'codereyes-mobile-shop' ) ); ?></p>
		</section>
	</div>

	<section class="cmshop-panel">
		<header class="cmshop-panel__head"><h2><?php esc_html_e( 'At the counter', 'codereyes-mobile-shop' ); ?></h2></header>
		<ul class="cmshop-tasks">
			<?php if ( $cmshop_ready > 0 ) : ?>
				<li class="cmshop-tasks__item cmshop-tasks__item--info">
					<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
					<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-repairs', array( 'status' => Cmshop_Repair_Status::READY ) ) ); ?>">
						<?php
						/* translators: %d: repairs ready to collect. */
						echo esc_html( sprintf( _n( '%d repair ready to collect: tell the customer', '%d repairs ready to collect: tell the customers', $cmshop_ready, 'codereyes-mobile-shop' ), $cmshop_ready ) );
						?>
					</a>
				</li>
			<?php endif; ?>
			<?php if ( $cmshop['dues']['sales'] > 0 ) : ?>
				<li class="cmshop-tasks__item cmshop-tasks__item--warn">
					<span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
					<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-dues' ) ); ?>">
						<?php
						/* translators: %d: customers who owe money. */
						echo esc_html( sprintf( _n( '%d customer owes money: remind or collect', '%d customers owe money: remind or collect', $cmshop['dues']['customers'], 'codereyes-mobile-shop' ), $cmshop['dues']['customers'] ) );
						?>
					</a>
				</li>
			<?php endif; ?>
			<li class="cmshop-tasks__item cmshop-tasks__item--muted">
				<span class="dashicons dashicons-lock" aria-hidden="true"></span>
				<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-shifts' ) ); ?>"><?php esc_html_e( 'Count the cash drawer before you leave', 'codereyes-mobile-shop' ); ?></a>
			</li>
		</ul>
	</section>
</div>
