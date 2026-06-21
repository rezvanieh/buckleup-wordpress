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
	$accent  = '#dc2626';
	$ink     = '#111827';
	$muted   = '#6b7280';
	$border  = '#e5e7eb';
	$panel   = '#f9fafb';
	$green   = '#16a34a';
	$home    = esc_url( home_url( '/' ) );

	$score  = (int) $result['score'];
	$total  = (int) $result['total'];
	$pct    = (int) $result['pct'];
	$passed = ! empty( $result['passed'] );

	$amber    = '#f59e0b';
	$pass_pct = (int) buckleup_quiz_cfg( 'pass_pct', 80 );

	$name  = isset( $result['name'] ) ? trim( (string) $result['name'] ) : '';
	$first = '' !== $name ? esc_html( explode( ' ', $name )[0] ) : '';

	$hero_bg     = $passed ? '#f0fdf4' : '#fef2f2';
	$score_color = $passed ? $green : $accent;
	$badge_text  = $passed ? esc_html__( 'PASSED', 'buckleup-quiz' ) : esc_html__( 'KEEP PRACTISING', 'buckleup-quiz' );
	$greeting    = $first
		/* translators: %s: first name. */
		? sprintf( esc_html__( 'Hi %s,', 'buckleup-quiz' ), $first )
		: esc_html__( 'Hi,', 'buckleup-quiz' );
	$opening     = $passed
		/* translators: %d: pass percentage. */
		? sprintf( esc_html__( 'Congratulations — you cleared the %d%% pass mark on your ICBC Class 4 practice test. That’s exactly the kind of preparation that turns into a first-time pass on the real thing.', 'buckleup-quiz' ), $pass_pct )
		/* translators: %d: pass percentage. */
		: sprintf( esc_html__( 'Good effort — you’re not quite at the %d%% pass mark yet, and that’s exactly what practice is for. A focused round or two on the topics below and you’ll be ready.', 'buckleup-quiz' ), $pass_pct );

	// Email-safe "ring" proxy: a 2-cell bar filled to pct%.
	$bar = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 8px;"><tr>'
		. '<td style="height:8px;background:' . $score_color . ';border-radius:4px 0 0 4px;width:' . $pct . '%;font-size:0;line-height:0;">&nbsp;</td>'
		. ( $pct < 100 ? '<td style="height:8px;background:' . $border . ';border-radius:0 4px 4px 0;font-size:0;line-height:0;">&nbsp;</td>' : '' )
		. '</tr></table>';

	// --- Greeting + opening ---
	$inner = '<tr><td style="padding:14px 32px 0;">'
		. '<p style="margin:14px 0 6px;font:400 15px/1.5 Arial,Helvetica,sans-serif;color:' . $ink . ';">' . $greeting . '</p>'
		. '<p style="margin:0 0 4px;font:400 15px/1.6 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . $opening . '</p>'
		. '</td></tr>';

	// --- Hero band (tinted card-within-card) ---
	$inner .= '<tr><td style="padding:14px 32px 0;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $hero_bg . ';border-radius:12px;"><tr><td style="padding:24px;text-align:center;">'
		. '<span style="display:inline-block;background:' . $score_color . ';color:#fff;font:700 12px/1 Arial,Helvetica,sans-serif;letter-spacing:.06em;padding:8px 14px;border-radius:999px;">' . $badge_text . '</span>'
		. '<div style="font:700 46px/1 Arial,Helvetica,sans-serif;color:' . $ink . ';margin:14px 0 2px;">' . $pct . '%</div>'
		. '<div style="font:400 16px/1.4 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . $score . ' / ' . $total . ' ' . esc_html__( 'correct', 'buckleup-quiz' ) . '</div>'
		. $bar
		/* translators: %d: passing percentage. */
		. '<div style="font:400 13px/1.5 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . sprintf( esc_html__( 'The ICBC pass mark is %d%%.', 'buckleup-quiz' ), $pass_pct ) . '</div>'
		. '</td></tr></table></td></tr>';

	// --- Detailed results CTA (always) — see every question + answers ---
	if ( ! empty( $result['result_token'] ) && function_exists( 'buckleup_quiz_result_url' ) ) {
		$res_url = esc_url( buckleup_quiz_result_url( $result['result_token'] ) );
		$inner  .= '<tr><td style="padding:22px 32px 0;text-align:center;">'
			. '<a href="' . $res_url . '" style="display:inline-block;background:' . $ink . ';color:#ffffff;text-decoration:none;font:600 15px/1 Arial,Helvetica,sans-serif;padding:14px 26px;border-radius:9px;">' . esc_html__( 'View your detailed results & answers', 'buckleup-quiz' ) . '</a>'
			. '<div style="margin:8px 0 0;font:400 12px/1.5 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . esc_html__( 'See every question, your answer, and the correct answer.', 'buckleup-quiz' ) . '</div>'
			. '</td></tr>';
	}

	// --- Certificate CTA (pass only) — the email's emotional peak ---
	if ( $passed && ! empty( $result['result_token'] ) && function_exists( 'buckleup_quiz_certificate_url' ) ) {
		$cert_url = esc_url( buckleup_quiz_certificate_url( $result['result_token'] ) );
		$inner   .= '<tr><td style="padding:22px 32px 0;text-align:center;">'
			. '<a href="' . $cert_url . '" style="display:inline-block;background:' . $accent . ';color:#ffffff;text-decoration:none;font:600 15px/1 Arial,Helvetica,sans-serif;padding:14px 26px;border-radius:9px;">🎓 ' . esc_html__( 'View your Certificate of Completion', 'buckleup-quiz' ) . '</a>'
			. '</td></tr>';
	}

	// --- Score by topic (label + colored bar + count) ---
	$breakdown = is_array( $result['breakdown'] ) ? $result['breakdown'] : array();
	if ( $breakdown ) {
		$rows = '';
		foreach ( $breakdown as $slug => $b ) {
			$c     = (int) $b['correct'];
			$t     = (int) $b['total'];
			$cp    = $t > 0 ? (int) round( $c / $t * 100 ) : 0;
			$col   = $cp >= $pass_pct ? $green : ( $cp >= 50 ? $amber : $accent );
			$label = esc_html( buckleup_quiz_category_label( $slug ) );
			$rows .= '<tr>'
				. '<td style="padding:8px 0;border-bottom:1px solid ' . $border . ';font:400 14px/1.4 Arial,Helvetica,sans-serif;color:' . $ink . ';">' . $label . '</td>'
				. '<td style="padding:8px 10px;border-bottom:1px solid ' . $border . ';width:90px;">'
				. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
				. '<td style="height:6px;background:' . $col . ';border-radius:3px;width:' . $cp . '%;font-size:0;line-height:0;">&nbsp;</td>'
				. ( $cp < 100 ? '<td style="height:6px;background:' . $border . ';border-radius:3px;font-size:0;line-height:0;">&nbsp;</td>' : '' )
				. '</tr></table></td>'
				. '<td style="padding:8px 0;border-bottom:1px solid ' . $border . ';width:50px;text-align:right;font:600 13px/1.4 Arial,Helvetica,sans-serif;color:' . $col . ';">' . $c . '/' . $t . '</td>'
				. '</tr>';
		}
		$inner .= '<tr><td style="padding:24px 32px 4px;">'
			. '<div style="font:600 12px/1.4 Arial,Helvetica,sans-serif;color:' . $muted . ';text-transform:uppercase;letter-spacing:.04em;margin:0 0 6px;">' . esc_html__( 'Topic-by-topic breakdown', 'buckleup-quiz' ) . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table></td></tr>';
	}

	// --- Focus areas (weakest categories, linked to their practice pages) ---
	$weak = buckleup_quiz_weakest_categories( $breakdown, 3 );
	if ( $weak ) {
		$items = '';
		foreach ( $weak as $slug ) {
			$label  = esc_html( buckleup_quiz_category_label( $slug ) );
			$url    = function_exists( 'buckleup_quiz_category_url' ) ? esc_url( buckleup_quiz_category_url( $slug ) ) : $home;
			$items .= '<li style="margin:0 0 6px;"><a href="' . $url . '" style="color:' . $accent . ';text-decoration:none;font:400 15px/1.5 Arial,Helvetica,sans-serif;">' . $label . '</a></li>';
		}
		$inner .= '<tr><td style="padding:16px 32px 4px;">'
			. '<div style="background:' . $panel . ';border-left:3px solid ' . $accent . ';border-radius:6px;padding:14px 18px;">'
			. '<div style="font:600 13px/1.4 Arial,Helvetica,sans-serif;color:' . $ink . ';margin:0 0 8px;">' . esc_html__( 'What to focus on next', 'buckleup-quiz' ) . '</div>'
			. '<ul style="margin:0;padding:0 0 0 18px;">' . $items . '</ul></div></td></tr>';
	}

	// --- CTA ---
	$retry_url = function_exists( 'buckleup_quiz_hub_url' ) ? esc_url( buckleup_quiz_hub_url() ) : $home;
	$book_url  = esc_url( home_url( '/contact/' ) );
	$inner    .= '<tr><td style="padding:24px 32px 30px;text-align:center;">'
		. '<a href="' . $book_url . '" style="display:inline-block;background:' . $ink . ';color:#ffffff;text-decoration:none;font:600 14px/1 Arial,Helvetica,sans-serif;padding:13px 24px;border-radius:8px;">' . esc_html__( 'Book a lesson with BuckleUp', 'buckleup-quiz' ) . '</a>'
		. '<div style="margin:14px 0 0;"><a href="' . $retry_url . '" style="color:' . $muted . ';text-decoration:underline;font:400 13px/1.5 Arial,Helvetica,sans-serif;">' . esc_html__( 'Take another practice test', 'buckleup-quiz' ) . '</a></div>'
		. '</td></tr>';

	$footer = sprintf(
		/* translators: %s: linked site domain. */
		esc_html__( 'You took this free practice test at %s. This is practice only and is not an official ICBC test.', 'buckleup-quiz' ),
		'<a href="' . $home . '" style="color:' . $muted . ';">buckleupdriving.ca</a>'
	);

	if ( function_exists( 'buckleup_email_shell' ) ) {
		return buckleup_email_shell( __( 'Your ICBC Class 4 practice test results', 'buckleup-quiz' ), $inner, $footer );
	}

	// Minimal fallback if core's shell helper is unavailable.
	return '<!DOCTYPE html><html><body style="margin:0;background:#f3f4f6;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;">'
		. $inner . '</table></td></tr></table></body></html>';
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
