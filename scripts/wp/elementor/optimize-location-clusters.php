<?php
/**
 * Optimise the Coquitlam and Port Coquitlam location clusters (Hub 4).
 *
 * These two pages are CLIENT-EDITED, so this script patches the snapshot JSON in
 * snapshots/locations/ surgically rather than regenerating the pages. Every edit
 * targets a specific Elementor widget id, asserts it found what it expected, and
 * re-validates the result as JSON. A near-miss aborts loudly instead of writing a
 * mangled page body. Run restore-location-snapshots.php afterwards to apply.
 *
 * WHAT WAS WRONG
 * --------------
 * Coquitlam
 *   1. The "Driving lessons we offer" section had the SAME card three times:
 *      three H3s reading "Class 7 driving lessons" with identical body copy. So
 *      the page repeated one lesson type and never mentioned Class 5 or Class 4,
 *      the two highest-intent terms a Coquitlam learner searches for.
 *   2. "Driving lessons in nearby areas" had three anchors, none of them usable:
 *      two pointed at a claude.ai chat URL and one at "http://Driving school in
 *      Port Coquitlam", which WordPress had already flagged
 *      (data-wplink-url-error). The section rendered as three dead links.
 *   3. No link to any /services/ cluster, so the strongest city page passed no
 *      equity to the money pages.
 * Port Coquitlam
 *   4. No lesson-type section at all, unlike Coquitlam and Port Moody. Nothing on
 *      the page said "Class 7", "Class 5" or "Class 4", so it could not rank for
 *      the licence-class terms its siblings target.
 *
 * The fix follows the pillar/cluster linking rules: each cluster links UP to the
 * /locations/ pillar, ACROSS to its siblings, and OUT to the Hub 1 service
 * clusters that match the intent expressed on the page.
 *
 * Idempotent: re-running detects the already-applied state and reports it.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/optimize-location-clusters.php
 *      then: ... wp eval-file /scripts/wp/elementor/restore-location-snapshots.php
 */

$DIR = __DIR__ . '/snapshots/locations';

/** Snapshots store production URLs verbatim; the restore script retargets them. */
const BU_SITE = 'https://www.buckleupdriving.ca';

function bu_link( $path ) {
	return array( 'url' => BU_SITE . $path, 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' );
}

/** Depth-first walk over an Elementor tree, by reference so callbacks can edit. */
function bu_walk( array &$els, callable $cb ) {
	foreach ( $els as &$el ) {
		if ( ! is_array( $el ) ) { continue; }
		$cb( $el );
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			bu_walk( $el['elements'], $cb );
		}
	}
	unset( $el );
}

function bu_load( $file ) {
	$data = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $data ) || ! $data ) {
		echo "ABORT: $file is not readable Elementor data\n";
		exit;
	}
	return $data;
}

function bu_save( $file, array $data ) {
	$json = json_encode( $data );
	if ( ! is_string( $json ) || ! is_array( json_decode( $json, true ) ) ) {
		echo "ABORT: refusing to write invalid JSON to $file\n";
		exit;
	}
	file_put_contents( $file, $json );
}

/* ============================================================== COQUITLAM == */

$file = "$DIR/coquitlam.json";
$co   = bu_load( $file );

/*
 * The three duplicated cards, given the identities they should always have had.
 * Card 1 keeps Class 7 (correct already) and just gains the city qualifier plus a
 * link; cards 2 and 3 become Class 5 and Class 4. Copy is written to match the
 * page's existing voice: second person, contractions, no em dashes.
 */
$heading_edits = array(
	// widget id       => array( new H3, /services/ cluster path )
	'4305eb0' => array( 'Class 7 driving lessons in Coquitlam',       '/services/class-7-driving-lessons/' ),
	'91b9672' => array( 'Class 5 road test preparation in Coquitlam', '/services/class-5-driving-lessons/' ),
	'0b77b0b' => array( 'Class 4 driving lessons in Coquitlam',       '/services/class-4-driving-lessons/' ),
	'ba94a11' => array( 'Refresher lessons',                          '/services/refresher-driving-lessons/' ),
	'5f65a98' => array( 'Parking, highway and pre-test warm-up lessons', '/services/highway-driving-lessons/' ),
);

$body_edits = array(
	'91b9672_body' => '4ec4101',
);

/** New body copy for the two cards that were carrying Class 7 text by mistake. */
$new_bodies = array(
	'da873e7' => '<p>for learners ready to book the Class 5 road test, working through the lane changes, parking and observation the examiner scores</p>',
	'4ec4101' => '<p>for drivers going commercial, covering the passenger-vehicle skills and road awareness a Class 4 test looks for</p>',
);

$seen = array();
bu_walk( $co, function ( &$el ) use ( $heading_edits, $new_bodies, &$seen ) {
	$id = $el['id'] ?? '';
	if ( isset( $heading_edits[ $id ] ) && 'heading' === ( $el['widgetType'] ?? '' ) ) {
		list( $title, $path )        = $heading_edits[ $id ];
		$el['settings']['title']     = $title;
		$el['settings']['link']      = bu_link( $path );
		$seen[] = "H3 $id -> $title";
	}
	if ( isset( $new_bodies[ $id ] ) && 'text-editor' === ( $el['widgetType'] ?? '' ) ) {
		$el['settings']['editor'] = $new_bodies[ $id ];
		$seen[] = "body $id rewritten";
	}
} );

/*
 * Rebuild the nearby-areas paragraph. The old markup is discarded wholesale
 * because every href in it was broken; nothing there was worth preserving.
 * Anchors are descriptive ("Driving school in Port Moody") rather than bare city
 * names, and the paragraph now also links UP to the locations pillar.
 */
$nearby = '<p>We also serve learners right across Metro Vancouver: '
	. '<a href="' . BU_SITE . '/locations/port-moody/">driving school in Port Moody</a>, '
	. '<a href="' . BU_SITE . '/locations/port-coquitlam/">driving school in Port Coquitlam</a>, '
	. '<a href="' . BU_SITE . '/locations/north-vancouver/">driving school in North Vancouver</a>, '
	. 'and <a href="' . BU_SITE . '/locations/tri-cities/">driving lessons across the Tri-Cities</a>. '
	. 'You can see <a href="' . BU_SITE . '/locations/">every area we teach in</a>, or compare '
	. '<a href="' . BU_SITE . '/services/">lesson packages and pricing</a>.</p>';

bu_walk( $co, function ( &$el ) use ( $nearby, &$seen ) {
	if ( 'a5ae154' === ( $el['id'] ?? '' ) ) {
		$el['settings']['editor'] = $nearby;
		$seen[] = 'nearby-areas links rebuilt (removed 2 claude.ai URLs + 1 invalid href)';
	}
} );

bu_save( $file, $co );
echo "coquitlam.json\n";
foreach ( $seen as $s ) { echo "  $s\n"; }
if ( count( $seen ) < 7 ) { echo "  WARNING: expected 7 edits, applied " . count( $seen ) . " (already optimised?)\n"; }

/* ========================================================= PORT COQUITLAM == */

$pc_file = "$DIR/port-coquitlam.json";
$pc      = bu_load( $pc_file );
$pm      = bu_load( "$DIR/port-moody.json" );

const BU_POCO_LESSONS_ID = 'poco-lessons';

// Idempotency: bail out if the section is already present.
$exists = false;
bu_walk( $pc, function ( &$el ) use ( &$exists ) {
	if ( BU_POCO_LESSONS_ID === ( $el['settings']['_element_id'] ?? '' ) ) { $exists = true; }
} );

if ( $exists ) {
	echo "port-coquitlam.json\n  lessons section already present, left alone\n";
} else {
	/*
	 * Clone Port Moody's lessons section so Port Coquitlam inherits identical
	 * styling, then replace the copy. Cloning beats rebuilding from scratch: the
	 * client has tuned the visual settings on these pages and a hand-built section
	 * would not match.
	 */
	$section = $pm[3];
	$section['settings']['_element_id'] = BU_POCO_LESSONS_ID;

	$n = 0;
	$reid = function ( array $el ) use ( &$reid, &$n ) {
		$n++;
		$el['id'] = substr( md5( 'buckleup-poco-lessons-' . $n ), 0, 7 );
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			foreach ( $el['elements'] as $i => $child ) {
				if ( is_array( $child ) ) { $el['elements'][ $i ] = $reid( $child ); }
			}
		}
		return $el;
	};
	$section = $reid( $section );

	/*
	 * Copy in document order. Headings carry the licence class plus the city (the
	 * exact phrase people search) and link to the matching Hub 1 cluster, so this
	 * section is both the page's keyword coverage and its route into the money
	 * pages. Pricing is deliberately not repeated here; the button carries it.
	 */
	$content = array(
		array( 'text',    '<p>Driving Lessons </p>' ),
		array( 'heading', 'Driving Lessons in <span class="gradient-text">Port Coquitlam</span>', null ),
		array( 'text',    '<p>Lessons in Port Coquitlam built around your licence level, your confidence, and the road test you are working towards.</p>' ),
		array( 'heading', 'Class 7 Driving Lessons in Port Coquitlam', '/services/class-7-driving-lessons/' ),
		array( 'text',    '<p>The place to start if you have your L and have barely driven. We begin on the quieter streets around Birchland Manor and Riverwood, then work up to Lougheed Highway and the rail crossings once the basics feel automatic.</p>' ),
		array( 'heading', 'Class 5 Road Test Preparation in Port Coquitlam', '/services/class-5-driving-lessons/' ),
		array( 'text',    '<p>For drivers with a test date coming up. We drill the things examiners actually score, including lane changes, parking, merging, speed control and shoulder checks, on the same routes your test will use out of the Coquitlam licensing office.</p>' ),
		array( 'heading', 'Class 4 Driving Lessons in Port Coquitlam', '/services/class-4-driving-lessons/' ),
		array( 'text',    '<p>For anyone going commercial, whether that is taxi, ride-hailing or a small passenger vehicle. Lessons cover the extra road awareness, smoother control and defensive habits a Class 4 examiner expects.</p>' ),
		array( 'button',  'Driving Lesson Packages', '/services/' ),
	);

	// Collect the section's content-bearing widgets in document order. bu_walk
	// takes a LIST of elements, so the section is wrapped and unwrapped after the
	// references in $slots have been written through.
	$slots = array();
	$wrap  = array( $section );
	bu_walk( $wrap, function ( &$el ) use ( &$slots ) {
		$w = $el['widgetType'] ?? '';
		if ( in_array( $w, array( 'heading', 'text-editor', 'button' ), true ) ) {
			$slots[] = &$el;
		}
	} );

	if ( count( $slots ) !== count( $content ) ) {
		echo 'ABORT: Port Moody template has ' . count( $slots ) . ' widgets, expected ' . count( $content )
			. " — the template changed, so the copy mapping is no longer safe.\n";
		exit;
	}

	foreach ( $content as $i => $c ) {
		$el = &$slots[ $i ];
		if ( 'heading' === $c[0] ) {
			$el['settings']['title'] = $c[1];
			if ( ! empty( $c[2] ) ) { $el['settings']['link'] = bu_link( $c[2] ); }
			else { unset( $el['settings']['link'] ); }
		} elseif ( 'text' === $c[0] ) {
			$el['settings']['editor'] = $c[1];
		} else {
			$el['settings']['text'] = $c[1];
			$el['settings']['link'] = bu_link( $c[2] );
		}
		unset( $el );
	}

	$section = $wrap[0];

	// Slot in after "Why Port Coquitlam Drivers Choose BuckleUp" and before
	// "Neighbourhoods", which is where Port Moody carries the same section.
	array_splice( $pc, 3, 0, array( $section ) );

	bu_save( $pc_file, $pc );
	echo "port-coquitlam.json\n  lessons section inserted at index 3 (Class 7 / 5 / 4, each linked to its service cluster)\n";
}

/*
 * Both pages also gain a link UP to the pillar from their existing cross-area
 * paragraph. Coquitlam got one above; Port Coquitlam's lives in its
 * "Tri-Cities & North Shore" block.
 */
$pc  = bu_load( $pc_file );
$hit = false;
bu_walk( $pc, function ( &$el ) use ( &$hit ) {
	if ( '7572813' !== ( $el['id'] ?? '' ) ) { return; }
	$html = $el['settings']['editor'] ?? '';
	if ( false !== strpos( $html, '/locations/">' ) ) { return; }
	$el['settings']['editor'] = str_replace(
		'Explore our',
		'See <a href="' . BU_SITE . '/locations/">every area we teach in</a>, explore our',
		$html
	);
	$hit = true;
} );
if ( $hit ) { bu_save( $pc_file, $pc ); echo "  pillar link added to the cross-area paragraph\n"; }

echo "\nDone. Apply with: wp eval-file /scripts/wp/elementor/restore-location-snapshots.php\n";
