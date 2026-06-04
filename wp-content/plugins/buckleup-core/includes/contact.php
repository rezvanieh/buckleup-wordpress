<?php
/**
 * Contact form submission handler.
 *
 * Faithful to the source `/api/contact`, implemented as a simple no-JS POST so
 * the form works without JavaScript and matches the theme markup the theme
 * engineer builds against the same contract.
 *
 * Flow:
 *   - The theme renders a <form method="post" action="{admin-post.php}"> with a
 *     hidden `action=buckleup_contact` and the nonce field
 *     `buckleup_contact_nonce` (action `buckleup_contact`).
 *   - This handler verifies the nonce, validates + sanitizes the fields, emails
 *     the business inbox via wp_mail(), then wp_safe_redirect()s back to the
 *     referring /contact page with `?contact=success` (or `?contact=error`).
 *   - The theme reads `$_GET['contact']` to show the success/error state.
 *
 * Fields (snake_case, matching the theme form):
 *   first_name (required), last_name (optional), email (required),
 *   phone (optional), subject (required), message (required).
 *
 * In dev, wp_mail() is routed to Mailpit by the docker mu-plugin.
 *
 * @package BuckleUp_Core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Required field keys (last_name + phone are optional), mirroring the source.
 *
 * @return string[]
 */
function buckleup_contact_required_fields() {
	return array( 'first_name', 'email', 'subject', 'message' );
}

/**
 * Validate + sanitize a raw submission into clean values, or return a WP_Error.
 *
 * @param array<string,mixed> $raw Raw $_POST (already unslashed).
 * @return array<string,string>|WP_Error
 */
function buckleup_contact_validate( $raw ) {
	$clean = array(
		'first_name' => isset( $raw['first_name'] ) ? sanitize_text_field( $raw['first_name'] ) : '',
		'last_name'  => isset( $raw['last_name'] ) ? sanitize_text_field( $raw['last_name'] ) : '',
		'email'      => isset( $raw['email'] ) ? sanitize_email( $raw['email'] ) : '',
		'phone'      => isset( $raw['phone'] ) ? sanitize_text_field( $raw['phone'] ) : '',
		'subject'    => isset( $raw['subject'] ) ? sanitize_text_field( $raw['subject'] ) : '',
		'message'    => isset( $raw['message'] ) ? sanitize_textarea_field( $raw['message'] ) : '',
	);

	foreach ( buckleup_contact_required_fields() as $field ) {
		if ( '' === $clean[ $field ] ) {
			return new WP_Error( 'missing_fields', __( 'Missing required fields', 'buckleup-core' ) );
		}
	}

	if ( ! is_email( $clean['email'] ) ) {
		return new WP_Error( 'invalid_email', __( 'Please provide a valid email address', 'buckleup-core' ) );
	}

	return $clean;
}

/**
 * Resolve the brand logo URL for emails: the theme's light logo attachment, then
 * a 'logo' attachment, then the theme custom logo, then the site icon.
 *
 * @return string Absolute URL (or '' if nothing resolves).
 */
function buckleup_contact_logo_url() {
	foreach ( array( 'buckleup-driving-school-logo-light', 'logo' ) as $slug ) {
		$ids = get_posts(
			array(
				'name'        => $slug,
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		if ( $ids ) {
			$url = wp_get_attachment_image_url( $ids[0], 'full' );
			if ( $url ) {
				return $url;
			}
		}
	}
	$custom = get_theme_mod( 'custom_logo' );
	if ( $custom ) {
		$url = wp_get_attachment_image_url( $custom, 'full' );
		if ( $url ) {
			return $url;
		}
	}
	return (string) get_site_icon_url( 192 );
}

/**
 * Render the branded HTML email body for a contact submission. Table + inline
 * styles for broad email-client support; all submitter data is escaped.
 *
 * @param array<string,string> $data      Validated submission.
 * @param string               $full_name Submitter display name.
 * @return string HTML document.
 */
function buckleup_contact_email_html( $data, $full_name ) {
	$logo   = esc_url( buckleup_contact_logo_url() );
	$site   = esc_html( get_bloginfo( 'name' ) );
	$home   = esc_url( home_url( '/' ) );
	$accent = '#dc2626';
	$ink    = '#111827';
	$muted  = '#6b7280';
	$border = '#e5e7eb';
	$panel  = '#f9fafb';
	$page   = '#f3f4f6';

	$name_e    = esc_html( $full_name );
	$email_e   = esc_html( $data['email'] );
	$phone_e   = '' !== $data['phone'] ? esc_html( $data['phone'] ) : '&mdash;';
	$subject_e = esc_html( $data['subject'] );
	$message_e = nl2br( esc_html( $data['message'] ) );
	$first_e   = esc_html( $data['first_name'] );
	$sent_e    = esc_html( wp_date( 'F j, Y \a\t g:i a' ) );
	$reply     = esc_url( 'mailto:' . $data['email'] . '?subject=' . rawurlencode( 'Re: ' . $data['subject'] ) );

	$row = function ( $label, $value ) use ( $muted, $ink, $border ) {
		return sprintf(
			'<tr><td style="padding:10px 0;border-bottom:1px solid %4$s;width:110px;vertical-align:top;font:600 12px/1.4 Arial,Helvetica,sans-serif;color:%1$s;text-transform:uppercase;letter-spacing:.04em;">%2$s</td>'
			. '<td style="padding:10px 0;border-bottom:1px solid %4$s;font:400 15px/1.5 Arial,Helvetica,sans-serif;color:%3$s;">%5$s</td></tr>',
			$muted,
			$label,
			$ink,
			$border,
			$value
		);
	};

	$logo_html = $logo
		? sprintf( '<a href="%1$s"><img src="%2$s" alt="%3$s" height="44" style="height:44px;width:auto;display:block;border:0;"></a>', $home, $logo, $site )
		: sprintf( '<a href="%1$s" style="font:700 22px/1 Arial,Helvetica,sans-serif;color:%2$s;text-decoration:none;">%3$s</a>', $home, $ink, $site );

	$rows  = $row( 'Name', $name_e );
	$rows .= $row( 'Email', sprintf( '<a href="mailto:%1$s" style="color:%2$s;text-decoration:none;">%1$s</a>', $email_e, $accent ) );
	$rows .= $row( 'Phone', $phone_e );
	$rows .= $row( 'Subject', $subject_e );

	return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $subject_e . '</title></head>'
		. '<body style="margin:0;padding:0;background:' . $page . ';">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $page . ';padding:24px 12px;"><tr><td align="center">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid ' . $border . ';border-radius:12px;overflow:hidden;">'
		. '<tr><td align="center" style="padding:28px 24px 22px;border-bottom:3px solid ' . $accent . ';">' . $logo_html . '</td></tr>'
		. '<tr><td style="padding:30px 32px 8px;">'
		. '<h1 style="margin:0 0 6px;font:700 20px/1.3 Arial,Helvetica,sans-serif;color:' . $ink . ';">New contact form submission</h1>'
		. '<p style="margin:0 0 22px;font:400 14px/1.5 Arial,Helvetica,sans-serif;color:' . $muted . ';">You&rsquo;ve received a new message from the website.</p>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table></td></tr>'
		. '<tr><td style="padding:18px 32px 4px;">'
		. '<div style="font:600 12px/1.4 Arial,Helvetica,sans-serif;color:' . $muted . ';text-transform:uppercase;letter-spacing:.04em;margin:0 0 8px;">Message</div>'
		. '<div style="background:' . $panel . ';border-left:3px solid ' . $accent . ';border-radius:6px;padding:16px 18px;font:400 15px/1.6 Arial,Helvetica,sans-serif;color:' . $ink . ';">' . $message_e . '</div></td></tr>'
		. '<tr><td style="padding:24px 32px 32px;">'
		. '<a href="' . $reply . '" style="display:inline-block;background:' . $ink . ';color:#ffffff;text-decoration:none;font:600 14px/1 Arial,Helvetica,sans-serif;padding:13px 22px;border-radius:8px;">Reply to ' . $first_e . '</a></td></tr>'
		. '<tr><td style="padding:18px 32px;background:' . $panel . ';border-top:1px solid ' . $border . ';">'
		. '<p style="margin:0;font:400 12px/1.5 Arial,Helvetica,sans-serif;color:' . $muted . ';">Sent from the contact form at <a href="' . $home . '" style="color:' . $muted . ';">buckleupdriving.ca</a> on ' . $sent_e . '.</p>'
		. '</td></tr></table></td></tr></table></body></html>';
}

/**
 * Build and send the notification email via wp_mail() as branded HTML.
 *
 * @param array<string,string> $data Validated submission.
 * @return bool wp_mail() result.
 */
function buckleup_contact_send_email( $data ) {
	$to = buckleup_get_setting( 'email', get_option( 'admin_email' ) );

	/* translators: %s: the submitter's subject line. */
	$subject = sprintf( __( 'New contact: %s', 'buckleup-core' ), $data['subject'] );

	$full_name = trim( $data['first_name'] . ' ' . $data['last_name'] );

	$body = buckleup_contact_email_html( $data, $full_name );

	// HTML body; Reply-To the submitter so the team can respond directly. From:
	// stays the authenticated site mailbox (set by the SMTP mu-plugin) for
	// deliverability (Zoho requires it).
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		sprintf( 'Reply-To: %s <%s>', $full_name, $data['email'] ),
	);

	return wp_mail( $to, $subject, $body, $headers );
}

/**
 * Resolve the URL to redirect back to after handling, appending the result
 * flag. Falls back to the /contact page if there is no usable referer.
 *
 * @param string $status 'success' | 'error'.
 * @return string
 */
function buckleup_contact_redirect_url( $status ) {
	$referer = wp_get_referer();
	if ( ! $referer ) {
		$referer = home_url( '/contact/' );
	}
	return add_query_arg( 'contact', $status, $referer );
}

/**
 * Best-effort client IP for rate-limiting. Not a trust decision — only used to
 * bucket submissions; spoofing just resets the attacker's own counter.
 *
 * @return string
 */
function buckleup_contact_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	return $ip ? sanitize_text_field( $ip ) : 'unknown';
}

/**
 * Lightweight transient rate limit: at most N submissions per window per
 * bucket (IP and email). Returns true if the submission is allowed.
 *
 * @param string $email Submitter email (already sanitized; may be '').
 * @return bool
 */
function buckleup_contact_rate_ok( $email ) {
	$max    = (int) apply_filters( 'buckleup_contact_rate_max', 3 );      // per window
	$window = (int) apply_filters( 'buckleup_contact_rate_window', 600 ); // 10 minutes

	$buckets = array( 'buckleup_contact_rl_ip_' . md5( buckleup_contact_client_ip() ) );
	if ( '' !== $email ) {
		$buckets[] = 'buckleup_contact_rl_em_' . md5( strtolower( $email ) );
	}

	$allowed = true;
	foreach ( $buckets as $key ) {
		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			$allowed = false;
		}
		// Increment regardless so a burst across buckets still counts.
		set_transient( $key, $count + 1, $window );
	}

	return $allowed;
}

/**
 * admin-post handler (shared for logged-in + logged-out visitors).
 *
 * Perimeter order: nonce → honeypot → min-fill-time → validate → rate-limit →
 * send. Bot/abuse rejections (honeypot, min-fill, rate-limit) silently redirect
 * to ?contact=success so a scraper can't tell a block from a real send; genuine
 * validation/mail failures return ?contact=error.
 */
function buckleup_admin_post_contact_handler() {
	$nonce_ok = isset( $_POST['buckleup_contact_nonce'] )
		&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['buckleup_contact_nonce'] ) ), 'buckleup_contact' );

	if ( ! $nonce_ok ) {
		wp_safe_redirect( buckleup_contact_redirect_url( 'error' ) );
		exit;
	}

	// Honeypot: the theme renders a hidden `website` field no human fills in.
	// Non-empty → bot. Silently succeed, send nothing.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
	if ( ! empty( $_POST['website'] ) ) {
		wp_safe_redirect( buckleup_contact_redirect_url( 'success' ) );
		exit;
	}

	// Optional min-fill-time: if the form supplies a `bu_ts` (unix seconds set
	// when the form rendered), reject implausibly fast submits (< ~3s) as bots.
	// No-op if the field is absent, so it never blocks the current theme form.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
	if ( isset( $_POST['bu_ts'] ) && '' !== $_POST['bu_ts'] ) {
		$started  = (int) wp_unslash( $_POST['bu_ts'] );
		$min_fill = (int) apply_filters( 'buckleup_contact_min_fill_seconds', 3 );
		if ( $started > 0 && ( time() - $started ) < $min_fill ) {
			wp_safe_redirect( buckleup_contact_redirect_url( 'success' ) );
			exit;
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
	$result = buckleup_contact_validate( wp_unslash( $_POST ) );

	// Rate limit (per IP + per email). Over the cap → silently succeed, no send.
	$email = is_wp_error( $result ) ? '' : $result['email'];
	if ( ! buckleup_contact_rate_ok( $email ) ) {
		wp_safe_redirect( buckleup_contact_redirect_url( 'success' ) );
		exit;
	}

	$status = ( is_wp_error( $result ) || ! buckleup_contact_send_email( $result ) ) ? 'error' : 'success';

	wp_safe_redirect( buckleup_contact_redirect_url( $status ) );
	exit;
}
add_action( 'admin_post_buckleup_contact', 'buckleup_admin_post_contact_handler' );
add_action( 'admin_post_nopriv_buckleup_contact', 'buckleup_admin_post_contact_handler' );
