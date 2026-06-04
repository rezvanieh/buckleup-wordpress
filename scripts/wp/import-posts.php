<?php
/**
 * Imports the 5 SEO blog posts from prisma/seed.ts as native WordPress posts.
 * Preserves slug, category, tags, excerpt, and HTML body verbatim. Relative
 * publish dates mirror the source (newest first, oldest 3 days back).
 *
 * The source posts contain no inline <img>/featured images (only <a> links), so
 * there are no images to side-load here; if a post body later gains an <img>,
 * extend with media_sideload_image + URL rewrite.
 *
 * Idempotent: upsert by slug. Category/tags are (re)assigned each run.
 * Run via: wp eval-file /scripts/wp/import-posts.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/lib.php';

$posts = array(
	array(
		'title'    => 'How to Pass Your ICBC Class 5 Road Test in Vancouver',
		'slug'     => 'how-to-pass-icbc-class-5-road-test-vancouver',
		'excerpt'  => 'A comprehensive guide on what examiners look for during the ICBC Class 5 road test in Greater Vancouver, complete with tips and common mistakes.',
		'category' => 'Tips',
		'tags'     => array( 'ICBC', 'Class 5', 'Vancouver', 'Road Test' ),
		'date'     => '2026-02-24',
		'content'  => '<h1>How to Pass Your ICBC Class 5 Road Test in Vancouver</h1>
<p>The Class 5 road test is the final hurdle to getting your full privilege driver\'s licence in British Columbia. Unlike the Class 7 (N) test which focuses heavily on basic vehicle control, the Class 5 test evaluates how you handle complex traffic situations, hazard perception, and overall driving maturity.</p>
<h2>Top 3 Reasons People Fail in Vancouver</h2>
<ul>
<li><strong>Speed Maintenance in School Zones:</strong> Vancouver has numerous school and playground zones. Going even 1 km/h over the 30 km/h limit is an automatic fail.</li>
<li><strong>Incomplete Shoulder Checks:</strong> Always shoulder check when changing lanes, turning right or left, and pulling away from the curb.</li>
<li><strong>Rolling Stops:</strong> You must come to a complete, full stop behind the white line at stop signs.</li>
</ul>
<blockquote>"The key to passing is showing the examiner you are a safe, confident, and predictive driver. You aren\'t just reacting to the road; you are anticipating it." - Lead Instructor at BuckleUp</blockquote>
<h2>Preparation is Key</h2>
<p>Before your test, familiarize yourself with the testing area. Locations like Port Moody and North Vancouver have unique geographical challenges (like steep hills and narrow residential streets). Make sure you understand how to park on a hill with and without a curb.</p>
<p>Ready to get your license? <a href="/services">Book a mock test with us today</a>.</p>',
	),
	array(
		'title'    => 'Mastering Parallel Parking: The Ultimate Guide',
		'slug'     => 'mastering-parallel-parking-ultimate-guide',
		'excerpt'  => 'Struggling with parallel parking? Follow our step-by-step mathematical approach to perfectly parallel park every single time.',
		'category' => 'Tutorials',
		'tags'     => array( 'Parking', 'Driving Skills', 'Tutorial' ),
		'date'     => '2026-02-24',
		'content'  => '<h1>Mastering Parallel Parking: The Ultimate Guide</h1>
<p>Parallel parking is often the most dreaded maneuver for new drivers. However, taking the guesswork out of it and relying on reference points makes it simple.</p>
<h2>The 4-Step Formula</h2>
<ol>
<li><strong>Positioning:</strong> Pull up parallel to the car you want to park behind. Keep about 1 meter of space between your car and theirs. Align your rear bumper with theirs.</li>
<li><strong>The First Turn:</strong> Check your mirrors and shoulder check. Put the car in reverse. Turn the steering wheel one full rotation towards the curb. Back up until your car is at a 45-degree angle.</li>
<li><strong>Straighten the Wheel:</strong> Straighten the wheel and back up slowly until your front bumper clears their rear bumper.</li>
<li><strong>The Final Turn:</strong> Turn the wheel all the way to the left (away from the curb) and glide back into the spot. Straighten out.</li>
</ol>
<h2>Common Mistakes</h2>
<ul>
<li>Turning the wheel while the car is stationary (dry steering).</li>
<li>Forgetting to signal or shoulder check before beginning the maneuver.</li>
<li>Rushing. Take your time.</li>
</ul>',
	),
	array(
		'title'    => 'Winter Driving in BC: Essential Safety Tips',
		'slug'     => 'winter-driving-bc-essential-safety-tips',
		'excerpt'  => 'Don\'t let black ice or heavy rain surprise you. Learn how to navigate British Columbia\'s unpredictable winter roads safely with these expert tips.',
		'category' => 'Safety',
		'tags'     => array( 'Winter Driving', 'Safety', 'Weather' ),
		'date'     => '2026-02-23',
		'content'  => '<h1>Winter Driving in BC: Essential Safety Tips</h1>
<p>Winter in Greater Vancouver might not mean constant snow, but it does mean heavy rain, fog, and the dreaded black ice. Driving conditions change rapidly, and your driving habits need to adjust accordingly.</p>
<h2>1. Tires are Everything</h2>
<p>In BC, winter tires or mud and snow (M+S) tires are legally required on specific highways from October 1st to March 31st. Even if you stay in the city, winter tires provide significantly better grip in cold temperatures than all-seasons.</p>
<h2>2. Double Your Following Distance</h2>
<p>On a clear day, the rule is 2-3 seconds of following distance. In rain or snow, increase this to 4-6 seconds. Braking distances increase exponentially on wet or icy roads.</p>
<h2>3. Smooth Inputs</h2>
<p>Harsh braking, aggressive acceleration, and sharp steering are the quickest ways to lose traction. In poor weather, pretend there\'s an egg under your pedals. Press them gently and smoothly.</p>
<blockquote>"To recover from a skid, take your foot off the gas and steer smoothly in the direction you want the front of the vehicle to go."</blockquote>',
	),
	array(
		'title'    => 'Why Port Moody is the Best Place to Learn to Drive',
		'slug'     => 'why-port-moody-best-place-learn-to-drive',
		'excerpt'  => 'Discover why Port Moody offers the perfect mix of residential calmness and complex traffic scenarios for beginner drivers.',
		'category' => 'Local',
		'tags'     => array( 'Port Moody', 'Locations', 'Learning' ),
		'date'     => '2026-02-22',
		'content'  => '<h1>Why Port Moody is the Best Place to Learn to Drive</h1>
<p>We\'ve found that learning to drive in Port Moody gives students a strategic advantage. It combines quiet, forgiving environments with complex, real-world challenges.</p>
<h2>The Ideal Progression</h2>
<ul>
<li><strong>Quiet Suburbs (Heritage Woods/Heritage Mountain):</strong> Perfect for those first two lessons. Wide streets and very little traffic allow students to focus purely on vehicle control without feeling overwhelmed.</li>
<li><strong>The Challenge of St. Johns Street:</strong> Once basic control is established, St. Johns Street provides heavy traffic, transit buses, multiple lanes, and complex intersections. It forces students to scan aggressively and make quick decisions.</li>
<li><strong>Hill Parking:</strong> Port Moody is notoriously hilly, offering natural practice areas for the uphill and downhill parking techniques required on the ICBC test.</li>
</ul>
<p>Check out our <a href="/locations/port-moody">Port Moody Driving Lessons</a> specifically tailored for this area.</p>',
	),
	array(
		'title'    => 'The Ultimate Highway Merging Checklist',
		'slug'     => 'ultimate-highway-merging-checklist',
		'excerpt'  => 'Merging onto Highway 1 in rush hour can be terrifying. Use our simple checklist to merge safely, quickly, and confidently.',
		'category' => 'Tutorials',
		'tags'     => array( 'Highway', 'Merging', 'Tutorial' ),
		'date'     => '2026-02-21',
		'content'  => '<h1>The Ultimate Highway Merging Checklist</h1>
<p>Merging onto a highway is all about matching speed and communicating intent. Many new drivers slow down at the end of the merge lane out of fear, which actually makes merging more dangerous.</p>
<h2>The Merging Blueprint</h2>
<ol>
<li><strong>Accelerate:</strong> The acceleration lane is exactly that—for accelerating. Do not brake. Use this lane to get up to highway speed (usually 80-90 km/h) so you can seamlessly glide into traffic.</li>
<li><strong>Signal Early:</strong> Turn your left blinker on as soon as you are on the straight part of the acceleration lane. This tells drivers you want in.</li>
<li><strong>Mirror, Shoulder Check, Mirror:</strong> Scan your side mirror, look over your left shoulder into your blind spot to find your gap, and check your mirror again to ensure the gap isn\'t closing.</li>
<li><strong>Commit and Glide:</strong> Once you establish your gap, maintain your speed and glide into the lane. Do not jerk the steering wheel.</li>
</ol>
<p>Need highway practice? Our advanced refresher courses cover highway driving extensively. <a href="/contact">Contact us</a>.</p>',
	),
);

// Byline author: the admin user (production shows "Admin User" on single posts).
$author_id = bu_post_author_id();

foreach ( $posts as $p ) {
	// Fixed publish date matching production (the live site's originals), kept in
	// local time so the blog ordering + displayed dates match exactly.
	$date_local = $p['date'] . ' 09:00:00';

	$existing = bu_find_post( 'post', $p['slug'], $p['title'] );
	$data = array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_name'    => $p['slug'],
		'post_title'   => $p['title'],
		'post_excerpt' => $p['excerpt'],
		// Strip the leading <h1> (duplicates the title; theme renders title as H1).
		'post_content' => bu_strip_leading_h1( $p['content'] ),
		'post_author'  => $author_id,
		'post_date'    => $date_local,
		'post_date_gmt'=> get_gmt_from_date( $date_local ),
	);
	if ( $existing ) { $data['ID'] = $existing; }

	$id = wp_insert_post( wp_slash( $data ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "  post '{$p['slug']}': " . $id->get_error_message() );
		continue;
	}

	// Single free-text category mapped to a WP category term.
	$cat_id = bu_ensure_category( $p['category'] );
	if ( $cat_id ) { wp_set_post_categories( $id, array( $cat_id ), false ); }

	// Tag array -> post_tag terms.
	wp_set_post_tags( $id, $p['tags'], false );

	$verb = $existing ? 'updated' : 'created';
	WP_CLI::log( "  post {$verb}: {$p['slug']} (#{$id}) [{$p['category']}]" );
}

WP_CLI::success( 'Imported 5 blog posts (slugs/categories/tags/HTML preserved).' );
