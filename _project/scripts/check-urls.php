<?php
function check_url($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$http_code, $err];
}

list($code1, $err1) = check_url('http://localhost/piecyfer/');
echo "URL / : HTTP $code1" . ($err1 ? " (Error: $err1)" : "") . "\n";

list($code2, $err2) = check_url('http://localhost/piecyfer/contact-us/');
echo "URL /contact-us/ : HTTP $code2" . ($err2 ? " (Error: $err2)" : "") . "\n";

if ($code1 === 200 && $code2 === 200) {
    echo "STATUS: ALL 200 OK\n";
    exit(0);
} else {
    echo "STATUS: FAILED\n";
    exit(1);
}
