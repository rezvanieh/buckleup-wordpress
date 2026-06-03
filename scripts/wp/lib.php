<?php
/**
 * Shared idempotent helpers for the BuckleUp content seed/migration scripts.
 *
 * Loaded by the other scripts/wp/*.php via require_once. All helpers upsert by a
 * natural key (slug / post_title within a post type) so the whole seed is safe to
 * re-run: the second run updates in place instead of creating duplicates.
 *
 * Meta keys + CPT keys are the contract from docs/CONTENT-MODEL.md (owned by the
 * buckleup-core plugin). Do NOT invent keys here — mirror that doc.
 *
 * Run indirectly: wp eval-file /scripts/wp/seed-*.php  (each requires this file)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'bu_find_post' ) ) {

	/**
	 * Find an existing CPT post by slug (post_name) within a post type, else by
	 * exact title. Returns the post ID or 0.
	 */
	function bu_find_post( $post_type, $slug = '', $title = '' ) {
		if ( $slug ) {
			$found = get_page_by_path( $slug, OBJECT, $post_type );
			if ( $found ) { return (int) $found->ID; }
		}
		if ( $title ) {
			$q = get_posts( array(
				'post_type'        => $post_type,
				'title'            => $title,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			) );
			if ( ! empty( $q ) ) { return (int) $q[0]; }
		}
		return 0;
	}

	/**
	 * Upsert a post of any type. $args mirrors wp_insert_post fields; on update we
	 * pass the resolved ID. Returns the post ID (0 on failure, logged as a warning).
	 *
	 * - Matches an existing row by post_name (preferred) then post_title.
	 * - Scalar meta in $meta replaces existing single values (update_post_meta).
	 * - List meta in $list_meta is rewritten as multiple single rows per CONTENT-MODEL.
	 */
	function bu_upsert_post( array $args, array $meta = array(), array $list_meta = array() ) {
		$post_type = $args['post_type'];
		$slug      = isset( $args['post_name'] ) ? $args['post_name'] : '';
		$title     = isset( $args['post_title'] ) ? $args['post_title'] : '';

		$existing = bu_find_post( $post_type, $slug, $title );

		$defaults = array( 'post_status' => 'publish' );
		$data     = array_merge( $defaults, $args );
		if ( $existing ) { $data['ID'] = $existing; }

		$id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( "  {$post_type} '{$title}': " . $id->get_error_message() );
			return 0;
		}

		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}

		foreach ( $list_meta as $k => $values ) {
			delete_post_meta( $id, $k );
			foreach ( (array) $values as $v ) {
				if ( '' === $v || null === $v ) { continue; }
				add_post_meta( $id, $k, $v, false );
			}
		}

		$verb = $existing ? 'updated' : 'created';
		WP_CLI::log( "  {$post_type} {$verb}: {$title} (#{$id})" );
		return (int) $id;
	}

	/**
	 * Build the exact live WhatsApp deep link for a pricing plan, matching the
	 * source template (landing/Pricing.tsx) byte-for-byte:
	 *   https://wa.me/16044413677?text=Hi! I'm interested in booking the *<name>* ($<price>).
	 * The plugin helper buckleup_whatsapp_link() derives this at render time; this
	 * is only used where a seed needs the literal (it normally should NOT be stored).
	 */
	function bu_whatsapp_link( $name, $price ) {
		$text = sprintf( "Hi! I'm interested in booking the *%s* ($%s).", $name, $price );
		return 'https://wa.me/16044413677?text=' . rawurlencode( $text );
	}

	/**
	 * Ensure a category exists (by name) and return its term_id. Idempotent.
	 */
	function bu_ensure_category( $name ) {
		$term = term_exists( $name, 'category' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'category' );
			if ( is_wp_error( $term ) ) { return 0; }
		}
		return (int) $term['term_id'];
	}

	/**
	 * Strip a single leading <h1>…</h1> (and surrounding whitespace) from a post
	 * body so it doesn't render a second H1 alongside the theme's post-title H1.
	 */
	function bu_strip_leading_h1( $html ) {
		return preg_replace( '/^\s*<h1\b[^>]*>.*?<\/h1>\s*/is', '', $html, 1 );
	}
}
