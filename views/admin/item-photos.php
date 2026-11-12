<?php
/**
 * Photos on the item form: one main photo and a gallery from the Media Library.
 * Included from item-form.php.
 *
 * @package CodereyesMobileShop
 *
 * @var array $cmshop_values   Current form values.
 * @var bool  $cmshop_is_phone Whether the item is a phone.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'upload_files' ) ) {
	return;
}

$cmshop_image_id = (int) ( $cmshop_values['image_id'] ?? 0 );
$cmshop_gallery  = Cmshop_Item::gallery_ids( (string) ( $cmshop_values['gallery_ids'] ?? '' ) );
?>
<div class="cmshop-photos" data-cmshop-photos data-max="<?php echo esc_attr( (string) Cmshop_Item::MAX_GALLERY ); ?>">
	<h3><?php esc_html_e( 'Photos', 'codereyes-mobile-shop' ); ?></h3>
	<p class="description">
		<?php
		echo esc_html(
			$cmshop_is_phone
				? __( 'Shown in your stock list and on your website. For a new phone, the photo appears when a customer picks this colour.', 'codereyes-mobile-shop' )
				: __( 'Shown in your stock list and on your website.', 'codereyes-mobile-shop' )
		);
		?>
	</p>

	<div class="cmshop-photos__main">
		<input type="hidden" name="image_id" value="<?php echo esc_attr( (string) $cmshop_image_id ); ?>" data-main-input>
		<div class="cmshop-photos__frame" data-main-preview>
			<?php if ( $cmshop_image_id > 0 ) : ?>
				<?php echo wp_get_attachment_image( $cmshop_image_id, 'thumbnail', false, array( 'alt' => '' ) ); ?>
			<?php else : ?>
				<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
			<?php endif; ?>
		</div>
		<div class="cmshop-photos__actions">
			<button type="button" class="button" data-choose-main>
				<?php echo esc_html( $cmshop_image_id > 0 ? __( 'Change main photo', 'codereyes-mobile-shop' ) : __( 'Choose main photo', 'codereyes-mobile-shop' ) ); ?>
			</button>
			<button type="button" class="button-link cmshop-photos__remove" data-remove-main <?php echo $cmshop_image_id > 0 ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'codereyes-mobile-shop' ); ?></button>
		</div>
	</div>

	<input type="hidden" name="gallery_ids" value="<?php echo esc_attr( Cmshop_Item::gallery_csv( $cmshop_gallery ) ); ?>" data-gallery-input>
	<ul class="cmshop-photos__gallery" data-gallery-preview aria-label="<?php esc_attr_e( 'More photos', 'codereyes-mobile-shop' ); ?>">
		<?php foreach ( $cmshop_gallery as $cmshop_photo ) : ?>
			<li data-id="<?php echo esc_attr( (string) $cmshop_photo ); ?>">
				<?php echo wp_get_attachment_image( $cmshop_photo, 'thumbnail', false, array( 'alt' => '' ) ); ?>
				<button type="button" class="cmshop-photos__drop" data-remove-photo aria-label="<?php esc_attr_e( 'Remove photo', 'codereyes-mobile-shop' ); ?>">&times;</button>
			</li>
		<?php endforeach; ?>
	</ul>
	<button type="button" class="button" data-add-gallery><?php esc_html_e( 'Add more photos', 'codereyes-mobile-shop' ); ?></button>
</div>
