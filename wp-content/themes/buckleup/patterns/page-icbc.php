<?php
/**
 * Title: Page: ICBC Road-Test Failures
 * Slug: buckleup/page-icbc
 * Inserter: no
 *
 * The /resources/icbc-road-test-failures guide, reproducing the source layout:
 * a red "ICBC Road Test Guide" badge, the H1 with a brand-colored "ICBC Road
 * Test" span, the 98%-pass-rate dek, the 5 failures as glass CARDS (red XCircle
 * "The Mistake" / primary CheckCircle "The BuckleUp Fix" with a left accent bar),
 * and the tinted CTA box linking to /#pricing. The mistake/fix copy is verbatim
 * from the source guide (stable, low-churn content).
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$causes = array(
	array(
		'title' => __( 'Rolling Through Stop Signs', 'buckleup' ),
		'desc'  => __( "It sounds simple, but a complete stop means absolute zero forward momentum behind the line. A 'Hollywood roll' is an automatic failure.", 'buckleup' ),
		'fix'   => __( "We drill the 'Stop, Think, Scan' method until coming to a complete, full-second stop becomes pure muscle memory.", 'buckleup' ),
	),
	array(
		'title' => __( 'Failing to Shoulder Check', 'buckleup' ),
		'desc'  => __( 'Missing a shoulder check before merging, turning, or pulling out is the single most common reason for demerits. Examiners watch your head movements closely.', 'buckleup' ),
		'fix'   => __( 'Our instructors build the mirror-signal-shoulder check sequence into every single maneuver from your very first lesson.', 'buckleup' ),
	),
	array(
		'title' => __( 'Speed Maintenance in School Zones', 'buckleup' ),
		'desc'  => __( 'Hitting 35km/h in a 30km/h school or playground zone during restricted hours is an instant fail. Nerves often cause students to lose track of their speed.', 'buckleup' ),
		'fix'   => __( 'We map out and practice on actual local test routes (like Port Moody and Burnaby) so you know exactly where the traps are before test day.', 'buckleup' ),
	),
	array(
		'title' => __( 'Poor Gap Selection When Merging', 'buckleup' ),
		'desc'  => __( "Hesitating when it's safe to turn, or pulling out into a gap that is too small, shows the examiner a lack of spatial awareness and confidence.", 'buckleup' ),
		'fix'   => __( 'We use structured exposure therapy on busier roads to safely build your confidence in assessing speed and distance of oncoming traffic.', 'buckleup' ),
	),
	array(
		'title' => __( 'Improper Left Turns on Yellow', 'buckleup' ),
		'desc'  => __( "Getting 'stuck' in the intersection on a red light because you failed to establish properly, or turning when oncoming traffic hasn't fully stopped.", 'buckleup' ),
		'fix'   => __( "We teach precise positioning and the 'point of no return' framework so you never have to guess when it's safe to clear the intersection.", 'buckleup' ),
	),
);
?>
<!-- wp:html -->
<section class="py-16 md:py-24">
	<article class="container max-w-4xl mx-auto px-4">

		<!-- Header -->
		<header class="mb-12 text-center">
			<div data-reveal class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-500/10 text-red-500 text-sm font-semibold mb-6">
				<?php echo buckleup_icon( 'shield-check', 'w-4 h-4' ); // phpcs:ignore ?>
				<?php esc_html_e( 'ICBC Road Test Guide', 'buckleup' ); ?>
			</div>
			<h1 data-reveal class="text-4xl md:text-5xl font-bold tracking-tight mb-6 text-foreground">
				<?php esc_html_e( 'Top 5 Reasons Students Fail the ', 'buckleup' ); ?><span class="text-primary"><?php esc_html_e( 'ICBC Road Test', 'buckleup' ); ?></span>
			</h1>
			<p data-reveal class="text-xl text-muted-foreground leading-relaxed max-w-2xl mx-auto">
				<?php
				printf(
					/* translators: %s: bolded "98% first-time pass rate" */
					esc_html__( 'With a %s, we know exactly what ICBC examiners are looking for. Here is why most test-takers fail, and how we ensure you don\'t.', 'buckleup' ),
					'<strong class="text-foreground">' . esc_html__( '98% first-time pass rate', 'buckleup' ) . '</strong>'
				);
				?>
			</p>
		</header>

		<!-- The 5 failures as glass cards -->
		<div data-reveal-stagger="0.05" class="space-y-8 mb-16">
			<?php foreach ( $causes as $i => $c ) : ?>
				<div data-reveal class="glass p-8 rounded-3xl border border-border relative overflow-hidden group">
					<div class="absolute top-0 left-0 w-2 h-full bg-primary"></div>
					<div class="flex items-start gap-4 mb-5">
						<span class="text-3xl font-black text-primary/30 leading-none"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<h2 class="text-2xl font-bold text-foreground pt-1"><?php echo esc_html( $c['title'] ); ?></h2>
					</div>
					<div class="grid md:grid-cols-2 gap-5">
						<div class="flex items-start gap-3">
							<?php echo buckleup_icon( 'x', 'w-6 h-6 shrink-0 text-red-500 mt-0.5' ); // phpcs:ignore ?>
							<div>
								<h3 class="font-semibold text-foreground mb-1"><?php esc_html_e( 'The Mistake', 'buckleup' ); ?></h3>
								<p class="text-sm text-muted-foreground leading-relaxed"><?php echo esc_html( $c['desc'] ); ?></p>
							</div>
						</div>
						<div class="flex items-start gap-3">
							<?php echo buckleup_icon( 'check', 'w-6 h-6 shrink-0 text-primary mt-0.5' ); // phpcs:ignore ?>
							<div>
								<h3 class="font-semibold text-foreground mb-1"><?php esc_html_e( 'The BuckleUp Fix', 'buckleup' ); ?></h3>
								<p class="text-sm text-muted-foreground leading-relaxed"><?php echo esc_html( $c['fix'] ); ?></p>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Tinted CTA box -->
		<div data-reveal class="bg-primary/10 rounded-3xl p-8 md:p-12 text-center border border-primary/20">
			<h2 class="text-3xl font-bold mb-4 text-foreground"><?php esc_html_e( 'Ready to Pass on Your First Try?', 'buckleup' ); ?></h2>
			<p class="text-lg text-muted-foreground mb-8 max-w-xl mx-auto"><?php esc_html_e( "Don't leave your license to chance. Join the 98% of BuckleUp students who pass their ICBC road test the very first time.", 'buckleup' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/#pricing' ) ); ?>">
				<?php
				buckleup_button( array(
					'label' => __( 'Book Road Test Prep', 'buckleup' ),
					'size'  => 'lg',
					'class' => 'h-14 px-8 rounded-full text-lg shadow-xl shadow-primary/20',
					'icon'  => buckleup_icon( 'chevron-right', 'ml-2 w-5 h-5' ),
				) );
				?>
			</a>
		</div>
	</article>
</section>
<!-- /wp:html -->
