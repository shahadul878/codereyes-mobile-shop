<?php
/**
 * [cmshop_repair_request] — customers book a repair on the website.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Creates a "Booked online" repair job with a ticket code, emails the shop
 * (and the customer, when they give an email), then redirects so a refresh
 * cannot book twice.
 */
final class Cmshop_Repair_Request {

	public const SHORTCODE = 'cmshop_repair_request';

	private const NONCE        = 'cmshop_repair_request';
	private const ACTION       = 'cmshop_repair_request';
	private const HONEYPOT     = 'cmshop_website';
	private const DONE_ARG     = 'cmshop_booked';
	private const MAX_BOOKINGS = 5;
	private const WINDOW       = HOUR_IN_SECONDS;

	/**
	 * Repairs.
	 *
	 * @var Cmshop_Repair_Repository
	 */
	private $repairs;

	/**
	 * Customers.
	 *
	 * @var Cmshop_Customer_Repository
	 */
	private $customers;

	/**
	 * Result of a submission in this request (errors and values to show again).
	 *
	 * @var array{values: array, errors: array, error: string}
	 */
	private $outcome = array(
		'values' => array(),
		'errors' => array(),
		'error'  => '',
	);

	/**
	 * Constructor.
	 *
	 * @param Cmshop_Repair_Repository   $repairs   Repairs.
	 * @param Cmshop_Customer_Repository $customers Customers.
	 */
	public function __construct( Cmshop_Repair_Repository $repairs, Cmshop_Customer_Repository $customers ) {
		$this->repairs   = $repairs;
		$this->customers = $customers;
	}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	/**
	 * Process a booking before the page is sent.
	 */
	public function handle(): void {
		if ( ! isset( $_POST['cmshop_action'] ) || self::ACTION !== $_POST['cmshop_action'] ) {
			return;
		}

		if ( ! isset( $_POST['_cmshop_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_cmshop_nonce'] ) ), self::NONCE ) ) {
			$this->outcome['error'] = __( 'This page has expired. Please reload it and try again.', 'codereyes-mobile-shop' );
			return;
		}

		$values = $this->posted();

		// Bots fill in every field, including the hidden one: save nothing.
		if ( '' !== trim( sanitize_text_field( wp_unslash( $_POST[ self::HONEYPOT ] ?? '' ) ) ) ) {
			return;
		}

		$result = Cmshop_Validator::repair_request( $values );

		if ( ! $result->is_valid() ) {
			$this->outcome = array_merge(
				$this->outcome,
				array(
					'values' => $values,
					'errors' => $result->errors(),
				)
			);
			return;
		}

		if ( Cmshop_Rate_Limit::exceeded( 'repair_request', self::MAX_BOOKINGS, self::WINDOW ) ) {
			$this->outcome = array_merge(
				$this->outcome,
				array(
					'values' => $values,
					'error'  => __( 'Too many bookings from this connection. Please call us instead.', 'codereyes-mobile-shop' ),
				)
			);
			return;
		}

		try {
			$repair = $this->book( $result->data() );
		} catch ( RuntimeException $error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side log of a database failure.
			error_log( 'Codereyes Mobile Shop: repair booking failed: ' . $error->getMessage() );
			$this->outcome = array_merge(
				$this->outcome,
				array(
					'values' => $values,
					'error'  => __( 'Sorry, we could not save your booking. Please try again or call us.', 'codereyes-mobile-shop' ),
				)
			);
			return;
		}

		$this->notify( $repair, $result->data() );
		$this->redirect( (string) $repair['ticket_code'] );
	}

	/**
	 * Shortcode output.
	 *
	 * @param array|string $atts accent, radius.
	 * @return string
	 */
	public function render( $atts = array() ): string {
		wp_enqueue_style( 'cmshop-frontend' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Shows the code from our own redirect; nothing is changed.
		$booked = isset( $_GET[ self::DONE_ARG ] ) ? Cmshop_Code::normalize_ticket( sanitize_text_field( wp_unslash( $_GET[ self::DONE_ARG ] ) ) ) : '';

		$view = array_merge(
			$this->outcome,
			array(
				'booked'   => Cmshop_Code::is_ticket( $booked ) ? $booked : '',
				'nonce'    => self::NONCE,
				'action'   => self::ACTION,
				'honeypot' => self::HONEYPOT,
				'today'    => current_time( 'Y-m-d' ),
				'style'    => Cmshop_Style::css_vars( is_array( $atts ) ? $atts : array() ),
			)
		);

		ob_start();
		include CMSHOP_DIR . 'views/frontend/repair-request.php';

		return (string) ob_get_clean();
	}

	/**
	 * Save the job (and the customer, matched by phone).
	 *
	 * @param array $data Validated booking.
	 * @return array Repair row.
	 * @throws RuntimeException When the job cannot be saved.
	 */
	private function book( array $data ): array {
		$customer = $this->customers->find_or_create( $data['customer_name'], $data['customer_phone'], $data['customer_email'] );
		$notes    = array( __( 'Booked on the website.', 'codereyes-mobile-shop' ) );

		if ( null !== $data['preferred_date'] ) {
			/* translators: %s: date the customer plans to bring the phone. */
			$notes[] = sprintf( __( 'Preferred drop-off: %s.', 'codereyes-mobile-shop' ), mysql2date( (string) get_option( 'date_format' ), $data['preferred_date'] ) );
		}

		if ( '' !== $data['customer_email'] ) {
			/* translators: %s: customer email. */
			$notes[] = sprintf( __( 'Email: %s', 'codereyes-mobile-shop' ), $data['customer_email'] );
		}

		$id = $this->repairs->insert_with_ticket(
			array(
				'customer_id'    => (int) $customer['id'],
				'customer_name'  => $data['customer_name'],
				'customer_phone' => $data['customer_phone'],
				'device'         => $data['device'],
				'imei'           => $data['imei'],
				'fault'          => $data['fault'],
				'estimate'       => $data['estimate'],
				'deposit'        => $data['deposit'],
				'status'         => $data['status'],
				'internal_notes' => implode( ' ', $notes ),
				'created_by'     => 0,
			)
		);

		$repair = $this->repairs->find( $id );

		if ( null === $repair ) {
			throw new RuntimeException( 'Saved repair not found.' );
		}

		return $repair;
	}

	/**
	 * Email the shop, and the customer when they gave an email address.
	 *
	 * A failed email does not undo the booking: the job is already on the
	 * Repairs screen.
	 *
	 * @param array $repair Repair row.
	 * @param array $data   Validated booking.
	 */
	private function notify( array $repair, array $data ): void {
		$shop = (string) Cmshop_Settings::get( 'shop_name' );
		$code = (string) $repair['ticket_code'];

		$lines = array(
			/* translators: %s: ticket code. */
			sprintf( __( 'Ticket: %s', 'codereyes-mobile-shop' ), $code ),
			/* translators: 1: name, 2: phone. */
			sprintf( __( 'Customer: %1$s, %2$s', 'codereyes-mobile-shop' ), $data['customer_name'], $data['customer_phone'] ),
			/* translators: %s: phone model. */
			sprintf( __( 'Device: %s', 'codereyes-mobile-shop' ), $data['device'] ),
			/* translators: %s: fault description. */
			sprintf( __( 'Fault: %s', 'codereyes-mobile-shop' ), $data['fault'] ),
			(string) $repair['internal_notes'],
			'',
			add_query_arg(
				array(
					'page'   => 'cmshop-repairs',
					'action' => 'edit',
					'id'     => (int) $repair['id'],
				),
				admin_url( 'admin.php' )
			),
		);

		wp_mail(
			(string) get_option( 'admin_email' ),
			/* translators: 1: shop name, 2: ticket code. */
			sprintf( __( '[%1$s] New repair booking %2$s', 'codereyes-mobile-shop' ), $shop, $code ),
			implode( "\n", $lines )
		);

		if ( '' === $data['customer_email'] ) {
			return;
		}

		wp_mail(
			$data['customer_email'],
			/* translators: 1: shop name, 2: ticket code. */
			sprintf( __( 'Your repair booking with %1$s: %2$s', 'codereyes-mobile-shop' ), $shop, $code ),
			implode(
				"\n",
				array(
					/* translators: %s: customer name. */
					sprintf( __( 'Hello %s,', 'codereyes-mobile-shop' ), $data['customer_name'] ),
					'',
					/* translators: %s: phone model. */
					sprintf( __( 'We have your repair booking for your %s.', 'codereyes-mobile-shop' ), $data['device'] ),
					/* translators: %s: ticket code. */
					sprintf( __( 'Your ticket number is %s. Bring the phone to the shop and quote this number. You can check progress on our website with the ticket number and the last 4 digits of your phone number.', 'codereyes-mobile-shop' ), $code ),
					'',
					$shop,
					(string) Cmshop_Settings::get( 'shop_phone' ),
				)
			)
		);
	}

	/**
	 * Submitted fields (raw strings; validation cleans them).
	 *
	 * @return array<string,string>
	 */
	private function posted(): array {
		$values = array();

		foreach ( array( 'customer_name', 'customer_phone', 'customer_email', 'device', 'imei', 'preferred_date' ) as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle().
			$values[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle().
		$values['fault'] = isset( $_POST['fault'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fault'] ) ) : '';

		return $values;
	}

	/**
	 * Back to the same page, showing the ticket code.
	 *
	 * @param string $code Ticket code.
	 */
	private function redirect( string $code ): void {
		// wp_get_referer() is empty when a form posts back to its own page, so
		// use the page being viewed, then the raw referrer.
		$referer = wp_get_raw_referer();
		$back    = is_singular() ? get_permalink() : ( $referer ? $referer : home_url( '/' ) );
		$page    = remove_query_arg( self::DONE_ARG, (string) $back );

		wp_safe_redirect( add_query_arg( self::DONE_ARG, rawurlencode( $code ), $page ) . '#cmshop-booking' );
		exit;
	}
}
