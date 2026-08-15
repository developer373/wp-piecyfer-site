<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/this-url-does-not-exist-404-test.html';
$f2 = __DIR__ . '/../snapshots/b-cutover-quick3/html/this-url-does-not-exist-404-test.html';

$h1 = file_get_contents($f1);
$h2 = file_get_contents($f2);

preg_match('/<div[^>]*data-elementor-id="171"[^>]*>.*?<\/div>\s*<div/s', $h1, $m1);
preg_match('/<div[^>]*data-elementor-id="171"[^>]*>.*?<\/div>\s*<div/s', $h2, $m2);

echo "Header HTML len ref3-a: " . strlen($m1[0] ?? '') . "\n";
echo "Header HTML len b-cutover-quick3: " . strlen($m2[0] ?? '') . "\n";

$lines1 = explode("\n", $m1[0] ?? '');
$lines2 = explode("\n", $m2[0] ?? '');

echo "Total header lines: ref=" . count($lines1) . ", new=" . count($lines2) . "\n";

$diffs = 0;
for ($i = 0; $i < max(count($lines1), count($lines2)); $i++) {
    $l1 = $lines1[$i] ?? '(EOF)';
    $l2 = $lines2[$i] ?? '(EOF)';
    if ($l1 !== $l2) {
        $diffs++;
        echo "L" . ($i+1) . ":\n";
        echo "  - " . trim(substr($l1, 0, 120)) . "\n";
        echo "  + " . trim(substr($l2, 0, 120)) . "\n";
    }
}
echo "Total diff lines in header: $diffs\n";
