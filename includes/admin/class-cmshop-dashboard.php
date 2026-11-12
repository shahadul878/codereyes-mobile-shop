<?php
/**
 * Everything the dashboard shows, gathered in one place.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Collects today's figures, the 30-day series, the payment split, best
 * sellers, slow-moving phones and today's to-do list.
 */
final class Cmshop_Dashboard {

	public const DAYS = 30;

	private const SLOW_DAYS = 60;
	private const TOP       = 5;
	private const SLOW_SHOW = 4;

	/**
	 * Payment method colours (cash, bKash pink, card violet...).
	 */
	public const METHOD_COLORS = array(
		'cash'           => '#0f9d8a',
		'mobile_banking' => '#db2777',
		'card'           => '#7c3aed',
		'bank_transfer'  => '#d97706',
		'online'         => '#2563eb',
	);

	/**
	 * Dashboard data.
	 *
	 * @return array
	 */
	public static function data(): array {
		$today     = current_time( 'Y-m-d' );
		$tomorrow  = self::shift( $today, 1 );
		$yesterday = self::shift( $today, -1 );
		$start     = self::shift( $today, 1 - self::DAYS );
		$sales     = Cmshop_Plugin::sales();
		$payments  = Cmshop_Plugin::payments();
		$stock     = Cmshop_Plugin::items()->stock_summary( (int) Cmshop_Settings::get( 'low_stock' ) );
		$repairs   = Cmshop_Plugin::repairs()->open_counts();
		$now       = $sales->totals( $today . ' 00:00:00', $tomorrow . ' 00:00:00' );
		$before    = $sales->totals( $yesterday . ' 00:00:00', $today . ' 00:00:00' );
		$series    = Cmshop_Dashboard_Math::fill_days( $sales->daily_totals( $start . ' 00:00:00', $tomorrow . ' 00:00:00' ), $start, self::DAYS );
		$dues      = $sales->dues_summary();
		$slow      = Cmshop_Plugin::items()->slow_movers( self::shift( $today, -self::SLOW_DAYS ) . ' 00:00:00', self::SLOW_SHOW );

		return array(
			'today'     => $now,
			'change'    => Cmshop_Dashboard_Math::change( Cmshop_Money::to_cents( $now['total'] ), Cmshop_Money::to_cents( $before['total'] ) ),
			'margin'    => Cmshop_Dashboard_Math::margin( Cmshop_Money::to_cents( $now['profit'] ), Cmshop_Money::to_cents( $now['total'] ) ),
			'drawer'    => $payments->totals_by_method( $today . ' 00:00:00', $tomorrow . ' 00:00:00' ),
			'dues'      => $dues,
			'series'    => Cmshop_Dashboard_Math::bar_heights( $series ),
			'period'    => array(
				'total'  => array_sum( array_column( $series, 'total' ) ),
				'profit' => array_sum( array_column( $series, 'profit' ) ),
				'sales'  => array_sum( array_column( $series, 'sales' ) ),
			),
			'methods'   => Cmshop_Dashboard_Math::donut( $payments->totals_by_method( $start . ' 00:00:00', $tomorrow . ' 00:00:00' ) ),
			'top'       => $sales->top_sellers( $start . ' 00:00:00', $tomorrow . ' 00:00:00', Cmshop_Installer::table( 'items' ), self::TOP ),
			'slow'      => $slow,
			'slow_days' => self::SLOW_DAYS,
			'stock'     => $stock,
			'repairs'   => $repairs,
			'tasks'     => self::tasks( $repairs, $stock, $dues, Cmshop_Plugin::repairs()->overdue_count( $today ) ),
			'recent'    => $sales->recent( 6 ),
		);
	}

	/**
	 * Today's to-do list: only what needs doing.
	 *
	 * @param array<string,int> $repairs Open repairs by status.
	 * @param array             $stock   Stock summary.
	 * @param array             $dues    Dues summary.
	 * @param int               $overdue Overdue repairs.
	 * @return array[] Tasks of icon, text, url, tone.
	 */
	private static function tasks( array $repairs, array $stock, array $dues, int $overdue ): array {
		$ready  = (int) ( $repairs[ Cmshop_Repair_Status::READY ] ?? 0 );
		$booked = (int) ( $repairs[ Cmshop_Repair_Status::REQUESTED ] ?? 0 );
		$low    = count( $stock['low'] );
		$tasks  = array();

		if ( $ready > 0 ) {
			$tasks[] = array(
				'icon' => 'phone',
				/* translators: %d: repairs ready to collect. */
				'text' => sprintf( _n( '%d repair is ready: call the customer', '%d repairs are ready: call the customers', $ready, 'codereyes-mobile-shop' ), $ready ),
				'url'  => Cmshop_View::url( 'cmshop-repairs', array( 'status' => Cmshop_Repair_Status::READY ) ),
				'tone' => 'good',
			);
		}

		if ( $booked > 0 ) {
			$tasks[] = array(
				'icon' => 'calendar-alt',
				/* translators: %d: repairs booked on the website. */
				'text' => sprintf( _n( '%d repair booked online: confirm with the customer', '%d repairs booked online: confirm with the customers', $booked, 'codereyes-mobile-shop' ), $booked ),
				'url'  => Cmshop_View::url( 'cmshop-repairs', array( 'status' => Cmshop_Repair_Status::REQUESTED ) ),
				'tone' => 'info',
			);
		}

		if ( $overdue > 0 ) {
			$tasks[] = array(
				'icon' => 'clock',
				/* translators: %d: overdue repairs. */
				'text' => sprintf( _n( '%d repair is past its promised date', '%d repairs are past their promised date', $overdue, 'codereyes-mobile-shop' ), $overdue ),
				'url'  => Cmshop_View::url( 'cmshop-repairs' ),
				'tone' => 'warn',
			);
		}

		if ( $low > 0 ) {
			$tasks[] = array(
				'icon' => 'cart',
				/* translators: %d: accessories running low. */
				'text' => sprintf( _n( 'Reorder %d accessory that is running low', 'Reorder %d accessories that are running low', $low, 'codereyes-mobile-shop' ), $low ),
				'url'  => Cmshop_View::url( 'cmshop-items', array( 'item_type' => 'accessory' ) ),
				'tone' => 'warn',
			);
		}

		if ( $dues['sales'] > 0 ) {
			$tasks[] = array(
				'icon' => 'money-alt',
				/* translators: 1: amount owed, 2: number of unpaid sales. */
				'text' => sprintf( _n( 'Collect %1$s owed on %2$d sale', 'Collect %1$s owed on %2$d sales', $dues['sales'], 'codereyes-mobile-shop' ), Cmshop_Settings::money( $dues['amount'] ), $dues['sales'] ),
				'url'  => Cmshop_View::url( 'cmshop-dues' ),
				'tone' => 'warn',
			);
		}

		$tasks[] = array(
			'icon' => 'lock',
			'text' => __( 'Count the cash drawer before closing', 'codereyes-mobile-shop' ),
			'url'  => Cmshop_View::url( 'cmshop-sales' ),
			'tone' => 'muted',
		);

		return $tasks;
	}

	/**
	 * A date some days away.
	 *
	 * @param string $date Y-m-d.
	 * @param int    $days Days to add (negative to go back).
	 * @return string
	 */
	private static function shift( string $date, int $days ): string {
		return ( new DateTimeImmutable( $date ) )->modify( ( $days >= 0 ? '+' : '' ) . $days . ' days' )->format( 'Y-m-d' );
	}
}
