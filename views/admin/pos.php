<?php
/**
 * Point of sale. Behaviour lives in assets/js/pos.js.
 *
 * @package CodereyesMobileShop
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cmshop-wrap cmshop-pos" id="cmshop-pos">
	<h1><?php esc_html_e( 'New sale', 'codereyes-mobile-shop' ); ?></h1>

	<noscript><div class="notice notice-error"><p><?php esc_html_e( 'The point of sale needs JavaScript.', 'codereyes-mobile-shop' ); ?></p></div></noscript>

	<div class="cmshop-pos__grid">
		<section class="cmshop-pos__search cmshop-card" aria-labelledby="cmshop-pos-find">
			<h2 id="cmshop-pos-find"><?php esc_html_e( 'Find item', 'codereyes-mobile-shop' ); ?></h2>
			<label class="screen-reader-text" for="cmshop-pos-term"><?php esc_html_e( 'Scan IMEI or barcode, or type a name', 'codereyes-mobile-shop' ); ?></label>
			<input type="search" id="cmshop-pos-term" class="cmshop-pos__term" autocomplete="off" autofocus placeholder="<?php esc_attr_e( 'Scan IMEI / barcode or type a name…', 'codereyes-mobile-shop' ); ?>">
			<p class="description"><?php esc_html_e( 'Press Enter to add when there is exactly one match.', 'codereyes-mobile-shop' ); ?></p>
			<ul class="cmshop-pos__results" id="cmshop-pos-results" aria-live="polite"></ul>
		</section>

		<section class="cmshop-pos__cart cmshop-card" aria-labelledby="cmshop-pos-cart-title">
			<h2 id="cmshop-pos-cart-title"><?php esc_html_e( 'Cart', 'codereyes-mobile-shop' ); ?></h2>
			<table class="cmshop-pos__lines">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'codereyes-mobile-shop' ); ?></th>
						<th class="num"><?php esc_html_e( 'Qty', 'codereyes-mobile-shop' ); ?></th>
						<th class="num"><?php esc_html_e( 'Price', 'codereyes-mobile-shop' ); ?></th>
						<th class="num"><?php esc_html_e( 'Total', 'codereyes-mobile-shop' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'codereyes-mobile-shop' ); ?></span></th>
					</tr>
				</thead>
				<tbody id="cmshop-pos-lines">
					<tr class="cmshop-pos__empty"><td colspan="5"><?php esc_html_e( 'The cart is empty. Scan or search for an item.', 'codereyes-mobile-shop' ); ?></td></tr>
				</tbody>
			</table>

			<div class="cmshop-pos__checkout">
				<fieldset class="cmshop-pos__customer">
					<legend><?php esc_html_e( 'Customer (optional)', 'codereyes-mobile-shop' ); ?></legend>
					<div class="cmshop-row">
						<div class="cmshop-field">
							<label for="cmshop-pos-phone"><?php esc_html_e( 'Phone', 'codereyes-mobile-shop' ); ?></label>
							<input type="tel" id="cmshop-pos-phone" inputmode="tel" autocomplete="off" list="cmshop-pos-customers">
							<datalist id="cmshop-pos-customers"></datalist>
						</div>
						<div class="cmshop-field">
							<label for="cmshop-pos-name"><?php esc_html_e( 'Name', 'codereyes-mobile-shop' ); ?></label>
							<input type="text" id="cmshop-pos-name" autocomplete="off" placeholder="<?php esc_attr_e( 'Walk-in customer', 'codereyes-mobile-shop' ); ?>">
						</div>
					</div>
				</fieldset>

				<div class="cmshop-row">
					<div class="cmshop-field">
						<label for="cmshop-pos-payment"><?php esc_html_e( 'Payment', 'codereyes-mobile-shop' ); ?></label>
						<select id="cmshop-pos-payment">
							<?php foreach ( Cmshop_Settings::payment_methods() as $cmshop_key => $cmshop_label ) : ?>
								<?php if ( 'online' !== $cmshop_key ) : ?>
									<option value="<?php echo esc_attr( $cmshop_key ); ?>"><?php echo esc_html( $cmshop_label ); ?></option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="cmshop-field">
						<label for="cmshop-pos-discount"><?php esc_html_e( 'Discount', 'codereyes-mobile-shop' ); ?></label>
						<input type="text" id="cmshop-pos-discount" inputmode="decimal" placeholder="0">
					</div>
					<div class="cmshop-field">
						<label for="cmshop-pos-paid"><?php esc_html_e( 'Amount received', 'codereyes-mobile-shop' ); ?></label>
						<input type="text" id="cmshop-pos-paid" inputmode="decimal" placeholder="<?php esc_attr_e( 'Full amount', 'codereyes-mobile-shop' ); ?>">
					</div>
				</div>

				<div class="cmshop-field">
					<label for="cmshop-pos-notes"><?php esc_html_e( 'Note on receipt', 'codereyes-mobile-shop' ); ?></label>
					<input type="text" id="cmshop-pos-notes" maxlength="500" class="large-text">
				</div>

				<dl class="cmshop-pos__totals">
					<dt><?php esc_html_e( 'Subtotal', 'codereyes-mobile-shop' ); ?></dt><dd id="cmshop-pos-subtotal">—</dd>
					<dt><?php esc_html_e( 'Discount', 'codereyes-mobile-shop' ); ?></dt><dd id="cmshop-pos-discount-total">—</dd>
					<dt class="cmshop-pos__grand"><?php esc_html_e( 'Total', 'codereyes-mobile-shop' ); ?></dt><dd class="cmshop-pos__grand" id="cmshop-pos-total">—</dd>
					<dt id="cmshop-pos-balance-label"><?php esc_html_e( 'Change', 'codereyes-mobile-shop' ); ?></dt><dd id="cmshop-pos-balance">—</dd>
				</dl>

				<p class="cmshop-pos__error" id="cmshop-pos-error" role="alert" hidden></p>
				<button type="button" class="button button-primary button-hero cmshop-pos__complete" id="cmshop-pos-complete" disabled><?php esc_html_e( 'Complete sale', 'codereyes-mobile-shop' ); ?></button>
			</div>
		</section>
	</div>
</div>
