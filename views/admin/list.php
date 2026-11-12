<?php
/**
 * Generic list screen: heading, add buttons, date range, search, table, export.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { table: Cmshop_List_Table, dataset: string, args: array }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_dataset = $cmshop['dataset'];
$cmshop_pages   = array(
	'items'     => array( __( 'Stock', 'codereyes-mobile-shop' ), 'cmshop-items', __( 'Search name, IMEI, SKU or supplier', 'codereyes-mobile-shop' ) ),
	'sales'     => array( __( 'Sales', 'codereyes-mobile-shop' ), 'cmshop-sales', __( 'Search invoice, customer or phone', 'codereyes-mobile-shop' ) ),
	'repairs'   => array( __( 'Repairs', 'codereyes-mobile-shop' ), 'cmshop-repairs', __( 'Search ticket, customer, phone, device or IMEI', 'codereyes-mobile-shop' ) ),
	'customers' => array( __( 'Customers', 'codereyes-mobile-shop' ), 'cmshop-customers', __( 'Search name, phone or email', 'codereyes-mobile-shop' ) ),
);
list( $cmshop_title, $cmshop_slug, $cmshop_placeholder ) = $cmshop_pages[ $cmshop_dataset ];
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $cmshop_title ); ?></h1>

	<?php if ( 'items' === $cmshop_dataset ) : ?>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items', array( 'action' => 'new', 'type' => 'phone' ) ) ); ?>"><?php esc_html_e( 'Add phone', 'codereyes-mobile-shop' ); ?></a>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-items', array( 'action' => 'new', 'type' => 'accessory' ) ) ); ?>"><?php esc_html_e( 'Add accessory', 'codereyes-mobile-shop' ); ?></a>
	<?php elseif ( 'sales' === $cmshop_dataset ) : ?>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( 'cmshop-pos' ) ); ?>"><?php esc_html_e( 'New sale', 'codereyes-mobile-shop' ); ?></a>
	<?php else : ?>
		<a class="page-title-action" href="<?php echo esc_url( Cmshop_View::url( $cmshop_slug, array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Add new', 'codereyes-mobile-shop' ); ?></a>
	<?php endif; ?>

	<a class="page-title-action" href="<?php echo esc_url( Cmshop_Export::url( $cmshop_dataset, $cmshop['args'] ) ); ?>"><?php esc_html_e( 'Export CSV', 'codereyes-mobile-shop' ); ?></a>
	<hr class="wp-header-end">

	<?php Cmshop_View::notice(); ?>

	<form method="get" class="cmshop-list-form">
		<input type="hidden" name="page" value="<?php echo esc_attr( $cmshop_slug ); ?>">

		<?php if ( 'sales' === $cmshop_dataset ) : ?>
			<p class="cmshop-date-range">
				<label for="cmshop-from"><?php esc_html_e( 'From', 'codereyes-mobile-shop' ); ?></label>
				<input type="date" id="cmshop-from" name="from" value="<?php echo esc_attr( $cmshop['args']['from'] ); ?>">
				<label for="cmshop-to"><?php esc_html_e( 'To', 'codereyes-mobile-shop' ); ?></label>
				<input type="date" id="cmshop-to" name="to" value="<?php echo esc_attr( $cmshop['args']['to'] ); ?>">
			</p>
		<?php endif; ?>

		<p class="search-box">
			<label class="screen-reader-text" for="cmshop-search"><?php echo esc_html( $cmshop_placeholder ); ?></label>
			<input type="search" id="cmshop-search" name="s" value="<?php echo esc_attr( $cmshop['args']['search'] ); ?>" placeholder="<?php echo esc_attr( $cmshop_placeholder ); ?>">
			<?php submit_button( __( 'Search', 'codereyes-mobile-shop' ), '', '', false ); ?>
		</p>

		<?php $cmshop['table']->display(); ?>
	</form>
</div>
