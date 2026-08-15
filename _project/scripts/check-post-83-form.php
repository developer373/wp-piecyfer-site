<?php
require_once __DIR__ . '/../../wp-load.php';

$data = get_post_meta(83, '_elementor_data', true);
if (is_string($data)) {
    $data = json_decode($data, true);
}

function find_form($elements, &$forms) {
    foreach ($elements as $el) {
        if (($el['widgetType'] ?? '') === 'form') {
            $forms[] = $el;
        }
        if (!empty($el['elements'])) {
            find_form($el['elements'], $forms);
        }
    }
}

$forms = [];
find_form($data, $forms);

echo "Found " . count($forms) . " forms in post 83:\n";
foreach ($forms as $f) {
    foreach ($f['settings']['form_fields'] as $field) {
        if ($field['custom_id'] === 'contact_us_products') {
            echo "Options for contact_us_products:\n";
            echo $field['field_options'] . "\n";
        }
    }
}
