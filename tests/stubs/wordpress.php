<?php
/**
 * Minimal WordPress stand-ins for pure-logic unit tests.
 *
 * @package CodereyesMobileShop
 */

// phpcs:disable

function __( $text, $domain = 'default' ) {
	return $text;
}

function sanitize_text_field( $str ) {
	$str = strip_tags( (string) $str );
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
	return trim( $str );
}

function sanitize_textarea_field( $str ) {
	return trim( strip_tags( (string) $str ) );
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
