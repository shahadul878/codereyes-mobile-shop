/**
 * Codereyes Mobile Shop — block editor UI for the website blocks.
 *
 * Block metadata (title, attributes, category) comes from blocks/*\/block.json,
 * registered in PHP. Blocks are rendered on the server, so the preview here is
 * the real output; it is shown disabled so clicks don't submit the forms.
 */
( function ( wp ) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, ToggleControl, RangeControl, ColorPalette, Disabled, BaseControl } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { __ } = wp.i18n;

	const DEFAULT_RADIUS = 16;

	const ACCENTS = [
		{ name: __( 'Teal', 'codereyes-mobile-shop' ), color: '#0d9488' },
		{ name: __( 'Blue', 'codereyes-mobile-shop' ), color: '#2563eb' },
		{ name: __( 'Violet', 'codereyes-mobile-shop' ), color: '#7c3aed' },
		{ name: __( 'Rose', 'codereyes-mobile-shop' ), color: '#e11d48' },
		{ name: __( 'Orange', 'codereyes-mobile-shop' ), color: '#ea580c' },
		{ name: __( 'Ink', 'codereyes-mobile-shop' ), color: '#0f172a' },
	];

	/**
	 * Accent colour and corner radius, shared by every block.
	 *
	 * @param {Object}   attributes    Block attributes.
	 * @param {Function} setAttributes Setter.
	 * @return {Object} Panel element.
	 */
	function stylePanel( attributes, setAttributes ) {
		return el(
			PanelBody,
			{ title: __( 'Style', 'codereyes-mobile-shop' ), initialOpen: false },
			el(
				BaseControl,
				{ label: __( 'Accent colour', 'codereyes-mobile-shop' ), id: 'cmshop-accent' },
				el( ColorPalette, {
					colors: ACCENTS,
					value: attributes.accentColor || undefined,
					onChange: ( value ) => setAttributes( { accentColor: value || '' } ),
					disableCustomColors: false,
				} )
			),
			el( RangeControl, {
				label: __( 'Corner radius', 'codereyes-mobile-shop' ),
				value: undefined === attributes.cornerRadius ? DEFAULT_RADIUS : attributes.cornerRadius,
				onChange: ( value ) => setAttributes( { cornerRadius: value } ),
				min: 0,
				max: 32,
				allowReset: true,
				resetFallbackValue: DEFAULT_RADIUS,
			} )
		);
	}

	/**
	 * Settings for the stock block.
	 *
	 * @param {Object}   attributes    Block attributes.
	 * @param {Function} setAttributes Setter.
	 * @return {Object} Panel element.
	 */
	function stockPanel( attributes, setAttributes ) {
		return el(
			PanelBody,
			{ title: __( 'What to show', 'codereyes-mobile-shop' ) },
			el( SelectControl, {
				label: __( 'Items', 'codereyes-mobile-shop' ),
				value: attributes.type,
				options: [
					{ label: __( 'Phones', 'codereyes-mobile-shop' ), value: 'phone' },
					{ label: __( 'Accessories', 'codereyes-mobile-shop' ), value: 'accessory' },
					{ label: __( 'Phones and accessories', 'codereyes-mobile-shop' ), value: 'all' },
				],
				onChange: ( value ) => setAttributes( { type: value } ),
			} ),
			el( SelectControl, {
				label: __( 'Condition', 'codereyes-mobile-shop' ),
				value: attributes.condition,
				options: [
					{ label: __( 'New and used', 'codereyes-mobile-shop' ), value: 'all' },
					{ label: __( 'New only', 'codereyes-mobile-shop' ), value: 'new' },
					{ label: __( 'Used only', 'codereyes-mobile-shop' ), value: 'used' },
				],
				onChange: ( value ) => setAttributes( { condition: value } ),
			} ),
			el( TextControl, {
				label: __( 'Only this brand', 'codereyes-mobile-shop' ),
				help: __( 'Leave empty to show every brand.', 'codereyes-mobile-shop' ),
				value: attributes.brand,
				onChange: ( value ) => setAttributes( { brand: value } ),
			} ),
			el( RangeControl, {
				label: __( 'Columns', 'codereyes-mobile-shop' ),
				help: __( '0 fits as many as the page allows.', 'codereyes-mobile-shop' ),
				value: attributes.columns,
				onChange: ( value ) => setAttributes( { columns: value || 0 } ),
				min: 0,
				max: 6,
			} ),
			el( RangeControl, {
				label: __( 'Items per page', 'codereyes-mobile-shop' ),
				value: attributes.perPage,
				onChange: ( value ) => setAttributes( { perPage: value || 12 } ),
				min: 1,
				max: 48,
			} ),
			el( ToggleControl, {
				label: __( 'Show brand and condition filters', 'codereyes-mobile-shop' ),
				checked: attributes.filters,
				onChange: ( value ) => setAttributes( { filters: value } ),
			} )
		);
	}

	/**
	 * Editor component for a block.
	 *
	 * @param {string}        name     Block name.
	 * @param {Function|null} settings Extra settings panel, or null.
	 * @return {Function} Edit component.
	 */
	function edit( name, settings ) {
		return function Edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					settings ? settings( attributes, setAttributes ) : null,
					stylePanel( attributes, setAttributes )
				),
				el(
					'div',
					blockProps,
					el( Disabled, null, el( ServerSideRender, { block: name, attributes, skipBlockSupportAttributes: true } ) )
				)
			);
		};
	}

	const BLOCKS = {
		'codereyes-mobile-shop/stock': stockPanel,
		'codereyes-mobile-shop/warranty': null,
		'codereyes-mobile-shop/repair-request': null,
		'codereyes-mobile-shop/repair-status': null,
	};

	Object.keys( BLOCKS ).forEach( ( name ) => {
		registerBlockType( name, {
			edit: edit( name, BLOCKS[ name ] ),
			save: () => null,
		} );
	} );
}( window.wp ) );
