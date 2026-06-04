<?php
/**
 * Title: Page: About
 * Slug: buckleup/page-about
 * Inserter: no
 *
 * About page, reproducing src/app/about/page.tsx sections: hero ("Driving
 * Excellence Since 2014"), the Mission two-column (mission copy + ICBC/Vehicles/
 * Scheduling/Booking key points, paired with a modern-fleet card), and the Core
 * Values 4-card grid (Student-Centered, Safety First, Modern Approach, Excellence).
 * Copy is verbatim from the source. Founding year pulled from settings.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$founding = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'founding_year', '2014' ) : '2014';

$key_points = array(
	__( 'ICBC Certified', 'buckleup' ),
	__( 'Modern Vehicles', 'buckleup' ),
	__( 'Flexible Scheduling', 'buckleup' ),
	__( 'Online Booking', 'buckleup' ),
);

$values = array(
	array( 'icon' => 'shield-check', 'title' => __( 'Student-Centered', 'buckleup' ), 'desc' => __( 'Every lesson is tailored to your pace, goals, and learning style.', 'buckleup' ) ),
	array( 'icon' => 'shield-check', 'title' => __( 'Safety First', 'buckleup' ),      'desc' => __( 'Modern vehicles with safety primary features and comprehensive insurance coverage.', 'buckleup' ) ),
	array( 'icon' => 'star',         'title' => __( 'Modern Approach', 'buckleup' ),   'desc' => __( 'Online booking, progress tracking, and flexible scheduling.', 'buckleup' ) ),
	array( 'icon' => 'star',         'title' => __( 'Excellence', 'buckleup' ),        'desc' => __( '98% first-time pass rate speaks to our commitment to quality.', 'buckleup' ) ),
);
?>
<!-- wp:html -->
<!-- Hero -->
<section class="py-16 md:py-24 text-center">
	<div class="container mx-auto px-4 max-w-3xl">
		<div data-reveal class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/10 border border-primary/20 mb-6">
			<?php echo buckleup_icon( 'shield-check', 'w-4 h-4 text-primary' ); // phpcs:ignore ?>
			<span class="text-sm font-medium text-primary"><?php printf( /* translators: %s year */ esc_html__( 'Serving Vancouver Since %s', 'buckleup' ), esc_html( $founding ) ); ?></span>
		</div>
		<h1 data-reveal class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 tracking-tight text-foreground">
			<?php esc_html_e( 'Driving Excellence', 'buckleup' ); ?> <span class="text-primary"><?php printf( /* translators: %s year */ esc_html__( 'Since %s', 'buckleup' ), esc_html( $founding ) ); ?></span>
		</h1>
		<p data-reveal class="text-lg md:text-xl text-muted-foreground">
			<?php esc_html_e( "We don't just teach you how to pass a test. We teach you how to become a confident, safe driver for life.", 'buckleup' ); ?>
		</p>
	</div>
</section>

<!-- Mission -->
<section class="py-20 bg-muted/30">
	<div class="container mx-auto px-4">
		<div class="grid lg:grid-cols-2 gap-12 items-center max-w-6xl mx-auto">
			<div data-reveal class="order-2 lg:order-1">
				<h2 class="text-3xl md:text-4xl font-bold mb-6 text-foreground"><?php esc_html_e( 'Our Mission', 'buckleup' ); ?></h2>
				<div class="space-y-4 text-muted-foreground leading-relaxed">
					<p><?php esc_html_e( 'BuckleUp Driving School was founded in 2014 by Farhad Sanaeifar, a certified driving instructor dedicated to helping students become safe, confident, and responsible drivers in North Vancouver, Coquitlam, Port Coquitlam, and Port Moody (Tri-Cities). With years of professional experience and ICBC certification, he provides structured and supportive training for students preparing for Class 4, Class 5, or Class 7 licenses.', 'buckleup' ); ?></p>
					<p><?php esc_html_e( 'Each lesson is designed to build confidence behind the wheel while focusing on defensive driving techniques and real-world road safety. Students receive personalized instruction tailored to their experience level.', 'buckleup' ); ?></p>
					<p><?php esc_html_e( 'Lessons are conducted in modern Toyota vehicles known for their reliability and safety features. With professional guidance, students gain the practical skills and confidence needed to drive safely and pass their road test.', 'buckleup' ); ?></p>
				</div>
				<div class="mt-8 grid grid-cols-2 gap-4">
					<?php foreach ( $key_points as $point ) : ?>
						<div class="flex items-center gap-2">
							<?php echo buckleup_icon( 'check', 'w-5 h-5 text-accent' ); // phpcs:ignore ?>
							<span class="text-foreground font-medium"><?php echo esc_html( $point ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<?php
			// Mission photo: owner_withcar (content task #28 → slug buckleup-owner-with-car,
			// mapped in buckleup_asset_url's slug_map). Falls back to other brand photos,
			// then a glass placeholder, so it degrades gracefully while media is importing.
			$mission_img = buckleup_asset_url( 'owner_withcar.png' );
			if ( '' === $mission_img ) {
				$mission_img = buckleup_asset_url( 'hero_card_image.png' );
			}
			if ( '' === $mission_img ) {
				$mission_img = buckleup_asset_url( 'image2.png' );
			}
			$mission_alt = __( 'BuckleUp Driving School instructor with a training vehicle in Metro Vancouver', 'buckleup' );
			?>
			<div data-reveal class="order-1 lg:order-2">
				<div class="relative h-[400px] rounded-3xl overflow-hidden border border-border shadow-xl">
					<?php if ( $mission_img ) : ?>
						<img src="<?php echo esc_url( $mission_img ); ?>" alt="<?php echo esc_attr( $mission_alt ); ?>" class="absolute inset-0 w-full h-full object-cover" loading="lazy" decoding="async">
						<div class="absolute inset-0 bg-gradient-to-t from-background/70 via-transparent to-transparent"></div>
					<?php else : ?>
						<div class="absolute inset-0 glass flex items-center justify-center">
							<div class="text-center p-8">
								<div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/10 mb-4"><?php echo buckleup_icon( 'shield-check', 'w-8 h-8 text-primary' ); // phpcs:ignore ?></div>
								<div class="text-xl font-bold text-foreground"><?php esc_html_e( 'Modern Fleet', 'buckleup' ); ?></div>
							</div>
						</div>
					<?php endif; ?>

					<!-- "Modern Fleet" corner badge -->
					<div class="absolute bottom-4 left-4 glass px-4 py-3 rounded-2xl shadow-lg flex items-center gap-3">
						<div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-primary/10 shrink-0"><?php echo buckleup_icon( 'shield-check', 'w-5 h-5 text-primary' ); // phpcs:ignore ?></div>
						<div>
							<div class="text-sm font-bold text-foreground"><?php esc_html_e( 'Modern Fleet', 'buckleup' ); ?></div>
							<div class="text-xs text-muted-foreground"><?php esc_html_e( 'Well-maintained, safety-focused vehicles', 'buckleup' ); ?></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- Core Values -->
<section class="py-20">
	<div class="container mx-auto px-4">
		<div class="text-center mb-12">
			<h2 data-reveal class="text-3xl md:text-4xl font-bold text-foreground mb-4"><?php esc_html_e( 'Our Core Values', 'buckleup' ); ?></h2>
		</div>
		<div data-reveal-stagger="0.05" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto">
			<?php foreach ( $values as $v ) : ?>
				<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'p-6 text-center items-center hover-lift card-highlight' ) ); ?>">
					<div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary/10 mb-4"><?php echo buckleup_icon( $v['icon'], 'w-7 h-7 text-primary' ); // phpcs:ignore ?></div>
					<h3 class="text-lg font-bold text-foreground mb-2"><?php echo esc_html( $v['title'] ); ?></h3>
					<p class="text-sm text-muted-foreground"><?php echo esc_html( $v['desc'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<!-- /wp:html -->
