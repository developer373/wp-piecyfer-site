<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/this-url-does-not-exist-404-test.html';
$f2 = __DIR__ . '/../snapshots/b-cutover-quick3/html/this-url-does-not-exist-404-test.html';

$h1 = file_get_contents($f1);
$h2 = file_get_contents($f2);

preg_match_all('/<link[^>]*rel=[\'"]stylesheet[\'"][^>]*>/i', $h1, $links1);
preg_match_all('/<link[^>]*rel=[\'"]stylesheet[\'"][^>]*>/i', $h2, $links2);

function extract_hrefs($tags) {
    $res = [];
    foreach ($tags as $t) {
        if (preg_match('/id=[\'"]([^\'"]+)[\'"]/', $t, $id)) {
            $idStr = $id[1];
        } else {
            $idStr = 'no-id';
        }
        if (preg_match('/href=[\'"]([^\'"]+)[\'"]/', $t, $href)) {
            $h = $href[1];
            // simplify url
            $h = preg_replace('/\?ver=[^&"\']+/', '', $h);
            $res[$idStr] = $h;
        }
    }
    return $res;
}

$css1 = extract_hrefs($links1[0]);
$css2 = extract_hrefs($links2[0]);

echo "Stylesheets in ref3-a but NOT in b-cutover-quick3:\n";
foreach ($css1 as $id => $href) {
    if (!isset($css2[$id])) {
        echo "  - [$id] $href\n";
    }
}

echo "\nStylesheets in b-cutover-quick3 but NOT in ref3-a:\n";
foreach ($css2 as $id => $href) {
    if (!isset($css1[$id])) {
        echo "  + [$id] $href\n";
    }
}
