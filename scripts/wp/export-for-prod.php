<?php
/**
 * Export the local content state for deployment, with every localhost URL
 * rewritten to the production host. Emits JSON on stdout.
 */
if (!defined('ABSPATH')) exit;
global $wpdb; mysqli_report(MYSQLI_REPORT_OFF);

$LOCAL = untrailingslashit(home_url());
$PROD  = 'https://www.buckleupdriving.ca';
$to_prod = function ($s) use ($LOCAL, $PROD) {
  $s = (string) $s;
  $s = str_replace($LOCAL, $PROD, $s);
  return str_replace(str_replace('/', '\/', $LOCAL), str_replace('/', '\/', $PROD), $s);
};

$meta_keys = array('rank_math_title','rank_math_description','rank_math_facebook_title','rank_math_facebook_description','rank_math_twitter_title','rank_math_twitter_description','rank_math_robots','_wp_page_template','_elementor_template_type');

$out = array('option' => get_option('buckleup_settings'), 'items' => array());

foreach (array('post','page','location','elementor_library') as $pt) {
  foreach (get_posts(array('post_type'=>$pt,'post_status'=>'publish','numberposts'=>-1)) as $p) {
    $r = mysqli_query($wpdb->dbh, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id={$p->ID} AND meta_key='_elementor_data' LIMIT 1");
    $row = $r ? mysqli_fetch_row($r) : null;
    $m = array();
    foreach ($meta_keys as $k) { $v = get_post_meta($p->ID,$k,true); if ('' !== $v && array() !== $v) { $m[$k] = $v; } }
    $out['items'][$pt.':'.$p->post_name] = array(
      'title'     => $p->post_title,
      'content'   => $to_prod($p->post_content),
      'elementor' => $row ? $to_prod($row[0]) : null,
      'meta'      => $m,
    );
  }
}
echo json_encode($out);
