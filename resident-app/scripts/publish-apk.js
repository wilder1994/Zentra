const fs = require('fs');
const path = require('path');

const src = path.join(__dirname, '..', 'android', 'app', 'build', 'outputs', 'apk', 'debug', 'app-debug.apk');
const destDir = path.join(__dirname, '..', '..', 'public', 'downloads');
const dest = path.join(destDir, 'zentra-residentes.apk');

if (!fs.existsSync(src)) {
  console.error('No hay APK en', src, '— ejecuta npm run apk primero.');
  process.exit(1);
}

fs.mkdirSync(destDir, { recursive: true });
fs.copyFileSync(src, dest);
console.log('Publicado', dest);
