<?php
/**
 * Add or edit a supplier.
 *
 * @package MobileShopManager
 *
 * @var array $cmshop { supplier: array|null, values: array, errors: array }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_supplier = $cmshop['supplier'];
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( null === $cmshop_supplier ? __( 'Add supplier', 'mobile-shop-manager' ) : __( 'Edit supplier', 'mobile-shop-manager' ) ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG ) ); ?>"><?php esc_html_e( 'All suppliers', 'mobile-shop-manager' ); ?></a>
	<hr class="wp-header-end">
	<?php Cmshop_View::error_summary( $cmshop['errors'] ); ?>

	<?php Cmshop_View::form_open( 'save_supplier' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( (string) (int) ( $cmshop_supplier['id'] ?? 0 ) ); ?>">
		<section class="cmshop-card cmshop-narrow">
			<div class="cmshop-row">
				<?php
				Cmshop_View::input( 'name', __( 'Name', 'mobile-shop-manager' ), $cmshop['values'], $cmshop['errors'], array( 'required' => true ) );
				Cmshop_View::input(
					'phone',
					__( 'Phone', 'mobile-shop-manager' ),
					$cmshop['values'],
					$cmshop['errors'],
					array(
						'type'      => 'tel',
						'inputmode' => 'tel',
					)
				);
				?>
			</div>
			<?php Cmshop_View::textarea( 'address', __( 'Address', 'mobile-shop-manager' ), $cmshop['values'], $cmshop['errors'] ); ?>
			<?php Cmshop_View::textarea( 'notes', __( 'Notes (terms, contact person…)', 'mobile-shop-manager' ), $cmshop['values'], $cmshop['errors'] ); ?>
			<?php submit_button( __( 'Save supplier', 'mobile-shop-manager' ), 'primary large', 'submit', false ); ?>
		</section>
	</form>
</div>
