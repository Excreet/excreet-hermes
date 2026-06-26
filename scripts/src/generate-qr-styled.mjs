/**
 * generate-qr-styled.mjs
 * Coca-Cola-style QR: circular dot modules, custom corner eyes, logo in centre.
 * Outputs PNG via Puppeteer (already a dependency).
 */
import QRCode from 'qrcode';
import puppeteer from 'puppeteer';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

const TARGET_URL  = 'https://excreet.com/card';
const OUT_DIR     = join(__dirname, '../../scripts/qr-output');
const LOGO_PATH   = join(__dirname, '../../artifacts/hermes-ui/public/excreet-logo-letterhead.png');

// Brand colours
const PURPLE = '#7A2885';   // lighter Excreet purple (was #56075E)
const GOLD   = '#F5C518';
const WHITE  = '#FFFFFF';

mkdirSync(OUT_DIR, { recursive: true });

// ── 1. Get QR matrix ──────────────────────────────────────────────────────────
const qr   = QRCode.create(TARGET_URL, { errorCorrectionLevel: 'H' });
const SIZE  = qr.modules.size;          // 29
const DATA  = qr.modules.data;          // Uint8Array, row-major

function isDark(row, col) {
  if (row < 0 || col < 0 || row >= SIZE || col >= SIZE) return false;
  return DATA[row * SIZE + col] === 1;
}

// Finder pattern bounding boxes [rowStart, colStart]
const FINDERS = [
  [0, 0],          // top-left
  [0, SIZE - 7],   // top-right
  [SIZE - 7, 0],   // bottom-left
];
function inFinder(row, col) {
  for (const [r, c] of FINDERS) {
    if (row >= r && row < r + 7 && col >= c && col < c + 7) return true;
  }
  return false;
}

// ── 2. Build SVG string ───────────────────────────────────────────────────────
const CANVAS   = 600;
const MARGIN   = 24;                        // px of quiet zone
const INNER    = CANVAS - MARGIN * 2;       // 552
const CELL     = INNER / SIZE;              // ~19.03 px per module
const R        = CELL * 0.42;              // circle radius ≈ 42% of cell

function cx(col) { return MARGIN + col * CELL + CELL / 2; }
function cy(row) { return MARGIN + row * CELL + CELL / 2; }

// Logo as base64
let logoB64 = '';
try {
  const buf = readFileSync(LOGO_PATH);
  logoB64 = 'data:image/png;base64,' + buf.toString('base64');
} catch { /* no logo — skip */ }

// Logo circle covers ~20% of canvas (H correction handles 30%)
const LOGO_R    = CANVAS * 0.13;   // 78 px radius
const LOGO_SIZE = LOGO_R * 1.7;    // logo image width/height inside circle

// ── Data dots ────────────────────────────────────────────────────────────────
let dots = '';
for (let row = 0; row < SIZE; row++) {
  for (let col = 0; col < SIZE; col++) {
    if (!isDark(row, col) || inFinder(row, col)) continue;
    dots += `<circle cx="${cx(col).toFixed(2)}" cy="${cy(row).toFixed(2)}" r="${R.toFixed(2)}" fill="${PURPLE}"/>`;
  }
}

// ── Finder pattern renderer ──────────────────────────────────────────────────
function finderSVG(startRow, startCol) {
  const x  = MARGIN + startCol * CELL;
  const y  = MARGIN + startRow * CELL;
  const W  = 7 * CELL;
  const rr = CELL * 0.6;   // corner radius for outer frame

  // Outer rounded square (gold border)
  const outerPath = `<rect x="${x.toFixed(2)}" y="${y.toFixed(2)}" width="${W.toFixed(2)}" height="${W.toFixed(2)}" rx="${rr.toFixed(2)}" fill="${GOLD}" stroke="none"/>`;

  // White inner (clears the gold, leaving a gold ring)
  const gap  = CELL;
  const innerX = x + gap;
  const innerY = y + gap;
  const innerW = W - gap * 2;
  const innerRR = CELL * 0.3;
  const innerWhite = `<rect x="${innerX.toFixed(2)}" y="${innerY.toFixed(2)}" width="${innerW.toFixed(2)}" height="${innerW.toFixed(2)}" rx="${innerRR.toFixed(2)}" fill="${WHITE}"/>`;

  // Purple inner square (3×3 modules centred)
  const sqGap = CELL * 2;
  const sqX   = x + sqGap;
  const sqY   = y + sqGap;
  const sqW   = W - sqGap * 2;
  const sqRR  = CELL * 0.25;
  const innerSquare = `<rect x="${sqX.toFixed(2)}" y="${sqY.toFixed(2)}" width="${sqW.toFixed(2)}" height="${sqW.toFixed(2)}" rx="${sqRR.toFixed(2)}" fill="${PURPLE}"/>`;

  return outerPath + innerWhite + innerSquare;
}

let finders = '';
for (const [r, c] of FINDERS) {
  finders += finderSVG(r, c);
}

// ── Logo area ─────────────────────────────────────────────────────────────────
const CX = CANVAS / 2;
const CY = CANVAS / 2;
const logoLayer = logoB64
  ? `<circle cx="${CX}" cy="${CY}" r="${LOGO_R}" fill="${WHITE}"/>
     <image href="${logoB64}" x="${(CX - LOGO_SIZE/2).toFixed(1)}" y="${(CY - LOGO_SIZE/2).toFixed(1)}" width="${LOGO_SIZE.toFixed(1)}" height="${LOGO_SIZE.toFixed(1)}" preserveAspectRatio="xMidYMid meet"/>`
  : `<circle cx="${CX}" cy="${CY}" r="${LOGO_R}" fill="${WHITE}"/>
     <text x="${CX}" y="${CY+6}" font-family="serif" font-size="14" fill="${PURPLE}" text-anchor="middle">EXCREET</text>`;

const SVG = `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${CANVAS}" height="${CANVAS}" viewBox="0 0 ${CANVAS} ${CANVAS}">
  <rect width="${CANVAS}" height="${CANVAS}" fill="${WHITE}"/>
  ${dots}
  ${finders}
  ${logoLayer}
</svg>`;

// Save SVG
const svgPath = join(OUT_DIR, 'excreet-card-qr-styled.svg');
writeFileSync(svgPath, SVG, 'utf8');
console.log('✓ SVG written:', svgPath);

// ── 3. Render to PNG via ImageMagick (magick / convert) ──────────────────────
import { execFileSync } from 'child_process';

const pngPath = join(OUT_DIR, 'excreet-card-qr-styled.png');
const magick  = (() => { try { execFileSync('magick', ['--version'], { stdio:'pipe' }); return 'magick'; } catch { return 'convert'; } })();

execFileSync(magick, ['-background', 'white', '-density', '150', svgPath, pngPath], { stdio: 'inherit' });
console.log('✓ PNG written:', pngPath);
console.log('');
console.log('  Purple:', PURPLE, '| Gold:', GOLD);
console.log('  URL:', TARGET_URL);
