<?php
/**
 * Dues: customers who owe money, with overdue installments first to see.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop { debtors: array[] }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_debtors = $cmshop['debtors'];
$cmshop_owed    = array_sum( array_map( 'Cmshop_Money::to_cents', array_map( 'strval', array_column( $cmshop_debtors, 'owed' ) ) ) );
$cmshop_overdue = array_sum( array_column( $cmshop_debtors, 'overdue' ) );
$cmshop_format  = (string) get_option( 'date_format' );
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Dues', 'codereyes-mobile-shop' ); ?></h1>
	<hr class="wp-header-end">
	<?php Cmshop_View::notice(); ?>

	<div class="cmshop-dues-summary">
		<div class="cmshop-card">
			<h2><?php esc_html_e( 'Owed to you', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-dues-summary__value"><?php echo esc_html( Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_owed ) ) ); ?></p>
			<p>
				<?php
				/* translators: %d: number of customers. */
				echo esc_html( sprintf( _n( '%d customer', '%d customers', count( $cmshop_debtors ), 'codereyes-mobile-shop' ), count( $cmshop_debtors ) ) );
				?>
			</p>
		</div>
		<div class="cmshop-card<?php echo $cmshop_overdue > 0 ? ' is-alert' : ''; ?>">
			<h2><?php esc_html_e( 'Overdue installments', 'codereyes-mobile-shop' ); ?></h2>
			<p class="cmshop-dues-summary__value"><?php echo esc_html( Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_overdue ) ) ); ?></p>
			<p><?php esc_html_e( 'Call these customers first.', 'codereyes-mobile-shop' ); ?></p>
		</div>
	</div>

	<?php if ( array() === $cmshop_debtors ) : ?>
		<p class="cmshop-empty"><?php esc_html_e( 'Nobody owes you money. Sales on credit and installment plans show up here.', 'codereyes-mobile-shop' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Customer', 'codereyes-mobile-shop' ); ?></th>
					<th class="num"><?php esc_html_e( 'Owes', 'codereyes-mobile-shop' ); ?></th>
					<th><?php esc_html_e( 'Overdue', 'codereyes-mobile-shop' ); ?></th>
					<th><?php esc_html_e( 'Next installment', 'codereyes-mobile-shop' ); ?></th>
					<th><?php esc_html_e( 'Since', 'codereyes-mobile-shop' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cmshop_debtors as $cmshop_row ) : ?>
					<?php $cmshop_url = Cmshop_View::url( 'cmshop-dues', array( 'action' => 'collect', 'customer' => (int) $cmshop_row['customer_id'] ) ); ?>
					<tr>
						<td>
							<a class="row-title" href="<?php echo esc_url( $cmshop_url ); ?>"><?php echo esc_html( (string) $cmshop_row['name'] ); ?></a><br>
							<small><?php echo esc_html( (string) $cmshop_row['phone'] ); ?> ·
								<?php
								/* translators: %d: unpaid sales. */
								echo esc_html( sprintf( _n( '%d sale', '%d sales', (int) $cmshop_row['sales'], 'codereyes-mobile-shop' ), (int) $cmshop_row['sales'] ) );
								?>
							</small>
						</td>
						<td class="num"><strong><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_row['owed'] ) ); ?></strong></td>
						<td>
							<?php if ( $cmshop_row['overdue'] > 0 ) : ?>
								<span class="cmshop-badge cmshop-badge--void"><?php echo esc_html( Cmshop_Settings::money( Cmshop_Money::from_cents( $cmshop_row['overdue'] ) ) ); ?></span>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( '' === $cmshop_row['next_date'] ? '—' : (string) mysql2date( $cmshop_format, $cmshop_row['next_date'] ) ); ?></td>
						<td><?php echo esc_html( Cmshop_View::date( (string) $cmshop_row['oldest'], false ) ); ?></td>
						<td class="cmshop-row-buttons">
							<a class="button button-primary" href="<?php echo esc_url( $cmshop_url ); ?>"><?php esc_html_e( 'Collect', 'codereyes-mobile-shop' ); ?></a>
							<?php Cmshop_View::whatsapp( Cmshop_Messages::due( $cmshop_row ), __( 'Remind', 'codereyes-mobile-shop' ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
