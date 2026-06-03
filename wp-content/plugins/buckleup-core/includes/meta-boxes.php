<?php
/**
 * Native admin meta boxes for the marketing CPTs (no ACF dependency).
 *
 * A small declarative layer: buckleup_meta_box_fields() returns the field
 * config per post type, and one generic render + one generic save handler
 * cover every box. Every save is nonce- and capability-guarded and routes the
 * input through the same sanitizers used by the registered meta.
 *
 * List fields (certifications, languages, package features) are edited as a
 * textarea (one item per line) and stored as repeating single meta rows so
 * they stay aligned with the register_post_meta() definitions in meta.php.
 *
 * @package BuckleUp_Core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Field definitions per CPT.
 *
 * Each field: key (meta key), label, and type. Supported types:
 *   text, number, integer, checkbox, textarea, select (needs `options`),
 *   list (textarea → repeating meta rows).
 *
 * @return array<string,array<int,array<string,mixed>>>
 */
function buckleup_meta_box_fields() {
	return array(
		'graduate'    => array(
			array( 'key' => 'bu_is_active', 'label' => __( 'Show in gallery', 'buckleup-core' ), 'type' => 'checkbox' ),
		),
		'testimonial' => array(
			array( 'key' => 'bu_author_name', 'label' => __( 'Author name', 'buckleup-core' ), 'type' => 'text' ),
			array( 'key' => 'bu_author_role', 'label' => __( 'Author role / result', 'buckleup-core' ), 'type' => 'text', 'placeholder' => 'Passed N Test' ),
			array( 'key' => 'bu_rating', 'label' => __( 'Rating (1-5)', 'buckleup-core' ), 'type' => 'integer', 'min' => 1, 'max' => 5 ),
			array( 'key' => 'bu_is_active', 'label' => __( 'Active', 'buckleup-core' ), 'type' => 'checkbox' ),
		),
		'faq'         => array(
			array( 'key' => 'bu_is_active', 'label' => __( 'Active', 'buckleup-core' ), 'type' => 'checkbox' ),
		),
		'service'     => array(
			array(
				'key'     => 'bu_service_type',
				'label'   => __( 'Type', 'buckleup-core' ),
				'type'    => 'select',
				'options' => array(
					'LESSON'      => 'Lesson',
					'TEST_PREP'   => 'Test Prep',
					'SPECIALIZED' => 'Specialized',
					'REFRESHER'   => 'Refresher',
					'PACKAGE'     => 'Package',
				),
			),
			array( 'key' => 'bu_duration', 'label' => __( 'Duration (minutes)', 'buckleup-core' ), 'type' => 'integer' ),
			array( 'key' => 'bu_price', 'label' => __( 'Price (CAD)', 'buckleup-core' ), 'type' => 'number' ),
			array( 'key' => 'bu_is_active', 'label' => __( 'Active', 'buckleup-core' ), 'type' => 'checkbox' ),
		),
		'package'     => array(
			array( 'key' => 'bu_price', 'label' => __( 'Price (CAD)', 'buckleup-core' ), 'type' => 'number' ),
			array(
				'key'     => 'bu_unit',
				'label'   => __( 'Unit', 'buckleup-core' ),
				'type'    => 'select',
				'options' => array(
					'lesson'  => 'lesson',
					'package' => 'package',
				),
			),
			array( 'key' => 'bu_sessions', 'label' => __( 'Sessions', 'buckleup-core' ), 'type' => 'integer' ),
			array( 'key' => 'bu_hours', 'label' => __( 'Total driving hours', 'buckleup-core' ), 'type' => 'number' ),
			array( 'key' => 'bu_car_fee', 'label' => __( 'Car-on-road-test fee (CAD)', 'buckleup-core' ), 'type' => 'number' ),
			array( 'key' => 'bu_is_popular', 'label' => __( 'Most Popular', 'buckleup-core' ), 'type' => 'checkbox' ),
			array( 'key' => 'bu_is_active', 'label' => __( 'Active', 'buckleup-core' ), 'type' => 'checkbox' ),
			array( 'key' => 'bu_cta_label', 'label' => __( 'CTA label', 'buckleup-core' ), 'type' => 'text', 'placeholder' => 'Get Started' ),
			array( 'key' => 'bu_features', 'label' => __( 'Feature bullets (one per line)', 'buckleup-core' ), 'type' => 'list' ),
		),
		'instructor'  => array(
			array( 'key' => 'bu_role', 'label' => __( 'Role / title', 'buckleup-core' ), 'type' => 'text', 'placeholder' => 'Senior Instructor' ),
			array( 'key' => 'bu_rating', 'label' => __( 'Rating', 'buckleup-core' ), 'type' => 'number' ),
			array( 'key' => 'bu_is_active', 'label' => __( 'Active', 'buckleup-core' ), 'type' => 'checkbox' ),
			array( 'key' => 'bu_certifications', 'label' => __( 'Certifications (one per line)', 'buckleup-core' ), 'type' => 'list' ),
			array( 'key' => 'bu_languages', 'label' => __( 'Languages (one per line)', 'buckleup-core' ), 'type' => 'list' ),
		),
		'location'    => array(
			array( 'key' => 'bu_hero_title', 'label' => __( 'Hero title', 'buckleup-core' ), 'type' => 'text', 'placeholder' => 'Driving Lessons in' ),
			array( 'key' => 'bu_hero_highlight', 'label' => __( 'Hero highlight (gradient span)', 'buckleup-core' ), 'type' => 'text', 'placeholder' => 'Coquitlam' ),
			array( 'key' => 'bu_hero_subtitle', 'label' => __( 'Hero subtitle', 'buckleup-core' ), 'type' => 'textarea' ),
			array( 'key' => 'bu_seo_title', 'label' => __( 'SEO title', 'buckleup-core' ), 'type' => 'text' ),
			array( 'key' => 'bu_seo_description', 'label' => __( 'SEO description', 'buckleup-core' ), 'type' => 'textarea' ),
		),
	);
}

/**
 * Register one "Details" meta box per CPT.
 */
function buckleup_add_meta_boxes() {
	foreach ( array_keys( buckleup_meta_box_fields() ) as $post_type ) {
		add_meta_box(
			'buckleup_details',
			__( 'Details', 'buckleup-core' ),
			'buckleup_render_meta_box',
			$post_type,
			'normal',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'buckleup_add_meta_boxes' );

/**
 * Render the generic details meta box.
 *
 * @param WP_Post $post Current post.
 */
function buckleup_render_meta_box( $post ) {
	$fields = buckleup_meta_box_fields();
	if ( empty( $fields[ $post->post_type ] ) ) {
		return;
	}

	wp_nonce_field( 'buckleup_save_meta', 'buckleup_meta_nonce' );

	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $fields[ $post->post_type ] as $field ) {
		$key   = $field['key'];
		$label = $field['label'];
		$id    = esc_attr( $key );

		echo '<tr>';
		echo '<th scope="row"><label for="' . $id . '">' . esc_html( $label ) . '</label></th>';
		echo '<td>';

		switch ( $field['type'] ) {
			case 'checkbox':
				$value = get_post_meta( $post->ID, $key, true );
				printf(
					'<input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s />',
					$id,
					checked( $value, '1', false )
				);
				break;

			case 'textarea':
				$value = get_post_meta( $post->ID, $key, true );
				printf(
					'<textarea id="%1$s" name="%1$s" rows="3" class="large-text">%2$s</textarea>',
					$id,
					esc_textarea( $value )
				);
				break;

			case 'list':
				// Repeating meta rows shown as newline-separated textarea.
				$values = get_post_meta( $post->ID, $key, false );
				printf(
					'<textarea id="%1$s" name="%1$s" rows="4" class="large-text">%2$s</textarea>',
					$id,
					esc_textarea( implode( "\n", (array) $values ) )
				);
				echo '<p class="description">' . esc_html__( 'One item per line.', 'buckleup-core' ) . '</p>';
				break;

			case 'select':
				$value = get_post_meta( $post->ID, $key, true );
				printf( '<select id="%1$s" name="%1$s">', $id );
				foreach ( $field['options'] as $opt_value => $opt_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $opt_value ),
						selected( $value, $opt_value, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;

			case 'number':
			case 'integer':
				$value = get_post_meta( $post->ID, $key, true );
				$step  = ( 'number' === $field['type'] ) ? '0.01' : '1';
				$attrs = '';
				if ( isset( $field['min'] ) ) {
					$attrs .= ' min="' . esc_attr( $field['min'] ) . '"';
				}
				if ( isset( $field['max'] ) ) {
					$attrs .= ' max="' . esc_attr( $field['max'] ) . '"';
				}
				printf(
					'<input type="number" step="%4$s" id="%1$s" name="%1$s" value="%2$s" class="regular-text"%3$s />',
					$id,
					esc_attr( $value ),
					$attrs, // phpcs:ignore — built from esc_attr() above.
					esc_attr( $step )
				);
				break;

			case 'text':
			default:
				$value       = get_post_meta( $post->ID, $key, true );
				$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
				printf(
					'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text"%3$s />',
					$id,
					esc_attr( $value ),
					$placeholder // phpcs:ignore — built from esc_attr() above.
				);
				break;
		}

		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * Save the details meta box.
 *
 * @param int $post_id Post being saved.
 */
function buckleup_save_meta_box( $post_id ) {
	// Bail on autosave / nonce failure / insufficient caps.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['buckleup_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['buckleup_meta_nonce'] ) ), 'buckleup_save_meta' ) ) {
		return;
	}
	$post_type = get_post_type( $post_id );
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = buckleup_meta_box_fields();
	if ( empty( $fields[ $post_type ] ) ) {
		return;
	}

	foreach ( $fields[ $post_type ] as $field ) {
		$key = $field['key'];

		switch ( $field['type'] ) {
			case 'checkbox':
				$raw = isset( $_POST[ $key ] ) ? '1' : '';
				update_post_meta( $post_id, $key, $raw );
				break;

			case 'list':
				// Replace all existing rows with the textarea lines.
				delete_post_meta( $post_id, $key );
				$raw   = isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '';
				$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
				foreach ( $lines as $line ) {
					add_post_meta( $post_id, $key, sanitize_text_field( $line ) );
				}
				break;

			case 'textarea':
				$raw = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
				update_post_meta( $post_id, $key, $raw );
				break;

			case 'number':
				$raw = isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ? floatval( wp_unslash( $_POST[ $key ] ) ) : '';
				update_post_meta( $post_id, $key, $raw );
				break;

			case 'integer':
				$raw = isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ? absint( wp_unslash( $_POST[ $key ] ) ) : '';
				update_post_meta( $post_id, $key, $raw );
				break;

			case 'select':
				$raw     = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
				$allowed = array_keys( $field['options'] );
				update_post_meta( $post_id, $key, in_array( $raw, $allowed, true ) ? $raw : '' );
				break;

			case 'text':
			default:
				$raw = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
				update_post_meta( $post_id, $key, $raw );
				break;
		}
	}
}
add_action( 'save_post', 'buckleup_save_meta_box' );
