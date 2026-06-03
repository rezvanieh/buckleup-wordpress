<?php
/**
 * Title: Location Hero
 * Slug: buckleup/location-hero
 * Inserter: no
 *
 * Per-city hero for the `location` CPT (/locations/{slug}). Pulls the verbatim
 * hero title / highlight / subtitle from buckleup_get_location_fields() (meta keys
 * bu_hero_title / bu_hero_highlight / bu_hero_subtitle). Mirrors the home hero's
 * type treatment (gradient highlight span, glass trust badges) but lighter — these
 * are content landing pages, not the full home hero.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// This pattern is rendered via the FSE template part (single-location.html), which
// has NO main-loop context — so get_post(null)/get_the_title() don't resolve to the
// current location. Resolve the queried location explicitly.
$location  = get_queried_object();
$location  = ( $location instanceof WP_Post ) ? $location : null;
$fields    = function_exists( 'buckleup_get_location_fields' ) ? buckleup_get_location_fields( $location ) : array();
$title     = $fields['hero_title'] ?? '';
$highlight = ! empty( $fields['hero_highlight'] ) ? $fields['hero_highlight'] : ( $location ? get_the_title( $location ) : '' );
$subtitle  = $fields['hero_subtitle'] ?? '';
$wa        = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'whatsapp', '16044413677' ) : '16044413677';
$wa_link   = 'https://wa.me/' . preg_replace( '/\D/', '', $wa );
?>
<!-- wp:html -->
<section class="relative overflow-hidden py-20 md:py-28">
	<div class="absolute inset-0 z-0">
		<div class="absolute top-0 left-1/4 w-[600px] h-[600px] bg-primary/15 rounded-full blur-[120px] animate-pulse-glow"></div>
		<div class="absolute bottom-0 right-1/4 w-[500px] h-[500px] bg-accent/10 rounded-full blur-[100px] animate-pulse-glow" style="animation-delay:1.5s;"></div>
	</div>
	<div class="container relative z-10 mx-auto px-4 text-center max-w-3xl">
		<div data-reveal class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full glass border border-border/50 mb-6">
			<?php echo buckleup_icon( 'map-pin', 'w-4 h-4 text-primary' ); // phpcs:ignore ?>
			<span class="text-sm font-medium text-muted-foreground"><?php esc_html_e( 'Service Area', 'buckleup' ); ?></span>
		</div>
		<h1 data-reveal data-reveal-y="30" class="text-4xl sm:text-5xl md:text-6xl lg:text-[5rem] font-bold tracking-tighter text-foreground leading-[0.95]">
			<?php if ( $title ) : ?><?php echo esc_html( $title ); ?> <?php endif; ?><span class="gradient-text"><?php echo esc_html( $highlight ); ?></span>
		</h1>
		<?php if ( $subtitle ) : ?>
			<p data-reveal data-reveal-y="30" class="text-lg sm:text-xl text-muted-foreground mt-6 leading-relaxed"><?php echo esc_html( $subtitle ); ?></p>
		<?php endif; ?>
		<div data-reveal class="flex flex-wrap gap-4 justify-center mt-8">
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
			<?php
			buckleup_button( array(
				'label'   => __( 'Chat on WhatsApp', 'buckleup' ),
				'href'    => $wa_link,
				'size'    => 'lg',
				'variant' => 'outline',
				'class'   => 'h-14 px-8 rounded-full',
				'icon'    => buckleup_icon( 'message-circle', 'mr-2 h-5 w-5' ),
				'attrs'   => array( 'target' => '_blank', 'rel' => 'noopener' ),
			) );
			?>
		</div>
	</div>
</section>
<!-- /wp:html -->
