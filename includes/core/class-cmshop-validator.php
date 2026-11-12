<?php
/**
 * Validation rules for stock items, customers and repairs.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Turns submitted forms into rows ready for the database.
 */
final class Cmshop_Validator {

	private const MAX_ACCESSORY_QUANTITY = 100000;
	private const MAX_BATTERY_HEALTH     = 100;

	/**
	 * Stock item (phone or accessory).
	 *
	 * @param array $raw Submitted values.
	 * @return Cmshop_Validation_Result
	 */
	public static function item( array $raw ): Cmshop_Validation_Result {
		$in   = new Cmshop_Input( $raw );
		$type = $in->choice( 'item_type', array_keys( Cmshop_Item::types() ), Cmshop_Item::TYPE_PHONE );

		$data = Cmshop_Item::TYPE_PHONE === $type ? self::phone_fields( $in ) : self::accessory_fields( $in );

		$data = array_merge(
			$data,
			array(
				'item_type'   => $type,
				'cost_price'  => $in->money( 'cost_price' ),
				'sale_price'  => $in->money( 'sale_price' ),
				'supplier'    => $in->text( 'supplier', 150 ),
				'notes'       => $in->textarea( 'notes', 2000 ),
				'image_id'    => (int) $in->integer( 'image_id', 0, PHP_INT_MAX, 0 ),
				'gallery_ids' => Cmshop_Item::gallery_csv( Cmshop_Item::gallery_ids( $in->text( 'gallery_ids', 255 ) ) ),
				'status'      => $in->choice(
					'status',
					array( Cmshop_Item::STATUS_IN_STOCK, Cmshop_Item::STATUS_RESERVED ),
					Cmshop_Item::STATUS_IN_STOCK
				),
			)
		);

		$data['status'] = Cmshop_Item::status_for_quantity( $data['status'], $data['quantity'] );
		$data['name']   = Cmshop_Item::display_name( $data );

		return $in->result( $data );
	}

	/**
	 * Customer.
	 *
	 * @param array $raw Submitted values.
	 * @return Cmshop_Validation_Result
	 */
	public static function customer( array $raw ): Cmshop_Validation_Result {
		$in = new Cmshop_Input( $raw );

		return $in->result(
			array(
				'name'    => $in->text( 'name', 150, __( 'Enter the customer name.', 'codereyes-mobile-shop' ) ),
				'phone'   => $in->phone( 'phone', true ),
				'email'   => $in->email( 'email' ),
				'address' => $in->textarea( 'address', 500 ),
				'notes'   => $in->textarea( 'notes', 2000 ),
			)
		);
	}

	/**
	 * Repair job, including the customer's name and phone.
	 *
	 * @param array $raw Submitted values.
	 * @return Cmshop_Validation_Result
	 */
	public static function repair( array $raw ): Cmshop_Validation_Result {
		$in = new Cmshop_Input( $raw );

		$data = array(
			'customer_name'  => $in->text( 'customer_name', 150, __( 'Enter the customer name.', 'codereyes-mobile-shop' ) ),
			'customer_phone' => $in->phone( 'customer_phone', true ),
			'device'         => $in->text( 'device', 150, __( 'Enter the phone brand and model.', 'codereyes-mobile-shop' ) ),
			'imei'           => $in->imei( 'imei', false ),
			'fault'          => $in->textarea( 'fault', 2000, __( 'Describe the fault.', 'codereyes-mobile-shop' ) ),
			'items_received' => $in->text( 'items_received', 255 ),
			'estimate'       => $in->money( 'estimate' ),
			'deposit'        => $in->money( 'deposit' ),
			'final_cost'     => $in->optional_money( 'final_cost' ),
			'status'         => $in->choice( 'status', array_keys( Cmshop_Repair_Status::all() ), Cmshop_Repair_Status::RECEIVED ),
			'due_date'       => $in->date( 'due_date' ),
			'public_note'    => $in->text( 'public_note', 255 ),
			'internal_notes' => $in->textarea( 'internal_notes', 2000 ),
		);

		return $in->result( $data );
	}

	/**
	 * Repair booked by a customer on the website. It starts as "Booked online"
	 * with no estimate; staff price it when the phone is brought in.
	 *
	 * @param array $raw Submitted values.
	 * @return Cmshop_Validation_Result
	 */
	public static function repair_request( array $raw ): Cmshop_Validation_Result {
		$in = new Cmshop_Input( $raw );

		return $in->result(
			array(
				'customer_name'  => $in->text( 'customer_name', 150, __( 'Enter your name.', 'codereyes-mobile-shop' ) ),
				'customer_phone' => $in->phone( 'customer_phone', true ),
				'customer_email' => $in->email( 'customer_email' ),
				'device'         => $in->text( 'device', 150, __( 'Enter the phone brand and model.', 'codereyes-mobile-shop' ) ),
				'imei'           => $in->imei( 'imei', false ),
				'fault'          => $in->textarea( 'fault', 2000, __( 'Tell us what is wrong with the phone.', 'codereyes-mobile-shop' ) ),
				'preferred_date' => $in->date( 'preferred_date' ),
				'status'         => Cmshop_Repair_Status::REQUESTED,
				'estimate'       => '0.00',
				'deposit'        => '0.00',
			)
		);
	}

	/**
	 * Phone-specific fields. A phone is always quantity 1.
	 *
	 * @param Cmshop_Input $in Input.
	 * @return array
	 */
	private static function phone_fields( Cmshop_Input $in ): array {
		// Read in form order so the error summary lists fields top to bottom.
		$fields = array(
			'brand'   => $in->text( 'brand', 100, __( 'Enter the brand, e.g. Samsung.', 'codereyes-mobile-shop' ) ),
			'model'   => $in->text( 'model', 100, __( 'Enter the model, e.g. Galaxy A15.', 'codereyes-mobile-shop' ) ),
			'storage' => $in->text( 'storage', 50 ),
			'color'   => $in->text( 'color', 50 ),
			'imei1'   => $in->imei( 'imei1', true ),
			'imei2'   => $in->imei( 'imei2', false ),
		);

		if ( '' !== $fields['imei2'] && $fields['imei1'] === $fields['imei2'] ) {
			$in->error( 'imei2', __( 'IMEI 2 must be different from IMEI 1.', 'codereyes-mobile-shop' ) );
		}

		return array_merge(
			$fields,
			array(
				'grade'          => $in->choice( 'grade', array_keys( Cmshop_Item::grades() ), 'good' ),
				'serial'         => $in->text( 'serial', 50 ),
				'sku'            => '',
				'battery_health' => $in->integer( 'battery_health', 1, self::MAX_BATTERY_HEALTH, null ),
				'quantity'       => 1,
			)
		);
	}

	/**
	 * Accessory fields: tracked by quantity, no IMEI.
	 *
	 * @param Cmshop_Input $in Input.
	 * @return array
	 */
	private static function accessory_fields( Cmshop_Input $in ): array {
		return array(
			'name'           => $in->text( 'name', 200, __( 'Enter the accessory name, e.g. 20W USB-C charger.', 'codereyes-mobile-shop' ) ),
			'brand'          => $in->text( 'brand', 100 ),
			'model'          => '',
			'storage'        => '',
			'color'          => $in->text( 'color', 50 ),
			'grade'          => 'new',
			'imei1'          => '',
			'imei2'          => '',
			'serial'         => '',
			'sku'            => $in->text( 'sku', 64 ),
			'battery_health' => null,
			'quantity'       => (int) $in->integer( 'quantity', 0, self::MAX_ACCESSORY_QUANTITY, 1 ),
		);
	}
}
