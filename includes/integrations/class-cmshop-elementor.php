<?php
/**
 * Elementor widgets for the website parts.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Adds a "Mobile Shop" widget category and one widget per website part.
 * The widget classes extend Elementor's, so they load only when Elementor
 * asks for widgets.
 */
final class Cmshop_Elementor {

	public const CATEGORY = 'codereyes-mobile-shop';

	private const WIDGETS = array(
		'class-cmshop-elementor-stock.php'          => 'Cmshop_Elementor_Stock',
		'class-cmshop-elementor-warranty.php'       => 'Cmshop_Elementor_Warranty',
		'class-cmshop-elementor-repair-request.php' => 'Cmshop_Elementor_Repair_Request',
		'class-cmshop-elementor-repair-status.php'  => 'Cmshop_Elementor_Repair_Status',
	);

	/**
	 * Hook in. Does nothing unless Elementor runs these hooks.
	 */
	public function register(): void {
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Add the "Mobile Shop" category to the widget panel.
	 *
	 * @param object $elements_manager \Elementor\Elements_Manager.
	 */
	public function add_category( $elements_manager ): void {
		$elements_manager->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'Mobile Shop', 'codereyes-mobile-shop' ),
				'icon'  => 'eicon-device-mobile',
			)
		);
	}

	/**
	 * Load and register the widgets.
	 *
	 * @param object $widgets_manager \Elementor\Widgets_Manager.
	 */
	public function register_widgets( $widgets_manager ): void {
		$dir = CMSHOP_DIR . 'includes/integrations/elementor/';

		require_once $dir . 'class-cmshop-elementor-widget.php';

		foreach ( self::WIDGETS as $file => $class ) {
			require_once $dir . $file;
			$widgets_manager->register( new $class() );
		}
	}
}
