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

		<h2><?php esc_html_e( 'Warranty', 'codereyes-mobile-shop' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cmshop-warranty-new"><?php esc_html_e( 'New phones', 'codereyes-mobile-shop' ); ?></label></th>
				<td><input type="number" id="cmshop-warranty-new" class="small-text" min="0" max="3650" name="<?php echo esc_attr( $cmshop_name( 'warranty_new' ) ); ?>" value="<?php echo esc_attr( (string) (int) $cmshop_s['warranty_new'] ); ?>"> <?php esc_html_e( 'days', 'codereyes-mobile-shop' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmshop-warranty-used"><?php esc_html_e( 'Used phones', 'codereyes-mobile-shop' ); ?></label></th>
				<td><input type="number" id="cmshop-warranty-used" class="small-text" min="0" max="3650" name="<?php echo esc_attr( $cmshop_name( 'warranty_used' ) ); ?>" value="<?php echo esc_attr( (string) (int) $cmshop_s['warranty_used'] ); ?>"> <?php esc_html_e( 'days', 'codereyes-mobile-shop' ); ?>
				<p class="description"><?php esc_html_e( 'Counted from the sale date and shown by the warranty check on your website. 0 means no warranty.', 'codereyes-mobile-shop' ); ?></p></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'WhatsApp messages', 'codereyes-mobile-shop' ); ?></h2>
		<p><?php esc_html_e( 'Buttons on receipts, repairs and dues open WhatsApp with these messages already written. You check the text and press send: no API account or fees. Write them in Bangla or English; {words} in braces are filled in for you. Clear a box to go back to the default wording.', 'codereyes-mobile-shop' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cmshop-wa-country"><?php esc_html_e( 'Country calling code', 'codereyes-mobile-shop' ); ?></label></th>
				<td>
					+<input type="text" id="cmshop-wa-country" class="small-text" inputmode="numeric" maxlength="4" name="<?php echo esc_attr( $cmshop_name( 'wa_country' ) ); ?>" value="<?php echo esc_attr( (string) $cmshop_s['wa_country'] ); ?>">
					<p class="description"><?php esc_html_e( 'Added to local numbers, so 01712-345678 is sent to +880 1712-345678.', 'codereyes-mobile-shop' ); ?></p>
				</td>
			</tr>
			<?php foreach ( Cmshop_Messages::templates() as $cmshop_key => $cmshop_template ) : ?>
				<tr>
					<th scope="row"><label for="cmshop-<?php echo esc_attr( $cmshop_key ); ?>"><?php echo esc_html( $cmshop_template[0] ); ?></label></th>
					<td>
						<textarea id="cmshop-<?php echo esc_attr( $cmshop_key ); ?>" class="large-text code" rows="6" name="<?php echo esc_attr( $cmshop_name( $cmshop_key ) ); ?>"><?php echo esc_textarea( Cmshop_Messages::template( $cmshop_key ) ); ?></textarea>
						<p class="description"><?php echo esc_html( $cmshop_template[2] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h2><?php esc_html_e( 'Website shortcodes', 'codereyes-mobile-shop' ); ?></h2>
		<p><?php esc_html_e( 'Add these to any page with the "Mobile Shop" blocks in the block editor, the "Mobile Shop" widgets in Elementor, or the shortcodes below. Every one also accepts accent="#0d9488" and radius="16".', 'codereyes-mobile-shop' ); ?></p>
		<table class="widefat striped cmshop-shortcodes">
			<tbody>
				<tr>
					<td><code>[<?php echo esc_html( Cmshop_Stock_Shortcode::SHORTCODE ); ?>]</code></td>
					<td>
						<?php esc_html_e( 'Phones in stock now, with photo, price, condition and colours, and brand and new/used filters. Cost prices and IMEIs are never shown.', 'codereyes-mobile-shop' ); ?><br>
						<small><?php esc_html_e( 'Options: type="phone|accessory|all", brand="Samsung", condition="new|used", per_page="12", columns="3", filters="no"', 'codereyes-mobile-shop' ); ?></small>
					</td>
				</tr>
				<tr>
					<td><code>[<?php echo esc_html( Cmshop_Warranty_Check::SHORTCODE ); ?>]</code></td>
					<td><?php esc_html_e( 'Customers enter an IMEI to see when they bought the phone from you and whether it is still under warranty.', 'codereyes-mobile-shop' ); ?></td>
				</tr>
				<tr>
					<td><code>[<?php echo esc_html( Cmshop_Repair_Request::SHORTCODE ); ?>]</code></td>
					<td><?php esc_html_e( 'Customers book a repair online. It appears under Repairs as "Booked online" with a ticket code, and you get an email.', 'codereyes-mobile-shop' ); ?></td>
				</tr>
				<tr>
					<td><code>[<?php echo esc_html( Cmshop_Repair_Tracker::SHORTCODE ); ?>]</code></td>
					<td><?php esc_html_e( 'Customers check a repair with their ticket number and the last 4 digits of their phone number.', 'codereyes-mobile-shop' ); ?></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
