<?php
/**
 * Price stickers with a scannable barcode, for label rolls or A4 sheets.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { labels: array[], ids: int[], added: string, ranges: array, copies: string, size: string, price: bool, shop: bool }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_labels = $cmshop['labels'];
$cmshop_shop   = (string) Cmshop_Settings::get( 'shop_name' );
$cmshop_copies = array(
	'1'     => __( 'One sticker per item', 'codereyes-mobile-shop' ),
	'2'     => __( 'Two per item', 'codereyes-mobile-shop' ),
	'3'     => __( 'Three per item', 'codereyes-mobile-shop' ),
	'stock' => __( 'One per unit in stock', 'codereyes-mobile-shop' ),
);
$cmshop_sizes  = array_map(
	static function ( array $size ): string {
		return $size[0];
	},
	Cmshop_Stickers::sizes()
);
?>
<div class="wrap cmshop-wrap">
	<div class="cmshop-no-print">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Price stickers', 'codereyes-mobile-shop' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items' ) ); ?>"><?php esc_html_e( 'Back to stock', 'codereyes-mobile-shop' ); ?></a>
		<hr class="wp-header-end">

		<form method="get" class="cmshop-card cmshop-sticker-tools">
			<input type="hidden" name="page" value="cmshop-items">
			<input type="hidden" name="action" value="stickers">
			<?php foreach ( $cmshop['ids'] as $cmshop_id ) : ?>
				<input type="hidden" name="item[]" value="<?php echo esc_attr( (string) $cmshop_id ); ?>">
			<?php endforeach; ?>

			<?php if ( array() === $cmshop['ids'] ) : ?>
				<label>
					<span><?php esc_html_e( 'Stock', 'codereyes-mobile-shop' ); ?></span>
					<select name="added">
						<?php foreach ( $cmshop['ranges'] as $cmshop_key => $cmshop_range ) : ?>
							<option value="<?php echo esc_attr( $cmshop_key ); ?>" <?php selected( $cmshop['added'], $cmshop_key ); ?>><?php echo esc_html( $cmshop_range[0] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>

			<label>
				<span><?php esc_html_e( 'Paper', 'codereyes-mobile-shop' ); ?></span>
				<select name="size">
					<?php foreach ( $cmshop_sizes as $cmshop_key => $cmshop_name ) : ?>
						<option value="<?php echo esc_attr( $cmshop_key ); ?>" <?php selected( $cmshop['size'], $cmshop_key ); ?>><?php echo esc_html( $cmshop_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<label>
				<span><?php esc_html_e( 'How many', 'codereyes-mobile-shop' ); ?></span>
				<select name="copies">
					<?php foreach ( $cmshop_copies as $cmshop_key => $cmshop_name ) : ?>
						<option value="<?php echo esc_attr( $cmshop_key ); ?>" <?php selected( $cmshop['copies'], $cmshop_key ); ?>><?php echo esc_html( $cmshop_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Show on the sticker', 'codereyes-mobile-shop' ); ?></legend>
				<input type="hidden" name="price" value="0">
				<label class="cmshop-check"><input type="checkbox" name="price" value="1" <?php checked( $cmshop['price'] ); ?>> <?php esc_html_e( 'Price', 'codereyes-mobile-shop' ); ?></label>
				<input type="hidden" name="shop" value="0">
				<label class="cmshop-check"><input type="checkbox" name="shop" value="1" <?php checked( $cmshop['shop'] ); ?>> <?php esc_html_e( 'Shop name', 'codereyes-mobile-shop' ); ?></label>
			</fieldset>

			<div class="cmshop-sticker-tools__go">
				<?php submit_button( __( 'Update preview', 'codereyes-mobile-shop' ), 'secondary', '', false ); ?>
				<?php if ( array() !== $cmshop_labels ) : ?>
					<button type="button" class="button button-primary cmshop-print">
						<?php
						/* translators: %s: number of stickers. */
						echo esc_html( sprintf( _n( 'Print %s sticker', 'Print %s stickers', count( $cmshop_labels ), 'codereyes-mobile-shop' ), number_format_i18n( count( $cmshop_labels ) ) ) );
						?>
					</button>
				<?php endif; ?>
			</div>
		</form>

		<?php if ( array() === $cmshop_labels ) : ?>
			<p class="cmshop-empty">
				<?php
				if ( array() === $cmshop['ids'] ) {
					esc_html_e( 'No stock arrived in this period. Widen the range, or tick items in Stock and choose "Print price stickers".', 'codereyes-mobile-shop' );
				} else {
					esc_html_e( 'Those items no longer exist.', 'codereyes-mobile-shop' );
				}
				?>
			</p>
		<?php elseif ( Cmshop_Stickers::MAX_LABELS === count( $cmshop_labels ) ) : ?>
			<p class="description">
				<?php
				/* translators: %s: maximum number of stickers. */
				echo esc_html( sprintf( __( 'Showing the first %s stickers. Print these, then pick the rest.', 'codereyes-mobile-shop' ), number_format_i18n( Cmshop_Stickers::MAX_LABELS ) ) );
				?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( array() !== $cmshop_labels ) : ?>
		<div class="cmshop-sticker-sheet is-<?php echo esc_attr( $cmshop['size'] ); ?>">
			<?php foreach ( $cmshop_labels as $cmshop_label ) : ?>
				<article class="cmshop-sticker">
					<?php if ( $cmshop['shop'] && '' !== $cmshop_shop ) : ?>
						<p class="cmshop-sticker__shop"><?php echo esc_html( $cmshop_shop ); ?></p>
					<?php endif; ?>
					<h2 class="cmshop-sticker__title"><?php echo esc_html( $cmshop_label['title'] ); ?></h2>
					<?php if ( '' !== $cmshop_label['detail'] || '' !== $cmshop_label['condition'] ) : ?>
						<p class="cmshop-sticker__meta"><?php echo esc_html( implode( ' · ', array_filter( array( $cmshop_label['detail'], $cmshop_label['condition'] ) ) ) ); ?></p>
					<?php endif; ?>
					<?php if ( $cmshop['price'] ) : ?>
						<p class="cmshop-sticker__price"><?php echo esc_html( Cmshop_Settings::money( $cmshop_label['price'] ) ); ?></p>
					<?php endif; ?>
					<?php
					// SVG built from fixed shapes and numbers only; no user text inside.
					echo Cmshop_Code128::svg( $cmshop_label['code'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<p class="cmshop-sticker__code"><?php echo esc_html( $cmshop_label['code'] ); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
