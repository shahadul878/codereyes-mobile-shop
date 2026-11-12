/**
 * Codereyes Mobile Shop — POS logic without the screen: money, filtering,
 * cart changes and payment maths. Pure functions; the screen is in pos.js.
 *
 * Money is handled in whole cents (integers) to avoid rounding errors.
 */
( function ( window ) {
	'use strict';

	const BENGALI_DIGITS = '০১২৩৪৫৬৭৮৯';
	const IMEI_LENGTH = 15;
	const ROUND_STEPS = [ 500, 1000, 5000 ];

	/**
	 * Text with Bengali digits turned into 0-9.
	 *
	 * @param {string} text Text.
	 * @return {string} ASCII digits.
	 */
	function toAscii( text ) {
		return String( text ).replace( /[০-৯]/g, ( digit ) => String( BENGALI_DIGITS.indexOf( digit ) ) );
	}

	/**
	 * Cents from typed money ("18,500", "৳ ১৮৫০০.৫"). Empty or invalid gives 0.
	 *
	 * @param {string} text Typed amount.
	 * @return {number} Cents.
	 */
	function cents( text ) {
		const clean = toAscii( text ).replace( /[^\d.]/g, '' );

		if ( '' === clean || ! /^\d*(\.\d{0,2})?$/.test( clean ) ) {
			return 0;
		}

		const [ units, fraction = '' ] = clean.split( '.' );

		return ( parseInt( units || '0', 10 ) * 100 ) + parseInt( ( fraction + '00' ).slice( 0, 2 ), 10 );
	}

	/**
	 * Cents as the plain amount the server accepts ("18500.00").
	 *
	 * @param {number} value Cents.
	 * @return {string} Amount.
	 */
	function amount( value ) {
		return ( Math.round( value ) / 100 ).toFixed( 2 );
	}

	/**
	 * Cents as shop money ("৳ 18,500.00").
	 *
	 * @param {number} value  Cents.
	 * @param {string} symbol Currency symbol.
	 * @return {string} Formatted amount.
	 */
	function money( value, symbol ) {
		const sign = value < 0 ? '-' : '';
		const formatted = ( Math.abs( Math.round( value ) ) / 100 ).toLocaleString( 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );

		return sign + symbol + ' ' + formatted;
	}

	/**
	 * Whether a tile matches the type tab, brand tab and search text.
	 *
	 * @param {Object} tile   Catalog tile.
	 * @param {Object} filter { type, brand, query }.
	 * @return {boolean} Match.
	 */
	function matches( tile, filter ) {
		const type = filter.type;

		if ( 'accessory' === type && 'accessory' !== tile.type ) {
			return false;
		}

		if ( ( 'new' === type || 'used' === type ) && type !== tile.condition ) {
			return false;
		}

		if ( '' !== filter.brand && filter.brand.toLowerCase() !== String( tile.brand ).toLowerCase() ) {
			return false;
		}

		const query = toAscii( filter.query ).trim().toLowerCase();

		return '' === query || searchText( tile ).includes( query );
	}

	/**
	 * Everything a tile can be found by, lower case.
	 *
	 * @param {Object} tile Catalog tile.
	 * @return {string} Search text.
	 */
	function searchText( tile ) {
		const units = ( tile.options || [] ).flatMap( ( option ) => [ option.color, option.storage ].concat( option.units.map( ( unit ) => unit.imei ) ) );

		return [ tile.title, tile.brand, tile.detail || '', tile.imei || '', tile.sku || '' ].concat( units ).join( ' ' ).toLowerCase();
	}

	/**
	 * What a scanned code points to: a phone unit (by IMEI) or an item (by SKU).
	 *
	 * @param {Object[]} tiles Catalog tiles.
	 * @param {string}   code  Scanned text.
	 * @return {Object|null} { tile, unit|null } or null.
	 */
	function findCode( tiles, code ) {
		const clean = toAscii( code ).trim();
		const digits = clean.replace( /\D/g, '' );

		for ( const tile of tiles ) {
			if ( IMEI_LENGTH === digits.length ) {
				if ( tile.imei === digits ) {
					return { tile, unit: null };
				}

				for ( const option of tile.options || [] ) {
					const unit = option.units.find( ( candidate ) => candidate.imei === digits );

					if ( unit ) {
						return { tile, unit: { ...unit, option } };
					}
				}
			}

			if ( '' !== clean && tile.sku && tile.sku.toLowerCase() === clean.toLowerCase() ) {
				return { tile, unit: null };
			}
		}

		return null;
	}

	/**
	 * A cart line for a tile or one phone unit.
	 *
	 * @param {Object}      tile Tile.
	 * @param {Object|null} unit Phone unit (model tiles).
	 * @return {Object} Line.
	 */
	function lineFor( tile, unit ) {
		if ( unit ) {
			return {
				key: 'item:' + unit.id,
				itemId: unit.id,
				title: tile.title,
				detail: [ unit.option.storage, unit.option.color ].filter( Boolean ).join( ' · ' ),
				imei: unit.imei,
				price: cents( unit.price ),
				quantity: 1,
				max: 1,
				type: 'phone',
			};
		}

		return {
			key: 'item:' + tile.id,
			itemId: tile.id,
			title: tile.title,
			detail: tile.detail || tile.sku || '',
			imei: tile.imei || '',
			price: cents( tile.price ),
			quantity: 1,
			max: 'phone' === tile.type ? 1 : tile.stock,
			type: tile.type,
		};
	}

	/**
	 * Lines with one more of a line (or the line added). Phones can't be added twice.
	 *
	 * @param {Object[]} lines Current lines.
	 * @param {Object}   line  Line to add.
	 * @return {{lines: Object[], error: string}} New lines and a problem, if any.
	 */
	function addLine( lines, line ) {
		const existing = lines.find( ( candidate ) => candidate.key === line.key );

		if ( ! existing ) {
			return { lines: lines.concat( [ line ] ), error: '' };
		}

		if ( existing.quantity >= existing.max ) {
			return { lines, error: 'max' };
		}

		return { lines: setQuantity( lines, line.key, existing.quantity + 1 ), error: '' };
	}

	/**
	 * Lines with one line's quantity changed (kept between 1 and the stock).
	 *
	 * @param {Object[]} lines    Lines.
	 * @param {string}   key      Line key.
	 * @param {number}   quantity New quantity.
	 * @return {Object[]} New lines.
	 */
	function setQuantity( lines, key, quantity ) {
		return lines.map( ( line ) => ( line.key === key ? { ...line, quantity: Math.min( line.max, Math.max( 1, quantity ) ) } : line ) );
	}

	/**
	 * Subtotal, discount and total in cents.
	 *
	 * @param {Object[]} lines    Lines.
	 * @param {string}   discount Typed discount.
	 * @return {{subtotal: number, discount: number, total: number}} Totals.
	 */
	function totals( lines, discount ) {
		const subtotal = lines.reduce( ( sum, line ) => sum + ( line.price * line.quantity ), 0 );
		const off = Math.min( subtotal, cents( discount ) );

		return { subtotal, discount: off, total: subtotal - off };
	}

	/**
	 * What the payment rows add up to. A single empty row means "paid in full".
	 *
	 * @param {Object[]} rows  Payment rows { method, amount, reference }.
	 * @param {number}   total Sale total in cents.
	 * @return {{paid: number, change: number, due: number}} Balance.
	 */
	function balance( rows, total ) {
		const paid = 1 === rows.length && '' === String( rows[ 0 ].amount ).trim()
			? total
			: rows.reduce( ( sum, row ) => sum + cents( row.amount ), 0 );

		return { paid, change: Math.max( 0, paid - total ), due: Math.max( 0, total - paid ) };
	}

	/**
	 * Quick cash buttons: the exact total and the next round amounts above it.
	 *
	 * @param {number} total Total in cents.
	 * @return {number[]} Amounts in cents.
	 */
	function quickCash( total ) {
		if ( total <= 0 ) {
			return [];
		}

		const rounded = ROUND_STEPS.map( ( step ) => Math.ceil( total / ( step * 100 ) ) * step * 100 ).filter( ( value ) => value > total );

		return [ total ].concat( Array.from( new Set( rounded ) ) );
	}

	window.cmshopPosCore = { toAscii, cents, amount, money, matches, findCode, lineFor, addLine, setQuantity, totals, balance, quickCash };
}( window ) );
