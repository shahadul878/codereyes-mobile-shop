<?php
/**
 * Per-visitor limits for the public forms.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Counts attempts per IP address (hashed) in a transient.
 */
final class Cmshop_Rate_Limit {

	/**
	 * Count an attempt and say whether this visitor is over the limit.
	 *
	 * @param string $bucket Form name (separate counts per form).
	 * @param int    $max    Attempts allowed in the window.
	 * @param int    $window Window in seconds.
	 * @return bool True when the attempt must be refused.
	 */
	public static function exceeded( string $bucket, int $max, int $window ): bool {
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key      = 'cmshop_rl_' . md5( $bucket . '|' . $ip . wp_salt( 'nonce' ) );
		$attempts = (int) get_transient( $key );

		if ( $attempts >= $max ) {
			return true;
		}

		set_transient( $key, $attempts + 1, $window );

		return false;
	}
}
