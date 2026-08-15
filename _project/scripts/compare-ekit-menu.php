<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/this-url-does-not-exist-404-test.html';
$f2 = __DIR__ . '/../snapshots/b-cutover-quick3/html/this-url-does-not-exist-404-test.html';

$h1 = file_get_contents($f1);
$h2 = file_get_contents($f2);

preg_match('/<div[^>]*class="[^"]*elementor-widget-elementskit-nav-menu[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/s', $h1, $w1);
preg_match('/<div[^>]*class="[^"]*elementor-widget-elementskit-nav-menu[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/s', $h2, $w2);

echo "Ekit widget HTML len: ref=" . strlen($w1[0] ?? '') . ", live=" . strlen($w2[0] ?? '') . "\n";

$lines1 = explode("\n", $w1[0] ?? '');
$lines2 = explode("\n", $w2[0] ?? '');

for ($i = 0; $i < max(count($lines1), count($lines2)); $i++) {
    $l1 = $lines1[$i] ?? '(EOF)';
    $l2 = $lines2[$i] ?? '(EOF)';
    if ($l1 !== $l2) {
        echo "L$i:\n  - $l1\n  + $l2\n";
    }
}
