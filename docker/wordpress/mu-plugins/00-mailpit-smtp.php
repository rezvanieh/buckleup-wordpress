<?php
/**
 * Plugin Name: BuckleUp Dev — Mailpit SMTP
 * Description: Routes all wp_mail() through the Mailpit container so no real email
 *              is ever sent in local dev. Loaded as an mu-plugin (always active).
 *              Remove / do not deploy to production.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'phpmailer_init', function ( $phpmailer ) {
	$phpmailer->isSMTP();
	$phpmailer->Host       = getenv( 'SMTP_HOST' ) ?: 'mailpit';
	$phpmailer->Port       = getenv( 'SMTP_PORT' ) ?: 1025;
	$phpmailer->SMTPAuth   = false;
	$phpmailer->SMTPSecure = '';
	$phpmailer->SMTPAutoTLS = false;
} );

// Sensible dev From: so caught mail looks like production.
add_filter( 'wp_mail_from', fn() => 'info@buckleupdriving.ca' );
add_filter( 'wp_mail_from_name', fn() => 'BuckleUp Driving School' );
