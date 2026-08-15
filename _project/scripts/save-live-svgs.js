const https = require('https');
const fs = require('fs');

function fetchUrl(url) {
  return new Promise((resolve, reject) => {
    https.get(url, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve(data));
    }).on('error', reject);
  });
}

async function main() {
  const liveSales = await fetchUrl('https://www.piecyfer.com/wp-content/uploads/2024/09/Sales.svg');
  fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_sales_direct.svg', liveSales, 'utf8');
  console.log('LIVE SALES LENGTH:', liveSales.length);

  const liveManuf = await fetchUrl('https://www.piecyfer.com/wp-content/uploads/2024/09/Manufacturing.svg');
  fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_manuf_direct.svg', liveManuf, 'utf8');
  console.log('LIVE MANUF LENGTH:', liveManuf.length);
}

main().catch(console.error);
