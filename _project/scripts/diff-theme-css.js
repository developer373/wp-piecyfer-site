const fs = require('fs');
const path = require('path');

const files = [
  ['wp-content/themes/tecnologia/vamtam/assets/css/dist/elementor/elementor-all.css', 'wp-content/themes/piecyfer-theme/assets/css/theme.css'],
  ['wp-content/themes/tecnologia/vamtam/assets/css/dist/elementor/responsive/elementor-max.css', 'wp-content/themes/piecyfer-theme/assets/css/theme-max.css'],
  ['wp-content/themes/tecnologia/vamtam/assets/css/dist/elementor/responsive/elementor-below-max.css', 'wp-content/themes/piecyfer-theme/assets/css/theme-below-max.css'],
  ['wp-content/themes/tecnologia/vamtam/assets/css/dist/elementor/responsive/elementor-small.css', 'wp-content/themes/piecyfer-theme/assets/css/theme-small.css'],
];

for (const [f1, f2] of files) {
  console.log(`\n=== DIFF FOR ${f2} ===`);
  const c1 = fs.readFileSync(path.join(__dirname, '../../', f1), 'utf8');
  const c2 = fs.readFileSync(path.join(__dirname, '../../', f2), 'utf8');
  
  // Find where they diverge
  let diffStart = -1;
  for (let i = 0; i < Math.min(c1.length, c2.length); i++) {
    if (c1[i] !== c2[i]) {
      diffStart = i;
      break;
    }
  }
  if (diffStart !== -1) {
    console.log(`Diverges at char ${diffStart}:`);
    console.log(`TECNOLOGIA: [${c1.substring(diffStart, diffStart + 100)}]`);
    console.log(`PIECYFER:   [${c2.substring(diffStart, diffStart + 100)}]`);
  } else {
    console.log(`c1 length: ${c1.length}, c2 length: ${c2.length}`);
    console.log(`Extra in tecnologia: [${c1.substring(c2.length)}]`);
  }
}
