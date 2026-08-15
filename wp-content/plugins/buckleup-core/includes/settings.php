<?php
/**
 * Site Settings options page (ACF-free, native Settings API).
 *
 * Stores global business data the theme + SEO mu-plugin both read: NAP,
 * opening hours, social handles, and the schema claims (rating, review count,
 * founding year). Everything is one option array, `buckleup_settings`, with
 * sensible defaults seeded from the live site (PLAN.md §4 / §1).
 *
 * Read via buckleup_get_setting() (see includes/helpers.php).
 *
 * @package BuckleUp_Core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const BUCKLEUP_SETTINGS_OPTION = 'buckleup_settings';

/**
 * Default settings — mirror the live site so a fresh install is already
 * brand-correct. The content engineer / admin can override in wp-admin.
 *
 * @return array<string,string>
 */
function buckleup_settings_defaults() {
	return array(
		// NAP.
		'business_name'    => 'BuckleUp Driving School',
		'street_address'   => '136 Maple Dr',
		'address_locality' => 'Port Moody',
		'address_region'   => 'BC',
		'postal_code'      => 'V3H 0A8',
		'address_country'  => 'CA',
		'phone'            => '(604) 441-3677',
		'phone_e164'       => '+16044413677', // for tel: + wa.me.
		'email'            => 'info@buckleupdriving.ca',
		'whatsapp'         => '16044413677',   // wa.me path segment.
		// Hours (single uniform block). Client confirmed 2026-08-15: closes 9pm.
		'hours_open'       => '09:00',
		'hours_close'      => '21:00',
		'hours_display'    => 'Mon–Sun 9am–9pm',

		// Geo.
		'geo_lat'          => '49.2838',
		'geo_lng'          => '-122.8556',

		// Social handles (full URLs).
		'instagram_url'    => 'https://www.instagram.com/budrivingschool',
		'facebook_url'     => 'https://www.facebook.com/DriveMasterca',

		// Schema claims, verified 2026-08-15 against the Google Business Profile:
		// 5.0 stars from 33 reviews. These MUST match the rating shown on-page;
		// a schema-vs-visible mismatch risks a Google review-snippet manual action.
		'rating_value'     => '5.0',
		'review_count'     => '33',
		'founding_year'    => '2014',
		'price_range'      => '$$',
	);
}

/**
 * Field metadata for rendering + sanitizing the settings form.
 * Grouped into sections for the admin UI.
 *
 * @return array<string,array{title:string,fields:array<string,string>}>
 */
function buckleup_settings_sections() {
	return array(
		'nap'    => array(
			'title'  => __( 'Business (NAP)', 'buckleup-core' ),
			'fields' => array(
				'business_name'    => __( 'Business name', 'buckleup-core' ),
				'street_address'   => __( 'Street address', 'buckleup-core' ),
				'address_locality' => __( 'City', 'buckleup-core' ),
				'address_region'   => __( 'Region / province', 'buckleup-core' ),
				'postal_code'      => __( 'Postal code', 'buckleup-core' ),
				'address_country'  => __( 'Country code', 'buckleup-core' ),
				'phone'            => __( 'Phone (display)', 'buckleup-core' ),
				'phone_e164'       => __( 'Phone (E.164, for tel:)', 'buckleup-core' ),
				'email'            => __( 'Email', 'buckleup-core' ),
				'whatsapp'         => __( 'WhatsApp number (wa.me path)', 'buckleup-core' ),
			),
		),
		'hours'  => array(
			'title'  => __( 'Hours & Geo', 'buckleup-core' ),
			'fields' => array(
				'hours_open'    => __( 'Opens (HH:MM)', 'buckleup-core' ),
				'hours_close'   => __( 'Closes (HH:MM)', 'buckleup-core' ),
				'hours_display' => __( 'Hours (display text)', 'buckleup-core' ),
				'geo_lat'       => __( 'Latitude', 'buckleup-core' ),
				'geo_lng'       => __( 'Longitude', 'buckleup-core' ),
			),
		),
		'social' => array(
			'title'  => __( 'Social', 'buckleup-core' ),
			'fields' => array(
				'instagram_url' => __( 'Instagram URL', 'buckleup-core' ),
				'facebook_url'  => __( 'Facebook URL', 'buckleup-core' ),
			),
		),
		'schema' => array(
			'title'  => __( 'Schema claims', 'buckleup-core' ),
			'fields' => array(
				'rating_value'  => __( 'Aggregate rating', 'buckleup-core' ),
				'review_count'  => __( 'Review count', 'buckleup-core' ),
				'founding_year' => __( 'Founding year', 'buckleup-core' ),
				'price_range'   => __( 'Price range', 'buckleup-core' ),
			),
		),
	);
}

/**
 * Register the setting + its sections/fields.
 */
function buckleup_register_settings() {
	register_setting(
		'buckleup_settings_group',
		BUCKLEUP_SETTINGS_OPTION,
		array(
			'type'              => 'object',
			'sanitize_callback' => 'buckleup_sanitize_settings',
			'default'           => buckleup_settings_defaults(),
			'show_in_rest'      => false,
		)
	);

	foreach ( buckleup_settings_sections() as $section_id => $section ) {
		add_settings_section(
			'buckleup_section_' . $section_id,
			$section['title'],
			'__return_false',
			'buckleup-settings'
		);

		foreach ( $section['fields'] as $field_key => $field_label ) {
			add_settings_field(
				$field_key,
				$field_label,
				'buckleup_render_settings_field',
				'buckleup-settings',
				'buckleup_section_' . $section_id,
				array(
					'key'        => $field_key,
					'label_for'  => $field_key,
				)
			);
		}
	}
}
add_action( 'admin_init', 'buckleup_register_settings' );

/**
 * Sanitize the entire settings array on save.
 *
 * URLs go through esc_url_raw; emails through sanitize_email; everything else
 * through sanitize_text_field. Unknown keys are dropped.
 *
 * @param mixed $input Raw submitted value.
 * @return array<string,string>
 */
function buckleup_sanitize_settings( $input ) {
	$defaults = buckleup_settings_defaults();
	$clean    = array();

	if ( ! is_array( $input ) ) {
		$input = array();
	}

	foreach ( $defaults as $key => $default ) {
		$value = isset( $input[ $key ] ) ? $input[ $key ] : $default;

		if ( in_array( $key, array( 'instagram_url', 'facebook_url' ), true ) ) {
			$clean[ $key ] = esc_url_raw( trim( (string) $value ) );
		} elseif ( 'email' === $key ) {
			$clean[ $key ] = sanitize_email( trim( (string) $value ) );
		} else {
			$clean[ $key ] = sanitize_text_field( (string) $value );
		}
	}

	return $clean;
}

/**
 * Render a single text input for a settings field.
 *
 * @param array{key:string} $args Field args.
 */
function buckleup_render_settings_field( $args ) {
	$key      = $args['key'];
	$settings = wp_parse_args( get_option( BUCKLEUP_SETTINGS_OPTION, array() ), buckleup_settings_defaults() );
	$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

	printf(
		'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
		esc_attr( $key ),
		esc_attr( BUCKLEUP_SETTINGS_OPTION ),
		esc_attr( $value )
	);
}

/**
 * Add the Site Settings page under Settings.
 */
function buckleup_add_settings_page() {
	add_options_page(
		__( 'BuckleUp Site Settings', 'buckleup-core' ),
		__( 'BuckleUp Settings', 'buckleup-core' ),
		'manage_options',
		'buckleup-settings',
		'buckleup_render_settings_page'
	);
}
add_action( 'admin_menu', 'buckleup_add_settings_page' );

/**
 * Render the settings page wrapper.
 */
function buckleup_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'buckleup_settings_group' );
			do_settings_sections( 'buckleup-settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Ensure the option row exists with defaults on activation.
 * Called from the activation hook so the theme never reads an empty option.
 */
function buckleup_seed_settings_defaults() {
	if ( false === get_option( BUCKLEUP_SETTINGS_OPTION, false ) ) {
		add_option( BUCKLEUP_SETTINGS_OPTION, buckleup_settings_defaults() );
	}
}
