<?php
/**
 * Minimal WordPress stand-ins for pure-logic unit tests.
 *
 * @package MobileShopManager
 */

// phpcs:disable

function __( $text, $domain = 'default' ) {
	return $text;
}

/**
 * Like WordPress: script and style blocks go with their contents.
 */
function wp_strip_all_tags( $text ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	return strip_tags( $text );
}

function sanitize_text_field( $str ) {
	$str = wp_strip_all_tags( (string) $str );
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
	return trim( $str );
}

function sanitize_textarea_field( $str ) {
	return trim( wp_strip_all_tags( (string) $str ) );
}

function absint( $maybeint ) {
	return abs( (int) $maybeint );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_email( $email ) {
	return trim( (string) $email );
}

function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}
