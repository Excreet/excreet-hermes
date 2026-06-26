import PDFDocument from 'pdfkit';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outputPath = path.resolve(__dirname, '../../artifacts/hermes-ui/public/excreet-technical-report-v3.pdf');

const doc = new PDFDocument({
  size: 'LETTER',
  margins: { top: 72, bottom: 72, left: 72, right: 72 },
  bufferPages: true,
  info: {
    Title: 'Excreet — Technical Report v3',
    Author: 'Excreet',
    Subject: 'Platform Architecture, Feature Delivery & Deployment Record',
  },
});

const stream = fs.createWriteStream(outputPath);
doc.pipe(stream);

// Palette
const PURPLE     = '#6B21A8';
const PURPLE_DK  = '#2a0a4a';
const GOLD       = '#B8860B';
const GOLD_LT    = '#C9A84C';
const DARKGRAY   = '#1a1a2e';
const MIDGRAY    = '#444444';
const LIGHTGRAY  = '#777777';
const WHITE      = '#ffffff';
const GREEN      = '#166534';
const RED        = '#7f1d1d';

const PW = doc.page.width  - 72 - 72;  // printable width

// ─── HELPERS ──────────────────────────────────────────────────────────────────

const h1 = (t) => doc.fillColor(DARKGRAY).font('Helvetica-Bold').fontSize(22).text(t, { align: 'left' }).moveDown(0.3);
const h2 = (t) => {
  doc.moveDown(1.2).fillColor(PURPLE).font('Helvetica-Bold').fontSize(13).text(t.toUpperCase(), { characterSpacing: 1.2 });
  const y = doc.y + 3;
  doc.fillColor(GOLD_LT).rect(72, y, 44, 2).fill();
  doc.moveDown(0.7);
};
const h3 = (t) => doc.moveDown(0.6).fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(11).text(t).moveDown(0.2);
const body = (t) => doc.fillColor(MIDGRAY).font('Helvetica').fontSize(10).lineGap(3).text(t, { align: 'justify' }).moveDown(0.4);
const bullet = (t) => doc.fillColor(MIDGRAY).font('Helvetica').fontSize(10).lineGap(2).text('•  ' + t, { indent: 10 });
const badge = (label, color, textColor = WHITE) => {
  const x = doc.x; const y = doc.y;
  const w = doc.widthOfString(label) + 12;
  doc.roundedRect(x, y, w, 16, 3).fill(color);
  doc.fillColor(textColor).font('Helvetica-Bold').fontSize(8).text(label, x + 6, y + 4, { lineBreak: false });
  doc.x = 72; doc.moveDown(1.2);
};
const rule = () => { doc.moveDown(0.5); doc.fillColor('#dddddd').rect(72, doc.y, PW, 1).fill(); doc.moveDown(0.8); };
const kv = (k, v) => {
  doc.fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(10).text(k + '  ', { continued: true });
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(10).text(v).moveDown(0.1);
};

const phaseBox = (num, title, version, status = 'complete') => {
  const color = status === 'complete' ? GREEN : status === 'active' ? GOLD : LIGHTGRAY;
  const label = status === 'complete' ? 'COMPLETE' : status === 'active' ? 'IN PROGRESS' : 'PLANNED';
  doc.moveDown(0.6);
  doc.fillColor(color).rect(72, doc.y, 4, 36).fill();
  const tx = 84; const ty = doc.y;
  doc.fillColor(DARKGRAY).font('Helvetica-Bold').fontSize(11).text(`Phase ${num} — ${title}`, tx, ty, { width: PW - 80 });
  doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(9).text(`${version}   ·   `, { continued: true }).fillColor(color).font('Helvetica-Bold').text(label);
  doc.x = 72; doc.moveDown(0.6);
};

// ══════════════════════════════════════════════════════════════════════════════
// COVER PAGE
// ══════════════════════════════════════════════════════════════════════════════
doc.rect(0, 0, doc.page.width, doc.page.height).fill(DARKGRAY);

doc.moveDown(7);
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(48).text('EXCREET', { align: 'center', characterSpacing: 10 });
doc.fillColor(WHITE).font('Helvetica').fontSize(13).moveDown(0.3).text('CLEANS COMPLETE', { align: 'center', characterSpacing: 5 });

doc.moveDown(1.2);
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(11).text('──────────────────────────────', { align: 'center' });
doc.moveDown(0.6);
doc.fillColor('#cccccc').font('Helvetica-Oblique').fontSize(15).text('Technical Report  v3', { align: 'center' });
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(11).moveDown(0.5).text('Platform Architecture · Feature Delivery · Deployment Record', { align: 'center' });

doc.moveDown(12);
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(9)
  .text('Confidential — ' + new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }), { align: 'center' });

// ══════════════════════════════════════════════════════════════════════════════
// PAGE 2 — Executive Summary + Stack
// ══════════════════════════════════════════════════════════════════════════════
doc.addPage();

h1('Executive Summary');
doc.fillColor(GOLD).font('Helvetica-Oblique').fontSize(11).text('Pre-Clinical Cellular Health Intelligence — v3 Platform State').moveDown(0.8);

body('Excreet is a members-only pre-clinical health intelligence platform delivered at excreet.com. It combines a WordPress/WooCommerce front-end hosted on SiteGround with a custom Express/TypeScript backend (Hermes) running on Replit. The platform provides AI-driven Body Score assessments, a Ministry of Healing conversational health companion, a Partner Product Store, and a clinical-grade provider report — all gate-kept behind a PMPro membership tier system.');

body('This report covers the complete engineering delivery record from Phase 1 (scaffold) through Phase 16 (WooCommerce store completion), the full production stack, and the canonical affiliate program rules.');

rule();

h2('Production Stack');

kv('Frontend:', 'WordPress 6.x on SiteGround shared hosting (PHP 8.x, nginx)');
kv('Membership:', 'Paid Memberships Pro (PMPro) — Starter $15/mo · Premium $25/mo');
kv('Commerce:', 'WooCommerce — affiliate product store (Amazon Associates tag: excreetshop06-20)');
kv('Backend:', 'Hermes API — Express 5 / TypeScript / Node 24 on Replit');
kv('Database:', 'PostgreSQL (Replit-managed) + Drizzle ORM');
kv('Validation:', 'Zod v4 + drizzle-zod');
kv('AI:', 'OpenAI GPT-4o — HCC scoring, Ministry of Healing, background generation');
kv('Auth:', 'Bearer token (HERMES_API_KEY) — WordPress ↔ Hermes');
kv('Deploy pipeline:', 'Replit SSH/SCP → SiteGround mu-plugins + nginx PURGE cache invalidation');
kv('Monorepo:', 'pnpm workspaces — TypeScript 5.9, esbuild CJS bundle');

rule();

h2('Hermes API Endpoints');

const endpoints = [
  ['GET',  '/api/hermes/health',                     'Public uptime check'],
  ['POST', '/api/hermes/intake',                     'Accept WP form → create HCC job'],
  ['GET',  '/api/hermes/job-status/:jobId',          'WordPress polls for AI result'],
  ['GET',  '/api/hermes/ministry/history/:memberId', 'Load Ministry chat history'],
  ['POST', '/api/hermes/ministry/history/mark',      'Append rebaseline system note'],
  ['POST', '/api/hermes/ministry/history/reset',     'Clear session (Start New Session)'],
  ['POST', '/api/hermes/admin/rotate-background',    'Manual DALL-E bg image trigger'],
];

endpoints.forEach(([method, path2, desc]) => {
  const mColor = method === 'GET' ? '#1d4ed8' : '#7c2d12';
  doc.fillColor(mColor).font('Helvetica-Bold').fontSize(9)
    .text(method, { continued: true, width: 36 });
  doc.fillColor(PURPLE_DK).font('Courier').fontSize(9)
    .text('  ' + path2, { continued: true, width: 280 });
  doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(9)
    .text('  — ' + desc);
});

// ══════════════════════════════════════════════════════════════════════════════
// PAGE 3 — Affiliate Program (Canonical)
// ══════════════════════════════════════════════════════════════════════════════
doc.addPage();

h2('Affiliate Program Rules — Canonical');
body('The following rules are the authoritative source of truth. No UI, copy, or onboarding flow may contradict them without owner confirmation.');

doc.moveDown(0.4);
h3('Starter Tier — $15/month');
bullet('Member automatically becomes an affiliate upon joining.');
bullet('Earns $5 per referred member per month while both accounts are active.');
doc.moveDown(0.5);
h3('Premium Tier — $25/month');
bullet('Member automatically becomes an affiliate upon joining.');
bullet('Earns $10 per referred member per month while both accounts are active.');
doc.moveDown(0.5);
h3('Payout Rules (both tiers)');
bullet('Referring member must hold an active, up-to-date membership.');
bullet('Minimum $50 accumulated balance required before payout is issued.');
bullet('Payouts processed every 2 weeks.');
bullet('The affiliate program is NOT exclusive to Premium — Starter members are full affiliates.');

rule();

h2('Membership Tiers & PMPro Configuration');
kv('Level 1 — Starter:', '$15/month  ·  Full affiliate  ·  Base platform access');
kv('Level 2 — Premium:', '$25/month  ·  Full affiliate  ·  Enhanced platform access');
kv('Level 3 — Unlimited:', 'Internal / no public signup');
kv('Level 4 — Single Session:', '$29 one-time  ·  billing_limit=1');
doc.moveDown(0.5);
body('PMPro replaced MemberPress in Phase 10. All MeprUser, MeprProduct, and MeprRule references were removed from every mu-plugin patch. Membership gates use pmpro_hasMembershipLevel(). Checkout URLs use pmpro_url(\'checkout\', \'?level=N\'). The pmpro_after_checkout hook replaces mepr-transaction-completed.');

// ══════════════════════════════════════════════════════════════════════════════
// PAGE 4 — Phase Delivery Record Part 1
// ══════════════════════════════════════════════════════════════════════════════
doc.addPage();

h2('Phase Delivery Record');

phaseBox(1,  'Hermes Scaffold',              'v1.0',   'complete');
body('Express/TypeScript backend scaffolded on Replit. Health, intake, and job-status endpoints created. In-memory job store. Bearer token auth middleware.');

phaseBox(2,  'HCC Intake & AI Scoring',      'v1.x',   'complete');
body('WordPress AJAX submits member intake form to Hermes. OpenAI GPT-4o scores the Body Score (0–100). Result stored in job store and returned on poll.');

phaseBox(3,  'PostgreSQL + Drizzle',         'v2.x',   'complete');
body('Job store migrated from in-memory to PostgreSQL. Drizzle ORM schema, migrations, and push pipeline established. Replit-managed database.');

phaseBox(4,  'Member Dashboard',             'v2.5.x', 'complete');
body('Body Score banner (server-side Hermes fetch, colored SVG ring). Clinical Pattern Report card. PMPro join date via pmpro_getMemberStartDate(). Account link updated to /membership-account/.');

phaseBox(5,  'Ministry of Healing — v1',     'v2.7.x', 'complete');
body('AI health companion on /ministry/ page. OpenAI streaming chat. Contextualizes Body Score history, environment, and supplement protocol into personalized guidance.');

phaseBox(6,  'Score Delta Badge',            'v2.9.8i','complete');
body('Trend header pill: ▲ +N / ▼ −N / → same. Gap-safe CSS. Visible on HCC result card and dashboard.');

phaseBox(7,  'Ministry Session Persistence', 'v2.9.3a','complete');
body('ministry_chat_history PostgreSQL table (JSONB messages). ministryChatStore.ts — getChatHistory() + appendChatHistory(). GET /api/hermes/ministry/history/:memberId. WP AJAX loads history on page init with "Prior conversations / New session" separators.');

phaseBox(8,  'Re-Baseline Flow',             'v2.9.8j','complete');
body('"Update My Health Baseline" toggle on HCC result card. Collapsible confirmation panel. sessionStorage setter detects ?rebaseline=1 and fires mark endpoint after storeResultV2 resolves. Server appends AI acknowledgment to Ministry history.');

phaseBox(9,  'PMPro Migration',              'v2.9.x', 'complete');
body('Full MemberPress → PMPro migration across patches 291, 293, 294, 296. pmpro_addMembershipLevel() creates levels on init. Login CSS scoped to body.pmpro_login. All deployed to SiteGround via SSH with OPcache + nginx cache flush.');

// ══════════════════════════════════════════════════════════════════════════════
// PAGE 5 — Phase Delivery Record Part 2
// ══════════════════════════════════════════════════════════════════════════════
doc.addPage();

phaseBox(10, 'Provider Report & Ministry Reset', 'v3.0.1', 'complete');
body('[excreet_provider_report] shortcode — printable one-page triage primer. Auto-creates /provider-report/ page. "Share with My Provider →" floating link on HCC page. Body Score fetched from Hermes. Print CSS. Legal disclaimer footer.');
body('Ministry session reset: POST /api/hermes/ministry/history/reset. resetChatHistory() in ministryChatStore.ts. "Start New Session" fixed button (bottom-right) with confirm dialog.');

phaseBox(11, 'PMPro Activation Helper',       'v3.0.2', 'complete');
body('patch-302: WP Admin → Excreet Activation dashboard. Live status checks for PMPro, Stripe, all 4 levels, page options. "Run Activation" AJAX creates levels, wires PMPro page options. Post-activation checklist.');

phaseBox(12, 'Homepage Rebuild',              'v3.0.4', 'complete');
body('excreet-homepage-index.php deployed to WP root index.php — runs before WordPress, bypasses nginx proxy cache. CSS Grid: 3-row layout (nav | headline | CTAs), scroll-free at all viewports. Responsive breakpoints: desktop/mobile/small-phone/large-desktop. nginx PURGE discovery: curl -X PURGE http://localhost/ -H "Host: excreet.com".');

phaseBox(13, 'Legal Styling + Audit',         'v3.0.9', 'complete');
body('Botanical palette on Terms (ID 7), Privacy (ID 3), Refund (ID 177). Monthly healer-bg rotation. patch-309 global catch-all healer-bg on all remaining WP pages. patch-310/311/312 fix /explore/, /welcome-member/, /know-the-signals/ — dead links, Gut→Body rename.');

phaseBox(14, 'Monthly AI Background Rotation','v3.1.x', 'complete');
body('DALL-E 3 scheduler in Hermes — fires 1st of each month at 06:00 UTC. Generates bathroom scene, SCPs to SiteGround as healer-bg-MM.jpg. Manual trigger: POST /api/hermes/admin/rotate-background.');

phaseBox(15, 'WooCommerce Store — Setup',     'v3.1.x', 'complete');
body('WooCommerce installed. patch-303: shop page CSS — dark purple/gold brand, member gate (PMPro-based, admin bypass), shop hero with Anton font + gold/white 3D title. Product card CSS: 2-col grid, object-fit: contain, white bg, border, shadow.');

phaseBox(16, 'WooCommerce Store — Products & Images', 'v3.2.x', 'active');
body('patch-320: 9 real Amazon affiliate products (tag=excreetshop06-20) — Vitamin C, Iodine, Olive Oil, Flax Oil, Coconut Oil, Flaxseed Omega, Enzymedica Digest, ENDUR-ACIN Niacin, Nutricost Niacin. All published with affiliate URLs.');
body('Image pipeline: Amazon CDN blocks 5 of 9 ASINs server-side. Solution — scrape real image URLs from product pages (m.media-amazon.com/images/I/), download to Replit, SCP to SiteGround, attach via media_handle_sideload from filesystem (patch-324). 4 ASINs resolved via images-na CDN, 5 via page-scraped URLs.');
body('patch-327 (current): Plain white shop page (bathroom bg removed on shop only), 4-column borderless product grid, large floating product image, name + price below — matching acceleratedhealthproducts.com reference layout.');

// ══════════════════════════════════════════════════════════════════════════════
// PAGE 6 — Patch Inventory
// ══════════════════════════════════════════════════════════════════════════════
doc.addPage();

h2('mu-plugin Patch Inventory');
body('All patches live in /wp-content/mu-plugins/ on SiteGround. Deployed via Replit SSH/SCP pipeline (deploy-patch-local.ts). OPcache + nginx PURGE flushed after every deploy.');

doc.moveDown(0.4);

const patches = [
  ['271', 'Neutralized (no-op stub)',                  'dead'],
  ['272', 'HCC core intake processor',                 'active'],
  ['280', 'Processing page — storeResultV2',           'active'],
  ['290', 'Intake form — sessionStorage rebaseline',   'active'],
  ['291', 'Member gate (any PMPro level)',              'active'],
  ['292', 'Dead code — MemberPress + page 630 deleted','dead'],
  ['293', 'Premium/unlimited gating + Ministry AJAX',  'active'],
  ['294', 'Single-session ($29) checkout',             'active'],
  ['295', 'Daily check-in sliders (disconnected)',     'inactive'],
  ['296', 'Override gate — hard-block if no PMPro',    'active'],
  ['297', 'Dashboard, legal styling, ministry reset',  'active'],
  ['298', 'HCC result card v2.9.8j — rebaseline flow', 'active'],
  ['299', 'Affiliate area styling',                    'active'],
  ['301', 'Share with My Provider shortcode',          'active'],
  ['302', 'PMPro Activation Helper admin page',        'active'],
  ['303', 'WooCommerce shop CSS + member gate',        'active'],
  ['304', 'Homepage fallback (query-string requests)', 'active'],
  ['309', 'Global healer-bg catch-all',                'active'],
  ['310', '/explore/ Elementor layout fix',            'active'],
  ['311', '/welcome-member/ Body Snapshot + links',    'active'],
  ['312', '/know-the-signals/ PMPro links + Body',     'active'],
  ['320', '9 affiliate products bulk creation',        'active'],
  ['323', 'Remove placeholder products',               'active'],
  ['324', 'Product image attach from filesystem',      'active'],
  ['325', 'Shop tile redesign v1 (superseded)',         'dead'],
  ['326', 'Shop tile redesign v2 (superseded)',         'dead'],
  ['327', 'Shop — plain white + 4-col borderless grid','active'],
];

patches.forEach(([num, desc, status]) => {
  const color = status === 'active' ? GREEN : status === 'dead' ? RED : LIGHTGRAY;
  const label = status === 'active' ? '●' : status === 'dead' ? '✕' : '○';
  doc.fillColor(color).font('Helvetica-Bold').fontSize(9).text(label + ' patch-' + num, { continued: true, width: 90 });
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(9).text('   ' + desc);
});

rule();

h2('Key Files & Directories');
[
  ['artifacts/api-server/src/routes/hermes/',     'All Hermes route handlers'],
  ['artifacts/api-server/src/middlewares/auth.ts','Bearer token auth middleware'],
  ['artifacts/api-server/src/lib/jobStore.ts',   'PostgreSQL job store'],
  ['artifacts/api-server/src/lib/ministryChatStore.ts', 'Ministry history DB ops'],
  ['artifacts/api-server/wordpress/',             'All mu-plugin PHP patches (source)'],
  ['artifacts/api-server/HERMES.md',              'Integration guide + deployment sequence'],
  ['scripts/src/deploy-patch-local.ts',           'SSH/SCP deploy pipeline'],
  ['scripts/src/upload-asset.ts',                 'SCP asset uploader'],
  ['artifacts/hermes-ui/',                        'Hermes admin dashboard (React/Vite)'],
  ['replit.md',                                   'Canonical affiliate rules + phase history'],
].forEach(([file, desc]) => {
  doc.fillColor(PURPLE_DK).font('Courier').fontSize(8.5).text(file, { continued: true, width: 300 });
  doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8.5).text('  — ' + desc);
});

// ══════════════════════════════════════════════════════════════════════════════
// PAGE 7 — Next Phase Candidates + Closing
// ══════════════════════════════════════════════════════════════════════════════
doc.addPage();

h2('Phase 16 — Next Candidates');

const next = [
  ['Complete PMPro Activation',       'Install PMPro on SiteGround → enter Stripe keys → Run Activation → test checkout flow end-to-end.'],
  ['Wire patch-295 sliders',          'Connect daily check-in sliders into the Body Snapshot pipeline, or formally deprecate the patch.'],
  ['Ministry session management UI',  'List past sessions by date, allow member to name and switch between session threads.'],
  ['/affiliate-area/ audit',          'Review patch-299 styling, test payout dashboard, validate referral tracking.'],
  ['/member-dashboard/ weekly digest','Add weekly summary panel — trend over 7 days, Ministry highlights, supplement adherence.'],
  ['WooCommerce checkout styling',    'Apply dark purple/gold brand to cart, checkout, and order confirmation pages.'],
  ['Product reviews integration',     'Surface star ratings and review counts on product tiles (WooCommerce Reviews or embedded Amazon data).'],
];

next.forEach(([title, desc]) => {
  h3(title);
  body(desc);
});

rule();

h2('Deployment Reference');
kv('SSH target:', 'u2198-g6bobebgdwk2@ssh.excreet.com');
kv('WP root:', '/home/customer/www/excreet.com/public_html/');
kv('mu-plugins:', '/home/customer/www/excreet.com/public_html/wp-content/mu-plugins/');
kv('Uploads:', '/home/customer/www/excreet.com/public_html/wp-content/uploads/');
kv('nginx PURGE:', 'curl -X PURGE http://localhost/ -H "Host: excreet.com"  (via SSH)');
kv('Deploy cmd:', 'pnpm --filter @workspace/scripts run deploy:patch:local -- <file>');
kv('Asset upload:', 'pnpm --filter @workspace/scripts run upload:asset -- <abs> <remote_rel>');

doc.moveDown(2);
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(11).text('EXCREET', { align: 'center', characterSpacing: 6 });
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(9).moveDown(0.3).text('CLEANS COMPLETE', { align: 'center', characterSpacing: 3 });
doc.moveDown(0.4);
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8).text('Technical Report v3  ·  Confidential', { align: 'center' });

// ══════════════════════════════════════════════════════════════════════════════
// FOOTERS
// ══════════════════════════════════════════════════════════════════════════════
const range = doc.bufferedPageRange();
for (let i = range.start; i < range.start + range.count; i++) {
  doc.switchToPage(i);
  if (i === range.start) continue; // skip cover
  const pageNum = i - range.start;
  const totalPages = range.count - 1;
  const bottom = doc.page.height - 40;
  doc.fillColor('#cccccc').rect(72, bottom - 6, PW, 0.5).fill();
  doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8)
    .text(
      `Excreet — Technical Report v3   ·   Confidential   |   Page ${pageNum} of ${totalPages}`,
      72, bottom,
      { align: 'center', width: PW }
    );
}

doc.end();
stream.on('finish', () => console.log('✅ PDF written to:', outputPath));
stream.on('error', (e) => { console.error('❌ Error:', e); process.exit(1); });
