const path = require('path');
const { execSync } = require('child_process');

// Run PHP to inspect elementor_data for the homepage
const phpScript = `
require_once 'wp-load.php';

$page_id = get_option('page_on_front');
echo "Front Page ID: " . $page_id . "\\n";

$data = get_post_meta($page_id, '_elementor_data', true);
if (is_string($data)) {
  $data = json_decode($data, true);
}

function find_sticky_sections($elements, &$found = []) {
  foreach ($elements as $el) {
    if (!empty($el['settings']['sticky'])) {
      $found[] = [
        'id' => $el['id'],
        'elType' => $el['elType'],
        'sticky' => $el['settings']['sticky'] ?? null,
        'sticky_offset' => $el['settings']['sticky_offset'] ?? null,
        'sticky_offset_tablet' => $el['settings']['sticky_offset_tablet'] ?? null,
        'sticky_offset_mobile' => $el['settings']['sticky_offset_mobile'] ?? null,
        'sticky_parent' => $el['settings']['sticky_parent'] ?? null,
      ];
    }
    if (!empty($el['elements'])) {
      find_sticky_sections($el['elements'], $found);
    }
  }
  return $found;
}

$sticky = find_sticky_sections($data);
echo "STICKY ELEMENTS IN HOMEPAGE DB:\\n";
echo json_encode($sticky, JSON_PRETTY_PRINT) . "\\n";
`;

const fs = require('fs');
fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\scripts\\check-elementor-db.php', phpScript);
