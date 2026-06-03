<?php
/**
 * BuckleUp theme bootstrap. Primary job here: enqueue the Vite-built, hashed CSS/JS
 * by reading build/.vite/manifest.json. The design/markup teams add nav menus,
 * pattern registration, and template parts.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BUCKLEUP_VERSION', '0.1.0' );

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

// Prevent a flash of wrong theme: set .dark before paint based on localStorage.
add_action( 'wp_head', function () {
	echo "<script>(function(){try{var s=localStorage.getItem('buckleup-theme')||'system';var d=s==='dark'||(s==='system'&&matchMedia('(prefers-color-scheme: dark)').matches);if(d){document.documentElement.classList.add('dark');document.documentElement.style.colorScheme='dark';}}catch(e){}})();</script>\n";
}, 1 );
