<?php
/**
 * Title: Home: Testimonials
 * Slug: buckleup/home-testimonials
 * Inserter: no
 *
 * "Trusted by Tri-Cities Learners" review grid. Card header leads with the reviewer
 * (avatar + name + role) and the star rating on top, with the review text
 * below. Data from the `testimonial` CPT via buckleup_get_testimonials()
 * (seeded from the REAL Google reviews — see scripts/wp/real-testimonials.php),
 * with any REAL approved+public student reviews (buckleup_get_public_reviews())
 * appended — so a review approved in the admin console also surfaces here.
 * Approved reviews are normalized to the card shape (comment → content, role
 * "Student"); the student's uploaded photo (the helper's `avatar`) renders in
 * the card, falling back to the author's initials when there's none.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$cpt_testimonials = function_exists( 'buckleup_get_testimonials' ) ? buckleup_get_testimonials() : array();

// Real approved+public student reviews (Phase-2 app), normalized to the
// testimonial card shape. These are GUARANTEED slots — every approved review must
// surface on the homepage (client intent: "approving a review makes it visible").
$review_cards = array();
if ( function_exists( 'buckleup_get_public_reviews' ) ) {
	foreach ( buckleup_get_public_reviews( 12 ) as $r ) {
		$comment = (string) ( $r['comment'] ?? '' );
		if ( '' === trim( $comment ) ) {
			continue;
		}
		$review_cards[] = array(
			'name'    => (string) ( $r['name'] ?? '' ),
			'role'    => __( 'Student', 'buckleup' ),
			'content' => $comment,
			'rating'  => (int) ( $r['rating'] ?? 5 ),
			// Student's uploaded photo (bu_avatar_id thumbnail) from the helper;
			// '' falls back to the author's initials in the card's buckleup_avatar().
			'image'   => (string) ( $r['avatar'] ?? '' ),
		);
	}
}

// Compose the grid. Show ALL curated testimonials (the real Google reviews) plus
// any approved+public student reviews. Approved reviews are GUARANTEED a slot —
// reserve room for them first, then let curated fill the rest, up to a generous
// cap so the section can't grow unbounded if many app reviews are approved later.
$grid_cap        = 24;
$curated_slots   = max( 0, $grid_cap - count( $review_cards ) );
$testimonials    = array_merge( array_slice( $cpt_testimonials, 0, $curated_slots ), $review_cards );

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
				<span class="text-foreground"><?php esc_html_e( 'Trusted by ', 'buckleup' ); ?></span><span class="gradient-text"><?php esc_html_e( 'Tri-Cities Learners', 'buckleup' ); ?></span>
			</h2>
			<p data-reveal class="text-muted-foreground max-w-2xl mx-auto"><?php esc_html_e( 'Join drivers across Coquitlam, Port Coquitlam, and Port Moody who have built their skills and confidence with BuckleUp Driving School.', 'buckleup' ); ?></p>
		</div>

		<div data-reveal-stagger="0.05" class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mx-auto">
			<?php /* $testimonials is already composed (all real Google reviews + any approved student reviews); cap at the grid max. */ ?>
			<?php foreach ( array_slice( $testimonials, 0, $grid_cap ) as $t ) : ?>
				<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'group p-8 hover-lift card-highlight' ) ); ?>">
					<div class="flex items-center gap-3 mb-4">
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
					<div class="flex items-center gap-1 mb-4">
						<?php for ( $i = 0; $i < max( 1, (int) $t['rating'] ); $i++ ) : ?>
							<?php echo buckleup_icon( 'star', 'h-4 w-4 fill-yellow-500 text-yellow-500' ); // phpcs:ignore ?>
						<?php endfor; ?>
					</div>
					<p class="text-foreground leading-relaxed text-pretty"><?php echo esc_html( $t['content'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Trust row -->
		<div data-reveal class="flex flex-wrap items-center justify-center gap-x-8 gap-y-4 mt-12 text-center">
			<div class="flex items-center gap-2">
				<?php echo buckleup_icon( 'star', 'w-5 h-5 fill-yellow-500 text-yellow-500' ); // phpcs:ignore ?>
				<span class="font-bold text-foreground">5.0</span>
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
