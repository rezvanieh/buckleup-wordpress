<?php
/**
 * Title: Page: Login
 * Slug: buckleup/page-login
 * Inserter: no
 *
 * Branded login card matching src/app/auth/login/LoginForm.tsx (social login
 * OMITTED — deferred). Posts to NATIVE wp-login.php with log/pwd/rememberme/
 * redirect_to (frozen contract); the plugin's login_redirect routes to the role
 * dashboard and bounces failures to /login/?login=failed. Reads ?registered=true
 * and ?login=failed for the banners and ?callbackUrl for the hidden redirect_to.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:disable WordPress.Security.NonceVerification.Recommended — read-only GET flags on a public login screen.
$registered  = isset( $_GET['registered'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['registered'] ) );
$login_fail  = isset( $_GET['login'] ) && 'failed' === sanitize_text_field( wp_unslash( $_GET['login'] ) );
$callback    = isset( $_GET['callbackUrl'] ) ? esc_url_raw( wp_unslash( $_GET['callbackUrl'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$logo        = buckleup_asset_url( 'logo.png' );
?>
<!-- wp:html -->
<section class="min-h-[80vh] flex items-center justify-center p-4">
	<div class="w-full max-w-md">
		<!-- Brand -->
		<div class="text-center mb-8">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="inline-flex items-center gap-2 mb-4">
				<?php if ( $logo ) : ?>
					<img src="<?php echo esc_url( $logo ); ?>" alt="BuckleUp Driving School" width="48" height="48" class="rounded-xl shadow-lg" data-logo data-logo-light="<?php echo esc_url( $logo ); ?>" data-logo-dark="<?php echo esc_url( buckleup_asset_url( 'logo-dark.png' ) ?: $logo ); ?>">
				<?php else : ?>
					<span class="text-2xl font-bold tracking-tight text-foreground">BuckleUp</span>
				<?php endif; ?>
			</a>
			<h1 class="text-3xl font-bold mb-2 text-foreground"><?php esc_html_e( 'Welcome Back', 'buckleup' ); ?></h1>
			<p class="text-muted-foreground"><?php esc_html_e( 'Sign in to your account to continue', 'buckleup' ); ?></p>
		</div>

		<?php if ( $registered ) : ?>
			<div role="status" class="mb-4 rounded-lg px-4 py-3 text-sm bg-accent/10 text-accent border border-accent/20"><?php esc_html_e( 'Account created successfully! Please sign in.', 'buckleup' ); ?></div>
		<?php endif; ?>
		<?php if ( $login_fail ) : ?>
			<div role="alert" class="mb-4 rounded-lg px-4 py-3 text-sm bg-destructive/10 text-destructive border border-destructive/20"><?php esc_html_e( 'Invalid email or password', 'buckleup' ); ?></div>
		<?php endif; ?>

		<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8' ) ); ?>">
			<form method="post" action="<?php echo esc_url( wp_login_url() ); ?>" class="space-y-5">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $callback ); ?>">

				<div>
					<label for="bu-login-email" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Email', 'buckleup' ); ?></label>
					<div class="relative">
						<span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"><?php echo buckleup_icon( 'mail', 'w-4 h-4' ); // phpcs:ignore ?></span>
						<input id="bu-login-email" name="log" type="email" required autocomplete="email" placeholder="your@email.com" class="<?php echo esc_attr( buckleup_input_class( 'pl-10' ) ); ?>">
					</div>
				</div>

				<div>
					<label for="bu-login-pwd" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Password', 'buckleup' ); ?></label>
					<div class="relative" data-password-field>
						<span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"><?php echo buckleup_icon( 'lock', 'w-4 h-4' ); // phpcs:ignore ?></span>
						<input id="bu-login-pwd" name="pwd" type="password" required autocomplete="current-password" placeholder="••••••••" class="<?php echo esc_attr( buckleup_input_class( 'pl-10 pr-10' ) ); ?>">
						<button type="button" data-password-toggle aria-label="<?php esc_attr_e( 'Show password', 'buckleup' ); ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
							<span data-pw-show><?php echo buckleup_icon( 'eye', 'w-4 h-4' ); // phpcs:ignore ?></span>
							<span data-pw-hide hidden><?php echo buckleup_icon( 'eye-off', 'w-4 h-4' ); // phpcs:ignore ?></span>
						</button>
					</div>
				</div>

				<div class="flex items-center justify-between">
					<label class="flex items-center gap-2 text-sm text-muted-foreground cursor-pointer">
						<input type="checkbox" name="rememberme" value="forever" class="rounded border-input text-primary focus:ring-ring">
						<?php esc_html_e( 'Remember me', 'buckleup' ); ?>
					</label>
					<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="text-sm font-medium text-primary hover:underline"><?php esc_html_e( 'Forgot password?', 'buckleup' ); ?></a>
				</div>

				<?php
				buckleup_button( array(
					'label' => __( 'Sign in', 'buckleup' ),
					'size'  => 'lg',
					'class' => 'w-full',
					'attrs' => array( 'type' => 'submit' ),
				) );
				?>
			</form>

			<p class="text-center text-sm text-muted-foreground mt-6">
				<?php esc_html_e( "Don't have an account?", 'buckleup' ); ?>
				<a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="font-medium text-primary hover:underline"><?php esc_html_e( 'Sign up', 'buckleup' ); ?></a>
			</p>
		</div>
	</div>
</section>
<!-- /wp:html -->
