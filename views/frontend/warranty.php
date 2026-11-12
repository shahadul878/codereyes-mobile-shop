<?php
/**
 * [cmshop_warranty] IMEI form and result.
 *
 * @package CodereyesMobileShop
 *
 * @var array $outcome { imei, error, sale, warranty }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_warranty = $outcome['warranty'];
$cmshop_format   = (string) get_option( 'date_format' );
?>
<div class="cmshop-tracker cmshop-warranty">
	<form method="post" class="cmshop-tracker__form cmshop-tracker__form--single">
		<?php wp_nonce_field( Cmshop_Warranty_Check::nonce_action(), '_cmshop_nonce' ); ?>
		<p>
			<label for="cmshop-warranty-imei"><?php esc_html_e( 'IMEI number', 'codereyes-mobile-shop' ); ?></label>
			<input type="text" id="cmshop-warranty-imei" name="cmshop_warranty_imei" value="<?php echo esc_attr( $outcome['imei'] ); ?>" inputmode="numeric" maxlength="20" autocomplete="off" required placeholder="490154203237518" aria-describedby="cmshop-warranty-help">
			<small id="cmshop-warranty-help"><?php esc_html_e( 'Dial *#06# on the phone, or look on the box.', 'codereyes-mobile-shop' ); ?></small>
		</p>
		<p><button type="submit"><?php esc_html_e( 'Check warranty', 'codereyes-mobile-shop' ); ?></button></p>
	</form>

	<?php if ( '' !== $outcome['error'] ) : ?>
		<p class="cmshop-tracker__error" role="alert"><?php echo esc_html( $outcome['error'] ); ?></p>
	<?php endif; ?>

	<?php if ( null !== $cmshop_warranty ) : ?>
		<section class="cmshop-tracker__result cmshop-warranty--<?php echo esc_attr( $cmshop_warranty['state'] ); ?>" aria-live="polite">
			<h3>
				<?php echo esc_html( (string) $outcome['sale']['description'] ); ?>
				<span class="cmshop-tracker__badge cmshop-warranty__badge">
					<?php
					$cmshop_labels = array(
						Cmshop_Warranty::ACTIVE  => __( 'Under warranty', 'codereyes-mobile-shop' ),
						Cmshop_Warranty::EXPIRED => __( 'Warranty ended', 'codereyes-mobile-shop' ),
						Cmshop_Warranty::NONE    => __( 'No warranty', 'codereyes-mobile-shop' ),
					);
					echo esc_html( $cmshop_labels[ $cmshop_warranty['state'] ] );
					?>
				</span>
			</h3>
			<dl>
				<dt><?php esc_html_e( 'Bought from us', 'codereyes-mobile-shop' ); ?></dt>
				<dd><?php echo esc_html( mysql2date( $cmshop_format, (string) $outcome['sale']['sold_at'] ) ); ?></dd>
				<?php if ( Cmshop_Warranty::NONE !== $cmshop_warranty['state'] ) : ?>
					<dt><?php echo esc_html( Cmshop_Warranty::ACTIVE === $cmshop_warranty['state'] ? __( 'Covered until', 'codereyes-mobile-shop' ) : __( 'Ended on', 'codereyes-mobile-shop' ) ); ?></dt>
					<dd><?php echo esc_html( mysql2date( $cmshop_format, $cmshop_warranty['ends'] ) ); ?></dd>
				<?php endif; ?>
				<?php if ( Cmshop_Warranty::ACTIVE === $cmshop_warranty['state'] ) : ?>
					<dt><?php esc_html_e( 'Days left', 'codereyes-mobile-shop' ); ?></dt>
					<dd><?php echo esc_html( (string) $cmshop_warranty['days_left'] ); ?></dd>
				<?php endif; ?>
			</dl>
			<?php if ( Cmshop_Warranty::ACTIVE === $cmshop_warranty['state'] ) : ?>
				<p class="cmshop-tracker__note"><?php esc_html_e( 'Bring the phone and your receipt to the shop for a warranty repair.', 'codereyes-mobile-shop' ); ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
