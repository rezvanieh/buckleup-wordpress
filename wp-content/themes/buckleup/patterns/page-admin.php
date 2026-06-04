<?php
/**
 * Title: Page: Admin Overview
 * Slug: buckleup/page-admin
 * Inserter: no
 *
 * Admin console OVERVIEW (`/admin`) inside the shared console shell. Thin welcome
 * shell for this milestone; the real KPI tiles (Active Students / Instructors /
 * Total Bookings / Revenue $0) come from GET /admin/stats in the build-out — and
 * the source's fake trend strings (+20.1% etc.) are DROPPED. Gated by the plugin.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$user  = wp_get_current_user();
$first = $user && $user->exists() ? ( $user->first_name ?: ( explode( ' ', $user->display_name ?: $user->user_login )[0] ) ) : '';

$cards = array(
	array( 'icon' => 'user',           'title' => __( 'Students', 'buckleup' ),  'desc' => __( 'Manage student accounts and bookings.', 'buckleup' ), 'href' => home_url( '/admin/students/' ) ),
	array( 'icon' => 'star',           'title' => __( 'Graduates', 'buckleup' ), 'desc' => __( 'Upload and manage Hall-of-Fame photos.', 'buckleup' ), 'href' => home_url( '/admin/graduates/' ) ),
	array( 'icon' => 'check',          'title' => __( 'Reviews', 'buckleup' ),   'desc' => __( 'Approve, unapprove, or remove reviews.', 'buckleup' ), 'href' => home_url( '/admin/reviews/' ) ),
	array( 'icon' => 'message-circle', 'title' => __( 'Blogs', 'buckleup' ),     'desc' => __( 'Write and edit posts in wp-admin.', 'buckleup' ),     'href' => admin_url( 'edit.php' ) ),
);

ob_start();
echo buckleup_console_heading( // phpcs:ignore WordPress.Security.EscapeOutput
	sprintf( /* translators: %s: admin first name */ __( 'Welcome back, %s', 'buckleup' ), $first ),
	__( 'Manage students, graduates, reviews, and content.', 'buckleup' )
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

echo buckleup_console_shell( 'admin', 'overview', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
