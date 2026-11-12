<?php
/**
 * Repair status lookup form and result.
 *
 * @package CodereyesMobileShop
 *
 * @var array  $outcome { code, error, repair }.
 * @var string $style   Inline CSS custom properties from block/widget settings.
 */

defined( 'ABSPATH' ) || exit;

$cmshop_repair = $outcome['repair'];
$cmshop_steps  = array( 'received', 'diagnosing', 'repairing', 'ready', 'collected' );

// A job booked online shows that step first until the phone is brought in.
if ( null !== $cmshop_repair && Cmshop_Repair_Status::REQUESTED === $cmshop_repair['status'] ) {
	$cmshop_steps = array_merge( array( Cmshop_Repair_Status::REQUESTED ), $cmshop_steps );
}

$cmshop_format = (string) get_option( 'date_format' );
?>
<div class="cmshop-ui cmshop-lookup cmshop-tracker" style="<?php echo esc_attr( $style ); ?>">
	<form method="post" class="cmshop-panel cmshop-lookup__form">
		<?php wp_nonce_field( Cmshop_Repair_Tracker::nonce_action(), '_cmshop_nonce' ); ?>
		<p>
			<label for="cmshop-ticket"><?php esc_html_e( 'Ticket number', 'codereyes-mobile-shop' ); ?></label>
			<input type="text" id="cmshop-ticket" name="cmshop_ticket" value="<?php echo esc_attr( $outcome['code'] ); ?>" maxlength="12" autocomplete="off" required placeholder="K7MQ2XRT">
		</p>
		<p>
			<label for="cmshop-last4"><?php esc_html_e( 'Last 4 digits of your phone', 'codereyes-mobile-shop' ); ?></label>
			<input type="text" id="cmshop-last4" name="cmshop_phone_last4" inputmode="numeric" pattern="[0-9০-৯]{4}" maxlength="4" autocomplete="off" required placeholder="5678">
		</p>
		<p><button type="submit"><?php esc_html_e( 'Check status', 'codereyes-mobile-shop' ); ?></button></p>
	</form>

	<?php if ( '' !== $outcome['error'] ) : ?>
		<p class="cmshop-alert" role="alert"><?php echo esc_html( $outcome['error'] ); ?></p>
	<?php endif; ?>

	<?php if ( null !== $cmshop_repair ) : ?>
		<?php
		$cmshop_status  = (string) $cmshop_repair['status'];
		$cmshop_index   = array_search( 'waiting_parts' === $cmshop_status ? 'diagnosing' : $cmshop_status, $cmshop_steps, true );
		$cmshop_variant = 'cancelled' === $cmshop_status ? ' cmshop-pill--danger' : ( 'ready' === $cmshop_status ? ' cmshop-pill--solid' : '' );
		?>
		<section class="cmshop-panel cmshop-lookup__result" aria-live="polite">
			<h3 class="cmshop-lookup__head">
				<?php echo esc_html( (string) $cmshop_repair['device'] ); ?>
				<span class="cmshop-pill<?php echo esc_attr( $cmshop_variant ); ?>"><?php echo esc_html( Cmshop_Repair_Status::label( $cmshop_status ) ); ?></span>
			</h3>

			<?php if ( 'cancelled' !== $cmshop_status ) : ?>
				<ol class="cmshop-steps">
					<?php foreach ( $cmshop_steps as $cmshop_i => $cmshop_step ) : ?>
						<?php
						$cmshop_state = array();

						if ( false !== $cmshop_index && $cmshop_i <= $cmshop_index ) {
							$cmshop_state[] = 'is-done';
						}

						if ( $cmshop_i === $cmshop_index ) {
							$cmshop_state[] = 'is-current';
						}
						?>
						<li class="<?php echo esc_attr( implode( ' ', $cmshop_state ) ); ?>" <?php echo $cmshop_i === $cmshop_index ? 'aria-current="step"' : ''; ?>><?php echo esc_html( Cmshop_Repair_Status::label( $cmshop_step ) ); ?></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<?php if ( '' !== (string) $cmshop_repair['public_note'] ) : ?>
				<p class="cmshop-note"><?php echo esc_html( (string) $cmshop_repair['public_note'] ); ?></p>
			<?php endif; ?>

			<dl class="cmshop-facts">
				<div>
					<dt><?php esc_html_e( 'Ticket', 'codereyes-mobile-shop' ); ?></dt>
					<dd><?php echo esc_html( (string) $cmshop_repair['ticket_code'] ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Booked in', 'codereyes-mobile-shop' ); ?></dt>
					<dd><?php echo esc_html( mysql2date( $cmshop_format, (string) $cmshop_repair['created_at'] ) ); ?></dd>
				</div>
				<?php if ( ! empty( $cmshop_repair['due_date'] ) ) : ?>
					<div>
						<dt><?php esc_html_e( 'Expected ready', 'codereyes-mobile-shop' ); ?></dt>
						<dd><?php echo esc_html( mysql2date( $cmshop_format, (string) $cmshop_repair['due_date'] ) ); ?></dd>
					</div>
				<?php endif; ?>
				<div>
					<dt><?php esc_html_e( 'Balance to pay', 'codereyes-mobile-shop' ); ?></dt>
					<dd><?php echo esc_html( Cmshop_Settings::money( Cmshop_Repair_Status::balance_due( $cmshop_repair ) ) ); ?></dd>
				</div>
			</dl>
		</section>
	<?php endif; ?>
</div>
