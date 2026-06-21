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
 * Return the optimized WebP sibling URL for a brand asset if EWWW produced one
 * (it writes `<file>.webp` alongside the original), else ''. We serve next-gen
 * WebP for the hero LCP via <picture>/preload because EWWW's front-end <img>
 * rewrite isn't enabled on this nginx + Cache-Enabler stack.
 *
 * @param string $filename Source filename (same key as buckleup_asset_url()).
 * @return string Absolute .webp URL, or '' if none.
 */
function buckleup_asset_webp_url( string $filename ): string {
	$url = buckleup_asset_url( $filename );
	if ( '' === $url ) {
		return '';
	}
	// Map the URL back to its file path to check for the .webp sibling on disk.
	$uploads = wp_get_upload_dir();
	if ( empty( $uploads['baseurl'] ) || 0 !== strpos( $url, $uploads['baseurl'] ) ) {
		return ''; // theme assets/ fallback — no EWWW webp there
	}
	$path = $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
	return file_exists( $path . '.webp' ) ? $url . '.webp' : '';
}

/* -------------------------------------------------------------------------
 * Shared hero partials — the trust-badge row + the right-column visual (hero
 * card image + Toyota badge + 4.98/200+ rating card + Farhad instructor chip).
 * Used by BOTH home-hero and location-hero so the two-column layout stays
 * identical (production parity). Return escaped HTML strings.
 * ---------------------------------------------------------------------- */

/**
 * The three hero trust badges: ICBC Certified / 5-Star Rated / 100% Pass
 * Guarantee (production order).
 */
function buckleup_hero_trust_badges(): string {
	$badges = array(
		array( 'icon' => 'shield-check', 'text' => __( 'ICBC Certified', 'buckleup' ) ),
		array( 'icon' => 'star',         'text' => __( '5-Star Rated', 'buckleup' ) ),
		array( 'icon' => 'check',        'text' => __( '100% Pass Guarantee', 'buckleup' ) ),
	);
	$out = '';
	foreach ( $badges as $b ) {
		$out .= '<div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full glass border border-border/50">'
			. buckleup_icon( $b['icon'], 'w-4 h-4 text-accent' )
			. '<span class="text-xs font-medium text-muted-foreground">' . esc_html( $b['text'] ) . '</span></div>';
	}
	return $out;
}

/**
 * The hero right-column visual: 3D-tilt card with the hero photo, Toyota badge,
 * floating 4.98/200+ rating card, and the Farhad instructor chip. lg-only.
 */
function buckleup_hero_visual(): string {
	$card        = buckleup_asset_url( 'hero_card_image.png' );
	$card_webp   = buckleup_asset_webp_url( 'hero_card_image.png' );   // ~136KB vs 184KB JPG
	$farhad      = buckleup_asset_url( 'farhad-instructor.jpg' );
	$farhad_webp = buckleup_asset_webp_url( 'farhad-instructor.jpg' ); // ~135KB vs 434KB JPG

	ob_start();
	?>
	<div data-tilt class="relative hidden lg:block">
		<div data-tilt-card class="relative w-full max-w-[500px] mx-auto">
			<div class="relative rounded-3xl overflow-hidden shadow-2xl glow-primary">
				<?php if ( $card ) : ?>
					<picture>
						<?php if ( $card_webp ) : ?><source srcset="<?php echo esc_url( $card_webp ); ?>" type="image/webp"><?php endif; ?>
						<img src="<?php echo esc_url( $card ); ?>" alt="<?php esc_attr_e( 'Farhad Sanaeifar with BuckleUp Driving School car', 'buckleup' ); ?>" class="w-full h-[400px] object-cover" decoding="async">
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
								<img src="<?php echo esc_url( $farhad ); ?>" alt="Farhad Sanaeifar" class="w-12 h-12 rounded-full object-cover border-2 border-accent" decoding="async">
							</picture>
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
	<?php
	return (string) ob_get_clean();
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
