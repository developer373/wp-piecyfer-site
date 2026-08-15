<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';

$terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
foreach ($terms as $t) {
    echo sprintf("%-35s -> %s\n", $t->slug, get_term_link($t));
}
