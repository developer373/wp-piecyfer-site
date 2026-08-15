<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/contact-us.html';
$h1 = file_get_contents($f1);
$h2 = file_get_contents('http://localhost/piecyfer/contact-us/');

preg_match('/<form[^>]*class="[^"]*elementor-form[^"]*"[^>]*>(.*?)<\/form>/s', $h1, $m1);
preg_match('/<form[^>]*class="[^"]*elementor-form[^"]*"[^>]*>(.*?)<\/form>/s', $h2, $m2);

echo "Form HTML len ref3-a: " . strlen($m1[0] ?? '') . "\n";
echo "Form HTML len b-cutover-quick3: " . strlen($m2[0] ?? '') . "\n";

$lines1 = explode("\n", $m1[0] ?? '');
$lines2 = explode("\n", $m2[0] ?? '');

for ($i = 0; $i < max(count($lines1), count($lines2)); $i++) {
    $l1 = $lines1[$i] ?? '(EOF)';
    $l2 = $lines2[$i] ?? '(EOF)';
    if ($l1 !== $l2) {
        echo "Line " . ($i+1) . ":\n";
        echo "  - " . $l1 . "\n";
        echo "  + " . $l2 . "\n";
    }
}
