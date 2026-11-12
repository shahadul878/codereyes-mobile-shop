<?php
/**
 * Elementor widget: Repair status tracker.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Repair status tracker, the same as the [cmshop_repair_status] shortcode.
 */
final class Cmshop_Elementor_Repair_Status extends Cmshop_Elementor_Widget {

	/**
	 * Widget ID.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'cmshop-repair-status';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Repair Tracker', 'codereyes-mobile-shop' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-search';
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'repair', 'status', 'tracker', 'ticket' );
	}

	/**
	 * Website part.
	 *
	 * @return string
	 */
	protected function part(): string {
		return 'repair_status';
	}
}
