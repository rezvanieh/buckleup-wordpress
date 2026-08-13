<?php
/**
 * Site chrome helpers + block-pattern registration for the header/footer and the
 * landing-section patterns.
 *
 * FSE template parts (parts/*.html) are static block markup, but the header,
 * footer, and home sections are highly dynamic (theme-aware logo, NAP from
 * settings, CPT-driven Pricing/Testimonials/FAQ/Graduates). So those live as
 * PHP block patterns (patterns/*.php) registered here; the parts/templates embed
 * them via `<!-- wp:pattern {"slug":"buckleup/…"} /-->`, which runs the PHP at
 * render time. This keeps presentation in the theme and content via the plugin's
 * helper API (docs/CONTENT-MODEL.md).
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Preload the location landing-page hero for a faster LCP.
 *
 * The landmark hero is an Elementor CSS background-image, which browsers discover
 * late (only after the per-post CSS is parsed). On a `location` single we preload
 * the featured image (= the hero, set by build-locations.php) with high priority so
 * it starts downloading immediately — improving Largest Contentful Paint / Core Web
 * Vitals, which feed local search ranking.
 */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'location' ) ) {
		return;
	}
	$tid = get_post_thumbnail_id();
	if ( ! $tid ) {
		return;
	}
	$url = wp_get_attachment_url( $tid );
	if ( $url ) {
		printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( $url ) );
	}
}, 2 );

/**
 * Theme-aware logo <img>. The JS theme module swaps src on toggle via data-logo*;
 * we render the correct src for the server-resolved theme so there's no flash.
 * Logos are migrated into the Media Library by the content task; we fall back to
 * the custom-logo / site title if not present.
 */
function buckleup_logo( string $class = 'h-8 min-[1100px]:h-16 w-auto transition-all duration-500' ): string {
	$light = buckleup_asset_url( 'logo.png' );
	$dark  = buckleup_asset_url( 'logo-dark.png' );
	$name  = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'business_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );

	if ( ! $light ) {
		return '<span class="text-xl font-bold tracking-tight text-foreground">' . esc_html( $name ) . '</span>';
	}
	if ( ! $dark ) {
		$dark = $light;
	}
	return sprintf(
		'<img data-logo data-logo-light="%1$s" data-logo-dark="%2$s" src="%1$s" alt="%3$s" class="%4$s" width="160" height="64" decoding="async">',
		esc_url( $light ),
		esc_url( $dark ),
		esc_attr( $name ),
		esc_attr( $class )
	);
}

/**
 * Resolve a brand asset URL by its source filename. The content migration
 * side-loaded these images under SEO-descriptive slugs (not the source
 * filenames), so we map each source filename to the Media-Library attachment
 * slug here. Falls back to the attachment whose slug literally matches the
 * filename, then to a theme assets/brand/ copy if present.
 */
function buckleup_asset_url( string $filename ): string {
	static $cache = array();
	if ( isset( $cache[ $filename ] ) ) {
		return $cache[ $filename ];
	}

	// Source filename → migrated Media-Library attachment slug (CONTENT task).
	$slug_map = array(
		'logo.png'             => 'buckleup-driving-school-logo-light',
		'logo-dark.png'        => 'buckleup-driving-school-logo-dark',
		'image2.png'           => 'buckleup-hero-background',
		'hero_card_image.png'  => 'buckleup-hero-card',
		'farhad-instructor.jpg' => 'farhad-sanaeifar-senior-instructor',
		'owner_withcar.png'    => 'buckleup-owner-with-car', // /about Mission photo
	);

	$candidates = array();
	if ( isset( $slug_map[ $filename ] ) ) {
		$candidates[] = $slug_map[ $filename ];
	}
	$candidates[] = sanitize_title( pathinfo( $filename, PATHINFO_FILENAME ) );

	$url = '';
	foreach ( $candidates as $slug ) {
		$found = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'name'           => $slug,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		if ( ! empty( $found ) ) {
			$url = (string) wp_get_attachment_url( $found[0] );
			break;
		}
	}

	if ( '' === $url && file_exists( get_theme_file_path( "assets/brand/$filename" ) ) ) {
		$url = get_theme_file_uri( "assets/brand/$filename" );
	}

	$cache[ $filename ] = $url;
	return $url;
}

/**
 * Return the optimized WebP sibling URL for ANY uploads URL if EWWW produced one
 * (it writes `<file>.webp` alongside the original), else ''.
 *
 * Split out of buckleup_asset_webp_url() (identical logic) so callers that already
 * hold a URL — e.g. an image an admin picked in the Elementor hero widget's Media
 * control, which has no brand-asset filename key — get the same next-gen treatment.
 *
 * @param string $url Absolute attachment URL.
 * @return string Absolute .webp URL, or '' if none / not an uploads URL.
 */
function buckleup_webp_sibling_url( string $url ): string {
	if ( '' === $url ) {
		return '';
	}
	// Map the URL back to its file path to check for the .webp sibling on disk.
	$uploads = wp_get_upload_dir();
	if ( empty( $uploads['baseurl'] ) || 0 !== strpos( $url, $uploads['baseurl'] ) ) {
		return ''; // theme assets/ fallback (or an off-site URL) — no EWWW webp there
	}
	$path = $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
	return file_exists( $path . '.webp' ) ? $url . '.webp' : '';
}

/**
 * Return the optimized WebP sibling URL for a brand asset if EWWW produced one
 * (it writes `<file>.webp` alongside the original), else ''. We serve next-gen
 * WebP for the hero LCP via <picture>/preload because EWWW's front-end <img>
 * rewrite isn't enabled on this nginx + Cache-Enabler stack.
 *
 * @param string $filename Source filename (same key as buckleup_asset_url()).
 * @return string Absolute .webp URL, or '' if none.
 */
function buckleup_asset_webp_url( string $filename ): string {
	return buckleup_webp_sibling_url( buckleup_asset_url( $filename ) );
}

/* -------------------------------------------------------------------------
 * Shared hero partials — the trust-badge row + the right-column visual (hero
 * card image + Toyota badge + 4.98/200+ rating card + Farhad instructor chip).
 * Used by BOTH home-hero and location-hero so the two-column layout stays
 * identical (production parity). Return escaped HTML strings.
 *
 * Every argument below is OPTIONAL and defaults to the exact production copy, so
 * the no-arg calls in patterns/location-hero.php keep rendering byte-identically.
 * The args exist for the Elementor "BuckleUp Hero" widget (inc/elementor-widgets/),
 * which feeds admin-edited values through the very same renderers — one code path,
 * so the editable hero can never drift from the hand-coded one.
 * ---------------------------------------------------------------------- */

/**
 * The hero trust badges. Defaults to the three production badges: ICBC Certified /
 * 5-Star Rated / Dual-Control Vehicles (production order).
 *
 * @param array|null $badges Optional. List of ['icon' => <buckleup_icon name>, 'text' => string].
 *                           null (the default) renders the three production badges;
 *                           an explicit empty array renders none — which is how the
 *                           Elementor widget expresses "all badge rows deleted".
 * @return string Escaped HTML.
 */
function buckleup_hero_trust_badges( ?array $badges = null ): string {
	if ( null === $badges ) {
		$badges = array(
			array( 'icon' => 'shield-check', 'text' => __( 'ICBC Certified', 'buckleup' ) ),
			array( 'icon' => 'star',         'text' => __( '5-Star Rated', 'buckleup' ) ),
			array( 'icon' => 'check',        'text' => __( 'Dual-Control Vehicles', 'buckleup' ) ),
		);
	}
	$out = '';
	foreach ( $badges as $b ) {
		$b = wp_parse_args( (array) $b, array( 'icon' => '', 'text' => '' ) );
		$out .= '<div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full glass border border-border/50">'
			. buckleup_icon( $b['icon'], 'w-4 h-4 text-accent' )
			. '<span class="text-xs font-medium text-muted-foreground">' . esc_html( $b['text'] ) . '</span></div>';
	}
	return $out;
}

/**
 * The hero right-column visual: 3D-tilt card with the hero photo, Toyota badge,
 * floating 4.98/200+ rating card, and the Farhad instructor chip. lg-only.
 *
 * @param array $args Optional overrides; every key defaults to today's production copy.
 *   card_image        string Card photo URL. ''  → the migrated hero_card_image.png.
 *   card_alt          string Card photo alt text.
 *   instructor_name   string Chip name; also the chip photo's alt text.
 *   instructor_photo  string Chip photo URL. '' → the migrated farhad-instructor.jpg.
 *   instructor_role   string Chip subtitle.
 *   instructor_rating string Chip rating pill value (e.g. '4.9').
 *   rating_value      string Floating card's big rating (e.g. '4.98').
 *   rating_caption    string Floating card's caption under the stars.
 *   vehicle_badge     string Top-left glass badge label (e.g. 'Toyota').
 * @return string Escaped HTML.
 */
function buckleup_hero_visual( array $args = array() ): string {
	$args = wp_parse_args( $args, array(
		'card_image'        => '',
		'card_alt'          => __( 'Farhad Sanaeifar with BuckleUp Driving School car', 'buckleup' ),
		'instructor_name'   => 'Farhad Sanaeifar',
		'instructor_photo'  => '',
		'instructor_role'   => __( 'Senior Instructor • ICBC Certified', 'buckleup' ),
		'instructor_rating' => '4.9',
		'rating_value'      => '4.98',
		'rating_caption'    => __( 'Based on 200+ reviews', 'buckleup' ),
		'vehicle_badge'     => __( 'Toyota', 'buckleup' ),
	) );

	// '' means "use the migrated brand asset" — so an emptied/deleted Media control
	// falls back to the production image instead of blanking the card.
	$card        = '' !== $args['card_image'] ? $args['card_image'] : buckleup_asset_url( 'hero_card_image.png' );
	$card_webp   = buckleup_webp_sibling_url( $card );   // ~136KB vs 184KB JPG
	$farhad      = '' !== $args['instructor_photo'] ? $args['instructor_photo'] : buckleup_asset_url( 'farhad-instructor.jpg' );
	$farhad_webp = buckleup_webp_sibling_url( $farhad ); // ~135KB vs 434KB JPG

	// Initials shown only when the chip photo is missing. Derived from the name so a
	// renamed instructor stays correct; the default name yields the previous "FS".
	$words    = preg_split( '/\s+/u', trim( (string) $args['instructor_name'] ), -1, PREG_SPLIT_NO_EMPTY );
	$initials = '';
	foreach ( array_slice( (array) $words, 0, 2 ) as $word ) {
		$initials .= strtoupper( mb_substr( $word, 0, 1 ) );
	}

	ob_start();
	?>
	<div data-tilt class="relative hidden lg:block">
		<div data-tilt-card class="relative w-full max-w-[500px] mx-auto">
			<div class="relative rounded-3xl overflow-hidden shadow-2xl glow-primary">
				<?php if ( $card ) : ?>
					<picture>
						<?php if ( $card_webp ) : ?><source srcset="<?php echo esc_url( $card_webp ); ?>" type="image/webp"><?php endif; ?>
						<img src="<?php echo esc_url( $card ); ?>" alt="<?php echo esc_attr( $args['card_alt'] ); ?>" class="w-full h-[400px] object-cover" decoding="async">
					</picture>
				<?php endif; ?>
				<div class="absolute inset-0 bg-gradient-to-t from-background/90 via-transparent to-transparent"></div>
			</div>

			<!-- Instructor chip -->
			<div class="-mt-6 glass p-4 rounded-2xl relative z-10 mx-4 md:mx-0 shadow-lg">
				<div class="flex items-center gap-3">
					<div class="relative">
						<?php if ( $farhad ) : ?>
							<picture>
								<?php if ( $farhad_webp ) : ?><source srcset="<?php echo esc_url( $farhad_webp ); ?>" type="image/webp"><?php endif; ?>
								<img src="<?php echo esc_url( $farhad ); ?>" alt="<?php echo esc_attr( $args['instructor_name'] ); ?>" class="w-12 h-12 rounded-full object-cover border-2 border-accent" decoding="async">
							</picture>
						<?php else : ?>
							<span class="w-12 h-12 rounded-full bg-muted border-2 border-accent flex items-center justify-center text-sm font-bold"><?php echo esc_html( $initials ); ?></span>
						<?php endif; ?>
						<div class="absolute -bottom-1 -right-1 w-4 h-4 bg-accent rounded-full border-2 border-background flex items-center justify-center">
							<?php echo buckleup_icon( 'check', 'w-3 h-3 text-background' ); // phpcs:ignore ?>
						</div>
					</div>
					<div class="flex-1">
						<div class="text-sm font-semibold text-foreground"><?php echo esc_html( $args['instructor_name'] ); ?></div>
						<div class="text-xs text-muted-foreground"><?php echo esc_html( $args['instructor_role'] ); ?></div>
					</div>
					<div class="flex items-center gap-1 px-2 py-1 bg-yellow-500/20 rounded-full">
						<?php echo buckleup_icon( 'star', 'w-3 h-3 fill-yellow-500 text-yellow-500' ); // phpcs:ignore ?>
						<span class="text-xs font-bold text-yellow-500"><?php echo esc_html( $args['instructor_rating'] ); ?></span>
					</div>
				</div>
			</div>

			<!-- Floating rating card -->
			<div class="absolute -top-4 -right-8 glass p-4 rounded-2xl shadow-xl animate-float" style="animation-delay:2s;">
				<div class="flex items-center gap-2 mb-2">
					<span class="text-3xl font-bold text-foreground"><?php echo esc_html( $args['rating_value'] ); ?></span>
					<div class="flex">
						<?php for ( $i = 0; $i < 5; $i++ ) : ?>
							<?php echo buckleup_icon( 'star', 'w-4 h-4 fill-yellow-500 text-yellow-500' ); // phpcs:ignore ?>
						<?php endfor; ?>
					</div>
				</div>
				<div class="text-[10px] text-muted-foreground"><?php echo esc_html( $args['rating_caption'] ); ?></div>
			</div>

			<!-- Toyota badge -->
			<div class="absolute top-4 left-4 glass px-3 py-2 rounded-xl">
				<div class="flex items-center gap-2">
					<div class="w-2 h-2 bg-accent rounded-full animate-pulse"></div>
					<span class="text-xs font-medium text-foreground"><?php echo esc_html( $args['vehicle_badge'] ); ?></span>
				</div>
			</div>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * The HOME hero, end to end — the single source of truth for that markup.
 *
 * Both callers render through here so they can never drift:
 *   - patterns/home-hero.php  (the block pattern / [buckleup_section name="home-hero"])
 *   - Buckleup_Hero_Widget    (the Elementor widget, inc/elementor-widgets/)
 *
 * Every argument is OPTIONAL and defaults to the exact production copy, so a
 * no-arg call — and an un-edited Elementor widget — emit byte-identical HTML to
 * the previously hand-coded pattern. The HTML below is deliberately indented from
 * column 0 (not to the function body): that reproduces the pattern file's original
 * whitespace verbatim, which is what makes the "no visual change" diff empty.
 *
 * @param array $args Optional overrides.
 *   bg_image          string Background photo URL. '' → the migrated image2.png.
 *   bg_alt            string Background photo alt text.
 *   headline          string H1 plain-text lead ("Master the Road").
 *   headline_gradient string H1 .gradient-text span ("with Confidence").
 *   subtitle          string Lede paragraph.
 *   cta_label         string Primary button label.
 *   cta_url           string Primary button href.
 *   cta_target        string Optional <a target> (e.g. '_blank'); '' omits it.
 *   cta_rel           string Optional <a rel> (e.g. 'nofollow'); '' omits it.
 *   badges            array|null Trust badges; see buckleup_hero_trust_badges().
 *   visual            array  Right-column overrides; see buckleup_hero_visual().
 * @return string Escaped HTML (the <section> only — no block delimiters).
 */
function buckleup_hero_markup( array $args = array() ): string {
	$defaults = array(
		'bg_image'          => '',
		'bg_alt'            => __( 'Scenic winding road', 'buckleup' ),
		'headline'          => __( 'Master the Road', 'buckleup' ),
		'headline_gradient' => __( 'with Confidence', 'buckleup' ),
		'subtitle'          => __( 'BuckleUp Driving School is a trusted driving school that Vancouver learners choose. We offer expert driving lessons in North Vancouver, Coquitlam, Port Moody, and the Tri-Cities, along with ICBC road test preparation.', 'buckleup' ),
		'cta_label'         => __( 'Start Learning Today', 'buckleup' ),
		'cta_url'           => '/#most-popular',
		'cta_target'        => '',
		'cta_rel'           => '',
		'badges'            => null,
		'visual'            => array(),
	);
	$args = wp_parse_args( $args, $defaults );

	// '' means "use the migrated brand asset" — an emptied/deleted Media control
	// falls back to the production background instead of blanking the LCP image.
	$bg      = '' !== $args['bg_image'] ? $args['bg_image'] : buckleup_asset_url( 'image2.png' );
	$bg_webp = buckleup_webp_sibling_url( $bg ); // optimized next-gen LCP source
	$bg_alt  = $args['bg_alt'];

	$headline          = $args['headline'];
	$headline_gradient = $args['headline_gradient'];
	$subtitle          = $args['subtitle'];
	$cta_label         = $args['cta_label'];
	// An emptied URL control would emit href="" — keep the pricing anchor instead.
	$cta_url           = '' !== (string) $args['cta_url'] ? (string) $args['cta_url'] : $defaults['cta_url'];
	$badges            = is_array( $args['badges'] ) ? $args['badges'] : null;
	$visual            = (array) $args['visual'];

	// Extra <a> attributes, escaped by buckleup_attrs(). Empty by default so the
	// untouched CTA still renders the bare `<a href="/#most-popular">` it always did.
	$cta_attrs = buckleup_attrs( array_filter( array(
		'target' => $args['cta_target'],
		'rel'    => $args['cta_rel'],
	) ) );
	$cta_attrs = '' !== $cta_attrs ? ' ' . $cta_attrs : '';

	ob_start();
	?>
<section class="relative min-h-screen w-full overflow-hidden flex items-center justify-center bg-background">

	<!-- Background image + overlays -->
	<div class="absolute inset-0 z-0">
		<?php if ( $bg ) : ?>
			<picture>
				<?php if ( $bg_webp ) : ?>
					<source srcset="<?php echo esc_url( $bg_webp ); ?>" type="image/webp">
				<?php endif; ?>
				<img src="<?php echo esc_url( $bg ); ?>" alt="<?php echo esc_attr( $bg_alt ); ?>" class="absolute inset-0 w-full h-full object-cover" fetchpriority="high" loading="eager" decoding="async">
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

	<div class="container relative z-10 mx-auto px-4 pt-16 pb-24">
		<div class="grid lg:grid-cols-[1.2fr_1fr] gap-12 items-center">

			<!-- Left: typography -->
			<div class="text-left space-y-8 lg:col-span-1 xl:pr-8">
				<div data-reveal class="flex flex-wrap gap-3">
					<?php echo buckleup_hero_trust_badges( $badges ); // phpcs:ignore WordPress.Security.EscapeOutput — escaped within helper ?>
				</div>

				<h1 data-reveal data-reveal-y="30" class="text-5xl sm:text-6xl md:text-7xl lg:text-[5.5rem] xl:text-[6.5rem] font-bold tracking-tighter text-foreground leading-[0.95]">
					<?php echo esc_html( $headline ); ?> <span class="gradient-text"><?php echo esc_html( $headline_gradient ); ?></span>
				</h1>

				<p data-reveal data-reveal-y="30" class="text-lg sm:text-xl text-muted-foreground max-w-lg leading-relaxed">
					<?php echo esc_html( $subtitle ); ?>
				</p>

				<div data-reveal class="flex flex-wrap gap-4 mb-8">
					<a href="<?php echo esc_url( $cta_url ); ?>"<?php echo $cta_attrs; // phpcs:ignore WordPress.Security.EscapeOutput — escaped by buckleup_attrs() ?>>
						<?php
						buckleup_button( array(
							'label' => $cta_label,
							'size'  => 'lg',
							'class' => 'h-14 px-8 rounded-full font-semibold text-base shine glow-primary',
							'icon'  => buckleup_icon( 'arrow-right', 'ml-2 h-5 w-5' ),
						) );
						?>
					</a>
				</div>
			</div>

			<!-- Right: hero card + overlay cards (shared with location-hero) -->
			<?php echo buckleup_hero_visual( $visual ); // phpcs:ignore WordPress.Security.EscapeOutput — escaped within helper ?>
		</div>
	</div>
</section>
<?php
	// Emit LF regardless of how this file happens to be stored. Inline HTML inherits
	// the source file's line endings, and this repo has a mix of LF and CRLF files —
	// without this, the same hero would ship different bytes from a Windows checkout
	// than from macOS/CI, which is pure noise in HTML parity diffs (the previously
	// hard-coded pattern file was LF). Purely cosmetic: \r is inter-tag whitespace.
	return str_replace( "\r\n", "\n", (string) ob_get_clean() );
}

/**
 * Public primary-nav items: Home · Services · Graduates · FAQ · Contact · Blog ·
 * About, plus the Locations dropdown (appended in the header). Services is shown
 * (client decision); Instructors is intentionally NOT in the primary nav. Each
 * item carries a lucide icon rendered before the label. Locations is a dropdown
 * built from the location CPT so it stays in sync with content.
 */
function buckleup_nav_items(): array {
	// Per-item lucide icons mirror the source Navbar.tsx (Home/Briefcase/ImageIcon/
	// HelpCircle/Phone/BookOpen/Info); rendered before the label at gap-1.5 in
	// site-header.php. `active` drives the white rounded "box" highlight (+ blue
	// icon) per item, mirroring the source's pathname===href / startsWith logic.
	// Page paths carry a trailing slash to match WP's permalink structure — the
	// slash-less form triggers a 301 redirect hop on every click (QA B2). Anchor
	// links (/#graduates, /#faq) and home_url('/') are already correct.
	$items = array(
		array( 'name' => 'Home', 'href' => home_url( '/' ), 'icon' => 'home', 'active' => is_front_page() ),
		array( 'name' => 'Services', 'href' => home_url( '/services/' ), 'icon' => 'briefcase', 'active' => buckleup_path_is( 'services' ) ),
		// Graduates + FAQ are #anchors to home sections — never their own active box
		// (Home carries the highlight on the front page).
		array( 'name' => 'Graduates', 'href' => home_url( '/#graduates' ), 'icon' => 'image', 'active' => false ),
		array( 'name' => 'FAQ', 'href' => home_url( '/#faq' ), 'icon' => 'help-circle', 'active' => false ),
		array( 'name' => 'Contact', 'href' => home_url( '/contact/' ), 'icon' => 'phone', 'active' => buckleup_path_is( 'contact' ) ),
		array( 'name' => 'Blog', 'href' => home_url( '/blog/' ), 'icon' => 'book-open', 'active' => buckleup_path_is( 'blog', true ) ),
		array( 'name' => 'About', 'href' => home_url( '/about/' ), 'icon' => 'info', 'active' => buckleup_path_is( 'about' ) ),
	);
	return $items;
}

/**
 * The differentiated header CTA → the free ICBC Class 4 practice-test hub. Uses
 * the EMERALD accent (not the red "Book a Lesson" primary) so the two CTAs read
 * as distinct actions. Returns null when the quiz plugin is inactive (graceful
 * absence). `active` is true on any practice-center page (hub or category), set
 * from the plugin's own page-context detection.
 *
 * @return array{href:string,label:string,active:bool}|null
 */
function buckleup_quiz_nav_cta(): ?array {
	if ( ! function_exists( 'buckleup_quiz_hub_url' ) || ! function_exists( 'buckleup_quiz_page_context' ) ) {
		return null;
	}
	$ctx = buckleup_quiz_page_context();
	return array(
		'href'   => buckleup_quiz_hub_url(),
		'label'  => __( 'Free Practice Test', 'buckleup' ),
		'active' => '' !== $ctx['type'],
	);
}

/**
 * Whether the current request path matches a top-level page slug — mirrors the
 * source's `pathname === href` (exact) / `pathname.startsWith(href)` (prefix, for
 * Blog so posts count). Compares the request path, not WP conditionals, so it
 * works whether the page is a real WP page or a CPT route.
 *
 * @param string $slug          Top-level slug, e.g. 'services', 'blog'.
 * @param bool   $starts_with   Prefix match (true) vs exact segment (false).
 * @return bool
 */
function buckleup_path_is( string $slug, bool $starts_with = false ): bool {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$path = trim( (string) $path, '/' );             // e.g. "blog/my-post" or "services".
	$first = explode( '/', $path )[0];               // first segment.
	return $starts_with ? ( $slug === $first ) : ( $slug === $path );
}

/**
 * Location items for the nav dropdown + footer, from the CPT helper when present,
 * else the known v1 set (exact slugs from PLAN §2).
 */
function buckleup_location_items(): array {
	// Trailing-slash all hrefs to match WP permalinks (no 301 hop on click — QA B2).
	if ( function_exists( 'buckleup_get_locations' ) ) {
		$locs = buckleup_get_locations();
		if ( ! empty( $locs ) ) {
			return array_map( static function ( $l ) {
				return array( 'name' => $l['title'], 'href' => user_trailingslashit( $l['url'] ) );
			}, $locs );
		}
	}
	$fallback = array(
		'north-vancouver' => 'North Vancouver',
		'port-coquitlam'  => 'Port Coquitlam',
		'port-moody'      => 'Port Moody',
		'coquitlam'       => 'Coquitlam',
		'tri-cities'      => 'Tri-Cities',
	);
	$out = array();
	foreach ( $fallback as $slug => $name ) {
		$out[] = array( 'name' => $name, 'href' => home_url( "/locations/$slug/" ) );
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Dynamic section block. The header/footer/home/page/location sections are PHP
 * in patterns/*.php. They MUST render at template-render time (not pattern-
 * registration time) because some — notably location-hero — depend on the main
 * query (get_queried_object()), which isn't available at `init`. So instead of
 * static block patterns (whose `content` is captured once at init), we register
 * ONE dynamic block `buckleup/section` with a render_callback that includes the
 * PHP file when the template actually renders. Templates reference sections via
 * `<!-- wp:buckleup/section {"name":"location-hero"} /-->`.
 * ---------------------------------------------------------------------- */

/** Allowed section names → their PHP file in patterns/ (allowlist; no arbitrary include). */
function buckleup_sections(): array {
	return array(
		'site-header', 'site-footer',
		'home-hero', 'home-graduates', 'home-pricing', 'home-testimonials', 'home-faq', 'home-blog',
		'location-hero',
		'page-instructors', 'page-services', 'page-contact', 'page-about', 'page-resources', 'page-icbc',
		'practice-test', // ICBC Class 4 knowledge practice-test hub + category pages (buckleup-quiz).
		'exam',          // Distraction-free timed Practice Exam Center (one page, ?mode=).
	);
}

add_action( 'init', function () {
	register_block_pattern_category( 'buckleup', array( 'label' => __( 'BuckleUp', 'buckleup' ) ) );

	register_block_type( 'buckleup/section', array(
		'api_version'     => 3,
		'attributes'      => array( 'name' => array( 'type' => 'string', 'default' => '' ) ),
		'render_callback' => 'buckleup_render_section_block',
		'supports'        => array( 'html' => false ),
	) );
} );

/**
 * Blog index (page_for_posts) shows 6 posts/page → a clean 2-col × 3-row grid in
 * patterns/home-blog.php. Scoped to the main query on the posts page only, so the
 * global posts_per_page option (feeds, REST, other archives) is left untouched.
 */
add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_home() ) {
		$query->set( 'posts_per_page', 6 );
	}
} );

/**
 * Render a section by name at template-render time (correct query context).
 *
 * @param array $attributes Block attributes; expects ['name' => '<section>'].
 * @return string Rendered HTML, or '' for an unknown/missing section.
 */
function buckleup_render_section_block( $attributes ): string {
	$name = isset( $attributes['name'] ) ? sanitize_file_name( (string) $attributes['name'] ) : '';
	if ( '' === $name || ! in_array( $name, buckleup_sections(), true ) ) {
		return '';
	}
	$file = get_theme_file_path( "patterns/$name.php" );
	if ( ! file_exists( $file ) ) {
		return '';
	}
	ob_start();
	include $file;
	return (string) ob_get_clean();
}
