<?php
/**
 * Title: Console: Admin — Settings
 * Slug: buckleup/console-admin-settings
 * Inserter: no
 *
 * /admin/settings — matching src/app/admin/settings/page.tsx (in-scope parts):
 * a Profile card (avatar upload/remove + disabled Full Name / Email) and an
 * Appearance card (Light/Dark/System theme chooser). The source's commented-out
 * System-Notifications / System-Configuration / Data-Management sections are dead
 * and OMITTED. The profile data (name/email/avatar) is server-rendered from
 * GET /user/avatar (rest_do_request runs as the logged-in admin); avatar
 * upload/remove are JS mutations (console-avatar.js → POST/DELETE /user/avatar,
 * X-WP-Nonce). The theme chooser uses the shared theme.js [data-theme-set].
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$me = array( 'name' => '', 'email' => '', 'avatar' => '' );
if ( function_exists( 'rest_do_request' ) ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/buckleup/v1/user/avatar' ) );
	if ( 200 === $res->get_status() ) {
		$d  = (array) $res->get_data();
		$me = array(
			'name'   => (string) ( $d['name'] ?? '' ),
			'email'  => (string) ( $d['email'] ?? '' ),
			'avatar' => (string) ( $d['avatar'] ?? ( $d['image'] ?? '' ) ),
		);
	}
}
$initials = '';
foreach ( array_slice( preg_split( '/\s+/', trim( $me['name'] ) ), 0, 2 ) as $part ) {
	$initials .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );
}

$themes = array(
	array( 'value' => 'light',  'icon' => 'sun',     'label' => __( 'Light', 'buckleup' ),  'desc' => __( 'Clean, bright theme.', 'buckleup' ) ),
	array( 'value' => 'dark',   'icon' => 'moon',    'label' => __( 'Dark', 'buckleup' ),   'desc' => __( 'Premium dark theme.', 'buckleup' ) ),
	array( 'value' => 'system', 'icon' => 'monitor', 'label' => __( 'System', 'buckleup' ), 'desc' => __( 'Match your device.', 'buckleup' ) ),
);

ob_start();
echo buckleup_console_heading( __( 'Admin Settings', 'buckleup' ), __( 'Manage your profile and preferences.', 'buckleup' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
?>
<!-- Profile card -->
<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8 mb-8' ) ); ?>" data-avatar-card>
	<h2 class="text-xl font-semibold text-foreground flex items-center gap-2 mb-6"><?php echo buckleup_icon( 'user', 'w-5 h-5' ); // phpcs:ignore ?><?php esc_html_e( 'Profile', 'buckleup' ); ?></h2>
	<div class="flex flex-col md:flex-row items-center md:items-start gap-8">
		<div class="flex flex-col items-center gap-3">
			<div class="w-24 h-24 rounded-full bg-primary/10 flex items-center justify-center overflow-hidden text-2xl font-bold text-primary" data-avatar-preview>
				<?php if ( $me['avatar'] ) : ?>
					<img src="<?php echo esc_url( $me['avatar'] ); ?>" alt="<?php echo esc_attr( $me['name'] ); ?>" class="w-full h-full object-cover">
				<?php else : ?>
					<?php echo esc_html( strtoupper( $initials ) ); ?>
				<?php endif; ?>
			</div>
			<div class="flex gap-2">
				<label class="<?php echo esc_attr( buckleup_button_class( 'outline', 'sm', 'cursor-pointer' ) ); ?>">
					<?php echo buckleup_icon( 'upload', 'w-4 h-4' ); // phpcs:ignore ?><?php esc_html_e( 'Change', 'buckleup' ); ?>
					<input type="file" accept="image/*" data-avatar-input class="sr-only">
				</label>
				<button type="button" data-avatar-remove class="<?php echo esc_attr( buckleup_button_class( 'ghost', 'sm', 'text-destructive hover:bg-destructive/10' ) ); ?>"><?php esc_html_e( 'Remove', 'buckleup' ); ?></button>
			</div>
		</div>

		<div class="flex-1 space-y-4 w-full">
			<div>
				<label for="bu-admin-name" class="<?php echo esc_attr( buckleup_label_class( 'mb-2 block text-muted-foreground' ) ); ?>"><?php esc_html_e( 'Full Name', 'buckleup' ); ?></label>
				<input id="bu-admin-name" type="text" value="<?php echo esc_attr( $me['name'] ); ?>" disabled class="<?php echo esc_attr( buckleup_input_class( 'bg-muted max-w-md' ) ); ?>">
			</div>
			<div>
				<label for="bu-admin-email" class="<?php echo esc_attr( buckleup_label_class( 'mb-2 block text-muted-foreground' ) ); ?>"><span class="inline-flex items-center gap-1"><?php echo buckleup_icon( 'mail', 'w-4 h-4' ); // phpcs:ignore ?><?php esc_html_e( 'Email Address', 'buckleup' ); ?></span></label>
				<input id="bu-admin-email" type="email" value="<?php echo esc_attr( $me['email'] ); ?>" disabled class="<?php echo esc_attr( buckleup_input_class( 'bg-muted max-w-md' ) ); ?>">
				<p class="text-xs text-muted-foreground mt-1"><?php esc_html_e( 'Contact support to change account details.', 'buckleup' ); ?></p>
			</div>
		</div>
	</div>
	<div data-avatar-status role="status" aria-live="polite" class="mt-4 text-sm hidden" hidden></div>
</div>

<!-- Appearance card -->
<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8 max-w-2xl' ) ); ?>">
	<h2 class="text-xl font-semibold text-foreground flex items-center gap-2 mb-1"><?php echo buckleup_icon( 'monitor', 'w-5 h-5' ); // phpcs:ignore ?><?php esc_html_e( 'Appearance', 'buckleup' ); ?></h2>
	<p class="text-sm text-muted-foreground mb-6"><?php esc_html_e( 'Choose your preferred theme. Your selection will sync across all your devices.', 'buckleup' ); ?></p>
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

echo buckleup_console_shell( 'admin', 'settings', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
