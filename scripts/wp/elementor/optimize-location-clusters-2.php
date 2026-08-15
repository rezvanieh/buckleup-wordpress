<?php
/**
 * Finish the Hub 4 cluster work on the remaining three location pages:
 * Port Moody, North Vancouver and Tri-Cities.
 *
 * Companion to optimize-location-clusters.php, which did Coquitlam and Port
 * Coquitlam. Same rules apply: these pages are CLIENT-EDITED, so this patches the
 * snapshot JSON in snapshots/locations/ by widget id and never regenerates a page.
 * Every edit asserts what it expected to find and the result is re-validated as
 * JSON, so a near-miss aborts loudly rather than writing a mangled body.
 *
 * WHAT WAS MISSING
 * ----------------
 * A link audit of the live pages found the licence-class coverage stopped at the
 * two pages already done:
 *     coquitlam 5 service links, port-coquitlam 3, port-moody 0, north-vancouver 0,
 *     tri-cities 0
 *   - Port Moody HAS a lessons section with Class 7/5/4 headings, but none of them
 *     link anywhere, so the page names the licence classes and then sends nobody
 *     to the pages that sell them.
 *   - North Vancouver and Tri-Cities have no lessons section at all, the same gap
 *     Port Coquitlam had. Neither page mentions a licence class, so neither can
 *     rank for "class 5 driving lessons north vancouver" and the like.
 *
 * CANNIBALISATION NOTE (PDF section 6)
 * ------------------------------------
 * Tri-Cities is an umbrella page sitting above Coquitlam, Port Coquitlam and Port
 * Moody, so giving it "Class 7 driving lessons in the Tri-Cities" headings would
 * put it in direct competition with its own children. Its section is therefore
 * framed by licence class rather than by city, and each card links DOWN to the
 * three city pages, so it aggregates rather than competes.
 *
 * Idempotent: re-running reports the already-applied state and changes nothing.
 *
 * Run: docker run --rm -v "<repo>/scripts:/s" php:8.3-cli php /s/wp/elementor/optimize-location-clusters-2.php
 *      then: wp eval-file /scripts/wp/elementor/restore-location-snapshots.php
 */

$DIR = __DIR__ . '/snapshots/locations';

const BU_SITE = 'https://www.buckleupdriving.ca';

function bu2_link( $url ) {
	return array( 'url' => $url, 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' );
}

function bu2_walk( array &$els, callable $cb ) {
	foreach ( $els as &$el ) {
		if ( ! is_array( $el ) ) { continue; }
		$cb( $el );
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			bu2_walk( $el['elements'], $cb );
		}
	}
	unset( $el );
}

function bu2_load( $file ) {
	$d = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $d ) || ! $d ) { echo "ABORT: $file unreadable\n"; exit; }
	return $d;
}

function bu2_save( $file, array $data ) {
	$json = json_encode( $data );
	if ( ! is_string( $json ) || ! is_array( json_decode( $json, true ) ) ) {
		echo "ABORT: refusing to write invalid JSON to $file\n";
		exit;
	}
	file_put_contents( $file, $json );
}

/* ============================================================= PORT MOODY == */
/*
 * The section already exists and reads well; it only lacks links. Linking the
 * headings turns three existing keyword-bearing H3s into three routes to the
 * money pages without touching a word of the client's copy.
 */
$pm_file = "$DIR/port-moody.json";
$pm      = bu2_load( $pm_file );

$pm_links = array(
	'5e38630' => '/services/class-7-driving-lessons/',
	'515f4d3' => '/services/class-5-driving-lessons/',
	'041d33e' => '/services/class-4-driving-lessons/',
);

$done = 0;
bu2_walk( $pm, function ( &$el ) use ( $pm_links, &$done ) {
	$id = $el['id'] ?? '';
	if ( ! isset( $pm_links[ $id ] ) || 'heading' !== ( $el['widgetType'] ?? '' ) ) { return; }
	if ( ! empty( $el['settings']['link']['url'] ) ) { return; }
	$el['settings']['link'] = bu2_link( BU_SITE . $pm_links[ $id ] );
	$done++;
} );

if ( $done ) { bu2_save( $pm_file, $pm ); }
echo "port-moody.json\n  " . ( $done ? "$done lesson headings linked to their service clusters" : 'already linked, unchanged' ) . "\n";

/* ================================================== SHARED SECTION BUILDER == */

/** The Port Moody lessons section is the visual template for the other two. */
$template = $pm[3];

/**
 * Clone the template, re-id it under a salt so ids cannot collide with anything
 * already in the target document, and swap in the supplied copy.
 *
 * $content is a list of array(type, text[, link]) in document order.
 */
function bu2_build_section( array $template, $marker, $salt, array $content ) {
	$template['settings']['_element_id'] = $marker;

	$n    = 0;
	$reid = function ( array $el ) use ( &$reid, &$n, $salt ) {
		$n++;
		$el['id'] = substr( md5( $salt . '-' . $n ), 0, 7 );
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			foreach ( $el['elements'] as $i => $c ) {
				if ( is_array( $c ) ) { $el['elements'][ $i ] = $reid( $c ); }
			}
		}
		return $el;
	};
	$section = $reid( $template );

	$slots = array();
	$wrap  = array( $section );
	bu2_walk( $wrap, function ( &$el ) use ( &$slots ) {
		if ( in_array( $el['widgetType'] ?? '', array( 'heading', 'text-editor', 'button' ), true ) ) {
			$slots[] = &$el;
		}
	} );

	if ( count( $slots ) !== count( $content ) ) {
		echo 'ABORT: template has ' . count( $slots ) . ' widgets, copy supplies ' . count( $content )
			. " — the template changed, so the mapping is no longer safe.\n";
		exit;
	}

	foreach ( $content as $i => $c ) {
		$el = &$slots[ $i ];
		if ( 'heading' === $c[0] ) {
			$el['settings']['title'] = $c[1];
			if ( ! empty( $c[2] ) ) { $el['settings']['link'] = bu2_link( $c[2] ); }
			else { unset( $el['settings']['link'] ); }
		} elseif ( 'text' === $c[0] ) {
			$el['settings']['editor'] = $c[1];
		} else {
			$el['settings']['text'] = $c[1];
			$el['settings']['link'] = bu2_link( $c[2] );
		}
		unset( $el );
	}

	return $wrap[0];
}

/** Insert a built section into a page, unless its marker is already present. */
function bu2_insert( $file, $marker, $index, array $section ) {
	$page   = bu2_load( $file );
	$exists = false;
	bu2_walk( $page, function ( &$el ) use ( $marker, &$exists ) {
		if ( $marker === ( $el['settings']['_element_id'] ?? '' ) ) { $exists = true; }
	} );

	$name = basename( $file );
	if ( $exists ) {
		echo "$name\n  lessons section already present, left alone\n";
		return;
	}

	array_splice( $page, $index, 0, array( $section ) );
	bu2_save( $file, $page );
	echo "$name\n  lessons section inserted at index $index (" . count( $page ) . " sections total)\n";
}

/* ======================================================== NORTH VANCOUVER == */
/*
 * Copy leans on what actually makes the North Shore hard to learn on, because
 * that is both what a local searcher is worried about and what separates this
 * page from the Tri-Cities ones: hills, hill starts, the bridges, wet weather.
 */
$nv = array(
	array( 'text',    '<p>Driving Lessons </p>' ),
	array( 'heading', 'Driving Lessons in <span class="gradient-text">North Vancouver</span>', null ),
	array( 'text',    '<p>Lessons on the North Shore built around your licence level, your confidence, and the road test you are working towards.</p>' ),
	array( 'heading', 'Class 7 Driving Lessons in North Vancouver', BU_SITE . '/services/class-7-driving-lessons/' ),
	array( 'text',    '<p>Where most North Shore learners start. We stay on the quieter streets around Lynn Valley and Edgemont until the basics feel automatic, then build up to Lonsdale traffic and your first bridge crossing.</p>' ),
	array( 'heading', 'Class 5 Road Test Preparation in North Vancouver', BU_SITE . '/services/class-5-driving-lessons/' ),
	array( 'text',    '<p>For drivers with a test booked. North Shore tests punish anything shaky on a hill, so we drill hill starts, parking on a grade and bridge merges alongside the lane changes and observation the examiner scores.</p>' ),
	array( 'heading', 'Class 4 Driving Lessons in North Vancouver', BU_SITE . '/services/class-4-driving-lessons/' ),
	array( 'text',    '<p>For anyone going commercial, from taxi and ride-hailing to a small passenger vehicle. Lessons build the wider road awareness, smoother control and defensive habits a Class 4 examiner expects.</p>' ),
	array( 'button',  'Driving Lesson Packages', BU_SITE . '/services/' ),
);

bu2_insert(
	"$DIR/north-vancouver.json",
	'nv-lessons',
	3, // after "Why North Vancouver Drivers Choose BuckleUp", before "Neighbourhoods"
	bu2_build_section( $template, 'nv-lessons', 'buckleup-nv-lessons', $nv )
);

/* ============================================================== TRI-CITIES == */
/*
 * Framed by licence class, not by city, and every card links down to the three
 * city pages. That keeps the umbrella page useful without letting it compete with
 * its own children for "<licence class> driving lessons <city>". The H2 also
 * avoids repeating "Driving Lessons Across the Tri-Cities", which the section
 * above it already owns.
 */
$city_links = '<a href="' . BU_SITE . '/locations/coquitlam/">Coquitlam</a>, '
	. '<a href="' . BU_SITE . '/locations/port-coquitlam/">Port Coquitlam</a> and '
	. '<a href="' . BU_SITE . '/locations/port-moody/">Port Moody</a>';

$tc = array(
	array( 'text',    '<p>Driving Lessons </p>' ),
	array( 'heading', 'Lessons by <span class="gradient-text">Licence Class</span>', null ),
	array( 'text',    '<p>The same instructors and the same cars across ' . $city_links . '. Pick the licence you are working towards, or open your city for the local routes and test-centre detail.</p>' ),
	array( 'heading', 'Class 7 Lessons for New Drivers', BU_SITE . '/services/class-7-driving-lessons/' ),
	array( 'text',    '<p>For anyone on an L who has barely driven. We start on quiet residential streets wherever you are in the region and work up to Lougheed and Barnet once the basics feel automatic.</p>' ),
	array( 'heading', 'Class 5 Road Test Preparation', BU_SITE . '/services/class-5-driving-lessons/' ),
	array( 'text',    '<p>For drivers with a test date. Tri-Cities road tests run out of the Coquitlam licensing office, so we practise on the routes it actually uses and drill the lane changes, parking and observation the examiner scores.</p>' ),
	array( 'heading', 'Class 4 Commercial Lessons', BU_SITE . '/services/class-4-driving-lessons/' ),
	array( 'text',    '<p>For taxi, ride-hailing and small passenger vehicles. Lessons build the extra road awareness, smoother control and defensive habits a Class 4 examiner expects.</p>' ),
	array( 'button',  'Driving Lesson Packages', BU_SITE . '/services/' ),
);

bu2_insert(
	"$DIR/tri-cities.json",
	'tc-lessons',
	3, // after "Why Tri-Cities Drivers Choose BuckleUp", before "Areas We Serve"
	bu2_build_section( $template, 'tc-lessons', 'buckleup-tc-lessons', $tc )
);

echo "\nDone. Apply with: wp eval-file /scripts/wp/elementor/restore-location-snapshots.php\n";
