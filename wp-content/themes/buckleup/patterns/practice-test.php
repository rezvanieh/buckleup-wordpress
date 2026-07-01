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
 * HUB layout (the conversion page): centred hero →
 * "two ways to practise" (Full Mock Exam vs Practice by Topic) → a 3-column grid
 * of all 12 topics (each links to its category page) → crawlable sample
 * questions + FAQ (kept below for the Quiz/FAQPage JSON-LD — schema == visible).
 * No sidebar: topics are reached by tapping their card.
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
$bu_counts    = function_exists( 'buckleup_quiz_category_counts' ) ? buckleup_quiz_category_counts() : array();
$bu_total_q   = array_sum( $bu_counts ) ?: 0;
$bu_hub_url   = buckleup_quiz_hub_url();
$bu_cat_idx   = ( $bu_is_cat && isset( $bu_cat_index[ $bu_cat ] ) ) ? (int) $bu_cat_index[ $bu_cat ] : 0;
$bu_cat_label = $bu_is_cat ? buckleup_quiz_category_label( $bu_cat ) : '';
$bu_cat_count = ( $bu_is_cat && isset( $bu_counts[ $bu_cat ] ) ) ? (int) $bu_counts[ $bu_cat ] : $bu_cat_total;

$bu_samples = buckleup_quiz_sample_questions( $bu_cat, 6 );

$bu_exam_url = add_query_arg( 'mode', $bu_is_cat ? $bu_cat : 'full', trailingslashit( $bu_hub_url ) . 'exam/' );

// The 3 featured topics shown on the "Practice by Topic" card.
$bu_featured = array( 'air-brakes', 'pre-trip-inspections', 'hours-of-service' );

/** Reusable button class strings (literal, purge-safe). */
$bu_btn_primary = 'no-underline! inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary px-6 h-12 text-primary-foreground font-semibold text-[15px] shadow-lg shadow-primary/25 hover:bg-primary/90 transition-colors';
$bu_btn_outline = 'no-underline! inline-flex w-full items-center justify-center gap-2 rounded-full bg-card border border-border px-6 h-12 text-primary font-semibold text-[15px] hover:bg-secondary transition-colors';

/** One topic card (link → category page). Colour via data-cat. */
$bu_topic_card = static function ( $slug, $meta, $idx, $count ) {
	?>
	<a href="<?php echo esc_url( buckleup_quiz_category_url( $slug ) ); ?>" data-cat="<?php echo esc_attr( (string) $idx ); ?>"
		class="no-underline! group flex items-center gap-4 rounded-2xl border border-border bg-card px-5 py-4 shadow-sm hover:border-primary/40 hover:shadow-md transition-all">
		<span class="cat-accent-soft inline-flex items-center justify-center w-11 h-11 rounded-xl shrink-0"><?php echo buckleup_icon( buckleup_quiz_category_icon( $slug ), 'cat-accent-text w-5 h-5' ); // phpcs:ignore ?></span>
		<span class="flex-1 min-w-0">
			<span class="block font-semibold text-foreground leading-tight"><?php echo esc_html( $meta['short'] ); ?></span>
			<span class="block text-sm text-muted-foreground mt-0.5"><?php /* translators: %d: question count */ printf( esc_html__( '%d practice questions', 'buckleup' ), (int) $count ); ?></span>
		</span>
		<span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg bg-secondary text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
			<?php echo buckleup_icon( 'arrow-right', 'w-4 h-4' ); // phpcs:ignore ?>
		</span>
	</a>
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
	<div class="container max-w-6xl mx-auto px-4">

	<?php if ( ! $bu_is_cat ) : /* ============================ HUB ============================ */ ?>

		<!-- HERO — centred. -->
		<div class="text-center max-w-3xl mx-auto mb-14 md:mb-20">
			<span class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 text-sm font-semibold tracking-wide text-primary mb-6">
				<span class="w-1.5 h-1.5 rounded-full bg-primary"></span><?php esc_html_e( 'FREE · NO SIGNUP TO START', 'buckleup' ); ?>
			</span>
			<h1 class="text-[2.5rem] leading-[1.04] md:text-6xl md:leading-[1.02] font-black tracking-tight text-foreground text-balance mb-5">
				<?php esc_html_e( 'Pass your ICBC Class 4 knowledge test the first time.', 'buckleup' ); ?>
			</h1>
			<p class="text-lg md:text-xl text-muted-foreground leading-relaxed text-pretty text-justify max-w-2xl mx-auto">
				<?php esc_html_e( 'Same length as the real ICBC exam, the same 12 commercial topics, and a score the moment you finish. Test day should feel familiar, not scary.', 'buckleup' ); ?>
			</p>
		</div>

		<!-- TWO WAYS TO PRACTISE. -->
		<p class="text-center text-xs md:text-sm font-semibold uppercase tracking-[0.12em] text-muted-foreground mb-6"><?php esc_html_e( 'Two ways to practise · pick one to start', 'buckleup' ); ?></p>
		<div class="grid lg:grid-cols-2 gap-5 md:gap-6 mb-16 md:mb-20 items-stretch">

			<!-- Full Mock Exam (highlighted). -->
			<div class="relative rounded-3xl border-2 border-primary bg-card p-7 md:p-8 pt-9 shadow-xl shadow-primary/10 flex flex-col">
				<span class="absolute -top-3 left-7 inline-flex items-center rounded-full bg-primary px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-primary-foreground shadow-md"><?php esc_html_e( 'Most popular', 'buckleup' ); ?></span>
				<div class="flex items-center gap-3 mb-4">
					<span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary/10 text-primary shrink-0"><?php echo buckleup_icon( 'clock', 'w-5 h-5' ); // phpcs:ignore ?></span>
					<div>
						<div class="text-xs font-bold uppercase tracking-wider text-primary"><?php esc_html_e( 'Exam Simulation', 'buckleup' ); ?></div>
						<h2 class="text-xl md:text-2xl font-bold text-foreground leading-tight"><?php esc_html_e( 'Full Mock Exam', 'buckleup' ); ?></h2>
					</div>
				</div>
				<p class="text-[15px] text-muted-foreground leading-relaxed text-justify mb-5">
					<?php /* translators: 1: questions, 2: minutes, 3: pass pct */ printf( esc_html__( '%1$d mixed questions, a %2$d-minute clock, the same %3$d%% pass mark ICBC uses. If you can handle this, you can handle the real thing.', 'buckleup' ), (int) $bu_full, (int) $bu_mins, (int) $bu_pass_pct ); ?>
				</p>
				<div class="grid grid-cols-3 sm:flex sm:flex-wrap gap-1.5 sm:gap-2 mb-6">
					<?php
					$bu_mock_chips = array(
						array( 'icon' => 'clock',   'text' => sprintf( /* translators: %d: minutes */ __( '%d-min timer', 'buckleup' ), $bu_mins ) ),
						array( 'icon' => 'shuffle', 'text' => sprintf( /* translators: %d: questions */ __( '%d randomized', 'buckleup' ), $bu_full ) ),
						array( 'icon' => 'award',   'text' => __( 'Certificate', 'buckleup' ) ),
					);
					foreach ( $bu_mock_chips as $bu_chip ) :
						?>
						<span class="inline-flex items-center justify-center sm:justify-start gap-1 sm:gap-1.5 rounded-lg bg-secondary px-1.5 sm:px-2.5 py-1.5 text-[11px] sm:text-xs font-medium text-muted-foreground text-center whitespace-nowrap">
							<?php echo buckleup_icon( $bu_chip['icon'], 'w-3 h-3 sm:w-3.5 sm:h-3.5 shrink-0' ); // phpcs:ignore ?><?php echo esc_html( $bu_chip['text'] ); ?>
						</span>
					<?php endforeach; ?>
				</div>
				<div class="mt-auto">
					<a href="<?php echo esc_url( $bu_exam_url ); ?>" class="<?php echo esc_attr( $bu_btn_primary ); ?>">
						<?php esc_html_e( 'Start the timed exam', 'buckleup' ); ?><?php echo buckleup_icon( 'arrow-right', 'w-4 h-4' ); // phpcs:ignore ?>
					</a>
				</div>
			</div>

			<!-- Practice by Topic. -->
			<div class="rounded-3xl border border-border bg-card p-7 md:p-8 shadow-sm flex flex-col">
				<div class="flex items-center gap-3 mb-4">
					<span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-secondary text-foreground shrink-0"><?php echo buckleup_icon( 'layout-dashboard', 'w-5 h-5' ); // phpcs:ignore ?></span>
					<div>
						<div class="text-xs font-bold uppercase tracking-wider text-muted-foreground"><?php esc_html_e( 'Untimed · 12 topics', 'buckleup' ); ?></div>
						<h2 class="text-xl md:text-2xl font-bold text-foreground leading-tight"><?php esc_html_e( 'Practice by Topic', 'buckleup' ); ?></h2>
					</div>
				</div>
				<p class="text-[15px] text-muted-foreground leading-relaxed text-justify mb-5">
					<?php esc_html_e( 'Work through one topic at a time, at your own speed. Every question comes with the right answer and a plain-English explanation, so you actually understand it instead of just memorizing it.', 'buckleup' ); ?>
				</p>
				<div class="flex flex-wrap gap-1.5 sm:gap-2 mb-6">
					<?php foreach ( $bu_featured as $bu_fslug ) : ?>
						<?php if ( isset( $bu_cats[ $bu_fslug ] ) ) : ?>
							<span data-cat="<?php echo esc_attr( (string) ( $bu_cat_index[ $bu_fslug ] ?? 0 ) ); ?>" class="inline-flex items-center gap-1 sm:gap-1.5 rounded-lg bg-secondary px-1.5 sm:px-2.5 py-1.5 text-[11px] sm:text-xs font-medium text-muted-foreground whitespace-nowrap">
								<?php echo buckleup_icon( buckleup_quiz_category_icon( $bu_fslug ), 'cat-accent-text w-3 h-3 sm:w-3.5 sm:h-3.5 shrink-0' ); // phpcs:ignore ?><?php echo esc_html( $bu_cats[ $bu_fslug ]['short'] ); ?>
							</span>
						<?php endif; ?>
					<?php endforeach; ?>
					<span class="inline-flex items-center rounded-lg border border-dashed border-border px-1.5 sm:px-2.5 py-1.5 text-[11px] sm:text-xs font-medium text-muted-foreground whitespace-nowrap"><?php esc_html_e( '+9 more', 'buckleup' ); ?></span>
				</div>
				<div class="mt-auto">
					<a href="#bu-topics" class="<?php echo esc_attr( $bu_btn_outline ); ?>">
						<?php esc_html_e( 'Browse the 12 topics', 'buckleup' ); ?><?php echo buckleup_icon( 'arrow-right', 'w-4 h-4' ); // phpcs:ignore ?>
					</a>
				</div>
			</div>
		</div>

		<!-- ALL 12 TOPICS GRID. -->
		<div id="bu-topics" class="mb-16 md:mb-20 scroll-mt-28">
			<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
				<h2 class="text-2xl md:text-3xl font-bold text-foreground"><?php esc_html_e( 'All 12 commercial topics', 'buckleup' ); ?></h2>
				<p class="text-sm text-muted-foreground"><?php esc_html_e( 'Tap any topic to practise it on its own.', 'buckleup' ); ?></p>
			</div>
			<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
				<?php foreach ( $bu_cats as $bu_slug => $bu_meta ) : ?>
					<?php $bu_topic_card( $bu_slug, $bu_meta, (int) ( $bu_cat_index[ $bu_slug ] ?? 0 ), (int) ( $bu_counts[ $bu_slug ] ?? 0 ) ); ?>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- SAMPLE QUESTIONS (crawlable — Quiz/Question JSON-LD marks up exactly these). -->
		<?php if ( ! empty( $bu_samples ) ) : ?>
			<div class="mb-16 md:mb-20">
				<div class="text-center max-w-2xl mx-auto mb-8">
					<h2 data-reveal class="text-2xl md:text-3xl font-bold text-foreground mb-3"><?php esc_html_e( 'Try a few sample questions', 'buckleup' ); ?></h2>
					<p data-reveal class="text-muted-foreground text-justify"><?php esc_html_e( 'A quick look at the kind of questions you\'ll actually get, answer shown, plus why. Scroll up when you\'re ready for the full set.', 'buckleup' ); ?></p>
				</div>
				<div data-reveal-stagger="0.05" class="grid gap-5 md:grid-cols-2">
					<?php foreach ( $bu_samples as $bu_i => $bu_q ) : ?>
						<?php $bu_render_sample( $bu_q, (int) $bu_i + 1 ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- FAQ — full width, centred (FAQPage JSON-LD source == this accordion). -->
		<?php
		$bu_hub_faqs = function_exists( 'buckleup_quiz_hub_faqs' ) ? buckleup_quiz_hub_faqs() : array();
		if ( ! empty( $bu_hub_faqs ) ) :
			$bu_faq_items = array_map( static function ( $f ) {
				return array( 'question' => $f['question'], 'answer' => wpautop( esc_html( $f['answer'] ) ) );
			}, $bu_hub_faqs );
			?>
			<div class="max-w-3xl mx-auto">
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

	<?php else : /* ========================== CATEGORY ========================== */ ?>

		<!-- Breadcrumb -->
		<nav aria-label="<?php esc_attr_e( 'Breadcrumb', 'buckleup' ); ?>" class="mb-5">
			<ol class="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="no-underline! hover:text-foreground transition-colors"><?php esc_html_e( 'Home', 'buckleup' ); ?></a></li>
				<li aria-hidden="true"><?php echo buckleup_icon( 'chevron-right', 'w-4 h-4' ); // phpcs:ignore ?></li>
				<li><a href="<?php echo esc_url( $bu_hub_url ); ?>" class="no-underline! hover:text-foreground transition-colors"><?php esc_html_e( 'Practice Tests', 'buckleup' ); ?></a></li>
				<li aria-hidden="true"><?php echo buckleup_icon( 'chevron-right', 'w-4 h-4' ); // phpcs:ignore ?></li>
				<li aria-current="page" class="font-medium text-foreground"><?php echo esc_html( $bu_cat_label ); ?></li>
			</ol>
		</nav>

		<!-- Category hero band -->
		<div data-reveal data-cat="<?php echo esc_attr( (string) $bu_cat_idx ); ?>" class="relative overflow-hidden rounded-3xl border cat-accent-border cat-accent-soft p-7 md:p-9 mb-10">
			<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-card/70 border border-border/50 text-sm font-semibold mb-4">
				<?php echo buckleup_icon( buckleup_quiz_category_icon( $bu_cat ), 'cat-accent-text w-4 h-4' ); // phpcs:ignore ?>
				<span class="cat-accent-text"><?php echo esc_html( $bu_cat_label ); ?></span>
			</div>
			<h1 class="text-3xl md:text-4xl font-bold tracking-tight mb-3 text-foreground text-balance">
				<?php /* translators: %s: category label */ echo esc_html( sprintf( __( '%s: ICBC Class 4 Practice Questions', 'buckleup' ), $bu_cat_label ) ); ?>
			</h1>
			<p class="text-base md:text-lg text-muted-foreground leading-relaxed text-pretty text-justify">
				<?php /* translators: %s: category label */ printf( esc_html__( 'Get comfortable with %s before test day. Same style of questions ICBC actually asks, an instant score, and a clear look at where you need more practice.', 'buckleup' ), esc_html( $bu_cat_label ) ); ?>
			</p>
		</div>

		<!-- Practise CTA + samples (no sidebar). -->
		<div class="space-y-14">
			<div class="glass rounded-3xl border border-border p-8 md:p-12 text-center relative overflow-hidden">
				<div class="absolute inset-0 -z-10 bg-gradient-to-br from-primary/5 via-transparent to-accent/5"></div>
				<h2 class="text-2xl md:text-3xl font-bold text-foreground mb-3">
					<?php /* translators: %s: category label */ echo esc_html( sprintf( __( 'Practise %s', 'buckleup' ), $bu_cat_label ) ); ?>
				</h2>
				<p class="text-muted-foreground text-justify mb-8 max-w-xl mx-auto">
					<?php /* translators: 1: number of questions, 2: pass percentage */ printf( esc_html__( 'One question at a time, just on this topic. Once you\'re done, you\'ll get your score and a full breakdown of every answer. %1$d%% is a pass, and there are %2$d questions in the bank to draw from.', 'buckleup' ), (int) $bu_pass_pct, (int) $bu_cat_count ); ?>
				</p>
				<a href="<?php echo esc_url( $bu_exam_url ); ?>" class="<?php echo esc_attr( buckleup_button_class( 'default', 'lg', 'h-14 px-8 rounded-full text-lg shadow-xl shadow-primary/20 max-sm:h-auto! max-sm:whitespace-normal! max-sm:text-center max-sm:py-3.5 max-sm:leading-snug' ) ); ?>">
					<?php echo buckleup_icon( 'graduation-cap', 'w-5 h-5 shrink-0' ); // phpcs:ignore ?>
					<?php /* translators: %s: category label */ echo esc_html( sprintf( __( 'Practise %s Now', 'buckleup' ), $bu_cat_label ) ); ?>
				</a>
			</div>

			<?php if ( ! empty( $bu_samples ) ) : ?>
				<div>
					<h2 data-reveal class="text-2xl md:text-3xl font-bold text-foreground mb-3"><?php esc_html_e( 'Sample questions', 'buckleup' ); ?></h2>
					<p data-reveal class="text-muted-foreground text-justify mb-8"><?php esc_html_e( 'A taste of what\'s in this category, with the answer and why. Hit practise above for the full set.', 'buckleup' ); ?></p>
					<div data-reveal-stagger="0.05" class="grid gap-5 md:grid-cols-2">
						<?php foreach ( $bu_samples as $bu_i => $bu_q ) : ?>
							<?php $bu_render_sample( $bu_q, (int) $bu_i + 1 ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Practise another topic (replaces the old sidebar nav). -->
			<div>
				<h2 class="text-xl md:text-2xl font-bold text-foreground mb-5"><?php esc_html_e( 'Practise another topic', 'buckleup' ); ?></h2>
				<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
					<?php
					foreach ( $bu_cats as $bu_slug => $bu_meta ) :
						if ( $bu_slug === $bu_cat ) {
							continue;
						}
						$bu_topic_card( $bu_slug, $bu_meta, (int) ( $bu_cat_index[ $bu_slug ] ?? 0 ), (int) ( $bu_counts[ $bu_slug ] ?? 0 ) );
					endforeach;
					?>
				</div>
			</div>
		</div>

	<?php endif; ?>

	</div>
</section>
<!-- /wp:html -->
