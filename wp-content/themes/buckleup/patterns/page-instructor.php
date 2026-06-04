<?php
/**
 * Title: Page: Instructor Dashboard
 * Slug: buckleup/page-instructor
 * Inserter: no
 *
 * Instructor console DASHBOARD (`/instructor`) inside the shared console shell.
 * Thin welcome shell for this milestone; the full stat tiles + "Next Upcoming
 * Lesson" card (GET /instructors/stats) land in the per-page build-out. NEVER
 * shows the source's fake "4.9 (47 reviews)" — real rating or omitted. Gated by
 * the plugin.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$user  = wp_get_current_user();
$first = $user && $user->exists() ? ( $user->first_name ?: ( explode( ' ', $user->display_name ?: $user->user_login )[0] ) ) : '';

$cards = array(
	array( 'icon' => 'clock',        'title' => __( 'My Schedule', 'buckleup' ),  'desc' => __( 'Confirm, decline, or cancel upcoming lessons.', 'buckleup' ), 'href' => home_url( '/instructor/schedule/' ) ),
	array( 'icon' => 'check',        'title' => __( 'Availability', 'buckleup' ),  'desc' => __( 'Set your weekly hours and calendar exceptions.', 'buckleup' ), 'href' => home_url( '/instructor/availability/' ) ),
	array( 'icon' => 'user',         'title' => __( 'My Students', 'buckleup' ),   'desc' => __( 'View your roster and their latest skills.', 'buckleup' ),      'href' => home_url( '/instructor/students/' ) ),
	array( 'icon' => 'shield-check', 'title' => __( 'My Profile', 'buckleup' ),    'desc' => __( 'Bio, certifications, languages, and avatar.', 'buckleup' ),     'href' => home_url( '/instructor/profile/' ) ),
);

ob_start();
echo buckleup_console_heading( // phpcs:ignore WordPress.Security.EscapeOutput
	sprintf( /* translators: %s: instructor first name */ __( 'Welcome back, %s', 'buckleup' ), $first ),
	__( 'Your schedule, students, and availability at a glance.', 'buckleup' )
);
?>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
	<?php foreach ( $cards as $c ) : ?>
		<a href="<?php echo esc_url( $c['href'] ); ?>" class="<?php echo esc_attr( buckleup_card_class( 'p-6 hover-lift card-highlight' ) ); ?>">
			<span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary/10 text-primary mb-4"><?php echo buckleup_icon( $c['icon'], 'w-5 h-5' ); // phpcs:ignore ?></span>
			<h2 class="font-semibold text-foreground"><?php echo esc_html( $c['title'] ); ?></h2>
			<p class="text-sm text-muted-foreground mt-1"><?php echo esc_html( $c['desc'] ); ?></p>
		</a>
	<?php endforeach; ?>
</div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'instructor', 'dashboard', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
