<?php
/**
 * BuckleUp theme bootstrap. Primary job here: enqueue the Vite-built, hashed CSS/JS
 * by reading build/.vite/manifest.json. The design/markup teams add nav menus,
 * pattern registration, and template parts.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BUCKLEUP_VERSION', '0.1.0' );

// Component library + icons (server-side shadcn reproductions emitting the exact
// class strings; behavioral components emit the data-state attrs the JS targets).
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/components.php';
require_once __DIR__ . '/inc/components-interactive.php';
// Site chrome helpers (logo, nav, locations) + header/footer/section pattern
// registration. Loaded after components so patterns can call those helpers.
require_once __DIR__ . '/inc/site.php';
// Front-end WebP <picture> wrapping for content images (post featured + body).
// EWWW's webp_for_cdn/picture_webp rewrite is INERT on this nginx+FPM+Cache-Enabler
// stack (confirmed by the content engineer; option reverted), so the theme owns
// content-image WebP delivery deterministically. The hero LCP <picture>/preload is
// separate (home-hero.php + the wp_head preload below).
require_once __DIR__ . '/inc/webp.php';
// Console (portal) shell + per-role sidebar nav for Student/Instructor/Admin pages.
require_once __DIR__ . '/inc/console.php';
// Elementor interop: registers the [buckleup_elementor]/[buckleup_section]/
// [buckleup_contact_form] shortcodes and force-enqueues Elementor's frontend CSS/JS
// site-wide (so the embedded footer + ElementsKit nav init render on every view,
// incl. non-Elementor consoles/blog), plus a defensive Geist @font-face fallback.
// NOTE: the Tailwind bundle is NOT dequeued — the design system loads site-wide.
require_once __DIR__ . '/inc/elementor.php';
// Custom Elementor widgets (inc/elementor-widgets/): the "BuckleUp Hero" widget that
// makes the front-page hero admin-editable while rendering the REAL theme markup via
// buckleup_hero_markup(). Loaded after inc/elementor.php (interop) and inc/site.php
// (the renderer + buckleup_icon_names(), which feeds the badge icon picker).
require_once __DIR__ . '/inc/elementor-widgets.php';
// [buckleup_image_credits] — CC attribution for the location hero photos (/image-credits/).
require_once __DIR__ . '/inc/credits.php';
// Front-end performance: strip dead-weight ElementsKit assets + jquery-migrate.
require_once __DIR__ . '/inc/perf.php';

add_action( 'after_setup_theme', function () {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	register_nav_menus( array(
		'primary'       => __( 'Primary Nav', 'buckleup' ),
		'locations'     => __( 'Locations Dropdown', 'buckleup' ),
		'footer_quick'  => __( 'Footer — Quick Links', 'buckleup' ),
		'footer_areas'  => __( 'Footer — Service Areas', 'buckleup' ),
	) );
} );

/**
 * Resolve a Vite manifest entry to its hashed public URL.
 */
function buckleup_vite_asset( string $entry ): ?string {
	static $manifest = null;
	$manifest_path = get_theme_file_path( 'build/.vite/manifest.json' );
	if ( null === $manifest ) {
		$manifest = file_exists( $manifest_path )
			? json_decode( file_get_contents( $manifest_path ), true )
			: array();
	}
	if ( empty( $manifest[ $entry ]['file'] ) ) {
		return null;
	}
	return get_theme_file_uri( 'build/' . $manifest[ $entry ]['file'] );
}

// Preload the self-hosted Geist Sans woff2 (the body face, used above the fold) so
// it's fetched in parallel with the stylesheet and text paints without a swap jump.
// Resolved through the manifest so the hashed filename stays correct after rebuilds.
add_action( 'wp_head', function () {
	$font = buckleup_vite_asset( 'src/fonts/Geist-Variable.woff2' );
	if ( $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( $font )
		);
	}

	// Preload the hero background — it's the front page's LCP element (QA PERF-1).
	// Front page only; the <img> itself is fetchpriority=high + loading=eager.
	// Prefer the optimized WebP (~60KB) and tag the preload with type=image/webp so
	// non-WebP browsers ignore it and fall back to the <picture> JPG (no double
	// download). If no WebP exists, preload the (already resized) JPG.
	// The background is now admin-editable (the Elementor "BuckleUp Hero" widget), so
	// resolve what that widget will ACTUALLY render before falling back to the stock
	// brand asset — otherwise a swapped hero would preload the wrong image and lose
	// the LCP win. With no hero widget present this is byte-for-byte the old path.
	if ( is_front_page() && function_exists( 'buckleup_asset_url' ) ) {
		$hero_src  = function_exists( 'buckleup_hero_widget_bg_url' ) ? buckleup_hero_widget_bg_url( (int) get_queried_object_id() ) : '';
		$hero_webp = $hero_src
			? ( function_exists( 'buckleup_webp_sibling_url' ) ? buckleup_webp_sibling_url( $hero_src ) : '' )
			: ( function_exists( 'buckleup_asset_webp_url' ) ? buckleup_asset_webp_url( 'image2.png' ) : '' );
		$hero      = $hero_webp ?: ( $hero_src ?: buckleup_asset_url( 'image2.png' ) );
		if ( $hero ) {
			printf(
				'<link rel="preload" href="%s" as="image" fetchpriority="high"%s>' . "\n",
				esc_url( $hero ),
				$hero_webp ? ' type="image/webp"' : ''
			);
		}
	}
}, 2 );

add_action( 'wp_enqueue_scripts', function () {
	$css = buckleup_vite_asset( 'src/css/app.css' );
	$js  = buckleup_vite_asset( 'src/js/main.js' );

	if ( $css ) {
		wp_enqueue_style( 'buckleup-app', $css, array(), BUCKLEUP_VERSION );
	} else {
		// Fallback so the site isn't unstyled if assets aren't built yet.
		wp_enqueue_style( 'buckleup-fallback', get_theme_file_uri( 'src/css/app.css' ), array(), BUCKLEUP_VERSION );
	}

	if ( $js ) {
		wp_enqueue_script( 'buckleup-main', $js, array(), BUCKLEUP_VERSION, true );
		// Auth: the register form (src/js/modules/auth.js) fetches the buckleup/v1
		// REST endpoint with the wp_rest nonce. (Harmless if the plugin later drops
		// the register nonce requirement — the header is simply ignored.)
		wp_localize_script( 'buckleup-main', 'buckleupAuth', array(
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'restUrl' => esc_url_raw( rest_url( 'buckleup/v1/' ) ),
		) );
	}
} );

// Add type="module" to our Vite script.
add_filter( 'script_loader_tag', function ( $tag, $handle, $src ) {
	if ( 'buckleup-main' === $handle ) {
		return '<script type="module" src="' . esc_url( $src ) . '" id="' . esc_attr( $handle ) . '-js"></script>' . "\n";
	}
	return $tag;
}, 10, 3 );

// Dark mode was removed (client request — it was broken). Force LIGHT before first
// paint and proactively clear any previously-stored "dark" preference (localStorage
// + cookie) so returning visitors aren't stranded in broken dark with no toggle to
// escape. The .no-transitions guard keeps the 150ms global color transition from
// animating on first paint; src/js/main.js removes it on load. Priority 1 keeps this
// ahead of any enqueued <head> CSS/JS.
add_action( 'wp_head', function () {
	echo "<script>(function(){var e=document.documentElement;e.classList.add('no-transitions');e.classList.remove('dark');e.style.colorScheme='light';try{localStorage.removeItem('buckleup-theme');document.cookie='buckleup-theme=; path=/; max-age=0';}catch(err){}})();</script>\n";
}, 1 );
