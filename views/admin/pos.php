<?php
/**
 * Full-screen point of sale. assets/js/pos.js fills the grid and the cart.
 *
 * @package CodereyesMobileShop
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cmshop-wrap cmshop-pos-app" id="cmshop-pos">
	<header class="cmshop-pos-top">
		<a class="cmshop-pos-top__exit" href="<?php echo esc_url( Cmshop_View::url( 'cmshop' ) ); ?>">
			<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><?php esc_html_e( 'Dashboard', 'codereyes-mobile-shop' ); ?>
		</a>
		<h1 class="cmshop-pos-top__shop"><?php echo esc_html( (string) Cmshop_Settings::get( 'shop_name' ) ); ?></h1>
		<div class="cmshop-pos-top__tools">
			<?php $cmshop_shift = Cmshop_Plugin::shifts()->current( get_current_user_id() ); ?>
			<a class="cmshop-pos-chip<?php echo null === $cmshop_shift ? ' is-warn' : ''; ?>" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-shifts' ) ); ?>">
				<span class="dashicons dashicons-<?php echo null === $cmshop_shift ? 'warning' : 'clock'; ?>" aria-hidden="true"></span>
				<?php
				echo esc_html(
					null === $cmshop_shift
						? __( 'Open a shift', 'codereyes-mobile-shop' )
						/* translators: %s: time the shift opened. */
						: sprintf( __( 'Shift since %s', 'codereyes-mobile-shop' ), (string) mysql2date( (string) get_option( 'time_format' ), (string) $cmshop_shift['opened_at'] ) )
				);
				?>
			</a>
			<button type="button" class="cmshop-pos-chip" data-action="held-open">
				<span class="dashicons dashicons-backup" aria-hidden="true"></span><?php esc_html_e( 'Held sales', 'codereyes-mobile-shop' ); ?>
				<span class="cmshop-pos-count" data-held-count>0</span>
			</button>
			<button type="button" class="cmshop-pos-chip" data-action="fullscreen" aria-label="<?php esc_attr_e( 'Full screen', 'codereyes-mobile-shop' ); ?>">
				<span class="dashicons dashicons-editor-expand" aria-hidden="true"></span>
			</button>
			<span class="cmshop-pos-top__user"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span data-cashier></span></span>
		</div>
	</header>

	<div class="cmshop-pos-body">
		<section class="cmshop-pos-catalog" aria-label="<?php esc_attr_e( 'Products', 'codereyes-mobile-shop' ); ?>">
			<div class="cmshop-pos-search">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<label class="screen-reader-text" for="cmshop-pos-search"><?php esc_html_e( 'Scan an IMEI or barcode, or type a name', 'codereyes-mobile-shop' ); ?></label>
				<input type="search" id="cmshop-pos-search" autocomplete="off" placeholder="<?php esc_attr_e( 'Scan an IMEI or barcode, or type a name', 'codereyes-mobile-shop' ); ?>">
				<kbd>F2</kbd>
			</div>
			<div class="cmshop-pos-filters">
				<div class="cmshop-pos-tabs" role="group" aria-label="<?php esc_attr_e( 'Item type', 'codereyes-mobile-shop' ); ?>" data-types></div>
				<div class="cmshop-pos-tabs cmshop-pos-tabs--brands" role="group" aria-label="<?php esc_attr_e( 'Brand', 'codereyes-mobile-shop' ); ?>" data-brands></div>
			</div>
			<ul class="cmshop-pos-grid" data-grid aria-live="polite"></ul>
			<p class="cmshop-pos-empty" data-grid-empty hidden></p>
		</section>

		<aside class="cmshop-pos-side" aria-label="<?php esc_attr_e( 'Current sale', 'codereyes-mobile-shop' ); ?>">
			<div class="cmshop-pos-customer">
				<div class="cmshop-pos-customer__fields">
					<label class="screen-reader-text" for="cmshop-pos-phone"><?php esc_html_e( 'Customer phone', 'codereyes-mobile-shop' ); ?></label>
					<input type="tel" id="cmshop-pos-phone" inputmode="tel" autocomplete="off" placeholder="<?php esc_attr_e( 'Customer phone', 'codereyes-mobile-shop' ); ?>">
					<label class="screen-reader-text" for="cmshop-pos-name"><?php esc_html_e( 'Customer name', 'codereyes-mobile-shop' ); ?></label>
					<input type="text" id="cmshop-pos-name" autocomplete="off" placeholder="<?php esc_attr_e( 'Walk-in customer', 'codereyes-mobile-shop' ); ?>">
				</div>
				<p class="cmshop-pos-customer__info" data-customer-info hidden></p>
			</div>

			<ul class="cmshop-pos-lines" data-lines></ul>
			<ul class="cmshop-pos-lines cmshop-pos-trades" data-trades></ul>
			<p class="cmshop-pos-empty cmshop-pos-empty--cart" data-cart-empty><?php esc_html_e( 'Scan or tap a product to start a sale.', 'codereyes-mobile-shop' ); ?></p>

			<details class="cmshop-pos-extra">
				<summary><?php esc_html_e( 'Discount and note', 'codereyes-mobile-shop' ); ?></summary>
				<div class="cmshop-pos-extra__fields">
					<label><?php esc_html_e( 'Discount', 'codereyes-mobile-shop' ); ?><input type="text" inputmode="decimal" data-discount placeholder="0"></label>
					<label><?php esc_html_e( 'Note on receipt', 'codereyes-mobile-shop' ); ?><input type="text" data-note maxlength="200"></label>
				</div>
			</details>

			<dl class="cmshop-pos-totals">
				<div><dt><?php esc_html_e( 'Subtotal', 'codereyes-mobile-shop' ); ?></dt><dd data-subtotal></dd></div>
				<div data-discount-row hidden><dt><?php esc_html_e( 'Discount', 'codereyes-mobile-shop' ); ?></dt><dd data-discount-text></dd></div>
				<div class="cmshop-pos-totals__total"><dt><?php esc_html_e( 'Total', 'codereyes-mobile-shop' ); ?></dt><dd data-total></dd></div>
				<div data-trade-row hidden><dt><?php esc_html_e( 'Trade-in', 'codereyes-mobile-shop' ); ?></dt><dd data-trade-text></dd></div>
				<div class="cmshop-pos-totals__topay" data-topay-row hidden><dt><?php esc_html_e( 'To pay', 'codereyes-mobile-shop' ); ?></dt><dd data-topay></dd></div>
			</dl>

			<div class="cmshop-pos-pay">
				<div data-payments></div>
				<div class="cmshop-pos-quick" data-quick aria-label="<?php esc_attr_e( 'Cash received', 'codereyes-mobile-shop' ); ?>"></div>
				<div class="cmshop-pos-links">
					<button type="button" class="button-link cmshop-pos-split" data-action="split"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Split payment', 'codereyes-mobile-shop' ); ?></button>
					<button type="button" class="button-link cmshop-pos-split" data-action="trade-open"><span class="dashicons dashicons-randomize" aria-hidden="true"></span><?php esc_html_e( 'Trade-in', 'codereyes-mobile-shop' ); ?></button>
				</div>
				<p class="cmshop-pos-balance" data-balance></p>
				<div class="cmshop-pos-plan" data-plan hidden>
					<label><?php esc_html_e( 'Installments', 'codereyes-mobile-shop' ); ?>
						<select data-plan-count>
							<option value="0"><?php esc_html_e( 'No plan', 'codereyes-mobile-shop' ); ?></option>
							<?php for ( $cmshop_n = Cmshop_Installments::MIN_COUNT; $cmshop_n <= Cmshop_Installments::MAX_COUNT; $cmshop_n++ ) : ?>
								<option value="<?php echo esc_attr( (string) $cmshop_n ); ?>"><?php echo esc_html( (string) $cmshop_n ); ?></option>
							<?php endfor; ?>
						</select>
					</label>
					<label><?php esc_html_e( 'Every', 'codereyes-mobile-shop' ); ?>
						<select data-plan-interval>
							<option value="month"><?php esc_html_e( 'Month', 'codereyes-mobile-shop' ); ?></option>
							<option value="week"><?php esc_html_e( 'Week', 'codereyes-mobile-shop' ); ?></option>
						</select>
					</label>
					<label><?php esc_html_e( 'First payment', 'codereyes-mobile-shop' ); ?>
						<input type="date" data-plan-first value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +1 month' ) ) ); ?>">
					</label>
					<p class="cmshop-pos-plan__each" data-plan-each></p>
				</div>
			</div>

			<p class="cmshop-pos-error" data-error role="alert" hidden></p>

			<div class="cmshop-pos-actions">
				<button type="button" class="button cmshop-pos-hold" data-action="hold"><span class="dashicons dashicons-controls-pause" aria-hidden="true"></span><?php esc_html_e( 'Hold', 'codereyes-mobile-shop' ); ?> <kbd>F9</kbd></button>
				<button type="button" class="button button-primary cmshop-pos-payit" data-action="pay"><?php esc_html_e( 'Take payment', 'codereyes-mobile-shop' ); ?> <kbd>F8</kbd></button>
			</div>
		</aside>
	</div>

	<dialog class="cmshop-pos-dialog" data-dialog="variant" aria-labelledby="cmshop-pos-variant-title">
		<header><h2 id="cmshop-pos-variant-title" data-variant-title></h2><button type="button" class="cmshop-pos-x" data-action="close" aria-label="<?php esc_attr_e( 'Close', 'codereyes-mobile-shop' ); ?>">&times;</button></header>
		<div class="cmshop-pos-options" data-options></div>
		<ul class="cmshop-pos-units" data-units></ul>
	</dialog>

	<dialog class="cmshop-pos-dialog" data-dialog="held" aria-labelledby="cmshop-pos-held-title">
		<header><h2 id="cmshop-pos-held-title"><?php esc_html_e( 'Held sales', 'codereyes-mobile-shop' ); ?></h2><button type="button" class="cmshop-pos-x" data-action="close" aria-label="<?php esc_attr_e( 'Close', 'codereyes-mobile-shop' ); ?>">&times;</button></header>
		<ul class="cmshop-pos-heldlist" data-held></ul>
	</dialog>

	<dialog class="cmshop-pos-dialog" data-dialog="trade" aria-labelledby="cmshop-pos-trade-title">
		<header><h2 id="cmshop-pos-trade-title"><?php esc_html_e( 'Take a phone in trade', 'codereyes-mobile-shop' ); ?></h2><button type="button" class="cmshop-pos-x" data-action="close" aria-label="<?php esc_attr_e( 'Close', 'codereyes-mobile-shop' ); ?>">&times;</button></header>
		<form class="cmshop-pos-trade" data-trade-form novalidate>
			<label><?php esc_html_e( 'Brand', 'codereyes-mobile-shop' ); ?><input type="text" name="brand" required list="cmshop-pos-brands"></label>
			<label><?php esc_html_e( 'Model', 'codereyes-mobile-shop' ); ?><input type="text" name="model" required placeholder="iPhone 11"></label>
			<label><?php esc_html_e( 'Storage', 'codereyes-mobile-shop' ); ?><input type="text" name="storage" placeholder="64GB"></label>
			<label><?php esc_html_e( 'Colour', 'codereyes-mobile-shop' ); ?><input type="text" name="color"></label>
			<label><?php esc_html_e( 'Condition', 'codereyes-mobile-shop' ); ?>
				<select name="grade">
					<?php foreach ( Cmshop_Item::grades() as $cmshop_key => $cmshop_label ) : ?>
						<?php if ( 'new' !== $cmshop_key ) : ?>
							<option value="<?php echo esc_attr( $cmshop_key ); ?>" <?php selected( 'good', $cmshop_key ); ?>><?php echo esc_html( $cmshop_label ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'Battery health %', 'codereyes-mobile-shop' ); ?><input type="text" name="battery_health" inputmode="numeric" maxlength="3"></label>
			<label class="cmshop-pos-trade__wide"><?php esc_html_e( 'IMEI (dial *#06#)', 'codereyes-mobile-shop' ); ?><input type="text" name="imei1" required inputmode="numeric" class="cmshop-mono"></label>
			<label class="cmshop-pos-trade__wide"><?php esc_html_e( 'Value you give', 'codereyes-mobile-shop' ); ?><input type="text" name="value" required inputmode="decimal"></label>
			<fieldset class="cmshop-pos-trade__wide">
				<legend><?php esc_html_e( 'Checked', 'codereyes-mobile-shop' ); ?></legend>
				<?php foreach ( Cmshop_Validator::trade_in_checks() as $cmshop_key => $cmshop_label ) : ?>
					<label class="cmshop-pos-check"><input type="checkbox" name="checks" value="<?php echo esc_attr( $cmshop_key ); ?>"> <?php echo esc_html( $cmshop_label ); ?></label>
				<?php endforeach; ?>
			</fieldset>
			<p class="cmshop-pos-error cmshop-pos-trade__wide" data-trade-error hidden></p>
			<button type="submit" class="button button-primary cmshop-pos-trade__wide"><?php esc_html_e( 'Add trade-in', 'codereyes-mobile-shop' ); ?></button>
		</form>
		<datalist id="cmshop-pos-brands"></datalist>
	</dialog>

	<dialog class="cmshop-pos-dialog cmshop-pos-dialog--done" data-dialog="done" aria-labelledby="cmshop-pos-done-title">
		<span class="cmshop-pos-done-icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
		<h2 id="cmshop-pos-done-title"><?php esc_html_e( 'Sale complete', 'codereyes-mobile-shop' ); ?></h2>
		<p class="cmshop-pos-done-change" data-done-text></p>
		<div class="cmshop-pos-done-actions">
			<a class="button" data-receipt="thermal" target="_blank" rel="noopener"><?php esc_html_e( 'Print receipt', 'codereyes-mobile-shop' ); ?></a>
			<a class="button" data-receipt="a4" target="_blank" rel="noopener"><?php esc_html_e( 'A4 invoice', 'codereyes-mobile-shop' ); ?></a>
			<a class="button cmshop-wa" data-receipt="whatsapp" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Send on WhatsApp', 'codereyes-mobile-shop' ); ?></a>
			<button type="button" class="button button-primary" data-action="new-sale"><?php esc_html_e( 'New sale', 'codereyes-mobile-shop' ); ?> <kbd>Enter</kbd></button>
		</div>
	</dialog>
</div>
