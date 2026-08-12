<?php
/**
 * Elementor interop for the marketing page bodies — EXACT-MIRROR model.
 *
 * The `buckleup` block theme stays active and OWNS the design system: the
 * compiled Tailwind bundle (buckleup-app) + the vanilla-JS interaction layer
 * (buckleup-main) load site-wide via functions.php, exactly like the live site.
 * We do NOT dequeue them on Elementor pages — Elementor only authors the page
 * *body*, while the real Tailwind chrome (header/footer/hero patterns) and all
 * the bespoke utilities (.glass, .gradient-text, .glow-*, card/button styles)
 * + the JS animations (data-reveal, scroll-aware navbar, Locations dropdown,
 * mouse-tilt hero, lightbox) keep working on top of / alongside Elementor.
 *
 * So this file no longer dequeues anything. It still:
 *   - provides [buckleup_elementor id="N"] to embed a library template, and
 *   - force-enqueues Elementor's own frontend CSS (styles only, not JS) site-wide
 *     so the embedded footer Container lays out correctly on every view, including
 *     non-Elementor pages (consoles, blog) that render the shared footer, and
 *   - enqueues the tiny .bu-pill/.bu-hug helper overrides, and
 *   - keeps a defensive Geist @font-face fallback for the (rare) case where the
 *     Tailwind bundle didn't build — the bundle already ships @font-face, so on
 *     a normal build this is a harmless identical re-declaration.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Is the current main-query singular page built with Elementor?
 *
 * Retained as a guarded utility (e.g. for conditional logic elsewhere) even
 * though we no longer dequeue on Elementor pages. Safe when Elementor is
 * inactive. Front-end singular only — archives/feeds carry no Elementor document.
 */
function buckleup_is_elementor_page(): bool {
	if ( is_admin() || ! is_singular() ) {
		return false;
	}
	if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
		return false;
	}
	$id = get_the_ID();
	if ( ! $id ) {
		return false;
	}
	$documents = \Elementor\Plugin::$instance->documents ?? null;
	if ( ! $documents || ! method_exists( $documents, 'get' ) ) {
		return false;
	}
	$document = $documents->get( $id );
	return $document && method_exists( $document, 'is_built_with_elementor' )
		&& $document->is_built_with_elementor();
}

/**
 * Defensive Geist @font-face fallback.
 *
 * The compiled Tailwind bundle (buckleup-app) already ships these @font-face
 * rules and loads site-wide, so on a normal build this is redundant. We keep it
 * only as a safety net for the no-build / fallback path (when buckleup-app isn't
 * available). Registered as a sourceless handle; rules attached via
 * wp_add_inline_style with manifest-resolved hashed woff2 URLs (falls back to the
 * unhashed copies in assets/fonts/).
 */
add_action( 'wp_enqueue_scripts', function () {
	$sans = buckleup_vite_asset( 'src/fonts/Geist-Variable.woff2' )
		?: get_theme_file_uri( 'assets/fonts/Geist-Variable.woff2' );

	wp_register_style( 'buckleup-geist-fonts', false, array(), BUCKLEUP_VERSION );
	wp_enqueue_style( 'buckleup-geist-fonts' );

	// Geist Sans only (variable, full 100..900 axis), display:swap — mirrors
	// src/css/app.css. Geist Mono is no longer self-hosted (font-mono is unused);
	// --font-geist-mono maps to a system monospace stack in the Tailwind bundle.
	$css = sprintf(
		'@font-face{font-family:"Geist";src:url("%s") format("woff2");font-weight:100 900;font-style:normal;font-display:swap}',
		esc_url( $sans )
	);
	wp_add_inline_style( 'buckleup-geist-fonts', $css );
}, 5 );

/**
 * Render an Elementor library template by ID: [buckleup_elementor id="N"].
 *
 * Used to embed the shared "Site Header" / "Site Footer" Elementor templates into
 * the block template parts (parts/header.html, parts/footer.html) so they appear
 * site-wide — including on non-Elementor views (consoles, blog). Elementor's
 * frontend ->get_builder_content_for_display() registers/enqueues the template's
 * own widget assets; we additionally force-enqueue Elementor's core frontend CSS
 * (see below) so the layout/typography render correctly off an Elementor page.
 */
add_shortcode( 'buckleup_elementor', function ( $atts ): string {
	$atts = shortcode_atts( array( 'id' => 0, 'slug' => '' ), $atts, 'buckleup_elementor' );
	$id   = (int) $atts['id'];
	// Prefer slug: library-template ids are assigned at build time and differ between
	// installs, so a hard-coded id silently renders nothing on a rebuilt database.
	if ( $atts['slug'] ) {
		$tpl = get_page_by_path( $atts['slug'], OBJECT, 'elementor_library' );
		if ( $tpl ) {
			$id = (int) $tpl->ID;
		}
	}
	if ( ! $id || ! class_exists( '\\Elementor\\Plugin' ) ) {
		return '';
	}
	$frontend = \Elementor\Plugin::$instance->frontend ?? null;
	if ( ! $frontend || ! method_exists( $frontend, 'get_builder_content_for_display' ) ) {
		return '';
	}
	return $frontend->get_builder_content_for_display( $id );
} );

/**
 * Render a real theme section by name inside Elementor: [buckleup_section name="X"].
 *
 * For the bespoke sections that are hard to reproduce 1:1 in Elementor (e.g. the
 * Graduates "Hall of Fame" horizontal snap-scroll rail), an Elementor Shortcode
 * widget can embed the REAL theme pattern so it's a pixel-exact mirror and stays
 * CPT-editable. Delegates to the existing allowlisted renderer (inc/site.php) —
 * only names in buckleup_sections() resolve; anything else returns ''. The
 * buckleup-main JS (rail + lightbox) loads site-wide now, so the interactions work.
 */
add_shortcode( 'buckleup_section', function ( $atts ): string {
	$a = shortcode_atts( array( 'name' => '' ), $atts, 'buckleup_section' );
	return function_exists( 'buckleup_render_section_block' )
		? buckleup_render_section_block( array( 'name' => $a['name'] ) )
		: '';
} );

/**
 * Contact FORM (only) for the Elementor Contact page: [buckleup_contact_form].
 *
 * Outputs the exact <form data-contact-form> block currently inlined in
 * patterns/page-contact.php — same action=buckleup_contact, nonce
 * `buckleup_contact_nonce`, honeypot `website`, fields (first_name/last_name/
 * email/phone/subject/message), the `data-contact-status` div + submit button,
 * using the same buckleup_input_class/label_class/textarea_class/buckleup_button
 * helpers. So the unchanged buckleup-core admin-post handler (Zoho SMTP, branded
 * email) and main.js's fetch enhancement keep working. The surrounding marketing
 * layout is authored in Elementor — this renders ONLY the form (+ status div).
 */
add_shortcode( 'buckleup_contact_form', function (): string {
	if ( ! function_exists( 'buckleup_input_class' ) || ! function_exists( 'buckleup_button' ) ) {
		return '';
	}
	// Read-only status flag mirrored from the no-JS redirect (?contact=success|error).
	$contact_state = isset( $_GET['contact'] ) ? sanitize_key( wp_unslash( $_GET['contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only status flag, no state change

	ob_start();
	?>
	<div data-contact-form-slot>
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
	<?php
	return (string) ob_get_clean();
} );

/**
 * The shared footer renders on every front-end view via the shortcode in
 * parts/footer.html, but on a NON-Elementor page (a console, the blog) Elementor
 * wouldn't otherwise decide the page "needs" its frontend assets. Force-enqueue
 * Elementor's core frontend *CSS* site-wide so the embedded footer Container +
 * its widgets lay out correctly everywhere.
 *
 * CSS ONLY — we deliberately do NOT force-enqueue Elementor's frontend JS
 * (elementor-frontend.js / frontend-modules / webpack.runtime) site-wide. The
 * embedded footer (Elementor post 164) is static columns/links (the Trustindex
 * review widget was removed), so it needs Elementor's CSS to lay out but no JS to
 * behave. Real Elementor pages (the marketing page bodies) still auto-enqueue
 * their OWN frontend JS via Elementor's normal render path — this closure only
 * covers the site-wide footer, which doesn't need it.
 *
 * The old reason for also calling ->enqueue_scripts() here — kicking off the
 * ElementsKit nav-menu widget's init chain (ekit-widget-scripts, hooked on
 * Elementor's `after_(register|enqueue)_scripts`) — is now VESTIGIAL: the shared
 * header is native theme markup + the theme's own vanilla-JS navbar, not an
 * ElementsKit nav widget, so no Elementor JS is needed for the chrome to work.
 *
 * No-op when Elementor is inactive. On true Elementor pages Elementor enqueues
 * the CSS itself; calling again is idempotent. Priority 9 keeps it ahead of our
 * dequeue at 100, which only touches the theme's own buckleup-* handles, never
 * Elementor's/ElementsKit's.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! class_exists( '\\Elementor\\Plugin' ) ) {
		return;
	}
	$frontend = \Elementor\Plugin::$instance->frontend ?? null;
	if ( $frontend ) {
		if ( method_exists( $frontend, 'enqueue_styles' ) ) {
			$frontend->enqueue_styles();
		}
		// NOTE: intentionally NOT calling $frontend->enqueue_scripts() here. The
		// site-wide footer is static and needs no Elementor JS; real Elementor
		// marketing pages still auto-enqueue their own frontend JS via Elementor's
		// render path. (See the docblock above re: the now-vestigial ElementsKit
		// nav-init reason — the header is native theme markup + JS.)
	}

	// Tiny overrides for things free-Elementor controls can't express (e.g. the
	// .bu-pill / .bu-hug "hug content" widths). Site-wide so header/footer template
	// pills + columns are covered too. Versioned by filemtime for cache-busting.
	$fixes = get_theme_file_path( 'assets/css/elementor-buckleup.css' );
	if ( file_exists( $fixes ) ) {
		wp_enqueue_style(
			'buckleup-elementor-fixes',
			get_theme_file_uri( 'assets/css/elementor-buckleup.css' ),
			array(),
			(string) filemtime( $fixes )
		);
	}
}, 9 );
