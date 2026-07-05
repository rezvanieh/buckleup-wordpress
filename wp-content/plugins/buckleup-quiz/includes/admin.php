<?php
/**
 * Admin settings page for the practice-test engine (ACF-free, native Settings API).
 *
 * A single page — Settings → Practice Test — exposing the admin-tunable knobs.
 * Currently one knob: the number of FREE practice attempts an ANONYMOUS visitor
 * may take before being nudged to sign up (logged-in users are always unlimited).
 *
 * Stored in one option array, `buckleup_quiz_settings`. The saved value is layered
 * OVER the code default (includes/config.php) via the `buckleup_quiz_config`
 * filter, which runs everywhere (front-end runner, REST /quiz/status, and the
 * attempt gates), so a missing/empty option cleanly falls back to the shipped
 * default and nothing breaks if the row is absent.
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const BUCKLEUP_QUIZ_SETTINGS_OPTION = 'buckleup_quiz_settings';
const BUCKLEUP_QUIZ_SETTINGS_GROUP  = 'buckleup_quiz_settings_group';
const BUCKLEUP_QUIZ_SETTINGS_PAGE   = 'buckleup-quiz-settings';

/** Sane bounds for the free-attempt cap (guards fat-finger input). */
const BUCKLEUP_QUIZ_MIN_ATTEMPTS = 1;
const BUCKLEUP_QUIZ_MAX_ATTEMPTS = 999;

/**
 * Layer the admin-saved settings over the code defaults. Runs on EVERY request
 * (front-end + REST), not just wp-admin, so the saved cap is authoritative.
 *
 * Reads the option directly (never buckleup_quiz_config()) so there's no filter
 * recursion. An unset/blank value leaves the code default untouched.
 *
 * @param array $config
 * @return array
 */
function buckleup_quiz_apply_saved_settings( $config ) {
	$saved = get_option( BUCKLEUP_QUIZ_SETTINGS_OPTION, array() );
	if ( is_array( $saved ) && isset( $saved['max_attempts'] ) && '' !== $saved['max_attempts'] ) {
		$config['max_attempts'] = buckleup_quiz_clamp_attempts( $saved['max_attempts'] );
	}
	return $config;
}
add_filter( 'buckleup_quiz_config', 'buckleup_quiz_apply_saved_settings' );

/**
 * Clamp a submitted attempt count into the allowed range.
 *
 * @param mixed $value
 * @return int
 */
function buckleup_quiz_clamp_attempts( $value ) {
	return (int) max( BUCKLEUP_QUIZ_MIN_ATTEMPTS, min( BUCKLEUP_QUIZ_MAX_ATTEMPTS, (int) $value ) );
}

/**
 * Register the setting + its section/field.
 */
function buckleup_quiz_register_settings() {
	register_setting(
		BUCKLEUP_QUIZ_SETTINGS_GROUP,
		BUCKLEUP_QUIZ_SETTINGS_OPTION,
		array(
			'type'              => 'object',
			'sanitize_callback' => 'buckleup_quiz_sanitize_settings',
			'default'           => array(),
			'show_in_rest'      => false,
		)
	);

	add_settings_section(
		'buckleup_quiz_section_attempts',
		__( 'Free practice attempts', 'buckleup-quiz' ),
		'buckleup_quiz_settings_section_intro',
		BUCKLEUP_QUIZ_SETTINGS_PAGE
	);

	add_settings_field(
		'max_attempts',
		__( 'Free attempts per visitor', 'buckleup-quiz' ),
		'buckleup_quiz_render_max_attempts_field',
		BUCKLEUP_QUIZ_SETTINGS_PAGE,
		'buckleup_quiz_section_attempts',
		array( 'label_for' => 'buckleup_quiz_max_attempts' )
	);
}
add_action( 'admin_init', 'buckleup_quiz_register_settings' );

/**
 * Section blurb.
 */
function buckleup_quiz_settings_section_intro() {
	echo '<p>' . esc_html__(
		'How many free practice tests an anonymous visitor may complete before being asked to sign up. Signed-in users always have unlimited attempts.',
		'buckleup-quiz'
	) . '</p>';
}

/**
 * Sanitize the settings array on save. Unknown keys are dropped; the cap is
 * clamped to the allowed range.
 *
 * @param mixed $input
 * @return array<string,int>
 */
function buckleup_quiz_sanitize_settings( $input ) {
	if ( ! is_array( $input ) ) {
		$input = array();
	}
	$clean = array();
	if ( isset( $input['max_attempts'] ) && '' !== $input['max_attempts'] ) {
		$clean['max_attempts'] = buckleup_quiz_clamp_attempts( $input['max_attempts'] );
	}
	return $clean;
}

/**
 * Render the number input. The shown value is the CURRENT EFFECTIVE cap
 * (buckleup_quiz_cfg, which already reflects the saved option via our filter),
 * so the form always mirrors what visitors actually get.
 */
function buckleup_quiz_render_max_attempts_field() {
	$value = (int) buckleup_quiz_cfg( 'max_attempts', 15 );
	printf(
		'<input type="number" id="buckleup_quiz_max_attempts" name="%1$s[max_attempts]" value="%2$s" min="%3$d" max="%4$d" step="1" class="small-text" />',
		esc_attr( BUCKLEUP_QUIZ_SETTINGS_OPTION ),
		esc_attr( (string) $value ),
		(int) BUCKLEUP_QUIZ_MIN_ATTEMPTS,
		(int) BUCKLEUP_QUIZ_MAX_ATTEMPTS
	);
	printf(
		'<p class="description">%s</p>',
		esc_html(
			sprintf(
				/* translators: 1: minimum, 2: maximum allowed value */
				__( 'Between %1$d and %2$d. Applies to anonymous visitors only.', 'buckleup-quiz' ),
				(int) BUCKLEUP_QUIZ_MIN_ATTEMPTS,
				(int) BUCKLEUP_QUIZ_MAX_ATTEMPTS
			)
		)
	);
}

/**
 * Add the settings page under the Settings menu.
 */
function buckleup_quiz_add_settings_page() {
	add_options_page(
		__( 'BuckleUp Practice Test Settings', 'buckleup-quiz' ),
		__( 'Practice Test', 'buckleup-quiz' ),
		'manage_options',
		BUCKLEUP_QUIZ_SETTINGS_PAGE,
		'buckleup_quiz_render_settings_page'
	);
}
add_action( 'admin_menu', 'buckleup_quiz_add_settings_page' );

/**
 * Render the settings page wrapper.
 */
function buckleup_quiz_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( BUCKLEUP_QUIZ_SETTINGS_GROUP );
			do_settings_sections( BUCKLEUP_QUIZ_SETTINGS_PAGE );
			submit_button();
			?>
		</form>
	</div>
	<?php
}
