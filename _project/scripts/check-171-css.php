<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/this-url-does-not-exist-404-test.html';
$f2 = __DIR__ . '/../snapshots/b-cutover-quick3/html/this-url-does-not-exist-404-test.html';

$h1 = file_get_contents($f1);
$h2 = file_get_contents($f2);

preg_match('/<link[^>]*id=[\'"]elementor-post-171-css[\'"][^>]*>/', $h1, $m1);
preg_match('/<link[^>]*id=[\'"]elementor-post-171-css[\'"][^>]*>/', $h2, $m2);

echo "171 CSS in ref3-a:\n  " . ($m1[0] ?? 'NONE') . "\n";
echo "171 CSS in b-cutover-quick3:\n  " . ($m2[0] ?? 'NONE') . "\n";
