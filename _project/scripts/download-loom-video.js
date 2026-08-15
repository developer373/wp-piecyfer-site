const fs = require('fs');
const https = require('https');

const videoUrl = 'https://cdn.loom.com/sessions/thumbnails/0a18b5e5da2948638cebc425bad1ce28-d7fa83a4c208af00.mp4';
const outputPath = 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\loom_preview.mp4';

const file = fs.createWriteStream(outputPath);
https.get(videoUrl, (response) => {
  response.pipe(file);
  file.on('finish', () => {
    file.close();
    console.log('Downloaded loom preview video to:', outputPath);
  });
}).on('error', (err) => {
  console.error('Error downloading:', err);
});
