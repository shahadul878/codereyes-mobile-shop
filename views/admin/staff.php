<?php
/**
 * Sales and commission per staff member.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { period: string, periods: array, report: array, cashiers: WP_User[] }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_report = $cmshop['report'];
$cmshop_rate   = (string) Cmshop_Settings::get( 'commission_rate' );
$cmshop_on     = Cmshop_Commission::rate( $cmshop_rate ) > 0;
$cmshop_money  = static function ( int $cents ): string {
	return Cmshop_Settings::money( Cmshop_Money::from_cents( $cents ) );
};
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Staff and commission', 'codereyes-mobile-shop' ); ?></h1>
	<?php if ( current_user_can( 'create_users' ) ) : ?>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Add a cashier', 'codereyes-mobile-shop' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<nav class="cmshop-format-switch" aria-label="<?php esc_attr_e( 'Period', 'codereyes-mobile-shop' ); ?>">
		<?php foreach ( $cmshop['periods'] as $cmshop_key => $cmshop_period ) : ?>
			<a class="<?php echo esc_attr( $cmshop_key === $cmshop['period'] ? 'is-active' : '' ); ?>" <?php echo $cmshop_key === $cmshop['period'] ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( Cmshop_View::url( 'cmshop-staff', array( 'period' => $cmshop_key ) ) ); ?>"><?php echo esc_html( $cmshop_period[0] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<section class="cmshop-card">
		<?php if ( array() === $cmshop_report['rows'] ) : ?>
			<p class="cmshop-empty"><?php esc_html_e( 'No sales in this period.', 'codereyes-mobile-shop' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Staff', 'codereyes-mobile-shop' ); ?></th>
						<th class="num"><?php esc_html_e( 'Sales', 'codereyes-mobile-shop' ); ?></th>
						<th class="num"><?php esc_html_e( 'Sold (after returns)', 'codereyes-mobile-shop' ); ?></th>
						<th class="num"><?php esc_html_e( 'Profit', 'codereyes-mobile-shop' ); ?></th>
						<th class="num"><?php esc_html_e( 'Commission', 'codereyes-mobile-shop' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cmshop_report['rows'] as $cmshop_row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( Cmshop_Staff::name( $cmshop_row['user_id'] ) ); ?></strong></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $cmshop_row['sales'] ) ); ?></td>
							<td class="num"><?php echo esc_html( $cmshop_money( $cmshop_row['total'] ) ); ?></td>
							<td class="num"><?php echo esc_html( $cmshop_money( $cmshop_row['profit'] ) ); ?></td>
							<td class="num"><?php echo esc_html( 0 === $cmshop_row['user_id'] ? '—' : $cmshop_money( $cmshop_row['commission'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th><?php esc_html_e( 'Total', 'codereyes-mobile-shop' ); ?></th>
						<th class="num"><?php echo esc_html( number_format_i18n( $cmshop_report['totals']['sales'] ) ); ?></th>
						<th class="num"><?php echo esc_html( $cmshop_money( $cmshop_report['totals']['total'] ) ); ?></th>
						<th class="num"><?php echo esc_html( $cmshop_money( $cmshop_report['totals']['profit'] ) ); ?></th>
						<th class="num"><?php echo esc_html( $cmshop_money( $cmshop_report['totals']['commission'] ) ); ?></th>
					</tr>
				</tfoot>
			</table>
		<?php endif; ?>
		<p class="description">
			<?php
			if ( $cmshop_on ) {
				echo esc_html(
					Cmshop_Commission::ON_PROFIT === Cmshop_Settings::get( 'commission_base' )
						/* translators: %s: commission percentage. */
						? sprintf( __( 'Commission is %s%% of the profit on each person\'s sales. Change it in Settings.', 'codereyes-mobile-shop' ), $cmshop_rate )
						/* translators: %s: commission percentage. */
						: sprintf( __( 'Commission is %s%% of each person\'s sales. Change it in Settings.', 'codereyes-mobile-shop' ), $cmshop_rate )
				);
			} else {
				esc_html_e( 'Commission is off. Set a percentage under Settings → Staff commission.', 'codereyes-mobile-shop' );
			}
			?>
		</p>
	</section>

	<section class="cmshop-card">
		<h2><?php esc_html_e( 'Cashiers', 'codereyes-mobile-shop' ); ?></h2>
		<p><?php esc_html_e( 'Give counter staff the "Shop Cashier" role (Users → Add New). Cashiers can sell, take repairs, collect dues and run their own cash register. They never see cost prices, profit, suppliers or other people\'s takings, and cannot void sales, refund, or change stock.', 'codereyes-mobile-shop' ); ?></p>
		<?php if ( array() === $cmshop['cashiers'] ) : ?>
			<p class="cmshop-empty"><?php esc_html_e( 'No cashiers yet.', 'codereyes-mobile-shop' ); ?></p>
		<?php else : ?>
			<ul class="cmshop-list">
				<?php foreach ( $cmshop['cashiers'] as $cmshop_user ) : ?>
					<li>
						<?php if ( current_user_can( 'edit_user', (int) $cmshop_user->ID ) ) : ?>
							<a href="<?php echo esc_url( get_edit_user_link( (int) $cmshop_user->ID ) ); ?>"><?php echo esc_html( $cmshop_user->display_name ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $cmshop_user->display_name ); ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
</div>
