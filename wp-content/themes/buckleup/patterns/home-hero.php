<?php
/**
 * Title: Home: Hero
 * Slug: buckleup/home-hero
 * Inserter: no
 *
 * Reproduces src/components/landing/Hero.tsx: full-bleed hero with the scenic
 * background (image2.png) + multi-layer gradient overlays, two ambient pulse-glow
 * blobs, the grid pattern, trust badges (ICBC Certified / 5-Star Rated / 100% Pass
 * Guarantee), the H1 "Master the Road with Confidence" (gradient span) at the exact
 * type scale (text-5xl…lg:text-[5.5rem] xl:text-[6.5rem], tracking-tighter,
 * leading-[0.95]), the subtitle, the "Start Learning Today" → #most-popular CTA, and
 * the lg-only 3D mouse-tilt card (hero_card_image.png) with the Farhad instructor
 * chip, the floating 4.98 / "Based on 200+ reviews" card, and the Toyota badge.
 *
 * The markup itself now lives in buckleup_hero_markup() (inc/site.php) because the
 * SAME hero is also rendered by the admin-editable Elementor widget
 * (inc/elementor-widgets/class-buckleup-hero-widget.php). One renderer, one code
 * path — the two can't drift. Called with no arguments here, so this pattern emits
 * exactly the hard-coded copy it always did.
 *
 * As of the Elementor hero conversion the FRONT PAGE renders the widget (first
 * element of page 38's Elementor body) rather than this pattern; the pattern stays
 * registered as the fallback / `[buckleup_section name="home-hero"]` embed.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!-- wp:html -->
<?php echo buckleup_hero_markup(); // phpcs:ignore WordPress.Security.EscapeOutput — escaped within helper ?>
<!-- /wp:html -->
