<?php
/**
 * Add or edit a phone or accessory.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { item: array|null, values: array, errors: array, sales: array[], listing: array|null, brands: string[] }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_item     = $cmshop['item'];
$cmshop_values   = $cmshop['values'];
$cmshop_errors   = $cmshop['errors'];
$cmshop_is_phone = Cmshop_Item::TYPE_ACCESSORY !== ( $cmshop_values['item_type'] ?? 'phone' );
$cmshop_is_sold  = null !== $cmshop_item && Cmshop_Item::STATUS_SOLD === $cmshop_item['status'];
$cmshop_woo      = class_exists( 'WooCommerce' ) && Cmshop_Settings::woo_enabled();
$cmshop_listing  = $cmshop['listing'] ?? null;

if ( null === $cmshop_item ) {
	$cmshop_title = $cmshop_is_phone ? __( 'Add phone', 'codereyes-mobile-shop' ) : __( 'Add accessory', 'codereyes-mobile-shop' );
} else {
	$cmshop_title = Cmshop_Item::display_name( $cmshop_item );
}
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $cmshop_title ); ?></h1>
	<?php if ( null !== $cmshop_item ) : ?>
		<?php echo wp_kses_post( Cmshop_View::badge( Cmshop_Item::label( Cmshop_Item::statuses(), (string) $cmshop_item['status'] ), (string) $cmshop_item['status'] ) ); ?>
		<?php if ( ! $cmshop_is_sold ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-pos', array( 'add' => (int) $cmshop_item['id'] ) ) ); ?>"><?php esc_html_e( 'Sell this item', 'codereyes-mobile-shop' ); ?></a>
			<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items', array( 'action' => 'stickers', 'item' => array( (int) $cmshop_item['id'] ), 'copies' => 'stock' ) ) ); ?>"><?php esc_html_e( 'Print sticker', 'codereyes-mobile-shop' ); ?></a>
		<?php endif; ?>
		<?php if ( $cmshop_is_phone ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items', array( 'action' => 'new', 'copy' => (int) $cmshop_item['id'] ) ) ); ?>"><?php esc_html_e( 'Add another like this', 'codereyes-mobile-shop' ); ?></a>
		<?php endif; ?>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php Cmshop_View::notice(); ?>
	<?php Cmshop_View::error_summary( $cmshop_errors ); ?>
	<?php if ( null === $cmshop_item && isset( $cmshop_values['model'] ) && '' === (string) ( $cmshop_values['imei1'] ?? '' ) && array() === $cmshop_errors ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'Details copied from the last phone. Scan the new IMEI and check the colour, then add it to stock.', 'codereyes-mobile-shop' ); ?></p></div>
	<?php endif; ?>

	<?php Cmshop_View::form_open( 'save_item' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( (string) (int) ( $cmshop_item['id'] ?? 0 ) ); ?>">
		<input type="hidden" name="item_type" value="<?php echo esc_attr( $cmshop_is_phone ? 'phone' : 'accessory' ); ?>">

		<div class="cmshop-form-grid">
			<section class="cmshop-card">
				<h2><?php echo esc_html( $cmshop_is_phone ? __( 'Phone', 'codereyes-mobile-shop' ) : __( 'Accessory', 'codereyes-mobile-shop' ) ); ?></h2>

				<?php if ( $cmshop_is_phone ) : ?>
					<div class="cmshop-row">
						<?php
						Cmshop_View::input(
							'brand',
							__( 'Brand', 'codereyes-mobile-shop' ),
							$cmshop_values,
							$cmshop_errors,
							array(
								'required'    => true,
								'placeholder' => 'Samsung',
								'class'       => '',
								'list'        => 'cmshop-brands',
							)
						);
						?>
						<?php
						Cmshop_View::input(
							'model',
							__( 'Model', 'codereyes-mobile-shop' ),
							$cmshop_values,
							$cmshop_errors,
							array(
								'required'    => true,
								'placeholder' => 'Galaxy A15',
								'class'       => '',
							)
						);
						?>
					</div>
					<div class="cmshop-row">
						<?php
						Cmshop_View::input(
							'storage',
							__( 'Storage', 'codereyes-mobile-shop' ),
							$cmshop_values,
							$cmshop_errors,
							array(
								'placeholder' => '128GB',
								'class'       => '',
							)
						);
						?>
						<?php
						Cmshop_View::input(
							'color',
							__( 'Colour', 'codereyes-mobile-shop' ),
							$cmshop_values,
							$cmshop_errors,
							array(
								'placeholder' => 'Black',
								'class'       => '',
							)
						);
						?>
					</div>
					<div class="cmshop-row">
						<?php
						Cmshop_View::input(
							'imei1',
							__( 'IMEI 1', 'codereyes-mobile-shop' ),
							$cmshop_values,
							$cmshop_errors,
							array(
								'required'  => true,
								'inputmode' => 'numeric',
								'class'     => 'cmshop-mono',
								'help'      => __( 'Dial *#06# on the phone. A barcode scanner works here too.', 'codereyes-mobile-shop' ),
							)
						);
						?>
						<?php
						Cmshop_View::input(
							'imei2',
							__( 'IMEI 2 (dual SIM)', 'codereyes-mobile-shop' ),
							$cmshop_values,
							$cmshop_errors,
							array(
								'inputmode' => 'numeric',
								'class'     => 'cmshop-mono',
							)
						);
						?>
					</div>
					<div class="cmshop-row">
						<?php Cmshop_View::select( 'grade', __( 'Condition', 'codereyes-mobile-shop' ), Cmshop_Item::grades(), $cmshop_values, $cmshop_errors ); ?>
						<?php
						Cmshop_View::input(
							'battery_health',
							__( 'Battery health %', 'codereyes-mobile-shop' ),
							$cmshop_values,
							$cmshop_errors,
							array(
								'type'      => 'number',
								'inputmode' => 'numeric',
								'class'     => 'small-text',
							)
						);
						?>
					</div>
					<?php Cmshop_View::input( 'serial', __( 'Serial number', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors, array( 'class' => 'cmshop-mono' ) ); ?>
				<?php else : ?>
					<?php
					Cmshop_View::input(
						'name',
						__( 'Name', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'required'    => true,
							'placeholder' => __( '20W USB-C charger', 'codereyes-mobile-shop' ),
						)
					);
					?>
					<div class="cmshop-row">
						<?php
						Cmshop_View::input(
							'brand',
							__( 'Brand', 'codereyes-mobile-shop' ),
							$cmshop_values,
							$cmshop_errors,
							array(
								'class' => '',
								'list'  => 'cmshop-brands',
							)
						);
						?>
						<?php Cmshop_View::input( 'color', __( 'Colour', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors, array( 'class' => '' ) ); ?>
					</div>
					<div class="cmshop-row">
						<?php Cmshop_View::input( 'sku', __( 'SKU / barcode', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors, array( 'class' => 'cmshop-mono' ) ); ?>
						<?php
						Cmshop_View::input(
							'quantity',
							__( 'Quantity in stock', 'codereyes-mobile-shop' ),
							$cmshop_values,
							$cmshop_errors,
							array(
								'type'      => 'number',
								'inputmode' => 'numeric',
								'class'     => 'small-text',
								'required'  => true,
							)
						);
						?>
					</div>
				<?php endif; ?>

				<?php require __DIR__ . '/item-photos.php'; ?>
				<?php Cmshop_View::datalist( 'cmshop-brands', $cmshop['brands'] ?? array() ); ?>
			</section>

			<section class="cmshop-card">
				<h2><?php esc_html_e( 'Price', 'codereyes-mobile-shop' ); ?></h2>
				<div class="cmshop-row">
					<?php
					Cmshop_View::input(
						'cost_price',
						__( 'Cost price', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'inputmode' => 'decimal',
							'class'     => '',
							'help'      => __( 'What you paid. Never shown to customers.', 'codereyes-mobile-shop' ),
						)
					);
					?>
					<?php
					Cmshop_View::input(
						'sale_price',
						__( 'Selling price', 'codereyes-mobile-shop' ),
						$cmshop_values,
						$cmshop_errors,
						array(
							'inputmode' => 'decimal',
							'class'     => '',
						)
					);
					?>
				</div>
				<?php Cmshop_View::input( 'supplier', __( 'Bought from', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors ); ?>
				<?php if ( ! $cmshop_is_sold ) : ?>
					<?php
					Cmshop_View::select(
						'status',
						__( 'Availability', 'codereyes-mobile-shop' ),
						array(
							Cmshop_Item::STATUS_IN_STOCK => __( 'In stock', 'codereyes-mobile-shop' ),
							Cmshop_Item::STATUS_RESERVED => __( 'Reserved for a customer', 'codereyes-mobile-shop' ),
						),
						$cmshop_values,
						$cmshop_errors
					);
					?>
				<?php endif; ?>
				<?php Cmshop_View::textarea( 'notes', __( 'Private notes', 'codereyes-mobile-shop' ), $cmshop_values, $cmshop_errors ); ?>

				<?php if ( null !== $cmshop_item && $cmshop_woo ) : ?>
					<div class="cmshop-online">
						<h3><?php esc_html_e( 'Online shop', 'codereyes-mobile-shop' ); ?></h3>
						<?php require __DIR__ . '/item-website.php'; ?>
					</div>
				<?php endif; ?>
			</section>
		</div>

		<p class="submit">
			<?php submit_button( null === $cmshop_item ? __( 'Add to stock', 'codereyes-mobile-shop' ) : __( 'Save changes', 'codereyes-mobile-shop' ), 'primary large', 'submit', false ); ?>
			<a class="button button-large" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items' ) ); ?>"><?php esc_html_e( 'Cancel', 'codereyes-mobile-shop' ); ?></a>
		</p>
	</form>

	<?php if ( array() !== $cmshop['sales'] ) : ?>
		<section class="cmshop-panel">
			<h2><?php esc_html_e( 'Sales of this item', 'codereyes-mobile-shop' ); ?></h2>
			<ul>
				<?php foreach ( $cmshop['sales'] as $cmshop_sale ) : ?>
					<li>
						<a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-sales', array( 'action' => 'view', 'id' => (int) $cmshop_sale['id'] ) ) ); ?>"><?php echo esc_html( (string) $cmshop_sale['invoice_no'] ); ?></a>
						— <?php echo esc_html( (string) $cmshop_sale['customer_name'] ); ?>, <?php echo esc_html( Cmshop_View::date( (string) $cmshop_sale['created_at'] ) ); ?>
						<?php echo 'void' === $cmshop_sale['status'] ? wp_kses_post( Cmshop_View::badge( __( 'Void', 'codereyes-mobile-shop' ), 'void' ) ) : ''; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</div>
