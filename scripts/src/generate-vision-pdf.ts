import puppeteer from "puppeteer";
import path from "path";
import { fileURLToPath } from "url";
import fs from "fs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const htmlPath = path.resolve(__dirname, "generate-vision-pdf.html");
const outPath  = path.resolve(__dirname, "../../excreet-vision-document-2026.pdf");

const browser = await puppeteer.launch({
  headless: true,
  args: ["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage"],
});

const page = await browser.newPage();
await page.goto(`file://${htmlPath}`, { waitUntil: "networkidle0" });

await page.pdf({
  path: outPath,
  format: "A4",
  printBackground: true,
  margin: { top: "0", right: "0", bottom: "0", left: "0" },
});

await browser.close();

const size = (fs.statSync(outPath).size / 1024).toFixed(0);
console.log(`✅ PDF generated: ${outPath} (${size} KB)`);
