<?php
/**
 * Title: Page: Student Dashboard
 * Slug: buckleup/page-student
 * Inserter: no
 *
 * Student console LANDING SHELL (the signed-in role dashboard the login flow
 * redirects to). Minimal-but-branded: welcome header + role badge + the console
 * section list + sign out. The full data-bound screens (My Lessons / Progress /
 * Profile / Reviews / Settings, consuming buckleup/v1/students/*) replace this in
 * the #31 build-out. Uses only the existing compiled-Tailwind vocabulary so no
 * asset rebuild is required. Access is gated by the plugin (template_redirect):
 * logged-out -> /login/?callbackUrl, wrong role -> home.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$user     = wp_get_current_user();
$bu_name  = $user && $user->exists() ? ( $user->display_name ? $user->display_name : $user->user_login ) : '';
$bu_logout = wp_logout_url( home_url() );

$bu_sections = array(
	array( 'icon' => 'clock',        'title' => __( 'My Lessons', 'buckleup' ),     'desc' => __( 'Your upcoming and past driving lessons.', 'buckleup' ) ),
	array( 'icon' => 'shield-check', 'title' => __( 'My Progress', 'buckleup' ),    'desc' => __( 'Skills your instructor has signed off on.', 'buckleup' ) ),
	array( 'icon' => 'check',        'title' => __( 'My Profile', 'buckleup' ),     'desc' => __( 'Your contact details and licence info.', 'buckleup' ) ),
	array( 'icon' => 'star',         'title' => __( 'Leave a Review', 'buckleup' ),  'desc' => __( 'Share feedback about your instructor.', 'buckleup' ) ),
	array( 'icon' => 'monitor',      'title' => __( 'Settings', 'buckleup' ),       'desc' => __( 'Theme and account preferences.', 'buckleup' ) ),
);
?>
<!-- wp:html -->
<section class="min-h-[80vh] flex items-center justify-center p-4">
	<div class="w-full max-w-2xl">
		<div class="text-center mb-8">
			<span class="inline-flex items-center gap-2 px-3 py-1 mb-4 rounded-full text-sm bg-accent/10 text-accent border border-accent/20"><?php esc_html_e( 'Student Console', 'buckleup' ); ?></span>
			<h1 class="text-3xl font-bold mb-2 text-foreground">
				<?php
				/* translators: %s: the signed-in user's name. */
				printf( esc_html__( 'Welcome back, %s', 'buckleup' ), '<span class="gradient-text">' . esc_html( $bu_name ) . '</span>' );
				?>
			</h1>
			<p class="text-muted-foreground"><?php esc_html_e( 'Track your lessons, progress, and bookings.', 'buckleup' ); ?></p>
		</div>

		<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8' ) ); ?>">
			<div class="space-y-3">
				<?php foreach ( $bu_sections as $bu_s ) : ?>
					<div class="flex items-start gap-3 rounded-lg p-3">
						<span class="rounded-lg bg-accent/10 text-accent p-2"><?php echo buckleup_icon( $bu_s['icon'], 'w-4 h-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div>
							<p class="font-medium text-foreground"><?php echo esc_html( $bu_s['title'] ); ?></p>
							<p class="text-sm text-muted-foreground"><?php echo esc_html( $bu_s['desc'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="mt-6 flex items-center justify-between border-t border-border pt-6">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="text-sm font-medium text-muted-foreground hover:text-foreground"><?php esc_html_e( '← Back to site', 'buckleup' ); ?></a>
				<a href="<?php echo esc_url( $bu_logout ); ?>" class="<?php echo esc_attr( buckleup_button_class( 'outline' ) ); ?>"><?php esc_html_e( 'Sign out', 'buckleup' ); ?></a>
			</div>
		</div>
	</div>
</section>
<!-- /wp:html -->
