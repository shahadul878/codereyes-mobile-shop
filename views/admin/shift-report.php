<?php
/**
 * End-of-day (shift) report, printable on a receipt printer.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { shift: array (with summary, petty) }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_shift  = $cmshop['shift'];
$cmshop_sum    = $cmshop_shift['summary'];
$cmshop_labels = Cmshop_Settings::payment_labels();
$cmshop_user   = get_userdata( (int) $cmshop_shift['user_id'] );
$cmshop_closed = null !== $cmshop_shift['closed_at'];
$cmshop_money  = static function ( int $cents ): string {
	return Cmshop_Settings::money( Cmshop_Money::from_cents( $cents ) );
};
$cmshop_minus  = static function ( int $cents ) use ( $cmshop_money ): string {
	return ( $cents > 0 ? '-' : '' ) . $cmshop_money( $cents );
};
$cmshop_diff   = $cmshop_closed ? Cmshop_Shift_Math::difference( Cmshop_Money::to_cents( (string) $cmshop_shift['counted_cash'] ), Cmshop_Money::to_cents( (string) $cmshop_shift['expected_cash'] ) ) : 0;
?>
<div class="wrap cmshop-wrap">
	<div class="cmshop-no-print">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Shift report', 'codereyes-mobile-shop' ); ?></h1>
		<button type="button" class="page-title-action cmshop-print"><?php esc_html_e( 'Print', 'codereyes-mobile-shop' ); ?></button>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-shifts' ) ); ?>"><?php esc_html_e( 'Cash register', 'codereyes-mobile-shop' ); ?></a>
		<hr class="wp-header-end">
		<?php Cmshop_View::notice(); ?>
	</div>

	<article class="cmshop-receipt">
		<header class="cmshop-receipt__head">
			<h2><?php echo esc_html( (string) Cmshop_Settings::get( 'shop_name' ) ); ?></h2>
			<p><?php echo esc_html( $cmshop_closed ? __( 'END OF SHIFT REPORT', 'codereyes-mobile-shop' ) : __( 'SHIFT SO FAR (STILL OPEN)', 'codereyes-mobile-shop' ) ); ?></p>
		</header>

		<dl class="cmshop-receipt__meta">
			<dt><?php esc_html_e( 'Cashier', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( $cmshop_user ? $cmshop_user->display_name : '—' ); ?></dd>
			<dt><?php esc_html_e( 'Opened', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( Cmshop_View::date( (string) $cmshop_shift['opened_at'] ) ); ?></dd>
			<?php if ( $cmshop_closed ) : ?>
				<dt><?php esc_html_e( 'Closed', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( Cmshop_View::date( (string) $cmshop_shift['closed_at'] ) ); ?></dd>
			<?php endif; ?>
		</dl>

		<table class="cmshop-receipt__lines">
			<thead><tr><th><?php esc_html_e( 'Taken by', 'codereyes-mobile-shop' ); ?></th><th class="num"><?php esc_html_e( 'Amount', 'codereyes-mobile-shop' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $cmshop_sum['by_method'] as $cmshop_method => $cmshop_cents ) : ?>
					<tr><td><?php echo esc_html( Cmshop_Item::label( $cmshop_labels, (string) $cmshop_method ) ); ?></td><td class="num"><?php echo esc_html( $cmshop_money( $cmshop_cents ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr><th><?php esc_html_e( 'Sales payments', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( $cmshop_money( $cmshop_sum['sales'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Dues collected', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( $cmshop_money( $cmshop_sum['dues'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Refunds', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( $cmshop_minus( $cmshop_sum['refunds'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Petty cash out', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( $cmshop_minus( $cmshop_sum['cash_out'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Petty cash in', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( $cmshop_money( $cmshop_sum['cash_in'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Opening float', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_shift['opening_float'] ) ); ?></td></tr>
				<tr class="is-total"><th><?php esc_html_e( 'Expected cash', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( $cmshop_money( $cmshop_closed ? Cmshop_Money::to_cents( (string) $cmshop_shift['expected_cash'] ) : $cmshop_sum['expected'] ) ); ?></td></tr>
				<?php if ( $cmshop_closed ) : ?>
					<tr><th><?php esc_html_e( 'Counted cash', 'codereyes-mobile-shop' ); ?></th><td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_shift['counted_cash'] ) ); ?></td></tr>
					<tr class="<?php echo esc_attr( 0 === $cmshop_diff ? '' : 'is-due' ); ?>"><th><?php echo esc_html( $cmshop_diff < 0 ? __( 'Short', 'codereyes-mobile-shop' ) : ( $cmshop_diff > 0 ? __( 'Over', 'codereyes-mobile-shop' ) : __( 'Balanced', 'codereyes-mobile-shop' ) ) ); ?></th><td class="num"><?php echo esc_html( $cmshop_money( abs( $cmshop_diff ) ) ); ?></td></tr>
				<?php endif; ?>
			</tfoot>
		</table>

		<?php if ( '' !== (string) $cmshop_shift['note'] ) : ?>
			<footer class="cmshop-receipt__foot"><?php echo esc_html( (string) $cmshop_shift['note'] ); ?></footer>
		<?php endif; ?>
	</article>
</div>
