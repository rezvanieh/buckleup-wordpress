<?php
/**
 * Plugin Name: BuckleUp SEO — PWA Manifest & Icons
 * Description: Serves a Web App Manifest at /manifest.webmanifest built from the
 *              brand icons in the Media Library, and injects the manifest link,
 *              theme-color, and apple-touch-icon / favicon <link>s into <head>.
 *              Companion to 10-buckleup-seo.php. Verbatim from the source
 *              public/manifest.json, but icon URLs resolve to the real uploads so
 *              it works in dev and production without hardcoded paths.
 *
 * Author:      BuckleUp team
 * Version:     1.0.0
 *
 * @package BuckleUp_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a brand icon's URL from the Media Library by attachment slug, falling
 * back to a conventional /wp-content/uploads path, then to the site icon.
 *
 * @param string $slug Attachment post_name (e.g. "buckleup-icon-192").
 * @param int    $size Square size in px, used for the site-icon fallback.
 * @return string URL or '' when nothing resolves.
 */
function buckleup_pwa_icon_url( $slug, $size = 512 ) {
	$cache_key = 'buckleup_pwa_icon_' . $slug;
	$cached    = wp_cache_get( $cache_key, 'buckleup_pwa' );
	if ( false !== $cached ) {
		return $cached;
	}

	$url   = '';
	$query = get_posts(
		array(
			'name'        => $slug,
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	if ( $query ) {
		$url = (string) wp_get_attachment_url( $query[0] );
	}
	if ( '' === $url ) {
		$site_icon = get_site_icon_url( $size );
		$url       = $site_icon ? $site_icon : '';
	}

	wp_cache_set( $cache_key, $url, 'buckleup_pwa' );
	return $url;
}

/**
 * Build the manifest array (verbatim from source public/manifest.json).
 *
 * @return array
 */
function buckleup_pwa_manifest() {
	$icon_192 = buckleup_pwa_icon_url( 'buckleup-icon-192', 192 );
	$icon_512 = buckleup_pwa_icon_url( 'buckleup-icon-512', 512 );
	$apple    = buckleup_pwa_icon_url( 'buckleup-apple-touch-icon', 180 );

	$icons = array();
	if ( $icon_192 ) {
		$icons[] = array(
			'src'   => $icon_192,
			'sizes' => '192x192',
			'type'  => 'image/png',
		);
	}
	if ( $icon_512 ) {
		$icons[] = array(
			'src'     => $icon_512,
			'sizes'   => '512x512',
			'type'    => 'image/png',
			'purpose' => 'any maskable',
		);
	}
	if ( $apple ) {
		$icons[] = array(
			'src'     => $apple,
			'sizes'   => '180x180',
			'type'    => 'image/png',
			'purpose' => 'apple touch icon',
		);
	}

	return array(
		'name'             => 'BuckleUp Driving School',
		'short_name'       => 'BuckleUp',
		'description'      => "Vancouver's premier driving academy with expert instructors.",
		'start_url'        => '/',
		'display'          => 'standalone',
		'background_color' => '#ffffff',
		'theme_color'      => '#000000',
		'icons'            => $icons,
	);
}

/**
 * Register a pretty rewrite for /manifest.webmanifest and serve it as JSON.
 * (Also answers /manifest.json for parity with the source app's link.)
 */
add_action(
	'init',
	function () {
		add_rewrite_rule( '^manifest\.webmanifest$', 'index.php?buckleup_manifest=1', 'top' );
		add_rewrite_rule( '^manifest\.json$', 'index.php?buckleup_manifest=1', 'top' );
	}
);

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'buckleup_manifest';
		return $vars;
	}
);

// Don't let WordPress' canonical redirect append a trailing slash to the
// manifest request (it would 301 /manifest.webmanifest → /manifest.webmanifest/).
add_filter(
	'redirect_canonical',
	function ( $redirect_url ) {
		return get_query_var( 'buckleup_manifest' ) ? false : $redirect_url;
	}
);

// Path fallback: if the rewrite rule is ever missing (e.g. another plugin flushed
// rewrites before this mu-plugin's init rule re-registered), still serve the
// manifest by matching the raw request path early, before WP resolves a 404.
add_action(
	'parse_request',
	function ( $wp ) {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ) : '';
		if ( 'manifest.webmanifest' === $path || 'manifest.json' === $path ) {
			$wp->query_vars['buckleup_manifest'] = 1;
		}
	}
);

add_action(
	'template_redirect',
	function () {
		if ( ! get_query_var( 'buckleup_manifest' ) ) {
			return;
		}
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( buckleup_pwa_manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}
);

/**
 * Inject the manifest link, theme-color, and icon <link>s into <head>.
 * WordPress already prints the favicon/apple-touch-icon for the Site Icon, but
 * we add the explicit PWA manifest + theme-color which core does not.
 */
add_action(
	'wp_head',
	function () {
		if ( is_admin() ) {
			return;
		}
		printf( "<link rel=\"manifest\" href=\"%s\" />\n", esc_url( home_url( '/manifest.webmanifest' ) ) );
		echo "<meta name=\"theme-color\" content=\"#000000\" />\n";
		echo "<meta name=\"mobile-web-app-capable\" content=\"yes\" />\n";
		echo "<meta name=\"apple-mobile-web-app-capable\" content=\"yes\" />\n";
		echo "<meta name=\"apple-mobile-web-app-title\" content=\"BuckleUp\" />\n";
	},
	8
);
