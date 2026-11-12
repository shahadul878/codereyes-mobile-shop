<?php
/**
 * A recorded purchase: what came in, what it cost, stickers for it.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { purchase: array, supplier: array|null, items: array[] }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_purchase = $cmshop['purchase'];
$cmshop_supplier = $cmshop['supplier'];
$cmshop_left     = Cmshop_Money::to_cents( (string) $cmshop_purchase['total'] ) - Cmshop_Money::to_cents( (string) $cmshop_purchase['paid'] );
$cmshop_ids      = array_map( 'intval', array_column( $cmshop['items'], 'id' ) );
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline">
		<?php
		/* translators: %d: purchase number. */
		echo esc_html( sprintf( __( 'Purchase #%d', 'codereyes-mobile-shop' ), (int) $cmshop_purchase['id'] ) );
		?>
	</h1>
	<?php if ( array() !== $cmshop_ids ) : ?>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items', array( 'action' => 'stickers', 'item' => $cmshop_ids, 'copies' => 'stock' ) ) ); ?>"><?php esc_html_e( 'Print price stickers', 'codereyes-mobile-shop' ); ?></a>
	<?php endif; ?>
	<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG, array( 'action' => 'purchase' ) ) ); ?>"><?php esc_html_e( 'Another purchase', 'codereyes-mobile-shop' ); ?></a>
	<hr class="wp-header-end">
	<?php Cmshop_View::notice(); ?>

	<div class="cmshop-dues-summary">
		<div class="cmshop-card">
			<h2><?php esc_html_e( 'From', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-dues-summary__value">
				<?php if ( null === $cmshop_supplier ) : ?>
					&mdash;
				<?php else : ?>
					<a href="<?php echo esc_url( Cmshop_View::url( Cmshop_Supplier_Pages::SLUG, array( 'action' => 'view', 'id' => (int) $cmshop_supplier['id'] ) ) ); ?>"><?php echo esc_html( (string) $cmshop_supplier['name'] ); ?></a>
				<?php endif; ?>
			</p>
			<p>
				<?php echo esc_html( (string) mysql2date( (string) get_option( 'date_format' ), (string) $cmshop_purchase['purchased_on'] ) ); ?>
				<?php if ( '' !== (string) $cmshop_purchase['ref_no'] ) : ?>
					· <?php echo esc_html( (string) $cmshop_purchase['ref_no'] ); ?>
				<?php endif; ?>
			</p>
		</div>
		<div class="cmshop-card<?php echo $cmshop_left > 0 ? ' is-alert' : ''; ?>">
			<h2><?php esc_html_e( 'Total', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-dues-summary__value"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_purchase['total'] ) ); ?></p>
			<p>
				<?php
				echo esc_html(
					$cmshop_left > 0
						/* translators: 1: amount paid, 2: amount still owed. */
						? sprintf( __( 'Paid %1$s · you owe %2$s', 'codereyes-mobile-shop' ), Cmshop_Settings::money( (string) $cmshop_purchase['paid'] ), Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_left ) ) )
						: __( 'Paid in full', 'codereyes-mobile-shop' )
				);
				?>
			</p>
		</div>
	</div>

	<section class="cmshop-card">
		<h2>
			<?php
			/* translators: %s: number of units. */
			echo esc_html( sprintf( _n( '%s unit received', '%s units received', (int) $cmshop_purchase['units'], 'codereyes-mobile-shop' ), number_format_i18n( (int) $cmshop_purchase['units'] ) ) );
			?>
		</h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Item', 'codereyes-mobile-shop' ); ?></th>
					<th><?php esc_html_e( 'IMEI / SKU', 'codereyes-mobile-shop' ); ?></th>
					<th class="num"><?php esc_html_e( 'In stock now', 'codereyes-mobile-shop' ); ?></th>
					<th class="num"><?php esc_html_e( 'Cost', 'codereyes-mobile-shop' ); ?></th>
					<th class="num"><?php esc_html_e( 'Price', 'codereyes-mobile-shop' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cmshop['items'] as $cmshop_item ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items', array( 'action' => 'edit', 'id' => (int) $cmshop_item['id'] ) ) ); ?>"><?php echo esc_html( Cmshop_Item::display_name( $cmshop_item ) ); ?></a></td>
						<td><code><?php echo esc_html( '' !== (string) $cmshop_item['imei1'] ? (string) $cmshop_item['imei1'] : Cmshop_Stickers::code( $cmshop_item ) ); ?></code></td>
						<td class="num"><?php echo esc_html( number_format_i18n( (int) $cmshop_item['quantity'] ) ); ?></td>
						<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_item['cost_price'] ) ); ?></td>
						<td class="num"><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_item['sale_price'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( '' !== (string) $cmshop_purchase['note'] ) : ?>
			<p class="description"><?php echo esc_html( (string) $cmshop_purchase['note'] ); ?></p>
		<?php endif; ?>
	</section>
</div>
