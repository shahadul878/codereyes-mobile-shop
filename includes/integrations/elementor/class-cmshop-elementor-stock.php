<?php
/**
 * Elementor widget: phones in stock.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

/**
 * Phones (or accessories) in stock, the same as the [cmshop_stock] shortcode.
 */
final class Cmshop_Elementor_Stock extends Cmshop_Elementor_Widget {

	/**
	 * Widget ID.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'cmshop-stock';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Phones in Stock', 'codereyes-mobile-shop' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-products';
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'phones', 'stock', 'shop', 'mobile', 'inventory' );
	}

	/**
	 * Website part.
	 *
	 * @return string
	 */
	protected function part(): string {
		return 'stock';
	}

	/**
	 * Content tab: what to show.
	 */
	protected function content_controls(): void {
		$this->start_controls_section(
			'cmshop_content',
			array(
				'label' => __( 'What to show', 'codereyes-mobile-shop' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'type',
			array(
				'label'   => __( 'Items', 'codereyes-mobile-shop' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'phone',
				'options' => array(
					'phone'     => __( 'Phones', 'codereyes-mobile-shop' ),
					'accessory' => __( 'Accessories', 'codereyes-mobile-shop' ),
					'all'       => __( 'Phones and accessories', 'codereyes-mobile-shop' ),
				),
			)
		);

		$this->add_control(
			'condition',
			array(
				'label'   => __( 'Condition', 'codereyes-mobile-shop' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'all',
				'options' => array(
					'all'  => __( 'New and used', 'codereyes-mobile-shop' ),
					'new'  => __( 'New only', 'codereyes-mobile-shop' ),
					'used' => __( 'Used only', 'codereyes-mobile-shop' ),
				),
			)
		);

		$this->add_control(
			'brand',
			array(
				'label'       => __( 'Only this brand', 'codereyes-mobile-shop' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'Samsung',
				'description' => __( 'Leave empty to show every brand.', 'codereyes-mobile-shop' ),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'       => __( 'Columns', 'codereyes-mobile-shop' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 6,
				'default'     => 0,
				'description' => __( '0 fits as many as the page allows.', 'codereyes-mobile-shop' ),
			)
		);

		$this->add_control(
			'per_page',
			array(
				'label'   => __( 'Items per page', 'codereyes-mobile-shop' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 48,
				'default' => 12,
			)
		);

		$this->add_control(
			'filters',
			array(
				'label'        => __( 'Brand and condition filters', 'codereyes-mobile-shop' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Shortcode attributes from the content settings.
	 *
	 * @param array $settings Widget settings.
	 * @return array<string,string>
	 */
	protected function atts( array $settings ): array {
		$atts = array(
			'type'      => (string) ( $settings['type'] ?? 'phone' ),
			'condition' => (string) ( $settings['condition'] ?? 'all' ),
			'brand'     => (string) ( $settings['brand'] ?? '' ),
			'per_page'  => (string) absint( $settings['per_page'] ?? 12 ),
			'filters'   => 'yes' === ( $settings['filters'] ?? 'yes' ) ? 'yes' : 'no',
		);

		if ( absint( $settings['columns'] ?? 0 ) > 0 ) {
			$atts['columns'] = (string) absint( $settings['columns'] );
		}

		return $atts;
	}
}
