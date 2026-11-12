<?php
/**
 * PHPUnit bootstrap for pure-logic unit tests (no WordPress runtime).
 *
 * @package CodereyesMobileShop
 */

define( 'ABSPATH', sys_get_temp_dir() . '/' );

require_once __DIR__ . '/stubs/wordpress.php';

foreach ( glob( dirname( __DIR__ ) . '/includes/core/*.php' ) as $cmshop_core_file ) {
	require_once $cmshop_core_file;
}
