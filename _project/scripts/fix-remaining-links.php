<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

$site_url = home_url(); // http://localhost/piecyfer
$escaped_site = str_replace('/', '\/', $site_url);

echo "Applying Fixes to all remaining broken links across DB...\n\n";

// 1. Fix Footer Templates (Post 1273 and 991509 and any other footer/header elementor_library)
$footer_posts = [1273, 991509];
foreach ($footer_posts as $f_id) {
  $data = get_post_meta($f_id, '_elementor_data', true);
  if ($data) {
    // Replace "url":"\/" with "url":"http:\/\/localhost\/piecyfer\/"
    $new_data = str_replace('"url":"\/"', '"url":"' . $escaped_site . '\/"', $data);
    $new_data = str_replace('"url":"/"', '"url":"' . $site_url . '/"', $new_data);
    if ($new_data !== $data) {
      update_post_meta($f_id, '_elementor_data', wp_slash($new_data));
      echo "Fixed Footer Logo in Post ID: $f_id\n";
    }
  }
}

// 2. Fix Homepage (Post 146)
$home_data = get_post_meta(146, '_elementor_data', true);
if ($home_data) {
  $new_home = $home_data;

  // Fix maintenance-and-support slug -> software-maintenance-and-support
  $new_home = str_replace('"url":"\/maintenance-and-support\/"', '"url":"' . $escaped_site . '\/software-maintenance-and-support\/"', $new_home);
  $new_home = str_replace('"url":"\/maintenance-and-support"', '"url":"' . $escaped_site . '\/software-maintenance-and-support\/"', $new_home);
  $new_home = str_replace('"url":"/maintenance-and-support/"', '"url":"' . $site_url . '/software-maintenance-and-support/"', $new_home);

  // Fix qa-and-testing slug -> software-quality-testing
  $new_home = str_replace('"url":"\/qa-and-testing\/"', '"url":"' . $escaped_site . '\/software-quality-testing\/"', $new_home);
  $new_home = str_replace('"url":"\/qa-and-testing"', '"url":"' . $escaped_site . '\/software-quality-testing\/"', $new_home);
  $new_home = str_replace('"url":"/qa-and-testing/"', '"url":"' . $site_url . '/software-quality-testing/"', $new_home);

  // Fix PDF uploads paths
  $new_home = str_replace('"url":"\/wp-content\/uploads\/', '"url":"' . $escaped_site . '\/wp-content\/uploads\/', $new_home);
  $new_home = str_replace('"url":"/wp-content/uploads/', '"url":"' . $site_url . '/wp-content/uploads/', $new_home);

  if ($new_home !== $home_data) {
    update_post_meta(146, '_elementor_data', wp_slash($new_home));
    echo "Fixed Homepage internal links and PDF links in Post ID: 146\n";
  }
}

// 3. Fix post_content in all posts/pages
$all_posts = $wpdb->get_results("SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE '%href=\"/%' OR post_content LIKE '%href=\'/%\'");
foreach ($all_posts as $p) {
  $content = $p->post_content;
  $new_content = preg_replace_callback('#href=(["\'])/([a-zA-Z0-9_\-\#][^"\']*)(\1)#i', function($matches) use ($site_url) {
    $quote = $matches[1];
    $path = $matches[2];
    // Don't replace if it starts with wp-content or wp-admin
    if (strpos($path, 'wp-content') === 0 || strpos($path, 'wp-admin') === 0) {
      return "href={$quote}{$site_url}/{$path}{$quote}";
    }
    return "href={$quote}{$site_url}/{$path}{$quote}";
  }, $content);

  if ($new_content !== $content) {
    $wpdb->update($wpdb->posts, ['post_content' => $new_content], ['ID' => $p->ID]);
    echo "Fixed post_content in Post ID: {$p->ID}\n";
  }
}

// 4. Scan ALL other Elementor posts for any remaining "url":"\/" or "url":"\/wp-content
$all_elem = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND (meta_value LIKE '%\"url\":\"\\\\/%' OR meta_value LIKE '%\"url\":\"/%')");
foreach ($all_elem as $el) {
  $data = $el->meta_value;
  $new_data = str_replace('"url":"\/"', '"url":"' . $escaped_site . '\/"', $data);
  $new_data = str_replace('"url":"\/wp-content\/uploads\/', '"url":"' . $escaped_site . '\/wp-content\/uploads\/', $new_data);
  $new_data = str_replace('"url":"/wp-content/uploads/', '"url":"' . $site_url . '/wp-content/uploads/', $new_data);
  
  if ($new_data !== $data) {
    update_post_meta($el->post_id, '_elementor_data', wp_slash($new_data));
    echo "Fixed residual Elementor URLs in Post ID: {$el->post_id}\n";
  }
}

// Clear all Elementor CSS & Cache
\Elementor\Plugin::$instance->files_manager->clear_cache();
wp_cache_flush();
echo "\nAll caches flushed successfully!\n";
