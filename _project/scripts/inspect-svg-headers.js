const fs = require('fs');

function inspectSvg(filename) {
  const content = fs.readFileSync('D:\\laragon\\www\\piecyfer\\wp-content\\uploads\\2024\\09\\' + filename, 'utf8');
  console.log(`=== ${filename} ===`);
  console.log('Length:', content.length);
  console.log('Start:', content.substring(0, 300));
}

inspectSvg('HR.svg');
inspectSvg('Sales.svg');
inspectSvg('Manufacturing.svg');
inspectSvg('distribution-1.svg');
inspectSvg('IT-1.svg');
