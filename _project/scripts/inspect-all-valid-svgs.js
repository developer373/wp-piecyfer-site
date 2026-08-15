const fs = require('fs');

const svgs = ['HR.svg', 'distribution-1.svg', 'finance-1.svg', 'IT-1.svg', 'mangement.svg', 'R-D-1.svg', 'production-1.svg', 'accounting-1.svg'];

svgs.forEach(name => {
  const content = fs.readFileSync('D:\\laragon\\www\\piecyfer\\wp-content\\uploads\\2024\\09\\' + name, 'utf8');
  console.log(`\n================== ${name} ==================`);
  console.log(content);
});
