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
	$card   = buckleup_asset_url( 'hero_card_image.png' );
	$farhad = buckleup_asset_url( 'farhad-instructor.jpg' );

	ob_start();
	?>
	<div data-tilt class="relative hidden lg:block">
		<div data-tilt-card class="relative w-full max-w-[580px] mx-auto">
			<div class="relative rounded-3xl overflow-hidden shadow-2xl glow-primary">
				<?php if ( $card ) : ?>
					<img src="<?php echo esc_url( $card ); ?>" alt="<?php esc_attr_e( 'Farhad Sanaeifar with BuckleUp Driving School car', 'buckleup' ); ?>" class="w-full h-[460px] object-cover" decoding="async">
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
	<?php
	return (string) ob_get_clean();
}

/**
 * Public primary-nav items (v1 marketing site). Services + Instructors are the
 * feature-flagged-on pages per PLAN §1. Locations is a dropdown built from the
 * location CPT so it stays in sync with content.
 */
function buckleup_nav_items(): array {
	$items = array(
		array( 'name' => 'Home', 'href' => home_url( '/' ) ),
		array( 'name' => 'Services', 'href' => home_url( '/services' ) ),
		array( 'name' => 'Instructors', 'href' => home_url( '/instructors' ) ),
		array( 'name' => 'Graduates', 'href' => home_url( '/#graduates' ) ),
		array( 'name' => 'FAQ', 'href' => home_url( '/#faq' ) ),
		array( 'name' => 'Contact', 'href' => home_url( '/contact' ) ),
		array( 'name' => 'Blog', 'href' => home_url( '/blog' ) ),
		array( 'name' => 'About', 'href' => home_url( '/about' ) ),
	);
	return $items;
}

/**
 * Location items for the nav dropdown + footer, from the CPT helper when present,
 * else the known v1 set (exact slugs from PLAN §2).
 */
function buckleup_location_items(): array {
	if ( function_exists( 'buckleup_get_locations' ) ) {
		$locs = buckleup_get_locations();
		if ( ! empty( $locs ) ) {
			return array_map( static function ( $l ) {
				return array( 'name' => $l['title'], 'href' => $l['url'] );
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
		$out[] = array( 'name' => $name, 'href' => home_url( "/locations/$slug" ) );
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
		'home-hero', 'home-graduates', 'home-pricing', 'home-testimonials', 'home-faq',
		'location-hero',
		'page-instructors', 'page-services', 'page-contact', 'page-about', 'page-resources', 'page-icbc',
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
