import { PDFDocument } from 'pdf-lib';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.resolve(__dirname, '../../artifacts/hermes-ui/public/excreet-article-deck-fatigue.pdf');

const doc = await PDFDocument.create();

for (let i = 1; i <= 10; i++) {
  const file = path.resolve(__dirname, `../../scripts/slide-captures/slide${String(i).padStart(2,'0')}.jpg`);
  const bytes = fs.readFileSync(file);
  const img = await doc.embedJpg(bytes);
  const page = doc.addPage([img.width, img.height]);
  page.drawImage(img, { x: 0, y: 0, width: img.width, height: img.height });
  console.log(`✅ Slide ${i} added`);
}

const out = await doc.save();
fs.writeFileSync(OUT, out);
console.log(`\n✅ PDF written: ${OUT}`);
console.log(`   Pages: ${doc.getPageCount()}  |  Size: ${(out.length / 1024).toFixed(0)} KB`);
