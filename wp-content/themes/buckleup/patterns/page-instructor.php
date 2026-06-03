<?php
/**
 * Title: Page: Instructor Dashboard
 * Slug: buckleup/page-instructor
 * Inserter: no
 *
 * Instructor console LANDING SHELL (the signed-in role dashboard the login flow
 * redirects to). Minimal-but-branded; the full data-bound screens (Availability /
 * Schedule / Students / Profile / Settings, consuming buckleup/v1/instructors/*)
 * replace this in the #31 build-out. Uses only the existing compiled-Tailwind
 * vocabulary so no asset rebuild is required. Access gated by the plugin.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$user      = wp_get_current_user();
$bu_name   = $user && $user->exists() ? ( $user->display_name ? $user->display_name : $user->user_login ) : '';
$bu_logout = wp_logout_url( home_url() );

$bu_sections = array(
	array( 'icon' => 'clock',        'title' => __( 'My Availability', 'buckleup' ), 'desc' => __( 'Set your weekly hours and day-off exceptions.', 'buckleup' ) ),
	array( 'icon' => 'chevron-right', 'title' => __( 'My Schedule', 'buckleup' ),    'desc' => __( 'Confirm, decline, or cancel booked lessons.', 'buckleup' ) ),
	array( 'icon' => 'check',        'title' => __( 'My Students', 'buckleup' ),     'desc' => __( 'Your students and their latest progress.', 'buckleup' ) ),
	array( 'icon' => 'shield-check', 'title' => __( 'My Profile', 'buckleup' ),      'desc' => __( 'Bio, certifications, languages, and rate.', 'buckleup' ) ),
	array( 'icon' => 'monitor',      'title' => __( 'Settings', 'buckleup' ),        'desc' => __( 'Theme and account preferences.', 'buckleup' ) ),
);
?>
<!-- wp:html -->
<section class="min-h-[80vh] flex items-center justify-center p-4">
	<div class="w-full max-w-2xl">
		<div class="text-center mb-8">
			<span class="inline-flex items-center gap-2 px-3 py-1 mb-4 rounded-full text-sm bg-accent/10 text-accent border border-accent/20"><?php esc_html_e( 'Instructor Console', 'buckleup' ); ?></span>
			<h1 class="text-3xl font-bold mb-2 text-foreground">
				<?php
				/* translators: %s: the signed-in user's name. */
				printf( esc_html__( 'Welcome back, %s', 'buckleup' ), '<span class="gradient-text">' . esc_html( $bu_name ) . '</span>' );
				?>
			</h1>
			<p class="text-muted-foreground"><?php esc_html_e( 'Manage your availability, schedule, and students.', 'buckleup' ); ?></p>
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
