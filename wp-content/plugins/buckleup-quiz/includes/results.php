<?php
/**
 * Detailed results page.
 *
 * A persistent, shareable page at /icbc-class-4-knowledge-test/result/{token}/
 * (rewrite rule) showing a graded attempt's score, per-topic breakdown, and the
 * FULL answer review — every question with the taker's answer, the correct
 * answer, and the explanation. The result email links here. Self-contained
 * (inline CSS, brand-styled) so it renders identically regardless of theme, and
 * noindex (per-user).
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Public results URL for a result token.
 *
 * @param string $token
 * @return string
 */
function buckleup_quiz_result_url( $token ) {
	return home_url( '/' . buckleup_quiz_base_slug() . '/result/' . sanitize_text_field( $token ) . '/' );
}

/** Rewrite rule (registered on init + at activation, then flushed). */
function buckleup_quiz_register_result_rewrite() {
	add_rewrite_rule( '^' . buckleup_quiz_base_slug() . '/result/([a-f0-9]{32})/?$', 'index.php?bu_quiz_result=$matches[1]', 'top' );
}
add_action( 'init', 'buckleup_quiz_register_result_rewrite' );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'bu_quiz_result';
	return $vars;
} );

add_action( 'template_redirect', function () {
	$token = get_query_var( 'bu_quiz_result' );
	if ( ! $token ) {
		return;
	}
	$result = preg_match( '/^[a-f0-9]{32}$/', $token ) ? buckleup_quiz_get_result_by_token( $token ) : null;
	buckleup_quiz_render_results_page( $result );
	exit;
} );

/**
 * Render the detailed results page (or a friendly not-found) and exit.
 *
 * @param array|null $result From buckleup_quiz_get_result_by_token().
 * @return void
 */
function buckleup_quiz_render_results_page( $result ) {
	$accent = '#dc2626';
	$ink    = '#111827';
	$muted  = '#6b7280';
	$green  = '#16a34a';
	$amber  = '#f59e0b';
	$border = '#e5e7eb';
	$panel  = '#f9fafb';
	$logo   = function_exists( 'buckleup_email_logo_url' ) ? buckleup_email_logo_url() : (string) get_site_icon_url( 180 );
	$home   = home_url( '/' );
	$hub    = function_exists( 'buckleup_quiz_hub_url' ) ? buckleup_quiz_hub_url() : $home;
	$letters = array( 'A', 'B', 'C', 'D' );

	header( 'Content-Type: text/html; charset=utf-8' );
	nocache_headers();
	if ( ! $result ) {
		status_header( 404 );
	}
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $result ? __( 'Your practice test results — BuckleUp', 'buckleup-quiz' ) : __( 'Results not found', 'buckleup-quiz' ) ); ?></title>
<style>
	*{box-sizing:border-box}
	body{margin:0;background:#f3f4f6;color:<?php echo esc_attr( $ink ); ?>;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;line-height:1.5}
	a{color:inherit}
	.bar{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-bottom:1px solid <?php echo esc_attr( $border ); ?>}
	.bar-in{max-width:880px;margin:0 auto;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px}
	.bar img{height:34px;width:auto;display:block}
	.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:9px;font-weight:600;font-size:14px;text-decoration:none;border:1px solid <?php echo esc_attr( $border ); ?>;background:#fff;color:<?php echo esc_attr( $ink ); ?>;white-space:nowrap}
	.btn-primary{background:<?php echo esc_attr( $accent ); ?>;color:#fff;border-color:<?php echo esc_attr( $accent ); ?>}
	.wrap{max-width:880px;margin:0 auto;padding:24px 20px 64px}
	.card{background:#fff;border:1px solid <?php echo esc_attr( $border ); ?>;border-radius:16px;padding:28px;box-shadow:0 8px 30px rgba(15,23,41,.05)}
	.hero{text-align:center;margin-bottom:24px}
	.score{font-size:54px;font-weight:800;line-height:1}
	.score small{color:<?php echo esc_attr( $muted ); ?>;font-weight:500}
	.pct{font-size:22px;font-weight:700;margin-top:6px}
	.pill{display:inline-block;color:#fff;font-weight:700;font-size:13px;letter-spacing:.04em;padding:7px 16px;border-radius:999px;margin:14px 0 6px}
	.muted{color:<?php echo esc_attr( $muted ); ?>}
	.h2{font-size:20px;font-weight:700;margin:36px 0 14px}
	.bd-row{display:flex;align-items:center;gap:14px;padding:9px 0;border-bottom:1px solid <?php echo esc_attr( $border ); ?>}
	.bd-label{flex:0 0 38%;font-size:14px}
	.bd-track{flex:1;height:8px;background:<?php echo esc_attr( $border ); ?>;border-radius:4px;overflow:hidden}
	.bd-fill{height:100%;border-radius:4px}
	.bd-num{flex:0 0 56px;text-align:right;font-weight:700;font-size:13px}
	.q{background:#fff;border:1px solid <?php echo esc_attr( $border ); ?>;border-radius:14px;padding:20px 22px;margin-bottom:14px}
	.q-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}
	.q-tag{font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:<?php echo esc_attr( $muted ); ?>}
	.q-badge{flex:none;font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px}
	.q-text{font-size:16px;font-weight:600;margin:0 0 14px}
	.opt{display:flex;align-items:flex-start;gap:10px;padding:9px 12px;border-radius:9px;font-size:14px;margin-bottom:6px;border:1px solid transparent}
	.opt .ic{flex:none;width:20px;height:20px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;border:1px solid <?php echo esc_attr( $border ); ?>;color:<?php echo esc_attr( $muted ); ?>}
	.opt-correct{background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.3)}
	.opt-correct .ic{background:<?php echo esc_attr( $green ); ?>;color:#fff;border-color:<?php echo esc_attr( $green ); ?>}
	.opt-wrong{background:rgba(220,38,38,.06);border-color:rgba(220,38,38,.3)}
	.opt-wrong .ic{background:<?php echo esc_attr( $accent ); ?>;color:#fff;border-color:<?php echo esc_attr( $accent ); ?>}
	.opt-tag{margin-left:auto;font-size:11px;font-weight:700;align-self:center}
	.why{display:flex;gap:8px;background:<?php echo esc_attr( $panel ); ?>;border-radius:10px;padding:12px 14px;font-size:13.5px;color:<?php echo esc_attr( $muted ); ?>;margin-top:10px}
	.why b{color:<?php echo esc_attr( $ink ); ?>}
	.fallback{max-width:560px;margin:80px auto;text-align:center}
	@media (max-width:560px){ .bd-label{flex-basis:46%} .btn span.lbl{display:none} }
</style>
</head>
<body>
	<?php if ( ! $result ) : ?>
		<div class="fallback">
			<h1 style="font-size:22px;"><?php esc_html_e( 'Results not found', 'buckleup-quiz' ); ?></h1>
			<p class="muted"><?php esc_html_e( 'We couldn’t find results for that link. It may have expired.', 'buckleup-quiz' ); ?></p>
			<p style="margin-top:20px;"><a class="btn btn-primary" href="<?php echo esc_url( $hub ); ?>"><?php esc_html_e( 'Take the practice test', 'buckleup-quiz' ); ?></a></p>
		</div>
	<?php else :
		$passed   = ! empty( $result['passed'] );
		$pass_pct = (int) buckleup_quiz_cfg( 'pass_pct', 80 );
		$badge_bg = $passed ? $green : $accent;
		$name     = '' !== $result['name'] ? $result['name'] : '';
		$date     = mysql2date( 'F j, Y', $result['created_at'] );
		$retake   = function_exists( 'buckleup_quiz_mode_label' ) && 'full' !== $result['mode'] ? add_query_arg( 'mode', $result['mode'], trailingslashit( $hub ) . 'exam/' ) : trailingslashit( $hub ) . 'exam/?mode=full';
		?>
		<div class="bar"><div class="bar-in">
			<a href="<?php echo esc_url( $home ); ?>"><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"><?php else : ?><strong><?php bloginfo( 'name' ); ?></strong><?php endif; ?></a>
			<div style="display:flex;gap:8px;">
				<a class="btn" href="<?php echo esc_url( $hub ); ?>"><?php esc_html_e( 'All practice tests', 'buckleup-quiz' ); ?></a>
				<a class="btn btn-primary" href="<?php echo esc_url( $retake ); ?>">↻ <span class="lbl"><?php esc_html_e( 'Retake', 'buckleup-quiz' ); ?></span></a>
			</div>
		</div></div>

		<div class="wrap">
			<!-- Score hero -->
			<div class="card hero">
				<?php if ( $name ) : ?><p class="muted" style="margin:0 0 8px;"><?php /* translators: %s: name */ printf( esc_html__( 'Results for %s', 'buckleup-quiz' ), '<strong style="color:' . esc_attr( $ink ) . '">' . esc_html( $name ) . '</strong>' ); // phpcs:ignore ?></p><?php endif; ?>
				<div class="score"><?php echo (int) $result['score']; ?> <small>/ <?php echo (int) $result['total']; ?></small></div>
				<div class="pct" style="color:<?php echo esc_attr( $badge_bg ); ?>;"><?php echo (int) $result['pct']; ?>%</div>
				<div><span class="pill" style="background:<?php echo esc_attr( $badge_bg ); ?>;"><?php echo $passed ? esc_html__( '✓ PASSED', 'buckleup-quiz' ) : esc_html__( 'KEEP PRACTISING', 'buckleup-quiz' ); ?></span></div>
				<p class="muted" style="margin:4px 0 0;font-size:14px;">
					<?php
					/* translators: 1: pass percentage, 2: test name, 3: date */
					printf( esc_html__( 'The pass mark is %1$d%%. %2$s · %3$s', 'buckleup-quiz' ), $pass_pct, esc_html( function_exists( 'buckleup_quiz_mode_label' ) ? buckleup_quiz_mode_label( $result['mode'] ) : 'ICBC Class 4' ), esc_html( $date ) );
					?>
				</p>
				<?php if ( $passed && function_exists( 'buckleup_quiz_certificate_url' ) ) : ?>
					<p style="margin:18px 0 0;"><a class="btn btn-primary" href="<?php echo esc_url( buckleup_quiz_certificate_url( $result['result_token'] ) ); ?>">🎓 <?php esc_html_e( 'View your certificate', 'buckleup-quiz' ); ?></a></p>
				<?php endif; ?>
			</div>

			<!-- Breakdown -->
			<?php if ( ! empty( $result['breakdown'] ) ) : ?>
				<div class="h2"><?php esc_html_e( 'Your score by topic', 'buckleup-quiz' ); ?></div>
				<div class="card" style="padding:18px 24px;">
					<?php foreach ( $result['breakdown'] as $slug => $b ) :
						$c   = (int) $b['correct'];
						$t   = (int) $b['total'];
						$cp  = $t > 0 ? (int) round( $c / $t * 100 ) : 0;
						$col = $cp >= $pass_pct ? $green : ( $cp >= 50 ? $amber : $accent );
						?>
						<div class="bd-row">
							<div class="bd-label"><?php echo esc_html( buckleup_quiz_category_label( $slug ) ); ?></div>
							<div class="bd-track"><div class="bd-fill" style="width:<?php echo (int) $cp; ?>%;background:<?php echo esc_attr( $col ); ?>;"></div></div>
							<div class="bd-num" style="color:<?php echo esc_attr( $col ); ?>;"><?php echo $c . '/' . $t; ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Full review -->
			<div class="h2"><?php esc_html_e( 'Review your answers', 'buckleup-quiz' ); ?></div>
			<?php foreach ( (array) $result['review'] as $i => $r ) :
				$correct_i = (int) $r['correct_index'];
				$picked_i  = ( null !== $r['picked_index'] ) ? (int) $r['picked_index'] : -1;
				$is_ok     = ! empty( $r['is_correct'] );
				$badge     = $is_ok ? array( $green, '✓ ' . __( 'Correct', 'buckleup-quiz' ) ) : ( $picked_i < 0 ? array( $muted, __( 'Not answered', 'buckleup-quiz' ) ) : array( $accent, '✗ ' . __( 'Incorrect', 'buckleup-quiz' ) ) );
				?>
				<div class="q">
					<div class="q-top">
						<span class="q-tag"><?php echo esc_html( sprintf( /* translators: 1: n, 2: total */ __( 'Question %1$d of %2$d · %3$s', 'buckleup-quiz' ), (int) $i + 1, count( $result['review'] ), buckleup_quiz_category_label( $r['category'] ) ) ); ?></span>
						<span class="q-badge" style="background:<?php echo esc_attr( $badge[0] ); ?>1a;color:<?php echo esc_attr( $badge[0] ); ?>;"><?php echo esc_html( $badge[1] ); ?></span>
					</div>
					<p class="q-text"><?php echo esc_html( $r['question'] ); ?></p>
					<?php foreach ( (array) $r['options'] as $oi => $opt ) :
						$cls = '';
						$tag = '';
						if ( (int) $oi === $correct_i ) { $cls = 'opt-correct'; $tag = __( 'Correct answer', 'buckleup-quiz' ); }
						elseif ( (int) $oi === $picked_i ) { $cls = 'opt-wrong'; $tag = __( 'Your answer', 'buckleup-quiz' ); }
						?>
						<div class="opt <?php echo esc_attr( $cls ); ?>">
							<span class="ic"><?php echo ( 'opt-correct' === $cls ) ? '✓' : ( ( 'opt-wrong' === $cls ) ? '✗' : esc_html( $letters[ $oi ] ?? '' ) ); ?></span>
							<span><?php echo esc_html( $opt ); ?></span>
							<?php if ( $tag ) : ?><span class="opt-tag" style="color:<?php echo esc_attr( 'opt-correct' === $cls ? $green : $accent ); ?>;"><?php echo esc_html( $tag ); ?></span><?php endif; ?>
						</div>
					<?php endforeach; ?>
					<?php if ( ! empty( $r['explanation'] ) ) : ?>
						<div class="why"><span>💡</span><p style="margin:0;"><b><?php esc_html_e( 'Why:', 'buckleup-quiz' ); ?></b> <?php echo esc_html( $r['explanation'] ); ?></p></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<p class="muted" style="text-align:center;font-size:12px;margin-top:24px;"><?php esc_html_e( 'This is a free BuckleUp practice test — not the official ICBC knowledge test.', 'buckleup-quiz' ); ?></p>
		</div>
	<?php endif; ?>
</body>
</html>
	<?php
}
