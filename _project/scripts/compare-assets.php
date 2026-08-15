<?php
function compare_assets($file) {
    echo "========================================\n";
    echo "ASSETS COMPARISON FOR $file\n";
    echo "========================================\n";
    
    $f1 = __DIR__ . '/../snapshots/ref3-a/html/' . $file;
    $f2 = __DIR__ . '/../snapshots/b-cutover-quick/html/' . $file;
    
    $h1 = file_get_contents($f1);
    $h2 = file_get_contents($f2);
    
    preg_match_all('/<link[^>]+stylesheet[^>]*>/i', $h1, $links1);
    preg_match_all('/<link[^>]+stylesheet[^>]*>/i', $h2, $links2);
    
    echo "STYLESHEETS in ref3-a (" . count($links1[0]) . "):\n";
    foreach ($links1[0] as $l) {
        if (preg_match('/id=[\'"]([^\'"]+)[\'"]/', $l, $m)) echo "  - " . $m[1] . "\n";
        else echo "  - (no id): " . substr($l, 0, 80) . "\n";
    }
    
    echo "\nSTYLESHEETS in b-cutover-quick (" . count($links2[0]) . "):\n";
    foreach ($links2[0] as $l) {
        if (preg_match('/id=[\'"]([^\'"]+)[\'"]/', $l, $m)) echo "  - " . $m[1] . "\n";
        else echo "  - (no id): " . substr($l, 0, 80) . "\n";
    }
    
    preg_match_all('/<script[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $h1, $scripts1);
    preg_match_all('/<script[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $h2, $scripts2);
    
    echo "\nSCRIPTS in ref3-a (" . count($scripts1[1]) . "):\n";
    foreach ($scripts1[1] as $s) echo "  - " . basename($s) . "\n";
    
    echo "\nSCRIPTS in b-cutover-quick (" . count($scripts2[1]) . "):\n";
    foreach ($scripts2[1] as $s) echo "  - " . basename($s) . "\n";
}

compare_assets('contact-us.html');
