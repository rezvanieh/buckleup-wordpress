<?php
/**
 * Title: Home: Graduates
 * Slug: buckleup/home-graduates
 * Inserter: no
 *
 * Reproduces src/components/graduates/GraduatesGallery.tsx: eyebrow "Milestones of
 * Success", h2 "The Hall of Fame" (gradient span), a 2-row horizontal snap-scroll
 * rail of graduate photo cards (each a [data-lightbox-item] that opens the shared-
 * element lightbox), and the "NO GRADUATES YET" empty state. Data from the
 * `graduate` CPT via buckleup_get_graduates(). Anchor id="graduates".
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$graduates = function_exists( 'buckleup_get_graduates' ) ? buckleup_get_graduates() : array();
?>
<!-- wp:html -->
<section id="graduates" class="py-12 md:py-16 relative overflow-hidden">
	<div class="container mx-auto px-4">
		<div class="text-center mb-12">
			<div data-reveal class="inline-flex items-center gap-2 mb-4">
				<span class="text-sm font-medium text-muted-foreground uppercase tracking-widest"><?php esc_html_e( 'Milestones of Success', 'buckleup' ); ?></span>
			</div>
			<h2 data-reveal class="text-4xl md:text-6xl font-black tracking-tight">
				<span class="text-foreground"><?php esc_html_e( 'The Hall of ', 'buckleup' ); ?></span><span class="gradient-text"><?php esc_html_e( 'Fame', 'buckleup' ); ?></span>
			</h2>
			<p data-reveal class="text-muted-foreground max-w-2xl mx-auto mt-4 text-pretty"><?php esc_html_e( 'Join the legacy of confident drivers. Our graduates from North Vancouver, Coquitlam, and Port Moody reflect our commitment to safe driving, expert training, and ICBC road test success.', 'buckleup' ); ?></p>
		</div>

		<?php if ( empty( $graduates ) ) : ?>
			<div data-graduates class="flex flex-col items-center justify-center py-20 text-center">
				<div class="<?php echo esc_attr( buckleup_card_class( 'items-center justify-center w-full max-w-md p-12 border-dashed' ) ); ?>">
					<h3 class="text-3xl font-black text-foreground tracking-tight"><?php esc_html_e( 'NO GRADUATES YET', 'buckleup' ); ?></h3>
					<p class="text-muted-foreground mt-3"><?php esc_html_e( 'Be the first success story on the road.', 'buckleup' ); ?></p>
				</div>
			</div>
		<?php else : ?>
			<div data-graduates data-lightbox tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Graduates gallery — scroll horizontally', 'buckleup' ); ?>"
				class="grid grid-flow-col grid-rows-2 gap-2 md:gap-3 overflow-x-auto pb-8 snap-x focus-visible:outline-2 focus-visible:outline-ring">
				<?php foreach ( $graduates as $g ) : ?>
					<button type="button" data-lightbox-item
						data-full="<?php echo esc_url( $g['image'] ); ?>"
						data-title="<?php echo esc_attr( $g['title'] ); ?>"
						data-desc="<?php echo esc_attr( $g['description'] ); ?>"
						class="group relative rounded-3xl overflow-hidden border border-border/40 bg-card cursor-pointer shadow-sm hover:shadow-2xl transition-all duration-700 hover:-translate-y-1 w-[260px] md:w-[340px] lg:w-[380px] flex-shrink-0 snap-start aspect-[4/5]">
						<?php
						// Prefer wp_get_attachment_image() so WP emits responsive srcset+sizes
						// (mobile pulls a ~340px variant, not the full 768px) and EWWW can WebP
						// it. Fall back to a raw <img> on the URL if the attachment id is absent.
						$img_class = 'absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105';
						if ( ! empty( $g['image_id'] ) ) {
							echo wp_get_attachment_image( (int) $g['image_id'], 'large', false, array(
								'class'    => $img_class,
								'alt'      => $g['title'],
								'loading'  => 'lazy',
								'decoding' => 'async',
								'sizes'    => '(min-width: 1024px) 380px, (min-width: 768px) 340px, 260px',
							) );
						} elseif ( $g['image'] ) {
							printf(
								'<img src="%s" alt="%s" class="%s" loading="lazy" decoding="async">',
								esc_url( $g['image'] ),
								esc_attr( $g['title'] ),
								esc_attr( $img_class )
							);
						}
						?>
						<!-- Production has NO visible caption on the tiles; title/desc still
						     feed the lightbox via data-title/data-desc above. A subtle hover
						     overlay only. -->
						<div class="absolute inset-0 bg-gradient-to-t from-background/40 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
<!-- /wp:html -->
