<?php
/**
 * Title: Page: Instructor Dashboard
 * Slug: buckleup/page-instructor
 * Inserter: no
 *
 * Instructor console DASHBOARD (`/instructor`) inside the shared console shell.
 * Real stat tiles (Upcoming / Completed) from GET /instructors/stats — the rating
 * tile is OMITTED unless a real avgRating is present (never the source's fake
 * "4.9 (47 reviews)"). "Next Upcoming Lesson" card from stats.nextLesson, or an
 * empty state. Gated by the plugin.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$user  = wp_get_current_user();
$first = $user && $user->exists() ? ( $user->first_name ?: ( explode( ' ', $user->display_name ?: $user->user_login )[0] ) ) : '';

$stats = array( 'upcomingBookings' => 0, 'completedBookings' => 0 );
$next  = null;
if ( function_exists( 'rest_do_request' ) ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/buckleup/v1/instructors/stats' ) );
	if ( 200 === $res->get_status() ) {
		$d     = (array) $res->get_data();
		$stats = array_merge( $stats, (array) ( $d['stats'] ?? array() ) );
		$next  = $d['nextLesson'] ?? null;
	}
}

// Tiles — only include the rating tile if a REAL avgRating is present (no fakes).
$tiles = array(
	array( 'icon' => 'clock', 'label' => __( 'Upcoming Lessons', 'buckleup' ),  'value' => (int) $stats['upcomingBookings'] ),
	array( 'icon' => 'check', 'label' => __( 'Completed Lessons', 'buckleup' ), 'value' => (int) $stats['completedBookings'] ),
);
if ( isset( $stats['avgRating'] ) && $stats['avgRating'] ) {
	$tiles[] = array( 'icon' => 'star', 'label' => __( 'Average Rating', 'buckleup' ), 'value' => number_format( (float) $stats['avgRating'], 1 ) );
}

ob_start();
echo buckleup_console_heading( // phpcs:ignore WordPress.Security.EscapeOutput
	sprintf( /* translators: %s: instructor first name */ __( 'Welcome back, %s', 'buckleup' ), $first ),
	__( 'Your schedule, students, and availability at a glance.', 'buckleup' )
);
?>
<!-- Stat tiles -->
<div class="grid grid-cols-2 <?php echo count( $tiles ) > 2 ? 'lg:grid-cols-3' : ''; ?> gap-5 mb-8">
	<?php foreach ( $tiles as $t ) : ?>
		<div class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
			<span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary/10 text-primary mb-3"><?php echo buckleup_icon( $t['icon'], 'w-5 h-5' ); // phpcs:ignore ?></span>
			<div class="text-3xl font-bold text-foreground"><?php echo esc_html( $t['value'] ); ?></div>
			<div class="text-sm text-muted-foreground"><?php echo esc_html( $t['label'] ); ?></div>
		</div>
	<?php endforeach; ?>
</div>

<!-- Next upcoming lesson -->
<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8 mb-8' ) ); ?>">
	<h2 class="text-lg font-semibold text-foreground mb-4"><?php esc_html_e( 'Next Upcoming Lesson', 'buckleup' ); ?></h2>
	<?php if ( $next && is_array( $next ) ) :
		$student = $next['student']['user']['name'] ?? ( $next['student']['name'] ?? __( 'Student', 'buckleup' ) );
		$service = $next['service']['name'] ?? '';
		$when    = ! empty( $next['datetime'] ) ? mysql2date( 'M j, Y · g:i A', $next['datetime'] ) : '';
		$dur     = $next['service']['duration'] ?? ( $next['duration'] ?? '' );
		?>
		<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
			<div><span class="text-muted-foreground"><?php esc_html_e( 'Student', 'buckleup' ); ?>:</span> <span class="font-medium text-foreground"><?php echo esc_html( $student ); ?></span></div>
			<?php if ( $service ) : ?><div><span class="text-muted-foreground"><?php esc_html_e( 'Service', 'buckleup' ); ?>:</span> <span class="font-medium text-foreground"><?php echo esc_html( $service ); ?></span></div><?php endif; ?>
			<?php if ( $when ) : ?><div><span class="text-muted-foreground"><?php esc_html_e( 'When', 'buckleup' ); ?>:</span> <span class="font-medium text-foreground"><?php echo esc_html( $when ); ?></span></div><?php endif; ?>
			<?php if ( $dur ) : ?><div><span class="text-muted-foreground"><?php esc_html_e( 'Duration', 'buckleup' ); ?>:</span> <span class="font-medium text-foreground"><?php echo esc_html( $dur ); ?> <?php esc_html_e( 'min', 'buckleup' ); ?></span></div><?php endif; ?>
		</div>
	<?php else : ?>
		<p class="text-sm text-muted-foreground py-4"><?php esc_html_e( 'No upcoming lessons scheduled.', 'buckleup' ); ?></p>
	<?php endif; ?>
</div>

<!-- Quick actions -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
	<a href="<?php echo esc_url( home_url( '/instructor/schedule/' ) ); ?>" class="<?php echo esc_attr( buckleup_card_class( 'p-6 hover-lift card-highlight' ) ); ?>">
		<span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary/10 text-primary mb-4"><?php echo buckleup_icon( 'clock', 'w-5 h-5' ); // phpcs:ignore ?></span>
		<h3 class="font-semibold text-foreground"><?php esc_html_e( 'My Schedule', 'buckleup' ); ?></h3>
		<p class="text-sm text-muted-foreground mt-1"><?php esc_html_e( 'Confirm, decline, or cancel lessons.', 'buckleup' ); ?></p>
	</a>
	<a href="<?php echo esc_url( home_url( '/instructor/availability/' ) ); ?>" class="<?php echo esc_attr( buckleup_card_class( 'p-6 hover-lift card-highlight' ) ); ?>">
		<span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary/10 text-primary mb-4"><?php echo buckleup_icon( 'check', 'w-5 h-5' ); // phpcs:ignore ?></span>
		<h3 class="font-semibold text-foreground"><?php esc_html_e( 'Availability', 'buckleup' ); ?></h3>
		<p class="text-sm text-muted-foreground mt-1"><?php esc_html_e( 'Set your weekly hours and exceptions.', 'buckleup' ); ?></p>
	</a>
</div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'instructor', 'dashboard', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
