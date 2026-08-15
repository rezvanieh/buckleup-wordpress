<?php
/**
 * Fix "Driving Lessons in Driving Lessons in Coquitlam" in the global footer.
 *
 * The footer is an Elementor library template (elementor_library / site-footer),
 * NOT the theme's patterns/site-footer.php, so the label is literal text rather
 * than something built from the location title. It appears on every page of the
 * site.
 *
 * Raw mysqli is used for the read because reading _elementor_data through $wpdb
 * replaces every literal % with a per-request hash, and Elementor data is full of
 * "%" units.
 */
if (!defined('ABSPATH')) exit;
global $wpdb;
mysqli_report(MYSQLI_REPORT_OFF);

$id = 0;
$q = get_posts(array('post_type'=>'elementor_library','name'=>'site-footer','numberposts'=>1,'post_status'=>'publish'));
if ($q) { $id = (int) $q[0]->ID; }
if (!$id) { echo "ABORT: site-footer template not found\n"; return; }

$r = mysqli_query($wpdb->dbh, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=$id AND meta_key='_elementor_data' LIMIT 1");
$row = $r ? mysqli_fetch_row($r) : null;
$json = $row ? $row[0] : '';
if (!$json) { echo "ABORT: no _elementor_data on $id\n"; return; }

$bad = 'Driving Lessons in Driving Lessons in Coquitlam';
$good = 'Driving Lessons in Coquitlam';
$n = substr_count($json, $bad);
if (!$n) { echo "already fixed (0 occurrences)\n"; return; }

$new = str_replace($bad, $good, $json);
if (!is_array(json_decode($new, true))) { echo "ABORT: result is not valid JSON\n"; return; }

update_post_meta($id, '_elementor_data', wp_slash($new));
delete_post_meta($id, '_elementor_element_cache');
if (class_exists('\Elementor\Plugin')) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
wp_cache_flush();
echo "fixed $n occurrence(s) in elementor_library/site-footer (post $id)\n";
