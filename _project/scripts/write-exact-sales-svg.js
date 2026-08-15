const fs = require('fs');

const liveSalesContent = fs.readFileSync('D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_sales_direct.svg', 'utf8');

// Extract the inner paths from liveSalesContent
const innerPaths = liveSalesContent
  .replace(/<\?xml.*?\?>/i, '')
  .replace(/<svg[^>]*>/i, '')
  .replace(/<\/svg>/i, '')
  .replace(/<defs>[\s\S]*?<\/defs>/i, '')
  .trim();

const salesSvg245 = `<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 245 170">
  <defs>
    <style>
      .cls-1{fill:#5d21a7;}
      .cls-2{fill:#3668b1;}
      .label{font-family:'Inter Tight','Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:16px;font-weight:500;fill:#1a1a1a;text-anchor:middle;letter-spacing:-0.2px;}
    </style>
  </defs>
  <g transform="translate(82.5, 25)">
    ${innerPaths}
  </g>
  <text class="label" x="122.5" y="142">Sales</text>
</svg>`;

fs.writeFileSync('D:\\laragon\\www\\piecyfer\\wp-content\\uploads\\2024\\09\\Sales.svg', salesSvg245, 'utf8');
fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\assets\\Sales.svg', salesSvg245, 'utf8');

console.log('Successfully wrote authentic Sales.svg to uploads and _project/assets!');
