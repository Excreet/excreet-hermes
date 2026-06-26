import PDFDocument from 'pdfkit';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outputPath = path.resolve(__dirname, '../../artifacts/hermes-ui/public/excreet-member-guide.pdf');

const doc = new PDFDocument({
  size: 'LETTER',
  margins: { top: 44, bottom: 36, left: 52, right: 52 },
  bufferPages: true,
  info: {
    Title:   'Excreet — Member Guide',
    Author:  'Excreet',
    Subject: 'How to use Excreet — your bathroom is your laboratory.',
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
const ML = 52;
const PW = doc.page.width - ML * 2;

// ── Helpers ──────────────────────────────────────────────────────────────────
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
    .text(t, { width: PW }).moveDown(0.08);

const body = (t) =>
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(9).lineGap(1.4)
    .text(t, { align: 'left', width: PW }).moveDown(0.1);

const step = (num, title, detail) => {
  doc.moveDown(0.1);
  const y0 = doc.y;
  doc.fillColor(PURPLE).rect(ML, y0, 22, 22).fill();
  doc.fillColor(WHITE).font('Helvetica-Bold').fontSize(11)
    .text(String(num), ML, y0 + 5, { width: 22, align: 'center' });
  doc.fillColor(DARKGRAY).font('Helvetica-Bold').fontSize(9.5)
    .text(title, ML + 28, y0 + 2, { width: PW - 28 });
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(8.8).lineGap(1.2)
    .text(detail, ML + 28, doc.y + 1, { width: PW - 28 });
  doc.moveDown(0.25);
};

const bullet = (t) =>
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(9).lineGap(1.2)
    .text('\u2022  ' + t, { indent: 8, width: PW - 8 }).moveDown(0.04);

const tip = (t) => {
  doc.moveDown(0.15);
  const y0 = doc.y;
  const lines = doc.heightOfString(t, { width: PW - 28, fontSize: 8.5 });
  doc.fillColor('#f5f0ff').rect(ML, y0, PW, lines + 14).fill();
  doc.fillColor(PURPLE).font('Helvetica-Bold').fontSize(8.5)
    .text('\u2605  ' + t, ML + 8, y0 + 7, { width: PW - 16 });
  doc.y = y0 + lines + 18;
  doc.moveDown(0.05);
};

const rule = () => {
  doc.moveDown(0.25);
  doc.fillColor('#ddd').rect(ML, doc.y, PW, 0.5).fill();
  doc.moveDown(0.28);
};

const kv = (k, v) =>
  doc.fillColor(PURPLE_DK).font('Helvetica-Bold').fontSize(9)
    .text(k + '  ', { continued: true, width: 130 })
    .fillColor(MIDGRAY).font('Helvetica').fontSize(9)
    .text(v, { width: PW - 130 })
    .moveDown(0.04);

// ── COVER ────────────────────────────────────────────────────────────────────
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
  .text('Member Guide', { align: 'center', width: PW });
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(10).moveDown(0.25)
  .text('Everything you need to know to get the most from your membership.', { align: 'center', width: PW });

doc.moveDown(2.5);
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(10)
  .text('Your bathroom is your laboratory.', { align: 'center', characterSpacing: 2, width: PW });

doc.y = doc.page.height - 68;
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8.5)
  .text('excreet.com  \u00b7  support@excreet.com  \u00b7  ' +
    new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long' }),
    { align: 'center', width: PW });

// ── PAGE 2: Welcome + What You Need ──────────────────────────────────────────
doc.addPage();

h1('Welcome to Excreet');
body('Excreet is a pre-clinical health intelligence platform. Every morning, your body sends a full report through your urine, saliva, and bowel — color, pH, consistency. These are signals that can show up weeks before a doctor would ever catch them.');
body('Excreet reads those signals, scores them, and tells you in plain language what your body is navigating — inflammation, gut dysfunction, cellular stress, hydration — and what to do about it. Under five minutes a day.');
rule();

h2('What You Need to Get Started');
h3('1. pH Test Strips');
body('You need wide-range pH test strips that measure both urine and saliva. Look for strips that read pH 4.5\u20139.0. Recommended options available on Amazon:');
bullet('"Health Logics Urine and Saliva pH Test Strips" \u2014 accurate, easy to read');
bullet('"pHion Balance Diagnostic pH Test Strips" \u2014 color chart included');
bullet('Any wide-range urinalysis strip that includes pH (5-in-1 or 10-in-1 strips work)');
tip('Tip: Store strips in a cool, dry place. Humidity ruins them. Keep the cap tightly closed.');

doc.moveDown(0.2);
h3('2. Your Phone Camera');
body('No special equipment needed. Your smartphone camera is all you use. Take photos in natural light when possible \u2014 bathroom light is fine, but avoid dim yellow lighting which skews color readings.');

h3('3. Your Excreet Account');
body('Log in at excreet.com before you start. Your check-in, score, and history are all stored to your account. If you are not logged in, your results will not be saved.');
rule();

h2('Your Morning Routine \u2014 Step by Step');
body('Do this first thing in the morning, before eating or drinking anything. Your first-morning samples give the most accurate reading.');
doc.moveDown(0.15);

step(1, 'Dip the pH strip \u2014 urine',
  'Hold the strip in your urine stream for 3\u20135 seconds, or dip it in a small cup. Shake off excess. Wait 15 seconds, then compare the color to the chart on the packaging. Note the pH number.');

step(2, 'Dip the pH strip \u2014 saliva (optional but recommended)',
  'Spit onto a spoon or small dish. Dip a fresh strip in your saliva for 3 seconds. Compare to the chart. Saliva pH gives Excreet a second reference point for your body\'s acid-alkaline balance.');

step(3, 'Note your urine color',
  'Look at the color of your urine. You do not need to photograph the toilet \u2014 just note the color: pale yellow, bright yellow, dark amber, orange, or clear. You will select this in the check-in form.');

step(4, 'Note your bowel (if applicable)',
  'If you had a bowel movement this morning, note the consistency: formed, loose, hard pellets, watery, or absent. This is one of the most informative signals Excreet reads.');

step(5, 'Photograph your pH strip',
  'Take a clear, well-lit photo of your pH strip next to the color chart on its packaging. This is the photo you upload. Keep the photo in good light \u2014 blurry or dark photos reduce accuracy.');

step(6, 'Open Excreet and submit',
  'Go to excreet.com and navigate to the Healing Command Center. Fill in the short questionnaire (how you feel, sleep, energy), select your colors, upload your strip photo, and submit. The AI takes it from there.');

step(7, 'Read your result',
  'Within 1\u20132 minutes, your Vitality Score and body reading appear. Review your score, read your pattern summary, and check the recommended actions.');

rule();

// ── PAGE 3: Understanding Your Score ─────────────────────────────────────────
h2('Understanding Your Vitality Score');
body('Your Vitality Score is a number from 0 to 100. It reflects how well your body\'s systems appear to be operating based on the signals you submitted. 100 means full alignment. 0 means acute distress. Most healthy adults score between 55 and 80 on a typical morning.');
doc.moveDown(0.1);

const tiers = [
  ['NUDGE  (Score ~60\u201380)',    'A simple, self-resolvable signal. You may be mildly dehydrated, had a rough night of sleep, or skipped a meal. The recommended actions are straightforward: drink water, rest, adjust a habit.'],
  ['CHECK-IN  (Score ~45\u201365)', 'A persistent mild signal or early pattern. Not urgent, but your body is asking for more attention. A Ministry of Healing session is recommended to explore what\'s building.'],
  ['PROTOCOL  (Score ~25\u201350)', 'A systemic pattern or moderate imbalance. A full Ministry of Healing protocol is recommended. You may also see doctor-visit guidance with specific questions and lab tests to request.'],
  ['ALARM  (Score ~0\u201335)',     'Your signal pattern warrants both medical navigation and healing support. A "Prepare Doctor Visit Summary" button will appear. This gives you a formatted, printable report to bring to your provider.'],
];
tiers.forEach(([title, desc]) => {
  doc.moveDown(0.1);
  doc.fillColor(PURPLE).font('Helvetica-Bold').fontSize(9.5).text(title, { width: PW });
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(8.8).lineGap(1.2)
    .text(desc, { indent: 10, width: PW - 10 }).moveDown(0.1);
});

tip('Important: Excreet is not a medical diagnosis tool. Your score is an educational signal, not a clinical finding. Always consult a qualified health professional for medical decisions.');
rule();

h2('Doctor Visit Summary');
body('If your result is in the Protocol or Alarm tier, a purple "Prepare Doctor Visit Summary" button appears on your results page. Click it to generate a formatted, printable report that includes:');
bullet('Your Vitality Score and pattern reading in plain language');
bullet('Questions to bring to your provider by name');
bullet('Specific lab tests to request (e.g. "Free T3/T4 thyroid panel")');
bullet('Red flags \u2014 specific signs that mean seek urgent care now');
body('Use the Print / Save as PDF button to keep a copy or email it to your provider before your appointment.');
rule();

h2('Ministry of Healing');
body('The Ministry of Healing is your private AI health intelligence companion inside Excreet. It knows your body check history, your clinical pattern, and your health context. You can have a real conversation with it \u2014 ask questions, dig deeper into your results, or get guided protocols.');
kv('Starter membership:', '10 sessions per month');
kv('Premium membership:', '20 sessions per month');
doc.moveDown(0.1);
bullet('Sessions reset on the 1st of each month.');
bullet('Each session is a full conversation \u2014 not just one message.');
bullet('You can upload photos or PDFs during a session for deeper context.');
bullet('To access: go to the Healing Command Center and open the Ministry of Healing section.');
rule();

// ── PAGE 4: Affiliate + Cancellation ─────────────────────────────────────────
h2('Your Affiliate Program');
body('Every Excreet member \u2014 Starter and Premium alike \u2014 is automatically enrolled as an affiliate. You do not need to apply. You have a unique referral link in your account.');

h3('How it works');
bullet('Share your referral link with friends, family, social media, or email.');
bullet('When someone joins through your link and their membership stays active, you earn a monthly commission.');
bullet('Your membership must also remain active for earnings to count.');

doc.moveDown(0.1);
h3('What you earn');

const earningsRows = [
  ['You are a Starter ($15/mo)', 'They join Starter ($15/mo)', '$5/mo'],
  ['You are a Starter ($15/mo)', 'They join Premium ($25/mo)', '$5/mo'],
  ['You are a Premium ($25/mo)', 'They join Starter ($15/mo)', '$5/mo'],
  ['You are a Premium ($25/mo)', 'They join Premium ($25/mo)', '$10/mo'],
];

const colW = [PW * 0.38, PW * 0.38, PW * 0.24];
const tableY = doc.y + 4;
const headerH = 18;
doc.fillColor(PURPLE).rect(ML, tableY, PW, headerH).fill();
doc.fillColor(WHITE).font('Helvetica-Bold').fontSize(8)
  .text('Your Tier', ML + 4, tableY + 5, { width: colW[0] - 4, continued: true })
  .text('Referred Member\'s Tier', { width: colW[1], continued: true })
  .text('You Earn', { width: colW[2] });

let rowY = tableY + headerH;
earningsRows.forEach(([c1, c2, c3], i) => {
  const bg = i % 2 === 0 ? '#faf7ff' : '#ffffff';
  doc.fillColor(bg).rect(ML, rowY, PW, 16).fill();
  doc.fillColor(MIDGRAY).font('Helvetica').fontSize(8)
    .text(c1, ML + 4, rowY + 4, { width: colW[0] - 4, continued: true })
    .text(c2, { width: colW[1], continued: true });
  doc.fillColor(GREEN).font('Helvetica-Bold').fontSize(8)
    .text(c3, { width: colW[2] });
  rowY += 16;
});
doc.rect(ML, tableY, PW, rowY - tableY).stroke('#e4d9f5');
doc.y = rowY + 8;

doc.moveDown(0.1);
h3('Payout rules');
bullet('Minimum $50 accumulated before payout.');
bullet('Payouts issued every 2 weeks.');
bullet('Store purchases and ancillary services do not generate commissions.');
bullet('If your referred member cancels, earnings for that member stop immediately.');

tip('Where is my referral link? Log in \u2192 go to /affiliate-area/ \u2192 your unique link is at the top of the page.');
rule();

h2('SMS Morning Notifications');
body('Excreet can send you a daily morning reminder to do your body check. To receive SMS notifications:');
bullet('Your phone number must be registered in the United States (US numbers only at this time).');
bullet('Go to your account settings and add your US mobile number.');
bullet('Notifications are sent between 6:00\u20138:00 AM in your local time zone.');
rule();

h2('Excreet Signature Formula');
body('The Excreet Signature Formula is an optional product available in the Excreet Store. It is currently available for shipping within the United States only. International availability will be announced when ready.');
rule();

h2('How to Cancel Your Membership');
body('You may cancel at any time. Your access continues through the end of the billing period you have already paid for.');
bullet('Log in \u2192 go to /membership-account/');
bullet('Under Membership, click Cancel next to your active plan.');
bullet('Confirm. You will receive an email confirmation.');
bullet('Affiliate earnings above $50 at time of cancellation will be paid on the next scheduled payout date.');
bullet('Need help? Email support@excreet.com \u2014 response within 1 business day.');
rule();

h2('Quick Reference');
kv('Daily check-in:', 'excreet.com \u2192 Healing Command Center');
kv('Ministry of Healing:', 'Healing Command Center \u2192 Ministry section');
kv('Your referral link:', 'excreet.com/affiliate-area/');
kv('Account & billing:', 'excreet.com/membership-account/');
kv('Store:', 'excreet.com/shop/');
kv('Support email:', 'support@excreet.com');

doc.moveDown(0.8);
doc.fillColor(GOLD_LT).font('Helvetica-Bold').fontSize(10)
  .text('EXCREET  \u00b7  Your bathroom is your laboratory.', { align: 'center', characterSpacing: 2, width: PW });
doc.fillColor(LIGHTGRAY).font('Helvetica').fontSize(8).moveDown(0.2)
  .text('This guide is for educational purposes. Excreet is not a medical device and does not provide medical advice.',
    { align: 'center', width: PW });

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
    .text(`Excreet Member Guide  \u00b7  excreet.com  |  Page ${pageNum} of ${total}`,
      ML, bot, { align: 'center', width: PW });
}

doc.end();
stream.on('finish', () => {
  const { size } = fs.statSync(outputPath);
  console.log(`\u2705 Member Guide PDF written: ${outputPath}`);
  console.log(`   Size: ${(size / 1024).toFixed(1)} KB`);
});
stream.on('error', (e) => { console.error('\u274c', e); process.exit(1); });
