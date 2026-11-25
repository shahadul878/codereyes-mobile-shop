<?php
/**
 * "Online shop" panel on the item form. Included from item-form.php.
 *
 * @package MobileShopManager
 *
 * @var array      $cmshop_item    Item row.
 * @var array|null $cmshop_listing From Cmshop_Woo_Products::website_listing().
 * @var bool       $cmshop_is_sold Whether the item is sold.
 */

defined( 'ABSPATH' ) || exit;

$cmshop_kind    = $cmshop_listing['kind'] ?? '';
$cmshop_product = (int) ( $cmshop_listing['product_id'] ?? 0 );
?>
<?php if ( $cmshop_product > 0 ) : ?>
	<p>
		<?php if ( 'model' === $cmshop_kind ) : ?>
			<?php
			printf(
				/* translators: 1: model name, 2: phones in stock, 3: number of colour/storage options. */
				esc_html( _n( 'Listed on your website as %1$s: %2$d new phone in stock across %3$d option.', 'Listed on your website as %1$s: %2$d new phones in stock across %3$d options.', (int) $cmshop_listing['options'], 'mobile-shop-manager' ) ),
				'<strong>' . esc_html( $cmshop_listing['title'] ) . '</strong>',
				(int) $cmshop_listing['model_stock'],
				(int) $cmshop_listing['options']
			);
			?>
			<?php if ( '' !== $cmshop_listing['option'] ) : ?>
				<br>
				<?php
				/* translators: 1: option such as "128GB · Black", 2: phones in stock. */
				echo esc_html( sprintf( __( '%1$s: %2$d in stock.', 'mobile-shop-manager' ), $cmshop_listing['option'], (int) $cmshop_listing['option_stock'] ) );
				?>
			<?php endif; ?>
		<?php else : ?>
			<?php esc_html_e( 'Listed on your website. Stock and price update automatically.', 'mobile-shop-manager' ); ?>
		<?php endif; ?>
		<a href="<?php echo esc_url( (string) get_permalink( $cmshop_product ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'mobile-shop-manager' ); ?></a> ·
		<a href="<?php echo esc_url( (string) get_edit_post_link( $cmshop_product ) ); ?>"><?php esc_html_e( 'Add photos', 'mobile-shop-manager' ); ?></a>
	</p>
	<?php if ( 'model' === $cmshop_kind ) : ?>
		<?php /* translators: %s: model name. */ ?>
		<a class="button cmshop-confirm" data-confirm="<?php echo esc_attr( sprintf( __( 'Take %s off your website? Every colour and storage option is removed. The phones stay in stock.', 'mobile-shop-manager' ), $cmshop_listing['title'] ) ); ?>" href="<?php echo esc_url( Cmshop_View::action_url( 'unpublish_item', array( 'id' => (int) $cmshop_item['id'] ) ) ); ?>"><?php esc_html_e( 'Remove model from website', 'mobile-shop-manager' ); ?></a>
	<?php else : ?>
		<a class="button" href="<?php echo esc_url( Cmshop_View::action_url( 'unpublish_item', array( 'id' => (int) $cmshop_item['id'] ) ) ); ?>"><?php esc_html_e( 'Remove from website', 'mobile-shop-manager' ); ?></a>
	<?php endif; ?>
<?php elseif ( ! $cmshop_is_sold && 'model_unlisted' === $cmshop_kind ) : ?>
	<p>
		<?php
		printf(
			/* translators: 1: model name, 2: phones in stock. */
			esc_html__( 'Not on your website yet. Publishing lists every new %1$s as one product where customers choose storage and colour (%2$d in stock now). Each order gets the phone of that colour that has been in stock longest. IMEIs stay private.', 'mobile-shop-manager' ),
			'<strong>' . esc_html( $cmshop_listing['title'] ) . '</strong>',
			(int) $cmshop_listing['model_stock']
		);
		?>
	</p>
	<a class="button" href="<?php echo esc_url( Cmshop_View::action_url( 'publish_item', array( 'id' => (int) $cmshop_item['id'] ) ) ); ?>"><?php esc_html_e( 'Publish model to website', 'mobile-shop-manager' ); ?></a>
<?php elseif ( ! $cmshop_is_sold ) : ?>
	<p><?php esc_html_e( 'Not on your website yet. Publishing creates a WooCommerce product with this price and stock. The IMEI stays private.', 'mobile-shop-manager' ); ?></p>
	<a class="button" href="<?php echo esc_url( Cmshop_View::action_url( 'publish_item', array( 'id' => (int) $cmshop_item['id'] ) ) ); ?>"><?php esc_html_e( 'Publish to website', 'mobile-shop-manager' ); ?></a>
<?php endif; ?>
