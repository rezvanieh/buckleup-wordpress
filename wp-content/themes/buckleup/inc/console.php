<?php
/**
 * Shared console (portal) shell — the per-role sidebar layout used by every
 * Student / Instructor / Admin console page (docs/CONSOLES-BUILD-PLAN.md).
 *
 * Matches the source `{role}/layout.tsx`: a fixed w-64 glass sidebar (brand block
 * → "{Role} Portal" + the user's name, linking to /), the role nav with the active
 * item highlighted, Sign out at the bottom, a mobile hamburger → slide-in drawer,
 * and the page content in a glass main panel. Each console page renders its inner
 * markup and passes it to buckleup_console_shell().
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Role config: portal label + the exact sidebar nav (order from the build plan,
 * linked items only). Each item: slug (for active state), name, href, icon.
 */
function buckleup_console_config( string $role ): array {
	$configs = array(
		'student' => array(
			'label' => __( 'Student Portal', 'buckleup' ),
			'nav'   => array(
				array( 'slug' => 'home',      'name' => __( 'Home Page', 'buckleup' ),      'href' => home_url( '/' ),                 'icon' => 'map-pin' ),
				array( 'slug' => 'dashboard', 'name' => __( 'Dashboard', 'buckleup' ),      'href' => home_url( '/student/' ),          'icon' => 'layout-dashboard' ),
				array( 'slug' => 'reviews',   'name' => __( 'Leave a Review', 'buckleup' ), 'href' => home_url( '/student/reviews/' ),  'icon' => 'star' ),
				array( 'slug' => 'profile',   'name' => __( 'Profile', 'buckleup' ),        'href' => home_url( '/student/profile/' ),  'icon' => 'user' ),
				array( 'slug' => 'settings',  'name' => __( 'Settings', 'buckleup' ),       'href' => home_url( '/student/settings/' ), 'icon' => 'monitor' ),
			),
		),
		'instructor' => array(
			'label' => __( 'Instructor Portal', 'buckleup' ),
			'nav'   => array(
				array( 'slug' => 'dashboard',    'name' => __( 'Dashboard', 'buckleup' ),     'href' => home_url( '/instructor/' ),             'icon' => 'layout-dashboard' ),
				array( 'slug' => 'schedule',     'name' => __( 'My Schedule', 'buckleup' ),   'href' => home_url( '/instructor/schedule/' ),     'icon' => 'clock' ),
				array( 'slug' => 'availability', 'name' => __( 'Availability', 'buckleup' ),  'href' => home_url( '/instructor/availability/' ), 'icon' => 'check' ),
				array( 'slug' => 'students',     'name' => __( 'My Students', 'buckleup' ),   'href' => home_url( '/instructor/students/' ),     'icon' => 'user' ),
				array( 'slug' => 'profile',      'name' => __( 'Profile', 'buckleup' ),       'href' => home_url( '/instructor/profile/' ),      'icon' => 'shield-check' ),
				array( 'slug' => 'settings',     'name' => __( 'Settings', 'buckleup' ),      'href' => home_url( '/instructor/settings/' ),     'icon' => 'monitor' ),
			),
		),
		'admin' => array(
			'label' => __( 'Admin Portal', 'buckleup' ),
			'nav'   => array(
				array( 'slug' => 'overview',  'name' => __( 'Overview', 'buckleup' ),  'href' => home_url( '/admin/' ),           'icon' => 'layout-dashboard' ),
				array( 'slug' => 'blogs',     'name' => __( 'Blogs', 'buckleup' ),     'href' => admin_url( 'edit.php' ),          'icon' => 'message-circle' ),
				array( 'slug' => 'students',  'name' => __( 'Students', 'buckleup' ),  'href' => home_url( '/admin/students/' ),   'icon' => 'user' ),
				array( 'slug' => 'graduates', 'name' => __( 'Graduates', 'buckleup' ), 'href' => home_url( '/admin/graduates/' ),  'icon' => 'star' ),
				array( 'slug' => 'reviews',   'name' => __( 'Reviews', 'buckleup' ),   'href' => home_url( '/admin/reviews/' ),    'icon' => 'check' ),
				array( 'slug' => 'settings',  'name' => __( 'Settings', 'buckleup' ),  'href' => home_url( '/admin/settings/' ),   'icon' => 'monitor' ),
			),
		),
	);
	return $configs[ $role ] ?? $configs['student'];
}

/**
 * Render one sidebar nav (shared by desktop sidebar + mobile drawer).
 *
 * @param array  $nav    Nav items from buckleup_console_config().
 * @param string $active Active item slug.
 * @param string $group  Magic-move group id (e.g. "console-student").
 */
function buckleup_console_nav_html( array $nav, string $active, string $group ): string {
	$out = '';
	foreach ( $nav as $item ) {
		$is_active = ( $item['slug'] === $active );
		$out .= sprintf(
			'<a href="%1$s" class="relative flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-colors %2$s">%3$s%4$s<span class="relative z-10 font-medium">%5$s</span></a>',
			esc_url( $item['href'] ),
			$is_active ? 'text-primary' : 'text-muted-foreground hover:text-foreground hover:bg-muted/50',
			$is_active ? '<span class="absolute inset-0 bg-primary/10 border border-primary/20 rounded-xl"></span>' : '',
			'<span class="relative z-10">' . buckleup_icon( $item['icon'], 'w-5 h-5' ) . '</span>',
			esc_html( $item['name'] )
		);
	}
	return $out;
}

/**
 * Render the full console shell with the page content inside the glass main panel.
 *
 * @param string $role     student|instructor|admin.
 * @param string $active   Active nav slug.
 * @param string $content  Inner page HTML (already escaped by the caller).
 */
function buckleup_console_shell( string $role, string $active, string $content ): string {
	$cfg     = buckleup_console_config( $role );
	$user    = wp_get_current_user();
	$name    = $user && $user->exists() ? ( $user->display_name ?: $user->user_login ) : '';
	$logout  = wp_logout_url( home_url() );
	$group   = 'console-' . $role;
	$nav_d   = buckleup_console_nav_html( $cfg['nav'], $active, $group );

	$brand = sprintf(
		'<a href="%1$s" class="flex items-center gap-3">'
			. '<span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-accent text-primary-foreground font-bold shadow-md shrink-0">B</span>'
			. '<span class="min-w-0"><span class="font-bold text-foreground text-lg block leading-tight">%2$s</span>'
			. '<span class="text-xs text-muted-foreground truncate max-w-[140px] block">%3$s</span></span></a>',
		esc_url( home_url( '/' ) ),
		esc_html( $cfg['label'] ),
		esc_html( $name )
	);

	$signout = sprintf(
		'<a href="%1$s" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors">%2$s<span>%3$s</span></a>',
		esc_url( $logout ),
		buckleup_icon( 'log-out', 'w-5 h-5' ),
		esc_html__( 'Sign out', 'buckleup' )
	);

	ob_start();
	?>
	<!-- wp:html -->
	<div class="min-h-screen bg-background flex" data-console>

		<!-- Desktop sidebar -->
		<aside class="w-64 border-r border-border glass hidden md:flex flex-col fixed h-full z-40">
			<div class="p-6 border-b border-border"><?php echo $brand; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<nav data-tabs="<?php echo esc_attr( $group ); ?>" class="flex-1 p-4 space-y-1 overflow-y-auto"><?php echo $nav_d; // phpcs:ignore WordPress.Security.EscapeOutput ?></nav>
			<div class="p-4 border-t border-border"><?php echo $signout; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		</aside>

		<!-- Mobile top bar -->
		<div class="md:hidden fixed top-0 left-0 right-0 z-40 glass border-b border-border px-4 py-3 flex items-center justify-between">
			<button type="button" data-console-toggle aria-label="<?php esc_attr_e( 'Open menu', 'buckleup' ); ?>" class="p-2 rounded-lg hover:bg-muted"><?php echo buckleup_icon( 'menu', 'w-6 h-6' ); // phpcs:ignore ?></button>
			<span class="font-bold text-foreground"><?php echo esc_html( $cfg['label'] ); ?></span>
			<button type="button" data-theme-toggle aria-label="<?php esc_attr_e( 'Toggle theme', 'buckleup' ); ?>" class="p-2 rounded-lg hover:bg-muted">
				<span class="hidden dark:inline"><?php echo buckleup_icon( 'sun', 'w-5 h-5' ); // phpcs:ignore ?></span>
				<span class="inline dark:hidden"><?php echo buckleup_icon( 'moon', 'w-5 h-5' ); // phpcs:ignore ?></span>
			</button>
		</div>

		<!-- Mobile drawer -->
		<div data-console-drawer data-state="closed" hidden>
			<div data-console-overlay class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[60] md:hidden"></div>
			<aside class="fixed top-0 left-0 bottom-0 w-72 bg-card border-r border-border z-[70] md:hidden flex flex-col">
				<div class="p-6 border-b border-border flex items-center justify-between">
					<?php echo $brand; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<button type="button" data-console-close aria-label="<?php esc_attr_e( 'Close menu', 'buckleup' ); ?>" class="p-2 hover:bg-muted rounded-lg"><?php echo buckleup_icon( 'x', 'w-5 h-5' ); // phpcs:ignore ?></button>
				</div>
				<nav class="flex-1 p-4 space-y-1 overflow-y-auto"><?php echo buckleup_console_nav_html( $cfg['nav'], $active, $group . '-m' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></nav>
				<div class="p-4 border-t border-border"><?php echo $signout; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			</aside>
		</div>

		<!-- Main -->
		<main class="flex-1 md:ml-64 pt-16 md:pt-0 min-w-0">
			<div class="p-4 md:p-8 max-w-6xl mx-auto">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput — caller-escaped page markup ?>
			</div>
		</main>
	</div>
	<!-- /wp:html -->
	<?php
	return (string) ob_get_clean();
}

/**
 * Console page heading block (title + optional subline) — used at the top of each
 * console page's content for consistent typography.
 */
function buckleup_console_heading( string $title, string $subline = '' ): string {
	$out = '<div class="mb-8"><h1 class="text-2xl md:text-3xl font-bold text-foreground">' . esc_html( $title ) . '</h1>';
	if ( '' !== $subline ) {
		$out .= '<p class="text-muted-foreground mt-1">' . esc_html( $subline ) . '</p>';
	}
	return $out . '</div>';
}
