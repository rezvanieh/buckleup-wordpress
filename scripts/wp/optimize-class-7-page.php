<?php
/**
 * Optimize /services/class-7-driving-lessons/ (page 630) for GLP commercial intent.
 *
 * WHY THIS PAGE AND NOT A NEW ONE
 * -------------------------------
 * The GLP is the licence system; the thing people actually buy at the start of it
 * is a beginner lesson. A dedicated "GLP course" page would rank for the query and
 * then fail the visitor, because BuckleUp does NOT offer the ICBC-approved GLP
 * course (confirmed by the client 2026-08-16). So this page absorbs the intent by
 * answering the question honestly instead of implying an answer.
 *
 * The stage durations below (12 months at 7L, 24 at 7N, 18 with an approved
 * course) are taken verbatim from the site's own GLP post so the two pages cannot
 * contradict each other. If that post is ever re-edited, re-check these.
 *
 * WHAT IT ADDS
 *   1. "Where Class 7 fits in BC's Graduated Licensing Program" after the intro.
 *   2. "Which stage are you at?" after "What these lessons cover".
 *   3. Three FAQs, including a flat "no" on the approved-course question.
 *   4. A short "where we teach" block (the SEO title promises Coquitlam and the
 *      body never delivered it). NO pickup wording, per the site-wide cleanup.
 *   5. rank_math_focus_keyword, which was empty.
 *
 * The hero, the icon list, "Other lessons we offer" and the whole CTA section are
 * left exactly as they are.
 *
 * HOW IT BUILDS WIDGETS
 * ---------------------
 * New widgets are DEEP CLONES of existing ones with fresh ids, not hand-authored
 * settings objects. Cloning inherits the page's typography and spacing exactly;
 * hand-authoring guesses at it and drifts.
 *
 * Idempotent: keyed off the GLP heading, so a second run is a no-op.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/optimize-class-7-page.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const BU_C7_SITE   = 'https://www.buckleupdriving.ca';
const BU_C7_MARKER = 'Where Class 7 fits';

/* The page id is NOT the same on dev and prod (630 here, 612 on the live site),
 * so resolve it by path. Hard-coding the dev id made the first prod run abort
 * with "no Elementor data", which is the harmless failure mode, but only by
 * luck: 630 could just as easily have been a different real page there. */
$bu_c7_page = get_page_by_path( 'services/class-7-driving-lessons' );
if ( ! $bu_c7_page ) { echo "ABORT: /services/class-7-driving-lessons/ not found\n"; return; }
define( 'BU_C7_PAGE', (int) $bu_c7_page->ID );
printf( "  target page: %d (%s)\n", BU_C7_PAGE, $bu_c7_page->post_title );

global $wpdb;
mysqli_report( MYSQLI_REPORT_OFF );

/** Raw read: through $wpdb every literal % in Elementor data gets rewritten. */
function bu_c7_data( $post_id ) {
	global $wpdb;
	$r   = mysqli_query( $wpdb->dbh, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=" . (int) $post_id . " AND meta_key='_elementor_data' LIMIT 1" );
	$row = $r ? mysqli_fetch_row( $r ) : null;
	return $row ? (string) $row[0] : '';
}

/** Fresh 7-hex id in Elementor's own format. */
function bu_c7_id() {
	return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 7 );
}

/** Deep clone a node, regenerating every id inside it. */
function bu_c7_clone( array $node ) {
	$node['id'] = bu_c7_id();
	if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
		foreach ( $node['elements'] as $i => $child ) {
			if ( is_array( $child ) ) { $node['elements'][ $i ] = bu_c7_clone( $child ); }
		}
	}
	return $node;
}

/** Locate a node by its Elementor id. Returns a reference. */
function &bu_c7_find( array &$els, $id ) {
	$null = null;
	foreach ( $els as &$el ) {
		if ( ! is_array( $el ) ) { continue; }
		if ( ( $el['id'] ?? '' ) === $id ) { return $el; }
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$hit = &bu_c7_find( $el['elements'], $id );
			if ( null !== $hit ) { return $hit; }
			unset( $hit );
		}
	}
	unset( $el );
	return $null;
}

$link = function ( $path, $label ) {
	return '<a href="' . BU_C7_SITE . $path . '">' . $label . '</a>';
};

/* ------------------------------------------------------------------ copy --- */

$glp_heading = "Where Class 7 fits in BC's Graduated Licensing Program";

$glp_body =
	'<p>Almost every new driver in BC goes through the Graduated Licensing Program, or GLP. It has three stages, and Class 7 covers the first two of them.</p>'
	. '<p><strong>Stage 1: Class 7L, your learner\'s licence.</strong> You pass the ICBC knowledge test, then hold your 7L for at least 12 months before you are eligible for the Class 7 road test. This is the stage most of our beginner lessons are for. We start on quiet residential streets and move up to busier roads once the basics stop taking all of your attention.</p>'
	. '<p><strong>Stage 2: Class 7N, your novice licence.</strong> Passing the Class 7 road test gets you here. You drive on your own, with restrictions on passengers and on your blood alcohol level. Lessons at this stage are usually about the roads people avoided as a learner: highway merging, driving at night, and parking somewhere genuinely tight.</p>'
	. '<p><strong>Stage 3: Class 5, a full licence.</strong> After at least 24 months at the novice stage you take one more road test, and passing it clears the last of the restrictions.</p>'
	. '<p><strong>A note on ICBC-approved GLP courses.</strong> You may have read that finishing an ICBC-approved GLP course during the learner stage can reduce the novice stage from 24 months to 18. That is a specific course a school has to be separately authorised by ICBC to deliver, and it is not the same thing as regular driving lessons. BuckleUp does not offer the approved GLP course. Our lessons are built to get you ready to pass, not to shorten the waiting period. If the shorter novice stage is what you are after, look for a school advertising the approved course by name.</p>'
	. '<p>Want the full picture first? Read our ' . $link( '/bc-graduated-licensing-program-explained-7l-7n-class-5/', "guide to BC's Graduated Licensing Program" ) . '.</p>';

$stage_heading = 'Which stage are you at?';

$stage_body =
	'<p><strong>I have my 7L and I have never really driven.</strong> Start with a beginner lesson. Dual-control car, no assumed knowledge, and an honest read on where you are after the first session. Most learners run lessons alongside practice hours with a parent or friend.</p>'
	. '<p><strong>I have my 7L and my road test is coming up.</strong> Lessons shift to test conditions: the manoeuvres that get marked, the mistakes that fail people, and the roads around the test centre. See ' . $link( '/class-7-road-test-preparation-bc/', 'Class 7 road test preparation' ) . '.</p>'
	. '<p><strong>I am driving on a 7N.</strong> You already have the basics. Lessons here target highway, night and confidence driving, and get you ready for the Class 5 test. Worth reading: ' . $link( '/class-7n-novice-restrictions-bc/', 'the rules that apply on a 7N' ) . ' and ' . $link( '/class-7n-to-class-5-bc/', 'going from your N to a full Class 5' ) . '.</p>';

$new_faqs = array(
	array(
		'Is this an ICBC-approved GLP course?',
		'<p>No. We teach ICBC-certified driving lessons, which is not the same as the ICBC-approved GLP course that a school has to be separately authorised to run. Our lessons prepare you to pass your road tests; they do not shorten the novice stage. We would rather tell you that up front than have you find out later.</p>',
	),
	array(
		'Will lessons shorten how long I have to wait between stages?',
		'<p>No. The waiting periods in the Graduated Licensing Program are set by ICBC and are the same whether or not you take lessons. What lessons change is how ready you are when the wait is over, which matters because a failed road test means booking again and waiting for the next appointment.</p>',
	),
	array(
		'I am nervous and I have never driven at all. Is that a problem?',
		'<p>Not at all, and it describes a lot of our students. First lessons for complete beginners start in a quiet area at a pace you set, in a car with a second brake on the instructor\'s side. There is nothing you can do that the instructor cannot undo.</p>',
	),
);

$local_body =
	'<p><strong>Where we teach.</strong> Lessons run across the Tri-Cities and the North Shore, including '
	. $link( '/locations/coquitlam/', 'Coquitlam' ) . ', '
	. $link( '/locations/port-coquitlam/', 'Port Coquitlam' ) . ', '
	. $link( '/locations/port-moody/', 'Port Moody' ) . ' and '
	. $link( '/locations/north-vancouver/', 'North Vancouver' ) . '.</p>';

/* --------------------------------------------------------------- guards --- */

$all_copy = $glp_heading . $glp_body . $stage_heading . $stage_body . $local_body;
foreach ( $new_faqs as $f ) { $all_copy .= $f[0] . $f[1]; }

if ( false !== strpos( $all_copy, "\xE2\x80\x94" ) ) {
	echo "ABORT: copy contains an em dash, which this site does not use.\n";
	return;
}
if ( preg_match( '/pick[ -]?up|pick you up|door[ -]?to[ -]?door/i', $all_copy ) ) {
	echo "ABORT: copy implies a pickup service, which BuckleUp does not offer.\n";
	return;
}

$json = bu_c7_data( BU_C7_PAGE );
if ( '' === $json ) { echo "ABORT: no Elementor data on page " . BU_C7_PAGE . "\n"; return; }

$data = json_decode( $json, true );
if ( ! is_array( $data ) ) { echo "ABORT: Elementor data will not decode\n"; return; }

if ( false !== strpos( $json, BU_C7_MARKER ) ) {
	echo "  already applied (found '" . BU_C7_MARKER . "')\n";
	return;
}

/* ---------------------------------------------------- templates to clone --- */

// A section shaped: container > container > container > [heading, widget]
$tpl_section = null;
foreach ( $data as $sec ) {
	if ( ( $sec['id'] ?? '' ) === 'a5a4574' ) { $tpl_section = $sec; break; }
}
// The intro paragraph, used as the text-editor template.
$tpl_text = bu_c7_find( $data, '7f3cd2a' );
// One existing FAQ item: container > [heading, text-editor]
$tpl_faq = bu_c7_find( $data, '60a7ba7' );

if ( ! $tpl_section || ! $tpl_text || ! $tpl_faq ) {
	echo "ABORT: could not find the template widgets to clone (page structure changed)\n";
	return;
}

/** Build a section from the template: heading text + one text-editor body. */
$make_section = function ( $heading, $body ) use ( $tpl_section, $tpl_text ) {
	$sec = bu_c7_clone( $tpl_section );
	$inner = &$sec['elements'][0]['elements'][0]['elements'];
	$inner[0]['settings']['title'] = $heading;          // the heading widget
	$txt = bu_c7_clone( $tpl_text );
	$txt['settings']['editor'] = $body;
	$inner[1] = $txt;                                    // replaces the icon-list
	unset( $inner );
	return $sec;
};

/* ------------------------------------------------------------ 1+2 sections - */

$glp_section   = $make_section( $glp_heading, $glp_body );
$stage_section = $make_section( $stage_heading, $stage_body );

// Find current top-level index of the intro section (fbe55bd) and the
// "What these lessons cover" section (a5a4574), then insert after each.
$idx = array();
foreach ( $data as $i => $sec ) { $idx[ $sec['id'] ?? '' ] = $i; }

if ( ! isset( $idx['fbe55bd'], $idx['a5a4574'] ) ) {
	echo "ABORT: expected top-level sections not found\n";
	return;
}

// Insert the later one first so the earlier index stays valid.
array_splice( $data, $idx['a5a4574'] + 1, 0, array( $stage_section ) );
array_splice( $data, $idx['fbe55bd'] + 1, 0, array( $glp_section ) );

/* --------------------------------------------------------------- 3. FAQs --- */

$faq_wrap = &bu_c7_find( $data, '85f3f79' );
if ( null === $faq_wrap ) { echo "ABORT: FAQ container not found\n"; return; }

foreach ( $new_faqs as $f ) {
	$item = bu_c7_clone( $tpl_faq );
	$item['elements'][0]['settings']['title']  = $f[0];
	$item['elements'][1]['settings']['editor'] = $f[1];
	$faq_wrap['elements'][] = $item;
}
unset( $faq_wrap );

/* -------------------------------------------------------- 4. local block --- */

$other_wrap = &bu_c7_find( $data, 'c80559a' );
if ( null === $other_wrap ) { echo "ABORT: 'Other lessons' container not found\n"; return; }
$local = bu_c7_clone( $tpl_text );
$local['settings']['editor'] = $local_body;
$other_wrap['elements'][] = $local;
unset( $other_wrap );

/* ----------------------------------------------------------------- save --- */

$out = wp_json_encode( $data );
if ( ! is_array( json_decode( $out, true ) ) ) { echo "ABORT: result is not valid JSON, nothing written\n"; return; }

update_post_meta( BU_C7_PAGE, '_elementor_data', wp_slash( $out ) );
delete_post_meta( BU_C7_PAGE, '_elementor_element_cache' );

/* ------------------------------------------------------------- 5. focus --- */

$kw = get_post_meta( BU_C7_PAGE, 'rank_math_focus_keyword', true );
if ( '' === trim( (string) $kw ) ) {
	update_post_meta( BU_C7_PAGE, 'rank_math_focus_keyword', 'class 7 driving lessons' );
	echo "  focus keyword set to 'class 7 driving lessons'\n";
} else {
	printf( "  focus keyword left as '%s'\n", $kw );
}

if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
wp_cache_flush();

echo "  added: GLP stage section, 'Which stage are you at?', 3 FAQs, where-we-teach block\n";
echo "  hero, icon list, 'Other lessons' and the CTA section untouched\n";
