<?php
$f1 = __DIR__ . '/../snapshots/ref3-a/html/this-url-does-not-exist-404-test.html';
$h1 = file_get_contents($f1);

preg_match_all('/<nav[^>]*>/', $h1, $navs);
print_r($navs[0]);
