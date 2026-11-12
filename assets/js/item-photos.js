/**
 * Codereyes Mobile Shop — photo picker on the stock item form.
 *
 * Uses the WordPress Media Library. Attachment IDs are kept in two hidden
 * fields (image_id, gallery_ids) that the form submits.
 */
( function () {
	'use strict';

	const root = document.querySelector( '[data-cmshop-photos]' );

	if ( ! root || ! window.wp || ! window.wp.media ) {
		return;
	}

	const { __ } = window.wp.i18n;
	const MAX_GALLERY = parseInt( root.dataset.max || '12', 10 );

	const mainInput = root.querySelector( '[data-main-input]' );
	const mainPreview = root.querySelector( '[data-main-preview]' );
	const chooseMain = root.querySelector( '[data-choose-main]' );
	const removeMain = root.querySelector( '[data-remove-main]' );
	const galleryInput = root.querySelector( '[data-gallery-input]' );
	const galleryList = root.querySelector( '[data-gallery-preview]' );
	const addGallery = root.querySelector( '[data-add-gallery]' );

	/**
	 * Thumbnail URL of a Media Library attachment.
	 *
	 * @param {Object} attachment Attachment JSON.
	 * @return {string} URL.
	 */
	function thumbnailUrl( attachment ) {
		const sizes = attachment.sizes || {};
		const best = sizes.thumbnail || sizes.medium || sizes.full;

		return best ? best.url : attachment.url;
	}

	/**
	 * An <img> for a preview.
	 *
	 * @param {string} url Image URL.
	 * @return {HTMLImageElement} Image.
	 */
	function image( url ) {
		const img = document.createElement( 'img' );
		img.src = url;
		img.alt = '';
		return img;
	}

	/**
	 * Gallery IDs currently in the hidden field.
	 *
	 * @return {number[]} IDs.
	 */
	function galleryIds() {
		return galleryInput.value
			.split( ',' )
			.map( ( part ) => parseInt( part, 10 ) )
			.filter( ( id ) => id > 0 );
	}

	/**
	 * A gallery list item with a remove button.
	 *
	 * @param {number} id  Attachment ID.
	 * @param {string} url Thumbnail URL.
	 * @return {HTMLLIElement} Item.
	 */
	function galleryItem( id, url ) {
		const li = document.createElement( 'li' );
		const remove = document.createElement( 'button' );

		li.dataset.id = String( id );
		remove.type = 'button';
		remove.className = 'cmshop-photos__drop';
		remove.dataset.removePhoto = '';
		remove.setAttribute( 'aria-label', __( 'Remove photo', 'codereyes-mobile-shop' ) );
		remove.textContent = '×';

		li.append( image( url ), remove );
		return li;
	}

	/**
	 * Show the main photo (or the empty placeholder).
	 *
	 * @param {number} id  Attachment ID, 0 for none.
	 * @param {string} url Thumbnail URL.
	 */
	function setMain( id, url ) {
		const placeholder = document.createElement( 'span' );
		placeholder.className = 'dashicons dashicons-format-image';
		placeholder.setAttribute( 'aria-hidden', 'true' );

		mainInput.value = String( id );
		mainPreview.replaceChildren( id > 0 ? image( url ) : placeholder );
		removeMain.hidden = id <= 0;
		chooseMain.textContent = id > 0 ? __( 'Change main photo', 'codereyes-mobile-shop' ) : __( 'Choose main photo', 'codereyes-mobile-shop' );
	}

	/**
	 * A Media Library frame limited to images.
	 *
	 * @param {string}  title    Frame title.
	 * @param {boolean} multiple Allow several.
	 * @return {Object} Frame.
	 */
	function frame( title, multiple ) {
		return window.wp.media( {
			title,
			multiple,
			library: { type: 'image' },
			button: { text: __( 'Use photo', 'codereyes-mobile-shop' ) },
		} );
	}

	const mainFrame = frame( __( 'Main photo', 'codereyes-mobile-shop' ), false );
	const galleryFrame = frame( __( 'More photos', 'codereyes-mobile-shop' ), true );

	mainFrame.on( 'select', () => {
		const attachment = mainFrame.state().get( 'selection' ).first().toJSON();
		setMain( attachment.id, thumbnailUrl( attachment ) );
	} );

	galleryFrame.on( 'select', () => {
		const current = galleryIds();
		const picked = galleryFrame
			.state()
			.get( 'selection' )
			.toJSON()
			.filter( ( attachment ) => ! current.includes( attachment.id ) )
			.slice( 0, Math.max( 0, MAX_GALLERY - current.length ) );

		galleryInput.value = current.concat( picked.map( ( attachment ) => attachment.id ) ).join( ',' );
		picked.forEach( ( attachment ) => galleryList.append( galleryItem( attachment.id, thumbnailUrl( attachment ) ) ) );
	} );

	chooseMain.addEventListener( 'click', () => mainFrame.open() );
	addGallery.addEventListener( 'click', () => galleryFrame.open() );
	removeMain.addEventListener( 'click', () => setMain( 0, '' ) );

	galleryList.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-remove-photo]' );

		if ( ! button ) {
			return;
		}

		const item = button.closest( 'li' );
		const id = parseInt( item.dataset.id, 10 );

		galleryInput.value = galleryIds().filter( ( other ) => other !== id ).join( ',' );
		item.remove();
	} );
}() );
