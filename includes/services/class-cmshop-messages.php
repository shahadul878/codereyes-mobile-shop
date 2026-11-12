<?php
/**
 * Ready-written WhatsApp messages for receipts, repairs and dues.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Fills the shop's message templates from a sale, repair or customer and
 * returns a click-to-chat link. Never includes cost prices or profit.
 */
final class Cmshop_Messages {

	public const RECEIPT = 'wa_receipt';
	public const REPAIR  = 'wa_repair';
	public const READY   = 'wa_ready';
	public const DUE     = 'wa_due';

	/**
	 * Templates: setting key => [label, default text, placeholders].
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function templates(): array {
		return array(
			self::RECEIPT => array(
				__( 'Receipt after a sale', 'codereyes-mobile-shop' ),
				__( "Hi {name}, thank you for shopping at {shop}!\nInvoice {invoice} · {date}\n{items}\nTotal: *{total}*\nPaid: {paid}\n{due_line}\n{warranty_line}\n{footer}", 'codereyes-mobile-shop' ),
				'{name} {shop} {invoice} {date} {items} {total} {paid} {due_line} {warranty_line} {footer} {shop_phone}',
			),
			self::REPAIR  => array(
				__( 'Repair status update', 'codereyes-mobile-shop' ),
				__( "Hi {name}, an update from {shop} on your {device}:\nTicket {ticket} is now *{status}*.\n{note}\n{shop_phone}", 'codereyes-mobile-shop' ),
				'{name} {shop} {device} {ticket} {status} {note} {shop_phone}',
			),
			self::READY   => array(
				__( 'Repair ready to collect', 'codereyes-mobile-shop' ),
				__( "Hi {name}, good news! Your {device} is repaired and ready to collect from {shop}.\nTicket: {ticket}\n{balance_line}\n{address}\n{shop_phone}", 'codereyes-mobile-shop' ),
				'{name} {shop} {device} {ticket} {balance_line} {address} {shop_phone}',
			),
			self::DUE     => array(
				__( 'Payment reminder', 'codereyes-mobile-shop' ),
				__( "Hi {name}, a friendly reminder from {shop}.\nYou have *{owed}* still to pay.\n{overdue_line}\n{next_line}\nThank you! {shop_phone}", 'codereyes-mobile-shop' ),
				'{name} {shop} {owed} {overdue_line} {next_line} {shop_phone}',
			),
		);
	}

	/**
	 * The shop's own template, or the default.
	 *
	 * @param string $key Template key.
	 * @return string
	 */
	public static function template( string $key ): string {
		$saved = trim( (string) Cmshop_Settings::get( $key ) );

		return '' === $saved ? self::templates()[ $key ][1] : $saved;
	}

	/**
	 * Receipt for a sale.
	 *
	 * @param array   $sale     Sale row.
	 * @param array[] $lines    Sale lines.
	 * @param string  $customer Customer name ('' for walk-in).
	 * @param string  $phone    Customer phone ('' for walk-in).
	 * @return string Link.
	 */
	public static function receipt( array $sale, array $lines, string $customer, string $phone ): string {
		$owed = Cmshop_Money::to_cents( (string) $sale['total'] ) - Cmshop_Money::to_cents( (string) $sale['amount_paid'] );
		$rows = array();

		foreach ( $lines as $line ) {
			$quantity = (int) $line['quantity'] - (int) ( $line['returned_qty'] ?? 0 );

			if ( $quantity > 0 ) {
				$rows[] = array( (string) $line['description'], $quantity, self::money( (string) $line['line_total'] ) );
			}
		}

		$vars = array_merge(
			self::shop_vars(),
			array(
				'name'          => self::name( $customer ),
				'invoice'       => (string) $sale['invoice_no'],
				'date'          => (string) mysql2date( (string) get_option( 'date_format' ), (string) $sale['created_at'] ),
				'items'         => Cmshop_Whatsapp::bullets( $rows ),
				'total'         => self::money( (string) $sale['total'] ),
				'paid'          => self::money( (string) $sale['amount_paid'] ),
				/* translators: %s: amount still owed. */
				'due_line'      => $owed > 0 ? sprintf( __( 'Still to pay: %s', 'codereyes-mobile-shop' ), self::money( Cmshop_Money::from_cents( $owed ) ) ) : '',
				'warranty_line' => self::warranty_line( $sale, $lines ),
				'footer'        => (string) Cmshop_Settings::get( 'receipt_footer' ),
			)
		);

		return self::link( $phone, self::RECEIPT, $vars );
	}

	/**
	 * Repair update, or the "ready to collect" message when it is ready.
	 *
	 * @param array $repair Repair row.
	 * @return string Link.
	 */
	public static function repair( array $repair ): string {
		$is_ready = Cmshop_Repair_Status::READY === $repair['status'];
		$cost     = null === $repair['final_cost'] || '' === (string) $repair['final_cost'] ? (string) $repair['estimate'] : (string) $repair['final_cost'];
		$balance  = Cmshop_Money::to_cents( $cost ) - Cmshop_Money::to_cents( (string) $repair['deposit'] );
		$vars     = array_merge(
			self::shop_vars(),
			array(
				'name'         => self::name( (string) $repair['customer_name'] ),
				'device'       => (string) $repair['device'],
				'ticket'       => (string) $repair['ticket_code'],
				'status'       => Cmshop_Repair_Status::label( (string) $repair['status'] ),
				'note'         => (string) $repair['public_note'],
				/* translators: %s: amount to pay on collection. */
				'balance_line' => $balance > 0 ? sprintf( __( 'Amount to pay: %s', 'codereyes-mobile-shop' ), self::money( Cmshop_Money::from_cents( $balance ) ) ) : '',
			)
		);

		return self::link( (string) $repair['customer_phone'], $is_ready ? self::READY : self::REPAIR, $vars );
	}

	/**
	 * Payment reminder.
	 *
	 * @param array $debtor Row with name, phone, owed (amount), overdue (cents), next_date.
	 * @return string Link.
	 */
	public static function due( array $debtor ): string {
		$vars = array_merge(
			self::shop_vars(),
			array(
				'name'         => self::name( (string) $debtor['name'] ),
				'owed'         => self::money( (string) $debtor['owed'] ),
				/* translators: %s: overdue amount. */
				'overdue_line' => (int) $debtor['overdue'] > 0 ? sprintf( __( 'Overdue now: %s', 'codereyes-mobile-shop' ), self::money( Cmshop_Money::from_cents( (int) $debtor['overdue'] ) ) ) : '',
				/* translators: %s: date of the next installment. */
				'next_line'    => '' === (string) $debtor['next_date'] ? '' : sprintf( __( 'Next installment due: %s', 'codereyes-mobile-shop' ), (string) mysql2date( (string) get_option( 'date_format' ), (string) $debtor['next_date'] ) ),
			)
		);

		return self::link( (string) $debtor['phone'], self::DUE, $vars );
	}

	/**
	 * Placeholders every message can use.
	 *
	 * @return array<string,string>
	 */
	private static function shop_vars(): array {
		return array(
			'shop'       => (string) Cmshop_Settings::get( 'shop_name' ),
			'shop_phone' => (string) Cmshop_Settings::get( 'shop_phone' ),
			'address'    => (string) Cmshop_Settings::get( 'shop_address' ),
		);
	}

	/**
	 * "Warranty until 12 November 2027" for the longest phone warranty on the sale.
	 *
	 * @param array   $sale  Sale row.
	 * @param array[] $lines Sale lines.
	 * @return string
	 */
	private static function warranty_line( array $sale, array $lines ): string {
		$ends = '';

		foreach ( $lines as $line ) {
			if ( Cmshop_Item::TYPE_PHONE !== $line['item_type'] || (int) $line['quantity'] <= (int) ( $line['returned_qty'] ?? 0 ) ) {
				continue;
			}

			$item   = Cmshop_Plugin::items()->find( (int) $line['item_id'] );
			$days   = Cmshop_Warranty::days_for( (string) ( $item['grade'] ?? 'new' ), (int) Cmshop_Settings::get( 'warranty_new' ), (int) Cmshop_Settings::get( 'warranty_used' ) );
			$status = Cmshop_Warranty::status( (string) $sale['created_at'], $days, current_time( 'Y-m-d' ) );
			$ends   = $status['ends'] > $ends ? $status['ends'] : $ends;
		}

		/* translators: %s: warranty end date. */
		return '' === $ends ? '' : sprintf( __( 'Warranty until %s', 'codereyes-mobile-shop' ), (string) mysql2date( (string) get_option( 'date_format' ), $ends ) );
	}

	/**
	 * Customer name, or a friendly word for walk-ins.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function name( string $name ): string {
		return '' === trim( $name ) ? __( 'there', 'codereyes-mobile-shop' ) : trim( $name );
	}

	/**
	 * Amount in the shop currency.
	 *
	 * @param string $amount Amount.
	 * @return string
	 */
	private static function money( string $amount ): string {
		return Cmshop_Settings::money( $amount );
	}

	/**
	 * Fill a template and build the link.
	 *
	 * @param string               $phone Phone as stored.
	 * @param string               $key   Template key.
	 * @param array<string,string> $vars  Values.
	 * @return string
	 */
	private static function link( string $phone, string $key, array $vars ): string {
		$country = Cmshop_Whatsapp::country( (string) Cmshop_Settings::get( 'wa_country' ) );

		return Cmshop_Whatsapp::link( Cmshop_Whatsapp::phone( $phone, $country ), Cmshop_Whatsapp::fill( self::template( $key ), $vars ) );
	}
}
