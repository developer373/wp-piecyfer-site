const https = require('https');

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
  console.log('LIVE SALES SVG CONTENT:\n', liveSales);

  const liveManuf = await fetchUrl('https://www.piecyfer.com/wp-content/uploads/2024/09/Manufacturing.svg');
  console.log('LIVE MANUF SVG CONTENT:\n', liveManuf);
}

main().catch(console.error);
