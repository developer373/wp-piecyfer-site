<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

echo "=======================================================\n";
echo "       COMPREHENSIVE SITE-WIDE LINK AUDIT & SCAN       \n";
echo "=======================================================\n\n";

$site_url = home_url(); // http://localhost/piecyfer
echo "Base Site URL: $site_url\n\n";

// 1. Get all published pages and posts to crawl
$all_posts = get_posts([
  'post_type' => ['page', 'post', 'elementor_library', 'header', 'footer'],
  'posts_per_page' => -1,
  'post_status' => 'publish'
]);

$public_urls = [];
foreach ($all_posts as $p) {
  if (in_array($p->post_type, ['page', 'post'])) {
    $public_urls[] = [
      'id' => $p->ID,
      'title' => $p->post_title,
      'type' => $p->post_type,
      'url' => get_permalink($p->ID)
    ];
  }
}

echo "Found " . count($public_urls) . " public pages/posts to test.\n";
file_put_contents(__DIR__ . '/public-urls.json', json_encode($public_urls, JSON_PRETTY_PRINT));

// 2. Scan ALL Database tables for suspicious / broken links:
// a) URLs starting with "/" (excluding external "//", anchors "#", tel:, mailto:, javascript:)
// b) URLs starting with "http://localhost/" but NOT "http://localhost/piecyfer/"
// c) URLs with "piecyfer.com" for internal pages that exist locally

echo "\n--- Scanning Database for Broken Custom Menu Items ---\n";
$bad_menu_items = $wpdb->get_results("
  SELECT p.ID, p.post_title, pm.meta_value as url
  FROM {$wpdb->posts} p
  JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_menu_item_url'
  WHERE (pm.meta_value LIKE '/%' AND pm.meta_value NOT LIKE '//%')
     OR (pm.meta_value LIKE 'http://localhost/%' AND pm.meta_value NOT LIKE 'http://localhost/piecyfer/%')
");
echo "Broken menu items remaining: " . count($bad_menu_items) . "\n";
foreach ($bad_menu_items as $bmi) {
  echo "  - [ID: {$bmi->ID}] {$bmi->post_title}: {$bmi->url}\n";
}

echo "\n--- Scanning Elementor Data for Suspicious Links ---\n";
$elementor_metas = $wpdb->get_results("
  SELECT post_id, meta_value 
  FROM {$wpdb->postmeta} 
  WHERE meta_key = '_elementor_data'
");

$broken_in_elementor = [];
foreach ($elementor_metas as $em) {
  $data = $em->meta_value;
  // Match "url":"..."
  if (preg_match_all('#"url":"([^"]+)"#', $data, $matches)) {
    foreach ($matches[1] as $u) {
      $clean_u = stripslashes($u);
      if (
        ($clean_u === '/' || (strpos($clean_u, '/') === 0 && strpos($clean_u, '//') !== 0 && strpos($clean_u, '/wp-content') === false && strpos($clean_u, '/wp-admin') === false && strpos($clean_u, '/colors') === false && strpos($clean_u, '/typography') === false))
        || (strpos($clean_u, 'http://localhost/') === 0 && strpos($clean_u, 'http://localhost/piecyfer') === false)
      ) {
        $p = get_post($em->post_id);
        $broken_in_elementor[] = [
          'post_id' => $em->post_id,
          'post_title' => $p ? $p->post_title : 'Unknown',
          'post_type' => $p ? $p->post_type : 'Unknown',
          'raw_url' => $u,
          'clean_url' => $clean_u
        ];
      }
    }
  }
}

echo "Broken link references found in Elementor data: " . count($broken_in_elementor) . "\n";
foreach (array_slice($broken_in_elementor, 0, 30) as $bie) {
  echo "  - Post {$bie['post_id']} ('{$bie['post_title']}', {$bie['post_type']}): '{$bie['clean_url']}'\n";
}

// 3. Scan wp_posts post_content for standard links
echo "\n--- Scanning post_content in wp_posts ---\n";
$content_posts = $wpdb->get_results("
  SELECT ID, post_title, post_type, post_content 
  FROM {$wpdb->posts} 
  WHERE post_status = 'publish' 
    AND (post_content LIKE '%href=\"/%' OR post_content LIKE '%href=\'/%\' OR post_content LIKE '%http://localhost/%')
");

$broken_in_content = [];
foreach ($content_posts as $cp) {
  if (preg_match_all('#href=["\']([^"\']+)["\']#i', $cp->post_content, $matches)) {
    foreach ($matches[1] as $href) {
      if (
        ($href === '/' || (strpos($href, '/') === 0 && strpos($href, '//') !== 0 && strpos($href, '/wp-content') === false && strpos($href, '/wp-admin') === false))
        || (strpos($href, 'http://localhost/') === 0 && strpos($href, 'http://localhost/piecyfer') === false)
      ) {
        $broken_in_content[] = [
          'id' => $cp->ID,
          'title' => $cp->post_title,
          'type' => $cp->post_type,
          'href' => $href
        ];
      }
    }
  }
}
echo "Broken links in post_content: " . count($broken_in_content) . "\n";
foreach ($broken_in_content as $bic) {
  echo "  - Post {$bic['id']} ('{$bic['title']}'): '{$bic['href']}'\n";
}

// 4. Scan wp_options
echo "\n--- Scanning wp_options ---\n";
$bad_options = $wpdb->get_results("
  SELECT option_name, option_value 
  FROM {$wpdb->options} 
  WHERE (option_value LIKE '%http://localhost/%' AND option_value NOT LIKE '%http://localhost/piecyfer/%' AND option_name NOT LIKE '%transient%')
     OR (option_value LIKE '%href=\"/%' AND option_value NOT LIKE '%href=\"/piecyfer/%')
");
echo "Suspicious options found: " . count($bad_options) . "\n";
foreach ($bad_options as $bo) {
  echo "  - Option '{$bo->option_name}'\n";
}
