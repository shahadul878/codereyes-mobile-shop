<?php
/**
 * Data changes that go with schema upgrades.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Runs once per upgrade, after the tables are created or altered.
 */
final class Cmshop_Migrations {

	/**
	 * Bring data up to date from the schema version that was installed.
	 *
	 * @param string $from Previously installed schema version ('' on a fresh install).
	 */
	public static function run( string $from ): void {
		if ( '' === $from ) {
			return;
		}

		if ( version_compare( $from, '1.2.0', '<' ) ) {
			self::backfill_payments();
		}
	}

	/**
	 * Sales made before the payments ledger get one entry for what was paid,
	 * and voided sales a matching refund, so cash reports add up.
	 */
	private static function backfill_payments(): void {
		global $wpdb;

		$sales    = Cmshop_Installer::table( 'sales' );
		$payments = Cmshop_Installer::table( 'payments' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-off upgrade of the plugin's own tables.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (sale_id, customer_id, kind, method, amount, created_by, created_at, updated_at)
				SELECT s.id, s.customer_id, %s, s.payment_method, LEAST(s.amount_paid, s.total), s.created_by, s.created_at, s.created_at
				FROM %i s
				WHERE LEAST(s.amount_paid, s.total) > 0
				AND NOT EXISTS (SELECT 1 FROM %i p WHERE p.sale_id = s.id AND p.kind = %s)',
				$payments,
				'sale',
				$sales,
				$payments,
				'sale'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (sale_id, customer_id, kind, method, amount, created_by, created_at, updated_at)
				SELECT s.id, s.customer_id, %s, s.payment_method, -LEAST(s.amount_paid, s.total), s.created_by, s.updated_at, s.updated_at
				FROM %i s
				WHERE s.status = %s AND LEAST(s.amount_paid, s.total) > 0
				AND NOT EXISTS (SELECT 1 FROM %i p WHERE p.sale_id = s.id AND p.kind = %s)',
				$payments,
				'refund',
				$sales,
				'void',
				$payments,
				'refund'
			)
		);
		// phpcs:enable
	}
}
