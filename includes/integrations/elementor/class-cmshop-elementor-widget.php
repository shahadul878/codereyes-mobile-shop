<?php
/**
 * Shared base for the Mobile Shop Elementor widgets.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

/**
 * Renders a website part and gives every widget the same Style tab: accent
 * colour, corner radius, card colours and heading typography. Style settings
 * become CSS custom properties, so the preview updates without a reload.
 */
abstract class Cmshop_Elementor_Widget extends Widget_Base {

	/**
	 * Website part this widget shows.
	 *
	 * @return string stock|warranty|repair_request|repair_status.
	 */
	abstract protected function part(): string;

	/**
	 * Widget categories.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( Cmshop_Elementor::CATEGORY );
	}

	/**
	 * Styles the widget needs.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( 'cmshop-frontend' );
	}

	/**
	 * Whether the widget output changes per visitor (forms and filters do).
	 *
	 * @return bool
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/**
	 * Controls: content (per widget) and style (shared).
	 */
	protected function register_controls(): void {
		$this->content_controls();
		$this->style_controls();
	}

	/**
	 * Content tab. Widgets with options override this.
	 */
	protected function content_controls(): void {
		$this->start_controls_section(
			'cmshop_content',
			array(
				'label' => __( 'Content', 'codereyes-mobile-shop' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'cmshop_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'This form works as it is. Change its look in the Style tab, and shop details under Mobile Shop → Settings.', 'codereyes-mobile-shop' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Shortcode attributes from the content settings. Widgets with options override this.
	 *
	 * @param array $settings Widget settings.
	 * @return array<string,string>
	 */
	protected function atts( array $settings ): array {
		unset( $settings );

		return array();
	}

	/**
	 * Output.
	 */
	protected function render(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The view escapes every value.
		echo Cmshop_Plugin::render_part( $this->part(), $this->atts( (array) $this->get_settings_for_display() ) );
	}

	/**
	 * Style tab shared by all widgets.
	 */
	private function style_controls(): void {
		$this->start_controls_section(
			'cmshop_style',
			array(
				'label' => __( 'Colours and shape', 'codereyes-mobile-shop' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'cmshop_accent',
			array(
				'label'     => __( 'Accent colour', 'codereyes-mobile-shop' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cmshop-ui' => '--cmshop-accent: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'cmshop_accent_ink',
			array(
				'label'     => __( 'Text on accent', 'codereyes-mobile-shop' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cmshop-ui' => '--cmshop-accent-ink: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'cmshop_card',
			array(
				'label'     => __( 'Card background', 'codereyes-mobile-shop' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cmshop-ui' => '--cmshop-card: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'cmshop_text',
			array(
				'label'     => __( 'Card text', 'codereyes-mobile-shop' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cmshop-panel, {{WRAPPER}} .cmshop-stock__card' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'cmshop_radius',
			array(
				'label'      => __( 'Corner radius', 'codereyes-mobile-shop' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 32,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .cmshop-ui' => '--cmshop-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'cmshop_heading',
				'label'    => __( 'Headings', 'codereyes-mobile-shop' ),
				'selector' => '{{WRAPPER}} .cmshop-stock__title, {{WRAPPER}} .cmshop-lookup__head, {{WRAPPER}} .cmshop-booking__done h3',
			)
		);

		$this->end_controls_section();
	}
}
