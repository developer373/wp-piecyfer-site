<?php
$json = json_decode(file_get_contents(__DIR__ . '/../behaviour-tool/results/test-b.json'), true);
foreach ($json['tests'] as $t) {
    if ($t['id'] === 'form-recaptcha-v3-token') {
        echo "=== CONSOLE ERRORS ===\n";
        print_r($t['consoleErrors'] ?? []);
        echo "=== JS ERRORS ===\n";
        print_r($t['jsErrors'] ?? []);
        echo "=== OBSERVED ===\n";
        print_r($t['observed'] ?? []);
    }
}
