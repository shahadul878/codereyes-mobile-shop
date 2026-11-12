<?php
/**
 * [cmshop_repair_status] — customers check their repair on the website.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Lookup by ticket code plus the last four digits of the customer's phone.
 */
final class Cmshop_Repair_Tracker {

	public const SHORTCODE = 'cmshop_repair_status';

	private const NONCE        = 'cmshop_repair_lookup';
	private const MAX_ATTEMPTS = 10;
	private const WINDOW       = 15 * MINUTE_IN_SECONDS;

	/**
	 * Repairs.
	 *
	 * @var Cmshop_Repair_Repository
	 */
	private $repairs;

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Repair_Repository $repairs Repairs.
	 */
	public function __construct( Cmshop_Repair_Repository $repairs ) {
		$this->repairs = $repairs;
	}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_style' ) );
	}

	/**
	 * Register the stylesheet (enqueued only where the shortcode is used).
	 */
	public function register_style(): void {
		wp_register_style( 'cmshop-frontend', CMSHOP_URL . 'assets/css/frontend.css', array(), CMSHOP_VERSION );
	}

	/**
	 * Shortcode output.
	 *
	 * @return string
	 */
	public function render(): string {
		wp_enqueue_style( 'cmshop-frontend' );

		$outcome = $this->lookup();

		ob_start();
		include CMSHOP_DIR . 'views/frontend/repair-status.php';

		return (string) ob_get_clean();
	}

	/**
	 * Handle a submitted lookup.
	 *
	 * @return array{code: string, error: string, repair: array|null}
	 */
	private function lookup(): array {
		$outcome = array(
			'code'   => '',
			'error'  => '',
			'repair' => null,
		);

		if ( ! isset( $_POST['cmshop_ticket'] ) ) {
			return $outcome;
		}

		if ( ! isset( $_POST['_cmshop_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_cmshop_nonce'] ) ), self::NONCE ) ) {
			return array_merge( $outcome, array( 'error' => __( 'This page has expired. Please reload it and try again.', 'codereyes-mobile-shop' ) ) );
		}

		$code  = Cmshop_Code::normalize_ticket( sanitize_text_field( wp_unslash( $_POST['cmshop_ticket'] ) ) );
		$last4 = Cmshop_Digits::only( sanitize_text_field( wp_unslash( $_POST['cmshop_phone_last4'] ?? '' ) ) );

		$outcome['code'] = $code;

		if ( Cmshop_Rate_Limit::exceeded( 'repair_lookup', self::MAX_ATTEMPTS, self::WINDOW ) ) {
			return array_merge( $outcome, array( 'error' => __( 'Too many lookups. Please wait 15 minutes and try again.', 'codereyes-mobile-shop' ) ) );
		}

		$repair = Cmshop_Code::is_ticket( $code ) && 4 === strlen( $last4 ) ? $this->repairs->find_by_ticket( $code ) : null;

		if ( null === $repair || ! hash_equals( Cmshop_Phone_Number::last_four( (string) $repair['customer_phone'] ), $last4 ) ) {
			return array_merge( $outcome, array( 'error' => __( 'We could not find a repair with that ticket number and phone number.', 'codereyes-mobile-shop' ) ) );
		}

		return array_merge( $outcome, array( 'repair' => $repair ) );
	}

	/**
	 * Nonce action (used by the view).
	 *
	 * @return string
	 */
	public static function nonce_action(): string {
		return self::NONCE;
	}
}
