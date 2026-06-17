<?php
/**
 * Front-end performance: drop dead-weight assets to cut bundle size / improve CWV.
 *
 * Two safe, high-impact wins verified against the live build:
 *
 * 1. **ElementsKit frontend assets (~550 KB).** ElementsKit hooks Elementor's
 *    frontend-enqueue chain and loads its full widget stylesheet (`ekit-widget-styles`
 *    ~459 KB), responsive CSS, the ekiticons pack, and its widget JS on EVERY view.
 *    But the ONLY ElementsKit widget in the whole site is `ekit-nav-menu` inside the
 *    Elementor "Site Header" template (post 163), which is **retained-but-unused** —
 *    the real Tailwind theme header (`buckleup/section name="site-header"`) renders
 *    instead. No rendered page uses an ElementsKit widget, so these assets are pure
 *    dead weight. We dequeue them on the front end. (If a future page genuinely adds
 *    an ElementsKit widget, gate this with a per-page check or remove it.)
 *
 * 2. **jquery-migrate.** A legacy shim for pre-3.0 jQuery code. The theme JS is
 *    vanilla and our code targets modern jQuery, so it's unnecessary render-blocking
 *    weight. We remove it as a jQuery dependency (jQuery itself stays — Elementor's
 *    frontend needs it).
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Handles for ElementsKit's front-end bulk assets (loaded via Elementor's enqueue
 * chain). Kept narrow: only the heavy widget/responsive/icon assets + ElementsKit's
 * own widget JS — NOT Elementor's core frontend (which the footer template needs).
 */
function buckleup_elementskit_dead_handles(): array {
	return array(
		'styles'  => array( 'ekit-widget-styles', 'ekit-responsive', 'elementor-icons-ekiticons' ),
		'scripts' => array( 'ekit-widget-scripts', 'elementskit-elementor' ),
	);
}

/**
 * Dequeue ElementsKit's dead-weight assets. Hooked late (and again at print time)
 * so it runs AFTER ElementsKit enqueues on Elementor's `after_enqueue_*` actions.
 */
function buckleup_strip_elementskit_assets(): void {
	if ( is_admin() ) {
		return;
	}
	$h = buckleup_elementskit_dead_handles();
	foreach ( $h['styles'] as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}
	foreach ( $h['scripts'] as $handle ) {
		wp_dequeue_script( $handle );
		wp_deregister_script( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'buckleup_strip_elementskit_assets', 100 );
add_action( 'wp_print_styles', 'buckleup_strip_elementskit_assets', 100 );
add_action( 'wp_print_scripts', 'buckleup_strip_elementskit_assets', 100 );
// ElementsKit enqueues the ekiticons pack on Elementor's own enqueue chain (later
// than the hooks above), so dequeue there too. The page's FA icons render as inline
// SVG (Elementor's e_font_icon_svg experiment), so no icon font is needed.
add_action( 'elementor/frontend/after_enqueue_styles', 'buckleup_strip_elementskit_assets', 9999 );
add_action( 'elementor/frontend/after_enqueue_scripts', 'buckleup_strip_elementskit_assets', 9999 );

/**
 * Drop jquery-migrate (keep jQuery). Removing it from jQuery's dependency list
 * prevents it from loading at all.
 */
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
		return;
	}
	$scripts->registered['jquery']->deps = array_diff(
		$scripts->registered['jquery']->deps,
		array( 'jquery-migrate' )
	);
} );
