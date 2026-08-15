const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));
const fs = require('fs');

// Raw Sales.svg icon
const salesRaw = fs.readFileSync('D:\\laragon\\www\\piecyfer\\wp-content\\uploads\\2024\\09\\Sales.svg', 'utf8');
// Raw Manufacturing.svg icon
const manufRaw = fs.readFileSync('D:\\laragon\\www\\piecyfer\\wp-content\\uploads\\2024\\09\\Manufacturing.svg', 'utf8');

console.log('SALES RAW:', salesRaw);
console.log('MANUF RAW:', manufRaw);
