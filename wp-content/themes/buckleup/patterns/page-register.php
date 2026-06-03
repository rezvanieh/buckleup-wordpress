<?php
/**
 * Title: Page: Register
 * Slug: buckleup/page-register
 * Inserter: no
 *
 * Branded "Create Account" card matching src/app/auth/register (social OMITTED).
 * Submits via JS fetch → POST /wp-json/buckleup/v1/auth/register with the wp_rest
 * nonce (localized to window.buckleupAuth.nonce). 201 → /login/?registered=true;
 * non-2xx → show data.error. Client-validates pw≥8 + match before the request.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$logo = buckleup_asset_url( 'logo.png' );
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
			<h1 class="text-3xl font-bold mb-2 text-foreground"><?php esc_html_e( 'Create Account', 'buckleup' ); ?></h1>
			<p class="text-muted-foreground"><?php esc_html_e( 'Start your driving journey today', 'buckleup' ); ?></p>
		</div>

		<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8' ) ); ?>">
			<div data-register-status role="alert" class="mb-4 rounded-lg px-4 py-3 text-sm bg-destructive/10 text-destructive border border-destructive/20 hidden" hidden></div>

			<form data-register-form class="space-y-4" novalidate>
				<div>
					<label for="bu-reg-name" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Full name', 'buckleup' ); ?></label>
					<input id="bu-reg-name" name="name" type="text" required autocomplete="name" placeholder="John Doe" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
				</div>
				<div>
					<label for="bu-reg-email" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Email', 'buckleup' ); ?></label>
					<input id="bu-reg-email" name="email" type="email" required autocomplete="email" placeholder="your@email.com" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
				</div>
				<div>
					<label for="bu-reg-phone" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Phone (optional)', 'buckleup' ); ?></label>
					<input id="bu-reg-phone" name="phone" type="tel" autocomplete="tel" placeholder="(604) 555-0123" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
				</div>
				<div>
					<label for="bu-reg-pwd" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Password', 'buckleup' ); ?></label>
					<div class="relative" data-password-field>
						<input id="bu-reg-pwd" name="password" type="password" required autocomplete="new-password" minlength="8" placeholder="<?php esc_attr_e( 'At least 8 characters', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class( 'pr-10' ) ); ?>">
						<button type="button" data-password-toggle aria-label="<?php esc_attr_e( 'Show password', 'buckleup' ); ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
							<span data-pw-show><?php echo buckleup_icon( 'eye', 'w-4 h-4' ); // phpcs:ignore ?></span>
							<span data-pw-hide hidden><?php echo buckleup_icon( 'eye-off', 'w-4 h-4' ); // phpcs:ignore ?></span>
						</button>
					</div>
				</div>
				<div>
					<label for="bu-reg-confirm" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Confirm password', 'buckleup' ); ?></label>
					<div class="relative" data-password-field>
						<input id="bu-reg-confirm" name="confirm" type="password" required autocomplete="new-password" placeholder="••••••••" class="<?php echo esc_attr( buckleup_input_class( 'pr-10' ) ); ?>">
						<button type="button" data-password-toggle aria-label="<?php esc_attr_e( 'Show password', 'buckleup' ); ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
							<span data-pw-show><?php echo buckleup_icon( 'eye', 'w-4 h-4' ); // phpcs:ignore ?></span>
							<span data-pw-hide hidden><?php echo buckleup_icon( 'eye-off', 'w-4 h-4' ); // phpcs:ignore ?></span>
						</button>
					</div>
				</div>

				<?php
				buckleup_button( array(
					'label' => __( 'Create account', 'buckleup' ),
					'size'  => 'lg',
					'class' => 'w-full',
					'attrs' => array( 'type' => 'submit', 'data-register-submit' => true ),
				) );
				?>
			</form>

			<p class="text-center text-sm text-muted-foreground mt-6">
				<?php esc_html_e( 'Already have an account?', 'buckleup' ); ?>
				<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="font-medium text-primary hover:underline"><?php esc_html_e( 'Sign in', 'buckleup' ); ?></a>
			</p>
		</div>
	</div>
</section>
<!-- /wp:html -->
