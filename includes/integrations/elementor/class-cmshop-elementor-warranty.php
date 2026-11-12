<?php
/**
 * Elementor widget: Warranty check by IMEI.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Warranty check by IMEI, the same as the [cmshop_warranty] shortcode.
 */
final class Cmshop_Elementor_Warranty extends Cmshop_Elementor_Widget {

	/**
	 * Widget ID.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'cmshop-warranty';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Warranty Check', 'codereyes-mobile-shop' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-check-circle';
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'warranty', 'imei', 'guarantee' );
	}

	/**
	 * Website part.
	 *
	 * @return string
	 */
	protected function part(): string {
		return 'warranty';
	}
}
