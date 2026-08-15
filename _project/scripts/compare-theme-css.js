const fs = require('fs');
const path = require('path');

const files = [
  ['wp-content/themes/tecnologia/vamtam/assets/css/dist/elementor/elementor-all.css', 'wp-content/themes/piecyfer-theme/assets/css/theme.css'],
  ['wp-content/themes/tecnologia/vamtam/assets/css/dist/elementor/responsive/elementor-max.css', 'wp-content/themes/piecyfer-theme/assets/css/theme-max.css'],
  ['wp-content/themes/tecnologia/vamtam/assets/css/dist/elementor/responsive/elementor-below-max.css', 'wp-content/themes/piecyfer-theme/assets/css/theme-below-max.css'],
  ['wp-content/themes/tecnologia/vamtam/assets/css/dist/elementor/responsive/elementor-small.css', 'wp-content/themes/piecyfer-theme/assets/css/theme-small.css'],
];

for (const [f1, f2] of files) {
  const c1 = fs.readFileSync(path.join(__dirname, '../../', f1), 'utf8');
  const c2 = fs.readFileSync(path.join(__dirname, '../../', f2), 'utf8');
  console.log(`${f1} (${c1.length} B) vs ${f2} (${c2.length} B) -> match: ${c1 === c2}`);
  if (c1 !== c2) {
    console.log(`  Length diff: ${c1.length - c2.length}`);
  }
}
