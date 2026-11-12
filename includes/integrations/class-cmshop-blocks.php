<?php
/**
 * Gutenberg blocks for the website parts.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Registers one server-rendered block per website part, in a "Mobile Shop"
 * block category. Blocks render through the same code as the shortcodes.
 */
final class Cmshop_Blocks {

	public const CATEGORY = 'codereyes-mobile-shop';

	private const SCRIPT = 'cmshop-blocks';

	/**
	 * Block folder → website part.
	 */
	private const BLOCKS = array(
		'stock'          => 'stock',
		'warranty'       => 'warranty',
		'repair-request' => 'repair_request',
		'repair-status'  => 'repair_status',
	);

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( $this, 'add_category' ) );
	}

	/**
	 * Register the editor script and the blocks.
	 */
	public function register_blocks(): void {
		wp_register_script(
			self::SCRIPT,
			CMSHOP_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			CMSHOP_VERSION,
			true
		);
		wp_set_script_translations( self::SCRIPT, 'codereyes-mobile-shop' );

		foreach ( self::BLOCKS as $folder => $part ) {
			register_block_type(
				CMSHOP_DIR . 'blocks/' . $folder,
				array(
					'render_callback' => static function ( array $attributes ) use ( $part ): string {
						return sprintf(
							'<div %s>%s</div>',
							get_block_wrapper_attributes(),
							Cmshop_Plugin::render_part( $part, Cmshop_Block_Atts::map( $part, $attributes ) )
						);
					},
				)
			);
		}
	}

	/**
	 * Add the "Mobile Shop" category to the block inserter.
	 *
	 * @param array $categories Categories.
	 * @return array
	 */
	public function add_category( array $categories ): array {
		return array_merge(
			array(
				array(
					'slug'  => self::CATEGORY,
					'title' => __( 'Mobile Shop', 'codereyes-mobile-shop' ),
					'icon'  => 'smartphone',
				),
			),
			$categories
		);
	}
}
