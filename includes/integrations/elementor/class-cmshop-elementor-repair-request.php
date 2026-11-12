<?php
/**
 * Elementor widget: Online repair booking form.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Online repair booking form, the same as the [cmshop_repair_request] shortcode.
 */
final class Cmshop_Elementor_Repair_Request extends Cmshop_Elementor_Widget {

	/**
	 * Widget ID.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'cmshop-repair-request';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Book a Repair', 'codereyes-mobile-shop' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-form-horizontal';
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'repair', 'booking', 'form', 'service' );
	}

	/**
	 * Website part.
	 *
	 * @return string
	 */
	protected function part(): string {
		return 'repair_request';
	}
}
