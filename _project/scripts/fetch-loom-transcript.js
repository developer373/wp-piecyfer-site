const fs = require('fs');
const path = require('path');
const https = require('https');

// Read content.md to extract captions_source_url or source_url
const content = fs.readFileSync('C:\\Users\\Haris Ali\\.gemini\\antigravity-ide\\brain\\585effe8-b29f-4b69-b305-8ca1f977a915\\.system_generated\\steps\\295\\content.md', 'utf8');

const matchTranscript = content.match(/"source_url":"(https:\/\/[^"]+)"/);
const matchCaptions = content.match(/"captions_source_url":"(https:\/\/[^"]+)"/);

function fetchUrl(url, outputPath) {
  return new Promise((resolve, reject) => {
    // replace unicode escapes if any
    const cleanUrl = url.replace(/\\u0026/g, '&');
    https.get(cleanUrl, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        fs.writeFileSync(outputPath, data, 'utf8');
        resolve(data);
      });
    }).on('error', reject);
  });
}

async function main() {
  if (matchTranscript) {
    console.log('Fetching transcript...');
    const data = await fetchUrl(matchTranscript[1], 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\loom_transcript.json');
    console.log('TRANSCRIPT DATA:\n', data);
  }
  if (matchCaptions) {
    console.log('Fetching captions...');
    const data = await fetchUrl(matchCaptions[1], 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\loom_captions.vtt');
    console.log('CAPTIONS DATA:\n', data);
  }
}

main().catch(console.error);
