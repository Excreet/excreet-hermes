import PDFDocument from 'pdfkit';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outputPath = path.resolve(__dirname, '../../artifacts/hermes-ui/public/excreet-technical-report-v4.pdf');

const doc = new PDFDocument({
  size: 'LETTER',
  margins: { top: 48, bottom: 48, left: 50, right: 50 },
  bufferPages: true,
  info: {
    Title:   'Excreet — Technical Report v4',
    Author:  'Excreet',
    Subject: 'Platform Architecture, Feature Delivery & Deployment Record',
  },
});

const stream = fs.createWriteStream(outputPath);
doc.pipe(stream);

const PURPLE    = '#6B21A8';
const PURPLE_DK = '#2a0a4a';
const GOLD      = '#B8860B';
const GOLD_LT   = '#C9A84C';
const DARKGRAY  = '#1a1a2e';
const MIDGRAY   = '#444444';
const LIGHTGRAY = '#777777';
const WHITE     = '#ffffff';
const GREEN     = '#166534';
const RED_DK    = '#7f1d1d';
const ML        = 50;
const PW        = doc.page.width - ML * 2;   // 512

// ── Helpers — purely sequential, no absolute coords, no continued ────────────
const h1 = (t) =>
  doc.fillColor(DARKGRAY).font('Helvetica-Bold').fontSize(19)
    .text(t, { width: PW }).moveDown(0.2);

const h2 = (t) => {
  doc.moveDown(0.5);
  doc.fillColor(PURPLE).font('Helvetica-Bold').fontSize(11)
    .text(t.toUpperCase(), { characterSpacing: 1.1, width: PW });
  doc.fillColor(GOLD_LT).rect(ML, doc.y + 2, 30, 1.5).fill();
  doc.moveDown(0.4);
};

const h3 = (t) =>
  doc.moveDown(0.15)
    .fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(9.5)
    .text(t, { width: PW }).moveDown(0.1);

const body = (t) =>
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(8.5).lineGap(1.6)
    .text(t, { align: 'justify', width: PW }).moveDown(0.15);

const bullet = (t) =>
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(8.5).lineGap(1.4)
    .text('\u2022  ' + t, { indent: 6, width: PW - 6 });

const code = (t, color) =>
  doc.fillColor(color || MIDGRAY).font('Courier').fontSize(7.8)
    .text('  ' + t, { width: PW }).moveDown(0.1);

const rule = () => {
  doc.moveDown(0.3);
  doc.fillColor('#dddddd').rect(ML, doc.y, PW, 0.5).fill();
  doc.moveDown(0.35);
};

// Key-value: one call per line, key bold left-aligned via spaces
const kv = (k, v) =>
  doc.fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(8.5)
    .text(k, { width: 115, continued: true })
    .fillColor(MIDGRAY).font('Helvetica').text(v, { width: PW - 115 })
    .moveDown(0.04);

const phaseBox = (num, title, version, status = 'complete') => {
  const barColor = status === 'complete' ? '#166534' : status === 'active' ? '#B8860B' : '#777777';
  const badge    = status === 'complete' ? 'COMPLETE' : status === 'active' ? 'IN PROGRESS' : 'PLANNED';
  doc.moveDown(0.28);
  const y0 = doc.y;
  doc.fillColor(barColor).rect(ML, y0, 3, 24).fill();
  // title + badge on same implicit line — avoid continued, use separate positioned calls
  doc.fillColor(DARKGRAY).font('Helvetica-Bold').fontSize(9.5)
    .text(`Phase ${num} \u2014 ${title}`, { indent: 8, width: PW - 8 });
  doc.fillColor(barColor).font('Helvetica-Bold').fontSize(7.5)
    .text(`${version}   \u00b7   ${badge}`, { indent: 8, width: PW - 8 }).moveDown(0.18);
};

// ── COVER ────────────────────────────────────────────────────────────────────
doc.rect(0, 0, doc.page.width, doc.page.height).fill(DARKGRAY);
doc.y = doc.page.height * 0.27;
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(52)
  .text('EXCREET', { align: 'center', characterSpacing: 10, width: PW });
doc.fillColor(WHITE).font('Helvetica').fontSize(11).moveDown(0.2)
  .text('CLEANS COMPLETE', { align: 'center', characterSpacing: 5, width: PW });
doc.moveDown(0.7);
doc.fillColor(GOLD_LT).rect(ML + PW * 0.2, doc.y, PW * 0.6, 0.75).fill();
doc.moveDown(0.5);
doc.fillColor('#cccccc').font('Helvetica-Oblique').fontSize(14)
  .text('Technical Report  v4', { align: 'center', width: PW });
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(9.5).moveDown(0.25)
  .text('Platform Architecture \u00b7 Feature Delivery \u00b7 Deployment Record', { align: 'center', width: PW });
doc.y = doc.page.height - 72;
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8.5)
  .text('Confidential \u2014 ' + new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
    { align: 'center', width: PW });

// ── CONTENT (all flows, no forced addPage after this one) ────────────────────
doc.addPage();

h1('Executive Summary');
doc.fillColor(GOLD).font('Helvetica-Oblique').fontSize(9.5)
  .text('Pre-Clinical Cellular Health Intelligence \u2014 v4 Platform State', { width: PW })
  .moveDown(0.35);
body('Excreet is a members-only pre-clinical health intelligence platform at excreet.com. A WordPress/WooCommerce front-end on SiteGround is paired with a custom Express/TypeScript backend (Hermes) on Replit. The platform delivers AI-driven Body Score assessments, a Ministry of Healing AI companion, a Partner Product Store, and a clinician-ready provider report \u2014 all gated behind a PMPro membership tier system.');
body('v4 covers Phases 1\u201316 in full, the WooCommerce store completion (image pipeline, tile redesign, shop page overhaul), homepage tagline, and a critical OPcache deploy-pipeline fix that was silently preventing all server-side PHP changes from taking effect.');
rule();

h2('Production Stack');
kv('Frontend:',    'WordPress 6.x on SiteGround (PHP 8.2.31, nginx)');
kv('Membership:',  'Paid Memberships Pro (PMPro) \u2014 Starter $15/mo \u00b7 Premium $25/mo');
kv('Commerce:',    'WooCommerce \u2014 affiliate product store (tag: excreetshop06-20)');
kv('Backend:',     'Hermes API \u2014 Express 5 / TypeScript / Node 24 on Replit');
kv('Database:',    'PostgreSQL (Replit-managed) + Drizzle ORM');
kv('Validation:',  'Zod v4 + drizzle-zod');
kv('AI:',          'OpenAI GPT-4o \u2014 HCC scoring, Ministry of Healing, background generation');
kv('Auth:',        'Bearer token (HERMES_API_KEY) \u2014 WordPress \u2194 Hermes');
kv('Deploy:',      'Replit SSH/SCP \u2192 SiteGround mu-plugins + nginx PURGE');
kv('Monorepo:',    'pnpm workspaces \u2014 TypeScript 5.9, esbuild CJS bundle');
rule();

h2('Hermes API Endpoints');
const epRows = [
  ['GET',  '/api/hermes/health',                     'Public uptime check'],
  ['POST', '/api/hermes/intake',                     'Accept WP form \u2192 create HCC job'],
  ['GET',  '/api/hermes/job-status/:jobId',          'WordPress polls for AI result'],
  ['GET',  '/api/hermes/ministry/history/:memberId', 'Load Ministry chat history'],
  ['POST', '/api/hermes/ministry/history/mark',      'Append rebaseline system note'],
  ['POST', '/api/hermes/ministry/history/reset',     'Clear session (Start New Session)'],
  ['POST', '/api/hermes/admin/rotate-background',    'Manual DALL-E bg image trigger'],
];
epRows.forEach(([method, epPath, desc]) => {
  const mc = method === 'GET' ? '#1d4ed8' : '#7c2d12';
  doc.fillColor(mc).font('Helvetica-Bold').fontSize(8).text(method + '  ', { continued: true, width: 36 });
  doc.fillColor(PURPLE_DK).font('Courier').fontSize(8).text(epPath + '  ', { continued: true });
  doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8).text('\u2014 ' + desc);
});
rule();

h2('Affiliate Program Rules \u2014 Canonical');
body('These rules are the authoritative source of truth. No UI, copy, or onboarding flow may contradict them without owner confirmation.');
h3('Starter ($15/mo) \u2014 Member auto-enrolls as affiliate. Earns $5/mo per active referred member.');
h3('Premium ($25/mo) \u2014 Member auto-enrolls as affiliate. Earns $10/mo per active referred member.');
h3('Payout Rules (both tiers)');
bullet('Referring member must hold an active, up-to-date membership.');
bullet('Minimum $50 accumulated balance required before payout.');
bullet('Payouts processed every 2 weeks.');
bullet('Affiliate program is NOT exclusive to Premium \u2014 Starter members are full affiliates.');
rule();

h2('Membership Tiers \u2014 PMPro Configuration');
kv('Level 1 \u2014 Starter:',        '$15/month  \u00b7  Full affiliate  \u00b7  Base platform access');
kv('Level 2 \u2014 Premium:',        '$25/month  \u00b7  Full affiliate  \u00b7  Enhanced platform access');
kv('Level 3 \u2014 Unlimited:',      'Internal / no public signup');
kv('Level 4 \u2014 Single Session:', '$29 one-time  \u00b7  billing_limit=1');
doc.moveDown(0.12);
body('PMPro replaced MemberPress in Phase 10. All MeprUser / MeprProduct / MeprRule refs removed from every mu-plugin patch. Gates use pmpro_hasMembershipLevel(). Checkout URLs use pmpro_url(). Hook pmpro_after_checkout replaces mepr-transaction-completed.');
rule();

h2('Phase Delivery Record');

phaseBox(1, 'Hermes Scaffold', 'v1.0');
body('Express/TypeScript backend on Replit. Health, intake, job-status endpoints. In-memory job store. Bearer token auth.');

phaseBox(2, 'HCC Intake & AI Scoring', 'v1.x');
body('WP AJAX submits member intake to Hermes. GPT-4o scores Body Score (0\u2013100). Result stored and returned on poll.');

phaseBox(3, 'PostgreSQL + Drizzle', 'v2.x');
body('Job store migrated to PostgreSQL. Drizzle ORM schema, migrations, push pipeline.');

phaseBox(4, 'Member Dashboard', 'v2.5.x');
body('Body Score banner (SVG ring, server-side Hermes fetch). Clinical Pattern Report card. PMPro join date. /membership-account/ link.');

phaseBox(5, 'Ministry of Healing v1', 'v2.7.x');
body('AI health companion. OpenAI streaming. Contextualises Body Score, environment, supplement protocol.');

phaseBox(6, 'Score Delta Badge', 'v2.9.8i');
body('Trend pill: \u25b2 +N / \u25bc \u2212N / \u2192 same. Gap-safe CSS on HCC result card and dashboard.');

phaseBox(7, 'Ministry Session Persistence', 'v2.9.3a');
body('ministry_chat_history table (JSONB). getChatHistory() + appendChatHistory(). "Prior conversations / New session" separators on page load.');

phaseBox(8, 'Re-Baseline Flow', 'v2.9.8j');
body('"Update My Health Baseline" toggle on HCC. Collapsible confirm panel. sessionStorage setter fires mark endpoint after storeResultV2 resolves.');

phaseBox(9, 'PMPro Migration', 'v2.9.x');
body('Full MemberPress \u2192 PMPro across patches 291\u2013296. pmpro_addMembershipLevel() creates levels on init. Login CSS scoped to body.pmpro_login.');

phaseBox(10, 'Provider Report & Ministry Reset', 'v3.0.1');
body('[excreet_provider_report] shortcode \u2014 printable triage primer. "Share with My Provider" link. Print CSS. "Start New Session" + confirm dialog. POST /api/hermes/ministry/history/reset.');

phaseBox(11, 'PMPro Activation Helper', 'v3.0.2');
body('patch-302: WP Admin \u2192 Excreet Activation. Live status checks for PMPro, Stripe, all 4 levels. "Run Activation" AJAX wires all levels + PMPro pages.');

phaseBox(12, 'Homepage Rebuild', 'v3.0.4');
body('excreet-homepage-index.php at WP root \u2014 runs before WordPress, bypasses nginx cache. CSS Grid 3-row layout. nginx PURGE: curl -X PURGE http://localhost/ -H "Host: excreet.com".');

phaseBox(13, 'Legal Styling + Page Audit', 'v3.0.9');
body('Botanical palette on Terms/Privacy/Refund. patch-309 global healer-bg catch-all. patch-310/311/312 fix /explore/, /welcome-member/, /know-the-signals/ \u2014 dead links, Gut\u2192Body rename.');

phaseBox(14, 'Monthly AI Background Rotation', 'v3.1.x');
body('DALL-E 3 scheduler fires 1st of month 06:00 UTC. Generates bathroom scene, SCPs to SiteGround as healer-bg-MM.jpg. Manual trigger: POST /api/hermes/admin/rotate-background.');

phaseBox(15, 'WooCommerce Store Setup', 'v3.1.x');
body('WooCommerce installed. patch-303: shop CSS, PMPro member gate + admin bypass, shop hero with Anton font + gold 3D title. Initial 2-col product card grid.');

phaseBox(16, 'WooCommerce Store \u2014 Products, Images & UI', 'v3.2.x \u2013 v3.3.x');
body('9 Amazon affiliate products (patch-320). Image pipeline: Amazon CDN blocks 5/9 ASINs server-side. Solution \u2014 scrape real image URLs from Amazon pages, download on Replit, SCP to SiteGround, attach via media_handle_sideload (patch-324). 4-col borderless product grid (patch-327). Shop labels removed via CSS + PHP hooks (patch-329).');
body('OPcache bug: SiteGround PHP 8.2.30\u21928.2.31 moved OPcache dir. Stale deploy-script path caused old bytecode to be served. Fixed by locating new path via SSH, clearing stale .bin files, updating OPCACHE_BASE in deploy-patch-local.ts.');
rule();

h2('Homepage Tagline (v4 Addition)');
doc.fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(13)
  .text('\u201cA Pre-Clinical Warning System.\u201d', { align: 'center', width: PW });
doc.moveDown(0.25);
body('"Pre-Clinical" places Excreet in a medically credible space \u2014 it signals the platform catches biochemical signals before physicians have a diagnosis. "Warning System" adds urgency without alarmism. CSS .ex-tagline: positioned absolute below logo, uppercase spaced tracking, white text with dark shadow, flanked by thin gold rules. Font: clamp(11px, 1vw, 15px) desktop.');
rule();

h2('Critical Infrastructure Fix \u2014 OPcache Deploy Pipeline');
body('Root cause: SiteGround silently updated PHP 8.2.30\u21928.2.31, moving the OPcache binary directory. deploy-patch-local.ts had the old path hardcoded \u2014 every deploy uploaded new PHP correctly via SCP but deleted the stale .bin at the wrong path (no-op). WordPress served old compiled bytecode regardless of what was deployed. Symptom: visual changes had no effect despite successful deploy confirmations. {"reset":false,"invalidated":false} was misread as "not cached" but was actually "wrong path".');
code('/home/customer/.opcache/8.2.30-Dec 18 2025-16:29:25-23d3f3e759bf1884a90d8c8be6a27edd/  (old \u2014 stale)', RED_DK);
code('/home/customer/.opcache/8.2.31-May 11 2026-05:48:02-86160aeceb74082fc91a5acc3d4b20ec/  (new \u2014 correct)', GREEN);
doc.moveDown(0.1);
bullet('SSH into SiteGround \u2014 located correct path via:  find /home/customer/.opcache');
bullet('Deleted all stale .bin files for mu-plugins directory manually.');
bullet('Updated OPCACHE_BASE in deploy-patch-local.ts to the 8.2.31 path.');
bullet('Check and update this path after every SiteGround PHP minor version update.');
rule();

h2('Patch Inventory \u2014 Current State');
const patches = [
  ['271', 'Neutralized \u2014 no-op stub',                   'dead'],
  ['272', 'HCC core intake processor',                       'active'],
  ['280', 'Processing page \u2014 storeResultV2',            'active'],
  ['290', 'Intake form \u2014 sessionStorage rebaseline',    'active'],
  ['291', 'Member gate (any PMPro level)',                    'active'],
  ['292', 'Dead code \u2014 MemberPress + page 630 deleted', 'dead'],
  ['293', 'Premium/unlimited gating + Ministry AJAX',        'active'],
  ['294', 'Single-session ($29) checkout',                   'active'],
  ['295', 'Daily check-in sliders (disconnected)',           'inactive'],
  ['296', 'Override gate \u2014 hard-block if no PMPro',     'active'],
  ['297', 'Dashboard, legal styling, ministry reset',        'active'],
  ['298', 'HCC result card v2.9.8j \u2014 rebaseline',       'active'],
  ['299', 'Affiliate area styling',                          'active'],
  ['301', 'Share with My Provider shortcode',                'active'],
  ['302', 'PMPro Activation Helper admin page',              'active'],
  ['303', 'WooCommerce shop CSS + member gate + hero',       'active'],
  ['304', 'Homepage fallback (query-string)',                 'active'],
  ['309', 'Global healer-bg catch-all',                      'active'],
  ['310', '/explore/ Elementor layout fix',                  'active'],
  ['311', '/welcome-member/ Body Snapshot + links',          'active'],
  ['312', '/know-the-signals/ PMPro links + Body',           'active'],
  ['320', '9 affiliate products bulk creation',              'active'],
  ['323', 'Remove placeholder products',                     'active'],
  ['324', 'Product image attach from filesystem',            'active'],
  ['325', 'Shop tile redesign v1 (superseded)',               'dead'],
  ['326', 'Shop tile redesign v2 (superseded)',               'dead'],
  ['327', 'Shop \u2014 plain white + 4-col borderless grid', 'active'],
  ['328', 'NEUTRALIZED \u2014 no-op',                        'dead'],
  ['329', 'Hide shop labels (PHP hooks + CSS)',               'active'],
  ['330', 'NEUTRALIZED \u2014 merged into patch-303',        'dead'],
  ['331', 'Global brand stylesheet (all WP pages)',          'active'],
];
patches.forEach(([num, desc, status]) => {
  const color = status === 'active' ? GREEN : status === 'dead' ? RED_DK : LIGHTGRAY;
  const icon  = status === 'active' ? '\u25cf' : status === 'dead' ? '\u2715' : '\u25cb';
  doc.fillColor(color).font('Helvetica-Bold').fontSize(8)
    .text(icon + '  patch-' + num + '  ', { continued: true })
    .fillColor(MIDGRAY).font('Helvetica').fontSize(8)
    .text(desc).moveDown(0.04);
});
rule();

h2('Deployment Reference');
kv('SSH target:',   'u2198-g6bobebgdwk2@ssh.excreet.com');
kv('WP root:',      '/home/customer/www/excreet.com/public_html/');
kv('mu-plugins:',   '/home/customer/www/excreet.com/public_html/wp-content/mu-plugins/');
kv('Uploads:',      '/home/customer/www/excreet.com/public_html/wp-content/uploads/');
kv('OPcache path:', '/home/customer/.opcache/8.2.31-May 11 2026-05:48:02-86160aeceb74082fc91a5acc3d4b20ec/');
kv('nginx PURGE:',  'curl -X PURGE http://localhost/ -H "Host: excreet.com"  (via SSH)');
kv('Deploy cmd:',   'pnpm --filter @workspace/scripts run deploy:patch:local -- <file>');
kv('Asset upload:', 'pnpm --filter @workspace/scripts run upload:asset -- <abs> <remote_rel>');
kv('Report gen:',   'node scripts/src/generate-technical-report-v4.mjs');
rule();

h2('Next Phase Candidates');
const next = [
  ['Complete PMPro Activation',      'Install PMPro on SiteGround \u2192 enter Stripe keys \u2192 Run Activation \u2192 test checkout.'],
  ['Wire patch-295 sliders',         'Connect daily check-in sliders into Body Snapshot pipeline, or formally deprecate.'],
  ['Ministry session management UI', 'List past sessions by date, allow member to name and switch between threads.'],
  ['/affiliate-area/ audit',         'Review patch-299 styling, test payout dashboard, validate referral tracking.'],
  ['Dashboard weekly digest',        'Add 7-day trend panel, Ministry highlights, supplement adherence.'],
  ['WooCommerce checkout styling',   'Apply dark purple/gold brand to cart, checkout, and order confirmation pages.'],
  ['Product reviews',                'Surface star ratings and review counts on product tiles.'],
  ['Shop banner',                    'User-specified banner beneath the hero logo on the shop page.'],
];
next.forEach(([title, desc]) => {
  doc.fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(8.5)
    .text('\u203a ' + title + '  \u2014  ', { continued: true })
    .fillColor(MIDGRAY).font('Helvetica').fontSize(8.5)
    .text(desc).moveDown(0.12);
});

doc.moveDown(0.9);
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(11)
  .text('EXCREET', { align: 'center', characterSpacing: 6, width: PW });
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8).moveDown(0.2)
  .text('CLEANS COMPLETE  \u00b7  Technical Report v4  \u00b7  Confidential',
    { align: 'center', width: PW });

// ── FOOTERS ───────────────────────────────────────────────────────────────────
const range = doc.bufferedPageRange();
for (let i = range.start; i < range.start + range.count; i++) {
  doc.switchToPage(i);
  if (i === range.start) continue;
  const pageNum = i - range.start;
  const total   = range.count - 1;
  const bot = doc.page.height - 26;
  doc.fillColor('#dddddd').rect(ML, bot - 5, PW, 0.5).fill();
  doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(7)
    .text(`Excreet \u2014 Technical Report v4  \u00b7  Confidential  |  Page ${pageNum} of ${total}`,
      ML, bot, { align: 'center', width: PW });
}

doc.end();
stream.on('finish', () => console.log('\u2705 PDF written to:', outputPath));
stream.on('error',  (e) => { console.error('\u274c', e); process.exit(1); });
