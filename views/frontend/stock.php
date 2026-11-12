<?php
/**
 * [cmshop_stock] grid of items in stock.
 *
 * @package CodereyesMobileShop
 *
 * @var array $view { cards, page, pages, brands, brand, condition, show_brand, show_cond, shop_phone, type, form_action }
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
?>
<div class="cmshop-stock">
	<?php if ( $view['show_brand'] || $view['show_cond'] ) : ?>
		<nav class="cmshop-stock__filters" aria-label="<?php esc_attr_e( 'Filter stock', 'codereyes-mobile-shop' ); ?>">
			<?php if ( $view['show_cond'] && 'accessory' !== $view['type'] ) : ?>
				<ul class="cmshop-stock__chips">
					<?php foreach ( $cmshop_conds as $cmshop_key => $cmshop_label ) : ?>
						<li><a class="<?php echo esc_attr( $cmshop_key === $view['condition'] ? 'is-active' : '' ); ?>" <?php echo $cmshop_key === $view['condition'] ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url( $cmshop_filter( array( 'cmshop_condition' => 'all' === $cmshop_key ? '' : $cmshop_key ) ) ); ?>"><?php echo esc_html( $cmshop_label ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $view['show_brand'] ) : ?>
				<ul class="cmshop-stock__chips">
					<li><a class="<?php echo esc_attr( '' === $view['brand'] ? 'is-active' : '' ); ?>" href="<?php echo esc_url( $cmshop_filter( array( 'cmshop_brand' => '' ) ) ); ?>"><?php esc_html_e( 'All brands', 'codereyes-mobile-shop' ); ?></a></li>
					<?php foreach ( $view['brands'] as $cmshop_brand ) : ?>
						<li><a class="<?php echo esc_attr( 0 === strcasecmp( $cmshop_brand, $view['brand'] ) ? 'is-active' : '' ); ?>" href="<?php echo esc_url( $cmshop_filter( array( 'cmshop_brand' => $cmshop_brand ) ) ); ?>"><?php echo esc_html( $cmshop_brand ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

	<?php if ( array() === $view['cards'] ) : ?>
		<p class="cmshop-stock__empty"><?php esc_html_e( 'Nothing in stock matches right now. Try another brand, or call us to ask.', 'codereyes-mobile-shop' ); ?></p>
	<?php else : ?>
		<ul class="cmshop-stock__grid">
			<?php foreach ( $view['cards'] as $cmshop_card ) : ?>
				<?php
				$cmshop_url   = $cmshop_card['product_id'] > 0 && 'publish' === get_post_status( $cmshop_card['product_id'] ) ? (string) get_permalink( $cmshop_card['product_id'] ) : '';
				$cmshop_badge = array();

				if ( Cmshop_Item::TYPE_PHONE === $cmshop_card['item_type'] ) {
					$cmshop_badge[] = 'new' === $cmshop_card['grade'] ? __( 'New', 'codereyes-mobile-shop' ) : __( 'Used', 'codereyes-mobile-shop' ) . ' · ' . Cmshop_Item::label( Cmshop_Item::grades(), $cmshop_card['grade'] );
				}

				if ( null !== $cmshop_card['battery_health'] ) {
					/* translators: %d: battery health percentage. */
					$cmshop_badge[] = sprintf( __( 'Battery %d%%', 'codereyes-mobile-shop' ), $cmshop_card['battery_health'] );
				}
				?>
				<li class="cmshop-stock__card">
					<div class="cmshop-stock__photo">
						<?php if ( $cmshop_card['image_id'] > 0 ) : ?>
							<?php echo wp_get_attachment_image( $cmshop_card['image_id'], 'medium', false, array( 'alt' => $cmshop_card['title'], 'loading' => 'lazy' ) ); ?>
						<?php else : ?>
							<svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true" focusable="false"><rect x="6.5" y="2.5" width="11" height="19" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 18.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
						<?php endif; ?>
					</div>
					<div class="cmshop-stock__body">
						<?php if ( array() !== $cmshop_badge ) : ?>
							<p class="cmshop-stock__badge cmshop-stock__badge--<?php echo esc_attr( 'new' === $cmshop_card['grade'] ? 'new' : 'used' ); ?>"><?php echo esc_html( implode( ' · ', $cmshop_badge ) ); ?></p>
						<?php endif; ?>
						<h3 class="cmshop-stock__title"><?php echo esc_html( $cmshop_card['title'] ); ?></h3>
						<?php if ( 'model' === $cmshop_card['kind'] && ( count( $cmshop_card['colors'] ) > 1 || count( $cmshop_card['storages'] ) > 1 ) ) : ?>
							<p class="cmshop-stock__options"><?php echo esc_html( implode( ' · ', array_filter( array( implode( ' / ', $cmshop_card['storages'] ), implode( ', ', $cmshop_card['colors'] ) ) ) ) ); ?></p>
						<?php elseif ( 'item' === $cmshop_card['kind'] && 'phone' === $cmshop_card['item_type'] && array() !== $cmshop_card['colors'] ) : ?>
							<p class="cmshop-stock__options"><?php echo esc_html( implode( ', ', $cmshop_card['colors'] ) ); ?></p>
						<?php endif; ?>
						<p class="cmshop-stock__price">
							<?php
							echo esc_html(
								$cmshop_card['price_min'] === $cmshop_card['price_max']
									? Cmshop_Settings::money( $cmshop_card['price_min'] )
									/* translators: %s: lowest price. */
									: sprintf( __( 'From %s', 'codereyes-mobile-shop' ), Cmshop_Settings::money( $cmshop_card['price_min'] ) )
							);
							?>
						</p>
						<p class="cmshop-stock__stock">
							<?php
							echo esc_html(
								1 === $cmshop_card['stock']
									? __( 'Last one in stock', 'codereyes-mobile-shop' )
									/* translators: %d: number in stock. */
									: sprintf( __( '%d in stock', 'codereyes-mobile-shop' ), $cmshop_card['stock'] )
							);
							?>
						</p>
						<?php if ( '' !== $cmshop_url ) : ?>
							<a class="cmshop-stock__cta" href="<?php echo esc_url( $cmshop_url ); ?>"><?php esc_html_e( 'View and buy', 'codereyes-mobile-shop' ); ?></a>
						<?php elseif ( '' !== $cmshop_phone ) : ?>
							<a class="cmshop-stock__cta cmshop-stock__cta--call" href="<?php echo esc_url( 'tel:' . $cmshop_phone ); ?>"><?php esc_html_e( 'Call to order', 'codereyes-mobile-shop' ); ?></a>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $view['pages'] > 1 ) : ?>
			<nav class="cmshop-stock__pages" aria-label="<?php esc_attr_e( 'Stock pages', 'codereyes-mobile-shop' ); ?>">
				<?php if ( $view['page'] > 1 ) : ?>
					<a href="<?php echo esc_url( $cmshop_filter( array( 'cmshop_pg' => (string) ( $view['page'] - 1 ) ) ) ); ?>" rel="prev">&larr; <?php esc_html_e( 'Previous', 'codereyes-mobile-shop' ); ?></a>
				<?php endif; ?>
				<span>
					<?php
					/* translators: 1: page number, 2: number of pages. */
					echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'codereyes-mobile-shop' ), $view['page'], $view['pages'] ) );
					?>
				</span>
				<?php if ( $view['page'] < $view['pages'] ) : ?>
					<a href="<?php echo esc_url( $cmshop_filter( array( 'cmshop_pg' => (string) ( $view['page'] + 1 ) ) ) ); ?>" rel="next"><?php esc_html_e( 'Next', 'codereyes-mobile-shop' ); ?> &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
