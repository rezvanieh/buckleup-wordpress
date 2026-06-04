<?php
/**
 * Lean security baseline (PLAN.md §6).
 *
 * v1 is a marketing site with no payments, so this is the free, no-PCI-scope
 * hardening: kill XML-RPC, lock user/author enumeration, and stop leaking the
 * WordPress version. Login limiting / firewall / headers live at the
 * server/plugin layer (DevOps + a security plugin), not here.
 *
 * @package BuckleUp_Core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 * 1. Disable XML-RPC entirely.
 *    Closes pingback SSRF + brute-force amplification (system.multicall).
 * ---------------------------------------------------------------------- */

add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Strip pingback/ping methods so even a forced xmlrpc.php can't relay them.
 *
 * @param array<string,callable> $methods Registered XML-RPC methods.
 * @return array<string,callable>
 */
function buckleup_remove_xmlrpc_pingback_methods( $methods ) {
	unset(
		$methods['pingback.ping'],
		$methods['pingback.extensions.getPingbacks'],
		$methods['system.multicall'],
		$methods['system.listMethods'],
		$methods['system.getCapabilities']
	);
	return $methods;
}
add_filter( 'xmlrpc_methods', 'buckleup_remove_xmlrpc_pingback_methods' );

/**
 * Hard-block the xmlrpc.php endpoint.
 *
 * Filtering `xmlrpc_methods` removes the WordPress-defined methods (pingback,
 * etc.), but `system.multicall` / `system.listMethods` are registered by the
 * parent IXR_Server independently of that filter, so a marketing site that has
 * no use for XML-RPC at all is best served by refusing the endpoint outright.
 * This closes pingback SSRF *and* the system.multicall brute-force amplifier in
 * one move. (Server-level deny in nginx is the belt-and-suspenders complement.)
 */
function buckleup_block_xmlrpc_endpoint() {
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		status_header( 403 );
		nocache_headers();
		exit( 'XML-RPC services are disabled on this site.' );
	}
}
add_action( 'init', 'buckleup_block_xmlrpc_endpoint', 0 );

// Remove the X-Pingback response header + the RSD/pingback <link> in <head>.
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

/* -------------------------------------------------------------------------
 * 2. Lock user / author enumeration.
 *    - REST: hide the users endpoint from unauthenticated requests.
 *    - ?author=N : block the numeric-id → username redirect.
 *    - Author archives: 404 for everyone (no author pages on a marketing site).
 *    - Remove the oEmbed author field + the rel=alternate author <link>.
 * ---------------------------------------------------------------------- */

/**
 * Remove the wp/v2/users REST routes for users who can't list users.
 *
 * @param array<string,mixed> $endpoints REST endpoints.
 * @return array<string,mixed>
 */
function buckleup_restrict_rest_users( $endpoints ) {
	if ( current_user_can( 'list_users' ) ) {
		return $endpoints;
	}
	if ( isset( $endpoints['/wp/v2/users'] ) ) {
		unset( $endpoints['/wp/v2/users'] );
	}
	if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'buckleup_restrict_rest_users' );

/**
 * Block ?author=N enumeration and author archive pages on the front end.
 *
 * WordPress 301-redirects /?author=1 to /author/{login}/, leaking the username;
 * we 404 both the numeric query and any author archive before that happens.
 */
function buckleup_block_author_enumeration() {
	if ( is_admin() ) {
		return;
	}

	// /?author=123 (only the numeric enumeration form; ignore other uses).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public query guard.
	if ( ! is_user_logged_in() && isset( $_GET['author'] ) && '' !== $_GET['author'] ) {
		$author = sanitize_text_field( wp_unslash( $_GET['author'] ) );
		if ( ctype_digit( (string) $author ) ) {
			buckleup_force_404();
			return;
		}
	}

	// /author/{login}/ archives.
	if ( is_author() ) {
		buckleup_force_404();
	}
}
add_action( 'template_redirect', 'buckleup_block_author_enumeration' );

/**
 * Send a clean 404 for blocked enumeration requests.
 */
function buckleup_force_404() {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}

// Drop the author object from oEmbed responses (it exposes name + author URL).
add_filter( 'oembed_response_data', function ( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
} );

// Remove the rel=alternate oEmbed discovery links from <head>.
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

/* -------------------------------------------------------------------------
 * 3. Stop leaking the WordPress version.
 *    - <meta name="generator"> in <head> + RSS feeds.
 *    - The ?ver=X.Y query string on enqueued CSS/JS.
 * ---------------------------------------------------------------------- */

remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/**
 * Strip the core-version `ver` query arg from script/style URLs so the WP
 * version isn't advertised on every asset. Theme/plugin asset versions set
 * explicitly are left intact.
 *
 * @param string $src Asset URL.
 * @return string
 */
function buckleup_remove_core_version_query( $src ) {
	global $wp_version;
	if ( $src && false !== strpos( $src, 'ver=' . $wp_version ) ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}
add_filter( 'style_loader_src', 'buckleup_remove_core_version_query', 9999 );
add_filter( 'script_loader_src', 'buckleup_remove_core_version_query', 9999 );
