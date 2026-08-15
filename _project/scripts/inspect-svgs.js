const fs = require('fs');

const hr = fs.readFileSync('D:\\laragon\\www\\piecyfer\\wp-content\\uploads\\2024\\09\\HR.svg', 'utf8');
const sales = fs.readFileSync('D:\\laragon\\www\\piecyfer\\wp-content\\uploads\\2024\\09\\Sales.svg', 'utf8');
const manuf = fs.readFileSync('D:\\laragon\\www\\piecyfer\\wp-content\\uploads\\2024\\09\\Manufacturing.svg', 'utf8');
const dist = fs.readFileSync('D:\\laragon\\www\\piecyfer\\wp-content\\uploads\\2024\\09\\distribution-1.svg', 'utf8');

console.log('=== HR.svg ===\n', hr);
console.log('=== Sales.svg ===\n', sales);
console.log('=== Manufacturing.svg ===\n', manuf);
console.log('=== distribution-1.svg ===\n', dist);
