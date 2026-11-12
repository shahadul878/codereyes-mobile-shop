/**
 * Codereyes Mobile Shop — POS DOM helpers: element builder, icons, product tile.
 * Text is always set with textContent.
 */
( function ( window, wp ) {
	'use strict';

	const { __, _n, sprintf } = wp.i18n;

	/**
	 * Element with class, text and attributes.
	 *
	 * @param {string} tag   Tag name.
	 * @param {Object} props className, text, and any attribute.
	 * @param {Array}  kids  Child nodes.
	 * @return {HTMLElement} Element.
	 */
	function el( tag, props = {}, kids = [] ) {
		const node = document.createElement( tag );

		Object.entries( props ).forEach( ( [ key, value ] ) => {
			if ( 'className' === key ) {
				node.className = value;
			} else if ( 'text' === key ) {
				node.textContent = value;
			} else if ( null !== value && undefined !== value && false !== value ) {
				node.setAttribute( key, true === value ? '' : value );
			}
		} );
		kids.filter( Boolean ).forEach( ( kid ) => node.append( kid ) );

		return node;
	}

	/**
	 * Dashicon span.
	 *
	 * @param {string} name Icon name.
	 * @return {HTMLElement} Icon.
	 */
	function icon( name ) {
		return el( 'span', { className: 'dashicons dashicons-' + name, 'aria-hidden': 'true' } );
	}

	/**
	 * One product tile.
	 *
	 * @param {Object}   tile  Catalog tile.
	 * @param {Function} money Money formatter.
	 * @return {HTMLElement} Tile.
	 */
	function tileNode( tile, money ) {
		const badge = 'new' === tile.condition ? __( 'New', 'codereyes-mobile-shop' ) : tile.grade;
		const detail = 'model' === tile.kind
			/* translators: %d: number of colour/storage options. */
			? sprintf( _n( '%d option', '%d options', tile.options.length, 'codereyes-mobile-shop' ), tile.options.length )
			: [ tile.detail, null !== tile.battery && undefined !== tile.battery ? sprintf( /* translators: %d: battery health. */ __( 'Battery %d%%', 'codereyes-mobile-shop' ), tile.battery ) : '' ].filter( Boolean ).join( ' · ' );
		const swatches = 'model' === tile.kind ? el( 'span', { className: 'cmshop-pos-tile__swatches' }, tile.options.slice( 0, 5 ).map( ( option ) => el( 'i', { style: option.swatch ? 'background:' + option.swatch : null, title: option.color } ) ) ) : null;
		const stock = 1 === tile.stock ? __( 'Last one', 'codereyes-mobile-shop' ) : sprintf( /* translators: %d: number in stock. */ __( '%d in stock', 'codereyes-mobile-shop' ), tile.stock );

		return el( 'li', {}, [
			el( 'button', { type: 'button', className: 'cmshop-pos-tile', 'data-tile': tile.key }, [
				el( 'span', { className: 'cmshop-pos-tile__media' }, [
					tile.image ? el( 'img', { src: tile.image, alt: '', loading: 'lazy' } ) : icon( 'accessory' === tile.type ? 'admin-plugins' : 'smartphone' ),
					badge ? el( 'span', { className: 'cmshop-pos-tile__badge cmshop-pos-tile__badge--' + ( tile.condition || 'item' ), text: badge } ) : null,
				] ),
				el( 'span', { className: 'cmshop-pos-tile__title', text: tile.title } ),
				detail ? el( 'span', { className: 'cmshop-pos-tile__detail', text: detail } ) : null,
				el( 'span', { className: 'cmshop-pos-tile__foot' }, [
					el( 'span', { className: 'cmshop-pos-tile__price', text: tile.price_text } ),
					swatches,
				] ),
				el( 'span', { className: 'cmshop-pos-tile__stock' + ( 1 === tile.stock ? ' is-low' : '' ), text: stock } ),
			] ),
		] );
	}

	window.cmshopPosDom = { el, icon, tile: tileNode };
}( window, window.wp ) );
