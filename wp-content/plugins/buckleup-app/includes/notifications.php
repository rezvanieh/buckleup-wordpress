<?php
/**
 * Notifications — wp_mail for booking events + the template store for the
 * admin CRUD UI.
 *
 * v1 of the app ships EMAIL only (via wp_mail → Mailpit in dev). SMS / WhatsApp
 * (Twilio) are DEFERRED: the channel enum + template store exist so the admin
 * notification UI can manage them, but no SMS is actually dispatched — guarded
 * by buckleup_app_channel_enabled().
 *
 * Templates are stored as `buckleup_notification_template` CPT posts (managed by
 * the admin REST controller); the booking emails fall back to built-in defaults
 * so the flow works before any templates are authored.
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Register the (private) template-store CPT used by the admin notifications UI.
 */
add_action( 'init', function () {
	register_post_type( 'bu_notif_template', array(
		'labels'       => array( 'name' => __( 'Notification Templates', 'buckleup-app' ) ),
		'public'       => false,
		'show_ui'      => false, // managed via the admin console REST, not wp-admin.
		'show_in_rest' => false,
		'supports'     => array( 'title', 'editor' ),
	) );
} );

/**
 * Whether a notification channel is live. EMAIL only in v1.
 *
 * @param string $channel EMAIL|SMS|WHATSAPP
 * @return bool
 */
function buckleup_app_channel_enabled( $channel ) {
	$enabled = array( 'EMAIL' => true, 'SMS' => false, 'WHATSAPP' => false );
	$enabled = apply_filters( 'buckleup_app_channels', $enabled );
	return ! empty( $enabled[ strtoupper( $channel ) ] );
}

/**
 * Notify the student about a booking status change (CONFIRMED/CANCELLED).
 *
 * @param array<string,mixed> $booking Updated bu_bookings row (ARRAY_A).
 * @param string              $status  CONFIRMED|CANCELLED
 * @param string              $reason  Optional reason.
 */
function buckleup_notify_booking_status( $booking, $status, $reason = '' ) {
	$student_id = (int) $booking['student_id'];
	$student    = get_user_by( 'id', $student_id );
	if ( ! $student || ! $student->user_email ) {
		return;
	}

	$service_name = $booking['service_id'] ? get_the_title( (int) $booking['service_id'] ) : __( 'your lesson', 'buckleup-app' );
	$ts           = strtotime( $booking['datetime'] );
	$date_str     = $ts ? wp_date( 'l, F j, Y', $ts ) : '';
	$time_str     = $ts ? wp_date( 'g:i A', $ts ) : '';
	$instructor   = get_the_author_meta( 'display_name', (int) $booking['instructor_id'] );
	$location     = $booking['pickup_addr'] ? $booking['pickup_addr'] : __( 'To be confirmed', 'buckleup-app' );

	$event = ( 'CONFIRMED' === $status ) ? 'booking.approved' : 'booking.cancelled';

	$vars = array(
		'studentName'    => $student->display_name,
		'serviceName'    => $service_name,
		'date'           => $date_str,
		'time'           => $time_str,
		'instructorName' => $instructor,
		'location'       => $location,
		'reason'         => $reason,
	);

	if ( buckleup_app_channel_enabled( 'EMAIL' ) ) {
		list( $subject, $body ) = buckleup_render_email_template( $event, $vars );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		wp_mail( $student->user_email, $subject, $body, $headers );
	}

	// SMS / WHATSAPP deferred — intentionally not dispatched in v1.
	do_action( 'buckleup_app_notification_queued', $event, $student_id, $vars );
}

/**
 * Render an email template for an event: a stored template (by event key) if
 * present, else a built-in default. Returns [ subject, plaintext-body ].
 *
 * @param string               $event booking.approved|booking.cancelled
 * @param array<string,string> $vars  Replacement variables.
 * @return array{0:string,1:string}
 */
function buckleup_render_email_template( $event, $vars ) {
	$stored = buckleup_get_template_for_event( $event, 'EMAIL' );
	if ( $stored ) {
		$subject = buckleup_interpolate( (string) $stored['subject'], $vars );
		$body    = buckleup_interpolate( wp_strip_all_tags( (string) $stored['textBody'] ), $vars );
		return array( $subject, $body );
	}

	if ( 'booking.approved' === $event ) {
		$subject = sprintf( /* translators: %s: service name. */ __( 'Booking Confirmed: %s', 'buckleup-app' ), $vars['serviceName'] );
		$body    = sprintf(
			/* translators: 1: student, 2: service, 3: date, 4: time, 5: instructor, 6: location. */
			__( "Hi %1\$s,\n\nGreat news! Your booking for %2\$s on %3\$s at %4\$s with %5\$s has been confirmed.\nLocation: %6\$s.\n\nPlease remember to bring your permit. See you there!\n\n— BuckleUp Driving School", 'buckleup-app' ),
			$vars['studentName'], $vars['serviceName'], $vars['date'], $vars['time'], $vars['instructorName'], $vars['location']
		);
	} else {
		$subject = sprintf( /* translators: %s: service name. */ __( 'Booking Cancelled: %s', 'buckleup-app' ), $vars['serviceName'] );
		$reason  = $vars['reason'] ? sprintf( /* translators: %s: reason. */ __( "\nReason: %s", 'buckleup-app' ), $vars['reason'] ) : '';
		$body    = sprintf(
			/* translators: 1: student, 2: service, 3: date, 4: time, 5: reason line. */
			__( "Hi %1\$s,\n\nYour booking for %2\$s on %3\$s at %4\$s has been cancelled.%5\$s\n\nReady to rebook? Visit buckleupdriving.ca to schedule a new lesson.\n\n— BuckleUp Driving School", 'buckleup-app' ),
			$vars['studentName'], $vars['serviceName'], $vars['date'], $vars['time'], $reason
		);
	}
	return array( $subject, $body );
}

/**
 * Replace {{key}} tokens in a string.
 *
 * @param string               $text
 * @param array<string,string> $vars
 * @return string
 */
function buckleup_interpolate( $text, $vars ) {
	foreach ( $vars as $k => $v ) {
		$text = str_replace( '{{' . $k . '}}', (string) $v, $text );
	}
	return $text;
}

/**
 * Look up an active stored template for an event+channel.
 *
 * @param string $event_key
 * @param string $channel
 * @return array{subject:string,textBody:string,htmlBody:string}|null
 */
function buckleup_get_template_for_event( $event_key, $channel ) {
	$query = new WP_Query( array(
		'post_type'      => 'bu_notif_template',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => 'bu_event_key', 'value' => $event_key ),
			array( 'key' => 'bu_channel', 'value' => strtoupper( $channel ) ),
			array( 'key' => 'bu_is_active', 'value' => '1' ),
		),
	) );
	if ( ! $query->have_posts() ) {
		return null;
	}
	$post = $query->posts[0];
	return array(
		'subject'  => (string) get_post_meta( $post->ID, 'bu_subject', true ),
		'textBody' => $post->post_content,
		'htmlBody' => (string) get_post_meta( $post->ID, 'bu_html_body', true ),
	);
}
