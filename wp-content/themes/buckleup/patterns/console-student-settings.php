<?php
/**
 * Title: Console: Student — Settings
 * Slug: buckleup/console-student-settings
 * Inserter: no
 *
 * /student/settings — a single Appearance card with the Light/Dark/System theme
 * chooser (reuses the theme module's [data-theme-set] controls → persisted to
 * localStorage + cookie by theme.js). Per the build plan + lead decision: ONLY the
 * theme toggle; the source's Notifications/Password/Privacy controls don't exist /
 * are decorative, so they're omitted.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$themes = array(
	array( 'value' => 'light',  'icon' => 'sun',     'label' => __( 'Light', 'buckleup' ),  'desc' => __( 'Clean, bright theme.', 'buckleup' ) ),
	array( 'value' => 'dark',   'icon' => 'moon',    'label' => __( 'Dark', 'buckleup' ),   'desc' => __( 'Premium dark theme.', 'buckleup' ) ),
	array( 'value' => 'system', 'icon' => 'monitor', 'label' => __( 'System', 'buckleup' ), 'desc' => __( 'Match your device.', 'buckleup' ) ),
);

ob_start();
echo buckleup_console_heading( __( 'Settings', 'buckleup' ), __( 'Manage how the console looks.', 'buckleup' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
?>
<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8 max-w-2xl' ) ); ?>">
	<h2 class="text-lg font-semibold text-foreground mb-1"><?php esc_html_e( 'Appearance', 'buckleup' ); ?></h2>
	<p class="text-sm text-muted-foreground mb-6"><?php esc_html_e( 'Choose the theme for the console and site.', 'buckleup' ); ?></p>
	<div class="space-y-3">
		<?php foreach ( $themes as $t ) : ?>
			<button type="button" data-theme-set="<?php echo esc_attr( $t['value'] ); ?>" data-state="unselected" aria-pressed="false"
				class="relative w-full flex items-center gap-4 p-4 rounded-xl border transition-all bg-card border-border hover:bg-muted/50 hover:border-muted-foreground/30 data-[state=selected]:bg-primary/10 data-[state=selected]:border-primary/50 data-[state=selected]:ring-2 data-[state=selected]:ring-primary/20">
				<span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-muted text-muted-foreground"><?php echo buckleup_icon( $t['icon'], 'w-5 h-5' ); // phpcs:ignore ?></span>
				<span class="flex-1 text-left">
					<span class="block font-medium text-foreground"><?php echo esc_html( $t['label'] ); ?></span>
					<span class="block text-sm text-muted-foreground"><?php echo esc_html( $t['desc'] ); ?></span>
				</span>
			</button>
		<?php endforeach; ?>
	</div>
	<p class="text-xs text-muted-foreground mt-4">
		<?php esc_html_e( 'Currently displaying:', 'buckleup' ); ?> <span data-theme-current class="font-medium text-foreground"></span>
	</p>
</div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'student', 'settings', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
