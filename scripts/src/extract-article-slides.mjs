import { PDFDocument } from 'pdf-lib';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const inputPath  = path.resolve(__dirname, '../../.local/outputs/Fatigue-Is-Not-a-Caffeine-Deficiency---Excreet-Think-Tank.pdf');
const outputPath = path.resolve(__dirname, '../../artifacts/hermes-ui/public/excreet-article-deck-fatigue.pdf');

const srcBytes = fs.readFileSync(inputPath);
const srcDoc   = await PDFDocument.load(srcBytes);
const outDoc   = await PDFDocument.create();

// Slides 7–16 are pages index 6–15 (0-based)
const pageIndices = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
const copied = await outDoc.copyPages(srcDoc, pageIndices);
copied.forEach(p => outDoc.addPage(p));

const outBytes = await outDoc.save();
fs.writeFileSync(outputPath, outBytes);
console.log(`✅ Article deck written: ${outputPath}`);
console.log(`   Pages: ${outDoc.getPageCount()}  |  Size: ${(outBytes.length / 1024).toFixed(1)} KB`);
