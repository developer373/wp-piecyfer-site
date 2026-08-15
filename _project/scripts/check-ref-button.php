<?php
$html = file_get_contents(__DIR__ . '/../snapshots/ref3-a/html/home-root.html');
if (preg_match('/<div[^>]*elementor-element-eefe266.*?<\/div>\s*<\/div>/s', $html, $m)) {
    echo "=== REF3-A BUTTON HTML ===\n";
    echo $m[0] . "\n";
} else {
    echo "eefe266 not found in ref3-a home-root.html\n";
}
