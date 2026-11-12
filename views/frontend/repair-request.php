<?php
/**
 * [cmshop_repair_request] booking form, or the confirmation after booking.
 *
 * @package CodereyesMobileShop
 *
 * @var array $view { values, errors, error, booked, nonce, action, honeypot, today }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_field = static function ( string $name, string $label, array $view, array $opts = array() ): void {
	$error    = $view['errors'][ $name ] ?? '';
	$id       = 'cmshop-book-' . str_replace( '_', '-', $name );
	$required = ! empty( $opts['required'] );
	?>
	<p class="cmshop-booking__field<?php echo '' !== $error ? ' has-error' : ''; ?>">
		<label for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $label ); ?>
			<?php if ( ! $required ) : ?>
				<span class="cmshop-booking__optional"><?php esc_html_e( '(optional)', 'codereyes-mobile-shop' ); ?></span>
			<?php endif; ?>
		</label>
		<?php if ( 'textarea' === ( $opts['type'] ?? '' ) ) : ?>
			<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4" maxlength="2000"<?php echo $required ? ' required' : ''; ?><?php echo '' !== $error ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-error"' : ''; ?>><?php echo esc_textarea( (string) ( $view['values'][ $name ] ?? '' ) ); ?></textarea>
		<?php else : ?>
			<input type="<?php echo esc_attr( $opts['type'] ?? 'text' ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) ( $view['values'][ $name ] ?? '' ) ); ?>"<?php echo isset( $opts['autocomplete'] ) ? ' autocomplete="' . esc_attr( $opts['autocomplete'] ) . '"' : ''; ?><?php echo isset( $opts['inputmode'] ) ? ' inputmode="' . esc_attr( $opts['inputmode'] ) . '"' : ''; ?><?php echo isset( $opts['min'] ) ? ' min="' . esc_attr( $opts['min'] ) . '"' : ''; ?><?php echo isset( $opts['placeholder'] ) ? ' placeholder="' . esc_attr( $opts['placeholder'] ) . '"' : ''; ?><?php echo $required ? ' required' : ''; ?><?php echo '' !== $error ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-error"' : ''; ?>>
		<?php endif; ?>
		<?php if ( isset( $opts['help'] ) ) : ?>
			<small><?php echo esc_html( $opts['help'] ); ?></small>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<span class="cmshop-booking__error" id="<?php echo esc_attr( $id ); ?>-error"><?php echo esc_html( $error ); ?></span>
		<?php endif; ?>
	</p>
	<?php
};
?>
<div class="cmshop-tracker cmshop-booking" id="cmshop-booking">
	<?php if ( '' !== $view['booked'] ) : ?>
		<section class="cmshop-tracker__result cmshop-booking__done" role="status">
			<h3><?php esc_html_e( 'Your repair is booked', 'codereyes-mobile-shop' ); ?></h3>
			<p><?php esc_html_e( 'Your ticket number is', 'codereyes-mobile-shop' ); ?></p>
			<p class="cmshop-booking__code"><?php echo esc_html( $view['booked'] ); ?></p>
			<p><?php esc_html_e( 'Bring the phone to the shop and quote this number. Keep it to check progress on our website with the last 4 digits of your phone number.', 'codereyes-mobile-shop' ); ?></p>
		</section>
	<?php else : ?>
		<?php if ( '' !== $view['error'] ) : ?>
			<p class="cmshop-tracker__error" role="alert"><?php echo esc_html( $view['error'] ); ?></p>
		<?php elseif ( array() !== $view['errors'] ) : ?>
			<p class="cmshop-tracker__error" role="alert"><?php esc_html_e( 'Please check the fields marked below.', 'codereyes-mobile-shop' ); ?></p>
		<?php endif; ?>

		<form method="post" class="cmshop-booking__form" novalidate>
			<?php wp_nonce_field( $view['nonce'], '_cmshop_nonce' ); ?>
			<input type="hidden" name="cmshop_action" value="<?php echo esc_attr( $view['action'] ); ?>">

			<div class="cmshop-booking__row">
				<?php
				$cmshop_field(
					'customer_name',
					__( 'Your name', 'codereyes-mobile-shop' ),
					$view,
					array(
						'required'     => true,
						'autocomplete' => 'name',
					)
				);
				$cmshop_field(
					'customer_phone',
					__( 'Phone number', 'codereyes-mobile-shop' ),
					$view,
					array(
						'required'     => true,
						'type'         => 'tel',
						'autocomplete' => 'tel',
					)
				);
				?>
			</div>
			<?php
			$cmshop_field(
				'customer_email',
				__( 'Email', 'codereyes-mobile-shop' ),
				$view,
				array(
					'type'         => 'email',
					'autocomplete' => 'email',
					'help'         => __( 'We will email you the ticket number.', 'codereyes-mobile-shop' ),
				)
			);
			?>
			<div class="cmshop-booking__row">
				<?php
				$cmshop_field(
					'device',
					__( 'Phone brand and model', 'codereyes-mobile-shop' ),
					$view,
					array(
						'required'    => true,
						'placeholder' => 'Samsung Galaxy A15',
					)
				);
				$cmshop_field(
					'imei',
					__( 'IMEI', 'codereyes-mobile-shop' ),
					$view,
					array(
						'inputmode' => 'numeric',
						'help'      => __( 'Dial *#06# to see it.', 'codereyes-mobile-shop' ),
					)
				);
				?>
			</div>
			<?php
			$cmshop_field(
				'fault',
				__( 'What is wrong with the phone?', 'codereyes-mobile-shop' ),
				$view,
				array(
					'required' => true,
					'type'     => 'textarea',
				)
			);
			$cmshop_field(
				'preferred_date',
				__( 'When will you bring it in?', 'codereyes-mobile-shop' ),
				$view,
				array(
					'type' => 'date',
					'min'  => $view['today'],
				)
			);
			?>

			<p class="cmshop-booking__trap" aria-hidden="true">
				<label for="cmshop-book-website"><?php esc_html_e( 'Leave this empty', 'codereyes-mobile-shop' ); ?></label>
				<input type="text" id="cmshop-book-website" name="<?php echo esc_attr( $view['honeypot'] ); ?>" value="" tabindex="-1" autocomplete="off">
			</p>

			<p><button type="submit"><?php esc_html_e( 'Book repair', 'codereyes-mobile-shop' ); ?></button></p>
		</form>
	<?php endif; ?>
</div>
