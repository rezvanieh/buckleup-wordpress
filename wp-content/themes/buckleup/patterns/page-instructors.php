<?php
/**
 * Title: Page: Instructors
 * Slug: buckleup/page-instructors
 * Inserter: no
 *
 * The Instructors page, driven by the `instructor` CPT via
 * buckleup_get_instructors() — i.e. the REAL instructor (Farhad Sanaeifar), NOT the
 * source page's Unsplash placeholder personas (PLAN §4 clean-up). Sarah Mitchell was
 * seeded from the original app but is no longer an instructor (removed 2026-08-14).
 * Mirrors the LIVE page's section weight: hero + marketing stats row, the instructor
 * card grid (photo, role, rating, bio, certs + languages pills, per-instructor
 * WhatsApp CTA), and a "Why Choose Our Instructors" feature section. The shared
 * Graduates→Pricing→Testimonials→FAQ rail is appended by the template.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$instructors = function_exists( 'buckleup_get_instructors' ) ? buckleup_get_instructors() : array();
$wa          = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'whatsapp', '16044413677' ) : '16044413677';
$wa_num      = preg_replace( '/\D/', '', $wa );

$initials = static function ( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$out   = '';
	foreach ( array_slice( $parts, 0, 2 ) as $p ) {
		$out .= mb_substr( $p, 0, 1 );
	}
	return mb_strtoupper( $out );
};

// Marketing stats row (kept from source for parity — see Services/PLAN).
$stats = array(
	// Was 10,000+ Students Taught / 94% Avg Pass Rate / 4.8 Avg Rating — unverifiable,
	// and the 4.8 contradicted the rating shown everywhere else. Replaced with the
	// figures that come from the real Google reviews.
	array( 'value' => '5.0★', 'label' => __( 'Google rating', 'buckleup' ) ),
	array( 'value' => '200+',  'label' => __( 'Google reviews', 'buckleup' ) ),
	array( 'value' => (string) max( 1, count( $instructors ) ), 'label' => __( 'Expert Instructors', 'buckleup' ) ),
);

$why = array(
	array( 'icon' => 'shield-check',   'title' => __( 'ICBC Certified', 'buckleup' ),       'desc' => __( 'All instructors are ICBC-approved with clean driving records', 'buckleup' ) ),
	array( 'icon' => 'message-circle', 'title' => __( 'Multilingual', 'buckleup' ),         'desc' => __( 'Lessons available in English, Farsi, French, and more', 'buckleup' ) ),
	array( 'icon' => 'star',           'title' => __( 'Patient & Supportive', 'buckleup' ), 'desc' => __( 'Specializing in nervous and first-time drivers', 'buckleup' ) ),
	// Was "Above-average pass rates with thousands of success stories" — an
	// unverifiable claim of the same kind removed site-wide on 2026-08-13.
	array( 'icon' => 'check',          'title' => __( 'Structured Lessons', 'buckleup' ),    'desc' => __( 'A clear plan for each drive, with honest feedback afterwards', 'buckleup' ) ),
);
?>
<!-- wp:html -->
<!-- Hero + stats -->
<section class="py-16 md:py-20">
	<div class="container mx-auto px-4">
		<div class="text-center mb-12 max-w-2xl mx-auto">
			<div data-reveal class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full glass border border-border/50 mb-4">
				<?php echo buckleup_icon( 'shield-check', 'w-4 h-4 text-primary' ); // phpcs:ignore ?>
				<span class="text-sm font-medium text-muted-foreground"><?php esc_html_e( 'Our Team', 'buckleup' ); ?></span>
			</div>
			<h1 data-reveal class="text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight">
				<span class="text-foreground"><?php esc_html_e( 'Meet Your ', 'buckleup' ); ?></span><span class="gradient-text"><?php esc_html_e( 'Instructors', 'buckleup' ); ?></span>
			</h1>
			<p data-reveal class="text-muted-foreground mt-4 text-lg"><?php esc_html_e( 'ICBC-certified, patient, and fluent in the languages our students speak.', 'buckleup' ); ?></p>
		</div>

		<div data-reveal-stagger="0.05" class="grid grid-cols-2 md:grid-cols-4 gap-6 max-w-4xl mx-auto">
			<?php foreach ( $stats as $s ) : ?>
				<div data-reveal class="text-center">
					<div class="text-3xl md:text-4xl font-bold text-primary"><?php echo esc_html( $s['value'] ); ?></div>
					<div class="text-sm text-muted-foreground mt-1"><?php echo esc_html( $s['label'] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- Instructor cards -->
<section class="pb-8">
	<div class="container mx-auto px-4">
		<?php if ( ! empty( $instructors ) ) : ?>
			<div data-reveal-stagger="0.05" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8 max-w-6xl mx-auto">
				<?php foreach ( $instructors as $ins ) :
					$first = explode( ' ', trim( $ins['name'] ) )[0];
					$wa_link = 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( sprintf( "Hi! I'd like to book a lesson with %s.", $ins['name'] ) );
					?>
					<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'group p-6 hover-lift card-highlight' ) ); ?>">
						<div class="flex items-center gap-4 mb-4">
							<?php
							buckleup_avatar( array(
								'src'      => $ins['image'],
								'alt'      => $ins['name'],
								'fallback' => $initials( $ins['name'] ),
								'class'    => 'size-16 border-2 border-primary/20',
							) );
							?>
							<div class="flex-1 min-w-0">
								<div class="text-lg font-bold text-foreground group-hover:text-primary transition-colors"><?php echo esc_html( $ins['name'] ); ?></div>
								<?php if ( ! empty( $ins['role'] ) ) : ?>
									<div class="text-sm text-muted-foreground"><?php echo esc_html( $ins['role'] ); ?></div>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $ins['rating'] ) ) : ?>
								<div class="flex items-center gap-1 px-2 py-1 bg-yellow-500/20 rounded-full shrink-0">
									<?php echo buckleup_icon( 'star', 'w-3 h-3 fill-yellow-500 text-yellow-500' ); // phpcs:ignore ?>
									<span class="text-xs font-bold text-yellow-500"><?php echo esc_html( $ins['rating'] ); ?></span>
								</div>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $ins['bio'] ) ) : ?>
							<p class="text-sm text-muted-foreground leading-relaxed mb-4 text-pretty"><?php echo esc_html( $ins['bio'] ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $ins['certifications'] ) ) : ?>
							<div class="mb-3">
								<div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2"><?php esc_html_e( 'Certifications', 'buckleup' ); ?></div>
								<div class="flex flex-wrap gap-2">
									<?php foreach ( $ins['certifications'] as $cert ) : ?>
										<?php buckleup_pill( array( 'label' => $cert, 'tone' => 'primary', 'class' => 'text-xs px-3 py-1' ) ); ?>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $ins['languages'] ) ) : ?>
							<div class="mb-5">
								<div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2"><?php esc_html_e( 'Languages', 'buckleup' ); ?></div>
								<div class="flex flex-wrap gap-2">
									<?php foreach ( $ins['languages'] as $lang ) : ?>
										<?php buckleup_pill( array( 'label' => $lang, 'tone' => 'muted', 'class' => 'text-xs px-3 py-1' ) ); ?>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>

						<?php
						buckleup_button( array(
							/* translators: %s: instructor first name */
							'label'   => sprintf( __( 'Book with %s', 'buckleup' ), $first ),
							'href'    => $wa_link,
							'variant' => 'outline',
							'class'   => 'w-full rounded-full',
							'icon'    => buckleup_icon( 'message-circle', 'mr-2 h-4 w-4' ),
							'attrs'   => array( 'target' => '_blank', 'rel' => 'noopener' ),
						) );
						?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="text-center text-muted-foreground"><?php esc_html_e( 'Our instructor profiles are coming soon.', 'buckleup' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<!-- Why Choose Our Instructors -->
<section class="py-12 md:py-16 bg-muted/30">
	<div class="container mx-auto px-4">
		<div class="text-center mb-10">
			<h2 data-reveal class="text-3xl md:text-4xl font-bold text-foreground"><?php esc_html_e( 'Why Choose Our Instructors', 'buckleup' ); ?></h2>
		</div>
		<div data-reveal-stagger="0.05" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto">
			<?php foreach ( $why as $w ) : ?>
				<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'p-6 text-center items-center hover-lift' ) ); ?>">
					<div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary/10 mb-4"><?php echo buckleup_icon( $w['icon'], 'w-7 h-7 text-primary' ); // phpcs:ignore ?></div>
					<h3 class="text-lg font-bold text-foreground mb-2"><?php echo esc_html( $w['title'] ); ?></h3>
					<p class="text-sm text-muted-foreground"><?php echo esc_html( $w['desc'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<!-- /wp:html -->
