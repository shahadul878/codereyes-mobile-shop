<?php
/**
 * Book in or update a repair job.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { repair: array|null, values: array, errors: array }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_repair = $cmshop['repair'];
$cmshop_values = $cmshop['values'];
$cmshop_errors = $cmshop['errors'];
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline">
		<?php
		if ( null === $cmshop_repair ) {
			esc_html_e( 'Book in a repair', 'codereyes-mobile-shop' );
		} else {
			/* translators: %s: ticket code. */
			echo esc_html( sprintf( __( 'Repair %s', 'codereyes-mobile-shop' ), (string) $cmshop_repair['ticket_code'] ) );
		}
		?>
	</h1>
	<?php if ( null !== $cmshop_repair ) : ?>
		<?php echo wp_kses_post( Cmshop_View::badge( Cmshop_Repair_Status::label( (string) $cmshop_repair['status'] ), (string) $cmshop_repair['status'] ) ); ?>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-repairs', array( 'action' => 'ticket', 'id' => (int) $cmshop_repair['id'] ) ) ); ?>"><?php esc_html_e( 'Print job ticket', 'codereyes-mobile-shop' ); ?></a>
		<?php if ( ! in_array( $cmshop_repair['status'], array( Cmshop_Repair_Status::COLLECTED, Cmshop_Repair_Status::CANCELLED ), true ) ) : ?>
			<?php Cmshop_View::whatsapp( Cmshop_Messages::repair( $cmshop_repair ), Cmshop_Repair_Status::READY === $cmshop_repair['status'] ? __( 'Tell customer it is ready', 'codereyes-mobile-shop' ) : __( 'Send update on WhatsApp', 'codereyes-mobile-shop' ), 'page-title-action' ); ?>
		<?php endif; ?>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php Cmshop_View::notice(); ?>
	<?php Cmshop_View::error_summary( $cmshop_errors ); ?>

	<?php Cmshop_View::form_open( 'save_repair' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( (string) (int) ( $cmshop_repair['id'] ?? 0 ) ); ?>">

		<div class="cmshop-form-grid">
			<section class="cmshop-card">
				<h2><?php esc_html_e( 'Customer and device', 'codereyes-mobile-shop' ); ?></h2>
				<div class="cmshop-row">
					<?php
					Cmshop_View::input(
						'customer_name',
						__( 'Customer name', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'required' => true,
							'class'    => '',
						)
					);
					?>
					<?php
					Cmshop_View::input(
						'customer_phone',
						__( 'Phone number', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'required'  => true,
							'type'      => 'tel',
							'inputmode' => 'tel',
							'class'     => '',
							'help'      => __( 'Existing customers are matched by this number.', 'codereyes-mobile-shop' ),
						)
					);
					?>
				</div>
				<div class="cmshop-row">
					<?php
					Cmshop_View::input(
						'device',
						__( 'Device', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'required'    => true,
							'placeholder' => 'iPhone 11, black',
							'class'       => '',
						)
					);
					?>
					<?php
					Cmshop_View::input(
						'imei',
						__( 'IMEI', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'inputmode' => 'numeric',
							'class'     => 'cmshop-mono',
						)
					);
					?>
				</div>
				<?php Cmshop_View::textarea( 'fault', __( 'Fault reported', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors, array( 'required' => true ) ); ?>
				<?php Cmshop_View::input( 'items_received', __( 'Left with the device', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors, array( 'placeholder' => __( 'SIM tray, case, charger', 'codereyes-mobile-shop' ) ) ); ?>
			</section>

			<section class="cmshop-card">
				<h2><?php esc_html_e( 'Job', 'codereyes-mobile-shop' ); ?></h2>
				<div class="cmshop-row">
					<?php Cmshop_View::select( 'status', __( 'Status', 'codereyes-mobile-shop' ), Cmshop_Repair_Status::all(), $cmshop_values, $cmshop_errors ); ?>
					<?php
					Cmshop_View::input(
						'due_date',
						__( 'Ready by', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'type'  => 'date',
							'class' => '',
						)
					);
					?>
				</div>
				<div class="cmshop-row cmshop-row--3">
					<?php
					Cmshop_View::input(
						'estimate',
						__( 'Estimate', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'inputmode' => 'decimal',
							'class'     => '',
						)
					);
					?>
					<?php
					Cmshop_View::input(
						'deposit',
						__( 'Deposit paid', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'inputmode' => 'decimal',
							'class'     => '',
						)
					);
					?>
					<?php
					Cmshop_View::input(
						'final_cost',
						__( 'Final cost', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'inputmode' => 'decimal',
							'class'     => '',
							'help'      => __( 'Leave empty to use the estimate.', 'codereyes-mobile-shop' ),
						)
					);
					?>
				</div>
				<?php if ( null !== $cmshop_repair ) : ?>
					<p class="cmshop-balance">
						<?php
						/* translators: %s: amount. */
						echo esc_html( sprintf( __( 'Balance to collect: %s', 'codereyes-mobile-shop' ), Cmshop_Settings::money( Cmshop_Repair_Status::balance_due( $cmshop_repair ) ) ) );
						?>
					</p>
				<?php endif; ?>
				<?php Cmshop_View::input( 'public_note', __( 'Message for the customer', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors, array( 'help' => __( 'Shown on the website repair tracker, e.g. "Screen ordered, arrives Tuesday".', 'codereyes-mobile-shop' ) ) ); ?>
				<?php Cmshop_View::textarea( 'internal_notes', __( 'Technician notes (private)', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors ); ?>
			</section>
		</div>

		<p class="submit">
			<?php submit_button( null === $cmshop_repair ? __( 'Book in and create ticket', 'codereyes-mobile-shop' ) : __( 'Save changes', 'codereyes-mobile-shop' ), 'primary large', 'submit', false ); ?>
			<a class="button button-large" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-repairs' ) ); ?>"><?php esc_html_e( 'Cancel', 'codereyes-mobile-shop' ); ?></a>
		</p>
	</form>
</div>
