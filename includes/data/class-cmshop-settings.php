<?php
/**
 * Shop settings stored in one option.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Read-only view of the settings plus the sanitiser used when saving.
 */
final class Cmshop_Settings {

	public const OPTION = 'cmshop_settings';

	private const MAX_LOW_STOCK = 1000;

	private const MAX_WARRANTY_DAYS = 3650;

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'shop_name'       => get_bloginfo( 'name' ),
			'shop_address'    => '',
			'shop_phone'      => '',
			'currency_symbol' => '৳',
			'invoice_prefix'  => 'INV-',
			'receipt_footer'  => __( 'Thank you for shopping with us.', 'codereyes-mobile-shop' ),
			'invoice_format'  => Cmshop_Invoice_Format::THERMAL,
			'thermal_width'   => '80',
			'repair_terms'    => __( 'Devices not collected within 60 days may be disposed of. We are not responsible for data loss.', 'codereyes-mobile-shop' ),
			'low_stock'       => 3,
			'warranty_new'    => 365,
			'warranty_used'   => 30,
			'woo_sync'        => 1,
		);
	}

	/**
	 * Payment methods offered at the counter.
	 *
	 * @return array<string,string>
	 */
	public static function payment_methods(): array {
		return array(
			'cash'           => __( 'Cash', 'codereyes-mobile-shop' ),
			'card'           => __( 'Card', 'codereyes-mobile-shop' ),
			'mobile_banking' => __( 'Mobile banking (bKash, Nagad, Rocket)', 'codereyes-mobile-shop' ),
			'bank_transfer'  => __( 'Bank transfer', 'codereyes-mobile-shop' ),
			'online'         => __( 'Online (website order)', 'codereyes-mobile-shop' ),
		);
	}

	/**
	 * All settings merged over the defaults.
	 *
	 * @return array
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * Currency symbol: WooCommerce's when it is active, otherwise the setting.
	 *
	 * @return string
	 */
	public static function currency_symbol(): string {
		if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
			return html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
		}

		return (string) self::get( 'currency_symbol' );
	}

	/**
	 * Format an amount in the shop currency.
	 *
	 * @param string|null $amount Amount.
	 * @return string
	 */
	public static function money( ?string $amount ): string {
		return Cmshop_Money::format( $amount, self::currency_symbol() );
	}

	/**
	 * Whether stock is synced with WooCommerce.
	 *
	 * @return bool
	 */
	public static function woo_enabled(): bool {
		return class_exists( 'WooCommerce' ) && 1 === (int) self::get( 'woo_sync' );
	}

	/**
	 * Sanitise submitted settings (register_setting callback).
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$text     = static function ( string $key, int $max ) use ( $input ): string {
			return mb_substr( sanitize_text_field( (string) ( $input[ $key ] ?? '' ) ), 0, $max );
		};
		$area     = static function ( string $key ) use ( $input ): string {
			return mb_substr( sanitize_textarea_field( (string) ( $input[ $key ] ?? '' ) ), 0, 1000 );
		};

		return array(
			'shop_name'       => $text( 'shop_name', 150 ),
			'shop_address'    => $area( 'shop_address' ),
			'shop_phone'      => $text( 'shop_phone', 50 ),
			'currency_symbol' => '' === $text( 'currency_symbol', 5 ) ? $defaults['currency_symbol'] : $text( 'currency_symbol', 5 ),
			'invoice_prefix'  => (string) preg_replace( '/[^A-Za-z0-9\-\/]/', '', $text( 'invoice_prefix', 10 ) ),
			'receipt_footer'  => $area( 'receipt_footer' ),
			'invoice_format'  => Cmshop_Invoice_Format::resolve( sanitize_key( (string) ( $input['invoice_format'] ?? '' ) ), Cmshop_Invoice_Format::THERMAL ),
			'thermal_width'   => Cmshop_Invoice_Format::thermal_width( (string) absint( $input['thermal_width'] ?? 0 ) ),
			'repair_terms'    => $area( 'repair_terms' ),
			'low_stock'       => max( 0, min( self::MAX_LOW_STOCK, absint( $input['low_stock'] ?? $defaults['low_stock'] ) ) ),
			'warranty_new'    => min( self::MAX_WARRANTY_DAYS, absint( $input['warranty_new'] ?? $defaults['warranty_new'] ) ),
			'warranty_used'   => min( self::MAX_WARRANTY_DAYS, absint( $input['warranty_used'] ?? $defaults['warranty_used'] ) ),
			'woo_sync'        => empty( $input['woo_sync'] ) ? 0 : 1,
		);
	}
}
