<?php
/**
 * Uninstall handler.
 *
 * Sales, stock and repair records belong to the shop, so they are kept by
 * default. To delete all tables and settings, add this to wp-config.php
 * before deleting the plugin:
 *
 *     define( 'CMSHOP_DELETE_DATA', true );
 *
 * @package CodereyesMobileShop
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'CMSHOP_DELETE_DATA' ) || true !== CMSHOP_DELETE_DATA ) {
	return;
}

require_once __DIR__ . '/includes/data/class-cmshop-installer.php';

global $wpdb;

foreach ( Cmshop_Installer::TABLES as $cmshop_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping the plugin's own tables on explicit request.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', Cmshop_Installer::table( $cmshop_table ) ) );
}

delete_option( Cmshop_Installer::OPTION );
delete_option( Cmshop_Installer::GRANTED_OPTION );
delete_option( Cmshop_Installer::SELL_OPTION );
delete_option( 'cmshop_settings' );
Cmshop_Installer::remove_capability();
