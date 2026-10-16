const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const files = ['index.html', 'app.js', 'offline.js', 'manifest.json', 'sw.js'];
const dests = [
    path.join(root, 'www'),
    path.join(root, '..', 'public', 'campo'),
];

const laragonPwa = 'C:\\laragon\\www\\Zentra_Supervision';
if (fs.existsSync(laragonPwa)) {
    dests.push(laragonPwa);
}

for (const dest of dests) {
    fs.mkdirSync(dest, { recursive: true });
    for (const file of files) {
        fs.copyFileSync(path.join(root, file), path.join(dest, file));
    }
    console.log(dest + ' listo');
}
