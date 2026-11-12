<?php
/**
 * Customers.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table; nothing to cache across requests.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the customers table. Customers are matched by phone number.
 */
final class Cmshop_Customer_Repository extends Cmshop_Repository {

	private const SEARCH_LIMIT = 10;

	/**
	 * Lock a customer row for the rest of the transaction.
	 *
	 * @param int $id Customer ID.
	 * @return array|null
	 */
	public function lock( int $id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE id = %d FOR UPDATE', $this->table, $id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Add to (or, negative, take from) a customer's store credit. Call inside a transaction.
	 *
	 * @param array $customer Locked customer row.
	 * @param int   $delta    Change in cents.
	 * @throws RuntimeException When the update fails.
	 */
	public function adjust_credit( array $customer, int $delta ): void {
		$this->update( (int) $customer['id'], array( 'credit' => Cmshop_Money::from_cents( max( 0, Cmshop_Money::to_cents( (string) $customer['credit'] ) + $delta ) ) ) );
	}

	/**
	 * Customer with this phone number.
	 *
	 * @param string $phone Normalised phone number.
	 * @return array|null
	 */
	public function find_by_phone( string $phone ): ?array {
		if ( '' === $phone ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE phone = %s ORDER BY id ASC LIMIT 1', $this->table, $phone ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Existing customer with this phone, or a new one.
	 *
	 * @param string $name  Name.
	 * @param string $phone Normalised phone number.
	 * @param string $email Optional email.
	 * @return array Customer row.
	 */
	public function find_or_create( string $name, string $phone, string $email = '' ): array {
		$existing = $this->find_by_phone( $phone );

		if ( null !== $existing ) {
			return $existing;
		}

		$data = array(
			'name'  => $name,
			'phone' => $phone,
			'email' => $email,
		);

		return array_merge( $data, array( 'id' => $this->insert( $data ) ) );
	}

	/**
	 * Customers matching a name or phone (POS and repair forms).
	 *
	 * @param string $term Search term.
	 * @return array[]
	 */
	public function search( string $term ): array {
		$like = '%' . $this->db->esc_like( $term ) . '%';
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT id, name, phone, email FROM %i WHERE name LIKE %s OR phone LIKE %s ORDER BY name ASC LIMIT %d',
				$this->table,
				$like,
				$like,
				self::SEARCH_LIMIT
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
