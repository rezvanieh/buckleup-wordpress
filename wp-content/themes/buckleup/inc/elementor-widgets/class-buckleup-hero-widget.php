<?php
/**
 * Elementor widget: BuckleUp Hero.
 *
 * Makes the home hero fully editable in Elementor with ZERO visual change. The
 * widget owns no markup of its own — render() collects the control values and
 * hands them to buckleup_hero_markup() (inc/site.php), the exact renderer that
 * patterns/home-hero.php uses. Consequences that matter:
 *
 *   - Every control default equals today's live copy, so a freshly-inserted or
 *     never-edited widget emits byte-identical HTML to the hand-coded hero:
 *     same DOM, same Tailwind class strings, same data-reveal / data-reveal-y
 *     hooks, same fetchpriority/loading/decoding attributes on the LCP <picture>.
 *   - The bespoke utilities (.gradient-text, .glass, .glow-primary, .shine,
 *     animate-pulse-glow) and the JS behaviours (scroll reveals, the lg-only 3D
 *     mouse-tilt card) keep working because the markup is unchanged and the
 *     theme's Tailwind bundle + interaction layer load site-wide (inc/elementor.php).
 *
 * Trust-badge icons are the theme's own inline lucide SVGs (buckleup_icon()), NOT
 * Elementor/Font Awesome icons — an Elementor ICONS control would emit <i class="fas…">
 * and break the pill markup. Hence a SELECT fed by buckleup_icon_names().
 *
 * Method signatures deliberately carry no return types: they override
 * \Elementor\Widget_Base, whose signatures have shifted across releases, and a
 * mismatch there is a fatal error rather than a warning.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

/**
 * "BuckleUp Hero" — the front page's full-bleed hero, control-driven.
 */
class Buckleup_Hero_Widget extends Widget_Base {

	/** Elementor widgetType. Kept in a constant so build-hero.php can target it. */
	public function get_name() {
		return BUCKLEUP_HERO_WIDGET;
	}

	/** Panel title. */
	public function get_title() {
		return __( 'BuckleUp Hero', 'buckleup' );
	}

	/** Panel icon (Elementor's own eicon set). */
	public function get_icon() {
		return 'eicon-banner';
	}

	/** Group under our own "BuckleUp" panel category. */
	public function get_categories() {
		return array( 'buckleup' );
	}

	/** Panel search terms. */
	public function get_keywords() {
		return array( 'hero', 'banner', 'buckleup', 'header', 'headline' );
	}

	/*
	 * NOTE: no content_template() on purpose. A JS twin of this markup would be a
	 * second source of truth (exactly what this widget exists to avoid), and without
	 * one Elementor simply server-renders the editor preview through render().
	 *
	 * Also no get_style_depends()/get_script_depends(): the Tailwind bundle
	 * (buckleup-app) and the interaction layer (buckleup-main) already load
	 * site-wide from functions.php — see the note at the top of inc/elementor.php.
	 */

	/* ---------------------------------------------------------------------
	 * Controls
	 * ------------------------------------------------------------------ */

	/**
	 * Content / CTA / Visual sections. Defaults mirror the previously hard-coded
	 * strings verbatim — do not "improve" the copy here; edit it in Elementor.
	 */
	protected function register_controls() {
		$this->register_content_section();
		$this->register_cta_section();
		$this->register_visual_section();
	}

	/** Headline, subtitle and the trust-badge repeater. */
	private function register_content_section() {
		$this->start_controls_section( 'section_hero_content', array(
			'label' => __( 'Content', 'buckleup' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'headline', array(
			'label'       => __( 'Headline', 'buckleup' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Master the Road', 'buckleup' ),
			'label_block' => true,
			'description' => __( 'Plain first half of the H1. The gradient words follow it on the same line.', 'buckleup' ),
		) );

		$this->add_control( 'headline_gradient', array(
			'label'       => __( 'Headline — gradient words', 'buckleup' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'with Confidence', 'buckleup' ),
			'label_block' => true,
			'description' => __( 'Rendered inside the brand gradient span.', 'buckleup' ),
		) );

		$this->add_control( 'subtitle', array(
			'label'       => __( 'Subtitle', 'buckleup' ),
			'type'        => Controls_Manager::TEXTAREA,
			'rows'        => 6,
			'default'     => __( 'BuckleUp Driving School is a trusted driving school that Vancouver learners choose. We offer expert driving lessons in North Vancouver, Coquitlam, Port Moody, and the Tri-Cities, along with ICBC road test preparation.', 'buckleup' ),
			'label_block' => true,
		) );

		$badge = new Repeater();
		$badge->add_control( 'badge_text', array(
			'label'       => __( 'Label', 'buckleup' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'ICBC Certified', 'buckleup' ),
			'label_block' => true,
		) );
		$badge->add_control( 'badge_icon', array(
			'label'       => __( 'Icon', 'buckleup' ),
			'type'        => Controls_Manager::SELECT,
			'default'     => 'shield-check',
			'options'     => self::icon_options(),
			'label_block' => true,
			'description' => __( 'Theme icon (inline SVG) — not a Font Awesome icon.', 'buckleup' ),
		) );

		$this->add_control( 'badges', array(
			'label'       => __( 'Trust badges', 'buckleup' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $badge->get_controls(),
			'title_field' => '{{{ badge_text }}}',
			'default'     => array(
				array( 'badge_icon' => 'shield-check', 'badge_text' => __( 'ICBC Certified', 'buckleup' ) ),
				array( 'badge_icon' => 'star',         'badge_text' => __( '5-Star Rated', 'buckleup' ) ),
				array( 'badge_icon' => 'check',        'badge_text' => __( 'Dual-Control Vehicles', 'buckleup' ) ),
			),
		) );

		$this->end_controls_section();
	}

	/** The single primary CTA (label + link). */
	private function register_cta_section() {
		$this->start_controls_section( 'section_hero_cta', array(
			'label' => __( 'Call to action', 'buckleup' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'cta_label', array(
			'label'       => __( 'Button label', 'buckleup' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Start Learning Today', 'buckleup' ),
			'label_block' => true,
		) );

		$this->add_control( 'cta_link', array(
			'label'       => __( 'Button link', 'buckleup' ),
			'type'        => Controls_Manager::URL,
			'default'     => array( 'url' => '/#most-popular' ),
			'placeholder' => '/#most-popular',
			'label_block' => true,
			'description' => __( 'Defaults to the Most Popular pricing card anchor on the home page.', 'buckleup' ),
		) );

		$this->end_controls_section();
	}

	/** Background image + the right-column card, instructor chip and rating card. */
	private function register_visual_section() {
		$this->start_controls_section( 'section_hero_visual', array(
			'label' => __( 'Visual', 'buckleup' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'background_image', array(
			'label'       => __( 'Background image', 'buckleup' ),
			'type'        => Controls_Manager::MEDIA,
			'default'     => array( 'url' => self::asset_url( 'image2.png' ) ),
			'description' => __( 'The page\'s LCP image: rendered eagerly with fetchpriority="high", and served as WebP automatically when an optimized sibling file exists.', 'buckleup' ),
		) );

		$this->add_control( 'background_alt', array(
			'label'       => __( 'Background alt text', 'buckleup' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Scenic winding road', 'buckleup' ),
			'label_block' => true,
		) );

		$this->add_control( 'heading_card', array(
			'label'     => __( 'Hero card', 'buckleup' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_control( 'card_image', array(
			'label'   => __( 'Card image', 'buckleup' ),
			'type'    => Controls_Manager::MEDIA,
			'default' => array( 'url' => self::asset_url( 'hero_card_image.png' ) ),
		) );

		$this->add_control( 'card_alt', array(
			'label'       => __( 'Card alt text', 'buckleup' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Farhad Sanaeifar with BuckleUp Driving School car', 'buckleup' ),
			'label_block' => true,
		) );

		$this->add_control( 'vehicle_badge', array(
			'label'       => __( 'Vehicle badge', 'buckleup' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Toyota', 'buckleup' ),
			'label_block' => true,
			'description' => __( 'The small glass pill on the top-left of the card.', 'buckleup' ),
		) );

		$this->add_control( 'heading_instructor', array(
			'label'     => __( 'Instructor chip', 'buckleup' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_control( 'instructor_name', array(
			'label'       => __( 'Name', 'buckleup' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => 'Farhad Sanaeifar',
			'label_block' => true,
			'description' => __( 'Doubles as the photo\'s alt text.', 'buckleup' ),
		) );

		$this->add_control( 'instructor_photo', array(
			'label'   => __( 'Photo', 'buckleup' ),
			'type'    => Controls_Manager::MEDIA,
			'default' => array( 'url' => self::asset_url( 'farhad-instructor.jpg' ) ),
		) );

		$this->add_control( 'instructor_role', array(
			'label'       => __( 'Role', 'buckleup' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Senior Instructor • ICBC Certified', 'buckleup' ),
			'label_block' => true,
		) );

		$this->add_control( 'instructor_rating', array(
			'label'   => __( 'Chip rating', 'buckleup' ),
			'type'    => Controls_Manager::TEXT,
			'default' => '4.9',
		) );

		$this->add_control( 'heading_rating', array(
			'label'     => __( 'Floating rating card', 'buckleup' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_control( 'rating_value', array(
			'label'   => __( 'Rating', 'buckleup' ),
			'type'    => Controls_Manager::TEXT,
			'default' => '5.0',
		) );

		$this->add_control( 'rating_caption', array(
			'label'       => __( 'Caption', 'buckleup' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Based on 33 Google reviews', 'buckleup' ),
			'label_block' => true,
		) );

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------------ */

	/**
	 * Map controls → buckleup_hero_markup() arguments and echo the hero.
	 *
	 * Nothing is escaped here on purpose: every value is escaped inside the shared
	 * renderer (esc_html / esc_attr / esc_url), exactly as it was when the strings
	 * were hard-coded in the pattern.
	 */
	protected function render() {
		if ( ! function_exists( 'buckleup_hero_markup' ) ) {
			return; // Theme helpers unavailable (should never happen — same theme).
		}

		$s    = $this->get_settings_for_display();
		$link = isset( $s['cta_link'] ) && is_array( $s['cta_link'] ) ? $s['cta_link'] : array();

		// Only emit target/rel when the editor actually asked for them, so the
		// untouched CTA stays the bare `<a href="/#most-popular">` it always was.
		$rel = array();
		if ( ! empty( $link['is_external'] ) ) {
			$rel[] = 'noopener';
		}
		if ( ! empty( $link['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}

		echo buckleup_hero_markup( array( // phpcs:ignore WordPress.Security.EscapeOutput — escaped within the renderer
			'bg_image'          => self::media_url( $s, 'background_image' ),
			'bg_alt'            => self::text( $s, 'background_alt' ),
			'headline'          => self::text( $s, 'headline' ),
			'headline_gradient' => self::text( $s, 'headline_gradient' ),
			'subtitle'          => self::text( $s, 'subtitle' ),
			'cta_label'         => self::text( $s, 'cta_label' ),
			'cta_url'           => ! empty( $link['url'] ) ? (string) $link['url'] : '',
			'cta_target'        => ! empty( $link['is_external'] ) ? '_blank' : '',
			'cta_rel'           => implode( ' ', $rel ),
			'badges'            => self::badges( $s ),
			'visual'            => array(
				'card_image'        => self::media_url( $s, 'card_image' ),
				'card_alt'          => self::text( $s, 'card_alt' ),
				'instructor_name'   => self::text( $s, 'instructor_name' ),
				'instructor_photo'  => self::media_url( $s, 'instructor_photo', 'thumbnail' ), // 48x48 chip
				'instructor_role'   => self::text( $s, 'instructor_role' ),
				'instructor_rating' => self::text( $s, 'instructor_rating' ),
				'rating_value'      => self::text( $s, 'rating_value' ),
				'rating_caption'    => self::text( $s, 'rating_caption' ),
				'vehicle_badge'     => self::text( $s, 'vehicle_badge' ),
			),
		) );
	}

	/* ---------------------------------------------------------------------
	 * Small helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Normalize a repeater into the [['icon'=>…,'text'=>…], …] shape
	 * buckleup_hero_trust_badges() expects.
	 *
	 * Returns an EMPTY array (→ no badges) when every row was deleted, and null
	 * only if the control is missing entirely — null is the renderer's "use the
	 * three production badges" sentinel, which is what an un-migrated widget wants.
	 *
	 * @param array $s Widget settings.
	 * @return array|null
	 */
	private static function badges( array $s ) {
		if ( ! isset( $s['badges'] ) || ! is_array( $s['badges'] ) ) {
			return null;
		}
		$out = array();
		foreach ( $s['badges'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'icon' => isset( $row['badge_icon'] ) ? (string) $row['badge_icon'] : '',
				'text' => isset( $row['badge_text'] ) ? (string) $row['badge_text'] : '',
			);
		}
		return $out;
	}

	/**
	 * A plain string from the settings array (Elementor already merged defaults).
	 *
	 * @param array  $s   Widget settings.
	 * @param string $key Control name.
	 * @return string
	 */
	private static function text( array $s, string $key ): string {
		return isset( $s[ $key ] ) && is_scalar( $s[ $key ] ) ? (string) $s[ $key ] : '';
	}

	/**
	 * The URL out of a MEDIA control, preferring the stored url and falling back to
	 * the attachment id. '' tells the renderer to use the migrated brand asset.
	 *
	 * @param array  $s   Widget settings.
	 * @param string $key Control name.
	 * @return string
	 */
	private static function media_url( array $s, string $key, string $size = 'full' ): string {
		if ( empty( $s[ $key ] ) || ! is_array( $s[ $key ] ) ) {
			return '';
		}
		$image = $s[ $key ];

		/*
		 * Elementor's media control stores both an id and a full-size url, and the
		 * url is what gets saved into the document. Reading it directly means a
		 * photo displayed at 48x48 was served at its full 1024x682. When a smaller
		 * size is asked for, resolve the attachment and let WordPress hand back the
		 * generated crop instead — including for documents saved before this, where
		 * only the full-size url is present.
		 */
		if ( 'full' !== $size ) {
			$id = ! empty( $image['id'] ) ? (int) $image['id'] : 0;
			if ( ! $id && ! empty( $image['url'] ) ) {
				$id = (int) attachment_url_to_postid( (string) $image['url'] );
			}
			if ( $id ) {
				$sized = wp_get_attachment_image_url( $id, $size );
				if ( $sized ) {
					return (string) $sized;
				}
			}
		}

		if ( ! empty( $image['url'] ) ) {
			return (string) $image['url'];
		}
		if ( ! empty( $image['id'] ) ) {
			return (string) wp_get_attachment_url( (int) $image['id'] );
		}
		return '';
	}

	/**
	 * Brand-asset URL for a control default, guarded so the panel still opens if the
	 * media migration hasn't run (an empty MEDIA control simply means "theme default",
	 * which the renderer resolves at render time anyway).
	 *
	 * @param string $filename Source filename, e.g. 'image2.png'.
	 * @return string
	 */
	private static function asset_url( string $filename ): string {
		return function_exists( 'buckleup_asset_url' ) ? buckleup_asset_url( $filename ) : '';
	}

	/**
	 * SELECT options for the badge icon picker, built from the theme's real icon set
	 * so adding an icon to inc/icons.php automatically offers it here.
	 *
	 * @return array<string,string> icon name => human label.
	 */
	private static function icon_options(): array {
		if ( ! function_exists( 'buckleup_icon_names' ) ) {
			return array( 'shield-check' => 'Shield Check', 'star' => 'Star', 'check' => 'Check' );
		}
		$options = array();
		foreach ( buckleup_icon_names() as $name ) {
			$options[ $name ] = ucwords( str_replace( '-', ' ', $name ) );
		}
		return $options;
	}
}
