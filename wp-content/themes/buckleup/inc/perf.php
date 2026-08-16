<?php
/**
 * Front-end performance: only ship the plugin assets a page actually uses.
 *
 * THE PROBLEM (measured, not assumed)
 * -----------------------------------
 * Five plugins enqueue their front-end bundles on every request regardless of
 * whether the page uses them: Elementor, ElementsKit, Metform, Gutenkit and
 * Complianz. A page like /services/, which is built from nothing but core
 * Elementor heading/text/button/icon-list widgets, was still loading:
 *
 *     ekit common.css .............. 201,699 bytes   (ElementsKit widget library)
 *     Font Awesome all.css .......... 73,625         (no FA icon on the page)
 *     jquery-ui core.js ............. 49,888         (pulled in by ElementsKit)
 *     magnific-popup.js ............. 20,849         (ElementsKit lightbox)
 *     metform text-editor.css ....... 22,474         (no form anywhere on the site)
 *     FA v4-shims.js ................ 17,459
 *     ekit nav-menu / header-* ...... ~10,000        (CSS + JS for widgets not used)
 *     cute-alert (Metform) .......... ~7,000
 *     gutenkit frontend-common.css ... 1,219         (no Gutenkit block anywhere)
 *
 * That is roughly 400 KB of CSS and JS, most of it render-blocking, on a page
 * that needs none of it.
 *
 * WHAT IS ACTUALLY USED (scan of every _elementor_data document on the site)
 * -------------------------------------------------------------------------
 *   - Metform, Gutenkit, EmailKit, Popup Builder: ZERO widgets or blocks. Nothing
 *     on the site uses them, so their front-end assets are dropped everywhere.
 *   - ElementsKit: exactly one widget type, `elementskit-accordion`, on 13 blog
 *     posts, plus `ekit-nav-menu` inside the retained-but-unused "Site Header"
 *     library template (the Tailwind theme header renders instead). So ElementsKit
 *     is needed on those 13 posts and nowhere else.
 *   - Font Awesome: used by icon widgets on some pages, absent on others.
 *
 * So the rule is per-page and derived from the page's own Elementor data, not a
 * hardcoded list of URLs that would rot the moment someone edits a page.
 *
 * WHY NOT JUST DEQUEUE EVERYTHING: an earlier version of this file dequeued
 * ElementsKit outright on the stated basis that "no rendered page uses an
 * ElementsKit widget". That was true when written and is not true now: 13 blog
 * posts gained accordions. Blanket dequeuing would have broken every FAQ on the
 * blog. Hence the per-page check, which self-heals when content changes.
 *
 * Complianz is deliberately left alone: it is the cookie-consent banner, and
 * consent tooling is not something to strip for page weight.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Elementor documents that will render on this request: the queried post, plus
 * any library template the theme embeds site-wide (the footer, via
 * parts/footer.html). Anything the page renders can enqueue assets, so all of it
 * has to be considered before deciding an asset is unused.
 *
 * @return string[] Raw _elementor_data strings.
 */
function buckleup_rendered_elementor_data(): array {
	static $cache = null;
	if ( null !== $cache ) { return $cache; }

	$ids = array();

	$post_id = get_queried_object_id();
	if ( $post_id ) { $ids[] = (int) $post_id; }

	/*
	 * The footer used to be an Elementor library template embedded on every page,
	 * and it had to be included here because its widgets could pull in assets. It
	 * is now the theme's own pattern (parts/footer.html), so the queried post is
	 * the only Elementor document that renders. If the footer is ever switched
	 * back, add its template id here again or these checks will under-report.
	 */

	$out = array();
	foreach ( array_unique( $ids ) as $id ) {
		$d = get_post_meta( $id, '_elementor_data', true );
		if ( is_string( $d ) && '' !== $d ) { $out[] = $d; }
	}

	$cache = $out;
	return $cache;
}

/**
 * Does anything rendering on this request use an ElementsKit widget?
 *
 * Matches the widgetType key rather than a loose substring: ElementsKit widget
 * types are "elementskit-*" and "ekit-*", and a loose search would also match the
 * plugin's own CSS class names if they ever appeared in content.
 */
function buckleup_page_uses_elementskit(): bool {
	static $uses = null;
	if ( null !== $uses ) { return $uses; }

	$uses = false;
	foreach ( buckleup_rendered_elementor_data() as $data ) {
		if ( preg_match( '/"widgetType"\s*:\s*"(elementskit|ekit)[-_]/i', $data ) ) {
			$uses = true;
			break;
		}
	}
	return $uses;
}

/**
 * Is Elementor rendering icons as inline SVG rather than as an icon font?
 *
 * Elementor's `e_font_icon_svg` experiment swaps every icon control's output from
 * <i class="fas fa-check"> to an inline <svg>. When it is on, the Font Awesome
 * stylesheet and its v4 shim are dead weight, because nothing on the page uses
 * the font. This site has the experiment ACTIVE, which is why a page like
 * /services/ renders 26 inline SVG icons and zero <i> icon elements.
 */
function buckleup_elementor_icons_are_svg(): bool {
	if ( ! class_exists( '\Elementor\Plugin' ) ) { return false; }
	$p = \Elementor\Plugin::$instance;
	if ( ! isset( $p->experiments ) || ! method_exists( $p->experiments, 'is_feature_active' ) ) { return false; }
	return (bool) $p->experiments->is_feature_active( 'e_font_icon_svg' );
}

/**
 * Does anything rendering on this request need the Font Awesome icon FONT?
 *
 * Two different sources of icons have to be told apart:
 *
 *   - Elementor icon CONTROLS, stored as {"value":"fas fa-check","library":"fa-solid"}.
 *     With the SVG experiment on these become inline <svg> and need no font.
 *   - Icon markup hand-written into a text editor widget as <i class="fas fa-x">.
 *     That is literal HTML, so it always needs the font.
 *
 * On this site only the five location pages contain the hand-written kind, which
 * is why the other ~49 pages can drop 132 KB of Font Awesome.
 */
function buckleup_page_uses_font_awesome(): bool {
	static $uses = null;
	if ( null !== $uses ) { return $uses; }

	$svg  = buckleup_elementor_icons_are_svg();
	$uses = false;

	foreach ( buckleup_rendered_elementor_data() as $data ) {
		// Hand-written icon markup always needs the font. The data is JSON, so the
		// quotes inside the embedded HTML arrive backslash-escaped.
		if ( preg_match( '/<(?:i|span)[^>]{0,200}?fa[bsrl]?\s+fa-/i', $data ) ) {
			$uses = true;
			break;
		}
		// Icon controls only need it when they are NOT being rendered as SVG.
		if ( ! $svg && preg_match( '/"library"\s*:\s*"fa-/i', $data ) ) {
			$uses = true;
			break;
		}
	}

	return $uses;
}

/**
 * Dequeue every queued style/script whose source lives under one of the given
 * plugin directories.
 *
 * Matching on the asset's SOURCE PATH rather than its handle is deliberate:
 * handles change between plugin versions (ElementsKit renamed its bulk handles
 * between 3.x and 4.x, which is how the previous version of this file silently
 * stopped covering common.css), whereas the plugin directory does not.
 *
 * @param string[] $dirs Plugin directory slugs.
 * @return int Number of assets dropped.
 */
function buckleup_dequeue_from_plugins( array $dirs ): int {
	$dropped = 0;

	foreach ( array( 'styles', 'scripts' ) as $kind ) {
		$reg = 'styles' === $kind ? wp_styles() : wp_scripts();
		if ( ! $reg || empty( $reg->queue ) ) { continue; }

		foreach ( $reg->queue as $handle ) {
			$src = isset( $reg->registered[ $handle ] ) ? (string) $reg->registered[ $handle ]->src : '';
			if ( '' === $src ) { continue; }
			foreach ( $dirs as $dir ) {
				if ( false === strpos( $src, "/plugins/$dir/" ) ) { continue; }
				if ( 'styles' === $kind ) { wp_dequeue_style( $handle ); } else { wp_dequeue_script( $handle ); }
				$dropped++;
				break;
			}
		}
	}

	return $dropped;
}

/**
 * On a page that legitimately uses ElementsKit, drop the per-widget assets for
 * the ElementsKit widgets it does NOT use.
 *
 * ElementsKit 4.x splits its CSS/JS per widget but still enqueues the header and
 * nav-menu set unconditionally alongside whatever the page actually needs.
 * Matched on the asset filename, since those files are named after their widget.
 */
function buckleup_dequeue_elementskit_unused_widgets(): void {
	$unused = array( 'nav-menu', 'header-search', 'header-offcanvas', 'header-info', 'magnific-popup' );

	foreach ( array( 'styles', 'scripts' ) as $kind ) {
		$reg = 'styles' === $kind ? wp_styles() : wp_scripts();
		if ( ! $reg || empty( $reg->queue ) ) { continue; }

		foreach ( $reg->queue as $handle ) {
			$src = isset( $reg->registered[ $handle ] ) ? (string) $reg->registered[ $handle ]->src : '';
			if ( '' === $src || false === strpos( $src, '/plugins/elementskit-lite/' ) ) { continue; }
			foreach ( $unused as $name ) {
				if ( false === strpos( $src, $name ) ) { continue; }
				if ( 'styles' === $kind ) { wp_dequeue_style( $handle ); } else { wp_dequeue_script( $handle ); }
				break;
			}
		}
	}
}

/**
 * The asset diet. Runs late and on several hooks because ElementsKit and Metform
 * enqueue from Elementor's own enqueue chain, which fires after wp_enqueue_scripts.
 */
function buckleup_asset_diet(): void {
	if ( is_admin() ) { return; }

	// Never touch the Elementor editor or its preview iframe: the editor genuinely
	// needs every widget's assets in order to render and edit them.
	if ( class_exists( '\Elementor\Plugin' ) ) {
		$p = \Elementor\Plugin::$instance;
		if ( isset( $p->preview ) && method_exists( $p->preview, 'is_preview_mode' ) && $p->preview->is_preview_mode() ) { return; }
		if ( isset( $p->editor ) && method_exists( $p->editor, 'is_edit_mode' ) && $p->editor->is_edit_mode() ) { return; }
	}

	// Unused site-wide: nothing on the site has a Metform form, a Gutenkit block,
	// an EmailKit template or a Popup Builder popup.
	$always = array( 'metform', 'emailkit', 'gutenkit-blocks-addon', 'popup-builder-block' );
	buckleup_dequeue_from_plugins( $always );

	// ElementsKit only where one of its widgets renders. This keeps the accordion
	// working on the 13 blog posts that use it, and drops ~280 KB everywhere else.
	if ( ! buckleup_page_uses_elementskit() ) {
		buckleup_dequeue_from_plugins( array( 'elementskit-lite' ) );
	} else {
		/*
		 * ElementsKit is needed here, but it loads assets for its whole widget set,
		 * not just the widget in use. The only ElementsKit widget on this site is
		 * the accordion, so the nav-menu, header-search, header-offcanvas and
		 * header-info assets — plus the magnific-popup lightbox library — are dead
		 * weight even on the pages that do need ElementsKit.
		 *
		 * common.css is deliberately KEPT: the accordion's own stylesheet builds on
		 * the shared classes defined there, so dropping it breaks the accordion's
		 * appearance rather than merely slimming it.
		 */
		buckleup_dequeue_elementskit_unused_widgets();
	}

	/*
	 * jquery-ui core.js (~49 KB) is deliberately NOT dequeued. It looks like
	 * ElementsKit dead weight but is not: elementor-frontend declares a dependency
	 * on jquery-ui-position, which depends on jquery-ui-core. Dequeuing it leaves
	 * Elementor's frontend without a dependency it expects. It stays for as long as
	 * Elementor renders anything on the page, which is every page while the footer
	 * is an Elementor template.
	 */

	/*
	 * Elementor's own base CSS on pages with NO Elementor content.
	 *
	 * Elementor enqueues frontend.css and the global "kit" stylesheet on every
	 * request, whether or not the page renders anything it built. That was
	 * unavoidable while the footer was an Elementor template — every page was an
	 * Elementor page. Now that the footer is the theme's pattern, roughly 25 pages
	 * (all the Class 4 practice-test pages, the blog index, and the classic blog
	 * posts) contain no Elementor content at all and can drop it: 64 KB of CSS
	 * that styles nothing on them.
	 *
	 * Keyed off the page's own data, so the moment someone builds one of these
	 * pages in Elementor the stylesheet comes back on its own.
	 */
	if ( ! buckleup_rendered_elementor_data() ) {
		foreach ( array( 'elementor-frontend', 'elementor-post-165', 'buckleup-elementor-fixes' ) as $h ) {
			wp_dequeue_style( $h );
		}
		// The per-kit stylesheet handle carries the kit's post id, which differs
		// between installs, so match it by source path rather than by name.
		$styles = wp_styles();
		if ( $styles && ! empty( $styles->queue ) ) {
			foreach ( $styles->queue as $handle ) {
				$src = isset( $styles->registered[ $handle ] ) ? (string) $styles->registered[ $handle ]->src : '';
				if ( '' !== $src && false !== strpos( $src, '/uploads/elementor/css/post-' ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
	}

	// Font Awesome only where an icon actually uses it.
	if ( ! buckleup_page_uses_font_awesome() ) {
		foreach ( array( 'font-awesome-5-all', 'font-awesome-4-shim', 'elementor-icons-fa-solid', 'elementor-icons-fa-regular', 'elementor-icons-fa-brands' ) as $h ) {
			wp_dequeue_style( $h );
		}
		wp_dequeue_script( 'font-awesome-4-shim' );
	}
}

add_action( 'wp_enqueue_scripts', 'buckleup_asset_diet', 100 );
add_action( 'wp_print_styles', 'buckleup_asset_diet', 100 );
add_action( 'wp_print_scripts', 'buckleup_asset_diet', 100 );
// ElementsKit and Metform enqueue on Elementor's chain, later than the hooks above.
add_action( 'elementor/frontend/after_enqueue_styles', 'buckleup_asset_diet', 9999 );
add_action( 'elementor/frontend/after_enqueue_scripts', 'buckleup_asset_diet', 9999 );

/**
 * Preload the hero image, which is the front page's LCP element.
 *
 * PageSpeed's LCP breakdown on mobile (16 Aug 2026) put 660ms of the 3.2s into
 * "resource load delay", against only 60ms of actual load duration. The image is
 * not slow; it is found late. It sits deep inside an Elementor document, inside
 * a <picture>, so the preload scanner reaches it well after the stylesheets in
 * <head> have been requested.
 *
 * A preload hint moves that discovery to the first bytes of the document. The
 * URL must match EXACTLY what <picture> ends up choosing or the browser fetches
 * the image twice, so this resolves it the same way buckleup_hero_markup() does:
 * the widget's own background image when one is set, otherwise the migrated
 * brand asset, then the WebP sibling that the <source> element serves.
 */
add_action( 'wp_head', function () {
	if ( ! is_front_page() || is_admin() ) { return; }
	if ( ! function_exists( 'buckleup_asset_url' ) || ! function_exists( 'buckleup_webp_sibling_url' ) ) { return; }

	$bg = '';

	// Prefer whatever the hero widget is actually set to render.
	$post_id = get_queried_object_id();
	if ( $post_id ) {
		global $wpdb;
		mysqli_report( MYSQLI_REPORT_OFF );
		$res = mysqli_query( $wpdb->dbh, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=" . (int) $post_id . " AND meta_key='_elementor_data' LIMIT 1" );
		$row = $res ? mysqli_fetch_row( $res ) : null;
		if ( $row && '' !== $row[0] && preg_match( '#"widgetType":"buckleup-hero".*?"background_image":\{"url":"(.*?)"#', $row[0], $m ) ) {
			$bg = stripslashes( $m[1] );
		}
	}

	// An unset control falls back to the brand asset, exactly as the hero does.
	if ( '' === $bg ) { $bg = buckleup_asset_url( 'image2.png' ); }
	if ( '' === $bg ) { return; }

	$webp = buckleup_webp_sibling_url( $bg );
	$href = $webp ? $webp : $bg;
	$type = $webp ? ' type="image/webp"' : '';

	printf(
		'<link rel="preload" as="image" href="%s"%s fetchpriority="high">' . "\n",
		esc_url( $href ),
		$type // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed literal.
	);
}, 2 );

/**
 * Move jQuery to the footer.
 *
 * After the diet above, jQuery (285 KB) was the ONLY render-blocking script left
 * in <head> on a marketing page — everything else, including all of Elementor's
 * frontend JS, already prints in the footer. Nothing in <head> uses it: the only
 * inline scripts there are the theme's no-transitions guard, the JSON-LD block,
 * an ElementsKit REST-url variable and Elementor's breakpoints object, none of
 * which touch jQuery.
 *
 * Verified before changing: no enqueued script in the head group depends on
 * jquery, so moving it cannot strand a dependency.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) { return; }
	$scripts = wp_scripts();
	if ( ! $scripts ) { return; }
	foreach ( array( 'jquery', 'jquery-core', 'jquery-migrate' ) as $handle ) {
		if ( isset( $scripts->registered[ $handle ] ) ) {
			// group 1 = print in the footer.
			$scripts->add_data( $handle, 'group', 1 );
		}
	}
}, 5 );

/**
 * Drop jquery-migrate (keep jQuery). A legacy shim for pre-3.0 jQuery code; the
 * theme's own JS is vanilla and Elementor targets modern jQuery. Removing it from
 * jQuery's dependency list prevents it loading at all.
 */
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( is_admin() || empty( $scripts->registered['jquery'] ) ) { return; }
	$scripts->registered['jquery']->deps = array_diff(
		$scripts->registered['jquery']->deps,
		array( 'jquery-migrate' )
	);
} );
