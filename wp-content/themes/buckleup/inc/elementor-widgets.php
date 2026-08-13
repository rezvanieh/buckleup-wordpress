<?php
/**
 * Custom Elementor widgets shipped by the theme.
 *
 * The marketing page bodies are authored in Elementor (see inc/elementor.php for
 * the interop layer), but a few showpieces are bespoke Tailwind markup that free
 * Elementor can't reproduce — the home hero above all: gradient H1 span, glass
 * trust pills, ambient pulse-glow blobs, the LCP <picture>/WebP treatment, the
 * lg-only 3D mouse-tilt card with its floating rating card, instructor chip and
 * Toyota badge, plus the data-reveal scroll animations the theme JS drives.
 *
 * Rather than approximating that in Elementor containers (which would regress the
 * design) we wrap the REAL renderer in a native Elementor widget: the widget's
 * render() calls buckleup_hero_markup() (inc/site.php) — the same function the
 * patterns/home-hero.php block pattern calls — passing admin-edited values. One
 * renderer, one code path: an un-edited widget is byte-identical to the previously
 * hard-coded hero, and the design can't drift from the editable copy.
 *
 * This file only wires things up:
 *   - registers the "BuckleUp" widget category (so our widgets group together),
 *   - registers the widget class on `elementor/widgets/register`,
 *   - exposes buckleup_hero_widget_bg_url(), which lets the front page's LCP
 *     preload (functions.php) point at whatever background the widget will
 *     actually render instead of assuming the stock brand asset.
 *
 * Every hook here is a no-op when Elementor is inactive.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The hero widget's Elementor `widgetType`. Shared by the widget class, the
 * `_elementor_data` scan below, and scripts/wp/elementor/build-hero.php.
 */
if ( ! defined( 'BUCKLEUP_HERO_WIDGET' ) ) {
	define( 'BUCKLEUP_HERO_WIDGET', 'buckleup-hero' );
}

/**
 * Register the "BuckleUp" panel category so theme widgets aren't scattered through
 * Elementor's Basic/General groups.
 */
add_action( 'elementor/elements/categories_registered', function ( $manager ) {
	if ( ! is_object( $manager ) || ! method_exists( $manager, 'add_category' ) ) {
		return;
	}
	$manager->add_category( 'buckleup', array(
		'title' => __( 'BuckleUp', 'buckleup' ),
		'icon'  => 'eicon-star',
	) );
} );

/**
 * Register the theme's widget classes.
 *
 * Elementor 3.5+ exposes Widgets_Manager::register(); older builds only had
 * register_widget_type(). We support both so the theme doesn't hard-fail on an
 * older Elementor (same defensive style as inc/elementor.php).
 */
add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
	require_once __DIR__ . '/elementor-widgets/class-buckleup-hero-widget.php';

	$widget = new Buckleup_Hero_Widget();
	if ( method_exists( $widgets_manager, 'register' ) ) {
		$widgets_manager->register( $widget );
	} elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
		$widgets_manager->register_widget_type( $widget );
	}
} );

/**
 * Settings of the first BuckleUp Hero widget on a post, straight from
 * `_elementor_data` — WITHOUT booting an Elementor document.
 *
 * Used from wp_head (LCP preload), which runs long before Elementor renders, so it
 * has to be cheap: a substring test on the raw meta short-circuits the (already
 * memoized) JSON decode on every page that has no hero widget.
 *
 * Returns the widget's SAVED settings only — Elementor merges control defaults at
 * render time, and those aren't in the meta until the page is saved in the editor.
 * Callers must therefore treat an empty result as "use the theme default".
 *
 * @param int $post_id Post to inspect.
 * @return array Saved widget settings, or [] when absent.
 */
function buckleup_hero_widget_settings( int $post_id ): array {
	static $cache = array();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$found = array();
	$raw   = $post_id > 0 ? get_post_meta( $post_id, '_elementor_data', true ) : '';
	if ( is_string( $raw ) && '' !== $raw && false !== strpos( $raw, BUCKLEUP_HERO_WIDGET ) ) {
		$elements = json_decode( $raw, true );
		if ( is_array( $elements ) ) {
			$found = buckleup_find_hero_widget_settings( $elements );
		}
	}

	$cache[ $post_id ] = $found;
	return $found;
}

/**
 * Depth-first search of an Elementor element tree for the hero widget's settings.
 *
 * @param array $elements Elementor element array (containers/widgets).
 * @return array Settings of the first hero widget found, else [].
 */
function buckleup_find_hero_widget_settings( array $elements ): array {
	foreach ( $elements as $element ) {
		if ( ! is_array( $element ) ) {
			continue;
		}
		if ( isset( $element['widgetType'] ) && BUCKLEUP_HERO_WIDGET === $element['widgetType'] ) {
			return isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		}
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$hit = buckleup_find_hero_widget_settings( $element['elements'] );
			if ( ! empty( $hit ) ) {
				return $hit;
			}
		}
	}
	return array();
}

/**
 * The background image URL the hero widget on $post_id will render, or '' when
 * there's no widget / no explicit image (callers then fall back to the migrated
 * image2.png brand asset, i.e. the pre-Elementor behaviour).
 *
 * Keeps the front page's `<link rel=preload as=image>` in sync with an admin-swapped
 * hero background — otherwise we'd preload a stale image and lose the LCP win.
 *
 * @param int $post_id Post to inspect.
 * @return string Absolute URL, or ''.
 */
function buckleup_hero_widget_bg_url( int $post_id ): string {
	$settings = buckleup_hero_widget_settings( $post_id );
	if ( empty( $settings['background_image'] ) || ! is_array( $settings['background_image'] ) ) {
		return '';
	}
	$image = $settings['background_image'];
	if ( ! empty( $image['url'] ) ) {
		return (string) $image['url'];
	}
	if ( ! empty( $image['id'] ) ) {
		return (string) wp_get_attachment_url( (int) $image['id'] );
	}
	return '';
}
