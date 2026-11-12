<?php
/**
 * Shared plumbing for admin-post form handlers.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin_post_cmshop_{action} hooks, checks capability and nonce
 * before any handler runs, and redirects back with a notice code.
 */
abstract class Cmshop_Handler {

	/**
	 * Action name => method. Subclasses override.
	 */
	protected const ACTIONS = array();

	/**
	 * Hook in.
	 */
	public function register(): void {
		foreach ( static::ACTIONS as $action => $method ) {
			add_action(
				'admin_post_cmshop_' . $action,
				function () use ( $action, $method ) {
					$this->guard( $action );
					$this->handle( $method );
				}
			);
		}
	}

	/**
	 * Run one handler. Implemented in each subclass so its private handler
	 * methods are called from their own scope.
	 *
	 * @param string $method Handler method from ACTIONS.
	 */
	abstract protected function handle( string $method ): void;

	/**
	 * Stop unless the user may act and the nonce is valid.
	 *
	 * @param string $action Action name.
	 */
	protected function guard( string $action ): void {
		if ( ! current_user_can( Cmshop_Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'codereyes-mobile-shop' ), 403 );
		}

		check_admin_referer( 'cmshop_' . $action );
	}

	/**
	 * Submitted form fields, unslashed.
	 *
	 * @return array
	 */
	protected function posted(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in guard(); every field is sanitised by Cmshop_Input.
		$posted = wp_unslash( $_POST );

		unset( $posted['_wpnonce'], $posted['_wp_http_referer'], $posted['action'] );

		return is_array( $posted ) ? $posted : array();
	}

	/**
	 * ID from the form.
	 *
	 * @return int
	 */
	protected function posted_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked in guard().
		return isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	}

	/**
	 * ID from a link.
	 *
	 * @return int
	 */
	protected function query_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked in guard().
		return isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	}

	/**
	 * Server-side log of an unexpected failure.
	 *
	 * @param Throwable $error Error.
	 */
	protected function log( Throwable $error ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side log; users see a generic notice.
		error_log( 'Codereyes Mobile Shop: ' . $error->getMessage() );
	}

	/**
	 * Redirect to a plugin page and stop.
	 *
	 * @param string $page   Page slug.
	 * @param string $notice Notice code ('' for none).
	 * @param array  $args   Extra query args.
	 */
	protected function redirect( string $page, string $notice, array $args = array() ): void {
		$args = '' === $notice ? $args : array_merge( $args, array( 'cmshop_notice' => $notice ) );

		wp_safe_redirect( Cmshop_View::url( $page, $args ) );
		exit;
	}
}
