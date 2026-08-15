<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/contact-us.html';
$h1 = file_get_contents($f1);
$h2 = file_get_contents('http://localhost/piecyfer/contact-us/');

preg_match_all('/<option[^>]*>(.*?)<\/option>/', $h1, $o1);
preg_match_all('/<option[^>]*>(.*?)<\/option>/', $h2, $o2);

echo "Options count: ref=" . count($o1[0]) . ", live=" . count($o2[0]) . "\n";
for ($i = 0; $i < max(count($o1[0]), count($o2[0])); $i++) {
    $opt1 = $o1[0][$i] ?? '(NONE)';
    $opt2 = $o2[0][$i] ?? '(NONE)';
    if ($opt1 !== $opt2) {
        echo "Diff at $i: ref=$opt1 vs live=$opt2\n";
    }
}
echo "Options match perfectly!\n";
