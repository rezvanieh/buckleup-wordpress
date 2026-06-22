<?php
/**
 * Branded HTML result email.
 *
 * Reuses buckleup_email_shell() from buckleup-core for the logo header + card +
 * footer (house style), and builds the result body: score, pass/fail badge,
 * per-category breakdown, a "focus your review on" list linking to the weakest
 * categories' practice pages, and a CTA. Degrades to a short plain message if
 * the core shell helper is unavailable.
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Build the result email body (inner rows) and wrap with the shared shell.
 *
 * @param array $result Output of buckleup_quiz_grade() (or get_result_by_token()).
 * @return string Full HTML document.
 */
function buckleup_quiz_result_email_html( $result ) {
	// Brand / system colours (blue/white system, table-safe inline styles).
	$blue       = '#0b5ce0'; // theme --primary
	$ink        = '#0f172a';
	$muted      = '#64748b';
	$border     = '#e2e8f0';
	$callout_bg = '#eff6ff';
	$page_bg    = '#eef2f7';
	$green      = '#16a34a';
	$amber      = '#f59e0b';
	$red        = '#dc2626';
	$home       = esc_url( home_url( '/' ) );

	$score    = (int) $result['score'];
	$total    = (int) $result['total'];
	$pct      = (int) $result['pct'];
	$passed   = ! empty( $result['passed'] );
	$pass_pct = (int) buckleup_quiz_cfg( 'pass_pct', 80 );

	$name  = isset( $result['name'] ) ? trim( (string) $result['name'] ) : '';
	$first = '' !== $name ? esc_html( explode( ' ', $name )[0] ) : '';

	// Three-state score block: pass (≥pass_pct) / borderline (≥60) / keep practising.
	if ( $passed ) {
		$state_bg   = '#f0fdf4';
		$state_col  = $green;
		$track      = '#dcfce7';
		$badge_text = esc_html__( 'YOU PASSED 🎉', 'buckleup-quiz' );
		/* translators: %d: pass percentage. */
		$opening = sprintf( esc_html__( 'Congratulations — you cleared the %d%% pass mark on your ICBC Class 4 practice test. That’s exactly the kind of preparation that turns into a first-time pass on the real thing.', 'buckleup-quiz' ), $pass_pct );
	} elseif ( $pct >= 60 ) {
		$state_bg   = '#fffbeb';
		$state_col  = $amber;
		$track      = '#fef3c7';
		$badge_text = esc_html__( 'SO CLOSE', 'buckleup-quiz' );
		/* translators: %d: pass percentage. */
		$opening = sprintf( esc_html__( 'So close — you’re nearly at the %d%% pass mark. One focused round on the topic below and you’ll be over the line.', 'buckleup-quiz' ), $pass_pct );
	} else {
		$state_bg   = '#fef2f2';
		$state_col  = $red;
		$track      = '#fee2e2';
		$badge_text = esc_html__( 'KEEP PRACTISING', 'buckleup-quiz' );
		/* translators: %d: pass percentage. */
		$opening = sprintf( esc_html__( 'Good effort — you’re not quite at the %d%% pass mark yet, and that’s exactly what practice is for. A focused round or two on the topic below and you’ll be ready.', 'buckleup-quiz' ), $pass_pct );
	}

	$greeting = $first
		/* translators: %s: first name. */
		? sprintf( esc_html__( 'Hi %s,', 'buckleup-quiz' ), $first )
		: esc_html__( 'Hi there,', 'buckleup-quiz' );

	// Email-safe progress bar: a 2-cell rounded bar filled to pct%.
	$bar = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 10px;"><tr>'
		. ( $pct > 0 ? '<td style="height:9px;background:' . $state_col . ';border-radius:5px 0 0 5px;width:' . $pct . '%;font-size:0;line-height:0;">&nbsp;</td>' : '' )
		. ( $pct < 100 ? '<td style="height:9px;background:' . $track . ';border-radius:' . ( $pct > 0 ? '0 5px 5px 0' : '5px' ) . ';font-size:0;line-height:0;">&nbsp;</td>' : '' )
		. '</tr></table>';

	// --- Greeting + opening ---
	$inner  = '<tr><td style="padding:6px 36px 0;">'
		. '<p style="margin:18px 0 8px;font:700 17px/1.4 Arial,Helvetica,sans-serif;color:' . $ink . ';">' . $greeting . '</p>'
		. '<p style="margin:0;font:400 15px/1.65 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . $opening . '</p>'
		. '</td></tr>';

	// --- Score block (state-coloured card) ---
	$inner .= '<tr><td style="padding:20px 36px 0;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $state_bg . ';border-radius:16px;"><tr><td style="padding:26px;text-align:center;">'
		. '<span style="display:inline-block;background:' . $state_col . ';color:#ffffff;font:700 12px/1 Arial,Helvetica,sans-serif;letter-spacing:.05em;padding:9px 16px;border-radius:999px;">' . $badge_text . '</span>'
		. '<div style="font:800 52px/1 Arial,Helvetica,sans-serif;color:' . $ink . ';margin:16px 0 4px;">' . $pct . '%</div>'
		. '<div style="font:400 15px/1.4 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . $score . ' / ' . $total . ' ' . esc_html__( 'correct', 'buckleup-quiz' ) . '</div>'
		. $bar
		/* translators: %d: passing percentage. */
		. '<div style="font:400 13px/1.5 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . sprintf( esc_html__( 'The ICBC pass mark is %d%%.', 'buckleup-quiz' ), $pass_pct ) . '</div>'
		. '</td></tr></table></td></tr>';

	// --- Detailed results CTA (always) — primary blue button + subtext ---
	if ( ! empty( $result['result_token'] ) && function_exists( 'buckleup_quiz_result_url' ) ) {
		$res_url = esc_url( buckleup_quiz_result_url( $result['result_token'] ) );
		$inner  .= '<tr><td style="padding:24px 36px 0;text-align:center;">'
			. '<a href="' . $res_url . '" style="display:inline-block;background:' . $blue . ';color:#ffffff;text-decoration:none;font:700 15px/1 Arial,Helvetica,sans-serif;padding:16px 30px;border-radius:999px;">' . esc_html__( 'View your detailed results & answers', 'buckleup-quiz' ) . '</a>'
			. '<div style="margin:10px 0 0;font:400 13px/1.5 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . esc_html__( 'See every question, your answer, and the correct answer.', 'buckleup-quiz' ) . '</div>'
			. '</td></tr>';
	}

	// --- Certificate CTA (pass only) ---
	if ( $passed && ! empty( $result['result_token'] ) && function_exists( 'buckleup_quiz_certificate_url' ) ) {
		$cert_url = esc_url( buckleup_quiz_certificate_url( $result['result_token'] ) );
		$inner   .= '<tr><td style="padding:12px 36px 0;text-align:center;">'
			. '<a href="' . $cert_url . '" style="display:inline-block;color:' . $blue . ';text-decoration:none;font:600 14px/1 Arial,Helvetica,sans-serif;">🎓 ' . esc_html__( 'View your Certificate of Completion', 'buckleup-quiz' ) . '</a>'
			. '</td></tr>';
	}

	// --- Topic-by-topic breakdown (label + coloured bar + count) ---
	$breakdown = is_array( $result['breakdown'] ) ? $result['breakdown'] : array();
	if ( $breakdown ) {
		$rows = '';
		foreach ( $breakdown as $slug => $b ) {
			$c     = (int) $b['correct'];
			$t     = (int) $b['total'];
			$cp    = $t > 0 ? (int) round( $c / $t * 100 ) : 0;
			$col   = $cp >= $pass_pct ? $green : ( $cp >= 50 ? $amber : $red );
			$dot   = '<span style="display:inline-block;width:8px;height:8px;border-radius:9px;background:' . $col . ';">&nbsp;</span>';
			$label = esc_html( buckleup_quiz_category_label( $slug ) );
			$rows .= '<tr>'
				. '<td style="padding:9px 0;border-bottom:1px solid ' . $border . ';font:400 14px/1.4 Arial,Helvetica,sans-serif;color:' . $ink . ';white-space:nowrap;">' . $dot . '&nbsp;&nbsp;' . $label . '</td>'
				. '<td style="padding:9px 12px;border-bottom:1px solid ' . $border . ';">'
				. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
				. ( $cp > 0 ? '<td style="height:6px;background:' . $col . ';border-radius:3px;width:' . $cp . '%;font-size:0;line-height:0;">&nbsp;</td>' : '' )
				. ( $cp < 100 ? '<td style="height:6px;background:' . $border . ';border-radius:3px;font-size:0;line-height:0;">&nbsp;</td>' : '' )
				. '</tr></table></td>'
				. '<td style="padding:9px 0;border-bottom:1px solid ' . $border . ';width:44px;text-align:right;font:700 13px/1.4 Arial,Helvetica,sans-serif;color:' . $col . ';">' . $c . '/' . $t . '</td>'
				. '</tr>';
		}
		$inner .= '<tr><td style="padding:28px 36px 4px;">'
			. '<div style="font:700 12px/1.4 Arial,Helvetica,sans-serif;color:' . $muted . ';text-transform:uppercase;letter-spacing:.06em;margin:0 0 8px;">' . esc_html__( 'Topic-by-topic breakdown', 'buckleup-quiz' ) . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table></td></tr>';
	}

	// --- Focus areas (weakest categories, linked) — light-blue callout ---
	$weak = buckleup_quiz_weakest_categories( $breakdown, 3 );
	if ( $weak ) {
		$items = '';
		foreach ( $weak as $slug ) {
			$label  = esc_html( buckleup_quiz_category_label( $slug ) );
			$url    = function_exists( 'buckleup_quiz_category_url' ) ? esc_url( buckleup_quiz_category_url( $slug ) ) : $home;
			$items .= '<div style="margin:0 0 6px;"><span style="display:inline-block;width:7px;height:7px;border-radius:9px;background:' . $blue . ';">&nbsp;</span>&nbsp;&nbsp;<a href="' . $url . '" style="color:' . $blue . ';text-decoration:none;font:600 15px/1.5 Arial,Helvetica,sans-serif;">' . $label . '</a></div>';
		}
		$inner .= '<tr><td style="padding:18px 36px 4px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $callout_bg . ';border-left:4px solid ' . $blue . ';border-radius:10px;"><tr><td style="padding:16px 20px;">'
			. '<div style="font:700 14px/1.4 Arial,Helvetica,sans-serif;color:' . $ink . ';margin:0 0 10px;">' . esc_html__( 'What to focus on next', 'buckleup-quiz' ) . '</div>'
			. $items . '</td></tr></table></td></tr>';
	}

	// --- CTAs: outlined "Book a lesson" + "Take another practice test" ---
	$retry_url = function_exists( 'buckleup_quiz_hub_url' ) ? esc_url( buckleup_quiz_hub_url() ) : $home;
	$book_url  = esc_url( home_url( '/contact/' ) );
	$inner    .= '<tr><td style="padding:26px 36px 32px;text-align:center;">'
		. '<a href="' . $book_url . '" style="display:inline-block;background:#ffffff;border:1.5px solid ' . $border . ';color:' . $ink . ';text-decoration:none;font:700 14px/1 Arial,Helvetica,sans-serif;padding:14px 26px;border-radius:999px;">' . esc_html__( 'Book a lesson with BuckleUp', 'buckleup-quiz' ) . '</a>'
		. '<div style="margin:16px 0 0;"><a href="' . $retry_url . '" style="color:' . $blue . ';text-decoration:underline;font:600 14px/1.5 Arial,Helvetica,sans-serif;">' . esc_html__( 'Take another practice test', 'buckleup-quiz' ) . '</a></div>'
		. '</td></tr>';

	return buckleup_quiz_email_document( $inner );
}

/**
 * Wrap the result rows in the branded email document: logo header + blue rule,
 * the card body, and the address/contact footer. Self-contained (table layout +
 * inline styles) for broad email-client support.
 *
 * @param string $inner Table rows for the card body.
 * @return string Full HTML document.
 */
function buckleup_quiz_email_document( $inner ) {
	$blue    = '#0b5ce0';
	$ink     = '#0f172a';
	$muted   = '#94a3b8';
	$page_bg = '#eef2f7';
	$logo    = function_exists( 'buckleup_email_logo_url' ) ? buckleup_email_logo_url() : '';
	$home    = esc_url( home_url( '/' ) );
	$name    = esc_html( get_bloginfo( 'name' ) );

	$addr  = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'street_address', '136 Maple Dr' ) : '136 Maple Dr';
	$city  = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'city', 'Port Moody' ) : 'Port Moody';
	$phone = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'phone', '(604) 441-3677' ) : '(604) 441-3677';
	$unsub = 'mailto:' . ( function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'email', 'info@buckleupdriving.ca' ) : 'info@buckleupdriving.ca' ) . '?subject=' . rawurlencode( 'Unsubscribe from practice emails' );

	$header = '<tr><td style="padding:32px 36px 0;text-align:center;">'
		. ( $logo ? '<a href="' . $home . '"><img src="' . esc_url( $logo ) . '" width="150" alt="' . $name . '" style="height:auto;max-width:150px;border:0;display:inline-block;"></a>' : '<div style="font:800 22px/1 Arial,Helvetica,sans-serif;color:' . $blue . ';">' . $name . '</div>' )
		. '<div style="margin:12px 0 0;font:700 11px/1.4 Arial,Helvetica,sans-serif;color:' . $muted . ';text-transform:uppercase;letter-spacing:.08em;">' . esc_html__( 'Driving School · Class 4 Practice Test', 'buckleup-quiz' ) . '</div>'
		. '</td></tr>'
		. '<tr><td style="padding:18px 36px 0;"><div style="height:3px;background:' . $blue . ';border-radius:2px;font-size:0;line-height:0;">&nbsp;</div></td></tr>';

	$footer = '<tr><td style="padding:28px 24px 8px;text-align:center;">'
		. '<div style="font:600 13px/1.6 Arial,Helvetica,sans-serif;color:#64748b;">' . $name . ' Ltd. · ' . esc_html( $addr ) . ', ' . esc_html( $city ) . ', BC</div>'
		. '<div style="font:400 13px/1.6 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . esc_html( $phone ) . ' · ' . esc_html__( 'Tri-Cities, British Columbia', 'buckleup-quiz' ) . '</div>'
		. '<div style="margin:10px 0 0;font:400 12px/1.6 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . esc_html__( 'You received this because you took a free practice test.', 'buckleup-quiz' )
		. ' <a href="' . esc_url( $unsub ) . '" style="color:' . $muted . ';text-decoration:underline;">' . esc_html__( 'Unsubscribe', 'buckleup-quiz' ) . '</a></div>'
		. '</td></tr>';

	return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting"></head>'
		. '<body style="margin:0;padding:0;background:' . $page_bg . ';-webkit-text-size-adjust:100%;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $page_bg . ';"><tr><td align="center" style="padding:28px 12px;">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 30px rgba(15,23,42,.06);">'
		. $header . $inner . '</table>'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">' . $footer . '</table>'
		. '</td></tr></table></body></html>';
}

/**
 * The N weakest categories (lowest % first), among those scoring below the pass
 * mark. Returns category slugs.
 *
 * @param array $breakdown category => {correct,total}
 * @param int   $limit
 * @return string[]
 */
function buckleup_quiz_weakest_categories( $breakdown, $limit = 3 ) {
	$scored = array();
	$pass   = (int) buckleup_quiz_cfg( 'pass_pct', 80 );
	foreach ( (array) $breakdown as $slug => $b ) {
		$t = (int) $b['total'];
		if ( $t < 1 ) {
			continue;
		}
		$pct = (int) round( (int) $b['correct'] / $t * 100 );
		if ( $pct < $pass ) {
			$scored[ $slug ] = $pct;
		}
	}
	asort( $scored );
	return array_slice( array_keys( $scored ), 0, max( 0, (int) $limit ) );
}

/**
 * Send the branded result email. From stays the authenticated mailbox (SMTP
 * mu-plugin); Reply-To the business inbox.
 *
 * @param string $email Recipient.
 * @param array  $result Graded result.
 * @return bool wp_mail() result.
 */
function buckleup_quiz_send_result_email( $email, $result ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return false;
	}

	$pct = (int) $result['pct'];
	if ( ! empty( $result['passed'] ) ) {
		/* translators: %d: percentage. */
		$subject = sprintf( __( '🎓 You passed your ICBC Class 4 practice test — %d%%', 'buckleup-quiz' ), $pct );
	} else {
		$subject = sprintf(
			/* translators: 1: score, 2: total, 3: percentage. */
			__( 'Your ICBC Class 4 practice test results: %1$d/%2$d (%3$d%%)', 'buckleup-quiz' ),
			(int) $result['score'],
			(int) $result['total'],
			$pct
		);
	}

	$body    = buckleup_quiz_result_email_html( $result );
	$reply   = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'email', get_option( 'admin_email' ) ) : get_option( 'admin_email' );
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		sprintf( 'Reply-To: %s <%s>', get_bloginfo( 'name' ), $reply ),
	);

	return wp_mail( $email, $subject, $body, $headers );
}
