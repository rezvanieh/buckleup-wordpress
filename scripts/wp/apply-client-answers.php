<?php
/**
 * Apply the three business facts the client confirmed on 2026-08-15.
 *
 *   Hours    - closes 9pm (the settings option already said 21:00; the code
 *              defaults said 18:00 and are fixed in the plugin/theme/mu-plugin).
 *   Payments - cash and e-transfer only, no credit card.
 *   /instructors/ - should be indexed. Instructor bios are strong trust content
 *              for a driving school, and the page was noindex with no meta
 *              description at all, so it also gets one; indexing a page with no
 *              description just hands Google a blank snippet to fill.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/apply-client-answers.php
 */
if (!defined('ABSPATH')) exit;

$s = get_option('buckleup_settings');
if (is_array($s)) {
  $s['hours_open']    = '09:00';
  $s['hours_close']   = '21:00';
  $s['hours_display'] = 'Mon–Sun 9am–9pm';
  $s['payments']      = array('Cash', 'E-Transfer');
  update_option('buckleup_settings', $s);
  echo "  settings: hours 09:00-21:00 (Mon-Sun 9am-9pm), payments Cash + E-Transfer\n";
}

$p = get_page_by_path('instructors');
if ($p) {
  update_post_meta($p->ID, 'rank_math_robots', array('index','follow'));
  $d = trim((string) get_post_meta($p->ID, 'rank_math_description', true));
  if ('' === $d) {
    $desc = 'Meet the ICBC-certified instructors behind BuckleUp Driving School, teaching in English and Farsi across Coquitlam, Port Moody and the Tri-Cities.'; // 148
    update_post_meta($p->ID, 'rank_math_description', $desc);
    update_post_meta($p->ID, 'rank_math_facebook_description', $desc);
    echo "  /instructors/: index,follow + meta description added\n";
  } else {
    echo "  /instructors/: index,follow (already had a description)\n";
  }
  $t = trim((string) get_post_meta($p->ID, 'rank_math_title', true));
  if ('' === $t || false !== stripos($t, 'Instructors - BuckleUp')) {
    update_post_meta($p->ID, 'rank_math_title', 'Our ICBC-Certified Driving Instructors | BuckleUp'); // 49
    echo "  /instructors/: title replaced (was the generic template default)\n";
  }
}
