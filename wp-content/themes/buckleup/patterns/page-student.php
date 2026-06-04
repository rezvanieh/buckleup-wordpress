<?php
/**
 * Title: Page: Student Dashboard
 * Slug: buckleup/page-student
 * Inserter: no
 *
 * Student console DASHBOARD (`/student`), rendered inside the shared console
 * sidebar shell (buckleup_console_shell). Thin welcome — no data fetch (the
 * source's "2 lessons until road test" is hard-coded, so we keep a generic
 * subline). Access is gated by the plugin (template_redirect): logged-out →
 * /login/?callbackUrl, wrong role → home.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$user  = wp_get_current_user();
$first = $user && $user->exists() ? ( $user->first_name ?: ( explode( ' ', $user->display_name ?: $user->user_login )[0] ) ) : '';

$cards = array(
	array( 'icon' => 'star',    'title' => __( 'Leave a Review', 'buckleup' ), 'desc' => __( 'Share feedback about your lessons and instructor.', 'buckleup' ), 'href' => home_url( '/student/reviews/' ) ),
	array( 'icon' => 'user',    'title' => __( 'Your Profile', 'buckleup' ),   'desc' => __( 'Update your contact details and licence info.', 'buckleup' ),    'href' => home_url( '/student/profile/' ) ),
	array( 'icon' => 'monitor', 'title' => __( 'Settings', 'buckleup' ),       'desc' => __( 'Choose your appearance theme.', 'buckleup' ),                    'href' => home_url( '/student/settings/' ) ),
);

ob_start();
echo buckleup_console_heading( // phpcs:ignore WordPress.Security.EscapeOutput
	sprintf( /* translators: %s: student first name */ __( 'Welcome back, %s', 'buckleup' ), $first ),
	__( 'Manage your reviews, profile, and preferences.', 'buckleup' )
);
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
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

echo buckleup_console_shell( 'student', 'dashboard', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
