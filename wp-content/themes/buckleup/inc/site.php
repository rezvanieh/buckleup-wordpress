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
		'page-instructors', 'page-services', 'page-contact', 'page-about', 'page-resources',
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
