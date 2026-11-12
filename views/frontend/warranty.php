<?php
/**
 * [cmshop_warranty] IMEI form and result.
 *
 * @package CodereyesMobileShop
 *
 * @var array  $outcome { imei, error, sale, warranty }
 * @var string $style   Inline CSS custom properties from block/widget settings.
 */

defined( 'ABSPATH' ) || exit;

$cmshop_warranty = $outcome['warranty'];
$cmshop_format   = (string) get_option( 'date_format' );
?>
<div class="cmshop-ui cmshop-lookup cmshop-warranty" style="<?php echo esc_attr( $style ); ?>">
	<form method="post" class="cmshop-panel cmshop-lookup__form cmshop-lookup__form--single">
		<?php wp_nonce_field( Cmshop_Warranty_Check::nonce_action(), '_cmshop_nonce' ); ?>
		<p>
			<label for="cmshop-warranty-imei"><?php esc_html_e( 'IMEI number', 'codereyes-mobile-shop' ); ?></label>
			<input type="text" id="cmshop-warranty-imei" name="cmshop_warranty_imei" value="<?php echo esc_attr( $outcome['imei'] ); ?>" inputmode="numeric" maxlength="20" autocomplete="off" required placeholder="490154203237518" aria-describedby="cmshop-warranty-help">
			<small id="cmshop-warranty-help"><?php esc_html_e( 'Dial *#06# on the phone, or look on the box.', 'codereyes-mobile-shop' ); ?></small>
		</p>
		<p><button type="submit"><?php esc_html_e( 'Check warranty', 'codereyes-mobile-shop' ); ?></button></p>
	</form>

	<?php if ( '' !== $outcome['error'] ) : ?>
		<p class="cmshop-alert" role="alert"><?php echo esc_html( $outcome['error'] ); ?></p>
	<?php endif; ?>

	<?php if ( null !== $cmshop_warranty ) : ?>
		<?php
		$cmshop_active = Cmshop_Warranty::ACTIVE === $cmshop_warranty['state'];
		$cmshop_labels = array(
			Cmshop_Warranty::ACTIVE  => __( 'Under warranty', 'codereyes-mobile-shop' ),
			Cmshop_Warranty::EXPIRED => __( 'Warranty ended', 'codereyes-mobile-shop' ),
			Cmshop_Warranty::NONE    => __( 'No warranty', 'codereyes-mobile-shop' ),
		);
		?>
		<section class="cmshop-panel cmshop-lookup__result" aria-live="polite">
			<h3 class="cmshop-lookup__head">
				<span class="cmshop-status-icon<?php echo $cmshop_active ? '' : ' cmshop-status-icon--bad'; ?>" aria-hidden="true"><?php echo $cmshop_active ? '&#10003;' : '!'; ?></span>
				<span>
					<?php echo esc_html( (string) $outcome['sale']['description'] ); ?><br>
					<span class="cmshop-pill<?php echo $cmshop_active ? ' cmshop-pill--solid' : ' cmshop-pill--danger'; ?>"><?php echo esc_html( $cmshop_labels[ $cmshop_warranty['state'] ] ); ?></span>
				</span>
			</h3>
			<dl class="cmshop-facts">
				<div>
					<dt><?php esc_html_e( 'Bought from us', 'codereyes-mobile-shop' ); ?></dt>
					<dd><?php echo esc_html( mysql2date( $cmshop_format, (string) $outcome['sale']['sold_at'] ) ); ?></dd>
				</div>
				<?php if ( Cmshop_Warranty::NONE !== $cmshop_warranty['state'] ) : ?>
					<div>
						<dt><?php echo esc_html( $cmshop_active ? __( 'Covered until', 'codereyes-mobile-shop' ) : __( 'Ended on', 'codereyes-mobile-shop' ) ); ?></dt>
						<dd><?php echo esc_html( mysql2date( $cmshop_format, $cmshop_warranty['ends'] ) ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( $cmshop_active ) : ?>
					<div>
						<dt><?php esc_html_e( 'Days left', 'codereyes-mobile-shop' ); ?></dt>
						<dd><?php echo esc_html( (string) $cmshop_warranty['days_left'] ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
			<?php if ( $cmshop_active ) : ?>
				<p class="cmshop-note"><?php esc_html_e( 'Bring the phone and your receipt to the shop for a warranty repair.', 'codereyes-mobile-shop' ); ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
