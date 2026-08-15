<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/category-erp.html';
$f2 = __DIR__ . '/../snapshots/b-cutover-quick2/html/category-erp.html';

$h1 = file_get_contents($f1);
$h2 = file_get_contents($f2);

// Compare classes of articles and widgets
preg_match_all('/<div class="([^"]*elementor-widget-archive-posts[^"]*)"/s', $h1, $w1);
preg_match_all('/<div class="([^"]*elementor-widget-archive-posts[^"]*)"/s', $h2, $w2);

echo "Archive posts widget class in ref3-a:\n  " . ($w1[1][0] ?? 'NONE') . "\n";
echo "Archive posts widget class in b-cutover-quick2:\n  " . ($w2[1][0] ?? 'NONE') . "\n";

preg_match_all('/<article class="([^"]*)"/s', $h1, $a1);
preg_match_all('/<article class="([^"]*)"/s', $h2, $a2);

echo "\nArticle 1 class in ref3-a:\n  " . ($a1[1][0] ?? 'NONE') . "\n";
echo "Article 1 class in b-cutover-quick2:\n  " . ($a2[1][0] ?? 'NONE') . "\n";

// Check inner content of first article
preg_match('/<article[^>]*>(.*?)<\/article>/s', $h1, $art1);
preg_match('/<article[^>]*>(.*?)<\/article>/s', $h2, $art2);

echo "\nArticle 1 HTML len ref3-a: " . strlen($art1[1] ?? '') . ", b-cutover-quick2: " . strlen($art2[1] ?? '') . "\n";

$lines1 = explode("\n", $art1[1] ?? '');
$lines2 = explode("\n", $art2[1] ?? '');

for ($i = 0; $i < max(count($lines1), count($lines2)); $i++) {
    $l1 = $lines1[$i] ?? '(EOF)';
    $l2 = $lines2[$i] ?? '(EOF)';
    if ($l1 !== $l2) {
        echo "Line " . ($i+1) . ":\n";
        echo "  - " . trim(substr($l1, 0, 100)) . "\n";
        echo "  + " . trim(substr($l2, 0, 100)) . "\n";
    }
}
