const fs = require('fs');
const https = require('https');

// Extract the signed m3u8 or raw CDN URL from content.md
const content = fs.readFileSync('C:\\Users\\Haris Ali\\.gemini\\antigravity-ide\\brain\\585effe8-b29f-4b69-b305-8ca1f977a915\\.system_generated\\steps\\295\\content.md', 'utf8');

const m3u8Match = content.match(/"url":"(https:\/\/luna\.loom\.com\/[^"]+)"/);
if (m3u8Match) {
  console.log('M3U8 URL:', m3u8Match[1]);
}
