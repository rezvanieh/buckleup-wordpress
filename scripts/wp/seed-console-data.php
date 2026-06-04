<?php
/**
 * Seeds the consoles' demo DATA into the buckleup-app bu_* tables (schemas in
 * buckleup-app/includes/tables.php) so every console page shows real content:
 *   - bu_bookings              PENDING + CONFIRMED (future) + COMPLETED (past)
 *   - bu_availability          instructor weekly recurring (Mon–Fri 09:00–17:00)
 *   - bu_availability_exceptions  one day-off + one custom-hours date
 *   - bu_lesson_progress       skills JSON on a COMPLETED booking
 *   - bu_reviews               one approved+public, one pending
 *
 * Relationship: student Sam (student@buckleup.test) ↔ instructor Farhad
 * (instructor@buckleup.test); service_id = a real `service` CPT post.
 *
 * Idempotent: rows are keyed on stable natural keys (student+instructor+datetime
 * for bookings; instructor+day for availability; instructor+date for exceptions;
 * booking_id for progress; student+rating+approved for reviews) and upserted, so
 * a re-run updates in place instead of duplicating.
 *
 * Run via: wp eval-file /scripts/wp/seed-console-data.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/lib.php';

global $wpdb;

if ( ! function_exists( 'buckleup_app_table' ) ) {
	WP_CLI::warning( 'buckleup-app not active (no buckleup_app_table) — skipping console demo data.' );
	return;
}
$t_book = buckleup_app_table( 'bookings' );
$t_avail = buckleup_app_table( 'availability' );
$t_exc  = buckleup_app_table( 'availability_exceptions' );
$t_prog = buckleup_app_table( 'lesson_progress' );
$t_rev  = buckleup_app_table( 'reviews' );

// Bail (don't fatal) if the tables aren't created yet.
foreach ( array( $t_book, $t_avail, $t_exc, $t_prog, $t_rev ) as $tbl ) {
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
		WP_CLI::warning( "table {$tbl} missing — activate buckleup-app first. Skipping demo data." );
		return;
	}
}

// Actors + a real service.
$student = get_user_by( 'email', 'student@buckleup.test' );
$instr   = get_user_by( 'email', 'instructor@buckleup.test' );
if ( ! $student || ! $instr ) {
	WP_CLI::warning( 'demo users missing — run seed-console-users-pages.php first. Skipping demo data.' );
	return;
}
$sid = (int) $student->ID;
$iid = (int) $instr->ID;

$svc = get_posts( array( 'post_type' => 'service', 'posts_per_page' => 1, 'fields' => 'ids', 'orderby' => 'menu_order', 'order' => 'ASC' ) );
$service_id = $svc ? (int) $svc[0] : 0;
if ( ! $service_id ) {
	WP_CLI::warning( 'no `service` CPT post — run seed-catalog.php first. Skipping demo data.' );
	return;
}

$now = current_time( 'timestamp' );
$fmt = 'Y-m-d H:i:s';

/* ---------------------------------------------------------------------------
 * BOOKINGS — upsert by (student, instructor, datetime).
 * ------------------------------------------------------------------------- */
// Upsert keyed on the (student, instructor, notes) — `notes` is a stable
// per-booking marker (each demo booking has a unique note), so re-runs match
// even though the datetime is recomputed relative to "now" each run (to keep
// past/future correct over time). Without this, relative datetimes would never
// match on re-run and the seed would duplicate.
function bu_upsert_booking( $wpdb, $t_book, $row ) {
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$t_book} WHERE student_id=%d AND instructor_id=%d AND notes=%s",
		$row['student_id'], $row['instructor_id'], $row['notes']
	) );
	if ( $existing ) {
		$wpdb->update( $t_book, $row, array( 'id' => $existing ) );
		return (int) $existing;
	}
	$wpdb->insert( $t_book, $row );
	return (int) $wpdb->insert_id;
}

$bookings = array(
	// COMPLETED (past) — 2
	array( 'datetime' => gmdate( $fmt, $now - 14 * DAY_IN_SECONDS ), 'status' => 'COMPLETED', 'pickup_addr' => '136 Maple Dr, Port Moody', 'notes' => 'First lesson — vehicle controls.' ),
	array( 'datetime' => gmdate( $fmt, $now - 7 * DAY_IN_SECONDS ),  'status' => 'COMPLETED', 'pickup_addr' => '136 Maple Dr, Port Moody', 'notes' => 'Parking + lane changes.' ),
	// CONFIRMED (future) — 2
	array( 'datetime' => gmdate( $fmt, $now + 2 * DAY_IN_SECONDS ),  'status' => 'CONFIRMED', 'pickup_addr' => 'Coquitlam Centre', 'notes' => 'Highway merging practice.' ),
	array( 'datetime' => gmdate( $fmt, $now + 5 * DAY_IN_SECONDS ),  'status' => 'CONFIRMED', 'pickup_addr' => 'Lougheed SkyTrain', 'notes' => 'Road-test route rehearsal.' ),
	// PENDING (future) — 1
	array( 'datetime' => gmdate( $fmt, $now + 9 * DAY_IN_SECONDS ),  'status' => 'PENDING',   'pickup_addr' => 'Port Moody Rec Centre', 'notes' => 'Mock road test.' ),
);
$booking_ids = array();
foreach ( $bookings as $b ) {
	$row = array(
		'student_id'    => $sid,
		'instructor_id' => $iid,
		'service_id'    => $service_id,
		'datetime'      => $b['datetime'],
		'duration'      => 90,
		'status'        => $b['status'],
		'pickup_addr'   => $b['pickup_addr'],
		'notes'         => $b['notes'],
	);
	$booking_ids[] = bu_upsert_booking( $wpdb, $t_book, $row );
}
WP_CLI::log( '  bookings: ' . count( $booking_ids ) . ' (2 COMPLETED, 2 CONFIRMED, 1 PENDING)' );

/* ---------------------------------------------------------------------------
 * AVAILABILITY — instructor Mon–Fri 09:00–17:00 recurring. Upsert by (instr,day).
 * ------------------------------------------------------------------------- */
$avail_n = 0;
for ( $dow = 1; $dow <= 5; $dow++ ) { // 1=Mon … 5=Fri
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$t_avail} WHERE instructor_id=%d AND day_of_week=%d", $iid, $dow
	) );
	$row = array( 'instructor_id' => $iid, 'day_of_week' => $dow, 'start_time' => '09:00', 'end_time' => '17:00', 'is_recurring' => 1 );
	if ( $existing ) { $wpdb->update( $t_avail, $row, array( 'id' => $existing ) ); }
	else { $wpdb->insert( $t_avail, $row ); }
	$avail_n++;
}
WP_CLI::log( "  availability: {$avail_n} weekly rows (Mon–Fri 09:00–17:00)" );

/* ---------------------------------------------------------------------------
 * EXCEPTIONS — one day-off + one custom-hours date. Upsert by (instr,date).
 * ------------------------------------------------------------------------- */
$exceptions = array(
	array( 'date' => gmdate( 'Y-m-d', $now + 10 * DAY_IN_SECONDS ), 'is_available' => 0, 'start_time' => null,    'end_time' => null,    'reason' => 'Statutory holiday — closed.' ),
	array( 'date' => gmdate( 'Y-m-d', $now + 12 * DAY_IN_SECONDS ), 'is_available' => 1, 'start_time' => '12:00', 'end_time' => '16:00', 'reason' => 'Afternoon only.' ),
);
foreach ( $exceptions as $e ) {
	// Key on (instructor, reason) — reason is a stable marker; the date is
	// recomputed relative to now each run, so date-keying would duplicate.
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$t_exc} WHERE instructor_id=%d AND reason=%s", $iid, $e['reason']
	) );
	$row = array_merge( array( 'instructor_id' => $iid ), $e );
	if ( $existing ) { $wpdb->update( $t_exc, $row, array( 'id' => $existing ) ); }
	else { $wpdb->insert( $t_exc, $row ); }
}
WP_CLI::log( '  exceptions: 2 (1 day-off, 1 custom-hours)' );

/* ---------------------------------------------------------------------------
 * LESSON PROGRESS — on the first COMPLETED booking. Upsert by booking_id (UNIQUE).
 * ------------------------------------------------------------------------- */
$completed_booking = $booking_ids[0]; // first COMPLETED
$skills = wp_json_encode( array(
	'Vehicle Controls'        => 5,
	'Steering & Lane Position'=> 4,
	'Speed Management'        => 4,
	'Intersections'          => 3,
	'Parallel Parking'       => 2,
) );
$prog_row = array(
	'booking_id'       => $completed_booking,
	'student_id'       => $sid,
	'skills'           => $skills,
	'notes'            => 'Good progress on vehicle control; keep practising parallel parking.',
	'instructor_notes' => 'Confident with basic controls. Next: hill parking + parallel parking reps.',
);
$existing_prog = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_prog} WHERE booking_id=%d", $completed_booking ) );
if ( $existing_prog ) { $wpdb->update( $t_prog, $prog_row, array( 'id' => $existing_prog ) ); }
else { $wpdb->insert( $t_prog, $prog_row ); }
WP_CLI::log( '  lesson_progress: 1 (skills JSON on a COMPLETED booking)' );

/* ---------------------------------------------------------------------------
 * REVIEWS — 1 approved+public, 1 pending. Upsert by (student, rating, approved).
 * ------------------------------------------------------------------------- */
$reviews = array(
	array( 'rating' => 5, 'comment' => 'Farhad is incredibly patient and clear. I went from nervous to confident in a handful of lessons — passed with zero demerits!', 'is_public' => 1, 'is_approved' => 1 ),
	array( 'rating' => 4, 'comment' => 'Great instructor, flexible scheduling. Looking forward to the road-test prep sessions.', 'is_public' => 1, 'is_approved' => 0 ),
);
foreach ( $reviews as $r ) {
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$t_rev} WHERE student_id=%d AND rating=%d AND is_approved=%d",
		$sid, $r['rating'], $r['is_approved']
	) );
	$row = array_merge( array( 'student_id' => $sid, 'instructor_id' => $iid ), $r );
	if ( $existing ) { $wpdb->update( $t_rev, $row, array( 'id' => $existing ) ); }
	else { $wpdb->insert( $t_rev, $row ); }
}
WP_CLI::log( '  reviews: 2 (1 approved+public, 1 pending)' );

WP_CLI::success( 'Console demo data seeded into the bu_* tables.' );
