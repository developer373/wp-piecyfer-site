<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/category-erp.html';
$f2 = __DIR__ . '/../snapshots/b-cutover-quick2/html/category-erp.html';

$h1 = file_get_contents($f1);
$h2 = file_get_contents($f2);

preg_match_all('/<article[^>]*>/', $h1, $m1);
preg_match_all('/<article[^>]*>/', $h2, $m2);

echo "Articles in ref3-a: " . count($m1[0]) . "\n";
echo "Articles in b-cutover-quick2: " . count($m2[0]) . "\n";

// Let's find article titles in both
preg_match_all('/<h[1-6][^>]*class="[^"]*elementor-post__title[^"]*"[^>]*>(.*?)<\/h[1-6]>/s', $h1, $t1);
preg_match_all('/<h[1-6][^>]*class="[^"]*elementor-post__title[^"]*"[^>]*>(.*?)<\/h[1-6]>/s', $h2, $t2);

echo "Titles in ref3-a:\n";
foreach ($t1[1] as $t) echo "  - " . trim(strip_tags($t)) . "\n";

echo "Titles in b-cutover-quick2:\n";
foreach ($t2[1] as $t) echo "  - " . trim(strip_tags($t)) . "\n";
