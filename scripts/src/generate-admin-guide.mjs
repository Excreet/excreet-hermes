import PDFDocument from 'pdfkit';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outputPath = path.resolve(__dirname, '../../artifacts/hermes-ui/public/excreet-admin-guide.pdf');

const doc = new PDFDocument({
  size: 'LETTER',
  margins: { top: 44, bottom: 36, left: 52, right: 52 },
  bufferPages: true,
  info: {
    Title:   'Excreet — Admin & Operations Guide',
    Author:  'Excreet Engineering',
    Subject: 'Site administration, deployment, and operations reference.',
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
const RED       = '#7f1d1d';
const ML = 52;
const PW = doc.page.width - ML * 2;

// ── Helpers ───────────────────────────────────────────────────────────────────
const h1 = (t) =>
  doc.fillColor(DARKGRAY).font('Helvetica-Bold').fontSize(16)
    .text(t, { width: PW }).moveDown(0.2);

const h2 = (t) => {
  doc.moveDown(0.4);
  doc.fillColor(PURPLE).font('Helvetica-Bold').fontSize(10.5)
    .text(t.toUpperCase(), { characterSpacing: 1, width: PW });
  doc.fillColor(GOLD_LT).rect(ML, doc.y + 2, 32, 1.2).fill();
  doc.moveDown(0.35);
};

const h3 = (t) =>
  doc.moveDown(0.1)
    .fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(9.5)
    .text(t, { width: PW }).moveDown(0.06);

const body = (t) =>
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(9).lineGap(1.4)
    .text(t, { align: 'left', width: PW }).moveDown(0.1);

const bullet = (t) =>
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(9).lineGap(1.2)
    .text('\u2022  ' + t, { indent: 8, width: PW - 8 }).moveDown(0.04);

const code = (t, color) =>
  doc.fillColor(color || MIDGRAY).font('Courier').fontSize(8)
    .text('  ' + t, { width: PW }).moveDown(0.06);

const kv = (k, v) =>
  doc.fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(9)
    .text(k + '  ', { continued: true, width: 155 })
    .fillColor(MIDGRAY).font('Helvetica').fontSize(9)
    .text(v, { width: PW - 155 })
    .moveDown(0.04);

const warn = (t) => {
  doc.moveDown(0.15);
  const y0 = doc.y;
  const lines = doc.heightOfString(t, { width: PW - 24, fontSize: 8.5 }) + 14;
  doc.fillColor('#fff3cd').rect(ML, y0, PW, lines).fill();
  doc.fillColor('#856404').font('Helvetica-Bold').fontSize(8.5)
    .text('\u26a0  ' + t, ML + 8, y0 + 7, { width: PW - 16 });
  doc.y = y0 + lines + 4;
  doc.moveDown(0.08);
};

const rule = () => {
  doc.moveDown(0.25);
  doc.fillColor('#ddd').rect(ML, doc.y, PW, 0.5).fill();
  doc.moveDown(0.28);
};

// ── COVER ─────────────────────────────────────────────────────────────────────
doc.rect(0, 0, doc.page.width, doc.page.height).fill(DARKGRAY);

doc.y = doc.page.height * 0.22;
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(48)
  .text('EXCREET', { align: 'center', characterSpacing: 10, width: PW });
doc.fillColor(WHITE).font('Helvetica').fontSize(11).moveDown(0.2)
  .text('A PRE-CLINICAL WARNING SYSTEM.', { align: 'center', characterSpacing: 3, width: PW });

doc.moveDown(0.7);
doc.fillColor(GOLD_LT).rect(ML + PW * 0.15, doc.y, PW * 0.7, 0.75).fill();
doc.moveDown(0.5);

doc.fillColor('#cccccc').font('Helvetica-Oblique').fontSize(18)
  .text('Admin & Operations Guide', { align: 'center', width: PW });
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(10).moveDown(0.25)
  .text('Site management, deployment, and operations reference.', { align: 'center', width: PW });

doc.moveDown(1.5);
doc.fillColor('#ff6b6b').font('Helvetica-Bold').fontSize(9)
  .text('CONFIDENTIAL \u2014 DO NOT DISTRIBUTE', { align: 'center', characterSpacing: 2, width: PW });

doc.y = doc.page.height - 68;
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8.5)
  .text('Excreet Engineering  \u00b7  ' +
    new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
    { align: 'center', width: PW });

// ── PAGE 2: System Overview + Access ─────────────────────────────────────────
doc.addPage();

h1('System Overview');
body('Excreet runs across two environments that talk to each other via a secure API key.');
doc.moveDown(0.1);
kv('WordPress (front-end):', 'Hosted on SiteGround. PHP 8.2.31, nginx proxy cache. All member-facing pages, PMPro membership, WooCommerce store.');
kv('Hermes (back-end API):', 'Hosted on Replit. Express 5 / TypeScript / Node 24. AI scoring, Ministry of Healing, job store, affiliate logic.');
kv('Database:', 'PostgreSQL on Replit (Replit-managed). Drizzle ORM. Tables: hermes_jobs, ministry_chat_history, etc.');
kv('AI:', 'Anthropic Claude (claude-haiku-4-5) for health intake scoring and Ministry sessions.');
kv('Payments:', 'Stripe via PMPro. Starter $15/mo (Level 1), Premium $25/mo (Level 2), Single Session $29 (Level 4).');
kv('Monorepo:', 'pnpm workspaces on Replit. TypeScript 5.9, esbuild CJS bundle.');
rule();

h2('Key Access Credentials');
warn('Never write API keys or passwords in documents. These are reference labels only \u2014 retrieve actual values from Replit Secrets.');
kv('SiteGround SSH:', 'u2198-g6bobebgdwk2@ssh.excreet.com  port 18765');
kv('Hermes API Key:', 'Stored in Replit Secrets as HERMES_API_KEY');
kv('OpenAI / Anthropic:', 'Stored in Replit Secrets as OPENAI_API_KEY');
kv('Session Secret:', 'Stored in Replit Secrets as SESSION_SECRET');
kv('SiteGround deploy key:', 'Stored in Replit Secrets as SITEGROUND_DEPLOY_KEY');
kv('WP root on server:', '/home/customer/www/excreet.com/public_html/');
kv('mu-plugins folder:', '/home/customer/www/excreet.com/public_html/wp-content/mu-plugins/');
kv('OPcache path:', '/home/customer/.opcache/8.2.31-May 11 2026-zts-20230831-aarch64-linux-gnu/');
rule();

h2('WordPress Admin');
kv('Admin URL:', 'excreet.com/wp-admin/');
kv('Excreet Activation:', 'WP Admin \u2192 Excreet Activation (patch-302 admin page)');
kv('PMPro Levels:', 'WP Admin \u2192 Memberships \u2192 Membership Levels');
kv('WooCommerce:', 'WP Admin \u2192 WooCommerce \u2192 Products / Orders');
kv('Affiliate area:', 'WP Admin \u2192 Affiliates (if AffiliateWP) or /affiliate-area/ page');
rule();

// ── PAGE 3: Deployment ────────────────────────────────────────────────────────
h2('How to Deploy a Patch (PHP mu-plugin)');
body('All WordPress customizations live as PHP files in the mu-plugins folder on SiteGround. They are deployed via SCP from Replit using a TypeScript deploy script.');

h3('Standard deploy command');
code('pnpm --filter @workspace/scripts run deploy:patch:local -- excreet-hermes-patch-NNN.php', GREEN);
body('This does the following automatically:');
bullet('SCP uploads the file to SiteGround mu-plugins folder');
bullet('Runs OPcache invalidation for the file (so PHP sees the new version immediately)');
bullet('Sends an nginx PURGE request to clear the proxy cache');
bullet('Flushes the WP object cache');

h3('Deploy a static asset (image, CSS, JS)');
code('pnpm --filter @workspace/scripts run upload:asset -- <localPath> <remotePath>', GREEN);

h3('Manual nginx cache purge (via SSH)');
body('If pages are stale after a deploy, SSH into SiteGround and run:');
code('curl -X PURGE http://localhost/ -H "Host: excreet.com"', GREEN);
body('This is the only reliable cache invalidation method. wp sg purge does not exist (no SG Optimizer plugin). wp cache flush only clears WP object cache, not nginx.');

h3('Important: OPcache path');
body('SiteGround silently moves the OPcache directory when PHP minor versions update. If deploys succeed but changes do not appear, the OPcache path may be stale. Check the current path via SSH:');
code('find /home/customer/.opcache -maxdepth 1 -type d', PURPLE_DK);
body('Update OPCACHE_BASE in deploy-patch-local.ts to match the new path.');
rule();

h2('How to Create a New Patch');
bullet('Create file: artifacts/api-server/wordpress/excreet-hermes-patch-NNN.php');
bullet('Use the next sequential number (currently up to patch-352)');
bullet('Add Plugin Name, Description, Version, Author in the header comment');
bullet('Always start with: if ( ! defined( \'ABSPATH\' ) ) exit;');
bullet('Add hooks: add_action(), add_filter(), add_shortcode() as needed');
bullet('Deploy using the standard deploy command above');
warn('mu-plugins load automatically on every WordPress page load. A PHP error in any mu-plugin can bring down the whole site. Always test logic before deploying.');
rule();

h2('Hermes API Server');
h3('Restart the server');
body('In Replit, open the project, find the "artifacts/api-server: API Server" workflow, and click Restart. Or use the Replit shell:');
code('# Replit handles this via the workflow panel \u2014 click Restart in the UI', LIGHTGRAY);

h3('Check server health');
code('curl https://excreet.com/api/hermes/health', GREEN);

h3('Trigger background image rotation manually');
code('curl -X POST https://excreet.com/api/hermes/admin/rotate-background \\', GREEN);
code('  -H "Authorization: Bearer <HERMES_API_KEY>"', GREEN);

h3('Scheduled jobs');
bullet('Monthly AI background rotation fires 1st of each month at 06:00 UTC');
bullet('Generates a bathroom scene via DALL-E 3, SCPs to SiteGround as healer-bg-MM.jpg');
rule();

// ── PAGE 4: PMPro + Membership ────────────────────────────────────────────────
h2('PMPro Activation (Fresh Install)');
body('If PMPro needs to be activated on a fresh site, use the built-in Activation Helper:');
bullet('WP Admin \u2192 Excreet Activation (patch-302)');
bullet('Confirm PMPro is installed and Stripe keys are entered');
bullet('Click "Run Activation" \u2014 this creates Levels 1\u20134 and wires all PMPro page options');
bullet('Complete the manual post-activation checklist: verify Stripe test mode, run a test checkout');

h3('PMPro Level Reference');
kv('Level 1 \u2014 Starter:', '$15/month  \u00b7  10 Ministry sessions  \u00b7  full affiliate');
kv('Level 2 \u2014 Premium:', '$25/month  \u00b7  20 Ministry sessions  \u00b7  full affiliate');
kv('Level 3 \u2014 Unlimited:', 'Internal only  \u00b7  no public signup  \u00b7  admin-assigned');
kv('Level 4 \u2014 Single Session:', '$29 one-time  \u00b7  billing_limit=1');

h3('PMPro key hooks in patches');
bullet('pmpro_hasMembershipLevel(null, $user_id) \u2014 checks any active level');
bullet('pmpro_hasMembershipLevel($level_id, $user_id) \u2014 checks specific level');
bullet('pmpro_addMembershipLevel() \u2014 creates levels on first init');
bullet('pmpro_after_checkout hook \u2014 fires after successful purchase');
bullet('pmpro_url(\'checkout\', \'?level=N\') \u2014 generates checkout URL for level N');
rule();

h2('Affiliate Program Rules (Canonical)');
warn('These rules must not be changed in any UI, copy, or onboarding flow without owner confirmation.');
kv('Starter earns (Starter referral):', '$5/month per active referred member');
kv('Starter earns (Premium referral):', '$5/month per active referred member');
kv('Premium earns (Starter referral):', '$5/month per active referred member');
kv('Premium earns (Premium referral):', '$10/month per active referred member');
kv('Payout minimum:', '$50 accumulated balance');
kv('Payout frequency:', 'Every 2 weeks');
kv('Store/ancillary proceeds:', 'NOT included in affiliate earnings');
kv('SMS notifications:', 'US phone numbers only');
kv('Excreet bottle shipping:', 'US only at this time');
rule();

h2('Database Reference');
kv('Connection:', 'DATABASE_URL environment variable (Replit Secrets)');
kv('ORM:', 'Drizzle ORM \u2014 schema in lib/db/src/schema.ts');
kv('Push schema changes:', 'pnpm --filter @workspace/db run push');
kv('hermes_jobs:', 'All AI job records \u2014 status, result, member_id, workflow_type');
kv('ministry_chat_history:', 'One row per member \u2014 messages stored as JSONB array');
rule();

h2('Typecheck & Build');
code('pnpm run typecheck                    # full workspace typecheck', GREEN);
code('pnpm --filter @workspace/api-server run typecheck   # API server only', GREEN);
code('pnpm --filter @workspace/api-spec run codegen       # regenerate API hooks', GREEN);
rule();

h2('Generate PDF Reports');
code('node scripts/src/generate-technical-report-v5.mjs  # Technical Report v5', GREEN);
code('node scripts/src/generate-member-guide.mjs         # Member User Guide', GREEN);
code('node scripts/src/generate-admin-guide.mjs          # This guide', GREEN);
body('Output location: artifacts/hermes-ui/public/ (served at excreet.com/hermes-ui/)');
rule();

h2('Key Pages on excreet.com');
kv('/  (homepage):', 'excreet-homepage-index.php at WP root \u2014 bypasses nginx cache');
kv('/healing-command-center/:', 'HCC \u2014 daily body check + AI result card (page ID 257)');
kv('/member-dashboard/:', 'Member overview \u2014 Body Score banner, CPR card');
kv('/affiliate-area/:', 'Referral link, earnings tracking');
kv('/membership-account/:', 'PMPro account management + cancellation');
kv('/membership-options/:', 'Pricing + tier comparison table');
kv('/shop/:', 'WooCommerce affiliate product store');
kv('/admin/:', 'Excreet Command-Centre dashboard (patch-348)');
kv('/provider-report/:', 'Share with My Provider printable page (patch-301)');
rule();

h2('Common Issues & Fixes');
h3('Deploy succeeds but change does not appear');
bullet('OPcache path may be stale \u2014 find current path and update OPCACHE_BASE in deploy script');
bullet('nginx cache not purged \u2014 run curl -X PURGE manually via SSH');
bullet('Check that the patch file is in mu-plugins, not plugins');

h3('PHP error brings down the site');
bullet('SSH in and rename or delete the offending mu-plugin file immediately');
bullet('mu-plugins load silently \u2014 a fatal error in any file = white screen');
bullet('Always test PHP syntax before deploy: php -l excreet-hermes-patch-NNN.php');

h3('Hermes API not responding');
bullet('Check workflow is running in Replit panel');
bullet('Check HERMES_API_KEY secret matches what WordPress sends in Authorization header');
bullet('Test: curl https://excreet.com/api/hermes/health');

h3('Member can\'t see their score after submitting');
bullet('Check job status: GET /api/hermes/job-status/:jobId');
bullet('Check hermes_jobs table for failed status and error column');
bullet('Check OpenAI/Anthropic API key is valid and has credits');

doc.moveDown(0.8);
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(10)
  .text('EXCREET  \u00b7  Admin & Operations Guide  \u00b7  CONFIDENTIAL', { align: 'center', characterSpacing: 1, width: PW });

// ── FOOTERS ───────────────────────────────────────────────────────────────────
const range = doc.bufferedPageRange();
for (let i = range.start; i < range.start + range.count; i++) {
  doc.switchToPage(i);
  if (i === range.start) continue;
  const pageNum = i - range.start;
  const total   = range.count - 1;
  const bot = doc.page.height - 22;
  doc.fillColor('#ddd').rect(ML, bot - 4, PW, 0.5).fill();
  doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(7)
    .text(`Excreet Admin & Operations Guide  \u00b7  CONFIDENTIAL  |  Page ${pageNum} of ${total}`,
      ML, bot, { align: 'center', width: PW });
}

doc.end();
stream.on('finish', () => {
  const { size } = fs.statSync(outputPath);
  console.log(`\u2705 Admin Guide PDF written: ${outputPath}`);
  console.log(`   Size: ${(size / 1024).toFixed(1)} KB`);
});
stream.on('error', (e) => { console.error('\u274c', e); process.exit(1); });
