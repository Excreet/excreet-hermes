import PDFDocument from 'pdfkit';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outputPath = path.resolve(__dirname, '../../artifacts/hermes-ui/public/excreet-technical-report-v5.pdf');

const doc = new PDFDocument({
  size: 'LETTER',
  margins: { top: 44, bottom: 36, left: 50, right: 50 },
  bufferPages: true,
  info: {
    Title:   'Excreet — Technical Report v5',
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
const LIGHTGRAY = '#888888';
const WHITE     = '#ffffff';
const GREEN     = '#166534';
const RED_DK    = '#7f1d1d';
const INACTIVE  = '#92400e';
const ML        = 50;
const PW        = doc.page.width - ML * 2;

// ── Helpers ──────────────────────────────────────────────────────────────────
const h1 = (t) =>
  doc.fillColor(DARKGRAY).font('Helvetica-Bold').fontSize(17)
    .text(t, { width: PW }).moveDown(0.15);

const h2 = (t) => {
  doc.moveDown(0.35);
  doc.fillColor(PURPLE).font('Helvetica-Bold').fontSize(10.5)
    .text(t.toUpperCase(), { characterSpacing: 1, width: PW });
  doc.fillColor(GOLD_LT).rect(ML, doc.y + 1, 28, 1.2).fill();
  doc.moveDown(0.3);
};

const h3 = (t) =>
  doc.moveDown(0.1)
    .fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(9)
    .text(t, { width: PW }).moveDown(0.05);

const body = (t) =>
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(8.5).lineGap(1.2)
    .text(t, { align: 'justify', width: PW }).moveDown(0.08);

const bullet = (t) =>
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(8.5).lineGap(1.1)
    .text('\u2022  ' + t, { indent: 6, width: PW - 6 }).moveDown(0.02);

const code = (t, color) =>
  doc.fillColor(color || MIDGRAY).font('Courier').fontSize(7.5)
    .text('  ' + t, { width: PW }).moveDown(0.06);

const rule = () => {
  doc.moveDown(0.2);
  doc.fillColor('#dddddd').rect(ML, doc.y, PW, 0.5).fill();
  doc.moveDown(0.22);
};

const kv = (k, v) =>
  doc.fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(8.5)
    .text(k, { width: 115, continued: true })
    .fillColor(MIDGRAY).font('Helvetica').text(v, { width: PW - 115 })
    .moveDown(0.02);

const phaseBox = (num, title, version, status = 'complete') => {
  const barColor = status === 'complete' ? GREEN : status === 'active' ? GOLD : LIGHTGRAY;
  const badge    = status === 'complete' ? 'COMPLETE' : status === 'active' ? 'IN PROGRESS' : 'PLANNED';
  doc.moveDown(0.18);
  const y0 = doc.y;
  doc.fillColor(barColor).rect(ML, y0, 3, 20).fill();
  doc.fillColor(DARKGRAY).font('Helvetica-Bold').fontSize(9)
    .text(`Phase ${num} \u2014 ${title}`, { indent: 8, width: PW - 8 });
  doc.fillColor(barColor).font('Helvetica-Bold').fontSize(7)
    .text(`${version}   \u00b7   ${badge}`, { indent: 8, width: PW - 8 }).moveDown(0.1);
};

// ── COVER ────────────────────────────────────────────────────────────────────
doc.rect(0, 0, doc.page.width, doc.page.height).fill(DARKGRAY);
doc.y = doc.page.height * 0.28;
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(52)
  .text('EXCREET', { align: 'center', characterSpacing: 10, width: PW });
doc.fillColor(WHITE).font('Helvetica').fontSize(11).moveDown(0.15)
  .text('A PRE-CLINICAL WARNING SYSTEM.', { align: 'center', characterSpacing: 3, width: PW });
doc.moveDown(0.6);
doc.fillColor(GOLD_LT).rect(ML + PW * 0.2, doc.y, PW * 0.6, 0.75).fill();
doc.moveDown(0.45);
doc.fillColor('#cccccc').font('Helvetica-Oblique').fontSize(14)
  .text('Technical Report  v5', { align: 'center', width: PW });
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(9.5).moveDown(0.2)
  .text('Platform Architecture \u00b7 Feature Delivery \u00b7 Deployment Record', { align: 'center', width: PW });
doc.y = doc.page.height - 68;
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8.5)
  .text('Confidential \u2014 ' + new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
    { align: 'center', width: PW });

// ── PAGE 2: Executive Summary + Stack + Endpoints + Affiliates ───────────────
doc.addPage();

h1('Executive Summary');
doc.fillColor(GOLD).font('Helvetica-Oblique').fontSize(9)
  .text('Pre-Clinical Cellular Health Intelligence \u2014 v5 Platform State', { width: PW })
  .moveDown(0.25);
body('Excreet is a members-only pre-clinical health intelligence platform at excreet.com. A WordPress front-end on SiteGround is paired with a custom Express/TypeScript backend (Hermes) on Replit. The platform delivers AI-driven Body Score assessments, a Ministry of Healing AI companion, a Partner Product Store, a clinician-ready provider report, and a printable Doctor Visit Summary \u2014 all gated behind a PMPro membership tier system.');
body('v5 covers Phases 1\u201318 in full: the WooCommerce store, site-wide brand consistency pass (tagline "A Pre-Clinical Warning System.", readability, elderly-accessibility), checkout pricing correction, Explore page rebuild, Doctor Visit Summary with flagged-result clinical output, plain-language membership clarity tables across three key pages, and all patch inventory updates through v3.5.2.');
rule();

h2('Production Stack');
kv('Frontend:',    'WordPress 6.x on SiteGround (PHP 8.2.31, nginx)');
kv('Membership:',  'Paid Memberships Pro (PMPro) \u2014 Starter $15/mo \u00b7 Premium $25/mo');
kv('Commerce:',    'WooCommerce \u2014 affiliate product store (tag: excreetshop06-20)');
kv('Backend:',     'Hermes API \u2014 Express 5 / TypeScript / Node 24 on Replit');
kv('Database:',    'PostgreSQL (Replit-managed) + Drizzle ORM');
kv('AI:',          'OpenAI GPT-4o \u2014 HCC scoring, Ministry of Healing, background generation');
kv('Auth:',        'Bearer token (HERMES_API_KEY) \u2014 WordPress \u2194 Hermes');
kv('Deploy:',      'Replit SSH/SCP \u2192 SiteGround mu-plugins + nginx PURGE');
kv('Monorepo:',    'pnpm workspaces \u2014 TypeScript 5.9, esbuild CJS bundle');
rule();

h2('Hermes API Endpoints');
const epRows = [
  ['GET',  '/api/hermes/health',                          'Public uptime check'],
  ['POST', '/api/hermes/intake',                          'Accept WP form \u2192 create HCC job'],
  ['GET',  '/api/hermes/job-status/:jobId',               'WordPress polls for AI result'],
  ['GET',  '/api/hermes/ministry/history/:memberId',      'Load Ministry chat history'],
  ['POST', '/api/hermes/ministry/history/mark',           'Append rebaseline system note'],
  ['POST', '/api/hermes/ministry/history/reset',          'Clear session (Start New Session)'],
  ['POST', '/api/hermes/admin/rotate-background',         'Manual DALL-E bg image trigger'],
  ['GET',  '/api/hermes/report/clinical-summary/:memberId', 'Doctor Visit Summary \u2014 flagged tier only (Phase 18)'],
];
epRows.forEach(([method, epPath, desc]) => {
  const mc = method === 'GET' ? '#1d4ed8' : '#7c2d12';
  doc.fillColor(mc).font('Helvetica-Bold').fontSize(8).text(method + '  ', { continued: true, width: 36 });
  doc.fillColor(PURPLE_DK).font('Courier').fontSize(8).text(epPath + '  ', { continued: true });
  doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8).text('\u2014 ' + desc);
});
rule();

h2('Affiliate Program Rules \u2014 Canonical');
body('Authoritative source of truth. No UI, copy, or onboarding flow may contradict these rules without owner confirmation.');
h3('Starter ($15/mo) \u2014 Auto-enrolled affiliate.');
bullet('Earns $5/mo for each active referred Starter ($15/mo) member.');
bullet('Earns $5/mo for each active referred Premium ($25/mo) member.');
h3('Premium ($25/mo) \u2014 Auto-enrolled affiliate.');
bullet('Earns $5/mo for each active referred Starter ($15/mo) member.');
bullet('Earns $10/mo for each active referred Premium ($25/mo) member.');
h3('Payout Rules (both tiers)');
bullet('Referring member must hold an active, up-to-date membership.');
bullet('Minimum $50 accumulated balance before payout.');
bullet('Payouts every 2 weeks.');
bullet('Affiliate program is NOT exclusive to Premium \u2014 Starter members are full affiliates.');
bullet('No proceeds from Excreet Store sales or ancillary services count toward affiliate earnings.');
bullet('SMS notifications: US-registered phone numbers only. Excreet bottle: US shipping only.');
rule();

h2('Membership Tiers \u2014 PMPro Configuration');
kv('Level 1 \u2014 Starter:',        '$15/month  \u00b7  Full affiliate  \u00b7  Base platform access');
kv('Level 2 \u2014 Premium:',        '$25/month  \u00b7  Full affiliate  \u00b7  Enhanced platform access');
kv('Level 3 \u2014 Unlimited:',      'Internal / no public signup');
kv('Level 4 \u2014 Single Session:', '$29 one-time  \u00b7  billing_limit=1');
doc.moveDown(0.08);
body('PMPro replaced MemberPress (Phase 9). All MeprUser/MeprProduct/MeprRule refs removed. Gates use pmpro_hasMembershipLevel(). Checkout URLs use pmpro_url(). pmpro_after_checkout replaces mepr-transaction-completed. patch-337 enforces $15.00 on every init \u2014 patch-334 ($0.01 test stub) permanently neutralised.');
rule();

// ── Phase Delivery Record ────────────────────────────────────────────────────
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
body('ministry_chat_history JSONB table. getChatHistory() + appendChatHistory(). "Prior conversations / New session" separators on page load.');

phaseBox(8, 'Re-Baseline Flow', 'v2.9.8j');
body('"Update My Health Baseline" toggle on HCC. Collapsible confirm panel. sessionStorage setter fires mark endpoint after storeResultV2 resolves.');

phaseBox(9, 'PMPro Migration', 'v2.9.x');
body('Full MemberPress \u2192 PMPro across patches 291\u2013296. pmpro_addMembershipLevel() creates levels on init. Login CSS scoped to body.pmpro_login.');

phaseBox(10, 'Provider Report & Ministry Reset', 'v3.0.1');
body('[excreet_provider_report] shortcode \u2014 printable triage primer. "Share with My Provider" link + print CSS. "Start New Session" confirm dialog. POST /api/hermes/ministry/history/reset.');

phaseBox(11, 'PMPro Activation Helper', 'v3.0.2');
body('patch-302: WP Admin \u2192 Excreet Activation. Live status checks for PMPro, Stripe, all 4 levels. "Run Activation" AJAX wires all levels + PMPro pages.');

phaseBox(12, 'Homepage Rebuild', 'v3.0.4');
body('excreet-homepage-index.php at WP root \u2014 runs before WordPress, bypasses nginx cache. CSS Grid 3-row layout. nginx PURGE: curl -X PURGE http://localhost/ -H "Host: excreet.com".');

phaseBox(13, 'Legal Styling + Page Audit', 'v3.0.9');
body('Botanical palette on Terms/Privacy/Refund. patch-309 global healer-bg catch-all. patch-310/311/312: /explore/, /welcome-member/, /know-the-signals/ \u2014 dead links fixed, Gut\u2192Body rename.');

phaseBox(14, 'Monthly AI Background Rotation', 'v3.1.x');
body('DALL-E 3 scheduler fires 1st of month 06:00 UTC. Generates bathroom scene, SCPs to SiteGround as healer-bg-MM.jpg. Manual trigger: POST /api/hermes/admin/rotate-background.');

phaseBox(15, 'WooCommerce Store Setup', 'v3.1.x \u2013 v3.3.x');
body('WooCommerce installed. 9 Amazon affiliate products (patch-320). Image pipeline: Amazon CDN blocks server-side; solution \u2014 scrape real image URLs, download on Replit, SCP + media_handle_sideload (patch-324). 4-col borderless grid (patch-327). OPcache deploy bug discovered and fixed (PHP 8.2.30\u21928.2.31 path migration).');

phaseBox(16, 'Member Onboarding & Admin Hardening', 'v3.3.x');
body('patch-332: post-checkout redirect to guided first-experience. patch-333: admin access guard. patch-335: referral code hardening + retroactive assignment tool. patch-338: four branded transactional emails.');

phaseBox(17, 'Site-Wide Brand & Readability Pass', 'v3.3.x \u2013 v3.4.x');
body('Tagline standardised to "A Pre-Clinical Warning System." across homepage, Explore page, intake form header, and page footers. Explore page rebuilt: vision copy, video caption (gold pill), tier cards, affiliate callout. Readability lifted to elderly-accessible standards. Checkout pricing corrected: patch-334 neutralised; patch-337 idempotent $15.00 enforcer. Shop page redesigned (patch-345). Signature Formula direct checkout (patch-346).');

phaseBox(18, 'Doctor Visit Summary + Membership Clarity', 'v3.5.1 \u2013 v3.5.2');
body('Two platform-wide improvements: (1) Flagged result clinical output \u2014 when a member\'s Body Check returns tier "protocol" or "alarm" (Vitality Score \u226450), a "Prepare Doctor Visit Summary" button appears on the HCC page. AJAX fetches GET /api/hermes/report/clinical-summary/:memberId, which returns structured data from the most recent completed health_intake job. A formatted printable modal renders: Vitality Score badge, pattern reading, questions to bring to provider, lab tests to request by name, red flags requiring urgent care, Ministry of Healing redirect, and Print/Save as PDF. Server-side tier gate prevents misuse by healthy-score members. (2) Plain-language membership clarity \u2014 injected across three pages: /membership-options/ (full side-by-side comparison table with sessions, affiliate earnings, US-only restrictions, no store proceeds, and cancellation steps); /affiliate-area/ (earnings matrix with exact dollar amounts per referral tier, all payout rules); /membership-account/ (step-by-step cancellation guide with support email).');
rule();

// ── OPcache Fix ──────────────────────────────────────────────────────────────
h2('Critical Fix \u2014 OPcache Deploy Pipeline');
body('SiteGround updated PHP 8.2.30\u21928.2.31, silently moving the OPcache directory. deploy-patch-local.ts had the old path hardcoded \u2014 SCP uploads succeeded but OPcache invalidation was a no-op; WordPress served old bytecode on every request.');
code('/home/customer/.opcache/8.2.30-Dec 18 2025-.../  \u2192  stale (wrong path)', RED_DK);
code('/home/customer/.opcache/8.2.31-May 11 2026-.../  \u2192  correct (OPCACHE_BASE updated)', GREEN);
bullet('Locate current path after PHP minor upgrades:  find /home/customer/.opcache -maxdepth 1 -type d');
bullet('Deploy script now clears the correct path automatically on every deploy.');
rule();

// ── Patch Inventory ──────────────────────────────────────────────────────────
h2('Patch Inventory \u2014 Current State');
const patches = [
  ['271', 'NEUTRALIZED \u2014 no-op stub',                           'dead'],
  ['272', 'HCC core intake processor',                               'active'],
  ['280', 'Processing page \u2014 storeResultV2',                    'active'],
  ['290', 'Intake form \u2014 sessionStorage rebaseline setter',     'active'],
  ['291', 'Member gate (any PMPro level)',                            'active'],
  ['292', 'DEAD CODE \u2014 MemberPress + page 630 deleted',         'dead'],
  ['293', 'Premium/unlimited gating + Ministry AJAX',                'active'],
  ['294', 'Single-session ($29) checkout',                           'active'],
  ['295', 'Daily check-in sliders (disconnected from pipeline)',      'inactive'],
  ['296', 'Override gate \u2014 hard-block if no PMPro',             'active'],
  ['297', 'Dashboard, legal styling, ministry reset',                'active'],
  ['298', 'HCC result card v2.9.8j \u2014 rebaseline toggle',        'active'],
  ['299', 'Affiliate area styling + referral field',                  'active'],
  ['300', 'Member journey design \u2014 Botanical palette',          'active'],
  ['301', 'Share with My Provider shortcode',                         'active'],
  ['302', 'PMPro Activation Helper admin page',                      'active'],
  ['303', 'WooCommerce shop CSS + member gate + hero',               'active'],
  ['304', 'Homepage fallback (query-string requests)',                'active'],
  ['305', 'Explore page full override (vision, video, tiers)',        'active'],
  ['306', 'Welcome Member page override',                            'active'],
  ['307', 'Login page branded override',                             'active'],
  ['308', 'Legacy URL rewrite (deleted MemberPress routes)',          'active'],
  ['309', 'Global healer-bg catch-all',                              'active'],
  ['310', '/explore/ Elementor layout fix + text replacements',      'active'],
  ['311', '/welcome-member/ Body Snapshot + dead link fix',          'active'],
  ['312', '/know-the-signals/ PMPro links + Body rename',            'active'],
  ['313', 'Affiliate upgrade, dashboard digest, ministry history',    'active'],
  ['320', '9 affiliate products bulk creation',                      'active'],
  ['323', 'Remove placeholder products',                             'active'],
  ['324', 'Product image attach from filesystem',                    'active'],
  ['325', 'Shop tile redesign v1 (superseded by 327)',               'dead'],
  ['326', 'Shop tile redesign v2 (superseded by 327)',               'dead'],
  ['327', 'Shop \u2014 4-col borderless grid',                       'active'],
  ['328', 'NEUTRALIZED \u2014 no-op',                                'dead'],
  ['329', 'Hide shop labels (PHP hooks + CSS)',                          'active'],
  ['330', 'NEUTRALIZED \u2014 merged into patch-303',                    'dead'],
  ['331', 'Global brand stylesheet (all WP pages)',                      'active'],
  ['332', 'Post-checkout redirect to onboarding page',                  'active'],
  ['333', 'Admin access guard (manage_options)',                         'active'],
  ['334', 'NEUTRALIZED \u2014 was $0.01 test price stub',                'dead'],
  ['335', 'Referral code hardening + retroactive assignment tool',       'active'],
  ['336', 'Excreet House internal affiliate account',                    'active'],
  ['337', 'Starter $15.00 price enforcer \u2014 idempotent init',       'active'],
  ['338', 'Email notification system \u2014 4 branded transactionals',   'active'],
  ['339', 'Global Design Unification \u2014 botanical dark-card palette','active'],
  ['340', 'Intake form header rebuild',                                  'active'],
  ['341', 'Intake form Elementor removal + theme delegation v1',         'dead'],
  ['342', 'Intake form theme delegation v2',                             'dead'],
  ['343', 'Intake form \u2014 page-member-intake-form.php (current)',    'active'],
  ['344', 'Shop grid iteration (superseded by 345)',                     'dead'],
  ['345', 'Shop page redesign \u2014 large white-panel layout',          'active'],
  ['346', 'Signature Formula direct checkout override',                  'active'],
  ['347', 'Global language selector (v3.4.7)',                           'active'],
  ['348', 'Admin Command-Centre dashboard at /admin/',                   'active'],
  ['349', 'Auto-assign unique referral code on every checkout',          'active'],
  ['350', 'Shop page title \u2014 bold gold Cormorant, no archive desc', 'active'],
  ['351', 'Doctor Visit Summary \u2014 flagged tier printable modal',    'active'],
  ['352', 'Membership clarity tables \u2014 pricing, affiliate, account','active'],
];
patches.forEach(([num, desc, status]) => {
  const color = status === 'active' ? GREEN : status === 'dead' ? RED_DK : INACTIVE;
  const icon  = status === 'active' ? '\u25cf' : status === 'dead' ? '\u2715' : '\u25cb';
  doc.fillColor(color).font('Helvetica-Bold').fontSize(7.8)
    .text(icon + '  patch-' + num + '  ', { continued: true })
    .fillColor(MIDGRAY).font('Helvetica').fontSize(7.8)
    .text(desc);
});
rule();

// ── Deployment Reference ─────────────────────────────────────────────────────
h2('Deployment Reference');
kv('SSH target:',   'u2198-g6bobebgdwk2@ssh.excreet.com');
kv('WP root:',      '/home/customer/www/excreet.com/public_html/');
kv('mu-plugins:',   'wp-content/mu-plugins/  (under WP root)');
kv('Uploads:',      'wp-content/uploads/  (under WP root)');
kv('OPcache:',      '/home/customer/.opcache/8.2.31-May 11 2026-.../');
kv('nginx PURGE:',  'curl -X PURGE http://localhost/ -H "Host: excreet.com"  (via SSH)');
kv('Deploy cmd:',   'cd scripts && node --experimental-strip-types src/deploy-patch-local.ts <file>');
kv('Report gen:',   'node scripts/src/generate-technical-report-v5.mjs');
rule();

// ── Next Phase Candidates ────────────────────────────────────────────────────
h2('Next Phase Candidates (Phase 19+)');
const next = [
  ['Complete PMPro Activation',       'Install PMPro on SiteGround \u2192 enter Stripe keys \u2192 Run Activation \u2192 test live checkout.'],
  ['Wire patch-295 sliders',          'Connect daily check-in sliders into Body Snapshot pipeline, or formally deprecate.'],
  ['Ministry session management UI',  'List past sessions by date, allow member to name and switch between threads.'],
  ['/affiliate-area/ full audit',     'Review patch-299 styling, test payout dashboard, validate referral tracking end-to-end.'],
  ['Dashboard weekly digest',         'Add 7-day trend panel, Ministry highlights, supplement adherence summary.'],
  ['Morning ritual procedure video',  'Record/animate the 3-step morning check-in: pH strip dip, urine color snap, bowl photo. TikTok-first 12-second format.'],
  ['WooCommerce checkout styling',    'Dark purple/gold brand on cart, checkout, and order confirmation pages.'],
  ['Mobile readability sweep',        'Full mobile pass \u2014 font sizes, contrast, touch targets across all pages.'],
];
next.forEach(([title, desc]) => {
  doc.fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(8.5)
    .text('\u203a ' + title + '  \u2014  ', { continued: true })
    .fillColor(MIDGRAY).font('Helvetica').fontSize(8.5)
    .text(desc).moveDown(0.08);
});

doc.moveDown(0.6);
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(11)
  .text('EXCREET', { align: 'center', characterSpacing: 6, width: PW });
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8).moveDown(0.15)
  .text('A PRE-CLINICAL WARNING SYSTEM.  \u00b7  Technical Report v5  \u00b7  Confidential',
    { align: 'center', width: PW });

// ── FOOTERS ──────────────────────────────────────────────────────────────────
const range = doc.bufferedPageRange();
for (let i = range.start; i < range.start + range.count; i++) {
  doc.switchToPage(i);
  if (i === range.start) continue;
  const pageNum = i - range.start;
  const total   = range.count - 1;
  const bot = doc.page.height - 22;
  doc.fillColor('#dddddd').rect(ML, bot - 4, PW, 0.5).fill();
  doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(7)
    .text(`Excreet \u2014 Technical Report v5  \u00b7  Confidential  |  Page ${pageNum} of ${total}`,
      ML, bot, { align: 'center', width: PW });
}

doc.end();
stream.on('finish', () => {
  const { size } = fs.statSync(outputPath);
  const range2 = doc.bufferedPageRange();
  console.log(`\u2705 PDF written: ${outputPath}`);
  console.log(`   Pages: ${range2.count}  |  Size: ${(size / 1024).toFixed(1)} KB`);
});
stream.on('error',  (e) => { console.error('\u274c', e); process.exit(1); });
