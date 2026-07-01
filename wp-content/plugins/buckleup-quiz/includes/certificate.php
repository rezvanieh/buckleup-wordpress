<?php
/**
 * Certificate of Completion.
 *
 * A standalone, print-to-PDF HTML certificate for PASSING attempts, served at
 * /quiz/certificate/{result_token}/ via a rewrite rule. No site chrome, no PDF
 * library — the browser's print dialog ("Download PDF") does the export. Reads
 * the attempt by token (incl. the optional name) and is gated to passed=1. A
 * lightweight REST verify endpoint confirms a certificate's authenticity.
 *
 * IMPORTANT: this is a BuckleUp PRACTICE achievement, not an official ICBC
 * document — the disclaimer is mandatory and always visible.
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Public certificate URL for a result token.
 *
 * @param string $token
 * @return string
 */
function buckleup_quiz_certificate_url( $token ) {
	return home_url( '/quiz/certificate/' . sanitize_text_field( $token ) . '/' );
}

/** Rewrite rule (registered on init + at activation, then flushed). */
function buckleup_quiz_register_cert_rewrite() {
	add_rewrite_rule( '^quiz/certificate/([a-f0-9]{32})/?$', 'index.php?bu_quiz_cert=$matches[1]', 'top' );
}
add_action( 'init', 'buckleup_quiz_register_cert_rewrite' );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'bu_quiz_cert';
	return $vars;
} );

/** Render the certificate page when the rewrite matches. */
add_action( 'template_redirect', function () {
	$token = get_query_var( 'bu_quiz_cert' );
	if ( ! $token ) {
		return;
	}
	$result = preg_match( '/^[a-f0-9]{32}$/', $token ) ? buckleup_quiz_get_result_by_token( $token ) : null;
	buckleup_quiz_render_certificate_page( $result );
	exit;
} );

/**
 * Human label for a test mode (full mock vs a category quiz).
 *
 * @param string $mode
 * @return string
 */
function buckleup_quiz_mode_label( $mode ) {
	if ( 'full' === $mode || '' === $mode ) {
		return __( 'ICBC Class 4 Knowledge: Full Practice Test', 'buckleup-quiz' );
	}
	/* translators: %s: category name. */
	return sprintf( __( 'ICBC Class 4: %s Practice Test', 'buckleup-quiz' ), buckleup_quiz_category_label( $mode ) );
}

/**
 * Output the standalone certificate (or a friendly fallback) and exit.
 *
 * @param array|null $result From buckleup_quiz_get_result_by_token().
 * @return void
 */
function buckleup_quiz_render_certificate_page( $result ) {
	$accent = '#dc2626';
	$ink    = '#111827';
	$muted  = '#6b7280';
	$green  = '#16a34a';
	$logo   = function_exists( 'buckleup_email_logo_url' ) ? buckleup_email_logo_url() : (string) get_site_icon_url( 180 );
	$home   = home_url( '/' );
	$hub    = function_exists( 'buckleup_quiz_hub_url' ) ? buckleup_quiz_hub_url() : $home;

	$not_available = ! $result || empty( $result['passed'] );

	header( 'Content-Type: text/html; charset=utf-8' );
	if ( $not_available ) {
		status_header( 404 );
	}
	nocache_headers();
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $not_available ? __( 'Certificate unavailable', 'buckleup-quiz' ) : __( 'BuckleUp Certificate of Completion', 'buckleup-quiz' ) ); ?></title>
<style>
	*{box-sizing:border-box}
	body{margin:0;background:#f3f4f6;color:<?php echo esc_attr( $ink ); ?>;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
	.wrap{max-width:1000px;margin:24px auto;padding:0 16px}
	.actions{text-align:center;margin:0 0 20px}
	.btn{display:inline-block;margin:4px;padding:11px 20px;border-radius:9px;font-weight:600;font-size:14px;text-decoration:none;cursor:pointer;border:0}
	.btn-primary{background:<?php echo esc_attr( $accent ); ?>;color:#fff}
	.btn-ghost{background:#fff;color:<?php echo esc_attr( $ink ); ?>;border:1px solid #e5e7eb}
	.cert{position:relative;background:#fff;border:3px solid <?php echo esc_attr( $accent ); ?>;border-radius:14px;padding:48px 56px;box-shadow:0 10px 40px rgba(0,0,0,.08)}
	.cert:before{content:"";position:absolute;inset:10px;border:1px solid #e5e7eb;border-radius:8px;pointer-events:none}
	.cert-head{display:flex;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid #e5e7eb;padding-bottom:18px;margin-bottom:8px}
	.cert-head img{height:46px;width:auto}
	.cert-kicker{text-align:right;font-weight:800;letter-spacing:.14em;color:<?php echo esc_attr( $accent ); ?>;font-size:13px;line-height:1.5}
	.cert-body{text-align:center;padding:26px 8px 6px}
	.lead{color:<?php echo esc_attr( $muted ); ?>;font-size:15px;margin:0 0 6px}
	.name{font-size:40px;font-weight:800;letter-spacing:.01em;margin:8px auto 4px;display:inline-block;border-bottom:2px solid #e5e7eb;padding:0 24px 8px;min-width:60%}
	.testname{font-size:18px;font-weight:600;margin:18px 0 22px}
	.tiles{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin:0 0 18px}
	.tile{min-width:130px;border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px}
	.tile .v{font-size:26px;font-weight:800;line-height:1}
	.tile .l{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:<?php echo esc_attr( $muted ); ?>;margin-top:6px}
	.badge{display:inline-block;background:<?php echo esc_attr( $green ); ?>;color:#fff;font-weight:700;font-size:13px;letter-spacing:.04em;padding:8px 16px;border-radius:999px;margin:4px 0 24px}
	.cert-foot{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;border-top:1px solid #e5e7eb;padding-top:18px;margin-top:8px;text-align:left}
	.sig{font-weight:700}.sig small{display:block;font-weight:400;color:<?php echo esc_attr( $muted ); ?>;font-size:12px;margin-top:2px}
	.verify{text-align:right;font-size:11px;color:<?php echo esc_attr( $muted ); ?>;line-height:1.6}
	.verify code{font-size:11px;color:<?php echo esc_attr( $ink ); ?>;word-break:break-all}
	.disclaimer{margin:18px 0 0;text-align:center;font-size:11px;color:<?php echo esc_attr( $muted ); ?>}
	.fallback{max-width:560px;margin:60px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:36px;text-align:center}
	@media print{
		@page{size:landscape;margin:12mm}
		body{background:#fff}
		[data-no-print]{display:none !important}
		.wrap{margin:0;max-width:none}
		.cert{box-shadow:none;border-radius:8px}
	}
</style>
</head>
<body>
<div class="wrap">
	<?php if ( $not_available ) : ?>
		<div class="fallback">
			<h1 style="margin:0 0 10px;font-size:22px;"><?php esc_html_e( 'Certificate not available', 'buckleup-quiz' ); ?></h1>
			<p style="color:<?php echo esc_attr( $muted ); ?>;line-height:1.6;">
				<?php echo $result
					? esc_html__( 'This attempt didn’t reach the pass mark, so there’s no certificate to show. But every practice run gets you closer. Try again!', 'buckleup-quiz' )
					: esc_html__( 'We couldn’t find a certificate for that link.', 'buckleup-quiz' ); ?>
			</p>
			<p style="margin-top:20px;"><a class="btn btn-primary" href="<?php echo esc_url( $hub ); ?>"><?php esc_html_e( 'Take the practice test', 'buckleup-quiz' ); ?></a></p>
		</div>
	<?php else :
		$name      = '' !== $result['name'] ? $result['name'] : __( 'BuckleUp Learner', 'buckleup-quiz' );
		$date      = mysql2date( 'F j, Y', $result['created_at'] );
		$pass_pct  = (int) buckleup_quiz_cfg( 'pass_pct', 80 );
		$verify    = esc_url( add_query_arg( 'token', $result['result_token'], home_url( '/wp-json/buckleup/v1/quiz/verify' ) ) );
		?>
		<div class="actions" data-no-print>
			<a class="btn btn-primary" href="#" onclick="window.print();return false;"><?php esc_html_e( '⬇ Download / Print PDF', 'buckleup-quiz' ); ?></a>
			<a class="btn btn-ghost" id="bu-cert-share" href="<?php echo esc_url( buckleup_quiz_certificate_url( $result['result_token'] ) ); ?>"><?php esc_html_e( '🔗 Share', 'buckleup-quiz' ); ?></a>
		</div>
		<div class="cert">
			<div class="cert-head">
				<?php if ( $logo ) : ?><a href="<?php echo esc_url( $home ); ?>"><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></a><?php endif; ?>
				<div class="cert-kicker"><?php esc_html_e( 'CERTIFICATE', 'buckleup-quiz' ); ?><br><?php esc_html_e( 'OF COMPLETION', 'buckleup-quiz' ); ?></div>
			</div>
			<div class="cert-body">
				<p class="lead"><?php esc_html_e( 'This certifies that', 'buckleup-quiz' ); ?></p>
				<div class="name"><?php echo esc_html( $name ); ?></div>
				<div class="testname"><?php
					/* translators: %s: test name. */
					printf( esc_html__( 'successfully completed the BuckleUp %s', 'buckleup-quiz' ), '<span style="color:' . esc_attr( $accent ) . '">' . esc_html( buckleup_quiz_mode_label( $result['mode'] ) ) . '</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?></div>
				<div class="tiles">
					<div class="tile"><div class="v"><?php echo (int) $result['pct']; ?>%</div><div class="l"><?php esc_html_e( 'Score', 'buckleup-quiz' ); ?></div></div>
					<div class="tile"><div class="v"><?php echo (int) $result['score']; ?>/<?php echo (int) $result['total']; ?></div><div class="l"><?php esc_html_e( 'Correct', 'buckleup-quiz' ); ?></div></div>
					<div class="tile"><div class="v" style="font-size:18px;"><?php echo esc_html( $date ); ?></div><div class="l"><?php esc_html_e( 'Date', 'buckleup-quiz' ); ?></div></div>
				</div>
				<?php /* translators: %d: pass percentage. */ ?>
				<div class="badge">✦ <?php printf( esc_html__( 'PASSED: above the %d%% pass mark', 'buckleup-quiz' ), $pass_pct ); ?></div>
			</div>
			<div class="cert-foot">
				<div class="sig"><?php echo esc_html( get_bloginfo( 'name' ) ); ?><small>buckleupdriving.ca · <?php esc_html_e( 'ICBC-certified instructors', 'buckleup-quiz' ); ?></small></div>
				<div class="verify"><?php esc_html_e( 'Verification', 'buckleup-quiz' ); ?>:<br><code><?php echo esc_html( $result['result_token'] ); ?></code><br><a href="<?php echo $verify; // phpcs:ignore ?>" style="color:<?php echo esc_attr( $muted ); ?>"><?php esc_html_e( 'Verify this certificate', 'buckleup-quiz' ); ?></a></div>
			</div>
			<p class="disclaimer"><?php esc_html_e( 'This is a BuckleUp practice-test achievement, not an official ICBC document or licence. It does not replace ICBC’s official Class 4 knowledge test or grant any driving privilege.', 'buckleup-quiz' ); ?></p>
		</div>
	<?php endif; ?>
</div>
<script>
	(function(){
		var s=document.getElementById('bu-cert-share');
		if(s&&navigator.share){s.addEventListener('click',function(e){e.preventDefault();navigator.share({title:document.title,url:s.href}).catch(function(){});});}
	})();
</script>
</body>
</html>
	<?php
}

/** REST: confirm a certificate's authenticity. GET /quiz/verify?token= */
add_action( 'rest_api_init', function () {
	register_rest_route( 'buckleup/v1', '/quiz/verify', array(
		'methods'             => 'GET',
		'callback'            => 'buckleup_quiz_rest_verify',
		'permission_callback' => '__return_true',
	) );
} );

function buckleup_quiz_rest_verify( WP_REST_Request $request ) {
	$token  = sanitize_text_field( (string) $request->get_param( 'token' ) );
	$result = buckleup_quiz_get_result_by_token( $token );
	if ( ! $result ) {
		return new WP_REST_Response( array( 'valid' => false ), 200 );
	}
	return new WP_REST_Response( array(
		'valid'  => true,
		'name'   => '' !== $result['name'] ? $result['name'] : null,
		'pct'    => $result['pct'],
		'passed' => $result['passed'],
		'test'   => buckleup_quiz_mode_label( $result['mode'] ),
		'date'   => mysql2date( 'F j, Y', $result['created_at'] ),
	), 200 );
}
