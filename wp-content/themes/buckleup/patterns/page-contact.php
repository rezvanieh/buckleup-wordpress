<?php
/**
 * Title: Page: Contact
 * Slug: buckleup/page-contact
 * Inserter: no
 *
 * Contact page: "Get in Touch" eyebrow + heading, contact-method cards (phone /
 * email / WhatsApp from settings), the WhatsApp quick-question chips (each opens
 * wa.me with the question prefilled), and the Google Maps embed for the NAP. The
 * actual contact FORM is a WPForms embed authored in the page body (rendered by
 * page.html's post-content) — placed via the [contact] / WPForms block by the
 * content team; this pattern supplies the surrounding marketing layout. Verbatim
 * map URL + NAP from PLAN §4 / source contact page.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$get = static function ( $k, $d = '' ) {
	return function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( $k, $d ) : $d;
};

$phone      = $get( 'phone', '(604) 441-3677' );
$phone_e164 = $get( 'phone_e164', '+16044413677' );
$email      = $get( 'email', 'info@buckleupdriving.ca' );
$wa         = preg_replace( '/\D/', '', $get( 'whatsapp', '16044413677' ) );
$wa_link    = 'https://wa.me/' . $wa;
$map_q      = '136+Maple+Dr,+Port+Moody,+BC+V3H+0A8,+Canada';

$methods = array(
	array( 'icon' => 'phone',          'label' => __( 'Call Us', 'buckleup' ),        'value' => $phone, 'href' => 'tel:' . $phone_e164 ),
	array( 'icon' => 'mail',           'label' => __( 'Email Us', 'buckleup' ),       'value' => $email, 'href' => 'mailto:' . $email ),
	array( 'icon' => 'message-circle', 'label' => __( 'WhatsApp', 'buckleup' ),       'value' => __( 'Chat with us', 'buckleup' ), 'href' => $wa_link ),
);

$quick = array(
	__( 'How do I book my first lesson?', 'buckleup' ),
	__( 'What packages do you offer?', 'buckleup' ),
	__( 'Do you provide pick-up service?', 'buckleup' ),
	__( 'How long until I can get my license?', 'buckleup' ),
);
?>
<!-- wp:html -->
<section class="py-16 md:py-24">
	<div class="container mx-auto px-4">
		<div class="text-center mb-12 max-w-2xl mx-auto">
			<div data-reveal class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full glass border border-border/50 mb-4">
				<?php echo buckleup_icon( 'message-circle', 'w-4 h-4 text-primary' ); // phpcs:ignore ?>
				<span class="text-sm font-medium text-primary"><?php esc_html_e( 'Get in Touch', 'buckleup' ); ?></span>
			</div>
			<h1 data-reveal class="text-4xl md:text-5xl font-bold tracking-tight">
				<span class="text-foreground"><?php esc_html_e( "Let's Get You ", 'buckleup' ); ?></span><span class="gradient-text"><?php esc_html_e( 'On the Road', 'buckleup' ); ?></span>
			</h1>
			<p data-reveal class="text-muted-foreground mt-4"><?php esc_html_e( 'Questions about lessons, packages, or scheduling? Reach out — we usually reply within a few hours.', 'buckleup' ); ?></p>
		</div>

		<!-- Contact-method cards -->
		<div data-reveal-stagger="0.05" class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto mb-12">
			<?php foreach ( $methods as $m ) : ?>
				<a data-reveal href="<?php echo esc_url( $m['href'] ); ?>"<?php echo 'message-circle' === $m['icon'] ? ' target="_blank" rel="noopener"' : ''; ?>
					class="<?php echo esc_attr( buckleup_card_class( 'p-6 items-center text-center hover-lift card-highlight' ) ); ?>">
					<div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center mx-auto mb-3"><?php echo buckleup_icon( $m['icon'], 'h-5 w-5 text-primary' ); // phpcs:ignore ?></div>
					<div class="font-semibold text-foreground"><?php echo esc_html( $m['label'] ); ?></div>
					<div class="text-sm text-muted-foreground mt-1"><?php echo esc_html( $m['value'] ); ?></div>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto">
			<!-- Form (WPForms embed lives in the page body / a child block) + quick questions -->
			<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8' ) ); ?>">
				<h2 class="text-xl font-bold text-foreground mb-1"><?php esc_html_e( 'Send us a message', 'buckleup' ); ?></h2>
				<p class="text-sm text-muted-foreground mb-6"><?php esc_html_e( 'Prefer chat? Tap a question below to message us on WhatsApp.', 'buckleup' ); ?></p>

				<!-- Contact form. Works without JS (posts to admin-post.php → redirect with
				     ?contact=success|error); main.js enhances it to a fetch (FormData) →
				     admin-post.php with inline status (no reload). Field names + nonce match
				     the buckleup-core admin-post handler exactly (first_name/last_name/email/
				     phone/subject/message; first_name+email+subject+message required). -->
				<?php
				$contact_state = isset( $_GET['contact'] ) ? sanitize_key( wp_unslash( $_GET['contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only status flag, no state change
				?>
				<div data-contact-form-slot class="mb-6">
					<div data-contact-status role="status" aria-live="polite"
						class="mb-4 rounded-lg px-4 py-3 text-sm <?php echo 'success' === $contact_state ? 'bg-accent/10 text-accent border border-accent/20' : ( 'error' === $contact_state ? 'bg-destructive/10 text-destructive border border-destructive/20' : 'hidden' ); ?>"
						<?php echo $contact_state ? '' : 'hidden'; ?>>
						<?php
						if ( 'success' === $contact_state ) {
							esc_html_e( "Thanks! Your message is on its way — we'll reply soon.", 'buckleup' );
						} elseif ( 'error' === $contact_state ) {
							esc_html_e( 'Sorry, something went wrong. Please try again or reach us on WhatsApp.', 'buckleup' );
						}
						?>
					</div>

					<form data-contact-form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="space-y-4">
						<input type="hidden" name="action" value="buckleup_contact">
						<?php wp_nonce_field( 'buckleup_contact', 'buckleup_contact_nonce' ); ?>
						<!-- Honeypot -->
						<input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

						<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
							<div>
								<label for="bu-first" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'First name', 'buckleup' ); ?></label>
								<input id="bu-first" name="first_name" type="text" required autocomplete="given-name" placeholder="John" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
							</div>
							<div>
								<label for="bu-last" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Last name (optional)', 'buckleup' ); ?></label>
								<input id="bu-last" name="last_name" type="text" autocomplete="family-name" placeholder="Doe" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
							</div>
						</div>
						<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
							<div>
								<label for="bu-email" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Email', 'buckleup' ); ?></label>
								<input id="bu-email" name="email" type="email" required autocomplete="email" placeholder="john@example.com" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
							</div>
							<div>
								<label for="bu-phone" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Phone (optional)', 'buckleup' ); ?></label>
								<input id="bu-phone" name="phone" type="tel" autocomplete="tel" placeholder="(604) 441-3677" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
							</div>
						</div>
						<div>
							<label for="bu-subject" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Subject', 'buckleup' ); ?></label>
							<input id="bu-subject" name="subject" type="text" required placeholder="<?php esc_attr_e( 'What is this regarding?', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
						</div>
						<div>
							<label for="bu-message" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Message', 'buckleup' ); ?></label>
							<textarea id="bu-message" name="message" required rows="5" placeholder="<?php esc_attr_e( 'How can we help you?', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_textarea_class() ); ?>"></textarea>
						</div>
						<?php
						buckleup_button( array(
							'label' => __( 'Send Message', 'buckleup' ),
							'size'  => 'lg',
							'class' => 'w-full',
							'icon'  => buckleup_icon( 'arrow-right', 'ml-2 h-5 w-5' ),
							'attrs' => array( 'type' => 'submit', 'data-contact-submit' => true ),
						) );
						?>
					</form>
				</div>

				<div class="border-t border-border pt-5">
					<h3 class="font-semibold text-foreground mb-3"><?php esc_html_e( 'Looking for quick answers?', 'buckleup' ); ?></h3>
					<div class="flex flex-wrap gap-2">
						<?php foreach ( $quick as $q ) : ?>
							<a href="<?php echo esc_url( $wa_link . '?text=' . rawurlencode( $q ) ); ?>" target="_blank" rel="noopener"
								class="<?php echo esc_attr( buckleup_pill_class( 'muted', 'hover:text-primary hover:border-primary/30 transition-colors' ) ); ?>">
								<?php echo esc_html( $q ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- Map -->
			<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'overflow-hidden p-0' ) ); ?>">
				<iframe title="<?php esc_attr_e( 'BuckleUp Driving School location map', 'buckleup' ); ?>" class="w-full h-[400px] border-0"
					src="https://maps.google.com/maps?q=<?php echo esc_attr( $map_q ); ?>&t=&z=15&ie=UTF8&iwloc=&output=embed"
					loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
				<a href="https://maps.google.com/maps?q=<?php echo esc_attr( $map_q ); ?>" target="_blank" rel="noopener"
					class="flex items-center justify-center gap-2 p-4 text-sm font-medium text-primary hover:underline">
					<?php echo buckleup_icon( 'map-pin', 'h-4 w-4' ); // phpcs:ignore ?>
					<?php esc_html_e( '136 Maple Dr, Port Moody, BC V3H 0A8', 'buckleup' ); ?>
				</a>
			</div>
		</div>
	</div>
</section>
<!-- /wp:html -->
