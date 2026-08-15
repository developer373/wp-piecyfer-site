<?php
function show_diff($file) {
    echo "========================================\n";
    echo "DIFF FOR $file\n";
    echo "========================================\n";
    
    $f1 = __DIR__ . '/../snapshots/ref3-a/html/' . $file;
    $f2 = __DIR__ . '/../snapshots/b-cutover-quick/html/' . $file;
    
    if (!file_exists($f1) || !file_exists($f2)) {
        echo "One of the files does not exist\n";
        return;
    }
    
    $h1 = file($f1, FILE_IGNORE_NEW_LINES);
    $h2 = file($f2, FILE_IGNORE_NEW_LINES);
    
    echo "ref3-a lines: " . count($h1) . ", b-cutover-quick lines: " . count($h2) . "\n";
    
    $diffs = 0;
    for ($i = 0; $i < max(count($h1), count($h2)); $i++) {
        $l1 = $h1[$i] ?? '(EOF)';
        $l2 = $h2[$i] ?? '(EOF)';
        if ($l1 !== $l2) {
            $diffs++;
            echo "Line " . ($i + 1) . ":\n";
            echo "  - " . substr($l1, 0, 120) . "\n";
            echo "  + " . substr($l2, 0, 120) . "\n";
            if ($diffs >= 10) {
                echo "... more diffs truncated ...\n";
                break;
            }
        }
    }
    if ($diffs === 0) {
        echo "IDENTICAL!\n";
    }
}

show_diff('contact-us.html');
show_diff('home-root.html');
