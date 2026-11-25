<?php
/**
 * End-of-day (shift) report, printable on a receipt printer.
 *
 * @package MobileShopManager
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
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Shift report', 'mobile-shop-manager' ); ?></h1>
		<button type="button" class="page-title-action cmshop-print"><?php esc_html_e( 'Print', 'mobile-shop-manager' ); ?></button>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-shifts' ) ); ?>"><?php esc_html_e( 'Cash register', 'mobile-shop-manager' ); ?></a>
		<hr class="wp-header-end">
		<?php Cmshop_View::notice(); ?>
	</div>

	<article class="cmshop-receipt">
		<header class="cmshop-receipt__head">
			<h2><?php echo esc_html( (string) Cmshop_Settings::get( 'shop_name' ) ); ?></h2>
			<p><?php echo esc_html( $cmshop_closed ? __( 'END OF SHIFT REPORT', 'mobile-shop-manager' ) : __( 'SHIFT SO FAR (STILL OPEN)', 'mobile-shop-manager' ) ); ?></p>
		</header>

		<dl class="cmshop-receipt__meta">
			<dt><?php esc_html_e( 'Cashier', 'mobile-shop-manager' ); ?></dt><dd><?php echo esc_html( $cmshop_user ? $cmshop_user->display_name : '—' ); ?></dd>
			<dt><?php esc_html_e( 'Opened', 'mobile-shop-manager' ); ?></dt><dd><?php echo esc_html( Cmshop_View::date( (string) $cmshop_shift['opened_at'] ) ); ?></dd>
			<?php if ( $cmshop_closed ) : ?>
				<dt><?php esc_html_e( 'Closed', 'mobile-shop-manager' ); ?></dt><dd><?php echo esc_html( Cmshop_View::date( (string) $cmshop_shift['closed_at'] ) ); ?></dd>
			<?php endif; ?>
		</dl>

		<table class="cmshop-receipt__lines">
			<thead><tr><th><?php esc_html_e( 'Taken by', 'mobile-shop-manager' ); ?></th><th class="num"><?php esc_html_e( 'Amount', 'mobile-shop-manager' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $cmshop_sum['by_method'] as $cmshop_method => $cmshop_cents ) : ?>
					<tr><td><?php echo esc_html( Cmshop_Item::label( $cmshop_labels, (string) $cmshop_method ) ); ?></td><td class="num"><?php echo esc_html( $cmshop_money( $cmshop_cents ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr><th><?php esc_html_e( 'Sales payments', 'mobile-shop-manager' ); ?></th><td class="num"><?php echo esc_html( $cmshop_money( $cmshop_sum['sales'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Dues collected', 'mobile-shop-manager' ); ?></th><td class="num"><?php echo esc_html( $cmshop_money( $cmshop_sum['dues'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Refunds', 'mobile-shop-manager' ); ?></th><td class="num"><?php echo esc_html( $cmshop_minus( $cmshop_sum['refunds'] ) ); ?></td></tr>
				<?php if ( $cmshop_sum['suppliers'] > 0 ) : ?>
					<tr><th><?php esc_html_e( 'Paid to suppliers', 'mobile-shop-manager' ); ?></th><td class="num"><?php echo esc_html( $cmshop_minus( $cmshop_sum['suppliers'] ) ); ?></td></tr>
				<?php endif; ?>
				<tr><th><?php esc_html_e( 'Petty cash out', 'mobile-shop-manager' ); ?></th><td class="num"><?php echo esc_html( $cmshop_minus( $cmshop_sum['cash_out'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Petty cash in', 'mobile-shop-manager' ); ?></th><td class="num"><?php echo esc_html( $cmshop_money( $cmshop_sum['cash_in'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Opening float', 'mobile-shop-manager' ); ?></th><td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_shift['opening_float'] ) ); ?></td></tr>
				<tr class="is-total"><th><?php esc_html_e( 'Expected cash', 'mobile-shop-manager' ); ?></th><td class="num"><?php echo esc_html( $cmshop_money( $cmshop_closed ? Cmshop_Money::to_cents( (string) $cmshop_shift['expected_cash'] ) : $cmshop_sum['expected'] ) ); ?></td></tr>
				<?php if ( $cmshop_closed ) : ?>
					<tr><th><?php esc_html_e( 'Counted cash', 'mobile-shop-manager' ); ?></th><td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_shift['counted_cash'] ) ); ?></td></tr>
					<tr class="<?php echo esc_attr( 0 === $cmshop_diff ? '' : 'is-due' ); ?>"><th><?php echo esc_html( $cmshop_diff < 0 ? __( 'Short', 'mobile-shop-manager' ) : ( $cmshop_diff > 0 ? __( 'Over', 'mobile-shop-manager' ) : __( 'Balanced', 'mobile-shop-manager' ) ) ); ?></th><td class="num"><?php echo esc_html( $cmshop_money( abs( $cmshop_diff ) ) ); ?></td></tr>
				<?php endif; ?>
			</tfoot>
		</table>

		<?php if ( '' !== (string) $cmshop_shift['note'] ) : ?>
			<footer class="cmshop-receipt__foot"><?php echo esc_html( (string) $cmshop_shift['note'] ); ?></footer>
		<?php endif; ?>
	</article>
</div>
