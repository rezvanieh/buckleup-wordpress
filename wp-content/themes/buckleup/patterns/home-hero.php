<?php
/**
 * Title: Home: Hero
 * Slug: buckleup/home-hero
 * Inserter: no
 *
 * Reproduces src/components/landing/Hero.tsx: full-bleed hero with the scenic
 * background (image2.png) + multi-layer gradient overlays, two ambient pulse-glow
 * blobs, the grid pattern, trust badges (ICBC Certified / Fully Insured / 100% Pass
 * Guarantee), the H1 "Master the Road with Confidence" (gradient span) at the exact
 * type scale (text-5xl…lg:text-[5.5rem] xl:text-[6.5rem], tracking-tighter,
 * leading-[0.95]), the subtitle, the "Start Learning Today" → #most-popular CTA, and
 * the lg-only 3D mouse-tilt card (hero_card_image.png) with the Farhad instructor
 * chip, the floating 4.98 / "Based on 200+ reviews" card, and the Toyota badge.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$bg      = buckleup_asset_url( 'image2.png' );
$bg_webp = buckleup_asset_webp_url( 'image2.png' ); // optimized next-gen LCP source
$card    = buckleup_asset_url( 'hero_card_image.png' );
$farhad  = buckleup_asset_url( 'farhad-instructor.jpg' );

$badges = array(
	array( 'icon' => 'shield-check', 'text' => __( 'ICBC Certified', 'buckleup' ) ),
	array( 'icon' => 'shield-check', 'text' => __( 'Fully Insured', 'buckleup' ) ),
	array( 'icon' => 'check',        'text' => __( '100% Pass Guarantee', 'buckleup' ) ),
);
?>
<!-- wp:html -->
<section class="relative min-h-screen w-full overflow-hidden flex items-center justify-center bg-background">

	<!-- Background image + overlays -->
	<div class="absolute inset-0 z-0">
		<?php if ( $bg ) : ?>
			<picture>
				<?php if ( $bg_webp ) : ?>
					<source srcset="<?php echo esc_url( $bg_webp ); ?>" type="image/webp">
				<?php endif; ?>
				<img src="<?php echo esc_url( $bg ); ?>" alt="<?php esc_attr_e( 'Scenic winding road', 'buckleup' ); ?>" class="absolute inset-0 w-full h-full object-cover" fetchpriority="high" loading="eager" decoding="async">
			</picture>
		<?php endif; ?>
		<div class="absolute inset-0 bg-gradient-to-r from-background/90 via-background/40 to-background/10"></div>
		<div class="absolute inset-0 bg-gradient-to-t from-background/80 via-background/20 to-transparent"></div>
	</div>

	<!-- Ambient gradient blobs -->
	<div class="absolute inset-0 z-0">
		<div class="absolute top-0 left-1/4 w-[600px] h-[600px] bg-primary/20 rounded-full blur-[120px] animate-pulse-glow"></div>
		<div class="absolute bottom-0 right-1/4 w-[500px] h-[500px] bg-accent/15 rounded-full blur-[100px] animate-pulse-glow" style="animation-delay:1.5s;"></div>
	</div>

	<!-- Grid pattern -->
	<div class="absolute inset-0 z-0 opacity-[0.02]" style="background-image:linear-gradient(hsl(var(--foreground)) 1px, transparent 1px), linear-gradient(90deg, hsl(var(--foreground)) 1px, transparent 1px); background-size:60px 60px;"></div>

	<div class="container relative z-10 mx-auto px-4 pt-24 pb-32">
		<div class="grid lg:grid-cols-[1.2fr_1fr] gap-12 items-center">

			<!-- Left: typography -->
			<div class="text-left space-y-8 lg:col-span-1 xl:pr-8">
				<div data-reveal class="flex flex-wrap gap-3">
					<?php foreach ( $badges as $b ) : ?>
						<div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full glass border border-border/50">
							<?php echo buckleup_icon( $b['icon'], 'w-4 h-4 text-accent' ); // phpcs:ignore ?>
							<span class="text-xs font-medium text-muted-foreground"><?php echo esc_html( $b['text'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>

				<h1 data-reveal data-reveal-y="30" class="text-5xl sm:text-6xl md:text-7xl lg:text-[5.5rem] xl:text-[6.5rem] font-bold tracking-tighter text-foreground leading-[0.95]">
					<?php esc_html_e( 'Master the Road', 'buckleup' ); ?> <span class="gradient-text"><?php esc_html_e( 'with Confidence', 'buckleup' ); ?></span>
				</h1>

				<p data-reveal data-reveal-y="30" class="text-lg sm:text-xl text-muted-foreground max-w-lg leading-relaxed">
					<?php esc_html_e( 'BuckleUp Driving School is a trusted driving school that Vancouver learners choose. We offer expert driving lessons in North Vancouver, Coquitlam, Port Moody, and the Tri-Cities, along with ICBC road test preparation.', 'buckleup' ); ?>
				</p>

				<div data-reveal class="flex flex-wrap gap-4 mb-8">
					<a href="#most-popular">
						<?php
						buckleup_button( array(
							'label' => __( 'Start Learning Today', 'buckleup' ),
							'size'  => 'lg',
							'class' => 'h-14 px-8 rounded-full font-semibold text-base shine glow-primary',
							'icon'  => buckleup_icon( 'arrow-right', 'ml-2 h-5 w-5' ),
						) );
						?>
					</a>
				</div>
			</div>

			<!-- Right: 3D mouse-tilt image card (lg only) -->
			<div data-tilt class="relative hidden lg:block">
				<div data-tilt-card class="relative w-full max-w-[500px] mx-auto">
					<div class="relative rounded-3xl overflow-hidden shadow-2xl glow-primary">
						<?php if ( $card ) : ?>
							<img src="<?php echo esc_url( $card ); ?>" alt="<?php esc_attr_e( 'Farhad Sanaeifar with BuckleUp Driving School car', 'buckleup' ); ?>" class="w-full h-[400px] object-cover" decoding="async">
						<?php endif; ?>
						<div class="absolute inset-0 bg-gradient-to-t from-background/90 via-transparent to-transparent"></div>
					</div>

					<!-- Instructor chip -->
					<div class="-mt-6 glass p-4 rounded-2xl relative z-10 mx-4 md:mx-0 shadow-lg">
						<div class="flex items-center gap-3">
							<div class="relative">
								<?php if ( $farhad ) : ?>
									<img src="<?php echo esc_url( $farhad ); ?>" alt="Farhad Sanaeifar" class="w-12 h-12 rounded-full object-cover border-2 border-accent" decoding="async">
								<?php else : ?>
									<span class="w-12 h-12 rounded-full bg-muted border-2 border-accent flex items-center justify-center text-sm font-bold">FS</span>
								<?php endif; ?>
								<div class="absolute -bottom-1 -right-1 w-4 h-4 bg-accent rounded-full border-2 border-background flex items-center justify-center">
									<?php echo buckleup_icon( 'check', 'w-3 h-3 text-background' ); // phpcs:ignore ?>
								</div>
							</div>
							<div class="flex-1">
								<div class="text-sm font-semibold text-foreground">Farhad Sanaeifar</div>
								<div class="text-xs text-muted-foreground"><?php esc_html_e( 'Senior Instructor • ICBC Certified', 'buckleup' ); ?></div>
							</div>
							<div class="flex items-center gap-1 px-2 py-1 bg-yellow-500/20 rounded-full">
								<?php echo buckleup_icon( 'star', 'w-3 h-3 fill-yellow-500 text-yellow-500' ); // phpcs:ignore ?>
								<span class="text-xs font-bold text-yellow-500">4.9</span>
							</div>
						</div>
					</div>

					<!-- Floating rating card -->
					<div class="absolute -top-4 -right-8 glass p-4 rounded-2xl shadow-xl animate-float" style="animation-delay:2s;">
						<div class="flex items-center gap-2 mb-2">
							<span class="text-3xl font-bold text-foreground">4.98</span>
							<div class="flex">
								<?php for ( $i = 0; $i < 5; $i++ ) : ?>
									<?php echo buckleup_icon( 'star', 'w-4 h-4 fill-yellow-500 text-yellow-500' ); // phpcs:ignore ?>
								<?php endfor; ?>
							</div>
						</div>
						<div class="text-[10px] text-muted-foreground"><?php esc_html_e( 'Based on 200+ reviews', 'buckleup' ); ?></div>
					</div>

					<!-- Toyota badge -->
					<div class="absolute top-4 left-4 glass px-3 py-2 rounded-xl">
						<div class="flex items-center gap-2">
							<div class="w-2 h-2 bg-accent rounded-full animate-pulse"></div>
							<span class="text-xs font-medium text-foreground"><?php esc_html_e( 'Toyota', 'buckleup' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
<!-- /wp:html -->
