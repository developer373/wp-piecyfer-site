<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/this-url-does-not-exist-404-test.html';
$f2 = __DIR__ . '/../snapshots/b-cutover-quick3/html/this-url-does-not-exist-404-test.html';

$h1 = file_get_contents($f1);
$h2 = file_get_contents($f2);

preg_match('/<nav[^>]*class="[^"]*elementskit[^"]*"[^>]*>(.*?)<\/nav>/s', $h1, $m1);
preg_match('/<nav[^>]*class="[^"]*elementskit[^"]*"[^>]*>(.*?)<\/nav>/s', $h2, $m2);

echo "Nav len: ref=" . strlen($m1[0] ?? '') . ", live=" . strlen($m2[0] ?? '') . "\n";
echo "Nav in ref:\n" . substr($m1[0] ?? '', 0, 300) . "\n";
echo "Nav in live:\n" . substr($m2[0] ?? '', 0, 300) . "\n";
