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

// 4 info cards matching production: Phone / Email / Office / Hours.
$methods = array(
	array( 'icon' => 'phone',   'label' => __( 'Phone', 'buckleup' ),  'value' => $phone,                          'desc' => __( 'Mon–Sun, 9am–6pm PST', 'buckleup' ),      'href' => 'tel:' . $phone_e164 ),
	array( 'icon' => 'mail',    'label' => __( 'Email', 'buckleup' ),  'value' => $email,                          'desc' => __( 'We reply within 24 hours', 'buckleup' ),  'href' => 'mailto:' . $email ),
	array( 'icon' => 'map-pin', 'label' => __( 'Office', 'buckleup' ), 'value' => $get( 'street_address', '136 Maple Dr' ), 'desc' => __( 'Port Moody, BC V3H 0A8, Canada', 'buckleup' ), 'href' => 'https://maps.google.com/maps?q=136+Maple+Dr,+Port+Moody,+BC+V3H+0A8,+Canada' ),
	array( 'icon' => 'clock',   'label' => __( 'Hours', 'buckleup' ),  'value' => __( 'Mon – Sun', 'buckleup' ),   'desc' => __( '9:00 AM – 6:00 PM', 'buckleup' ),         'href' => null ),
);

$quick = array(
	__( 'How do I book my first lesson?', 'buckleup' ),
	__( 'What packages do you offer?', 'buckleup' ),
	__( 'Where do lessons start and finish?', 'buckleup' ),
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
				<span class="text-foreground"><?php esc_html_e( "We'd Love to ", 'buckleup' ); ?></span><span class="gradient-text"><?php esc_html_e( 'Hear From You', 'buckleup' ); ?></span>
			</h1>
			<p data-reveal class="text-muted-foreground mt-4"><?php esc_html_e( 'Have questions about our driving lessons? Ready to start your journey?', 'buckleup' ); ?></p>
		</div>

		<!-- Contact-info cards: Phone / Email / Office / Hours -->
		<div data-reveal-stagger="0.05" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto mb-12">
			<?php foreach ( $methods as $m ) :
				$tag   = $m['href'] ? 'a' : 'div';
				$attrs = $m['href'] ? ' href="' . esc_url( $m['href'] ) . '"' . ( false !== strpos( (string) $m['href'], 'maps.google' ) ? ' target="_blank" rel="noopener"' : '' ) : '';
				?>
				<<?php echo esc_html( $tag ); ?><?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput — built from esc_url above ?> data-reveal
					class="<?php echo esc_attr( buckleup_card_class( 'p-6 items-center text-center hover-lift card-highlight' ) ); ?>">
					<div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center mx-auto mb-3"><?php echo buckleup_icon( $m['icon'], 'h-5 w-5 text-primary' ); // phpcs:ignore ?></div>
					<div class="font-semibold text-foreground"><?php echo esc_html( $m['label'] ); ?></div>
					<div class="text-sm text-foreground mt-1"><?php echo esc_html( $m['value'] ); ?></div>
					<div class="text-xs text-muted-foreground mt-0.5"><?php echo esc_html( $m['desc'] ); ?></div>
				</<?php echo esc_html( $tag ); ?>>
			<?php endforeach; ?>
		</div>

		<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto">
			<!-- Send us a Message: quick-question chips at TOP, then the form -->
			<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8' ) ); ?>">
				<h2 class="text-2xl font-bold text-foreground mb-1"><?php esc_html_e( 'Send us a Message', 'buckleup' ); ?></h2>
				<p class="text-sm text-muted-foreground mb-5"><?php esc_html_e( 'Have a quick question? Tap one to message us on WhatsApp, or fill out the form below.', 'buckleup' ); ?></p>

				<!-- Quick-question chips (top of the form) -->
				<div class="flex flex-wrap gap-2 mb-6 pb-6 border-b border-border">
					<?php foreach ( $quick as $q ) : ?>
						<a href="<?php echo esc_url( $wa_link . '?text=' . rawurlencode( $q ) ); ?>" target="_blank" rel="noopener"
							class="<?php echo esc_attr( buckleup_pill_class( 'muted', 'hover:text-primary hover:border-primary/30 transition-colors' ) ); ?>">
							<?php echo esc_html( $q ); ?>
						</a>
					<?php endforeach; ?>
				</div>

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
			</div>

			<!-- Right column: map + Visit FAQ + Fast Response -->
			<div class="space-y-6">
				<!-- Map -->
				<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'overflow-hidden p-0' ) ); ?>">
					<iframe title="<?php esc_attr_e( 'BuckleUp Driving School location map', 'buckleup' ); ?>" class="w-full h-[320px] border-0"
						src="https://maps.google.com/maps?q=<?php echo esc_attr( $map_q ); ?>&t=&z=15&ie=UTF8&iwloc=&output=embed"
						loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
					<a href="https://maps.google.com/maps?q=<?php echo esc_attr( $map_q ); ?>" target="_blank" rel="noopener"
						class="flex items-center justify-center gap-2 p-4 text-sm font-medium text-primary hover:underline">
						<?php echo buckleup_icon( 'map-pin', 'h-4 w-4' ); // phpcs:ignore ?>
						<?php esc_html_e( 'Get directions', 'buckleup' ); ?>
					</a>
				</div>

				<!-- Visit FAQ card -->
				<a data-reveal href="<?php echo esc_url( home_url( '/#faq' ) ); ?>" class="<?php echo esc_attr( buckleup_card_class( 'p-6 hover-lift card-highlight' ) ); ?>">
					<div class="flex items-start gap-4">
						<div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0"><?php echo buckleup_icon( 'message-circle', 'w-5 h-5 text-primary' ); // phpcs:ignore ?></div>
						<div>
							<h3 class="font-semibold text-foreground mb-1 inline-flex items-center gap-1"><?php esc_html_e( 'Visit FAQ', 'buckleup' ); ?><?php echo buckleup_icon( 'arrow-right', 'w-4 h-4' ); // phpcs:ignore ?></h3>
							<p class="text-sm text-muted-foreground"><?php esc_html_e( 'Find quick answers to the most common questions about lessons, pricing, and road tests.', 'buckleup' ); ?></p>
						</div>
					</div>
				</a>

				<!-- Fast Response Time card -->
				<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
					<div class="flex items-start gap-4">
						<div class="w-10 h-10 rounded-full bg-accent/10 flex items-center justify-center shrink-0"><?php echo buckleup_icon( 'clock', 'w-5 h-5 text-accent' ); // phpcs:ignore ?></div>
						<div>
							<h3 class="font-semibold text-foreground mb-1"><?php esc_html_e( 'Fast Response Time', 'buckleup' ); ?></h3>
							<p class="text-sm text-muted-foreground"><?php esc_html_e( 'We typically respond to all inquiries within 2–4 hours during business hours.', 'buckleup' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
<!-- /wp:html -->
