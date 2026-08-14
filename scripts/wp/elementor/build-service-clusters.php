<?php
/**
 * Create + build the Hub 1 CLUSTER pages — one per lesson type, as children of the
 * /services/ pillar.
 *
 * Per the content plan (Documents/pillar-cluster-driving-school.pdf §6) these are
 * "the single biggest gap" on the site: the licence classes are the money terms and
 * had no page of their own. Copy lives in services-content.php, shared with the
 * pillar, so the two can never drift.
 *
 * URL shape: /services/<slug>/ — the page is a real WP child of the pillar, so the
 * URL itself expresses the pillar→cluster relationship and breadcrumbs come out right.
 *
 * Linking, per plan §5:
 *   - Cluster → PILLAR twice with a descriptive anchor (intro + closing), so authority
 *     flows back up and Google sees the group.
 *   - Cluster ↔ SIBLINGS, so the set reads as one silo.
 *   - Cluster → other hubs (road-test guides, GLP explainer, Class 4 tool) where it
 *     genuinely helps the reader.
 *   - Pricing is NOT duplicated here. The pillar owns commercial/price intent (§6);
 *     repeating the four package cards on six pages would be textbook duplicate
 *     content. Each cluster links up to /services/#pricing instead.
 *
 * Idempotent: pages are matched by path and updated in place, never duplicated.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-service-clusters.php
 *      (optionally pass slugs to build only some: ... build-service-clusters.php class-4-driving-lessons)
 */
require __DIR__ . '/lib.php';

$SERVICES = require __DIR__ . '/services-content.php';

$pillar = el_post_id( 'services' );
if ( ! $pillar ) {
	echo "ERROR: no /services/ page — build the pillar first.\n";
	return;
}
$pillar_url = get_permalink( $pillar );

/** Optional slug filter from the command line. */
$only = ( isset( $args ) && is_array( $args ) ) ? array_filter( array_map( 'sanitize_title', $args ) ) : array();

/* ---------------------------------------------------------------- helpers -- */

/** Re-key a subtree with ids salted per cluster, so no two pages share element ids. */
function svc_reid( array $el, $salt, &$n ) {
	$n++;
	$el['id'] = substr( md5( 'buckleup-elementor-svc-' . $salt . '-' . $n ), 0, 7 );
	if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
		foreach ( $el['elements'] as $i => $kid ) {
			if ( is_array( $kid ) ) { $el['elements'][ $i ] = svc_reid( $kid, $salt, $n ); }
		}
	}
	return $el;
}

/**
 * Centred page hero: eyebrow pill + H1 + dek.
 *
 * Deliberately defined here rather than reused from build-pages.php — that file
 * builds pages as a side effect of being loaded, so requiring it would rebuild the
 * whole marketing site every time this script runs.
 */
function svc_hero( $eyebrow, $icon, $title_html, $sub ) {
	$kids = array();
	if ( $eyebrow ) {
		$kids[] = el_container(
			array( 'content_width' => 'full', 'width' => el_size( 100, '%' ), 'flex_direction' => 'row', 'flex_justify_content' => 'center' ),
			array( el_pill( $eyebrow, $icon ) )
		);
	}
	$kids[] = el_heading( $title_html, array( 'tag' => 'h1', 'size' => 48, 'weight' => 800, 'align' => 'center', 'color_global' => 'text', 'line_height' => 1.08, 'max_width' => 820 ) );
	if ( $sub ) {
		$kids[] = el_text( $sub, array( 'align' => 'center', 'size' => 18, 'color_global' => 'mutedcol', 'max_width' => 680 ) );
	}
	return el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 72, 'gap' => 18 ),
		array( el_col( $kids, array( 'width' => 100, 'gap_px' => 16, 'align' => 'center' ) ) )
	);
}

/** A prose block (links inherit the theme's .prose styling, which survives CSS purge). */
function svc_prose( $html, array $o = array() ) {
	return el_text( '<div class="prose">' . $html . '</div>', array_merge( array( 'raw' => true, 'size' => 16, 'color_global' => 'mutedcol' ), $o ) );
}

/* ------------------------------------------------------------------- build -- */

$position = 0;
$built   = array();
$skipped = array();

foreach ( $SERVICES as $slug => $s ) {
	if ( $only && ! in_array( $slug, $only, true ) ) { continue; }

	/* --- 1. ensure the page exists (idempotent, matched by full path) --------- */
	// menu_order follows the order in services-content.php — the learner journey
	// (Class 7 → 5 → 4, then the specialist lessons). Without it the nav dropdown
	// falls back to alphabetical and opens on "Class 4", which is the class the
	// fewest visitors want.
	$order    = ++$position;
	$existing = get_page_by_path( 'services/' . $slug );
	$title    = trim( strip_tags( html_entity_decode( $s['card_title'] ) ) );
	if ( $existing ) {
		$id = (int) $existing->ID;
		wp_update_post( array( 'ID' => $id, 'post_title' => $title, 'post_status' => 'publish', 'post_parent' => $pillar, 'menu_order' => $order ) );
	} else {
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_parent'  => $pillar,
			'post_status'  => 'publish',
			'menu_order'   => $order,
			'post_content' => '',
		) );
		if ( is_wp_error( $id ) ) { echo "ERROR creating $slug: " . $id->get_error_message() . "\n"; continue; }
	}

	/* --- 2. sibling links (this silo, minus self) ---------------------------- */
	$sibling_items = '';
	foreach ( $SERVICES as $sib_slug => $sib ) {
		if ( $sib_slug === $slug ) { continue; }
		$sib_page = get_page_by_path( 'services/' . $sib_slug );
		$sib_url  = $sib_page ? get_permalink( $sib_page->ID ) : ( $pillar_url . '#lessons' );
		$sibling_items .= '<li><a href="' . esc_url( $sib_url ) . '">' . $sib['card_title'] . '</a></li>';
	}

	/* --- 3. the page ---------------------------------------------------------- */
	$tree = array();

	// HERO — H1 carries the licence-class head term.
	$tree[] = svc_hero( $s["eyebrow"], $s["icon"], $s["h1"], $s["short"] );

	// INTRO — long-form copy + the first descriptive link UP to the pillar.
	$tree[] = el_section(
		array( 'bg' => '#FFFFFF', 'pad_y' => 56, 'gap' => 20, 'content_width' => 860 ),
		array(
			el_col(
				array(
					svc_prose( '<p>' . $s['long'] . '</p>' ),
					svc_prose( '<p>This is one of our <a href="' . esc_url( $pillar_url ) . '">driving lessons and packages</a> — see the full range if you are not sure which licence class you need.</p>', array( 'size' => 15 ) ),
				),
				array( 'width' => 100, 'gap_px' => 14, 'align' => 'flex-start' )
			),
		)
	);

	// WHAT'S COVERED — the feature list as a scannable H2 section.
	$tree[] = el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 56, 'gap' => 24, 'content_width' => 860 ),
		array(
			el_col(
				array(
					el_heading( 'What these lessons cover', array( 'tag' => 'h2', 'size' => 30, 'weight' => 800, 'color_global' => 'text', 'line_height' => 1.15 ) ),
					el_icon_list( $s['features'], array( 'icon' => 'fas fa-check-circle', 'color_global' => 'secondary', 'size' => 16, 'gap' => 12 ) ),
				),
				array( 'width' => 100, 'gap_px' => 16, 'align' => 'flex-start' )
			),
		)
	);

	// FAQ — unique long-tail copy per cluster, so no page is thin.
	$faq_kids = array( el_heading( 'Common questions', array( 'tag' => 'h2', 'size' => 30, 'weight' => 800, 'color_global' => 'text', 'line_height' => 1.15 ) ) );
	foreach ( $s['faqs'] as $faq ) {
		$faq_kids[] = el_col(
			array(
				el_heading( $faq['q'], array( 'tag' => 'h3', 'size' => 18, 'weight' => 700, 'color_global' => 'text', 'line_height' => 1.3 ) ),
				svc_prose( '<p>' . $faq['a'] . '</p>', array( 'size' => 15 ) ),
			),
			array( 'width' => 100, 'bg' => '#FFFFFF', 'pad' => 22, 'radius' => 16, 'border' => '#CBD5E1', 'gap_px' => 8, 'align' => 'flex-start' )
		);
	}
	$tree[] = el_section( array( 'bg' => '#FFFFFF', 'pad_y' => 56, 'gap' => 20, 'content_width' => 860 ), array( el_col( $faq_kids, array( 'width' => 100, 'gap_px' => 16, 'align' => 'flex-start' ) ) ) );

	// SIBLINGS + cross-hub — keeps the silo tight (plan §5).
	$tree[] = el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 56, 'gap' => 24, 'content_width' => 860 ),
		array(
			el_col(
				array(
					el_heading( 'Other lessons we offer', array( 'tag' => 'h2', 'size' => 30, 'weight' => 800, 'color_global' => 'text', 'line_height' => 1.15 ) ),
					svc_prose( '<ul>' . $sibling_items . '</ul>', array( 'size' => 15 ) ),
					svc_prose(
						'<p>Also useful: our <a href="' . esc_url( home_url( '/bc-graduated-licensing-program-explained-7l-7n-class-5/' ) ) . '">guide to BC\'s Graduated Licensing Program</a>, the '
						. '<a href="' . esc_url( home_url( '/icbc-class-4-knowledge-test/' ) ) . '">free ICBC Class 4 knowledge practice test</a>, and '
						. '<a href="' . esc_url( home_url( '/locations/' ) ) . '">where we teach across Metro Vancouver</a>.</p>',
						array( 'size' => 15 )
					),
				),
				array( 'width' => 100, 'gap_px' => 14, 'align' => 'flex-start' )
			),
		)
	);

	// CTA — second link up to the pillar, this time to its pricing anchor.
	$tree[] = el_section(
		array( 'bg' => '#FFFFFF', 'pad_y' => 64, 'gap' => 16, 'content_width' => 860 ),
		array(
			el_col(
				array(
					el_heading( 'Ready to book a lesson?', array( 'tag' => 'h2', 'size' => 28, 'weight' => 800, 'align' => 'center', 'color_global' => 'text' ) ),
					el_text( 'Tell us where you are starting from and we will map out the fastest sensible route to your licence.', array( 'align' => 'center', 'size' => 16, 'color_global' => 'mutedcol', 'max_width' => 560 ) ),
					el_container(
						array( 'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row', 'flex_gap' => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12' ), '_flex_grow' => 0, 'padding' => el_box( 8, 0, 0, 0 ) ),
						array(
							el_button( 'See packages &amp; pricing', array( 'url' => $pillar_url . '#pricing', 'size' => 'lg', 'icon' => 'fas fa-arrow-right', 'bg_global' => 'primary' ) ),
							el_button( 'Contact us', array( 'url' => home_url( '/contact/' ), 'size' => 'lg', 'variant' => 'outline' ) ),
						)
					),
				),
				array( 'width' => 100, 'gap_px' => 14, 'align' => 'center', 'bg' => '#FFFFFF', 'pad' => 32, 'radius' => 24, 'border' => '#CBD5E1', 'shadow' => true )
			),
		)
	);

	// Salt element ids per page.
	$n = 0;
	foreach ( $tree as $i => $node ) { $tree[ $i ] = svc_reid( $node, $slug, $n ); }

	el_save_page( $id, $tree );

	/* --- 4. SEO meta (both the content model and Rank Math) ------------------- */
	$seo_title = html_entity_decode( $s['seo_title'] );
	$seo_desc  = html_entity_decode( $s['seo_desc'] );
	update_post_meta( $id, 'bu_seo_title', $seo_title );
	update_post_meta( $id, 'bu_seo_description', $seo_desc );
	update_post_meta( $id, 'rank_math_title', $seo_title );
	update_post_meta( $id, 'rank_math_description', $seo_desc );

	$built[] = $slug . '(' . $id . ')';
}

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
flush_rewrite_rules( false );

echo 'Built ' . count( $built ) . " cluster page(s):\n";
foreach ( $built as $b ) { echo "  - $b\n"; }
echo "Pillar: " . $pillar_url . "\n";
echo "NOTE: re-run build-pages.php services so the pillar cards link to these pages\n";
echo "      instead of their transitional fallbacks.\n";
