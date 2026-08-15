const fs = require('fs');

const liveManufContent = fs.readFileSync('D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_manuf_direct.svg', 'utf8');

// Extract the inner paths and defs from liveManufContent
const defsMatch = liveManufContent.match(/<defs>([\s\S]*?)<\/defs>/i);
let innerDefs = defsMatch ? defsMatch[1] : '';

// Remove any existing .label or styles if present, and customize clean styles
innerDefs = innerDefs.replace(/<style>[\s\S]*?<\/style>/i, '');

const innerPaths = liveManufContent
  .replace(/<\?xml.*?\?>/i, '')
  .replace(/<svg[^>]*>/i, '')
  .replace(/<\/svg>/i, '')
  .replace(/<defs>[\s\S]*?<\/defs>/i, '')
  .trim();

const manufSvg245 = `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="Layer_1" data-name="Layer 1" viewBox="0 0 245 170">
  <defs>
    <style>
      .cls-1{fill:none;}
      .cls-2{fill:#3668b1;}
      .cls-3{clip-path:url(#clip-path-m);}
      .cls-4{fill:#5d21a7;}
      .label{font-family:'Inter Tight','Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:16px;font-weight:500;fill:#1a1a1a;text-anchor:middle;letter-spacing:-0.2px;}
    </style>
    <clipPath id="clip-path-m">
      <path class="cls-1" d="M50.57,10.73a6.22,6.22,0,0,1-2.74,4.17l-1.52.78-.65,1.05-.32.92L46,19.23l0,3.49L39.34,33.43,33.58,36l-2.28,5,1.79,2.85,1.31,2.34,3.46,1.43L41,48l1.25,3.4,1.39,3.1,1.43,1.76.73,1.46v2.64l-.49,2.15.79,1.55,1.79.73L50,67.06l1.79,4.46L57,73.59S68.62,79,69.59,78.38s4.46-9.55,4.46-9.55l1.45-5.42L75.66,35l-1-7.53-1-9.3.16-12.79-7-3.81-6.07.89S55.27,4.89,54.3,6.51A28.76,28.76,0,0,1,50.57,10.73Z"/>
    </clipPath>
  </defs>
  <g transform="translate(82.5, 25)">
    ${innerPaths.replace(/clip-path:url\(#clip-path\)/g, 'clip-path:url(#clip-path-m)')}
  </g>
  <text class="label" x="122.5" y="142">Manufacturing</text>
</svg>`;

fs.writeFileSync('D:\\laragon\\www\\piecyfer\\wp-content\\uploads\\2024\\09\\Manufacturing.svg', manufSvg245, 'utf8');
fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\assets\\Manufacturing.svg', manufSvg245, 'utf8');

console.log('Successfully wrote authentic Manufacturing.svg to uploads and _project/assets!');
