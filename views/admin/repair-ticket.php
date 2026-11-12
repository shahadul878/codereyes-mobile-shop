<?php
/**
 * Printable repair job ticket: customer copy and shop copy.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { repair: array }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_repair   = $cmshop['repair'];
$cmshop_settings = Cmshop_Settings::all();
$cmshop_copies   = array(
	__( 'Customer copy', 'codereyes-mobile-shop' ),
	__( 'Shop copy', 'codereyes-mobile-shop' ),
);
?>
<div class="wrap cmshop-wrap">
	<div class="cmshop-no-print">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Job ticket', 'codereyes-mobile-shop' ); ?></h1>
		<button type="button" class="page-title-action cmshop-print"><?php esc_html_e( 'Print', 'codereyes-mobile-shop' ); ?></button>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-repairs', array( 'action' => 'edit', 'id' => (int) $cmshop_repair['id'] ) ) ); ?>"><?php esc_html_e( 'Back to job', 'codereyes-mobile-shop' ); ?></a>
		<hr class="wp-header-end">
	</div>

	<?php foreach ( $cmshop_copies as $cmshop_index => $cmshop_copy ) : ?>
		<article class="cmshop-receipt cmshop-ticket">
			<header class="cmshop-receipt__head">
				<h2><?php echo esc_html( (string) $cmshop_settings['shop_name'] ); ?></h2>
				<?php if ( '' !== $cmshop_settings['shop_phone'] ) : ?>
					<p><?php echo esc_html( (string) $cmshop_settings['shop_phone'] ); ?></p>
				<?php endif; ?>
				<p class="cmshop-ticket__copy"><?php echo esc_html( $cmshop_copy ); ?></p>
			</header>

			<p class="cmshop-ticket__code"><?php echo esc_html( (string) $cmshop_repair['ticket_code'] ); ?></p>

			<dl class="cmshop-receipt__meta">
				<dt><?php esc_html_e( 'Booked in', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( Cmshop_View::date( (string) $cmshop_repair['created_at'] ) ); ?></dd>
				<dt><?php esc_html_e( 'Customer', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( $cmshop_repair['customer_name'] . ' · ' . $cmshop_repair['customer_phone'] ); ?></dd>
				<dt><?php esc_html_e( 'Device', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( (string) $cmshop_repair['device'] ); ?></dd>
				<?php if ( '' !== (string) $cmshop_repair['imei'] ) : ?>
					<dt>IMEI</dt><dd><?php echo esc_html( (string) $cmshop_repair['imei'] ); ?></dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Fault', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( (string) $cmshop_repair['fault'] ); ?></dd>
				<?php if ( '' !== (string) $cmshop_repair['items_received'] ) : ?>
					<dt><?php esc_html_e( 'Left with device', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( (string) $cmshop_repair['items_received'] ); ?></dd>
				<?php endif; ?>
				<?php if ( ! empty( $cmshop_repair['due_date'] ) ) : ?>
					<dt><?php esc_html_e( 'Ready by', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( Cmshop_View::date( (string) $cmshop_repair['due_date'], false ) ); ?></dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Estimate', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_repair['estimate'] ) ); ?></dd>
				<dt><?php esc_html_e( 'Deposit', 'codereyes-mobile-shop' ); ?></dt><dd><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_repair['deposit'] ) ); ?></dd>
			</dl>

			<?php if ( 0 === $cmshop_index ) : ?>
				<p class="cmshop-ticket__track">
					<?php
					/* translators: %s: ticket code. */
					echo esc_html( sprintf( __( 'Check progress on our website with ticket %s and the last 4 digits of your phone number.', 'codereyes-mobile-shop' ), (string) $cmshop_repair['ticket_code'] ) );
					?>
				</p>
				<?php if ( '' !== (string) $cmshop_settings['repair_terms'] ) : ?>
					<footer class="cmshop-receipt__foot"><?php echo nl2br( esc_html( (string) $cmshop_settings['repair_terms'] ) ); ?></footer>
				<?php endif; ?>
			<?php else : ?>
				<p class="cmshop-ticket__sign"><?php esc_html_e( 'Customer signature', 'codereyes-mobile-shop' ); ?></p>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
