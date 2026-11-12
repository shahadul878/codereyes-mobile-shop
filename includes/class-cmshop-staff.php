<?php
/**
 * Who may do what in the shop.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Two levels: managers (owners, shop managers) see and change everything;
 * cashiers ("Shop Cashier" role) sell, take repairs, collect dues and run
 * their own cash register, but never see cost prices, profit or suppliers.
 */
final class Cmshop_Staff {

	/**
	 * Whether the current user may use the counter (POS, sales, repairs, dues, own register).
	 *
	 * @return bool
	 */
	public static function can_sell(): bool {
		return current_user_can( Cmshop_Installer::CAP_SELL ) || self::can_manage();
	}

	/**
	 * Whether the current user may manage stock, money, suppliers and staff.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( Cmshop_Installer::CAPABILITY );
	}

	/**
	 * Whether cost prices and profit may be shown.
	 *
	 * @return bool
	 */
	public static function sees_costs(): bool {
		return self::can_manage();
	}

	/**
	 * Display name of a staff member; sales without one came from the website.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function name( int $user_id ): string {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;

		if ( $user_id <= 0 ) {
			return __( 'Website orders', 'codereyes-mobile-shop' );
		}

		return false === $user ? __( 'Former staff', 'codereyes-mobile-shop' ) : $user->display_name;
	}
}
