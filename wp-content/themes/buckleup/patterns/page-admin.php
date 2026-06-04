<?php
/**
 * Title: Page: Admin Overview
 * Slug: buckleup/page-admin
 * Inserter: no
 *
 * Admin console OVERVIEW (`/admin`) inside the shared console shell. 4 real KPI
 * tiles — Total Revenue (always $0, payments deferred), Total Bookings, Active
 * Students, Instructors — from GET /admin/stats (rest_do_request). The source's
 * fake trend strings (+20.1% / +12 / +19% / +2) are DROPPED, as are its
 * commented-out Recent-Bookings + Quick-Actions sections. A "Manage Students"
 * action links to /admin/students. Gated by the plugin.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$stats = array( 'totalRevenue' => 0, 'totalBookings' => 0, 'totalStudents' => 0, 'totalInstructors' => 0 );
if ( function_exists( 'rest_do_request' ) ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/buckleup/v1/admin/stats' ) );
	if ( 200 === $res->get_status() ) {
		$d     = (array) $res->get_data();
		$stats = array_merge( $stats, (array) ( $d['stats'] ?? array() ) );
	}
}

// KPI tiles — values are real counts; revenue is $0 (no transactions in v1). No
// trend strings (the source's were hard-coded fakes).
$tiles = array(
	array( 'icon' => 'dollar-sign',    'tint' => 'bg-accent/10 text-accent',   'label' => __( 'Total Revenue', 'buckleup' ),    'value' => '$' . number_format_i18n( (float) $stats['totalRevenue'] ) ),
	array( 'icon' => 'calendar',       'tint' => 'bg-primary/10 text-primary', 'label' => __( 'Total Bookings', 'buckleup' ),   'value' => number_format_i18n( (int) $stats['totalBookings'] ) ),
	array( 'icon' => 'users',          'tint' => 'bg-primary/10 text-primary', 'label' => __( 'Active Students', 'buckleup' ),  'value' => number_format_i18n( (int) $stats['totalStudents'] ) ),
	array( 'icon' => 'graduation-cap', 'tint' => 'bg-accent/10 text-accent',   'label' => __( 'Instructors', 'buckleup' ),      'value' => number_format_i18n( (int) $stats['totalInstructors'] ) ),
);

ob_start();
?>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
	<div>
		<h1 class="text-2xl md:text-3xl font-bold text-foreground"><?php esc_html_e( 'Dashboard Overview', 'buckleup' ); ?></h1>
		<p class="text-muted-foreground mt-1"><?php esc_html_e( "Welcome back! Here's what's happening with your driving school.", 'buckleup' ); ?></p>
	</div>
	<?php
	buckleup_button( array(
		'label' => __( 'Manage Students', 'buckleup' ),
		'href'  => home_url( '/admin/students/' ),
		'icon'  => buckleup_icon( 'users', 'w-4 h-4' ),
	) );
	?>
</div>

<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
	<?php foreach ( $tiles as $t ) : ?>
		<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 hover:border-primary/30 transition-colors' ) ); ?>">
			<div class="flex items-center justify-between mb-2">
				<span class="text-sm font-medium text-muted-foreground"><?php echo esc_html( $t['label'] ); ?></span>
				<span class="inline-flex items-center justify-center w-8 h-8 rounded-lg <?php echo esc_attr( $t['tint'] ); ?>"><?php echo buckleup_icon( $t['icon'], 'w-4 h-4' ); // phpcs:ignore ?></span>
			</div>
			<div class="text-2xl font-bold text-foreground"><?php echo esc_html( $t['value'] ); ?></div>
		</div>
	<?php endforeach; ?>
</div>

<!-- Management shortcuts -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mt-8">
	<?php
	$cards = array(
		array( 'icon' => 'user',           'title' => __( 'Students', 'buckleup' ),  'desc' => __( 'Manage student accounts and bookings.', 'buckleup' ), 'href' => home_url( '/admin/students/' ) ),
		array( 'icon' => 'star',           'title' => __( 'Graduates', 'buckleup' ), 'desc' => __( 'Upload and manage Hall-of-Fame photos.', 'buckleup' ), 'href' => home_url( '/admin/graduates/' ) ),
		array( 'icon' => 'check',          'title' => __( 'Reviews', 'buckleup' ),   'desc' => __( 'Approve, unapprove, or remove reviews.', 'buckleup' ), 'href' => home_url( '/admin/reviews/' ) ),
		array( 'icon' => 'message-circle', 'title' => __( 'Blogs', 'buckleup' ),     'desc' => __( 'Write and edit posts in wp-admin.', 'buckleup' ),     'href' => admin_url( 'edit.php' ) ),
	);
	foreach ( $cards as $c ) : ?>
		<a href="<?php echo esc_url( $c['href'] ); ?>" class="<?php echo esc_attr( buckleup_card_class( 'p-6 hover-lift card-highlight' ) ); ?>">
			<span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary/10 text-primary mb-4"><?php echo buckleup_icon( $c['icon'], 'w-5 h-5' ); // phpcs:ignore ?></span>
			<h2 class="font-semibold text-foreground"><?php echo esc_html( $c['title'] ); ?></h2>
			<p class="text-sm text-muted-foreground mt-1"><?php echo esc_html( $c['desc'] ); ?></p>
		</a>
	<?php endforeach; ?>
</div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'admin', 'overview', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
