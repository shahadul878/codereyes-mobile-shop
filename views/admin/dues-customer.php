<?php
/**
 * One customer's dues: unpaid sales, installment schedules and the collect form.
 *
 * @package MobileShopManager
 *
 * @var array $cmshop { customer: array, sales: array[], values: array, errors: array }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_customer = $cmshop['customer'];
$cmshop_format   = (string) get_option( 'date_format' );
$cmshop_owed     = 0;

foreach ( $cmshop['sales'] as $cmshop_sale ) {
	$cmshop_owed += Cmshop_Money::to_cents( (string) $cmshop_sale['total'] ) - Cmshop_Money::to_cents( (string) $cmshop_sale['amount_paid'] );
}

$cmshop_states = array(
	'paid'     => __( 'Paid', 'mobile-shop-manager' ),
	'partial'  => __( 'Part paid', 'mobile-shop-manager' ),
	'upcoming' => __( 'Due', 'mobile-shop-manager' ),
);
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline">
		<?php
		/* translators: %s: customer name. */
		echo esc_html( sprintf( __( 'Dues: %s', 'mobile-shop-manager' ), (string) $cmshop_customer['name'] ) );
		?>
	</h1>
	<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-dues' ) ); ?>"><?php esc_html_e( 'All dues', 'mobile-shop-manager' ); ?></a>
	<?php if ( $cmshop_owed > 0 ) : ?>
		<?php
		Cmshop_View::whatsapp(
			Cmshop_Messages::due(
				array_merge(
					array(
						'name'  => (string) $cmshop_customer['name'],
						'phone' => (string) $cmshop_customer['phone'],
						'owed'  => Cmshop_Money::from_cents( $cmshop_owed ),
					),
					Cmshop_Plugin::dues()->standing( (int) $cmshop_customer['id'] )
				)
			),
			__( 'Send reminder', 'mobile-shop-manager' ),
			'page-title-action'
		);
		?>
	<?php endif; ?>
	<hr class="wp-header-end">
	<?php Cmshop_View::notice(); ?>
	<?php Cmshop_View::error_summary( $cmshop['errors'] ); ?>

	<div class="cmshop-form-grid">
		<section class="cmshop-card">
			<h2><?php esc_html_e( 'Receive a payment', 'mobile-shop-manager' ); ?></h2>
			<p class="cmshop-dues-owed">
				<?php
				/* translators: 1: phone, 2: amount owed. */
				echo esc_html( sprintf( __( '%1$s owes %2$s', 'mobile-shop-manager' ), (string) $cmshop_customer['phone'], Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_owed ) ) ) );
				?>
			</p>
			<?php if ( $cmshop_owed > 0 ) : ?>
				<?php Cmshop_View::form_open( 'collect_due' ); ?>
					<input type="hidden" name="customer_id" value="<?php echo esc_attr( (string) (int) $cmshop_customer['id'] ); ?>">
					<div class="cmshop-row">
						<?php
						Cmshop_View::input(
							'amount',
							__( 'Amount received', 'mobile-shop-manager' ),
							$cmshop['values'],
							$cmshop['errors'],
							array(
								'inputmode'   => 'decimal',
								'placeholder' => Cmshop_Money::from_cents( $cmshop_owed ),
								'required'    => true,
								'class'       => '',
							)
						);
						$cmshop_methods = Cmshop_Settings::payment_methods();
						unset( $cmshop_methods['online'] );
						Cmshop_View::select( 'method', __( 'Paid by', 'mobile-shop-manager' ), $cmshop_methods, $cmshop['values'], $cmshop['errors'] );
						?>
					</div>
					<?php Cmshop_View::input( 'reference', __( 'Transaction ID (bKash, card, bank)', 'mobile-shop-manager' ), $cmshop['values'], $cmshop['errors'], array( 'class' => '' ) ); ?>
					<p class="description"><?php esc_html_e( 'The payment goes to the oldest unpaid sale first.', 'mobile-shop-manager' ); ?></p>
					<?php submit_button( __( 'Record payment', 'mobile-shop-manager' ), 'primary large', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</section>

		<section class="cmshop-card">
			<h2><?php esc_html_e( 'Unpaid sales', 'mobile-shop-manager' ); ?></h2>
			<?php if ( array() === $cmshop['sales'] ) : ?>
				<p class="cmshop-empty"><?php esc_html_e( 'All paid up.', 'mobile-shop-manager' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $cmshop['sales'] as $cmshop_sale ) : ?>
				<?php $cmshop_due = Cmshop_Money::to_cents( (string) $cmshop_sale['total'] ) - Cmshop_Money::to_cents( (string) $cmshop_sale['amount_paid'] ); ?>
				<div class="cmshop-due-sale">
					<p class="cmshop-due-sale__head">
						<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-sales', array( 'action' => 'view', 'id' => (int) $cmshop_sale['id'] ) ) ); ?>"><strong><?php echo esc_html( (string) $cmshop_sale['invoice_no'] ); ?></strong></a>
						<span><?php echo esc_html( Cmshop_View::date( (string) $cmshop_sale['created_at'], false ) ); ?></span>
						<span>
							<?php
							/* translators: 1: total, 2: still owed. */
							echo esc_html( sprintf( __( 'Total %1$s · owes %2$s', 'mobile-shop-manager' ), Cmshop_Settings::money( (string) $cmshop_sale['total'] ), Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_due ) ) ) );
							?>
						</span>
					</p>
					<?php if ( null !== $cmshop_sale['schedule'] ) : ?>
						<ol class="cmshop-plan">
							<?php foreach ( $cmshop_sale['schedule']['rows'] as $cmshop_row ) : ?>
								<li class="cmshop-plan__row is-<?php echo esc_attr( $cmshop_row['state'] . ( $cmshop_row['overdue'] ? ' is-overdue' : '' ) ); ?>">
									<span><?php echo esc_html( (string) mysql2date( $cmshop_format, $cmshop_row['due_date'] ) ); ?></span>
									<span><?php echo esc_html( Cmshop_Settings::money( Cmshop_Money::from_cents( (int) $cmshop_row['amount'] ) ) ); ?></span>
									<span class="cmshop-plan__state"><?php echo esc_html( $cmshop_row['overdue'] ? __( 'Overdue', 'mobile-shop-manager' ) : $cmshop_states[ $cmshop_row['state'] ] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</section>
	</div>
</div>
