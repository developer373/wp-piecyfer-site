<?php
$html = file_get_contents(__DIR__ . '/../snapshots/ref3-a/html/home-root.html');
preg_match_all('/<a[^>]*class="[^"]*elementor-button[^"]*"[^>]*>.*?<\/a>/s', $html, $m);
echo "Buttons in ref3-a home-root.html count: " . count($m[0]) . "\n";
foreach ($m[0] as $i => $btn) {
    echo "[$i] " . substr(strip_tags($btn), 0, 50) . " | HTML: " . substr($btn, 0, 120) . "\n";
}
