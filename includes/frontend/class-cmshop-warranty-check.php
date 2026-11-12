<?php
/**
 * [cmshop_warranty] — customers check a phone's warranty by IMEI.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Looks up the latest completed sale of an IMEI and shows the phone, the
 * purchase date and the warranty status. Never shows the buyer or the price.
 */
final class Cmshop_Warranty_Check {

	public const SHORTCODE = 'cmshop_warranty';

	private const NONCE        = 'cmshop_warranty_lookup';
	private const MAX_ATTEMPTS = 10;
	private const WINDOW       = 15 * MINUTE_IN_SECONDS;

	/**
	 * Sales.
	 *
	 * @var Cmshop_Sale_Repository
	 */
	private $sales;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Sale_Repository $sales Sales.
	 */
	public function __construct( Cmshop_Sale_Repository $sales ) {
		$this->sales = $sales;
	}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Shortcode output.
	 *
	 * @param array|string $atts accent, radius.
	 * @return string
	 */
	public function render( $atts = array() ): string {
		wp_enqueue_style( 'cmshop-frontend' );

		$outcome = $this->lookup();
		$style   = Cmshop_Style::css_vars( is_array( $atts ) ? $atts : array() );

		ob_start();
		include CMSHOP_DIR . 'views/frontend/warranty.php';

		return (string) ob_get_clean();
	}

	/**
	 * Nonce action (used by the view).
	 *
	 * @return string
	 */
	public static function nonce_action(): string {
		return self::NONCE;
	}

	/**
	 * Handle a submitted IMEI.
	 *
	 * @return array{imei: string, error: string, sale: array|null, warranty: array|null}
	 */
	private function lookup(): array {
		$outcome = array(
			'imei'     => '',
			'error'    => '',
			'sale'     => null,
			'warranty' => null,
		);

		if ( ! isset( $_POST['cmshop_warranty_imei'] ) ) {
			return $outcome;
		}

		if ( ! isset( $_POST['_cmshop_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_cmshop_nonce'] ) ), self::NONCE ) ) {
			return array_merge( $outcome, array( 'error' => __( 'This page has expired. Please reload it and try again.', 'codereyes-mobile-shop' ) ) );
		}

		$imei            = Cmshop_Imei::normalize( sanitize_text_field( wp_unslash( $_POST['cmshop_warranty_imei'] ) ) );
		$outcome['imei'] = $imei;

		if ( ! Cmshop_Imei::is_valid( $imei ) ) {
			return array_merge( $outcome, array( 'error' => __( 'That is not a valid IMEI. Dial *#06# on the phone to see it (15 digits).', 'codereyes-mobile-shop' ) ) );
		}

		if ( Cmshop_Rate_Limit::exceeded( 'warranty', self::MAX_ATTEMPTS, self::WINDOW ) ) {
			return array_merge( $outcome, array( 'error' => __( 'Too many checks. Please wait 15 minutes and try again.', 'codereyes-mobile-shop' ) ) );
		}

		$sale = $this->sales->find_phone_sale( $imei, Cmshop_Installer::table( 'items' ) );

		if ( null === $sale ) {
			return array_merge( $outcome, array( 'error' => __( 'We have no sale for this IMEI. Only phones bought from our shop can be checked here.', 'codereyes-mobile-shop' ) ) );
		}

		$days = Cmshop_Warranty::days_for( (string) $sale['grade'], (int) Cmshop_Settings::get( 'warranty_new' ), (int) Cmshop_Settings::get( 'warranty_used' ) );

		return array_merge(
			$outcome,
			array(
				'sale'     => $sale,
				'warranty' => Cmshop_Warranty::status( (string) $sale['sold_at'], $days, current_time( 'Y-m-d' ) ),
			)
		);
	}
}
