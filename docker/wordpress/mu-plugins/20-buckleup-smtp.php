<?php
/**
 * Plugin Name: BuckleUp — SMTP transport
 * Description: Routes wp_mail() through an authenticated SMTP server when the
 *              BUCKLEUP_SMTP_* constants are defined (production wp-config).
 *              No-op when they're absent, so dev keeps using its own transport
 *              (Mailpit). Credentials live in wp-config.php, never in this file,
 *              so it's safe to version-control.
 *
 * Zoho note: the From / envelope-sender MUST be the authenticated mailbox (or a
 * verified alias), so we force it; Reply-To (e.g. the contact-form submitter)
 * is left untouched.
 *
 * @package BuckleUp_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Inactive unless production SMTP is configured.
if ( ! defined( 'BUCKLEUP_SMTP_HOST' ) || ! BUCKLEUP_SMTP_HOST ) {
	return;
}

add_action(
	'phpmailer_init',
	function ( $phpmailer ) {
		$port = defined( 'BUCKLEUP_SMTP_PORT' ) ? (int) BUCKLEUP_SMTP_PORT : 465;
		$from = ( defined( 'BUCKLEUP_SMTP_FROM' ) && BUCKLEUP_SMTP_FROM ) ? BUCKLEUP_SMTP_FROM : BUCKLEUP_SMTP_USER;

		$phpmailer->isSMTP();
		$phpmailer->Host       = BUCKLEUP_SMTP_HOST;
		$phpmailer->Port       = $port;
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = BUCKLEUP_SMTP_USER;
		$phpmailer->Password   = BUCKLEUP_SMTP_PASS;
		$phpmailer->SMTPSecure = ( 587 === $port ) ? 'tls' : 'ssl';

		// Zoho rejects mail whose From isn't the authenticated user/alias.
		$phpmailer->From   = $from;
		$phpmailer->Sender = $from; // envelope sender / Return-Path
		if ( empty( $phpmailer->FromName ) ) {
			$phpmailer->FromName = 'BuckleUp Driving School';
		}
	}
);

// Default From for mail with no explicit From header.
add_filter(
	'wp_mail_from',
	function ( $email ) {
		if ( defined( 'BUCKLEUP_SMTP_FROM' ) && BUCKLEUP_SMTP_FROM ) {
			return BUCKLEUP_SMTP_FROM;
		}
		return defined( 'BUCKLEUP_SMTP_USER' ) ? BUCKLEUP_SMTP_USER : $email;
	}
);
add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		return $name ? $name : 'BuckleUp Driving School';
	}
);
