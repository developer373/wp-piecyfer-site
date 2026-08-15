<?php
require_once __DIR__ . '/compare-style-tags.php';

echo "=== DIFF FOR [vamtam-front-all-inline-css] ===\n";
$lines1 = explode("\n", $s1['vamtam-front-all-inline-css'] ?? '');
$lines2 = explode("\n", $s2['vamtam-front-all-inline-css'] ?? '');

for ($i = 0; $i < max(count($lines1), count($lines2)); $i++) {
    $l1 = $lines1[$i] ?? '(EOF)';
    $l2 = $lines2[$i] ?? '(EOF)';
    if ($l1 !== $l2) {
        echo "Line $i:\n";
        echo "  - " . $l1 . "\n";
        echo "  + " . $l2 . "\n";
    }
}

echo "\n=== DIFF FOR [vamtam-theme-options] ===\n";
$lines1 = explode("\n", $s1['vamtam-theme-options'] ?? '');
$lines2 = explode("\n", $s2['vamtam-theme-options'] ?? '');

for ($i = 0; $i < max(count($lines1), count($lines2)); $i++) {
    $l1 = $lines1[$i] ?? '(EOF)';
    $l2 = $lines2[$i] ?? '(EOF)';
    if ($l1 !== $l2) {
        echo "Line $i:\n";
        echo "  - " . $l1 . "\n";
        echo "  + " . $l2 . "\n";
    }
}
