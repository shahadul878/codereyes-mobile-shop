<?php
/**
 * [cmshop_stock] grid of items in stock.
 *
 * @package CodereyesMobileShop
 *
 * @var array $view { cards, page, pages, brands, brand, condition, show_brand, show_cond, shop_phone, type, form_action, style, has_columns }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_base   = $view['form_action'];
$cmshop_filter = static function ( array $args ) use ( $cmshop_base, $view ): string {
	$current = array_filter(
		array(
			'cmshop_brand'     => $view['brand'],
			'cmshop_condition' => 'all' === $view['condition'] ? '' : $view['condition'],
		),
		'strlen'
	);

	return add_query_arg( array_filter( array_merge( $current, $args ), 'strlen' ), $cmshop_base );
};
$cmshop_conds  = array(
	'all'  => __( 'All', 'codereyes-mobile-shop' ),
	'new'  => __( 'New', 'codereyes-mobile-shop' ),
	'used' => __( 'Used', 'codereyes-mobile-shop' ),
);
$cmshop_phone  = preg_replace( '/[^\d+]/', '', $view['shop_phone'] );
$cmshop_class  = 'cmshop-ui cmshop-stock' . ( $view['has_columns'] ? ' cmshop-stock--cols' : '' );
?>
<div class="<?php echo esc_attr( $cmshop_class ); ?>" style="<?php echo esc_attr( $view['style'] ); ?>">
	<?php if ( $view['show_brand'] || $view['show_cond'] ) : ?>
		<nav class="cmshop-stock__filters" aria-label="<?php esc_attr_e( 'Filter stock', 'codereyes-mobile-shop' ); ?>">
			<?php if ( $view['show_cond'] ) : ?>
				<ul class="cmshop-segmented">
					<?php foreach ( $cmshop_conds as $cmshop_key => $cmshop_label ) : ?>
						<li><a class="<?php echo esc_attr( $cmshop_key === $view['condition'] ? 'is-active' : '' ); ?>" <?php echo $cmshop_key === $view['condition'] ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url( $cmshop_filter( array( 'cmshop_condition' => 'all' === $cmshop_key ? '' : $cmshop_key ) ) ); ?>"><?php echo esc_html( $cmshop_label ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $view['show_brand'] ) : ?>
				<ul class="cmshop-chips">
					<li><a class="<?php echo esc_attr( '' === $view['brand'] ? 'is-active' : '' ); ?>" <?php echo '' === $view['brand'] ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url( $cmshop_filter( array( 'cmshop_brand' => '' ) ) ); ?>"><?php esc_html_e( 'All brands', 'codereyes-mobile-shop' ); ?></a></li>
					<?php foreach ( $view['brands'] as $cmshop_brand ) : ?>
						<?php $cmshop_on = 0 === strcasecmp( $cmshop_brand, $view['brand'] ); ?>
						<li><a class="<?php echo esc_attr( $cmshop_on ? 'is-active' : '' ); ?>" <?php echo $cmshop_on ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url( $cmshop_filter( array( 'cmshop_brand' => $cmshop_brand ) ) ); ?>"><?php echo esc_html( $cmshop_brand ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

	<?php if ( array() === $view['cards'] ) : ?>
		<p class="cmshop-empty"><?php esc_html_e( 'Nothing in stock matches right now. Try another brand, or call us to ask.', 'codereyes-mobile-shop' ); ?></p>
	<?php else : ?>
		<ul class="cmshop-stock__grid">
			<?php foreach ( $view['cards'] as $cmshop_card ) : ?>
				<?php
				$cmshop_url    = $cmshop_card['product_id'] > 0 && 'publish' === get_post_status( $cmshop_card['product_id'] ) ? (string) get_permalink( $cmshop_card['product_id'] ) : '';
				$cmshop_is_new = 'new' === $cmshop_card['grade'];
				$cmshop_badge  = '';

				if ( Cmshop_Item::TYPE_PHONE === $cmshop_card['item_type'] ) {
					$cmshop_badge = $cmshop_is_new ? __( 'New', 'codereyes-mobile-shop' ) : __( 'Used', 'codereyes-mobile-shop' ) . ' · ' . Cmshop_Item::label( Cmshop_Item::grades(), $cmshop_card['grade'] );
				}
				?>
				<li class="cmshop-stock__card">
					<div class="cmshop-stock__media">
						<?php if ( $cmshop_card['image_id'] > 0 ) : ?>
							<?php echo wp_get_attachment_image( $cmshop_card['image_id'], 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
						<?php else : ?>
							<svg viewBox="0 0 24 24" width="64" height="64" aria-hidden="true" focusable="false"><rect x="6.5" y="2.5" width="11" height="19" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.2"/><path d="M10.5 18.5h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
						<?php endif; ?>
						<?php if ( '' !== $cmshop_badge ) : ?>
							<span class="cmshop-pill<?php echo $cmshop_is_new ? '' : ' cmshop-pill--warn'; ?>"><?php echo esc_html( $cmshop_badge ); ?></span>
						<?php endif; ?>
					</div>
					<div class="cmshop-stock__body">
						<h3 class="cmshop-stock__title">
							<?php if ( '' !== $cmshop_url ) : ?>
								<a class="cmshop-stock__link" href="<?php echo esc_url( $cmshop_url ); ?>"><?php echo esc_html( $cmshop_card['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $cmshop_card['title'] ); ?>
							<?php endif; ?>
						</h3>
						<?php if ( Cmshop_Item::TYPE_PHONE === $cmshop_card['item_type'] && ( array() !== $cmshop_card['colors'] || array() !== $cmshop_card['storages'] || null !== $cmshop_card['battery_health'] ) ) : ?>
							<ul class="cmshop-stock__meta">
								<?php foreach ( $cmshop_card['colors'] as $cmshop_colour ) : ?>
									<?php $cmshop_hex = Cmshop_Style::swatch( $cmshop_colour ); ?>
									<?php if ( '' !== $cmshop_hex ) : ?>
										<li><span class="cmshop-swatch" style="<?php echo esc_attr( '--swatch:' . $cmshop_hex ); ?>" title="<?php echo esc_attr( $cmshop_colour ); ?>" role="img" aria-label="<?php echo esc_attr( $cmshop_colour ); ?>"></span></li>
									<?php else : ?>
										<li><span class="cmshop-tag"><?php echo esc_html( $cmshop_colour ); ?></span></li>
									<?php endif; ?>
								<?php endforeach; ?>
								<?php foreach ( $cmshop_card['storages'] as $cmshop_storage ) : ?>
									<li><span class="cmshop-tag"><?php echo esc_html( $cmshop_storage ); ?></span></li>
								<?php endforeach; ?>
								<?php if ( null !== $cmshop_card['battery_health'] ) : ?>
									<li><span class="cmshop-tag">
										<?php
										/* translators: %d: battery health percentage. */
										echo esc_html( sprintf( __( 'Battery %d%%', 'codereyes-mobile-shop' ), $cmshop_card['battery_health'] ) );
										?>
									</span></li>
								<?php endif; ?>
							</ul>
						<?php endif; ?>
						<div class="cmshop-stock__foot">
							<p class="cmshop-stock__price">
								<?php if ( $cmshop_card['price_min'] !== $cmshop_card['price_max'] ) : ?>
									<small><?php esc_html_e( 'From', 'codereyes-mobile-shop' ); ?></small>
								<?php endif; ?>
								<?php echo esc_html( Cmshop_Settings::money( $cmshop_card['price_min'] ) ); ?>
							</p>
							<p class="cmshop-stock__stock<?php echo 1 === $cmshop_card['stock'] ? ' cmshop-stock__stock--low' : ''; ?>">
								<?php
								echo esc_html(
									1 === $cmshop_card['stock']
										? __( 'Last one', 'codereyes-mobile-shop' )
										/* translators: %d: number in stock. */
										: sprintf( __( '%d in stock', 'codereyes-mobile-shop' ), $cmshop_card['stock'] )
								);
								?>
							</p>
						</div>
						<?php if ( '' !== $cmshop_url ) : ?>
							<a class="cmshop-btn" href="<?php echo esc_url( $cmshop_url ); ?>" tabindex="-1" aria-hidden="true"><?php esc_html_e( 'View and buy', 'codereyes-mobile-shop' ); ?></a>
						<?php elseif ( '' !== $cmshop_phone ) : ?>
							<a class="cmshop-btn cmshop-btn--ghost" href="<?php echo esc_url( 'tel:' . $cmshop_phone ); ?>"><?php esc_html_e( 'Call to order', 'codereyes-mobile-shop' ); ?></a>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $view['pages'] > 1 ) : ?>
			<nav class="cmshop-stock__pages" aria-label="<?php esc_attr_e( 'Stock pages', 'codereyes-mobile-shop' ); ?>">
				<?php if ( $view['page'] > 1 ) : ?>
					<a class="cmshop-btn cmshop-btn--ghost" href="<?php echo esc_url( $cmshop_filter( array( 'cmshop_pg' => (string) ( $view['page'] - 1 ) ) ) ); ?>" rel="prev">&larr; <?php esc_html_e( 'Previous', 'codereyes-mobile-shop' ); ?></a>
				<?php endif; ?>
				<span>
					<?php
					/* translators: 1: page number, 2: number of pages. */
					echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'codereyes-mobile-shop' ), $view['page'], $view['pages'] ) );
					?>
				</span>
				<?php if ( $view['page'] < $view['pages'] ) : ?>
					<a class="cmshop-btn cmshop-btn--ghost" href="<?php echo esc_url( $cmshop_filter( array( 'cmshop_pg' => (string) ( $view['page'] + 1 ) ) ) ); ?>" rel="next"><?php esc_html_e( 'Next', 'codereyes-mobile-shop' ); ?> &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
