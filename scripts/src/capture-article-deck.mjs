import puppeteer from 'puppeteer';
import { PDFDocument } from 'pdf-lib';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.resolve(__dirname, '../../artifacts/hermes-ui/public/excreet-article-deck-fatigue.pdf');

const BASE = 'http://localhost:80/excreet-pitch-deck';
const SLIDES = [7,8,9,10,11,12,13,14,15,16];

console.log('Launching browser...');
const browser = await puppeteer.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
});

const page = await browser.newPage();
await page.setViewport({ width: 1280, height: 720 });

const pdfDoc = await PDFDocument.create();

for (const slideNum of SLIDES) {
  const url = `${BASE}/slide${slideNum}`;
  console.log(`  Capturing ${url}...`);
  await page.goto(url, { waitUntil: 'networkidle0', timeout: 30000 });
  await new Promise(r => setTimeout(r, 800)); // let fonts/images settle

  const pdfBytes = await page.pdf({
    width: '1280px',
    height: '720px',
    printBackground: true,
  });

  const slidePdf = await PDFDocument.load(pdfBytes);
  const [copiedPage] = await pdfDoc.copyPages(slidePdf, [0]);
  pdfDoc.addPage(copiedPage);
  console.log(`  ✅ slide${slideNum} done`);
}

await browser.close();

const outBytes = await pdfDoc.save();
fs.writeFileSync(OUT, outBytes);
console.log(`\n✅ PDF written: ${OUT}`);
console.log(`   Pages: ${pdfDoc.getPageCount()}  |  Size: ${(outBytes.length / 1024).toFixed(1)} KB`);
