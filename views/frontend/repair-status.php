<?php
/**
 * Repair status lookup form and result.
 *
 * @package CodereyesMobileShop
 *
 * @var array $outcome { code, error, repair }.
 */

defined( 'ABSPATH' ) || exit;

$cmshop_repair = $outcome['repair'];
$cmshop_steps  = array( 'received', 'diagnosing', 'repairing', 'ready', 'collected' );
?>
<div class="cmshop-tracker">
	<form method="post" class="cmshop-tracker__form">
		<?php wp_nonce_field( Cmshop_Repair_Tracker::nonce_action(), '_cmshop_nonce' ); ?>
		<p>
			<label for="cmshop-ticket"><?php esc_html_e( 'Ticket number', 'codereyes-mobile-shop' ); ?></label>
			<input type="text" id="cmshop-ticket" name="cmshop_ticket" value="<?php echo esc_attr( $outcome['code'] ); ?>" maxlength="12" autocomplete="off" required placeholder="K7MQ2XRT">
		</p>
		<p>
			<label for="cmshop-last4"><?php esc_html_e( 'Last 4 digits of your phone number', 'codereyes-mobile-shop' ); ?></label>
			<input type="text" id="cmshop-last4" name="cmshop_phone_last4" inputmode="numeric" pattern="[0-9০-৯]{4}" maxlength="4" autocomplete="off" required>
		</p>
		<p><button type="submit"><?php esc_html_e( 'Check repair status', 'codereyes-mobile-shop' ); ?></button></p>
	</form>

	<?php if ( '' !== $outcome['error'] ) : ?>
		<p class="cmshop-tracker__error" role="alert"><?php echo esc_html( $outcome['error'] ); ?></p>
	<?php endif; ?>

	<?php if ( null !== $cmshop_repair ) : ?>
		<?php
		$cmshop_status = (string) $cmshop_repair['status'];
		$cmshop_index  = array_search( 'waiting_parts' === $cmshop_status ? 'diagnosing' : $cmshop_status, $cmshop_steps, true );
		?>
		<section class="cmshop-tracker__result" aria-live="polite">
			<h3>
				<?php echo esc_html( (string) $cmshop_repair['device'] ); ?>
				<span class="cmshop-tracker__badge cmshop-tracker__badge--<?php echo esc_attr( $cmshop_status ); ?>"><?php echo esc_html( Cmshop_Repair_Status::label( $cmshop_status ) ); ?></span>
			</h3>

			<?php if ( 'cancelled' !== $cmshop_status ) : ?>
				<ol class="cmshop-tracker__steps">
					<?php foreach ( $cmshop_steps as $cmshop_i => $cmshop_step ) : ?>
						<li class="<?php echo esc_attr( false !== $cmshop_index && $cmshop_i <= $cmshop_index ? 'is-done' : '' ); ?>"><?php echo esc_html( Cmshop_Repair_Status::label( $cmshop_step ) ); ?></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<?php if ( '' !== (string) $cmshop_repair['public_note'] ) : ?>
				<p class="cmshop-tracker__note"><?php echo esc_html( (string) $cmshop_repair['public_note'] ); ?></p>
			<?php endif; ?>

			<dl>
				<dt><?php esc_html_e( 'Ticket', 'codereyes-mobile-shop' ); ?></dt>
				<dd><?php echo esc_html( (string) $cmshop_repair['ticket_code'] ); ?></dd>
				<dt><?php esc_html_e( 'Booked in', 'codereyes-mobile-shop' ); ?></dt>
				<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), (string) $cmshop_repair['created_at'] ) ); ?></dd>
				<?php if ( ! empty( $cmshop_repair['due_date'] ) ) : ?>
					<dt><?php esc_html_e( 'Expected ready', 'codereyes-mobile-shop' ); ?></dt>
					<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), (string) $cmshop_repair['due_date'] ) ); ?></dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Balance to pay', 'codereyes-mobile-shop' ); ?></dt>
				<dd><?php echo esc_html( Cmshop_Settings::money( Cmshop_Repair_Status::balance_due( $cmshop_repair ) ) ); ?></dd>
			</dl>
		</section>
	<?php endif; ?>
</div>
