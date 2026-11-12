<?php
/**
 * Sale: printable thermal receipt or A4 invoice, with void action.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { sale: array, lines: array[], customer: ?array, format: string }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_sale     = $cmshop['sale'];
$cmshop_format   = $cmshop['format'];
$cmshop_settings = Cmshop_Settings::all();
$cmshop_is_void  = 'void' === $cmshop_sale['status'];
$cmshop_balance  = Cmshop_Money::to_cents( (string) $cmshop_sale['total'] ) - Cmshop_Money::to_cents( (string) $cmshop_sale['amount_paid'] );
$cmshop_payment  = Cmshop_Item::label( Cmshop_Settings::payment_labels(), (string) $cmshop_sale['payment_method'] );
$cmshop_order    = null;

if ( (int) $cmshop_sale['wc_order_id'] > 0 && function_exists( 'wc_get_order' ) ) {
	$cmshop_found = wc_get_order( (int) $cmshop_sale['wc_order_id'] );
	$cmshop_order = $cmshop_found ? $cmshop_found : null;
}

// Totals rows shared by both formats: label, amount, row class.
$cmshop_totals = array(
	array( __( 'Subtotal', 'codereyes-mobile-shop' ), Cmshop_Settings::money( (string) $cmshop_sale['subtotal'] ), '' ),
);

if ( Cmshop_Money::to_cents( (string) $cmshop_sale['discount'] ) > 0 ) {
	$cmshop_totals[] = array( __( 'Discount', 'codereyes-mobile-shop' ), '-' . Cmshop_Settings::money( (string) $cmshop_sale['discount'] ), '' );
}

$cmshop_totals[] = array( __( 'Total', 'codereyes-mobile-shop' ), Cmshop_Settings::money( (string) $cmshop_sale['total'] ), 'is-total' );
$cmshop_paid_in  = array_values(
	array_filter(
		Cmshop_Plugin::payments()->for_sale( (int) $cmshop_sale['id'] ),
		static function ( array $payment ): bool {
			return Cmshop_Payment_Repository::KIND_SALE === $payment['kind'];
		}
	)
);

if ( count( $cmshop_paid_in ) > 1 ) {
	// Split payment: one line per method. Cash shows what was handed over (change is added back).
	$cmshop_change = max( 0, -$cmshop_balance );

	foreach ( $cmshop_paid_in as $cmshop_payment_row ) {
		$cmshop_cents    = Cmshop_Money::to_cents( (string) $cmshop_payment_row['amount'] ) + ( 'cash' === $cmshop_payment_row['method'] ? $cmshop_change : 0 );
		$cmshop_label    = Cmshop_Item::label( Cmshop_Settings::payment_labels(), (string) $cmshop_payment_row['method'] );
		$cmshop_label   .= '' !== (string) $cmshop_payment_row['reference'] ? ' (' . $cmshop_payment_row['reference'] . ')' : '';
		$cmshop_totals[] = array(
			/* translators: %s: payment method, e.g. "bKash (TRX123)". */
			sprintf( __( 'Paid · %s', 'codereyes-mobile-shop' ), $cmshop_label ),
			Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_cents ) ),
			'',
		);
	}
} else {
	$cmshop_totals[] = array( __( 'Paid', 'codereyes-mobile-shop' ), Cmshop_Settings::money( (string) $cmshop_sale['amount_paid'] ), '' );
}

if ( $cmshop_balance > 0 ) {
	$cmshop_totals[] = array( __( 'Balance due', 'codereyes-mobile-shop' ), Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_balance ) ), 'is-due' );
} elseif ( $cmshop_balance < 0 ) {
	$cmshop_totals[] = array( __( 'Change', 'codereyes-mobile-shop' ), Cmshop_Settings::money( Cmshop_Money::from_cents( -$cmshop_balance ) ), '' );
}

$cmshop_is_a4 = Cmshop_Invoice_Format::A4 === $cmshop_format;
?>
<div class="wrap cmshop-wrap">
	<div class="cmshop-no-print">
		<h1 class="wp-heading-inline">
			<?php
			/* translators: %s: invoice number. */
			echo esc_html( sprintf( __( 'Sale %s', 'codereyes-mobile-shop' ), (string) $cmshop_sale['invoice_no'] ) );
			?>
		</h1>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-pos' ) ); ?>"><?php esc_html_e( 'New sale', 'codereyes-mobile-shop' ); ?></a>
		<?php if ( ! $cmshop_is_void ) : ?>
			<a class="page-title-action cmshop-danger cmshop-confirm" data-confirm="<?php esc_attr_e( 'Void this sale and put its items back in stock?', 'codereyes-mobile-shop' ); ?>" href="<?php echo esc_url( Cmshop_View::action_url( 'void_sale', array( 'id' => (int) $cmshop_sale['id'] ) ) ); ?>"><?php esc_html_e( 'Void / return', 'codereyes-mobile-shop' ); ?></a>
		<?php endif; ?>
		<hr class="wp-header-end">
		<?php Cmshop_View::notice(); ?>
		<?php if ( null !== $cmshop_order ) : ?>
			<p>
				<?php esc_html_e( 'From website order', 'codereyes-mobile-shop' ); ?>
				<a href="<?php echo esc_url( $cmshop_order->get_edit_order_url() ); ?>">#<?php echo esc_html( (string) $cmshop_order->get_order_number() ); ?></a>
			</p>
		<?php endif; ?>

		<div class="cmshop-print-bar">
			<nav class="cmshop-format-switch" aria-label="<?php esc_attr_e( 'Print format', 'codereyes-mobile-shop' ); ?>">
				<?php foreach ( Cmshop_Invoice_Format::all() as $cmshop_key => $cmshop_label ) : ?>
					<a class="<?php echo esc_attr( $cmshop_key === $cmshop_format ? 'is-active' : '' ); ?>" <?php echo $cmshop_key === $cmshop_format ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( Cmshop_View::url( 'cmshop-sales', array( 'action' => 'view', 'id' => (int) $cmshop_sale['id'], 'format' => $cmshop_key ) ) ); ?>"><?php echo esc_html( $cmshop_label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<button type="button" class="button button-primary cmshop-print">
				<?php $cmshop_is_a4 ? esc_html_e( 'Print invoice', 'codereyes-mobile-shop' ) : esc_html_e( 'Print receipt', 'codereyes-mobile-shop' ); ?>
			</button>
		</div>
	</div>

	<?php require __DIR__ . '/invoice/' . ( $cmshop_is_a4 ? 'a4' : 'thermal' ) . '.php'; ?>

	<?php $cmshop_schedule = Cmshop_Plugin::dues()->schedule( $cmshop_sale ); ?>
	<?php if ( $cmshop_balance > 0 && ! $cmshop_is_void ) : ?>
		<section class="cmshop-card cmshop-no-print cmshop-due-panel">
			<h2>
				<?php
				/* translators: %s: amount still owed. */
				echo esc_html( sprintf( __( 'Still owed: %s', 'codereyes-mobile-shop' ), Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_balance ) ) ) );
				?>
			</h2>
			<?php if ( null !== $cmshop_schedule ) : ?>
				<ol class="cmshop-plan">
					<?php foreach ( $cmshop_schedule['rows'] as $cmshop_row ) : ?>
						<li class="cmshop-plan__row is-<?php echo esc_attr( $cmshop_row['state'] . ( $cmshop_row['overdue'] ? ' is-overdue' : '' ) ); ?>">
							<span><?php echo esc_html( (string) mysql2date( (string) get_option( 'date_format' ), $cmshop_row['due_date'] ) ); ?></span>
							<span><?php echo esc_html( Cmshop_Settings::money( Cmshop_Money::from_cents( (int) $cmshop_row['amount'] ) ) ); ?></span>
							<span class="cmshop-plan__state"><?php echo esc_html( $cmshop_row['overdue'] ? __( 'Overdue', 'codereyes-mobile-shop' ) : ( 'paid' === $cmshop_row['state'] ? __( 'Paid', 'codereyes-mobile-shop' ) : __( 'Due', 'codereyes-mobile-shop' ) ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
			<?php if ( (int) $cmshop_sale['customer_id'] > 0 ) : ?>
				<p><a class="button button-primary" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-dues', array( 'action' => 'collect', 'customer' => (int) $cmshop_sale['customer_id'] ) ) ); ?>"><?php esc_html_e( 'Collect payment', 'codereyes-mobile-shop' ); ?></a></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<p class="cmshop-no-print cmshop-profit">
		<?php
		/* translators: 1: cost, 2: profit. */
		echo esc_html( sprintf( __( 'Cost %1$s · Profit %2$s (not printed)', 'codereyes-mobile-shop' ), Cmshop_Settings::money( (string) $cmshop_sale['cost_total'] ), Cmshop_Settings::money( (string) $cmshop_sale['profit'] ) ) );
		?>
	</p>
</div>
