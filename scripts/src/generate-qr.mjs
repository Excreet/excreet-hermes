import QRCode from 'qrcode';
import { writeFileSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

const TARGET_URL = 'https://excreet.com/card';

const outDir = join(__dirname, '../../scripts/qr-output');
mkdirSync(outDir, { recursive: true });

// 1. High-res PNG for print (600×600, quiet zone 4)
await QRCode.toFile(join(outDir, 'excreet-card-qr.png'), TARGET_URL, {
  type: 'png',
  width: 600,
  margin: 2,
  color: { dark: '#0a0318', light: '#FFFFFF' },
  errorCorrectionLevel: 'H',
});

// 2. Gold-on-dark version
await QRCode.toFile(join(outDir, 'excreet-card-qr-gold.png'), TARGET_URL, {
  type: 'png',
  width: 600,
  margin: 2,
  color: { dark: '#F5C518', light: '#0a0318' },
  errorCorrectionLevel: 'H',
});

// 3. SVG (vector, scalable for print / card designer)
await QRCode.toFile(join(outDir, 'excreet-card-qr.svg'), TARGET_URL, {
  type: 'svg',
  width: 600,
  margin: 2,
  color: { dark: '#0a0318', light: '#FFFFFF' },
  errorCorrectionLevel: 'H',
});

console.log('✓ QR codes generated in scripts/qr-output/');
console.log('  excreet-card-qr.png       — dark on white (print-safe)');
console.log('  excreet-card-qr-gold.png  — gold on dark (card design)');
console.log('  excreet-card-qr.svg       — vector (scalable)');
console.log('');
console.log('Target URL:', TARGET_URL);
