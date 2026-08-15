<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/this-url-does-not-exist-404-test.html';
$f2 = __DIR__ . '/../snapshots/b-cutover-quick3/html/this-url-does-not-exist-404-test.html';

$h1 = file_get_contents($f1);
$h2 = file_get_contents($f2);

preg_match_all('/<style([^>]*)>(.*?)<\/style>/si', $h1, $styles1, PREG_SET_ORDER);
preg_match_all('/<style([^>]*)>(.*?)<\/style>/si', $h2, $styles2, PREG_SET_ORDER);

function map_styles($styles) {
    $res = [];
    foreach ($styles as $i => $s) {
        $attrs = $s[1];
        $id = 'no-id-' . $i;
        if (preg_match('/id=[\'"]([^\'"]+)[\'"]/', $attrs, $m)) {
            $id = $m[1];
        }
        $res[$id] = trim($s[2]);
    }
    return $res;
}

$s1 = map_styles($styles1);
$s2 = map_styles($styles2);

echo "Style tags count: ref=" . count($s1) . ", new=" . count($s2) . "\n\n";

echo "Styles in ref3-a but NOT in b-cutover-quick3:\n";
foreach ($s1 as $id => $content) {
    if (!isset($s2[$id])) {
        echo "  - [$id] (len " . strlen($content) . ")\n";
    }
}

echo "\nStyles in b-cutover-quick3 but NOT in ref3-a:\n";
foreach ($s2 as $id => $content) {
    if (!isset($s1[$id])) {
        echo "  + [$id] (len " . strlen($content) . ")\n";
    }
}

echo "\nStyles with different content:\n";
foreach ($s1 as $id => $c1) {
    if (isset($s2[$id])) {
        $c2 = $s2[$id];
        if ($c1 !== $c2) {
            echo "  ~ [$id] ref len=" . strlen($c1) . ", new len=" . strlen($c2) . "\n";
        }
    }
}
