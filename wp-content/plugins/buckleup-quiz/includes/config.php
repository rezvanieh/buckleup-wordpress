<?php
/**
 * Quiz configuration: the 12-category taxonomy and the engine tunables.
 *
 * Single source of truth for categories (slug ↔ label ↔ blurb) shared by the
 * selection engine, the front-end runner/landing pages, the SEO schema, and the
 * seed. Every tunable is filterable so behaviour can change without code edits.
 *
 * Category slugs mirror the ICBC Class 4 "Driving Commercial Vehicles" chapters
 * the source PDF is drawn from. The order here is the canonical display order.
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The 12 quiz categories.
 *
 * @return array<string,array{label:string,short:string,blurb:string}>
 *               Keyed by slug. `label` = human title, `short` = nav/chip label,
 *               `blurb` = one-line description for cards/landing intros.
 */
function buckleup_quiz_categories() {
	$categories = array(
		'getting-your-licence' => array(
			'label' => __( 'Getting Your Licence', 'buckleup-quiz' ),
			'short' => __( 'Getting Your Licence', 'buckleup-quiz' ),
			'blurb' => __( 'Class 4 licence classes, eligibility, medical & vision standards, and the testing process.', 'buckleup-quiz' ),
		),
		'heavy-vehicle-braking' => array(
			'label' => __( 'Heavy Vehicle Braking', 'buckleup-quiz' ),
			'short' => __( 'Heavy Vehicle Braking', 'buckleup-quiz' ),
			'blurb' => __( 'Stopping distance, the effect of speed and weight, brake fade, and downhill braking.', 'buckleup-quiz' ),
		),
		'basic-driving-skills' => array(
			'label' => __( 'Basic Driving Skills', 'buckleup-quiz' ),
			'short' => __( 'Basic Driving Skills', 'buckleup-quiz' ),
			'blurb' => __( 'Following distance, manoeuvring, seeing and being seen, and vehicle and personal safety.', 'buckleup-quiz' ),
		),
		'fuel-efficient-driving' => array(
			'label' => __( 'Fuel-Efficient Driving', 'buckleup-quiz' ),
			'short' => __( 'Fuel-Efficient Driving', 'buckleup-quiz' ),
			'blurb' => __( 'Smart driving habits and vehicle-maintenance choices that cut fuel use.', 'buckleup-quiz' ),
		),
		'trucks-and-trailers' => array(
			'label' => __( 'Trucks and Trailers', 'buckleup-quiz' ),
			'short' => __( 'Trucks & Trailers', 'buckleup-quiz' ),
			'blurb' => __( 'Vehicle dimensions, loading basics, weight distribution, and load securement.', 'buckleup-quiz' ),
		),
		'buses-taxis-limos-ride-hailing' => array(
			'label' => __( 'Buses, Taxis, Limos & Ride-Hailing', 'buckleup-quiz' ),
			'short' => __( 'Buses, Taxis & Ride-Hailing', 'buckleup-quiz' ),
			'blurb' => __( 'Passenger safety, pick-ups and drop-offs, vehicle operations, and when to refuse passengers.', 'buckleup-quiz' ),
		),
		'hours-of-service' => array(
			'label' => __( 'Hours of Service', 'buckleup-quiz' ),
			'short' => __( 'Hours of Service', 'buckleup-quiz' ),
			'blurb' => __( 'On-duty and off-duty time limits and the record-keeping rules for commercial drivers.', 'buckleup-quiz' ),
		),
		'air-brakes' => array(
			'label' => __( 'Air Brakes', 'buckleup-quiz' ),
			'short' => __( 'Air Brakes', 'buckleup-quiz' ),
			'blurb' => __( 'Air-brake components and operation, system checks, low-air warnings, and ABS.', 'buckleup-quiz' ),
		),
		'air-brake-adjustment' => array(
			'label' => __( 'Air Brake Adjustment', 'buckleup-quiz' ),
			'short' => __( 'Air Brake Adjustment', 'buckleup-quiz' ),
			'blurb' => __( 'Why adjustment matters, how to check pushrod stroke, and slack-adjuster procedures.', 'buckleup-quiz' ),
		),
		'pre-trip-inspections' => array(
			'label' => __( 'Pre-Trip Inspections', 'buckleup-quiz' ),
			'short' => __( 'Pre-Trip Inspections', 'buckleup-quiz' ),
			'blurb' => __( 'Inspection duties, bus and passenger-vehicle procedures, and reporting defects.', 'buckleup-quiz' ),
		),
		'signs-signals-and-markings' => array(
			'label' => __( 'Signs, Signals & Road Markings', 'buckleup-quiz' ),
			'short' => __( 'Signs & Signals', 'buckleup-quiz' ),
			'blurb' => __( 'Regulatory and warning signs, traffic signals, and lane and road markings.', 'buckleup-quiz' ),
		),
		'industrial-roads' => array(
			'label' => __( 'Industrial Roads', 'buckleup-quiz' ),
			'short' => __( 'Industrial Roads', 'buckleup-quiz' ),
			'blurb' => __( 'Awareness and right-of-way when driving on private, resource, and industrial roads.', 'buckleup-quiz' ),
		),
	);

	/**
	 * Filter the quiz category taxonomy.
	 *
	 * @param array $categories Keyed by slug.
	 */
	return apply_filters( 'buckleup_quiz_categories', $categories );
}

/**
 * Whether a string is a known category slug.
 *
 * @param string $slug
 * @return bool
 */
function buckleup_quiz_is_category( $slug ) {
	return is_string( $slug ) && '' !== $slug && array_key_exists( $slug, buckleup_quiz_categories() );
}

/**
 * Human label for a category slug (falls back to a humanised slug).
 *
 * @param string $slug
 * @return string
 */
function buckleup_quiz_category_label( $slug ) {
	$cats = buckleup_quiz_categories();
	if ( isset( $cats[ $slug ]['label'] ) ) {
		return $cats[ $slug ]['label'];
	}
	return ucwords( str_replace( '-', ' ', (string) $slug ) );
}

/**
 * Distinct icon (theme `buckleup_icon` name) for each category — used everywhere
 * a category is shown (hub topic grid, category hero, the runner rail/card) so the
 * topics are recognisable at a glance instead of relying on a colour dot alone.
 *
 * @param string $slug
 * @return string Icon name.
 */
function buckleup_quiz_category_icon( $slug ) {
	$map = array(
		'getting-your-licence'           => 'id-card',
		'heavy-vehicle-braking'          => 'gauge',
		'basic-driving-skills'           => 'car',
		'fuel-efficient-driving'         => 'fuel',
		'trucks-and-trailers'            => 'truck',
		'buses-taxis-limos-ride-hailing' => 'bus',
		'hours-of-service'               => 'clock',
		'air-brakes'                     => 'wind',
		'air-brake-adjustment'           => 'wrench',
		'pre-trip-inspections'           => 'clipboard-check',
		'signs-signals-and-markings'     => 'octagon',
		'industrial-roads'               => 'construction',
	);
	$icon = isset( $map[ $slug ] ) ? $map[ $slug ] : 'check-circle';
	/**
	 * Filter the icon for a category slug.
	 *
	 * @param string $icon
	 * @param string $slug
	 */
	return (string) apply_filters( 'buckleup_quiz_category_icon', $icon, $slug );
}

/**
 * Engine tunables. Filter individual keys via `buckleup_quiz_config`.
 *
 * - full_total        : questions in a full mock exam (~real ICBC length).
 * - category_total    : questions in a single-category quiz.
 * - pass_pct          : passing percentage (ICBC knowledge test is 80%).
 * - max_attempts      : attempts allowed for ANONYMOUS identities (logged-in = unlimited).
 * - session_ttl       : seconds a started test session lives (Redis/transient).
 * - min_fill_seconds  : minimum plausible time to complete (bot guard on submit).
 * - sample_count      : sample questions rendered (crawlable) on each landing page.
 *
 * @return array<string,int>
 */
function buckleup_quiz_config() {
	$config = array(
		'full_total'          => 50,
		'category_total'      => 10,
		'pass_pct'            => 80,
		'max_attempts'        => 3,
		'session_ttl'         => 2 * HOUR_IN_SECONDS,
		'min_fill_seconds'    => 10,
		'sample_count'        => 6,
		'time_limit_full'     => 45 * MINUTE_IN_SECONDS, // timed full mock exam
		'time_limit_category' => 10 * MINUTE_IN_SECONDS, // timed topic quiz
	);

	/**
	 * Filter the quiz engine tunables.
	 *
	 * @param array $config
	 */
	return (array) apply_filters( 'buckleup_quiz_config', $config );
}

/**
 * Convenience getter for a single tunable.
 *
 * @param string $key
 * @param int    $default
 * @return int
 */
function buckleup_quiz_cfg( $key, $default = 0 ) {
	$config = buckleup_quiz_config();
	return isset( $config[ $key ] ) ? (int) $config[ $key ] : (int) $default;
}

/**
 * Time limit (seconds) for a test mode. 0 = untimed.
 *
 * @param string $mode 'full' or a category slug.
 * @return int
 */
function buckleup_quiz_time_limit( $mode ) {
	$seconds = buckleup_quiz_is_category( $mode )
		? buckleup_quiz_cfg( 'time_limit_category', 10 * MINUTE_IN_SECONDS )
		: buckleup_quiz_cfg( 'time_limit_full', 45 * MINUTE_IN_SECONDS );
	/**
	 * Filter the time limit (seconds) for a mode. Return 0 for untimed.
	 *
	 * @param int    $seconds
	 * @param string $mode
	 */
	return (int) apply_filters( 'buckleup_quiz_time_limit', $seconds, $mode );
}

/**
 * Standard REST error (self-contained — does not depend on buckleup-app).
 *
 * @param string $message
 * @param int    $status
 * @return WP_Error
 */
function buckleup_quiz_rest_error( $message, $status = 400 ) {
	return new WP_Error( 'buckleup_quiz_error', $message, array( 'status' => $status ) );
}

/**
 * Sanitise + length-cap a display name (for the certificate / email greeting).
 *
 * @param string $name
 * @return string
 */
function buckleup_quiz_clean_name( $name ) {
	$name = sanitize_text_field( (string) $name );
	$name = trim( preg_replace( '/\s+/', ' ', $name ) );
	return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 120 ) : substr( $name, 0, 120 );
}
