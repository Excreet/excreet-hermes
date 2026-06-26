import PDFDocument from 'pdfkit';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outputPath = path.resolve(__dirname, '../../attached_assets/Excreet_Vision_Document.pdf');

const doc = new PDFDocument({
  size: 'LETTER',
  margins: { top: 72, bottom: 72, left: 72, right: 72 },
  info: {
    Title: 'Excreet — Vision Document',
    Author: 'Excreet',
    Subject: 'Product Vision & Mission',
  },
});

const stream = fs.createWriteStream(outputPath);
doc.pipe(stream);

// Color palette
const PURPLE   = '#6B21A8';
const GOLD     = '#B8860B';
const DARKGRAY = '#1a1a2e';
const MIDGRAY  = '#444444';
const LIGHTGRAY = '#777777';

// ─── COVER ────────────────────────────────────────────────────────────────────
doc.rect(0, 0, doc.page.width, doc.page.height).fill(DARKGRAY);

doc.moveDown(8);

doc
  .fillColor(GOLD)
  .font('Helvetica-Bold')
  .fontSize(42)
  .text('EXCREET', { align: 'center', characterSpacing: 8 });

doc
  .fillColor('#ffffff')
  .font('Helvetica')
  .fontSize(14)
  .moveDown(0.4)
  .text('CLEANS COMPLETE', { align: 'center', characterSpacing: 4 });

doc
  .fillColor(GOLD)
  .moveDown(1.5)
  .fontSize(11)
  .text('─────────────────────────────', { align: 'center' });

doc
  .fillColor('#cccccc')
  .font('Helvetica-Oblique')
  .fontSize(14)
  .moveDown(1)
  .text('Vision, Mission & Product Philosophy', { align: 'center' });

doc
  .fillColor(LIGHTGRAY)
  .font('Helvetica')
  .fontSize(10)
  .moveDown(14)
  .text('Confidential — ' + new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long' }), {
    align: 'center',
  });

// ─── PAGE 2 ───────────────────────────────────────────────────────────────────
doc.addPage();

const sectionTitle = (text) => {
  doc
    .moveDown(1.5)
    .fillColor(PURPLE)
    .font('Helvetica-Bold')
    .fontSize(13)
    .text(text.toUpperCase(), { characterSpacing: 1.5 });
  doc
    .moveDown(0.2)
    .fillColor(GOLD)
    .rect(doc.page.margins.left, doc.y, 40, 2)
    .fill();
  doc.moveDown(0.6);
};

const body = (text) => {
  doc
    .fillColor(MIDGRAY)
    .font('Helvetica')
    .fontSize(11)
    .lineGap(4)
    .text(text, { align: 'justify' });
};

const pullQuote = (text) => {
  const x = doc.page.margins.left;
  const w = doc.page.width - doc.page.margins.left - doc.page.margins.right;
  doc.moveDown(0.8);
  doc.rect(x, doc.y, 3, 44).fill(GOLD);
  doc
    .fillColor(DARKGRAY)
    .font('Helvetica-BoldOblique')
    .fontSize(12)
    .text(text, x + 16, doc.y - 44 + 8, { width: w - 16, align: 'left', lineGap: 4 });
  doc.moveDown(1);
};

// Executive Summary heading
doc
  .fillColor(DARKGRAY)
  .font('Helvetica-Bold')
  .fontSize(22)
  .text('The Excreet Vision', { align: 'left' });

doc
  .fillColor(GOLD)
  .font('Helvetica-Oblique')
  .fontSize(12)
  .moveDown(0.4)
  .text('A Pre-Clinical Intelligence System for the Modern Body', { align: 'left' });

doc.moveDown(1);

// Section 1
sectionTitle('The Problem: A Storm No One Warned You About');

body(
  'We live in the most toxically saturated era in human history. Environmental pollution, electromagnetic smog, agricultural chemicals — including glyphosate and pesticide residues classified as probable carcinogens by the International Agency for Research on Cancer (IARC) — contaminated municipal water supplies, industrially processed food products, and the compounding effects of sedentary lifestyles, chronic sleep deprivation, dehydration, and toxic relational stress: these forces do not announce themselves. They accumulate silently.'
);

doc.moveDown(0.6);

body(
  'The insidious truth is that the body does not immediately protest. Long before fatigue, inflammation, pain, or diagnosed disease appears, the damage is underway at the cellular and biochemical level. pH begins to shift. Mitochondria lose voltage. The electron-rich environment that healthy cells require is quietly eroded. The storm gathers — but no alarm sounds.'
);

pullQuote(
  '"By the time a doctor sees you, the storm has already broken. Excreet is the barking dog that warns you it\'s coming."'
);

body(
  'Modern medicine, for all its brilliance, is architected to respond to symptoms — to arrive after the damage has been done. Pharmaceuticals manage. Surgery repairs. Radiation targets. But these are late-stage interventions layered onto a body that has been silently degrading, often for years. In many cases, the treatments themselves introduce new toxic burdens that the already-compromised body must then metabolize.'
);

// Section 2
sectionTitle('The Insight: The Body Speaks Before Symptoms Do');

body(
  'There is a window — a pre-symptomatic phase — during which the body\'s internal chemistry is shifting but has not yet manifested as observable illness. This is the critical window that conventional medicine has no reliable mechanism to act within.'
);

doc.moveDown(0.6);

body(
  'The body\'s first signal is electrochemical: cellular voltage drops, oxidative burden rises, pH becomes progressively more acidic. These are not mysterious forces — they are measurable, trackable, and, crucially, reversible — if caught early enough and addressed at the root rather than the symptom.'
);

doc.moveDown(0.6);

body(
  'This is where Excreet enters. Not as a medication, not as a treatment, not as a response to disease — but as an intelligence system and a cellular support protocol designed to operate entirely within this pre-clinical window.'
);

// ─── PAGE 3 ───────────────────────────────────────────────────────────────────
doc.addPage();

sectionTitle('The Solution: Excreet Cleans Complete');

body(
  'Excreet operates on two parallel tracks that together form a complete system:'
);

doc.moveDown(0.6);

doc
  .fillColor(PURPLE)
  .font('Helvetica-Bold')
  .fontSize(11)
  .text('Track 1 — The Supplement: Cellular Recharge');

doc.moveDown(0.3);

body(
  'The Excreet supplement is formulated to donate high-density electrons to the body\'s cellular environment — directly addressing the electrochemical deficit at the root of cellular degradation. By restoring the electron-rich, alkaline-leaning conditions that healthy cells require, the supplement works upstream of symptoms: neutralizing oxidative stress, supporting mitochondrial energy production, and creating the biochemical conditions in which the body can perform its own elimination and repair functions completely. Hence — Excreet Cleans Complete.'
);

doc.moveDown(0.8);

doc
  .fillColor(PURPLE)
  .font('Helvetica-Bold')
  .fontSize(11)
  .text('Track 2 — The Platform: Body Intelligence');

doc.moveDown(0.3);

body(
  'The Excreet member platform is the intelligence layer. Through periodic body signal assessments, daily check-ins, and AI-powered pattern recognition, the platform generates a living Body Score — a dynamic index of cellular health trends over time. Members see not just where they are, but where they are heading, and why. The platform translates biochemical signal data into plain language that a person can act on today — without requiring a clinical appointment, a lab order, or a diagnosis.'
);

doc.moveDown(0.6);

body(
  'The Ministry of Healing — Excreet\'s AI health companion — deepens this further. It is not a generic chatbot. It is a cellular health interpreter: able to contextualize a member\'s Body Score history, their environment, their lifestyle inputs, and their supplement protocol into personalized, actionable guidance. When the body\'s score trends downward, Ministry explains why. When a pattern signals a biochemical warning, Ministry surfaces it — before the symptom does.'
);

pullQuote(
  '"Excreet is not where you go when you\'re sick. It\'s how you ensure you never get there."'
);

// Section 4
sectionTitle('The Population This Serves');

body(
  'Excreet is designed for the intelligent adult who senses that something is quietly wrong — who feels the gap between how they know they should feel and how they actually feel — but who has been failed by a medical system that only speaks the language of diagnosis. It serves the parent who reads the label. The professional who notices the slow cognitive fog. The athlete who feels the diminishing returns. The person who has watched someone they love receive a late-stage diagnosis and has decided that their own story will be written differently.'
);

doc.moveDown(0.6);

body(
  'These are not passive patients. They are active participants in their own biology — they simply need the right instrument to hear what their body is already trying to say.'
);

// ─── PAGE 4 ───────────────────────────────────────────────────────────────────
doc.addPage();

sectionTitle('The Long-Term Vision');

body(
  'In the near term, Excreet is a membership platform paired with a cellular support supplement — a direct-to-consumer system that delivers early warning intelligence and biochemical support to individuals who choose to act before the crisis arrives.'
);

doc.moveDown(0.6);

body(
  'In the long term, Excreet aspires to be the standard of pre-clinical health monitoring for the modern world: a platform where the Body Score becomes as routine as a blood pressure reading, where AI-interpreted body signal trends can be shared directly with forward-thinking clinicians, and where the supplement protocol evolves alongside each member\'s biological data.'
);

doc.moveDown(0.6);

body(
  'The Provider Report — already embedded in the platform — is a first step in this direction: a printable, clinician-ready summary of a member\'s body signal history and AI-interpreted health patterns, bridging the Excreet intelligence layer with the clinical world in a language both parties can use.'
);

doc.moveDown(0.6);

body(
  'The moat Excreet builds is not chemical — it is informational and relational. A member who has two years of Body Score history, Ministry conversation context, and a personalized supplement response profile does not simply leave. They have, in effect, a continuous biological record that becomes more valuable over time. Excreet becomes part of their health identity.'
);

pullQuote(
  '"The supplement cleanses the body. The platform teaches it to speak. Together, they give the modern individual what medicine never has: a warning."'
);

// Section — Closing
sectionTitle('Mission Statement');

doc
  .fillColor(DARKGRAY)
  .font('Helvetica-BoldOblique')
  .fontSize(13)
  .lineGap(6)
  .text(
    'Excreet exists to give every person a fighting chance against the invisible forces eroding their health — to stand between modern civilization\'s toxic tide and the human body, and to sound the alarm early enough that it still means something.',
    { align: 'justify' }
  );

doc.moveDown(2);

doc
  .fillColor(GOLD)
  .font('Helvetica-Bold')
  .fontSize(11)
  .text('EXCREET', { align: 'center', characterSpacing: 6 });

doc
  .fillColor(LIGHTGRAY)
  .font('Helvetica')
  .fontSize(9)
  .moveDown(0.3)
  .text('CLEANS COMPLETE', { align: 'center', characterSpacing: 3 });

// ─── Footer on each page ──────────────────────────────────────────────────────
const range = doc.bufferedPageRange();
for (let i = range.start; i < range.start + range.count; i++) {
  doc.switchToPage(i);
  if (i === range.start) continue; // skip cover
  const bottom = doc.page.height - 40;
  doc
    .fillColor(LIGHTGRAY)
    .font('Helvetica')
    .fontSize(8)
    .text(
      `Excreet — Confidential Vision Document   |   Page ${i}`,
      doc.page.margins.left,
      bottom,
      { align: 'center', width: doc.page.width - doc.page.margins.left - doc.page.margins.right }
    );
}

doc.end();

stream.on('finish', () => {
  console.log('PDF written to:', outputPath);
});
