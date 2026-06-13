<?php
/**
 * Front-end WebP delivery for content images (post featured images + post-body
 * images). EWWW generated `<file>.webp` siblings for every upload + size variant,
 * but EWWW's front-end rewrite (webp_for_cdn / picture_webp) and Cache Enabler's
 * url->webp rewrite do NOT engage on this nginx + PHP-FPM + Cache-Enabler stack
 * (their output-buffer parsers never fire — verified inert), so
 * the_post_thumbnail()/content <img> would otherwise ship raw PNG/JPG. We wrap
 * those <img>s in a <picture> with a WebP <source> (mirroring the original
 * srcset/sizes, each URL → its .webp sibling) so WebP-capable browsers get the
 * smaller asset and others fall back to the <img>. This is the deterministic path
 * where EWWW's rewrite is inert (the team-lead's confirmed contingency).
 *
 * Mirrors the hero's <picture> approach. Only rewrites when the .webp sibling
 * actually exists on disk; otherwise the markup is returned untouched.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Map an uploads URL to its local file path, or '' if it's not in the uploads dir.
 */
function buckleup_url_to_upload_path( string $url ): string {
	static $uploads = null;
	if ( null === $uploads ) {
		$uploads = wp_get_upload_dir();
	}
	if ( empty( $uploads['baseurl'] ) ) {
		return '';
	}
	// Normalize protocol-relative + scheme differences to a baseurl-relative path.
	$base = preg_replace( '#^https?:#', '', $uploads['baseurl'] );
	$u    = preg_replace( '#^https?:#', '', $url );
	if ( 0 !== strpos( $u, $base ) ) {
		return '';
	}
	return $uploads['basedir'] . substr( $u, strlen( $base ) );
}

/**
 * Does a `<url>.webp` sibling exist on disk for this uploads URL?
 */
function buckleup_has_webp_sibling( string $url ): bool {
	$path = buckleup_url_to_upload_path( $url );
	return '' !== $path && file_exists( $path . '.webp' );
}

/**
 * Build a WebP srcset from an existing srcset value by appending `.webp` to each
 * candidate URL — but only if every candidate has a real .webp sibling (so we
 * never emit a 404 source). Returns '' if any sibling is missing.
 *
 * @param string $srcset e.g. "https://…/x-300.png 300w, https://…/x-768.png 768w"
 */
function buckleup_webp_srcset( string $srcset ): string {
	if ( '' === trim( $srcset ) ) {
		return '';
	}
	$out = array();
	foreach ( array_map( 'trim', explode( ',', $srcset ) ) as $candidate ) {
		if ( '' === $candidate ) {
			continue;
		}
		// "<url> <descriptor>" — split on the last space.
		$pos = strrpos( $candidate, ' ' );
		$url = false === $pos ? $candidate : substr( $candidate, 0, $pos );
		$dsc = false === $pos ? '' : substr( $candidate, $pos );
		if ( ! buckleup_has_webp_sibling( $url ) ) {
			return ''; // bail — incomplete webp set, keep the original only
		}
		$out[] = $url . '.webp' . $dsc;
	}
	return implode( ', ', $out );
}

/**
 * Wrap a single <img …> tag in a <picture> with a WebP <source>, reusing the
 * img's existing srcset/sizes (or its src). No-op if it already sits in a
 * <picture>, has no resolvable webp, or isn't an uploads image.
 *
 * @param string $img_tag The <img …> HTML.
 * @return string Original tag, or <picture>…</picture>.
 */
function buckleup_wrap_img_webp( string $img_tag ): string {
	if ( false === stripos( $img_tag, '<img' ) ) {
		return $img_tag;
	}

	// Prefer srcset; fall back to src.
	$webp_srcset = '';
	$sizes_attr  = '';
	if ( preg_match( '/srcset=(["\'])(.*?)\1/is', $img_tag, $m ) ) {
		$webp_srcset = buckleup_webp_srcset( $m[2] );
		if ( preg_match( '/sizes=(["\'])(.*?)\1/is', $img_tag, $sm ) ) {
			$sizes_attr = ' sizes="' . esc_attr( $sm[2] ) . '"';
		}
	}
	if ( '' === $webp_srcset && preg_match( '/src=(["\'])(.*?)\1/is', $img_tag, $sm2 ) ) {
		if ( buckleup_has_webp_sibling( $sm2[2] ) ) {
			$webp_srcset = $sm2[2] . '.webp';
		}
	}
	if ( '' === $webp_srcset ) {
		return $img_tag; // nothing to optimize
	}

	return '<picture><source type="image/webp" srcset="' . esc_attr( $webp_srcset ) . '"' . $sizes_attr . '>' . $img_tag . '</picture>';
}

/**
 * core/post-featured-image — wrap its <img> in a WebP <picture>. The block's
 * wrapper classes stay on the outer markup; we only touch the inner <img>.
 */
add_filter( 'render_block_core/post-featured-image', function ( $block_content ) {
	if ( false === stripos( (string) $block_content, '<img' ) || false !== stripos( (string) $block_content, '<picture' ) ) {
		return $block_content;
	}
	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		static function ( $mm ) {
			return buckleup_wrap_img_webp( $mm[0] );
		},
		$block_content,
		1
	);
}, 10, 1 );

/**
 * the_post_thumbnail() / get_the_post_thumbnail() — wrap in a WebP <picture>.
 * The block editor path goes through render_block_core/post-featured-image above;
 * this covers PHP templates/patterns that call the_post_thumbnail() directly (e.g.
 * the blog index cards in patterns/home-blog.php). Skipped in admin/feeds.
 */
add_filter( 'post_thumbnail_html', function ( $html ) {
	if ( is_admin() || is_feed() || false === stripos( (string) $html, '<img' ) || false !== stripos( (string) $html, '<picture' ) ) {
		return $html;
	}
	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		static function ( $mm ) {
			return buckleup_wrap_img_webp( $mm[0] );
		},
		$html,
		1
	);
}, 10, 1 );

/**
 * Post/page body images (core/image + classic content). Wrap each <img> that has
 * a webp sibling. Runs on the_content only (not admin/feeds).
 */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || is_feed() || false === stripos( (string) $content, '<img' ) ) {
		return $content;
	}
	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		static function ( $mm ) {
			// core/image rarely pre-wraps in <picture>; the wrap fn only adds a
			// <source>, so it's safe/idempotent for marketing content.
			return buckleup_wrap_img_webp( $mm[0] );
		},
		$content
	);
}, 20 );
