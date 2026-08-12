<?php
/**
 * Inject the admin-editable "BuckleUp Hero" widget at the top of Home (post 38).
 *
 * The hero used to be hard-coded in the block template
 * (`themes/buckleup/templates/front-page.html` → `wp:buckleup/section name=home-hero`),
 * which put it ABOVE `wp:post-content` and therefore outside anything the client
 * could edit. It is now a native Elementor widget
 * (`themes/buckleup/inc/elementor-widgets/class-buckleup-hero-widget.php`) whose
 * render() calls the very same renderer the pattern used (buckleup_hero_markup()),
 * so an un-edited widget is byte-identical to the old markup.
 *
 * This script only MOVES it: it prepends the widget to page 38's existing
 * `_elementor_data` (built by build-home.php) and re-saves. It deliberately writes
 * NO widget settings — every control defaults to today's live copy, so the hero
 * renders exactly as before until someone edits it in Elementor.
 *
 * ⚠ PAIRED CHANGE — the hero must render exactly once. This script and the
 * `front-page.html` edit that removes the `wp:buckleup/section {"name":"home-hero"}`
 * line go together:
 *   - template edit deployed but script not run  → NO hero on the home page.
 *   - script run but template edit not deployed  → hero renders TWICE.
 * Deploy the theme and run this in the same maintenance step. (After the edit,
 * front-page.html is identical to page-elementor.html — Home is now a plain
 * Elementor body, hero included.)
 *
 * Idempotent: re-running strips any previously-injected hero container first, so it
 * can be re-run after build-home.php (which rewrites the whole tree and drops it).
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-hero.php
 */
require __DIR__ . '/lib.php';

$PAGE_ID = el_post_id( 'home' );
if ( ! $PAGE_ID ) {
	echo "ERROR: no page with slug 'home'. Run provision first.\n";
	return;
}
$WIDGET  = defined( 'BUCKLEUP_HERO_WIDGET' ) ? BUCKLEUP_HERO_WIDGET : 'buckleup-hero';

if ( ! defined( 'BUCKLEUP_HERO_WIDGET' ) ) {
	echo "WARNING: BUCKLEUP_HERO_WIDGET is undefined — is the buckleup theme active?\n";
}

/**
 * Re-key an element subtree with ids salted for THIS builder.
 *
 * lib.php's el_id() is a per-run counter, so build-hero.php would otherwise mint
 * the same first ids build-home.php already gave to elements living in the same
 * document. Salting keeps them unique while staying deterministic across re-runs
 * (clean diffs, stable per-element CSS selectors).
 *
 * @param array $element Element array (containers/widgets).
 * @param int   $n       Running counter, by reference.
 * @return array Element with rewritten ids.
 */
function hero_reid( array $element, &$n ) {
	$n++;
	$element['id'] = substr( md5( 'buckleup-elementor-hero-' . $n ), 0, 7 );
	if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
		foreach ( $element['elements'] as $i => $child ) {
			if ( is_array( $child ) ) {
				$element['elements'][ $i ] = hero_reid( $child, $n );
			}
		}
	}
	return $element;
}

/**
 * Does this subtree contain the hero widget? (Used to strip a previous injection.)
 *
 * @param array  $element Element array.
 * @param string $widget  widgetType to look for.
 * @return bool
 */
function hero_subtree_has_widget( array $element, $widget ) {
	if ( isset( $element['widgetType'] ) && $widget === $element['widgetType'] ) {
		return true;
	}
	if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
		foreach ( $element['elements'] as $child ) {
			if ( is_array( $child ) && hero_subtree_has_widget( $child, $widget ) ) {
				return true;
			}
		}
	}
	return false;
}

/* ------------------------------------------------------- LOAD THE HOME BODY -- */
$raw      = get_post_meta( $PAGE_ID, '_elementor_data', true );
$elements = ( is_string( $raw ) && '' !== $raw ) ? json_decode( $raw, true ) : array();
if ( ! is_array( $elements ) ) {
	echo "ERROR: page $PAGE_ID has no readable _elementor_data. Run build-home.php first.\n";
	return;
}

$before = count( $elements );

// Drop a previously-injected hero so re-runs never stack two of them.
$elements = array_values( array_filter( $elements, function ( $element ) use ( $WIDGET ) {
	return ! ( is_array( $element ) && hero_subtree_has_widget( $element, $WIDGET ) );
} ) );
$removed = $before - count( $elements );

/* ------------------------------------------------------------------- HERO -- */
// Full-width, zero-padding wrapper — the same idiom build-home.php uses to host the
// other full-bleed theme sections ([buckleup_section name="home-graduates"] etc.).
// The hero <section> is min-h-screen and paints its own background, so the container
// must not add padding or a boxed max-width.
$hero = el_container(
	array(
		'content_width' => 'full',
		'padding'       => el_box( 0, 0, 0, 0 ),
	),
	array( el_widget( $WIDGET, array() ) ) // no settings → the widget's own defaults
);

$n    = 0;
$hero = hero_reid( $hero, $n );

array_unshift( $elements, $hero );

el_save_page( $PAGE_ID, $elements );

echo 'Hero widget (' . $WIDGET . ') injected as element 1 of ' . count( $elements )
	. ( $removed ? " (replaced $removed existing hero container(s))" : '' ) . ".\n";
echo "Reminder: templates/front-page.html must NOT also render wp:buckleup/section name=home-hero.\n";
echo 'URL: ' . get_permalink( $PAGE_ID ) . "\n";
