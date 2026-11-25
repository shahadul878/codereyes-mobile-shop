<?php
/**
 * Cash register: open a shift, petty cash, close with a note count, history.
 *
 * @package MobileShopManager
 *
 * @var array $cmshop { current: array|null, history: array[], errors: array, values: array }
 */

defined( 'ABSPATH' ) || exit;

$cmshop_current = $cmshop['current'];
$cmshop_labels  = Cmshop_Settings::payment_labels();
$cmshop_money   = static function ( int $cents ): string {
	return Cmshop_Settings::money( Cmshop_Money::from_cents( $cents ) );
};
?>
<div class="wrap cmshop-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Cash register', 'mobile-shop-manager' ); ?></h1>
	<hr class="wp-header-end">
	<?php Cmshop_View::notice(); ?>
	<?php Cmshop_View::error_summary( $cmshop['errors'] ); ?>

	<?php if ( null === $cmshop_current ) : ?>
		<section class="cmshop-card cmshop-shift-open">
			<h2><?php esc_html_e( 'Start your shift', 'mobile-shop-manager' ); ?></h2>
			<p><?php esc_html_e( 'Count the cash already in the drawer (change for the day). At closing, the plugin works out how much should be there.', 'mobile-shop-manager' ); ?></p>
			<?php Cmshop_View::form_open( 'open_shift' ); ?>
				<?php
				Cmshop_View::input(
					'opening_float',
					__( 'Cash in the drawer now', 'mobile-shop-manager' ),
					$cmshop['values'],
					array(),
					array(
						'inputmode'   => 'decimal',
						'placeholder' => '2000',
						'class'       => '',
					)
				);
				?>
				<?php submit_button( __( 'Open shift', 'mobile-shop-manager' ), 'primary large', 'submit', false ); ?>
			</form>
		</section>
	<?php else : ?>
		<?php $cmshop_sum = $cmshop_current['summary']; ?>
		<div class="cmshop-shift-live">
			<section class="cmshop-card cmshop-shift-expected">
				<h2><?php esc_html_e( 'Cash that should be in the drawer', 'mobile-shop-manager' ); ?></h2>
				<p class="cmshop-shift-big"><?php echo esc_html( $cmshop_money( $cmshop_sum['expected'] ) ); ?></p>
				<p class="cmshop-shift-meta">
					<?php
					/* translators: 1: time the shift opened, 2: opening float. */
					echo esc_html( sprintf( __( 'Open since %1$s with %2$s float', 'mobile-shop-manager' ), Cmshop_View::date( (string) $cmshop_current['opened_at'] ), Cmshop_Settings::money( (string) $cmshop_current['opening_float'] ) ) );
					?>
				</p>
				<?php if ( array() === $cmshop_sum['by_method'] ) : ?>
					<p class="cmshop-empty"><?php esc_html_e( 'No money taken yet. Sales, dues and petty cash show up here as they happen.', 'mobile-shop-manager' ); ?></p>
				<?php endif; ?>
				<ul class="cmshop-shift-methods">
					<?php foreach ( $cmshop_sum['by_method'] as $cmshop_method => $cmshop_cents ) : ?>
						<li><span><?php echo esc_html( Cmshop_Item::label( $cmshop_labels, (string) $cmshop_method ) ); ?></span><strong><?php echo esc_html( $cmshop_money( $cmshop_cents ) ); ?></strong></li>
					<?php endforeach; ?>
				</ul>
			</section>

			<section class="cmshop-card">
				<h2><?php esc_html_e( 'Petty cash', 'mobile-shop-manager' ); ?></h2>
				<?php Cmshop_View::form_open( 'petty_cash' ); ?>
					<div class="cmshop-row">
						<?php
						Cmshop_View::select(
							'direction',
							__( 'Cash', 'mobile-shop-manager' ),
							array(
								'out' => __( 'Taken out (expense)', 'mobile-shop-manager' ),
								'in'  => __( 'Put in (top-up)', 'mobile-shop-manager' ),
							),
							array(),
							array()
						);
						Cmshop_View::input(
							'amount',
							__( 'Amount', 'mobile-shop-manager' ),
							array(),
							array(),
							array(
								'inputmode' => 'decimal',
								'class'     => '',
							)
						);
						?>
					</div>
					<?php Cmshop_View::input( 'reason', __( 'What for?', 'mobile-shop-manager' ), array(), array(), array( 'placeholder' => __( 'Tea, transport, change from the bank…', 'mobile-shop-manager' ) ) ); ?>
					<?php submit_button( __( 'Record', 'mobile-shop-manager' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( array() !== $cmshop_current['petty'] ) : ?>
					<ul class="cmshop-list cmshop-shift-petty">
						<?php foreach ( $cmshop_current['petty'] as $cmshop_row ) : ?>
							<li><span><?php echo esc_html( (string) $cmshop_row['note'] ); ?></span><strong><?php echo esc_html( Cmshop_Settings::money( (string) $cmshop_row['amount'] ) ); ?></strong></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>

		<section class="cmshop-card">
			<h2><?php esc_html_e( 'Close the shift: count the drawer', 'mobile-shop-manager' ); ?></h2>
			<?php Cmshop_View::form_open( 'close_shift' ); ?>
				<div class="cmshop-count" data-expected="<?php echo esc_attr( (string) $cmshop_sum['expected'] ); ?>">
					<?php foreach ( Cmshop_Shift_Math::DENOMINATIONS as $cmshop_note ) : ?>
						<label class="cmshop-count__note">
							<span>&#2547; <?php echo esc_html( (string) $cmshop_note ); ?> &times;</span>
							<input type="number" min="0" step="1" name="notes[<?php echo esc_attr( (string) $cmshop_note ); ?>]" data-note="<?php echo esc_attr( (string) $cmshop_note ); ?>" class="small-text" inputmode="numeric">
						</label>
					<?php endforeach; ?>
					<p class="cmshop-count__total">
						<?php esc_html_e( 'Counted', 'mobile-shop-manager' ); ?> <strong data-count-total><?php echo esc_html( $cmshop_money( 0 ) ); ?></strong>
						<span data-count-diff></span>
					</p>
				</div>
				<?php Cmshop_View::input( 'note', __( 'Note for the owner', 'mobile-shop-manager' ), array(), array(), array( 'placeholder' => __( 'Anything to explain a difference', 'mobile-shop-manager' ) ) ); ?>
				<?php submit_button( __( 'Close shift and print report', 'mobile-shop-manager' ), 'primary large', 'submit', false ); ?>
			</form>
		</section>
	<?php endif; ?>

	<section class="cmshop-card">
		<h2><?php esc_html_e( 'Past shifts', 'mobile-shop-manager' ); ?></h2>
		<?php if ( array() === $cmshop['history'] ) : ?>
			<p class="cmshop-empty"><?php esc_html_e( 'Closed shifts and their reports appear here.', 'mobile-shop-manager' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Opened', 'mobile-shop-manager' ); ?></th>
						<th><?php esc_html_e( 'Cashier', 'mobile-shop-manager' ); ?></th>
						<th class="num"><?php esc_html_e( 'Expected', 'mobile-shop-manager' ); ?></th>
						<th class="num"><?php esc_html_e( 'Counted', 'mobile-shop-manager' ); ?></th>
						<th><?php esc_html_e( 'Difference', 'mobile-shop-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cmshop['history'] as $cmshop_row ) : ?>
						<?php
						$cmshop_user = get_userdata( (int) $cmshop_row['user_id'] );
						$cmshop_diff = null === $cmshop_row['closed_at'] ? null : Cmshop_Shift_Math::difference( Cmshop_Money::to_cents( (string) $cmshop_row['counted_cash'] ), Cmshop_Money::to_cents( (string) $cmshop_row['expected_cash'] ) );
						?>
						<tr>
							<td><a href="<?php echo esc_url( Cmshop_View::url( 'cmshop-shifts', array( 'action' => 'report', 'id' => (int) $cmshop_row['id'] ) ) ); ?>"><?php echo esc_html( Cmshop_View::date( (string) $cmshop_row['opened_at'] ) ); ?></a></td>
							<td><?php echo esc_html( $cmshop_user ? $cmshop_user->display_name : '—' ); ?></td>
							<td class="num"><?php echo esc_html( null === $cmshop_row['expected_cash'] ? '—' : Cmshop_Settings::money( (string) $cmshop_row['expected_cash'] ) ); ?></td>
							<td class="num"><?php echo esc_html( null === $cmshop_row['counted_cash'] ? '—' : Cmshop_Settings::money( (string) $cmshop_row['counted_cash'] ) ); ?></td>
							<td>
								<?php if ( null === $cmshop_diff ) : ?>
									<span class="cmshop-badge cmshop-badge--in_stock"><?php esc_html_e( 'Open', 'mobile-shop-manager' ); ?></span>
								<?php elseif ( 0 === $cmshop_diff ) : ?>
									<span class="cmshop-badge cmshop-badge--completed"><?php esc_html_e( 'Balanced', 'mobile-shop-manager' ); ?></span>
								<?php else : ?>
									<span class="cmshop-badge cmshop-badge--<?php echo esc_attr( $cmshop_diff < 0 ? 'void' : 'reserved' ); ?>"><?php echo esc_html( ( $cmshop_diff > 0 ? '+' : '' ) . $cmshop_money( $cmshop_diff ) ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>
</div>
