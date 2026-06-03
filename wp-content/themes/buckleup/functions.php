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
	if ( is_front_page() && function_exists( 'buckleup_asset_url' ) ) {
		$hero = buckleup_asset_url( 'image2.png' );
		if ( $hero ) {
			printf(
				'<link rel="preload" href="%s" as="image" fetchpriority="high">' . "\n",
				esc_url( $hero )
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
	}
} );

// Add type="module" to our Vite script.
add_filter( 'script_loader_tag', function ( $tag, $handle, $src ) {
	if ( 'buckleup-main' === $handle ) {
		return '<script type="module" src="' . esc_url( $src ) . '" id="' . esc_attr( $handle ) . '-js"></script>' . "\n";
	}
	return $tag;
}, 10, 3 );

// Prevent a flash of wrong theme: resolve and apply .dark BEFORE first paint from
// the stored preference (cookie/localStorage, "system" honored). Also add the
// .no-transitions guard so the 150ms global color transition doesn't animate the
// initial light->dark swap; src/js/main.js removes it on load. Priority 1 keeps
// this ahead of any enqueued <head> CSS/JS.
add_action( 'wp_head', function () {
	echo "<script>(function(){var e=document.documentElement;e.classList.add('no-transitions');try{var s=localStorage.getItem('buckleup-theme')||'system';var d=s==='dark'||(s==='system'&&matchMedia('(prefers-color-scheme: dark)').matches);if(d){e.classList.add('dark');e.style.colorScheme='dark';}}catch(err){}})();</script>\n";
}, 1 );
