<?php
/**
 * Title: Home: Testimonials
 * Slug: buckleup/home-testimonials
 * Inserter: no
 *
 * Reproduces src/components/landing/Testimonials.tsx: eyebrow "Student Reviews"
 * (star), h2 "Loved by Thousands" (gradient span), a 3-col card grid on md+ (the
 * source also has a mobile carousel; the grid stacks to 1-col on mobile here), each
 * card with a Quote glyph, the quote, a 5-star row, and the named author + role.
 * Data from the `testimonial` CPT via buckleup_get_testimonials() (seeded with the
 * 5 named live fallbacks: Jason Kim, Amanda Liu, David Wang, Sarah Martinez,
 * Michael Chen).
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$testimonials = function_exists( 'buckleup_get_testimonials' ) ? buckleup_get_testimonials() : array();
if ( empty( $testimonials ) ) {
	return;
}

$initials = static function ( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$out   = '';
	foreach ( array_slice( $parts, 0, 2 ) as $p ) {
		$out .= mb_substr( $p, 0, 1 );
	}
	return mb_strtoupper( $out );
};
?>
<!-- wp:html -->
<section id="testimonials" data-testimonials class="py-12 md:py-16 relative">
	<div class="container mx-auto px-4">
		<div class="text-center mb-12">
			<div data-reveal class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full glass border border-border/50 mb-4">
				<?php echo buckleup_icon( 'star', 'w-4 h-4 text-yellow-500' ); // phpcs:ignore ?>
				<span class="text-sm font-medium text-muted-foreground"><?php esc_html_e( 'Student Reviews', 'buckleup' ); ?></span>
			</div>
			<h2 data-reveal class="text-4xl md:text-5xl font-bold mb-4">
				<span class="text-foreground"><?php esc_html_e( 'Loved by ', 'buckleup' ); ?></span><span class="gradient-text"><?php esc_html_e( 'Thousands', 'buckleup' ); ?></span>
			</h2>
			<p data-reveal class="text-muted-foreground max-w-2xl mx-auto"><?php esc_html_e( 'Join over 10,000 happy students who are now safe, confident drivers on the road.', 'buckleup' ); ?></p>
		</div>

		<div data-reveal-stagger="0.05" class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mx-auto">
			<?php foreach ( array_slice( $testimonials, 0, 6 ) as $t ) : ?>
				<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'group p-8 hover-lift card-highlight' ) ); ?>">
					<?php echo buckleup_icon( 'message-circle', 'h-10 w-10 text-primary/20 mb-6 group-hover:text-primary/40 transition-colors' ); // phpcs:ignore ?>
					<p class="text-foreground leading-relaxed mb-6 text-pretty"><?php echo esc_html( $t['content'] ); ?></p>
					<div class="flex items-center gap-1 mb-4">
						<?php for ( $i = 0; $i < max( 1, (int) $t['rating'] ); $i++ ) : ?>
							<?php echo buckleup_icon( 'star', 'h-4 w-4 fill-yellow-500 text-yellow-500' ); // phpcs:ignore ?>
						<?php endfor; ?>
					</div>
					<div class="flex items-center gap-3">
						<?php
						buckleup_avatar( array(
							'src'      => $t['image'],
							'alt'      => $t['name'],
							'fallback' => $initials( $t['name'] ),
							'class'    => 'size-12 border-2 border-primary/20',
						) );
						?>
						<div>
							<div class="font-semibold text-foreground"><?php echo esc_html( $t['name'] ); ?></div>
							<?php if ( ! empty( $t['role'] ) ) : ?>
								<div class="text-sm text-muted-foreground"><?php echo esc_html( $t['role'] ); ?></div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Trust row -->
		<div data-reveal class="flex flex-wrap items-center justify-center gap-x-8 gap-y-4 mt-12 text-center">
			<div class="flex items-center gap-2">
				<?php echo buckleup_icon( 'star', 'w-5 h-5 fill-yellow-500 text-yellow-500' ); // phpcs:ignore ?>
				<span class="font-bold text-foreground">4.9</span>
				<span class="text-sm text-muted-foreground"><?php esc_html_e( 'Google Reviews', 'buckleup' ); ?></span>
			</div>
			<div class="hidden sm:block w-px h-6 bg-border"></div>
			<div class="flex items-center gap-2">
				<span class="font-bold text-foreground">A+</span>
				<span class="text-sm text-muted-foreground"><?php esc_html_e( 'BBB Rating', 'buckleup' ); ?></span>
			</div>
			<div class="hidden sm:block w-px h-6 bg-border"></div>
			<div class="flex items-center gap-2">
				<span class="font-bold text-foreground">#1</span>
				<span class="text-sm text-muted-foreground"><?php esc_html_e( 'Rated in Vancouver', 'buckleup' ); ?></span>
			</div>
		</div>
	</div>
</section>
<!-- /wp:html -->
