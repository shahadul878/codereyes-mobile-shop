<?php
/**
 * Small rendering helpers shared by the admin templates.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Escaped form fields, links, badges and notices.
 */
final class Cmshop_View {

	/**
	 * Admin page URL.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Query args.
	 * @return string
	 */
	public static function url( string $page, array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * URL of an admin-post action with its nonce.
	 *
	 * @param string $action Action name (without the cmshop_ prefix).
	 * @param array  $args   Query args.
	 * @return string
	 */
	public static function action_url( string $action, array $args = array() ): string {
		$url = add_query_arg( array_merge( array( 'action' => 'cmshop_' . $action ), $args ), admin_url( 'admin-post.php' ) );

		return wp_nonce_url( $url, 'cmshop_' . $action );
	}

	/**
	 * Open an admin-post form.
	 *
	 * @param string $action Action name (without the cmshop_ prefix).
	 */
	public static function form_open( string $action ): void {
		printf( '<form method="post" action="%s" class="cmshop-form" novalidate>', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( 'cmshop_' . $action ) );
		wp_nonce_field( 'cmshop_' . $action );
	}

	/**
	 * Text-like input with label and error.
	 *
	 * @param string $name   Field name.
	 * @param string $label  Label.
	 * @param array  $values Current values.
	 * @param array  $errors Errors.
	 * @param array  $opts   type, required, placeholder, help, inputmode, class.
	 */
	public static function input( string $name, string $label, array $values, array $errors, array $opts = array() ): void {
		self::field_open( $name, $label, $opts );
		printf(
			'<input type="%1$s" id="cmshop-%2$s" name="%2$s" value="%3$s" class="%4$s" placeholder="%5$s" inputmode="%6$s"%7$s%8$s%9$s>',
			esc_attr( $opts['type'] ?? 'text' ),
			esc_attr( $name ),
			esc_attr( (string) ( $values[ $name ] ?? '' ) ),
			esc_attr( $opts['class'] ?? 'regular-text' ),
			esc_attr( $opts['placeholder'] ?? '' ),
			esc_attr( $opts['inputmode'] ?? 'text' ),
			empty( $opts['required'] ) ? '' : ' required aria-required="true"',
			isset( $errors[ $name ] ) ? ' aria-invalid="true" aria-describedby="cmshop-' . esc_attr( $name ) . '-error"' : '',
			isset( $opts['list'] ) ? ' list="' . esc_attr( $opts['list'] ) . '" autocomplete="off"' : ''
		);
		self::field_close( $name, $errors, $opts );
	}

	/**
	 * A <datalist> of suggestions for an input's "list" option.
	 *
	 * @param string   $id      Datalist ID.
	 * @param string[] $options Suggestions.
	 */
	public static function datalist( string $id, array $options ): void {
		printf( '<datalist id="%s">', esc_attr( $id ) );

		foreach ( $options as $option ) {
			printf( '<option value="%s"></option>', esc_attr( $option ) );
		}

		echo '</datalist>';
	}

	/**
	 * Select with label and error.
	 *
	 * @param string               $name    Field name.
	 * @param string               $label   Label.
	 * @param array<string,string> $choices Value => label.
	 * @param array                $values  Current values.
	 * @param array                $errors  Errors.
	 * @param array                $opts    help.
	 */
	public static function select( string $name, string $label, array $choices, array $values, array $errors, array $opts = array() ): void {
		self::field_open( $name, $label, $opts );
		printf( '<select id="cmshop-%1$s" name="%1$s">', esc_attr( $name ) );

		foreach ( $choices as $value => $text ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $value ),
				selected( (string) ( $values[ $name ] ?? '' ), (string) $value, false ),
				esc_html( $text )
			);
		}

		echo '</select>';
		self::field_close( $name, $errors, $opts );
	}

	/**
	 * Textarea with label and error.
	 *
	 * @param string $name   Field name.
	 * @param string $label  Label.
	 * @param array  $values Current values.
	 * @param array  $errors Errors.
	 * @param array  $opts   required, help, rows.
	 */
	public static function textarea( string $name, string $label, array $values, array $errors, array $opts = array() ): void {
		self::field_open( $name, $label, $opts );
		printf(
			'<textarea id="cmshop-%1$s" name="%1$s" rows="%2$d" class="large-text"%3$s>%4$s</textarea>',
			esc_attr( $name ),
			(int) ( $opts['rows'] ?? 3 ),
			empty( $opts['required'] ) ? '' : ' required aria-required="true"',
			esc_textarea( (string) ( $values[ $name ] ?? '' ) )
		);
		self::field_close( $name, $errors, $opts );
	}

	/**
	 * Error summary at the top of a form.
	 *
	 * @param array $errors Errors.
	 */
	public static function error_summary( array $errors ): void {
		if ( array() === $errors ) {
			return;
		}

		echo '<div class="notice notice-error cmshop-error-summary" role="alert"><p><strong>';
		esc_html_e( 'Please fix the highlighted fields.', 'codereyes-mobile-shop' );
		echo '</strong></p><ul>';

		foreach ( $errors as $field => $message ) {
			printf( '<li><a href="#cmshop-%s">%s</a></li>', esc_attr( $field ), esc_html( $message ) );
		}

		echo '</ul></div>';
	}

	/**
	 * Coloured status badge.
	 *
	 * @param string $text     Label.
	 * @param string $modifier Status key (used as CSS modifier).
	 * @return string HTML.
	 */
	public static function badge( string $text, string $modifier ): string {
		return sprintf( '<span class="cmshop-badge cmshop-badge--%s">%s</span>', esc_attr( sanitize_html_class( $modifier ) ), esc_html( $text ) );
	}

	/**
	 * Local date and time for display.
	 *
	 * @param string|null $mysql MySQL datetime.
	 * @param bool        $time  Include time.
	 * @return string
	 */
	public static function date( ?string $mysql, bool $time = true ): string {
		if ( null === $mysql || '' === $mysql ) {
			return '—';
		}

		$format = get_option( 'date_format' ) . ( $time ? ' ' . get_option( 'time_format' ) : '' );

		return (string) mysql2date( $format, $mysql );
	}

	/**
	 * Print the notice named in the URL after a redirect.
	 */
	public static function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only; the code is matched against a fixed list.
		$code     = isset( $_GET['cmshop_notice'] ) ? sanitize_key( wp_unslash( $_GET['cmshop_notice'] ) ) : '';
		$messages = self::notices();

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		list( $type, $message ) = $messages[ $code ];

		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}

	/**
	 * Known notices: code => [ type, message ].
	 *
	 * @return array<string,array{0: string, 1: string}>
	 */
	private static function notices(): array {
		return array(
			'item_saved'       => array( 'success', __( 'Stock item saved.', 'codereyes-mobile-shop' ) ),
			'item_deleted'     => array( 'success', __( 'Stock item deleted.', 'codereyes-mobile-shop' ) ),
			'item_has_sales'   => array( 'error', __( 'This item has sales, so it cannot be deleted. Its sales history is kept.', 'codereyes-mobile-shop' ) ),
			'published'        => array( 'success', __( 'Item published to your online shop.', 'codereyes-mobile-shop' ) ),
			'unpublished'      => array( 'success', __( 'Item removed from your online shop.', 'codereyes-mobile-shop' ) ),
			'model_published'  => array( 'success', __( 'Model published: customers choose storage and colour, and stock follows your shop.', 'codereyes-mobile-shop' ) ),
			'model_removed'    => array( 'success', __( 'Model removed from your online shop. Its phones are still in stock.', 'codereyes-mobile-shop' ) ),
			'publish_failed'   => array( 'error', __( 'The item could not be published. Check that WooCommerce is active.', 'codereyes-mobile-shop' ) ),
			'customer_saved'   => array( 'success', __( 'Customer saved.', 'codereyes-mobile-shop' ) ),
			'customer_deleted' => array( 'success', __( 'Customer deleted.', 'codereyes-mobile-shop' ) ),
			'customer_in_use'  => array( 'error', __( 'This customer has sales or repairs, so they cannot be deleted.', 'codereyes-mobile-shop' ) ),
			'repair_saved'     => array( 'success', __( 'Repair job saved.', 'codereyes-mobile-shop' ) ),
			'repair_deleted'   => array( 'success', __( 'Repair job deleted.', 'codereyes-mobile-shop' ) ),
			'sale_voided'      => array( 'success', __( 'Sale voided. Its items are back in stock.', 'codereyes-mobile-shop' ) ),
			'sale_not_voided'  => array( 'error', __( 'Only completed sales can be voided.', 'codereyes-mobile-shop' ) ),
			'not_found'        => array( 'error', __( 'That record no longer exists.', 'codereyes-mobile-shop' ) ),
			'shift_opened'     => array( 'success', __( 'Shift opened. Good selling!', 'codereyes-mobile-shop' ) ),
			'petty_saved'      => array( 'success', __( 'Cash movement recorded.', 'codereyes-mobile-shop' ) ),
			'shift_closed'     => array( 'success', __( 'Shift closed. Here is the end-of-day report.', 'codereyes-mobile-shop' ) ),
			'items_returned'   => array( 'success', __( 'Return recorded. Stock and money are updated.', 'codereyes-mobile-shop' ) ),
			'due_collected'    => array( 'success', __( 'Payment received and added to the customer\'s account.', 'codereyes-mobile-shop' ) ),
			'db_error'         => array( 'error', __( 'Something went wrong while saving. Nothing was changed. Please try again.', 'codereyes-mobile-shop' ) ),
		);
	}

	/**
	 * Label, wrapper and required marker.
	 *
	 * @param string $name  Field name.
	 * @param string $label Label.
	 * @param array  $opts  required.
	 */
	private static function field_open( string $name, string $label, array $opts ): void {
		printf( '<div class="cmshop-field cmshop-field--%s">', esc_attr( $name ) );
		printf(
			'<label for="cmshop-%s">%s%s</label>',
			esc_attr( $name ),
			esc_html( $label ),
			empty( $opts['required'] ) ? '' : ' <span class="cmshop-required" aria-hidden="true">*</span>'
		);
	}

	/**
	 * Help text, error and closing wrapper.
	 *
	 * @param string $name   Field name.
	 * @param array  $errors Errors.
	 * @param array  $opts   help.
	 */
	private static function field_close( string $name, array $errors, array $opts ): void {
		if ( ! empty( $opts['help'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $opts['help'] ) );
		}

		if ( isset( $errors[ $name ] ) ) {
			printf( '<p class="cmshop-field-error" id="cmshop-%s-error">%s</p>', esc_attr( $name ), esc_html( $errors[ $name ] ) );
		}

		echo '</div>';
	}
}
