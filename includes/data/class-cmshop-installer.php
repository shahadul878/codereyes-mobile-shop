<?php
/**
 * Creates the shop tables and grants the capability.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Installation and schema upgrades.
 */
final class Cmshop_Installer {

	public const DB_VERSION     = '1.6.0';
	public const OPTION         = 'cmshop_db_version';
	public const GRANTED_OPTION = 'cmshop_granted_roles';
	public const CAPABILITY     = 'manage_cmshop';

	/**
	 * Roles that get the capability.
	 */
	private const ROLES = array( 'administrator', 'shop_manager' );

	/**
	 * Table suffixes.
	 */
	public const TABLES = array( 'items', 'customers', 'sales', 'sale_items', 'repairs', 'payments', 'installments', 'shifts', 'suppliers', 'purchases' );

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		$installed = (string) get_option( self::OPTION, '' );

		self::install();
		Cmshop_Migrations::run( $installed );
	}

	/**
	 * Install when the stored schema version is behind (also covers new multisite sites).
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::OPTION, '' );

		if ( self::DB_VERSION !== $installed ) {
			self::install();
			Cmshop_Migrations::run( $installed );
		}
	}

	/**
	 * Full name of one of the plugin tables.
	 *
	 * @param string $name Table suffix from TABLES.
	 * @return string
	 */
	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . 'cmshop_' . $name;
	}

	/**
	 * Grant the capability to each default role once, including Shop Manager
	 * when WooCommerce is installed later. A role the owner removes it from
	 * on purpose is not given it back.
	 */
	public static function ensure_capability(): void {
		$granted = (array) get_option( self::GRANTED_OPTION, array() );
		$added   = array();

		foreach ( array_diff( self::ROLES, $granted ) as $role_name ) {
			$role = get_role( $role_name );

			if ( null !== $role ) {
				$role->add_cap( self::CAPABILITY );
				$added[] = $role_name;
			}
		}

		if ( array() !== $added ) {
			update_option( self::GRANTED_OPTION, array_values( array_merge( $granted, $added ) ), false );
		}
	}

	/**
	 * Remove the capability from every role (uninstall).
	 */
	public static function remove_capability(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			$role->remove_cap( self::CAPABILITY );
		}
	}

	/**
	 * Create or update the tables.
	 */
	private static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		foreach ( self::schema() as $name => $columns ) {
			// dbDelta needs two spaces after PRIMARY KEY and one field per line.
			dbDelta( 'CREATE TABLE ' . self::table( $name ) . " (\n{$columns}\n) {$charset};" );
		}

		self::ensure_capability();
		update_option( self::OPTION, self::DB_VERSION, false );
	}

	/**
	 * Column definitions per table.
	 *
	 * @return array<string,string>
	 */
	private static function schema(): array {
		return array(
			'items'        => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
item_type varchar(20) NOT NULL DEFAULT 'phone',
name varchar(255) NOT NULL DEFAULT '',
brand varchar(100) NOT NULL DEFAULT '',
model varchar(100) NOT NULL DEFAULT '',
storage varchar(50) NOT NULL DEFAULT '',
color varchar(50) NOT NULL DEFAULT '',
grade varchar(20) NOT NULL DEFAULT 'good',
imei1 varchar(15) NOT NULL DEFAULT '',
imei2 varchar(15) NOT NULL DEFAULT '',
serial varchar(50) NOT NULL DEFAULT '',
sku varchar(64) NOT NULL DEFAULT '',
battery_health tinyint(3) unsigned NULL,
quantity int(11) NOT NULL DEFAULT 1,
status varchar(20) NOT NULL DEFAULT 'in_stock',
cost_price decimal(12,2) NOT NULL DEFAULT '0.00',
sale_price decimal(12,2) NOT NULL DEFAULT '0.00',
supplier varchar(150) NOT NULL DEFAULT '',
purchase_id bigint(20) unsigned NOT NULL DEFAULT 0,
notes text NULL,
wc_product_id bigint(20) unsigned NOT NULL DEFAULT 0,
image_id bigint(20) unsigned NOT NULL DEFAULT 0,
gallery_ids varchar(255) NOT NULL DEFAULT '',
created_by bigint(20) unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY item_type (item_type),
KEY status (status),
KEY imei1 (imei1),
KEY imei2 (imei2),
KEY sku (sku),
KEY purchase_id (purchase_id),
KEY wc_product_id (wc_product_id)",
			'customers'    => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
name varchar(150) NOT NULL DEFAULT '',
phone varchar(20) NOT NULL DEFAULT '',
email varchar(100) NOT NULL DEFAULT '',
address text NULL,
notes text NULL,
credit decimal(12,2) NOT NULL DEFAULT '0.00',
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY phone (phone)",
			'sales'        => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
invoice_no varchar(40) NOT NULL DEFAULT '',
channel varchar(20) NOT NULL DEFAULT 'store',
status varchar(20) NOT NULL DEFAULT 'completed',
customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
customer_name varchar(150) NOT NULL DEFAULT '',
customer_phone varchar(20) NOT NULL DEFAULT '',
payment_method varchar(30) NOT NULL DEFAULT 'cash',
subtotal decimal(12,2) NOT NULL DEFAULT '0.00',
discount decimal(12,2) NOT NULL DEFAULT '0.00',
total decimal(12,2) NOT NULL DEFAULT '0.00',
amount_paid decimal(12,2) NOT NULL DEFAULT '0.00',
cost_total decimal(12,2) NOT NULL DEFAULT '0.00',
profit decimal(12,2) NOT NULL DEFAULT '0.00',
refunded decimal(12,2) NOT NULL DEFAULT '0.00',
refunded_cost decimal(12,2) NOT NULL DEFAULT '0.00',
wc_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
notes text NULL,
created_by bigint(20) unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY invoice_no (invoice_no),
KEY customer_id (customer_id),
KEY wc_order_id (wc_order_id),
KEY status_created (status,created_at)",
			'sale_items'   => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
sale_id bigint(20) unsigned NOT NULL,
item_id bigint(20) unsigned NOT NULL,
item_type varchar(20) NOT NULL DEFAULT 'phone',
description varchar(255) NOT NULL DEFAULT '',
imei varchar(15) NOT NULL DEFAULT '',
quantity int(11) NOT NULL DEFAULT 1,
unit_price decimal(12,2) NOT NULL DEFAULT '0.00',
unit_cost decimal(12,2) NOT NULL DEFAULT '0.00',
line_total decimal(12,2) NOT NULL DEFAULT '0.00',
returned_qty int(11) NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
KEY sale_id (sale_id),
KEY item_id (item_id)",
			'repairs'      => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
ticket_code varchar(20) NOT NULL DEFAULT '',
customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
customer_name varchar(150) NOT NULL DEFAULT '',
customer_phone varchar(20) NOT NULL DEFAULT '',
device varchar(150) NOT NULL DEFAULT '',
imei varchar(15) NOT NULL DEFAULT '',
fault text NULL,
items_received varchar(255) NOT NULL DEFAULT '',
estimate decimal(12,2) NOT NULL DEFAULT '0.00',
deposit decimal(12,2) NOT NULL DEFAULT '0.00',
final_cost decimal(12,2) NULL,
status varchar(20) NOT NULL DEFAULT 'received',
due_date date NULL,
public_note varchar(255) NOT NULL DEFAULT '',
internal_notes text NULL,
created_by bigint(20) unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
collected_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY ticket_code (ticket_code),
KEY customer_id (customer_id),
KEY status (status),
KEY imei (imei)",
			'payments'     => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
sale_id bigint(20) unsigned NOT NULL DEFAULT 0,
customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
shift_id bigint(20) unsigned NOT NULL DEFAULT 0,
supplier_id bigint(20) unsigned NOT NULL DEFAULT 0,
kind varchar(20) NOT NULL DEFAULT 'sale',
method varchar(30) NOT NULL DEFAULT 'cash',
amount decimal(12,2) NOT NULL DEFAULT '0.00',
reference varchar(100) NOT NULL DEFAULT '',
note varchar(255) NOT NULL DEFAULT '',
created_by bigint(20) unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY sale_id (sale_id),
KEY customer_id (customer_id),
KEY shift_id (shift_id),
KEY supplier_id (supplier_id),
KEY created_at (created_at)",
			'installments' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
sale_id bigint(20) unsigned NOT NULL,
customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
seq tinyint(3) unsigned NOT NULL DEFAULT 1,
due_date date NOT NULL,
amount decimal(12,2) NOT NULL DEFAULT '0.00',
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY sale_id (sale_id),
KEY customer_id (customer_id),
KEY due_date (due_date)",
			'shifts'       => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned NOT NULL DEFAULT 0,
opened_at datetime NOT NULL,
opening_float decimal(12,2) NOT NULL DEFAULT '0.00',
closed_at datetime NULL,
expected_cash decimal(12,2) NULL,
counted_cash decimal(12,2) NULL,
note varchar(255) NOT NULL DEFAULT '',
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY user_open (user_id,closed_at),
KEY opened_at (opened_at)",
			'suppliers'    => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
name varchar(150) NOT NULL DEFAULT '',
phone varchar(20) NOT NULL DEFAULT '',
address text NULL,
notes text NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY name (name),
KEY phone (phone)",
			'purchases'    => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
supplier_id bigint(20) unsigned NOT NULL DEFAULT 0,
ref_no varchar(60) NOT NULL DEFAULT '',
purchased_on date NOT NULL,
total decimal(12,2) NOT NULL DEFAULT '0.00',
paid decimal(12,2) NOT NULL DEFAULT '0.00',
units int(11) NOT NULL DEFAULT 0,
note varchar(255) NOT NULL DEFAULT '',
created_by bigint(20) unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY supplier_id (supplier_id),
KEY purchased_on (purchased_on)",
		);
	}
}
