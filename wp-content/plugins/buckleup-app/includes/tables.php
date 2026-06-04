<?php
/**
 * Custom database tables for the application backend.
 *
 * Created/upgraded with dbDelta (idempotent) and versioned via the
 * `buckleup_app_db_version` option. Field shapes mirror prisma/schema.prisma
 * (Booking, Availability, AvailabilityException, LessonProgress, Review) with
 * WordPress conventions: BIGINT user/post ids instead of cuid strings.
 *
 * Relationship mapping vs the source:
 *   - Prisma Student.id / Instructor.id  → the WP user ID (we don't keep a
 *     separate profile-row id; a "student" / "instructor" is a WP user with the
 *     matching role + profile user-meta).
 *   - service_id → a `service` CPT post ID (from buckleup-core).
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fully-qualified table name for a logical table key.
 *
 * @param string $key One of: bookings, availability, availability_exceptions,
 *                    lesson_progress, reviews.
 * @return string
 */
function buckleup_app_table( $key ) {
	global $wpdb;
	return $wpdb->prefix . 'bu_' . $key;
}

/**
 * Create or upgrade all custom tables. Safe to call repeatedly (dbDelta).
 */
function buckleup_app_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$bookings        = buckleup_app_table( 'bookings' );
	$availability    = buckleup_app_table( 'availability' );
	$exceptions      = buckleup_app_table( 'availability_exceptions' );
	$progress        = buckleup_app_table( 'lesson_progress' );
	$reviews         = buckleup_app_table( 'reviews' );

	// bu_bookings — one row per booking.
	$sql_bookings = "CREATE TABLE {$bookings} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		student_id BIGINT UNSIGNED NOT NULL,
		instructor_id BIGINT UNSIGNED NOT NULL,
		service_id BIGINT UNSIGNED NOT NULL,
		datetime DATETIME NOT NULL,
		duration INT NOT NULL DEFAULT 60,
		status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
		pickup_addr VARCHAR(255) NULL,
		notes TEXT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY student_id (student_id),
		KEY instructor_id (instructor_id),
		KEY datetime (datetime),
		KEY status (status)
	) {$charset_collate};";

	// bu_availability — instructor weekly recurring hours.
	$sql_availability = "CREATE TABLE {$availability} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		instructor_id BIGINT UNSIGNED NOT NULL,
		day_of_week TINYINT NOT NULL,
		start_time VARCHAR(5) NOT NULL,
		end_time VARCHAR(5) NOT NULL,
		is_recurring TINYINT(1) NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		KEY instructor_id (instructor_id),
		KEY instructor_day (instructor_id, day_of_week)
	) {$charset_collate};";

	// bu_availability_exceptions — per-date overrides; UNIQUE(instructor,date).
	$sql_exceptions = "CREATE TABLE {$exceptions} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		instructor_id BIGINT UNSIGNED NOT NULL,
		date DATE NOT NULL,
		is_available TINYINT(1) NOT NULL DEFAULT 0,
		start_time VARCHAR(5) NULL,
		end_time VARCHAR(5) NULL,
		reason VARCHAR(255) NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY instructor_date (instructor_id, date)
	) {$charset_collate};";

	// bu_lesson_progress — one row per completed booking; skills = JSON.
	$sql_progress = "CREATE TABLE {$progress} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		booking_id BIGINT UNSIGNED NOT NULL,
		student_id BIGINT UNSIGNED NOT NULL,
		skills LONGTEXT NULL,
		notes TEXT NULL,
		instructor_notes TEXT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY booking_id (booking_id),
		KEY student_id (student_id)
	) {$charset_collate};";

	// bu_reviews — student reviews; moderated via is_approved.
	$sql_reviews = "CREATE TABLE {$reviews} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		student_id BIGINT UNSIGNED NOT NULL,
		instructor_id BIGINT UNSIGNED NULL,
		rating TINYINT NOT NULL,
		comment TEXT NULL,
		is_public TINYINT(1) NOT NULL DEFAULT 0,
		is_approved TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY student_id (student_id),
		KEY instructor_id (instructor_id),
		KEY is_approved (is_approved)
	) {$charset_collate};";

	dbDelta( $sql_bookings );
	dbDelta( $sql_availability );
	dbDelta( $sql_exceptions );
	dbDelta( $sql_progress );
	dbDelta( $sql_reviews );

	update_option( 'buckleup_app_db_version', BUCKLEUP_APP_DB_VERSION );
}

/**
 * Allowed booking statuses (mirrors the Prisma BookingStatus enum).
 *
 * @return string[]
 */
function buckleup_app_booking_statuses() {
	return array( 'PENDING', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'NO_SHOW' );
}
