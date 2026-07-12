import { copyFile, mkdir } from 'node:fs/promises';
await mkdir('public/assets/vendor', { recursive: true });
await copyFile('node_modules/chart.js/dist/chart.umd.js', 'public/assets/vendor/chart.umd.js');

