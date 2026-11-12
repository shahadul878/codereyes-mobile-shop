<?php
/**
 * Add or edit a customer, with their purchases and repairs.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { customer: array|null, values: array, errors: array, sales: array[], repairs: array[] }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_customer = $cmshop['customer'];
$cmshop_values   = $cmshop['values'];
$cmshop_errors   = $cmshop['errors'];
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( null === $cmshop_customer ? __( 'Add customer', 'codereyes-mobile-shop' ) : (string) $cmshop_customer['name'] ); ?></h1>
	<hr class="wp-header-end">

	<?php Cmshop_View::notice(); ?>
	<?php Cmshop_View::error_summary( $cmshop_errors ); ?>

	<div class="cmshop-form-grid">
		<section class="cmshop-card">
			<?php Cmshop_View::form_open( 'save_customer' ); ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) (int) ( $cmshop_customer['id'] ?? 0 ) ); ?>">
				<?php Cmshop_View::input( 'name', __( 'Name', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors, array( 'required' => true ) ); ?>
				<div class="cmshop-row">
					<?php
					Cmshop_View::input(
						'phone',
						__( 'Phone', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'required'  => true,
							'type'      => 'tel',
							'inputmode' => 'tel',
							'class'     => '',
						)
					);
					?>
					<?php
					Cmshop_View::input(
						'email',
						__( 'Email', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'type'      => 'email',
							'inputmode' => 'email',
							'class'     => '',
						)
					);
					?>
				</div>
				<?php Cmshop_View::textarea( 'address', __( 'Address', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors ); ?>
				<?php Cmshop_View::textarea( 'notes', __( 'Notes', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors ); ?>
				<p class="submit">
					<?php submit_button( __( 'Save customer', 'codereyes-mobile-shop' ), 'primary', 'submit', false ); ?>
					<a class="button" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-customers' ) ); ?>"><?php esc_html_e( 'Cancel', 'codereyes-mobile-shop' ); ?></a>
				</p>
			</form>
		</section>

		<?php if ( null !== $cmshop_customer ) : ?>
			<section class="cmshop-card">
				<h2><?php esc_html_e( 'Purchases', 'codereyes-mobile-shop' ); ?></h2>
				<?php if ( array() === $cmshop['sales'] ) : ?>
					<p class="cmshop-empty"><?php esc_html_e( 'No purchases yet.', 'codereyes-mobile-shop' ); ?></p>
				<?php else : ?>
					<ul class="cmshop-history">
						<?php foreach ( $cmshop['sales'] as $cmshop_sale ) : ?>
							<li>
								<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-sales', array( 'action' => 'view', 'id' => (int) $cmshop_sale['id'] ) ) ); ?>"><?php echo esc_html( (string) $cmshop_sale['invoice_no'] ); ?></a>
								<span><?php echo esc_html( Cmshop_View::date( (string) $cmshop_sale['created_at'], false ) ); ?></span>
								<strong><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_sale['total'] ) ); ?></strong>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Repairs', 'codereyes-mobile-shop' ); ?></h2>
				<?php if ( array() === $cmshop['repairs'] ) : ?>
					<p class="cmshop-empty"><?php esc_html_e( 'No repairs yet.', 'codereyes-mobile-shop' ); ?></p>
				<?php else : ?>
					<ul class="cmshop-history">
						<?php foreach ( $cmshop['repairs'] as $cmshop_job ) : ?>
							<li>
								<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-repairs', array( 'action' => 'edit', 'id' => (int) $cmshop_job['id'] ) ) ); ?>"><?php echo esc_html( (string) $cmshop_job['ticket_code'] ); ?></a>
								<span><?php echo esc_html( (string) $cmshop_job['device'] ); ?></span>
								<?php echo wp_kses_post( Cmshop_View::badge( Cmshop_Repair_Status::label( (string) $cmshop_job['status'] ), (string) $cmshop_job['status'] ) ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</div>
</div>
