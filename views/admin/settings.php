<?php
/**
 * Settings screen.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { settings: array }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_s    = $cmshop['settings'];
$cmshop_name = static function ( string $key ): string {
	return Cmshop_Settings::OPTION . '[' . $key . ']';
};
?>
<div class="wrap cmshop-wrap">
	<h1><?php esc_html_e( 'Mobile Shop settings', 'codereyes-mobile-shop' ); ?></h1>
	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'cmshop_settings' ); ?>

		<h2><?php esc_html_e( 'Shop details (printed on receipts and job tickets)', 'codereyes-mobile-shop' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cmshop-shop-name"><?php esc_html_e( 'Shop name', 'codereyes-mobile-shop' ); ?></label></th>
				<td><input type="text" id="cmshop-shop-name" class="regular-text" name="<?php echo esc_attr( $cmshop_name( 'shop_name' ) ); ?>" value="<?php echo esc_attr( (string) $cmshop_s['shop_name'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmshop-shop-address"><?php esc_html_e( 'Address', 'codereyes-mobile-shop' ); ?></label></th>
				<td><textarea id="cmshop-shop-address" class="large-text" rows="3" name="<?php echo esc_attr( $cmshop_name( 'shop_address' ) ); ?>"><?php echo esc_textarea( (string) $cmshop_s['shop_address'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmshop-shop-phone"><?php esc_html_e( 'Phone', 'codereyes-mobile-shop' ); ?></label></th>
				<td><input type="text" id="cmshop-shop-phone" class="regular-text" name="<?php echo esc_attr( $cmshop_name( 'shop_phone' ) ); ?>" value="<?php echo esc_attr( (string) $cmshop_s['shop_phone'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmshop-footer"><?php esc_html_e( 'Receipt footer', 'codereyes-mobile-shop' ); ?></label></th>
				<td><textarea id="cmshop-footer" class="large-text" rows="2" name="<?php echo esc_attr( $cmshop_name( 'receipt_footer' ) ); ?>"><?php echo esc_textarea( (string) $cmshop_s['receipt_footer'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Warranty or return policy, e.g. "7-day replacement warranty with this receipt."', 'codereyes-mobile-shop' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmshop-terms"><?php esc_html_e( 'Repair terms', 'codereyes-mobile-shop' ); ?></label></th>
				<td><textarea id="cmshop-terms" class="large-text" rows="3" name="<?php echo esc_attr( $cmshop_name( 'repair_terms' ) ); ?>"><?php echo esc_textarea( (string) $cmshop_s['repair_terms'] ); ?></textarea></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Receipts and invoices', 'codereyes-mobile-shop' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Default print format', 'codereyes-mobile-shop' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Default print format', 'codereyes-mobile-shop' ); ?></legend>
						<?php foreach ( Cmshop_Invoice_Format::all() as $cmshop_format => $cmshop_format_label ) : ?>
							<label><input type="radio" name="<?php echo esc_attr( $cmshop_name( 'invoice_format' ) ); ?>" value="<?php echo esc_attr( $cmshop_format ); ?>" <?php checked( $cmshop_format, (string) $cmshop_s['invoice_format'] ); ?>> <?php echo esc_html( $cmshop_format_label ); ?></label><br>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Shown first after a sale. You can switch to the other format on any sale before printing.', 'codereyes-mobile-shop' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmshop-thermal-width"><?php esc_html_e( 'Thermal paper width', 'codereyes-mobile-shop' ); ?></label></th>
				<td>
					<select id="cmshop-thermal-width" name="<?php echo esc_attr( $cmshop_name( 'thermal_width' ) ); ?>">
						<?php foreach ( Cmshop_Invoice_Format::thermal_widths() as $cmshop_width => $cmshop_width_label ) : ?>
							<option value="<?php echo esc_attr( (string) $cmshop_width ); ?>" <?php selected( (string) $cmshop_width, (string) $cmshop_s['thermal_width'] ); ?>><?php echo esc_html( $cmshop_width_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Match your receipt printer roll. In the print dialog, set margins to None.', 'codereyes-mobile-shop' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Sales and stock', 'codereyes-mobile-shop' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cmshop-currency"><?php esc_html_e( 'Currency symbol', 'codereyes-mobile-shop' ); ?></label></th>
				<td>
					<input type="text" id="cmshop-currency" class="small-text" maxlength="5" name="<?php echo esc_attr( $cmshop_name( 'currency_symbol' ) ); ?>" value="<?php echo esc_attr( (string) $cmshop_s['currency_symbol'] ); ?>">
					<?php if ( class_exists( 'WooCommerce' ) ) : ?>
						<p class="description"><?php esc_html_e( 'WooCommerce is active, so its currency is used instead.', 'codereyes-mobile-shop' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmshop-prefix"><?php esc_html_e( 'Invoice prefix', 'codereyes-mobile-shop' ); ?></label></th>
				<td><input type="text" id="cmshop-prefix" class="small-text" maxlength="10" name="<?php echo esc_attr( $cmshop_name( 'invoice_prefix' ) ); ?>" value="<?php echo esc_attr( (string) $cmshop_s['invoice_prefix'] ); ?>">
				<p class="description"><?php esc_html_e( 'Letters, numbers, - and /. Applies to new sales.', 'codereyes-mobile-shop' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmshop-low"><?php esc_html_e( 'Low stock warning', 'codereyes-mobile-shop' ); ?></label></th>
				<td><input type="number" id="cmshop-low" class="small-text" min="0" max="1000" name="<?php echo esc_attr( $cmshop_name( 'low_stock' ) ); ?>" value="<?php echo esc_attr( (string) (int) $cmshop_s['low_stock'] ); ?>">
				<p class="description"><?php esc_html_e( 'Accessories at or below this quantity show on the dashboard.', 'codereyes-mobile-shop' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Online shop', 'codereyes-mobile-shop' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $cmshop_name( 'woo_sync' ) ); ?>" value="1" <?php checked( 1, (int) $cmshop_s['woo_sync'] ); ?>> <?php esc_html_e( 'Sync stock with WooCommerce', 'codereyes-mobile-shop' ); ?></label>
					<p class="description"><?php esc_html_e( 'Counter sales update website stock, and paid website orders are recorded as sales and taken from shop stock.', 'codereyes-mobile-shop' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Repair tracker', 'codereyes-mobile-shop' ); ?></h2>
		<p>
			<?php esc_html_e( 'Add this shortcode to any page so customers can check their repair with their ticket number:', 'codereyes-mobile-shop' ); ?>
			<code>[<?php echo esc_html( Cmshop_Repair_Tracker::SHORTCODE ); ?>]</code>
		</p>

		<?php submit_button(); ?>
	</form>
</div>
