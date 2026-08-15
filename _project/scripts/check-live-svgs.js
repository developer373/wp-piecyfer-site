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

async function checkLiveSVGs() {
  const liveSales = await fetchUrl('https://www.piecyfer.com/wp-content/uploads/2024/09/Sales.svg');
  console.log('LIVE Sales.svg length:', liveSales.length);
  console.log('LIVE Sales.svg start:\n', liveSales.substring(0, 300));

  const liveManuf = await fetchUrl('https://www.piecyfer.com/wp-content/uploads/2024/09/Manufacturing.svg');
  console.log('LIVE Manufacturing.svg length:', liveManuf.length);
  console.log('LIVE Manufacturing.svg start:\n', liveManuf.substring(0, 300));
}

checkLiveSVGs().catch(console.error);
