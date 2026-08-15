<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/contact-us.html';
$f2 = __DIR__ . '/../snapshots/b-cutover-quick/html/contact-us.html';

$h1 = file_get_contents($f1);
$h2 = file_get_contents($f2);

// extract body
preg_match('/<body[^>]*>(.*?)<\/body>/s', $h1, $b1);
preg_match('/<body[^>]*>(.*?)<\/body>/s', $h2, $b2);

$lines1 = explode("\n", $b1[1] ?? '');
$lines2 = explode("\n", $b2[1] ?? '');

echo "Body lines: ref = " . count($lines1) . ", new = " . count($lines2) . "\n";

$diffs = 0;
for ($i = 0; $i < max(count($lines1), count($lines2)); $i++) {
    $l1 = $lines1[$i] ?? '(EOF)';
    $l2 = $lines2[$i] ?? '(EOF)';
    if ($l1 !== $l2) {
        $diffs++;
        echo "Body diff at line " . ($i+1) . ":\n";
        echo "  - " . trim(substr($l1, 0, 140)) . "\n";
        echo "  + " . trim(substr($l2, 0, 140)) . "\n";
        if ($diffs > 25) break;
    }
}
if ($diffs === 0) echo "BODY IS IDENTICAL!\n";
