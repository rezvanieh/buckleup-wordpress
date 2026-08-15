<?php
if (!defined('ABSPATH')) exit;
global $wpdb; mysqli_report(MYSQLI_REPORT_OFF);
$p = get_page_by_path('coquitlam', OBJECT, 'location');
$r = mysqli_query($wpdb->dbh, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id={$p->ID} AND meta_key='_elementor_data' LIMIT 1");
$row = $r ? mysqli_fetch_row($r) : null; $d = $row ? $row[0] : '';
echo "  data bytes: " . strlen($d) . "\n";
echo "  matches '<i ... fa- ' pattern: " . (preg_match('/<(?:i|span)[^>]{0,200}?fa[bsrl]?\s+fa-/i', $d) ? 'YES' : 'NO') . "\n";
if (preg_match_all('/.{0,60}fa[bsrl]?\\?\s+fa-[a-z-]+.{0,20}/i', $d, $m)) {
  foreach (array_slice(array_unique($m[0]),0,3) as $x) echo "    ..." . $x . "\n";
}
