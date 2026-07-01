<?php
/**
 * Title: ICBC Class 4 Practice Exam Center
 * Slug: buckleup/exam
 * Inserter: no
 *
 * The dedicated, distraction-free, timed Practice Exam experience. ONE WP page
 * (slug `exam`, child of the practice hub, `noindex`) serves every mode via
 * `?mode={full|<category-slug>}`. Rendered on the `page-exam` template, which has
 * NO site header/footer — this pattern emits its OWN fixed chrome shell so the
 * candidate sees nothing but the exam.
 *
 * It reuses the SAME [data-quiz] engine (src/js/modules/quiz.js) and the .cat-*
 * palette as patterns/practice-test.php — the runner card / rail / results
 * renderer are shared verbatim. The exam-specific behaviour (briefing→consent
 * gating, the wall-clock timer + states + toasts + auto-submit, the exit dialog +
 * beforeunload guard) is gated in JS behind the presence of [data-exam-shell], so
 * the (now link-only) landing pages are untouched.
 *
 * Top to bottom this pattern renders:
 *   1. The fixed chrome shell (logo + centred title + live timer chip + Exit).
 *   2. The [data-quiz] mount at data-quiz-step="intro":
 *      - Screen 1 (intro): the server-rendered Briefing & Consent panel.
 *      - empty running / gate / results panels (the JS swaps them in).
 *   3. The exit-confirm dialog + the auto-submit overlay (hidden until armed).
 *
 * Purge note: like practice-test.php + quiz.js, category COLOUR is only ever set
 * via a literal data-cat="<N>" attribute + the .cat-* classes in app.css; the
 * timer state rides on data-timer + the bespoke literal .timer-* classes — never
 * a dynamically-built Tailwind string.
 *
 * Included at template-render time via `buckleup/section {"name":"exam"}`.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// The plugin must be active for the helpers to exist; bail gracefully if not.
if ( ! function_exists( 'buckleup_quiz_js_config' ) ) {
	return;
}

// Mode from the query string: a known category slug, else the full mock.
$bu_raw_mode  = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only mode switch, no state change.
$bu_is_cat    = buckleup_quiz_is_category( $bu_raw_mode );
$bu_mode      = $bu_is_cat ? $bu_raw_mode : 'full';

$bu_config    = buckleup_quiz_js_config( $bu_mode );
$bu_time      = (int) buckleup_quiz_time_limit( $bu_mode );        // seconds; 0 = untimed
$bu_pass_pct  = (int) buckleup_quiz_cfg( 'pass_pct', 80 );
$bu_total     = $bu_is_cat
	? (int) buckleup_quiz_cfg( 'category_total', 10 )
	: (int) buckleup_quiz_cfg( 'full_total', 50 );
$bu_max_att   = (int) buckleup_quiz_cfg( 'max_attempts', 3 );
$bu_hub_url   = buckleup_quiz_hub_url();
$bu_cat_label = $bu_is_cat ? buckleup_quiz_category_label( $bu_mode ) : '';

// MM:SS for the elevated time-limit row (server-rendered; JS re-renders live).
$bu_mins      = (int) floor( $bu_time / 60 );
$bu_secs      = $bu_time % 60;
$bu_time_disp = $bu_time > 0 ? sprintf( '%d:%02d', $bu_mins, $bu_secs ) : __( 'No limit', 'buckleup' );
// Plain-words minute count for the prose conditions ("45-minute timer").
$bu_time_words = (int) round( $bu_time / 60 );

// Subtitle + timer-helper phrasing differ between full and category.
if ( $bu_is_cat ) {
	$bu_subtitle = sprintf( /* translators: %s: category label */ __( 'ICBC Class 4: %s Practice', 'buckleup' ), $bu_cat_label );
} else {
	$bu_subtitle = __( 'ICBC Class 4 Knowledge: Full Practice Exam', 'buckleup' );
}
?>
<!-- wp:html -->
<div data-exam-shell class="min-h-screen bg-background">

	<!-- ============================================================
	     CHROME SHELL — fixed top bar. Logo (unlinked while running),
	     centred title + live timer chip, ghost Exit. A spacer follows.
	     ============================================================ -->
	<header data-exam-chrome class="fixed top-0 left-0 right-0 z-50 h-12 md:h-14 glass bg-background/95 backdrop-blur-xl border-b border-border">
		<div class="h-full max-w-6xl mx-auto px-4 flex items-center justify-between gap-3">

			<!-- Left: logo. Linked home on intro/results; unlinked while running
			     (a click-away mid-exam is an exit, so we strip the link in JS). -->
			<div data-exam-logo class="shrink-0">
				<a data-exam-logo-link href="<?php echo esc_url( $bu_hub_url ); ?>" class="flex items-center" aria-label="<?php esc_attr_e( 'BuckleUp Driving School', 'buckleup' ); ?>">
					<?php echo buckleup_logo( 'h-6 md:h-7 w-auto' ); // phpcs:ignore WordPress.Security.EscapeOutput — escaped within ?>
				</a>
			</div>

			<!-- Center: exam title + (running only) the live timer chip. -->
			<div class="flex-1 min-w-0 flex items-center justify-center gap-3">
				<span class="hidden sm:block text-sm font-semibold text-foreground text-center truncate">
					<?php esc_html_e( 'ICBC Class 4 Knowledge Test: Practice Exam', 'buckleup' ); ?>
				</span>
				<?php if ( $bu_time > 0 ) : ?>
					<!-- Timer chip — hidden until the exam is running (JS reveals + ticks it).
					     State rides on data-timer (calm|warn|urgent|expired) → literal .timer-*.
					     The label is aria-hidden (announced separately, throttled). -->
					<span data-quiz-timer data-timer="calm" hidden class="timer-calm inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-sm font-semibold tabular-nums transition-colors border border-transparent">
						<?php echo buckleup_icon( 'clock', 'w-4 h-4 shrink-0' ); // phpcs:ignore ?>
						<span data-quiz-timer-label aria-hidden="true"><?php echo esc_html( $bu_time_disp ); ?></span>
					</span>
					<!-- sr-only live region: announces ONLY at 10/5/2/1/0.5-min marks. -->
					<span data-quiz-timer-live aria-live="polite" class="sr-only"></span>
				<?php endif; ?>
			</div>

			<!-- Right: Exit. Running → opens the confirm dialog (JS); intro/results
			     → navigates straight to the hub. -->
			<button type="button" data-quiz-exit class="shrink-0 inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
				<span data-quiz-exit-label class="hidden sm:inline"><?php esc_html_e( 'Exit exam', 'buckleup' ); ?></span>
				<?php echo buckleup_icon( 'log-out', 'w-4 h-4 shrink-0' ); // phpcs:ignore ?>
			</button>
		</div>
	</header>
	<!-- Spacer matching the fixed chrome height. -->
	<div class="h-12 md:h-14" aria-hidden="true"></div>

	<!-- Fire-once timer toasts: fixed top-right just under the chrome. -->
	<div data-quiz-timer-toasts aria-live="assertive" class="fixed top-14 md:top-16 right-3 z-[60] flex flex-col items-end gap-2 pointer-events-none"></div>

	<!-- ============================================================
	     BODY — plain bg, no gradient/glow. The [data-quiz] mount.
	     ============================================================ -->
	<div class="px-4 pb-20 pt-6 md:pt-10">
		<div
			data-quiz
			data-quiz-mode="<?php echo esc_attr( $bu_mode ); ?>"
			data-quiz-config="<?php echo esc_attr( wp_json_encode( $bu_config ) ); ?>"
			data-quiz-step="intro"
			class="mx-auto"
		>

			<!-- ========================================================
			     SCREEN 1 — Briefing & Consent. Official instruction-panel
			     feel; NO marketing red except the Begin button.
			     ======================================================== -->
			<div data-quiz-panel="intro" class="max-w-3xl mx-auto">
				<div class="text-center mb-8">
					<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-muted text-muted-foreground text-xs font-semibold uppercase tracking-wider mb-4">
						<?php echo buckleup_icon( 'shield-check', 'w-4 h-4' ); // phpcs:ignore ?>
						<?php esc_html_e( 'Practice Examination', 'buckleup' ); ?>
					</div>
					<h1 data-quiz-focus tabindex="-1" class="text-3xl md:text-4xl font-bold tracking-tight text-foreground mb-2 outline-none"><?php esc_html_e( 'Before you begin', 'buckleup' ); ?></h1>
					<p class="text-base md:text-lg text-muted-foreground"><?php echo esc_html( $bu_subtitle ); ?></p>
				</div>

				<p class="text-muted-foreground leading-relaxed text-justify max-w-2xl mx-auto mb-8">
					<?php esc_html_e( "Give the conditions below a quick read. Once you hit Begin, the clock starts, so make sure you've got a quiet stretch of time before you tap it.", 'buckleup' ); ?>
				</p>

				<!-- Test details card. The time-limit row is the elevated eye-anchor. -->
				<div class="glass rounded-3xl border border-border p-6 md:p-8 mb-6">
					<div class="grid sm:grid-cols-2 gap-x-8 gap-y-4">
						<div class="flex items-center justify-between gap-3 sm:col-span-2 bg-primary/5 rounded-xl px-3 py-2">
							<span class="inline-flex items-center gap-2 text-sm text-muted-foreground"><?php echo buckleup_icon( 'clock', 'w-5 h-5 text-primary' ); // phpcs:ignore ?><?php esc_html_e( 'Time limit', 'buckleup' ); ?></span>
							<span class="text-2xl md:text-3xl font-bold text-foreground tabular-nums"><?php echo esc_html( $bu_time_disp ); ?></span>
						</div>
						<div class="flex items-center justify-between gap-3">
							<span class="text-sm text-muted-foreground"><?php esc_html_e( 'Questions', 'buckleup' ); ?></span>
							<span class="font-bold text-foreground tabular-nums"><?php echo esc_html( (string) $bu_total ); ?></span>
						</div>
						<div class="flex items-center justify-between gap-3">
							<span class="text-sm text-muted-foreground"><?php esc_html_e( 'Pass mark', 'buckleup' ); ?></span>
							<span class="font-bold text-foreground tabular-nums"><?php echo esc_html( $bu_pass_pct . '%' ); ?></span>
						</div>
						<div class="flex items-center justify-between gap-3">
							<span class="text-sm text-muted-foreground"><?php esc_html_e( 'Format', 'buckleup' ); ?></span>
							<span class="font-bold text-foreground text-right"><?php esc_html_e( 'One question at a time', 'buckleup' ); ?></span>
						</div>
						<div class="flex items-center justify-between gap-3">
							<span class="text-sm text-muted-foreground"><?php esc_html_e( 'Going back', 'buckleup' ); ?></span>
							<span class="font-bold text-foreground"><?php esc_html_e( 'Allowed', 'buckleup' ); ?></span>
						</div>
						<div class="flex items-center justify-between gap-3 sm:col-span-2">
							<span class="text-sm text-muted-foreground"><?php esc_html_e( 'Attempts left', 'buckleup' ); ?></span>
							<!-- JS fills this from a GET /quiz/status probe (Unlimited when signed in). -->
							<span data-quiz-attempts class="font-bold text-foreground tabular-nums"><?php echo esc_html( sprintf( /* translators: %d: max free attempts */ __( '%d free attempts', 'buckleup' ), $bu_max_att ) ); ?></span>
						</div>
					</div>
					<p class="text-sm text-muted-foreground mt-5 pt-5 border-t border-border leading-relaxed text-justify">
						<?php esc_html_e( "Finish up and you'll get your score right away, a breakdown by topic, a study report in your inbox, and (if you pass) a certificate you can keep.", 'buckleup' ); ?>
					</p>
				</div>

				<!-- Exam conditions — 4 ticks. -->
				<div class="mb-6">
					<h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground mb-3"><?php esc_html_e( 'Exam conditions', 'buckleup' ); ?></h2>
					<ul class="space-y-3">
						<?php
						$bu_conditions = array(
							__( 'One question at a time. You can go back and change an earlier answer any time before you submit.', 'buckleup' ),
							sprintf( /* translators: %d: time-limit minutes */ __( "The %d-minute timer starts the second you press Begin, and it can't be paused, so don't step away.", 'buckleup' ), $bu_time_words ),
							__( "Time runs out, and the exam submits itself. No do-overs.", 'buckleup' ),
							__( "Leave the page, refresh it, or close the tab, and the attempt's over. Whatever you'd answered is gone.", 'buckleup' ),
						);
						foreach ( $bu_conditions as $bu_cond ) :
							?>
							<li class="flex items-start gap-3">
								<?php echo buckleup_icon( 'check-circle', 'w-5 h-5 text-accent shrink-0 mt-0.5' ); // phpcs:ignore ?>
								<span class="text-sm text-foreground leading-relaxed"><?php echo esc_html( $bu_cond ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<!-- Integrity pledge (verbatim). -->
				<blockquote class="rounded-2xl border border-border bg-muted/40 p-5 text-sm leading-relaxed text-foreground mb-6">
					<?php esc_html_e( "I will complete this test on my own — with no AI tools, no search engines, no notes or apps, and no help from anyone else — and I'll answer as I would in the real ICBC exam. I understand this is a practice test to help me prepare, not the official ICBC knowledge test.", 'buckleup' ); ?>
				</blockquote>

				<!-- Consent checkbox + Begin. Begin stays disabled until ticked. -->
				<div class="glass rounded-2xl border border-border p-5 md:p-6">
					<label class="flex items-start gap-3 cursor-pointer select-none">
						<input type="checkbox" data-quiz-consent class="mt-0.5 h-5 w-5 shrink-0 rounded border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background accent-primary">
						<span class="text-sm text-foreground leading-relaxed"><?php esc_html_e( 'I have read and agree to the exam conditions and the integrity pledge above.', 'buckleup' ); ?></span>
					</label>
					<div class="mt-5 flex flex-col items-stretch sm:items-center gap-2">
						<button type="button" data-quiz-begin disabled class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-base font-semibold transition-all duration-200 h-12 px-8 bg-primary text-primary-foreground shadow-md hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50">
							<?php esc_html_e( 'Begin exam', 'buckleup' ); ?>
							<?php echo buckleup_icon( 'arrow-right', 'w-5 h-5' ); // phpcs:ignore ?>
						</button>
						<p data-quiz-begin-help class="text-xs text-muted-foreground text-center"><?php esc_html_e( 'Tick the box above to begin.', 'buckleup' ); ?></p>
					</div>
				</div>

				<!-- Out-of-attempts CTA — hidden until the status probe detects remaining===0. -->
				<div data-quiz-locked hidden class="glass rounded-2xl border border-border p-6 md:p-8 mt-6 text-center">
					<p class="text-muted-foreground text-justify mb-5 max-w-xl mx-auto">
						<?php esc_html_e( "You've used all your free attempts. Nice work putting in the prep. Want unlimited practice and a real instructor in your corner? Create a free account to keep going, or book a lesson with a BuckleUp instructor and walk into your ICBC test ready.", 'buckleup' ); ?>
					</p>
					<div class="flex flex-col sm:flex-row items-center justify-center gap-3">
						<a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="no-underline! inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-12 px-6 bg-primary text-primary-foreground shadow-md hover:bg-primary/90">
							<?php echo buckleup_icon( 'user', 'w-5 h-5' ); // phpcs:ignore ?>
							<?php esc_html_e( 'Create a free account', 'buckleup' ); ?>
						</a>
						<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="no-underline! inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-12 px-6 border-2 border-border bg-background text-foreground shadow-sm hover:bg-secondary">
							<?php echo buckleup_icon( 'calendar', 'w-5 h-5' ); // phpcs:ignore ?>
							<?php esc_html_e( 'Book a lesson', 'buckleup' ); ?>
						</a>
					</div>
				</div>

				<p class="text-xs text-muted-foreground text-center mt-6 max-w-xl mx-auto">
					<?php esc_html_e( 'This is a free practice exam from BuckleUp Driving School. It is not affiliated with ICBC.', 'buckleup' ); ?>
				</p>
			</div>

			<!-- running: the runner builds the live rail + question card here. -->
			<div data-quiz-panel="running" hidden class="max-w-4xl mx-auto"></div>

			<!-- gate: anonymous email + optional name capture (the runner reveals it). -->
			<div data-quiz-panel="gate" hidden class="glass rounded-3xl border border-border p-8 md:p-10 max-w-lg mx-auto">
				<div class="text-center mb-6">
					<span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary/10 text-primary mb-4">
						<?php echo buckleup_icon( 'mail', 'w-6 h-6' ); // phpcs:ignore ?>
					</span>
					<h2 data-quiz-focus tabindex="-1" class="text-2xl font-bold text-foreground mb-2 outline-none"><?php esc_html_e( 'Almost there!', 'buckleup' ); ?></h2>
					<p class="text-muted-foreground text-sm"><?php esc_html_e( "Pop in your email and we'll show you your results plus send over a study report.", 'buckleup' ); ?></p>
				</div>
				<form data-quiz-gate-form novalidate class="space-y-4">
					<div>
						<label for="bu-exam-name" class="<?php echo esc_attr( buckleup_label_class( 'mb-2 block' ) ); ?>">
							<?php esc_html_e( 'Full name', 'buckleup' ); ?>
							<span class="font-normal text-muted-foreground"><?php esc_html_e( '(optional, for your certificate)', 'buckleup' ); ?></span>
						</label>
						<input type="text" id="bu-exam-name" name="name" autocomplete="name" placeholder="<?php esc_attr_e( 'Jane Driver', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
					</div>
					<div>
						<label for="bu-exam-email" class="<?php echo esc_attr( buckleup_label_class( 'mb-2 block' ) ); ?>"><?php esc_html_e( 'Email address', 'buckleup' ); ?></label>
						<input type="email" id="bu-exam-email" name="email" required autocomplete="email" placeholder="<?php esc_attr_e( 'you@example.com', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
					</div>
					<!-- Honeypot: humans leave this blank. -->
					<div class="hidden" aria-hidden="true">
						<label for="bu-exam-website"><?php esc_html_e( 'Website', 'buckleup' ); ?></label>
						<input type="text" id="bu-exam-website" name="website" tabindex="-1" autocomplete="off">
					</div>
					<p data-quiz-gate-error hidden role="alert" aria-live="polite" class="text-sm text-destructive"></p>
					<button type="submit" data-quiz-gate-submit class="<?php echo esc_attr( buckleup_button_class( 'default', 'lg', 'w-full h-12 rounded-full' ) ); ?>">
						<?php esc_html_e( 'See my results', 'buckleup' ); ?>
					</button>
				</form>
			</div>

			<!-- results: the runner renders donut / breakdown / focus / cert here. -->
			<div data-quiz-panel="results" hidden class="max-w-3xl mx-auto"></div>
		</div>
	</div>

	<!-- ============================================================
	     EXIT-CONFIRM DIALOG — cloned from the console.js drawer pattern
	     (data-state=open|closed, overlay + Esc + scroll-lock). Hidden
	     until the chrome Exit is pressed mid-exam.
	     ============================================================ -->
	<div data-quiz-exit-dialog data-state="closed" hidden class="fixed inset-0 z-[70]">
		<div data-quiz-exit-overlay class="absolute inset-0 bg-black/50 backdrop-blur-sm data-[state=closed]:opacity-0 transition-opacity duration-200" data-state="closed"></div>
		<div class="absolute inset-0 flex items-center justify-center p-4">
			<div role="dialog" aria-modal="true" aria-labelledby="bu-exam-exit-title" data-state="closed" class="relative w-full max-w-md bg-card border border-border rounded-3xl shadow-2xl p-6 md:p-8 data-[state=closed]:opacity-0 data-[state=closed]:scale-95 transition-all duration-200">
				<h2 id="bu-exam-exit-title" class="text-xl font-bold text-foreground mb-2"><?php esc_html_e( 'Leave the exam?', 'buckleup' ); ?></h2>
				<p class="text-sm text-muted-foreground leading-relaxed text-justify mb-6">
					<?php esc_html_e( "Your progress won't be saved and this attempt is gone for good. You can always start fresh, but the timer resets from zero.", 'buckleup' ); ?>
				</p>
				<div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
					<button type="button" data-quiz-exit-cancel class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-11 px-6 border-2 border-border bg-background text-foreground shadow-sm hover:bg-secondary">
						<?php esc_html_e( 'Keep going', 'buckleup' ); ?>
					</button>
					<button type="button" data-quiz-exit-confirm class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-11 px-6 bg-destructive text-destructive-foreground shadow-md hover:bg-destructive/90">
						<?php esc_html_e( 'Leave exam', 'buckleup' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ============================================================
	     AUTO-SUBMIT OVERLAY — shown only when the timer expires
	     (data-timer="expired"). Inputs are disabled the instant 0:00 hits,
	     then the JS submits with the current answers after a short countdown.
	     ============================================================ -->
	<div data-quiz-expired-overlay hidden class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-background/95 backdrop-blur-md">
		<div role="alertdialog" aria-modal="true" aria-labelledby="bu-exam-expired-title" class="w-full max-w-md text-center">
			<span class="inline-flex items-center justify-center w-16 h-16 rounded-full timer-expired mb-5">
				<?php echo buckleup_icon( 'clock', 'w-8 h-8' ); // phpcs:ignore ?>
			</span>
			<h2 id="bu-exam-expired-title" tabindex="-1" class="text-2xl md:text-3xl font-bold text-foreground mb-2 outline-none"><?php esc_html_e( "Time's up.", 'buckleup' ); ?></h2>
			<p class="text-muted-foreground"><?php esc_html_e( 'Submitting your exam…', 'buckleup' ); ?> <span data-quiz-expired-count class="font-semibold text-foreground tabular-nums"></span></p>
		</div>
	</div>

</div>
<!-- /wp:html -->
