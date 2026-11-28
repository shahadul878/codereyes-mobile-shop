<?php
/**
 * Record not found.
 *
 * @package CodereyesMobileShopManager
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cmshop-wrap">
	<h1><?php esc_html_e( 'Not found', 'codereyes-mobile-shop-manager' ); ?></h1>
	<p><?php esc_html_e( 'That record no longer exists. It may have been deleted.', 'codereyes-mobile-shop-manager' ); ?></p>
	<p><a class="button" href="<?php echo esc_url( Cmshop_View::url( Cmshop_Admin::SLUG ) ); ?>"><?php esc_html_e( 'Back to the dashboard', 'codereyes-mobile-shop-manager' ); ?></a></p>
</div>
