<?php
/**
 * Correct the Google rating claims to the real numbers, and remove the last
 * "we pick you up" claim.
 *
 * THE NUMBERS
 * -----------
 * Confirmed 2026-08-15 from the client's own Google Business Profile:
 *
 *     5.0 stars, 33 Google reviews
 *
 * The site was carrying two different wrong figures at once:
 *   - Visible copy said "4.98" and "Based on 200+ reviews" (hero cards, the
 *     instructors and services stat rows, and ten location meta descriptions).
 *   - The AggregateRating schema said ratingValue 5.0 with reviewCount 200.
 *
 * So the star rating was understated and the review count was overstated by
 * roughly 6x. "200+ reviews" against a real 33 is the serious one: it is a
 * measurable false claim about the business, it appears on every page, and in
 * schema it is the kind of thing Google penalises rich-result eligibility for.
 *
 * THE PICKUP CLAIM
 * ----------------
 * The sweep on 2026-08-14 removed the "pickup / drop-off" wording but missed
 * "We can pick you up from home, work, school, or a SkyTrain station", because
 * the pattern it searched for ("pick up", "pick-up", "pickup") does not match a
 * phrase with a pronoun in the middle. That sentence is in the Coquitlam FAQ,
 * which is emitted as FAQPage JSON-LD, so it is a false claim eligible to be
 * shown directly in Google's results. A second instance sits in the Tri-Cities
 * copy in this same file.
 *
 * Run (plain PHP, no WordPress needed - it only rewrites files):
 *   docker run --rm -v "<repo>:/r" php:8.3-cli php /r/scripts/wp/fix-rating-claims.php
 */

$ROOT = dirname( __DIR__, 2 );

/** file => list of [from, to, expected-count or null for "any"] */
$EDITS = array(

	/* ------------------------------------------------ the pickup claims -- */

	'scripts/wp/elementor/locations-content.php' => array(
		array( ' We can pick you up from home, work, school, or a SkyTrain station.', '', 1 ),
		array(
			'Lessons are available in English and Farsi, and we pick you up anywhere across Coquitlam, Port Coquitlam, and Port Moody.',
			'Lessons are available in English and Farsi right across Coquitlam, Port Coquitlam and Port Moody.',
			1,
		),
	),
	'docker/wordpress/mu-plugins/10-buckleup-seo.php' => array(
		array( ' We can pick you up from home, work, school, or a SkyTrain station.', '', 1 ),
		// Schema defaults: these are the fallback when the settings option is unset.
		array( "'rating_value'   => '4.98',", "'rating_value'   => '5.0',", 1 ),
		array( "'review_count'   => '200',", "'review_count'   => '33',", 1 ),
	),

	/* ------------------------------------------------ the rating claims -- */

	'wp-content/themes/buckleup/inc/site.php' => array(
		array( "'rating_value'      => '4.98',", "'rating_value'      => '5.0',", 1 ),
		array( "'rating_caption'    => __( 'Based on 200+ reviews', 'buckleup' ),", "'rating_caption'    => __( 'Based on 33 Google reviews', 'buckleup' ),", 1 ),
		array( '4.98/200+ rating card', '5.0 / 33-review rating card', null ),
		array( "floating 4.98/200+ rating card", 'floating 5.0 / 33-review rating card', null ),
		array( "(e.g. '4.98')", "(e.g. '5.0')", null ),
	),
	'wp-content/themes/buckleup/inc/elementor-widgets/class-buckleup-hero-widget.php' => array(
		array( "'default' => '4.98',", "'default' => '5.0',", 1 ),
		array( "'default'     => __( 'Based on 200+ reviews', 'buckleup'", "'default'     => __( 'Based on 33 Google reviews', 'buckleup'", 1 ),
	),
	'wp-content/themes/buckleup/patterns/home-hero.php' => array(
		array( 'the floating 4.98 / "Based on 200+ reviews" card', 'the floating 5.0 / "Based on 33 Google reviews" card', null ),
	),
	'wp-content/themes/buckleup/patterns/location-hero.php' => array(
		array( '4.98/200+ rating card', '5.0 / 33-review rating card', null ),
	),
	'wp-content/themes/buckleup/patterns/page-instructors.php' => array(
		array( "array( 'value' => '4.98★', 'label' => __( 'Google rating', 'buckleup' ) ),", "array( 'value' => '5.0★', 'label' => __( 'Google rating', 'buckleup' ) ),", 1 ),
		array( 'the 4.8 contradicted the 4.98 shown everywhere else', 'the 4.8 contradicted the rating shown everywhere else', null ),
	),
	'wp-content/themes/buckleup/patterns/page-services.php' => array(
		array( "array( 'value' => '4.98★', 'label' => __( 'Google rating', 'buckleup' ),", "array( 'value' => '5.0★', 'label' => __( 'Google rating', 'buckleup' ),", 1 ),
	),
	'scripts/wp/elementor/build-home.php' => array(
		array( "el_heading( '4.98',", "el_heading( '5.0',", 1 ),
		array( "el_text( 'Based on 200+ reviews',", "el_text( 'Based on 33 Google reviews',", 1 ),
	),
	'scripts/wp/elementor/build-pages.php' => array(
		array( "array( '4.98★', 'Google rating' ),", "array( '5.0★', 'Google rating' ),", 1 ),
		array( 'the 4.8 contradicted the 4.98 shown everywhere else', 'the 4.8 contradicted the rating shown everywhere else', null ),
	),
);

/* Bulk phrases that recur across locations-content.php and the snapshots. */
$BULK = array(
	'With a 4.98★ rating from 200+ Google reviews' => 'With a 5.0★ rating from 33 Google reviews',
	'Backed by a 4.98★ rating from 200+ Google reviews' => 'Backed by a 5.0★ rating from 33 Google reviews',
	"array( 'value' => '4.98★', 'label' => '200+ Google reviews' )" => "array( 'value' => '5.0★', 'label' => '33 Google reviews' )",
	'rated 4.98★'  => 'rated 5.0★',
	'Rated 4.98★'  => 'Rated 5.0★',
	'instructors rated 4.98'  => 'instructors rated 5.0',
	'4.98★ on 200+ reviews'   => '5.0★ from 33 reviews',
	'200+ Google reviews'     => '33 Google reviews',
	'200+ reviews'            => '33 Google reviews',
	'"4.98"'                  => '"5.0"',
	'4.98★'                   => '5.0★',
);

$BULK_FILES = array(
	'scripts/wp/elementor/locations-content.php',
	'scripts/wp/elementor/snapshots/locations/manifest.json',
	'scripts/wp/elementor/snapshots/pages/manifest.json',
	'scripts/wp/elementor/snapshots/pages/instructors.json',
);

/* ------------------------------------------------------------------- run -- */

$changed = 0;
$problem = 0;

foreach ( $EDITS as $rel => $pairs ) {
	$file = "$ROOT/$rel";
	if ( ! is_readable( $file ) ) { echo "  MISSING $rel\n"; $problem++; continue; }
	$s = $o = file_get_contents( $file );
	foreach ( $pairs as $p ) {
		list( $from, $to, $want ) = $p;
		$n = substr_count( $s, $from );
		if ( null !== $want && $n !== $want ) {
			printf( "  !! %s: expected %d match(es), found %d - %s\n", basename( $rel ), $want, $n, substr( $from, 0, 55 ) );
			$problem++;
			continue;
		}
		if ( $n ) { $s = str_replace( $from, $to, $s ); }
	}
	if ( $s !== $o ) { file_put_contents( $file, $s ); echo "  updated $rel\n"; $changed++; }
}

foreach ( $BULK_FILES as $rel ) {
	$file = "$ROOT/$rel";
	if ( ! is_readable( $file ) ) { echo "  MISSING $rel\n"; $problem++; continue; }
	$s = $o = file_get_contents( $file );
	$hits = 0;
	foreach ( $BULK as $from => $to ) {
		$n = substr_count( $s, $from );
		if ( $n ) { $s = str_replace( $from, $to, $s ); $hits += $n; }
	}
	if ( $s === $o ) { continue; }
	// Snapshots must still parse; a mangled body would blank a live page.
	if ( '.json' === substr( $rel, -5 ) && ! is_array( json_decode( $s, true ) ) ) {
		echo "  ABORT $rel: result is not valid JSON, left untouched\n";
		$problem++;
		continue;
	}
	file_put_contents( $file, $s );
	printf( "  updated %s (%d replacement%s)\n", $rel, $hits, 1 === $hits ? '' : 's' );
	$changed++;
}

echo "\n$changed file(s) updated" . ( $problem ? ", $problem problem(s)" : '' ) . ".\n";

/* Anything left anywhere? */
$left = array();
$scan = array( 'scripts/wp', 'wp-content/themes/buckleup', 'docker/wordpress/mu-plugins' );
foreach ( $scan as $dir ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$ROOT/$dir", FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( ! $f->isFile() ) { continue; }
		$ext = strtolower( $f->getExtension() );
		if ( ! in_array( $ext, array( 'php', 'json', 'html', 'js', 'css' ), true ) ) { continue; }
		$c = file_get_contents( $f->getPathname() );
		if ( preg_match( '/4\.98|200\+ ?(Google )?reviews|pick (you|them) up/i', $c, $m ) ) {
			$left[] = str_replace( "$ROOT/", '', $f->getPathname() ) . '  (' . $m[0] . ')';
		}
	}
}
if ( $left ) {
	echo "\nSTILL PRESENT - review these:\n";
	foreach ( array_unique( $left ) as $l ) { echo "  - $l\n"; }
} else {
	echo "No occurrences of the old rating or the pickup phrasing remain in the repo.\n";
}
