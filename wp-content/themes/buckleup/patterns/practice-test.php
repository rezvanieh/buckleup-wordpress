<?php
/**
 * Title: ICBC Class 4 Practice Test
 * Slug: buckleup/practice-test
 * Inserter: no
 *
 * The free ICBC Class 4 knowledge practice-test HUB + the 12 category pages.
 * Driven by the buckleup-quiz plugin. The timed test itself lives on the
 * dedicated, distraction-free Practice Exam Center (patterns/exam.php); these
 * landing pages are marketing + SEO + navigation, and their CTAs LINK into the
 * exam (one page, ?mode-switched).
 *
 * Layout uses the full content width: a 2-column hero (copy + exam-at-a-glance),
 * a full-width "how it works", then a topics sidebar beside the sample questions.
 *
 * SEO: only buckleup_quiz_sample_questions() answers are shown on the page (the
 * Quiz/Question JSON-LD marks up EXACTLY these). The hub FAQ accordion reads
 * buckleup_quiz_hub_faqs() (same source as the FAQPage JSON-LD), so visible == schema.
 *
 * Purge note: category COLOUR is only ever set via a literal `data-cat="<N>"`
 * attribute (N from catIndex) + the `.cat-*` classes in app.css.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'buckleup_quiz_page_context' ) ) {
	return;
}

$bu_ctx       = buckleup_quiz_page_context();
$bu_is_cat    = ( 'category' === $bu_ctx['type'] );
$bu_cat       = $bu_is_cat ? $bu_ctx['category'] : '';
$bu_pass_pct  = (int) buckleup_quiz_cfg( 'pass_pct', 80 );
$bu_full      = (int) buckleup_quiz_cfg( 'full_total', 50 );
$bu_cat_total = (int) buckleup_quiz_cfg( 'category_total', 10 );
$bu_mins      = (int) round( ( function_exists( 'buckleup_quiz_time_limit' ) ? buckleup_quiz_time_limit( 'full' ) : 2700 ) / 60 );
$bu_cats      = buckleup_quiz_categories();
$bu_cat_index = buckleup_quiz_category_index_map();
$bu_hub_url   = buckleup_quiz_hub_url();
$bu_cat_idx   = ( $bu_is_cat && isset( $bu_cat_index[ $bu_cat ] ) ) ? (int) $bu_cat_index[ $bu_cat ] : 0;
$bu_cat_label = $bu_is_cat ? buckleup_quiz_category_label( $bu_cat ) : '';

$bu_samples = buckleup_quiz_sample_questions( $bu_cat, 6 );

$bu_stats     = function_exists( 'buckleup_quiz_aggregate_stats' ) ? buckleup_quiz_aggregate_stats() : array();
$bu_has_stats = ! empty( $bu_stats ) && (int) ( $bu_stats['tests_taken'] ?? 0 ) >= 25;

$bu_exam_url = add_query_arg( 'mode', $bu_is_cat ? $bu_cat : 'full', trailingslashit( $bu_hub_url ) . 'exam/' );

/** "Your exam at a glance" facts card (used in the hero). */
$bu_glance = static function () use ( $bu_full, $bu_pass_pct, $bu_mins ) {
	$facts = array(
		array( 'icon' => 'list-checks',  'label' => sprintf( /* translators: %d: questions */ __( '%d questions', 'buckleup' ), $bu_full ) ),
		array( 'icon' => 'shield-check', 'label' => sprintf( /* translators: %d: pass pct */ __( '%d%% to pass', 'buckleup' ), $bu_pass_pct ) ),
		array( 'icon' => 'clock',        'label' => sprintf( /* translators: %d: minutes */ __( '%d-minute timer', 'buckleup' ), $bu_mins ) ),
		array( 'icon' => 'book-open',    'label' => __( '12 commercial topics', 'buckleup' ) ),
		array( 'icon' => 'mail',         'label' => __( 'Instant + emailed results', 'buckleup' ) ),
		array( 'icon' => 'award',        'label' => __( 'Certificate when you pass', 'buckleup' ) ),
	);
	?>
	<div class="bg-card/80 backdrop-blur rounded-2xl border border-border p-6 shadow-lg shadow-black/5">
		<h2 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-4"><?php esc_html_e( 'Your exam at a glance', 'buckleup' ); ?></h2>
		<ul class="space-y-3.5">
			<?php foreach ( $facts as $f ) : ?>
				<li class="flex items-center gap-3 text-[15px] text-foreground">
					<span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-primary/10 text-primary shrink-0"><?php echo buckleup_icon( $f['icon'], 'w-4 h-4' ); // phpcs:ignore ?></span>
					<span class="font-medium"><?php echo esc_html( $f['label'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
};

/** "All 12 topics" navigation list (sticky sidebar). */
$bu_topics = static function () use ( $bu_cats, $bu_cat_index, $bu_cat, $bu_is_cat ) {
	?>
	<aside class="lg:sticky lg:top-28 self-start mb-10 lg:mb-0">
		<div class="glass rounded-2xl border border-border p-3">
			<div class="px-2 py-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground"><?php esc_html_e( 'All 12 topics', 'buckleup' ); ?></div>
			<ul class="space-y-0.5">
				<?php foreach ( $bu_cats as $bu_slug => $bu_meta ) : ?>
					<?php
					$bu_idx = isset( $bu_cat_index[ $bu_slug ] ) ? (int) $bu_cat_index[ $bu_slug ] : 0;
					$bu_on  = ( $bu_is_cat && $bu_slug === $bu_cat );
					?>
					<li>
						<a href="<?php echo esc_url( buckleup_quiz_category_url( $bu_slug ) ); ?>"
							data-cat="<?php echo esc_attr( (string) $bu_idx ); ?>"
							class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2 text-sm transition-colors <?php echo $bu_on ? 'cat-rail-active' : 'text-muted-foreground hover:bg-muted hover:text-foreground'; ?>"
							<?php echo $bu_on ? 'aria-current="page"' : ''; ?>>
							<span class="cat-dot w-2 h-2 rounded-full shrink-0"></span>
							<span class="cat-label leading-tight"><?php echo esc_html( $bu_meta['short'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</aside>
	<?php
};

/** Render one crawlable sample question (prompt + options + marked answer + why). */
$bu_render_sample = static function ( array $q, int $n ) {
	$letters = array( 'A', 'B', 'C', 'D' );
	?>
	<div data-reveal class="glass p-6 md:p-7 rounded-2xl border border-border">
		<div class="flex items-start gap-3 mb-4">
			<span class="shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary/10 text-primary text-sm font-bold"><?php echo esc_html( (string) $n ); ?></span>
			<h3 class="text-base md:text-lg font-semibold text-foreground leading-snug"><?php echo esc_html( $q['question'] ); ?></h3>
		</div>
		<ul class="space-y-2 mb-4">
			<?php foreach ( (array) $q['options'] as $oi => $opt ) : ?>
				<?php $bu_correct = ( (int) $oi === (int) $q['correct_index'] ); ?>
				<li class="flex items-start gap-3 rounded-lg px-3 py-2 text-sm <?php echo $bu_correct ? 'bg-accent/10 text-foreground font-medium' : 'text-muted-foreground'; ?>">
					<span class="shrink-0 mt-0.5">
						<?php if ( $bu_correct ) : ?>
							<?php echo buckleup_icon( 'check-circle', 'w-5 h-5 text-accent' ); // phpcs:ignore ?>
						<?php else : ?>
							<span class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-border text-[11px] font-semibold text-muted-foreground"><?php echo esc_html( $letters[ $oi ] ?? '' ); ?></span>
						<?php endif; ?>
					</span>
					<span><?php echo esc_html( $opt ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( ! empty( $q['explanation'] ) ) : ?>
			<div class="flex items-start gap-2 rounded-xl bg-muted/60 px-4 py-3 text-sm text-muted-foreground">
				<?php echo buckleup_icon( 'info', 'w-4 h-4 shrink-0 mt-0.5 text-primary' ); // phpcs:ignore ?>
				<p class="leading-relaxed"><span class="font-semibold text-foreground"><?php esc_html_e( 'Why:', 'buckleup' ); ?></span> <?php echo esc_html( $q['explanation'] ); ?></p>
			</div>
		<?php endif; ?>
	</div>
	<?php
};
?>
<!-- wp:html -->
<section class="py-12 md:py-16">
	<div class="container max-w-7xl mx-auto px-4">

	<?php if ( ! $bu_is_cat ) : /* ============================ HUB ============================ */ ?>

		<!-- HERO: 2-column (copy + exam-at-a-glance) so the full width is used. -->
		<div data-reveal class="relative overflow-hidden rounded-3xl border border-border glass p-8 md:p-12 mb-14">
			<div class="absolute inset-0 -z-10 bg-gradient-to-br from-primary/5 via-transparent to-accent/5"></div>
			<div class="lg:grid lg:grid-cols-[1fr_20rem] lg:gap-12 lg:items-center">
				<div>
					<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-sm font-semibold mb-5">
						<?php echo buckleup_icon( 'graduation-cap', 'w-4 h-4' ); // phpcs:ignore ?>
						<?php esc_html_e( 'ICBC Class 4 Knowledge Test', 'buckleup' ); ?>
					</div>
					<h1 class="text-3xl md:text-5xl font-bold tracking-tight mb-4 text-foreground text-balance">
						<?php esc_html_e( 'Pass Your ICBC Class 4 Knowledge Test — Free Practice, Built for BC', 'buckleup' ); ?>
					</h1>
					<p class="text-lg text-muted-foreground leading-relaxed text-pretty mb-6 max-w-2xl">
						<?php esc_html_e( "Sharpen up before the real exam with a free practice test that mirrors ICBC's Class 4 — same length, same 12 commercial-driving topics, instant scoring. No signup needed to start.", 'buckleup' ); ?>
					</p>
					<div class="flex flex-wrap items-center gap-2.5 mb-7 text-sm">
						<?php
						$bu_chips = array(
							array( 'icon' => 'list-checks',    'text' => sprintf( /* translators: %d: question count */ __( 'Know before you go (~%d Qs)', 'buckleup' ), $bu_full ) ),
							array( 'icon' => 'graduation-cap', 'text' => __( 'Find your weak topics', 'buckleup' ) ),
							array( 'icon' => 'shield-check',   'text' => sprintf( /* translators: %d: pass percentage */ __( 'Real %d%% pass mark', 'buckleup' ), $bu_pass_pct ) ),
							array( 'icon' => 'check',          'text' => __( '100% free, instant + emailed results', 'buckleup' ) ),
						);
						foreach ( $bu_chips as $bu_chip ) :
							?>
							<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-card/60 border border-border/50 text-muted-foreground">
								<?php echo buckleup_icon( $bu_chip['icon'], 'w-4 h-4 text-accent' ); // phpcs:ignore ?>
								<span><?php echo esc_html( $bu_chip['text'] ); ?></span>
							</span>
						<?php endforeach; ?>
					</div>
					<a href="<?php echo esc_url( $bu_exam_url ); ?>" class="<?php echo esc_attr( buckleup_button_class( 'default', 'lg', 'h-14 px-8 rounded-full text-lg shadow-xl shadow-primary/20' ) ); ?>">
						<?php echo buckleup_icon( 'graduation-cap', 'w-5 h-5' ); // phpcs:ignore ?>
						<?php esc_html_e( 'Start the Free Practice Test', 'buckleup' ); ?>
					</a>
					<p class="text-sm text-muted-foreground mt-4 max-w-2xl">
						<?php esc_html_e( "Built by BuckleUp's ICBC-certified instructors — a Tri-Cities driving school with a ~98% first-time pass rate since 2014.", 'buckleup' ); ?>
					</p>
				</div>
				<div class="mt-8 lg:mt-0"><?php $bu_glance(); ?></div>
			</div>
		</div>

		<!-- HOW IT WORKS — full width so the three steps breathe. -->
		<div class="mb-16">
			<h2 data-reveal class="text-2xl md:text-3xl font-bold text-foreground text-center mb-3"><?php esc_html_e( 'How the practice exam works', 'buckleup' ); ?></h2>
			<p data-reveal class="text-muted-foreground text-center mb-10 max-w-2xl mx-auto"><?php esc_html_e( 'A real exam-room experience — so test day feels familiar.', 'buckleup' ); ?></p>
			<div data-reveal-stagger="0.05" class="grid gap-6 md:grid-cols-3">
				<?php
				$bu_steps = array(
					array( 'icon' => 'shield-check', 'title' => __( '1. Agree to the conditions', 'buckleup' ), 'text' => __( 'A short briefing screen lays out the rules — your own work, no AI or help — and the timer. Tick to begin.', 'buckleup' ) ),
					array( 'icon' => 'clock',        'title' => __( '2. Take the timed exam', 'buckleup' ), 'text' => sprintf( /* translators: 1: questions, 2: minutes */ __( '%1$d questions, one at a time, %2$d minutes — distraction-free, just like the real ICBC test.', 'buckleup' ), $bu_full, $bu_mins ) ),
					array( 'icon' => 'award',        'title' => __( '3. Get your score & certificate', 'buckleup' ), 'text' => __( 'Instant score, a topic-by-topic breakdown, an emailed report — and a certificate when you pass.', 'buckleup' ) ),
				);
				foreach ( $bu_steps as $bu_step ) :
					?>
					<div data-reveal class="glass rounded-2xl border border-border p-7 hover-lift">
						<span class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-primary/10 text-primary mb-5"><?php echo buckleup_icon( $bu_step['icon'], 'w-6 h-6' ); // phpcs:ignore ?></span>
						<h3 class="text-lg font-semibold text-foreground mb-2 leading-snug"><?php echo esc_html( $bu_step['title'] ); ?></h3>
						<p class="text-[15px] text-muted-foreground leading-relaxed"><?php echo esc_html( $bu_step['text'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- TOPICS SIDEBAR + SAMPLE QUESTIONS -->
		<div class="lg:grid lg:grid-cols-[17rem_1fr] lg:gap-10 lg:items-start mb-16">
			<?php $bu_topics(); ?>
			<div class="min-w-0">
				<h2 data-reveal class="text-2xl md:text-3xl font-bold text-foreground mb-3"><?php esc_html_e( 'Sample questions from the test', 'buckleup' ); ?></h2>
				<p data-reveal class="text-muted-foreground mb-8 max-w-2xl"><?php esc_html_e( 'A preview with the correct answer and an explanation. Start the exam above for a full randomized set, or pick a topic from the left.', 'buckleup' ); ?></p>
				<?php if ( ! empty( $bu_samples ) ) : ?>
					<div data-reveal-stagger="0.05" class="grid gap-5 xl:grid-cols-2">
						<?php foreach ( $bu_samples as $bu_i => $bu_q ) : ?>
							<?php $bu_render_sample( $bu_q, (int) $bu_i + 1 ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- FAQ — full width, centred. -->
		<?php
		$bu_hub_faqs = function_exists( 'buckleup_quiz_hub_faqs' ) ? buckleup_quiz_hub_faqs() : array();
		if ( ! empty( $bu_hub_faqs ) ) :
			$bu_faq_items = array_map( static function ( $f ) {
				return array( 'question' => $f['question'], 'answer' => wpautop( esc_html( $f['answer'] ) ) );
			}, $bu_hub_faqs );
			?>
			<div class="mb-16 max-w-3xl mx-auto">
				<div class="text-center mb-10">
					<div data-reveal class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full glass border border-border/50 mb-4">
						<?php echo buckleup_icon( 'message-circle', 'w-4 h-4 text-primary' ); // phpcs:ignore ?>
						<span class="text-sm font-medium text-muted-foreground"><?php esc_html_e( 'FAQ', 'buckleup' ); ?></span>
					</div>
					<h2 data-reveal class="text-2xl md:text-3xl font-bold">
						<span class="text-foreground"><?php esc_html_e( 'ICBC Class 4 knowledge test ', 'buckleup' ); ?></span><span class="gradient-text"><?php esc_html_e( 'questions', 'buckleup' ); ?></span>
					</h2>
				</div>
				<div data-reveal>
					<?php buckleup_faq_accordion( array( 'items' => $bu_faq_items, 'first_open' => true ) ); ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Closing CTA — full-width band. -->
		<div data-reveal class="relative overflow-hidden rounded-3xl border border-border glass p-10 md:p-14 text-center">
			<div class="absolute inset-0 -z-10 bg-gradient-to-br from-primary/10 via-transparent to-accent/10"></div>
			<h2 class="text-2xl md:text-4xl font-bold text-foreground mb-3"><?php esc_html_e( 'Ready to test yourself?', 'buckleup' ); ?></h2>
			<p class="text-muted-foreground text-lg mb-7 max-w-xl mx-auto"><?php esc_html_e( 'Take the full timed mock exam now — free, no signup, instant results.', 'buckleup' ); ?></p>
			<a href="<?php echo esc_url( $bu_exam_url ); ?>" class="<?php echo esc_attr( buckleup_button_class( 'default', 'lg', 'h-14 px-8 rounded-full text-lg shadow-xl shadow-primary/20' ) ); ?>">
				<?php echo buckleup_icon( 'graduation-cap', 'w-5 h-5' ); // phpcs:ignore ?>
				<?php esc_html_e( 'Start the timed mock exam', 'buckleup' ); ?>
			</a>
		</div>

	<?php else : /* ========================== CATEGORY ========================== */ ?>

		<!-- Breadcrumb -->
		<nav aria-label="<?php esc_attr_e( 'Breadcrumb', 'buckleup' ); ?>" class="mb-5">
			<ol class="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-foreground transition-colors"><?php esc_html_e( 'Home', 'buckleup' ); ?></a></li>
				<li aria-hidden="true"><?php echo buckleup_icon( 'chevron-right', 'w-4 h-4' ); // phpcs:ignore ?></li>
				<li><a href="<?php echo esc_url( $bu_hub_url ); ?>" class="hover:text-foreground transition-colors"><?php esc_html_e( 'Practice Tests', 'buckleup' ); ?></a></li>
				<li aria-hidden="true"><?php echo buckleup_icon( 'chevron-right', 'w-4 h-4' ); // phpcs:ignore ?></li>
				<li aria-current="page" class="font-medium text-foreground"><?php echo esc_html( $bu_cat_label ); ?></li>
			</ol>
		</nav>

		<!-- Category hero band -->
		<div data-reveal data-cat="<?php echo esc_attr( (string) $bu_cat_idx ); ?>" class="relative overflow-hidden rounded-3xl border cat-accent-border cat-accent-soft p-7 md:p-9 mb-10">
			<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-card/70 border border-border/50 text-sm font-semibold mb-4">
				<span class="cat-dot w-2.5 h-2.5 rounded-full"></span>
				<span class="cat-accent-text"><?php echo esc_html( $bu_cat_label ); ?></span>
			</div>
			<h1 class="text-3xl md:text-4xl font-bold tracking-tight mb-3 text-foreground text-balance">
				<?php /* translators: %s: category label */ echo esc_html( sprintf( __( '%s — ICBC Class 4 Practice Questions', 'buckleup' ), $bu_cat_label ) ); ?>
			</h1>
			<p class="text-base md:text-lg text-muted-foreground leading-relaxed text-pretty max-w-2xl">
				<?php /* translators: %s: category label */ printf( esc_html__( 'Master %s before test day — practise the exact kind of questions ICBC asks, score instantly, and see where to focus.', 'buckleup' ), esc_html( $bu_cat_label ) ); ?>
			</p>
		</div>

		<!-- TOPICS SIDEBAR + main (practise CTA + samples) -->
		<div class="lg:grid lg:grid-cols-[17rem_1fr] lg:gap-10 lg:items-start">
			<?php $bu_topics(); ?>
			<div class="min-w-0 space-y-14">
				<div class="glass rounded-3xl border border-border p-8 md:p-12 text-center relative overflow-hidden">
					<div class="absolute inset-0 -z-10 bg-gradient-to-br from-primary/5 via-transparent to-accent/5"></div>
					<h2 class="text-2xl md:text-3xl font-bold text-foreground mb-3">
						<?php /* translators: %s: category label */ echo esc_html( sprintf( __( 'Practise %s', 'buckleup' ), $bu_cat_label ) ); ?>
					</h2>
					<p class="text-muted-foreground mb-8 max-w-xl mx-auto">
						<?php /* translators: 1: number of questions, 2: pass percentage */ printf( esc_html__( '%1$d focused questions on this topic, one at a time. Answer them all, then see your score and a full explained review — %2$d%% is a pass.', 'buckleup' ), (int) $bu_cat_total, (int) $bu_pass_pct ); ?>
					</p>
					<a href="<?php echo esc_url( $bu_exam_url ); ?>" class="<?php echo esc_attr( buckleup_button_class( 'default', 'lg', 'h-14 px-8 rounded-full text-lg shadow-xl shadow-primary/20' ) ); ?>">
						<?php echo buckleup_icon( 'graduation-cap', 'w-5 h-5' ); // phpcs:ignore ?>
						<?php /* translators: %s: category label */ echo esc_html( sprintf( __( 'Practise %s Now', 'buckleup' ), $bu_cat_label ) ); ?>
					</a>
				</div>

				<?php if ( ! empty( $bu_samples ) ) : ?>
					<div>
						<h2 data-reveal class="text-2xl md:text-3xl font-bold text-foreground mb-3"><?php esc_html_e( 'Sample questions', 'buckleup' ); ?></h2>
						<p data-reveal class="text-muted-foreground mb-8 max-w-2xl"><?php esc_html_e( 'A preview with the correct answer and an explanation. Start the practice above for a full set.', 'buckleup' ); ?></p>
						<div data-reveal-stagger="0.05" class="grid gap-5 xl:grid-cols-2">
							<?php foreach ( $bu_samples as $bu_i => $bu_q ) : ?>
								<?php $bu_render_sample( $bu_q, (int) $bu_i + 1 ); ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

	<?php endif; ?>

	</div>
</section>
<!-- /wp:html -->
