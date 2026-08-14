<?php
/**
 * Inject the "Lessons by licence class" card section into Home, right below the hero.
 *
 * WHY THIS SECTION EXISTS (SEO): the home page ranked on brand + location terms but
 * had no on-page copy targeting the actual services people search for — "Class 7
 * driving lessons", "Class 5 road test", "Class 4 licence", "ICBC road test". This
 * section puts those terms in an H2 + one H3 card per lesson type immediately after
 * the H1, which is the strongest position on the page, and links each straight to
 * its /services/ cluster page — so the strongest section on the site feeds the money
 * pages rather than the blog.
 *
 * PLACEMENT: element index 1 — directly after the BuckleUp Hero, before the
 * graduates gallery. Hero (what we are) → services (what we sell) → proof
 * (graduates/testimonials) → pricing → CTA.
 *
 * Cards are native Elementor Containers + widgets, so every heading, paragraph,
 * bullet and link is editable in the Elementor panel — no code edit needed to
 * reword them later.
 *
 * Idempotent: re-running strips the previously-injected section (matched on the
 * container's `_element_id` = "lessons") before re-inserting, so it never stacks.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-lessons.php
 */
require __DIR__ . '/lib.php';

$PAGE_ID = el_post_id( 'home' );
if ( ! $PAGE_ID ) {
	echo "ERROR: no page with slug 'home'. Run provision first.\n";
	return;
}

/** Anchor/CSS id — also the marker used to strip a previous injection. */
const BU_LESSONS_ID = 'lessons';

/**
 * Re-key a subtree with ids salted for THIS builder, so it can never collide with
 * ids build-home.php / build-hero.php already minted in the same document.
 */
function lessons_reid( array $element, &$n ) {
	$n++;
	$element['id'] = substr( md5( 'buckleup-elementor-lessons-' . $n ), 0, 7 );
	if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
		foreach ( $element['elements'] as $i => $child ) {
			if ( is_array( $child ) ) {
				$element['elements'][ $i ] = lessons_reid( $child, $n );
			}
		}
	}
	return $element;
}

/** Round icon chip behind a Font Awesome glyph (matches the inner-page card style). */
function lessons_icon_chip( $fa ) {
	return el_container(
		array(
			'content_width'         => 'full',
			'css_classes'           => 'bu-hug',
			'flex_direction'        => 'row',
			'flex_justify_content'  => 'center',
			'flex_align_items'      => 'center',
			'background_background' => 'classic',
			'background_color'      => 'rgba(11,92,224,0.10)',
			'border_radius'         => el_box( 16, 16, 16, 16 ),
			'padding'               => el_box( 14, 14, 14, 14 ),
			'_flex_grow'            => 0,
		),
		array( el_icon( $fa, array( 'size' => 24, 'color_global' => 'primary' ) ) )
	);
}

/**
 * One lesson card: icon, H3 (the keyword-bearing heading), description, feature
 * bullets, and a text link into the matching article/hub.
 */
function lessons_card( array $c ) {
	// The four cards carry different amounts of copy. Letting the feature list grow
	// absorbs the slack inside each card, so every CTA button lines up along the
	// bottom of the row instead of floating at a different height per card.
	// NOTE: Elementor ignores `_flex_grow` unless `_flex_size` is 'custom' — without
	// it the control never reaches the CSS and every card keeps its natural height.
	$features = el_icon_list( $c['features'], array( 'icon' => 'fas fa-check', 'color_global' => 'secondary', 'size' => 14 ) );
	$features['settings']['_flex_size']   = 'custom';
	$features['settings']['_flex_grow']   = 1;
	$features['settings']['_flex_shrink'] = 1;

	return el_col(
		array(
			lessons_icon_chip( $c['icon'] ),
			el_heading( $c['title'], array( 'tag' => 'h3', 'size' => 20, 'weight' => 700, 'color_global' => 'text', 'line_height' => 1.25 ) ),
			el_text( $c['desc'], array( 'size' => 15, 'color_global' => 'mutedcol' ) ),
			$features,
			el_button( $c['cta'], array( 'url' => $c['url'], 'size' => 'sm', 'variant' => 'outline', 'icon' => 'fas fa-arrow-right' ) ),
		),
		array(
			'width'  => 31,
			'bg'     => '#FFFFFF',
			'pad'    => 24,
			'radius' => 18,
			'border' => '#CBD5E1',
			'shadow' => true,
			'gap_px' => 12,
			'align'  => 'flex-start',
		)
	);
}

/* ------------------------------------------------------------------ COPY -- */
/*
 * Cards come from services-content.php — the SAME file the /services/ pillar and
 * the cluster pages read. Previously this block held its own copy of the card
 * text and pointed at blog posts; that meant the home page could drift from the
 * service pages, and the strongest section on the site sent visitors to articles
 * rather than to the money pages.
 *
 * Each card now links to its cluster page at /services/<slug>/, falling back to
 * the pillar's #lessons anchor if a cluster has not been built yet — so this
 * never emits a dead link.
 */
$svc   = require __DIR__ . '/services-content.php';
$cards = array();
foreach ( $svc as $svc_slug => $svc_item ) {
	$svc_page = get_page_by_path( 'services/' . $svc_slug );
	$cards[] = array(
		'icon'     => $svc_item['icon'],
		'title'    => $svc_item['card_title'],
		'desc'     => $svc_item['short'],
		// Three bullets keeps the home teaser scannable; the cluster page carries
		// the full list.
		'features' => array_slice( $svc_item['features'], 0, 3 ),
		'cta'      => $svc_item['nav_label'],
		'url'      => $svc_page ? get_permalink( $svc_page->ID ) : ( home_url( '/services/' ) . '#lessons' ),
	);
}
/* --------------------------------------------------------------- SECTION -- */
$heading_block = el_col(
	array(
		el_container(
			array( 'content_width' => 'full', 'width' => el_size( 100, '%' ), 'flex_direction' => 'row', 'flex_justify_content' => 'center' ),
			array( el_pill( 'Lessons &amp; Training', 'fas fa-graduation-cap' ) )
		),
		// H2 carries the primary head term; the gradient span matches the site's
		// existing section-heading treatment.
		el_heading(
			'Driving Lessons for Every <span class="gradient-text">ICBC Licence Class</span>',
			array( 'tag' => 'h2', 'size' => 38, 'weight' => 800, 'align' => 'center', 'color_global' => 'text', 'line_height' => 1.12 )
		),
		el_text(
			'ICBC-certified driving lessons in Coquitlam, Port Coquitlam, Port Moody and North Vancouver — from your first lesson on a Class 7L learner\'s licence through to your Class 5 or Class 4 road test, plus highway and refresher sessions.',
			array( 'align' => 'center', 'size' => 17, 'color_global' => 'mutedcol', 'max_width' => 720 )
		),
	),
	array( 'width' => 100, 'gap_px' => 14, 'align' => 'center' )
);

$section = el_section(
	array( 'bg_global' => 'bgcolor', 'pad_y' => 72, 'gap' => 32, 'id_css' => BU_LESSONS_ID ),
	array(
		$heading_block,
		el_row( array_map( 'lessons_card', $cards ), 20, 'stretch', 'center' ),
	)
);

/* ------------------------------------------------------ INJECT INTO HOME -- */
$raw      = get_post_meta( $PAGE_ID, '_elementor_data', true );
$elements = ( is_string( $raw ) && '' !== $raw ) ? json_decode( $raw, true ) : array();
if ( ! is_array( $elements ) || ! $elements ) {
	echo "ERROR: page $PAGE_ID has no readable _elementor_data. Run build-home.php first.\n";
	return;
}

$before   = count( $elements );
$elements = array_values( array_filter( $elements, function ( $el ) {
	return ! ( is_array( $el ) && isset( $el['settings']['_element_id'] ) && BU_LESSONS_ID === $el['settings']['_element_id'] );
} ) );
$removed = $before - count( $elements );

$n       = 0;
$section = lessons_reid( $section, $n );

// Position 1 = straight after the hero (index 0). If the hero is ever absent we
// still want the section near the top, so clamp rather than assume.
$pos = min( 1, count( $elements ) );
array_splice( $elements, $pos, 0, array( $section ) );

el_save_page( $PAGE_ID, $elements );

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}

echo 'Lessons section injected at index ' . $pos . ' of ' . count( $elements )
	. ( $removed ? " (replaced $removed existing)" : '' ) . ".\n";
echo 'Cards: ' . count( $cards ) . ' — ' . implode( ', ', array_map( function ( $c ) {
	return html_entity_decode( strip_tags( $c['title'] ) );
}, $cards ) ) . "\n";
$unlinked = count( array_filter( $cards, function ( $c ) { return false !== strpos( $c['url'], '#lessons' ); } ) );
if ( $unlinked ) {
	echo "WARNING: $unlinked card(s) fell back to the pillar anchor — run build-service-clusters.php.\n";
}
echo 'Anchor: ' . get_permalink( $PAGE_ID ) . '#' . BU_LESSONS_ID . "\n";
