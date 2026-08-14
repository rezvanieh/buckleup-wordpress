<?php
/**
 * Build /locations/ as the Hub 4 PILLAR — a real, linked service-area index.
 *
 * Per the content plan (Documents/pillar-cluster-driving-school.pdf, Hub 4 + §8):
 * "/locations/ — Optimize: make it a real index that links to and briefly describes
 * each area." It was literally empty: 2 bytes of Elementor data, no headings, no
 * metadata. The only city links on it came from the nav and footer chrome, so the
 * hub had no pillar at all and the city pages had nothing pointing down at them
 * from a page of their own.
 *
 * SEO decisions, and why:
 *
 *   - The H1 owns the HUB term ("service areas" / "driving lessons across Metro
 *     Vancouver"). It deliberately does NOT target "driving school <city>" — §6 of
 *     the plan names /locations/coquitlam/ the single owner of Coquitlam commercial
 *     intent, and that term already fell from ~position 8 to 40 through exactly this
 *     kind of dilution. The pillar sends authority down; it does not compete.
 *
 *   - Every city link uses the anchor the plan asks for: "Driving lessons in
 *     <City>" (§5, and the highest-priority link list).
 *
 *   - Coquitlam is listed first and given the most room. It is the flagship money
 *     page (1,691 impressions at position 52) and the plan's top recovery priority.
 *
 *   - Tri-Cities is presented as an UMBRELLA over the three cities rather than a
 *     fifth peer. §6 flags it as overlapping Coquitlam/PoCo/Port Moody; framing it
 *     as the "all three" option is what stops the index from teaching Google that
 *     they are competing pages.
 *
 *   - Copy here is written fresh, NOT reused from the city pages. Repeating their
 *     intros on the index would be duplicate content against the very pages this
 *     is meant to promote.
 *
 * The five CLUSTER pages are not touched: they are client-edited and captured in
 * snapshots/locations/. This script only ever writes page 'locations'.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-locations-pillar.php
 */
require __DIR__ . '/lib.php';

$PAGE = el_post_id( 'locations' );
if ( ! $PAGE ) {
	echo "ERROR: no page with slug 'locations'.\n";
	return;
}

/** Resolve a location CPT post's URL by slug, or '' when missing. */
function locp_url( $slug ) {
	$p = get_page_by_path( $slug, OBJECT, 'location' );
	return $p ? user_trailingslashit( get_permalink( $p->ID ) ) : '';
}

/** Prose block; `.prose` is a custom class so it survives the Tailwind purge. */
function locp_prose( $html, array $o = array() ) {
	return el_text( '<div class="prose">' . $html . '</div>', array_merge( array( 'raw' => true, 'size' => 16, 'color_global' => 'mutedcol' ), $o ) );
}

/** Salt element ids so they cannot collide with other builders on other pages. */
function locp_reid( array $el, &$n ) {
	$n++;
	$el['id'] = substr( md5( 'buckleup-elementor-locpillar-' . $n ), 0, 7 );
	if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
		foreach ( $el['elements'] as $i => $kid ) {
			if ( is_array( $kid ) ) { $el['elements'][ $i ] = locp_reid( $kid, $n ); }
		}
	}
	return $el;
}

/* ------------------------------------------------------------------- COPY -- */
/*
 * One short, distinct paragraph per area. Written for the index: what makes
 * learning there different, in a sentence or two. The city pages carry the depth.
 */
$AREAS = array(
	array(
		'slug'    => 'coquitlam',
		'city'    => 'Coquitlam',
		'flag'    => 'Busiest teaching area',
		'icon'    => 'fas fa-city',
		'blurb'   => 'The widest range of driving in one lesson. Town Centre traffic around Lafarge Lake, the steep residential climbs of Westwood Plateau, then the high-speed merges onto the Lougheed. Learn to drive comfortably here and most of the Lower Mainland feels straightforward.',
	),
	array(
		'slug'    => 'port-coquitlam',
		'city'    => 'Port Coquitlam',
		'flag'    => 'Good for first lessons',
		'icon'    => 'fas fa-road',
		'blurb'   => 'Quiet residential grids make PoCo a forgiving place to start, and the rail crossings and Coast Meridian Overpass give you something more demanding once the basics feel automatic.',
	),
	array(
		'slug'    => 'port-moody',
		'city'    => 'Port Moody',
		'flag'    => 'Where we are based',
		'icon'    => 'fas fa-home',
		'blurb'   => 'Our home turf. Rocky Point, Newport Village and the Heritage Mountain climbs are minutes from the door, so lessons start driving rather than commuting.',
	),
	array(
		'slug'    => 'north-vancouver',
		'city'    => 'North Vancouver',
		'flag'    => 'Hills and bridges',
		'icon'    => 'fas fa-mountain',
		'blurb'   => 'The North Shore asks more of a new driver: sustained hills, bridge approaches and the Upper Levels. Skills that feel hard here make everywhere else easier.',
	),
);

/* ---------------------------------------------------------------- SECTIONS -- */

$tree = array();

// HERO — H1 on the hub term, not on any city head term.
$tree[] = el_section(
	array( 'bg_global' => 'bgcolor', 'pad_y' => 76, 'gap' => 18 ),
	array(
		el_col(
			array(
				el_container(
					array( 'content_width' => 'full', 'width' => el_size( 100, '%' ), 'flex_direction' => 'row', 'flex_justify_content' => 'center' ),
					array( el_pill( 'Service Areas', 'fas fa-map-marker-alt' ) )
				),
				el_heading(
					'Driving Lessons Across <span class="gradient-text">Metro Vancouver</span>',
					array( 'tag' => 'h1', 'size' => 50, 'weight' => 800, 'align' => 'center', 'color_global' => 'text', 'line_height' => 1.06, 'max_width' => 860 )
				),
				el_text(
					'We teach where we live. Every instructor knows these roads, the intersections that catch people out, and what the local ICBC test centres expect. Pick your area to see what learning to drive there actually looks like.',
					array( 'align' => 'center', 'size' => 18, 'color_global' => 'mutedcol', 'max_width' => 720 )
				),
			),
			array( 'width' => 100, 'gap_px' => 16, 'align' => 'center' )
		),
	)
);

// PILLAR -> CLUSTERS. One card per city, each with the plan's descriptive anchor.
$cards = array();
foreach ( $AREAS as $a ) {
	$url = locp_url( $a['slug'] );
	if ( ! $url ) {
		echo "WARNING: no location post '{$a['slug']}' — card omitted.\n";
		continue;
	}
	$body = el_text( $a['blurb'], array( 'size' => 15, 'color_global' => 'mutedcol' ) );
	// Elementor ignores _flex_grow unless _flex_size is 'custom'; without this the
	// CTA buttons sit at different heights when blurbs differ in length.
	$body['settings']['_flex_size']   = 'custom';
	$body['settings']['_flex_grow']   = 1;
	$body['settings']['_flex_shrink'] = 1;

	$cards[] = el_col(
		array(
			el_container(
				array(
					'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row',
					'flex_align_items' => 'center', 'flex_gap' => array( 'unit' => 'px', 'size' => 10, 'column' => '10', 'row' => '10' ),
					'_flex_grow' => 0,
				),
				array(
					el_icon( $a['icon'], array( 'size' => 22, 'color_global' => 'primary' ) ),
					el_text( $a['flag'], array( 'size' => 13, 'color_global' => 'primary' ) ),
				)
			),
			el_heading( $a['city'], array( 'tag' => 'h3', 'size' => 24, 'weight' => 700, 'color_global' => 'text', 'line_height' => 1.2 ) ),
			$body,
			el_button( 'Driving lessons in ' . $a['city'], array( 'url' => $url, 'size' => 'sm', 'variant' => 'outline', 'icon' => 'fas fa-arrow-right' ) ),
		),
		array( 'width' => 48, 'bg' => '#FFFFFF', 'pad' => 26, 'radius' => 18, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 12, 'align' => 'flex-start' )
	);
}

$tree[] = el_section(
	array( 'bg' => '#FFFFFF', 'pad_y' => 64, 'gap' => 30, 'id_css' => 'areas' ),
	array(
		el_col(
			array(
				el_heading( 'Where We <span class="gradient-text">Teach</span>', array( 'tag' => 'h2', 'size' => 36, 'weight' => 800, 'align' => 'center', 'color_global' => 'text', 'line_height' => 1.1 ) ),
				el_text( 'Four areas, each with its own character. Lessons run in all of them, and we will pick up from home, school or work.', array( 'align' => 'center', 'size' => 17, 'color_global' => 'mutedcol', 'max_width' => 660 ) ),
			),
			array( 'width' => 100, 'gap_px' => 14, 'align' => 'center' )
		),
		el_row( $cards, 22, 'stretch', 'center' ),
	)
);

// TRI-CITIES as the umbrella, not a fifth peer (plan §6 overlap fix).
$tri_url = locp_url( 'tri-cities' );
if ( $tri_url ) {
	$links = array();
	foreach ( array( 'coquitlam' => 'Coquitlam', 'port-coquitlam' => 'Port Coquitlam', 'port-moody' => 'Port Moody' ) as $s => $label ) {
		$u = locp_url( $s );
		if ( $u ) { $links[] = '<a href="' . esc_url( $u ) . '">' . $label . '</a>'; }
	}
	$tree[] = el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 60, 'gap' => 20, 'content_width' => 900 ),
		array(
			el_col(
				array(
					el_heading( 'Covering the Whole <span class="gradient-text">Tri-Cities</span>', array( 'tag' => 'h2', 'size' => 32, 'weight' => 800, 'color_global' => 'text', 'line_height' => 1.12 ) ),
					locp_prose(
						'<p>Coquitlam, Port Coquitlam and Port Moody sit close enough together that plenty of students cross between them every week, so we treat the Tri-Cities as one teaching area. If you are not sure which page applies to you, start with the '
						. '<a href="' . esc_url( $tri_url ) . '">Tri-Cities overview</a>, or go straight to ' . implode( ', ', array_slice( $links, 0, 2 ) )
						. ( isset( $links[2] ) ? ' or ' . $links[2] : '' ) . '.</p>'
					),
				),
				array( 'width' => 100, 'gap_px' => 14, 'align' => 'flex-start' )
			),
		)
	);
}

// CROSS-HUB — local pages feed the commercial pages (plan §5 "connect the hubs").
$svc = get_page_by_path( 'services' );
$tree[] = el_section(
	array( 'bg' => '#FFFFFF', 'pad_y' => 60, 'gap' => 24, 'content_width' => 900 ),
	array(
		el_col(
			array(
				el_heading( 'Whichever Area You Are In', array( 'tag' => 'h2', 'size' => 30, 'weight' => 800, 'color_global' => 'text', 'line_height' => 1.15 ) ),
				locp_prose(
					'<p>The lessons themselves are the same wherever you book. See the full range of '
					. '<a href="' . esc_url( $svc ? get_permalink( $svc->ID ) : home_url( '/services/' ) ) . '">driving lessons and packages</a>, from '
					. '<a href="' . esc_url( home_url( '/services/class-7-driving-lessons/' ) ) . '">Class 7 lessons for new drivers</a> through to '
					. '<a href="' . esc_url( home_url( '/services/class-5-driving-lessons/' ) ) . '">Class 5 road test preparation</a> and '
					. '<a href="' . esc_url( home_url( '/services/class-4-driving-lessons/' ) ) . '">Class 4 commercial training</a>.</p>'
					. '<p>Getting ready for a test? Our local guides cover '
					. '<a href="' . esc_url( home_url( '/icbc-road-test-routes-coquitlam-tri-cities/' ) ) . '">road test routes in Coquitlam and the Tri-Cities</a> and '
					. '<a href="' . esc_url( home_url( '/icbc-road-test-routes-north-vancouver-lynn-valley/' ) ) . '">North Vancouver</a>, plus '
					. '<a href="' . esc_url( home_url( '/what-to-expect-icbc-road-test-coquitlam/' ) ) . '">what to expect on the day</a>.</p>',
					array( 'size' => 15 )
				),
			),
			array( 'width' => 100, 'gap_px' => 14, 'align' => 'flex-start' )
		),
	)
);

// CTA
$tree[] = el_section(
	array( 'bg_global' => 'bgcolor', 'pad_y' => 64, 'gap' => 16, 'content_width' => 900 ),
	array(
		el_col(
			array(
				el_heading( 'Not sure we cover your area?', array( 'tag' => 'h2', 'size' => 28, 'weight' => 800, 'align' => 'center', 'color_global' => 'text' ) ),
				el_text( 'Ask. We travel a little beyond these areas for regular students, and we will tell you honestly if someone closer would serve you better.', array( 'align' => 'center', 'size' => 16, 'color_global' => 'mutedcol', 'max_width' => 580 ) ),
				el_container(
					array( 'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row', 'flex_gap' => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12' ), '_flex_grow' => 0, 'padding' => el_box( 8, 0, 0, 0 ) ),
					array(
						el_button( 'Contact us', array( 'url' => home_url( '/contact/' ), 'size' => 'lg', 'icon' => 'fas fa-arrow-right', 'bg_global' => 'primary' ) ),
						el_button( 'See packages', array( 'url' => home_url( '/services/#pricing' ), 'size' => 'lg', 'variant' => 'outline' ) ),
					)
				),
			),
			array( 'width' => 100, 'gap_px' => 14, 'align' => 'center', 'bg' => '#FFFFFF', 'pad' => 32, 'radius' => 24, 'border' => '#CBD5E1', 'shadow' => true )
		),
	)
);

/* ------------------------------------------------------------------- SAVE -- */
$n = 0;
foreach ( $tree as $i => $node ) { $tree[ $i ] = locp_reid( $node, $n ); }

el_save_page( $PAGE, $tree );

// SEO. Targets the hub/service-area terms, never a city head term.
$title = 'Driving Lesson Service Areas in Metro Vancouver | BuckleUp';
$desc  = 'Where BuckleUp teaches: Coquitlam, Port Coquitlam, Port Moody, North Vancouver and the wider Tri-Cities. Local instructors who know the roads and the test centres.';
update_post_meta( $PAGE, 'bu_seo_title', $title );
update_post_meta( $PAGE, 'bu_seo_description', $desc );
update_post_meta( $PAGE, 'rank_math_title', $title );
update_post_meta( $PAGE, 'rank_math_description', $desc );

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();

echo 'Locations pillar built: ' . count( $cards ) . " area cards + Tri-Cities umbrella.\n";
echo 'URL: ' . get_permalink( $PAGE ) . "\n";
