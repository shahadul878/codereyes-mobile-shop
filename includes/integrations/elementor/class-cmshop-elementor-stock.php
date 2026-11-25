<?php
/**
 * Elementor widget: phones in stock.
 *
 * @package MobileShopManager
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
		return __( 'Phones in Stock', 'mobile-shop-manager' );
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
				'label' => __( 'What to show', 'mobile-shop-manager' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'type',
			array(
				'label'   => __( 'Items', 'mobile-shop-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'phone',
				'options' => array(
					'phone'     => __( 'Phones', 'mobile-shop-manager' ),
					'accessory' => __( 'Accessories', 'mobile-shop-manager' ),
					'all'       => __( 'Phones and accessories', 'mobile-shop-manager' ),
				),
			)
		);

		$this->add_control(
			'condition',
			array(
				'label'   => __( 'Condition', 'mobile-shop-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'all',
				'options' => array(
					'all'  => __( 'New and used', 'mobile-shop-manager' ),
					'new'  => __( 'New only', 'mobile-shop-manager' ),
					'used' => __( 'Used only', 'mobile-shop-manager' ),
				),
			)
		);

		$this->add_control(
			'brand',
			array(
				'label'       => __( 'Only this brand', 'mobile-shop-manager' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'Samsung',
				'description' => __( 'Leave empty to show every brand.', 'mobile-shop-manager' ),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'       => __( 'Columns', 'mobile-shop-manager' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 6,
				'default'     => 0,
				'description' => __( '0 fits as many as the page allows.', 'mobile-shop-manager' ),
			)
		);

		$this->add_control(
			'per_page',
			array(
				'label'   => __( 'Items per page', 'mobile-shop-manager' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 48,
				'default' => 12,
			)
		);

		$this->add_control(
			'filters',
			array(
				'label'        => __( 'Brand and condition filters', 'mobile-shop-manager' ),
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
