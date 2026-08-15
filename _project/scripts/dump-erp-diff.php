<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/category-erp.html';
$f2 = __DIR__ . '/../snapshots/b-cutover-quick2/html/category-erp.html';

$h1 = file($f1, FILE_IGNORE_NEW_LINES);
$h2 = file($f2, FILE_IGNORE_NEW_LINES);

echo "Total lines: ref=" . count($h1) . ", new=" . count($h2) . "\n";

$diffs = 0;
for ($i = 0; $i < max(count($h1), count($h2)); $i++) {
    $l1 = $h1[$i] ?? '(EOF)';
    $l2 = $h2[$i] ?? '(EOF)';
    if ($l1 !== $l2) {
        $diffs++;
        echo "L" . ($i+1) . ":\n";
        echo "  - " . trim(substr($l1, 0, 120)) . "\n";
        echo "  + " . trim(substr($l2, 0, 120)) . "\n";
    }
}
echo "Total diff lines: $diffs\n";
