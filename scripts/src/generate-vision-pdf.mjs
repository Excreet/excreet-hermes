import PDFDocument from "pdfkit";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.resolve(__dirname, "../../excreet-vision-document-2026.pdf");

const doc = new PDFDocument({ size: "A4", margin: 0, autoFirstPage: false });
doc.pipe(fs.createWriteStream(OUT));

// ── Colours ──────────────────────────────────────────────────────────────────
const BG_DARK   = "#0c0115";
const GOLD      = "#C9A84C";
const PURPLE    = "#3D1060";
const PURPLE_LT = "#6B2FA0";
const TEXT      = "#1a1a2e";
const MUTED     = "#6B7A8D";
const OFF_WHITE = "#faf8ff";
const CARD_BDR  = "#D5C5E8";
const W         = 595.28;   // A4 width in points
const H         = 841.89;   // A4 height in points
const ML        = 57;       // margin left
const MR        = W - 57;   // margin right
const CW        = MR - ML;  // content width

// ── Helpers ──────────────────────────────────────────────────────────────────
function newPage(bg = "#ffffff") {
  doc.addPage();
  if (bg !== "#ffffff") {
    doc.rect(0, 0, W, H).fill(bg);
  }
}

function hRule(y, color = GOLD, opacity = 0.4) {
  doc.save().strokeColor(color).strokeOpacity(opacity)
     .lineWidth(0.5).moveTo(ML, y).lineTo(ML + CW * 0.6, y).stroke().restore();
}

function chapterLabel(y, text) {
  doc.fillColor(GOLD).fontSize(7).font("Helvetica-Bold")
     .text(text.toUpperCase(), ML, y, { characterSpacing: 2.5 });
  return y + 14;
}

function chapterTitle(y, title, subtitle) {
  doc.fillColor(TEXT).fontSize(24).font("Helvetica-Bold")
     .text(title, ML, y, { width: CW });
  y = doc.y + 3;
  doc.fillColor(MUTED).fontSize(10).font("Helvetica-Oblique")
     .text(subtitle, ML, y, { width: CW });
  y = doc.y + 8;
  hRule(y);
  return y + 10;
}

function sectionHead(y, text) {
  doc.fillColor(PURPLE).fontSize(6.5).font("Helvetica-Bold")
     .text(text.toUpperCase(), ML, y, { characterSpacing: 2, width: CW });
  return doc.y + 5;
}

function bodyText(y, text, indent = 0) {
  doc.fillColor(TEXT).fontSize(9.5).font("Helvetica")
     .text(text, ML + indent, y, { width: CW - indent, lineGap: 3 });
  return doc.y + 6;
}

function pullQuote(y, text) {
  const boxH = estimateTextHeight(text, 9.5, CW - 28) + 20;
  doc.save()
     .rect(ML, y, 3, boxH).fill(GOLD)
     .rect(ML + 3, y, CW - 3, boxH).fillColor(OFF_WHITE).fill()
     .restore();
  doc.fillColor(TEXT).fontSize(9.5).font("Helvetica-Oblique")
     .text(`"${text}"`, ML + 14, y + 9, { width: CW - 28, lineGap: 3 });
  return y + boxH + 8;
}

function bulletItem(y, text) {
  doc.circle(ML + 5, y + 5, 2).fill(GOLD);
  doc.fillColor(TEXT).fontSize(9.5).font("Helvetica")
     .text(text, ML + 14, y, { width: CW - 14, lineGap: 2 });
  return doc.y + 5;
}

function pageFooter(leftText, rightText) {
  const fy = H - 28;
  doc.save().strokeColor("#e8e0f0").strokeOpacity(1)
     .lineWidth(0.5).moveTo(ML, fy).lineTo(MR, fy).stroke().restore();
  doc.fillColor("#9a9a9a").fontSize(7).font("Helvetica")
     .text(leftText, ML, fy + 5, { width: CW - 40 })
     .text(rightText, ML, fy + 5, { width: CW, align: "right" });
}

function estimateTextHeight(text, size, width) {
  const charsPerLine = Math.floor(width / (size * 0.52));
  const lines = Math.ceil(text.length / charsPerLine);
  return lines * (size * 1.4);
}

function tierCard(x, y, w, name, price, period, features, featured = false) {
  const h = 110;
  doc.roundedRect(x, y, w, h, 6)
     .fillAndStroke(featured ? "#fffdf4" : OFF_WHITE, featured ? GOLD : CARD_BDR);
  doc.fillColor(PURPLE).fontSize(6.5).font("Helvetica-Bold")
     .text(name.toUpperCase(), x + 10, y + 10, { characterSpacing: 1.5, width: w - 20 });
  doc.fillColor(GOLD).fontSize(22).font("Helvetica-Bold")
     .text(price, x + 10, y + 22);
  doc.fillColor(MUTED).fontSize(7.5).font("Helvetica")
     .text(period, x + 10, y + 48);
  doc.fillColor(TEXT).fontSize(8).font("Helvetica")
     .text(features, x + 10, y + 62, { width: w - 20, lineGap: 2 });
}

const FOOTER_LEFT = "Excreet  ·  Vision, Mission & Product Philosophy  ·  Confidential  ·  May 2026";

// ════════════════════════════════════════════════════════════════════════════
// COVER
// ════════════════════════════════════════════════════════════════════════════
newPage(BG_DARK);

// Gold rule at top
doc.rect(ML, 48, CW, 0.5).fill(GOLD);

// Eyebrow
doc.fillColor(GOLD).fillOpacity(0.65).fontSize(7).font("Helvetica-Bold")
   .text("PRE-CLINICAL INTELLIGENCE PLATFORM", ML, 62, { characterSpacing: 3 });
doc.fillOpacity(1);

// Wordmark
doc.fillColor("#ffffff").fontSize(52).font("Helvetica-Bold")
   .text("EXCREET", ML, 82, { characterSpacing: 5 });

// Sub-tagline
doc.fillColor("#ffffff").fillOpacity(0.3).fontSize(8).font("Helvetica")
   .text("C L E A N S          C O M P L E T E", ML, 145, { characterSpacing: 4 });
doc.fillOpacity(1);

// Divider
doc.rect(ML, 172, CW, 0.5).fillColor(GOLD).fillOpacity(0.3).fill();
doc.fillOpacity(1);

// Subtitle
doc.fillColor("#ffffff").fillOpacity(0.55).fontSize(12).font("Helvetica")
   .text("Vision, Mission & Product Philosophy", ML, 182, { characterSpacing: 1 });
doc.fillOpacity(1);

// Quote
doc.fillColor("#ffffff").fillOpacity(0.72).fontSize(13).font("Helvetica-Oblique")
   .text('"Your body warns every day.\nExcreet translates that warning."', ML, 230, { width: 340, lineGap: 5 });
doc.fillOpacity(1);

// Cover footer
doc.rect(ML, H - 45, CW, 0.5).fillColor("#ffffff").fillOpacity(0.12).fill();
doc.fillOpacity(1);
doc.fillColor("#ffffff").fillOpacity(0.28).fontSize(7.5).font("Helvetica")
   .text("Confidential  ·  May 2026  ·  excreet.com", ML, H - 32);
doc.fillOpacity(1);

// ════════════════════════════════════════════════════════════════════════════
// TABLE OF CONTENTS
// ════════════════════════════════════════════════════════════════════════════
newPage();

doc.fillColor(GOLD).fontSize(7).font("Helvetica-Bold")
   .text("EXCREET  ·  VISION DOCUMENT", ML, 50, { characterSpacing: 2.5 });

doc.fillColor(TEXT).fontSize(9).font("Helvetica-Bold")
   .text("Contents", ML, 72, { characterSpacing: 0.5 });

const toc = [
  ["01", "The Problem — A Storm No One Warned You About", "3"],
  ["02", "The Insight — The Body Speaks Before Symptoms Do", "4"],
  ["03", "The Solution — Excreet Cleans Complete", "5"],
  ["04", "Product Architecture — Two Parallel Tracks", "6"],
  ["05", "The Body Score — What We Measure & Why", "7"],
  ["06", "Ministry of Healing — The AI Companion", "8"],
  ["07", "Membership Tiers & Affiliate Program", "9"],
  ["08", "The Population This Serves", "10"],
  ["09", "Brand Voice & Visual Language", "11"],
  ["10", "The Long-Term Vision", "12"],
  ["11", "Mission Statement", "13"],
];

let ty = 100;
for (const [num, title, pg] of toc) {
  doc.rect(ML, ty + 14, CW, 0.4).fillColor("#e8e0f0").fill();
  doc.fillColor(GOLD).fontSize(7.5).font("Helvetica-Bold")
     .text(num, ML, ty + 4);
  doc.fillColor(TEXT).fontSize(9).font("Helvetica")
     .text(title, ML + 18, ty + 4);
  doc.fillColor("#9a9a9a").fontSize(8).font("Helvetica")
     .text(pg, ML, ty + 4, { width: CW, align: "right" });
  ty += 22;
}

pageFooter(FOOTER_LEFT, "");

// ════════════════════════════════════════════════════════════════════════════
// CH01 — THE PROBLEM
// ════════════════════════════════════════════════════════════════════════════
newPage();
let y = 50;
y = chapterLabel(y, "Chapter 01");
y = chapterTitle(y, "The Problem", "A Storm No One Warned You About");

y = bodyText(y, "We live in the most toxically saturated era in human history. Environmental pollution, electromagnetic smog, agricultural chemicals — including glyphosate and pesticide residues classified as probable carcinogens by the International Agency for Research on Cancer — contaminated municipal water supplies, industrially processed food products, and the compounding effects of sedentary lifestyles, chronic sleep deprivation, dehydration, and toxic relational stress: these forces do not announce themselves. They accumulate silently.");

y = bodyText(y, "The insidious truth is that the body does not immediately protest. Long before fatigue, inflammation, pain, or diagnosed disease appears, the damage is underway at the cellular and biochemical level. pH begins to shift. Mitochondria lose voltage. The electron-rich environment that healthy cells require is quietly eroded. The storm gathers — but no alarm sounds.");

y = pullQuote(y, "By the time a doctor sees you, the storm has already broken. Excreet is the barking dog that warns you it's coming.");

y = sectionHead(y, "The Failure of Reactive Medicine");
y = bodyText(y, "Modern medicine, for all its brilliance, is architected to respond to symptoms — to arrive after the damage has been done. Pharmaceuticals manage. Surgery repairs. Radiation targets. But these are late-stage interventions layered onto a body that has been silently degrading, often for years. In many cases, the treatments themselves introduce new toxic burdens that the already-compromised body must then metabolize.");
y = bodyText(y, "The result is a system that is extraordinarily good at crisis management and extraordinarily poor at prevention. The gap is not one of medical incompetence — it is one of timing. Medicine arrives at the end of a story that began years earlier, in the dark, at the cellular level, where no one was watching.");

y = sectionHead(y, "What Has Been Missing");
y = bodyText(y, "What has been absent from the modern health landscape is a pre-diagnostic intelligence layer — a system that monitors the upstream signals, detects the early biochemical drift, and translates what the body is already communicating into language that a person can act on today, without requiring a clinical appointment, a lab order, or a diagnosis.");

pageFooter(FOOTER_LEFT, "Page 2 of 11");

// ════════════════════════════════════════════════════════════════════════════
// CH02 — THE INSIGHT
// ════════════════════════════════════════════════════════════════════════════
newPage();
y = 50;
y = chapterLabel(y, "Chapter 02");
y = chapterTitle(y, "The Insight", "The Body Speaks Before Symptoms Do");

y = bodyText(y, "There is a window — a pre-symptomatic phase — during which the body's internal chemistry is shifting but has not yet manifested as observable illness. This is the critical window that conventional medicine has no reliable mechanism to act within.");
y = bodyText(y, "The body's first signals are electrochemical: cellular voltage drops, oxidative burden rises, pH becomes progressively more acidic. These are not mysterious forces — they are measurable, trackable, and crucially, reversible — if caught early enough and addressed at the root rather than the symptom.");

y = pullQuote(y, "Excreet is not where you go when you're sick. It's how you ensure you never get there.");

y = sectionHead(y, "The Body Check Paradigm");
y = bodyText(y, "The Excreet system is built on a foundational insight: that daily observations of the body's most accessible signals — saliva chemistry, urine chemistry, and bowel function — collectively form a real-time biochemical fingerprint. Individually, each observation is a single data point. Aggregated over time, pattern-matched by AI, and cross-referenced against a member's personal history, they become a living early-warning system.");

y = sectionHead(y, "Pre-Clinical Intelligence");
y = bodyText(y, "The term \"pre-clinical\" is precise and intentional. Excreet operates entirely within the pre-clinical window — the space before clinical thresholds are crossed, before diagnostic criteria are met, before a physician would consider intervention warranted. This is not a medical device. It is an intelligence system. It does not diagnose. It does not prescribe. It listens, patterns, and warns.");
y = bodyText(y, "This distinction is the foundation of Excreet's legal positioning, its ethical framework, and its product design philosophy. The language is always interpretive, not prescriptive. The system speaks in patterns, trends, and correlations — never in clinical conclusions.");

pageFooter(FOOTER_LEFT, "Page 3 of 11");

// ════════════════════════════════════════════════════════════════════════════
// CH03 — THE SOLUTION
// ════════════════════════════════════════════════════════════════════════════
newPage();
y = 50;
y = chapterLabel(y, "Chapter 03");
y = chapterTitle(y, "The Solution", "Excreet Cleans Complete");

y = bodyText(y, "Excreet operates on two parallel tracks that together form a complete system. Neither track is sufficient alone. Together, they create a feedback loop that is both biochemically supportive and intelligently adaptive to each member's unique biology.");

y = pullQuote(y, "The supplement cleanses the body. The platform teaches it to speak. Together, they give the modern individual what medicine never has: a warning.");

y = sectionHead(y, "Track 1 — The Supplement: Cell Ready Minerals");
y = bodyText(y, "The Excreet supplement is formulated to donate high-density electrons to the body's cellular environment — directly addressing the electrochemical deficit at the root of cellular degradation. By restoring the electron-rich, alkaline-leaning conditions that healthy cells require, the supplement works upstream of symptoms: neutralizing oxidative stress, supporting mitochondrial energy production, and creating the biochemical conditions in which the body can perform its own elimination and repair functions completely.");
y = bodyText(y, "The supplement does not target a disease. It targets the environment in which disease cannot thrive. It is not a treatment — it is a restoration. Hence: Excreet Cleans Complete.");

y = sectionHead(y, "Track 2 — The Platform: Body Intelligence");
y = bodyText(y, "The Excreet member platform is the intelligence layer. Through periodic Body Check assessments, daily check-ins, and AI-powered pattern recognition, the platform generates a living Body Score — a dynamic index of cellular health trends over time. Members see not just where they are, but where they are heading, and why.");
y = bodyText(y, "The Ministry of Healing — Excreet's AI health companion — deepens this further. It is not a generic chatbot. It is a cellular health interpreter: able to contextualize a member's Body Score history, their environment, their lifestyle inputs, and their supplement protocol into personalized, actionable guidance.");

pageFooter(FOOTER_LEFT, "Page 4 of 11");

// ════════════════════════════════════════════════════════════════════════════
// CH04 — PRODUCT ARCHITECTURE
// ════════════════════════════════════════════════════════════════════════════
newPage();
y = 50;
y = chapterLabel(y, "Chapter 04");
y = chapterTitle(y, "Product Architecture", "Two Parallel Tracks — One Complete System");

y = sectionHead(y, "The Body Check");
y = bodyText(y, "The Body Check is the core interaction loop. Members complete a structured observation form capturing key biological signals accessible without laboratory equipment. Two formats:");
y = bulletItem(y, "Quick Body Check — a rapid daily check-in capturing the most time-sensitive signals");
y = bulletItem(y, "Full Body Check — the comprehensive periodic assessment that feeds the full AI scoring model");
y = bodyText(y, "Body Check data feeds directly into the Hermes AI processing pipeline, which scores the submission, generates a Body Score, and identifies pattern trends against the member's historical data.");

y = sectionHead(y, "The Hermes API Layer");
y = bodyText(y, "Hermes is the backend agent layer that sits between the WordPress member platform and the AI processing infrastructure. It is the intelligence engine of Excreet — receiving Body Check submissions, orchestrating AI analysis, persisting results, and serving them back to the member-facing experience. Hermes handles intake processing, job queue management, Body Score calculation, Ministry of Healing conversation context, clinical pattern reports, affiliate account management, and provider report generation. It is designed as a stateless, horizontally scalable API — decoupled from WordPress and independently deployable.");

y = sectionHead(y, "The Dashboard — Healing Command Center (HCC)");
y = bodyText(y, "The member dashboard presents the Body Score as a visual ring with delta indicators (▲ improvement / ▼ decline / → stable), a trend chart across recent submissions, clinical pattern summary, Ministry of Healing chat interface, provider report download, and affiliate earnings summary. It is the member's command center — the single place where all biological intelligence is visible, organized, and actionable.");

y = sectionHead(y, "Share With My Provider");
y = bodyText(y, "The Provider Report is a printable one-page triage primer designed for clinical settings. It presents the member's Body Score history, AI-identified health patterns, and key signals in language that bridges the Excreet intelligence layer with the clinical world — enabling a member to walk into any practitioner's office with a data-backed conversation starter.");

pageFooter(FOOTER_LEFT, "Page 5 of 11");

// ════════════════════════════════════════════════════════════════════════════
// CH05 — THE BODY SCORE
// ════════════════════════════════════════════════════════════════════════════
newPage();
y = 50;
y = chapterLabel(y, "Chapter 05");
y = chapterTitle(y, "The Body Score", "What We Measure & Why");

y = bodyText(y, "The Body Score is the primary output of the Excreet intelligence system — a single dynamic index that encapsulates the current state of a member's measurable cellular health signals. It is not a medical metric. It is a pattern-derived indicator: a translation of biochemical observations into a number that can be tracked, trended, and acted upon over time.");

y = sectionHead(y, "What Feeds the Score");
y = bulletItem(y, "Saliva pH — electrochemical proxy for systemic acid-alkaline balance");
y = bulletItem(y, "Urine pH — renal filtration efficiency and metabolic waste processing");
y = bulletItem(y, "Urine specific gravity — hydration status and kidney function indicator");
y = bulletItem(y, "Bowel observation data — elimination completeness, transit time, consistency");
y = bulletItem(y, "Daily check-in signals — energy level, cognitive clarity, sleep quality, physical sensation");
y = bulletItem(y, "Trend context — directional velocity relative to the member's personal baseline");
y += 4;

y = sectionHead(y, "How the Score Is Used");
y = bodyText(y, "The Body Score is never interpreted in isolation. Its power is in trajectory. A member with a score of 64 who has been trending upward for three weeks is in a fundamentally different position than a member with the same score who has been declining. The system always presents delta — the direction of movement — alongside the absolute value.");

y = pullQuote(y, "The score is not a grade. It is a compass. It tells you not where you are — but which way you are moving.");

y = sectionHead(y, "The Re-Baseline Protocol");
y = bodyText(y, "When a member completes a Full Body Check after a period of focused supplement use and lifestyle change, they have the option to formally re-baseline — updating their health reference point within the system. The Ministry of Healing acknowledges this milestone and recalibrates its pattern analysis against the new baseline. This is how the system evolves alongside the member: not a static record, but a living biological portrait that deepens over time.");

pageFooter(FOOTER_LEFT, "Page 6 of 11");

// ════════════════════════════════════════════════════════════════════════════
// CH06 — MINISTRY OF HEALING
// ════════════════════════════════════════════════════════════════════════════
newPage();
y = 50;
y = chapterLabel(y, "Chapter 06");
y = chapterTitle(y, "Ministry of Healing", "The AI Companion");

y = bodyText(y, "The Ministry of Healing is Excreet's AI-powered health intelligence companion. It is the conversational interface through which a member engages with their biological data — asking questions, receiving interpretations, exploring patterns, and receiving personalized guidance anchored in their own history.");

y = sectionHead(y, "What Makes It Different");
y = bodyText(y, "Most AI health tools operate as generic Q&A interfaces — they answer questions about health topics, but they know nothing about you. The Ministry of Healing is different. Every conversation is anchored in the member's actual data: their Body Score history, their submitted observations, their supplement protocol, their prior conversations. It is not answering questions about health in general. It is answering questions about your health specifically.");

y = bulletItem(y, "Contextualizes responses against the member's Body Score history and trends");
y = bulletItem(y, "Remembers prior session context — conversations build, not reset");
y = bulletItem(y, "Identifies patterns the member may not have noticed across weeks of data");
y = bulletItem(y, "Translates AI findings into plain language — no clinical jargon");
y = bulletItem(y, "Generates personalized healing protocols based on accumulated intelligence");
y += 4;

y = sectionHead(y, "Session Limits & Tiers");
y = bodyText(y, "Ministry of Healing sessions are allocated per membership tier to ensure AI quality and system sustainability:");
y += 4;

const cardW = (CW - 10) / 2;
tierCard(ML,              y, cardW, "Starter",
  "10", "Ministry sessions / month",
  "Full conversation history saved\nPatterns identified across sessions\nPersonal healing protocol access");
tierCard(ML + cardW + 10, y, cardW, "Premium",
  "20", "Ministry sessions / month",
  "Everything in Starter\nPriority pattern analysis\nHealing protocol generation\nEarly access to new features",
  true);
y += 120;

y = bodyText(y, "A standalone $29 Protocol Session add-on is available for members who want a single deep-dive personalized healing protocol outside their monthly allocation — valid for 30 days.");

pageFooter(FOOTER_LEFT, "Page 7 of 11");

// ════════════════════════════════════════════════════════════════════════════
// CH07 — MEMBERSHIP & AFFILIATE
// ════════════════════════════════════════════════════════════════════════════
newPage();
y = 50;
y = chapterLabel(y, "Chapter 07");
y = chapterTitle(y, "Membership Tiers & Affiliate Program", "Two Ways to Begin — Both Change the Story");

y = sectionHead(y, "Membership Structure");
y = bodyText(y, "Every Excreet membership gives the member access to the Body Check intelligence platform — and automatically enrolls them as an affiliate from day one. The tier a member chooses determines the depth of AI guidance they receive and the size of their affiliate earnings.");
y += 4;

tierCard(ML,              y, cardW, "Starter",
  "$15", "per month  ·  billed monthly",
  "10 Ministry sessions/month\n$5 per referred member/month\nAuto-enrolled as affiliate from Day 1");
tierCard(ML + cardW + 10, y, cardW, "Premium",
  "$25", "per month  ·  billed monthly",
  "20 Ministry sessions/month\n$10 per referred member/month\nAuto-enrolled as affiliate from Day 1",
  true);
y += 124;

y = sectionHead(y, "Affiliate Program — Canonical Rules");
y = bulletItem(y, "Both tiers — Starter and Premium — include full affiliate status from Day 1. This is not a Premium-only benefit.");
y = bulletItem(y, "Starter members earn $5 per referred active member per month.");
y = bulletItem(y, "Premium members earn $10 per referred active member per month.");
y = bulletItem(y, "Payouts require: (1) referring member's own membership is active and current; (2) minimum $50 accumulated balance; (3) disbursements processed every 2 weeks.");
y = bulletItem(y, "Refer 3 active members as a Starter — your membership pays for itself. At Premium, 3 referrals cover the membership with $5 surplus monthly.");

pageFooter(FOOTER_LEFT, "Page 8 of 11");

// ════════════════════════════════════════════════════════════════════════════
// CH08 — THE POPULATION
// ════════════════════════════════════════════════════════════════════════════
newPage();
y = 50;
y = chapterLabel(y, "Chapter 08");
y = chapterTitle(y, "The Population This Serves", "The Intelligent Adult Who Senses Something Is Wrong");

y = bodyText(y, "Excreet is designed for the intelligent adult who senses that something is quietly wrong — who feels the gap between how they know they should feel and how they actually feel — but who has been failed by a medical system that only speaks the language of diagnosis.");

y = sectionHead(y, "Who This Is");
y = bulletItem(y, "The parent who reads every label, filters the water, buys organic — and still feels like they're losing ground");
y = bulletItem(y, "The professional who notices the slow cognitive fog settling in — sharper once, not sure when it changed");
y = bulletItem(y, "The athlete who feels the diminishing returns — the recovery taking longer, the performance ceiling dropping");
y = bulletItem(y, "The person who watched someone they love receive a late-stage diagnosis and has decided their own story will be written differently");
y = bulletItem(y, "The health-aware individual who has tried supplements, protocols, and programs but lacked the feedback loop to know what was actually working");
y += 4;

y = pullQuote(y, "These are not passive patients. They are active participants in their own biology — they simply need the right instrument to hear what their body is already trying to say.");

y = sectionHead(y, "What This Person Wants");
y = bulletItem(y, "Not a diagnosis — a direction. Not a treatment — a trajectory. Not a prescription — a pattern.");
y = bulletItem(y, "They want to understand their own data. They want to see the trend. They want to know if what they're doing is working.");
y = bulletItem(y, "They want to walk into their doctor's office with something — a number, a chart, a report — not just a feeling.");
y = bulletItem(y, "They want the warning early enough that they can act on it.");

pageFooter(FOOTER_LEFT, "Page 9 of 11");

// ════════════════════════════════════════════════════════════════════════════
// CH09 — BRAND VOICE
// ════════════════════════════════════════════════════════════════════════════
newPage();
y = 50;
y = chapterLabel(y, "Chapter 09");
y = chapterTitle(y, "Brand Voice & Visual Language", "Storm. Gold. Cellular Intelligence.");

y = sectionHead(y, "Tone of Voice");
y = bodyText(y, "Excreet speaks with the quiet authority of a system that knows something most people don't — and is choosing to share it. The voice is calm, precise, and deeply respectful of the member's intelligence. It never sensationalizes. It never trivializes. It treats the body as the sophisticated, self-repairing system it actually is.");
y = bulletItem(y, "Authoritative but never clinical");
y = bulletItem(y, "Urgent but never alarmist");
y = bulletItem(y, "Scientific but never inaccessible");
y = bulletItem(y, "Warm but never sentimental");
y += 4;

y = sectionHead(y, "Visual Language");
y = bodyText(y, "The Excreet visual world is built around three interconnected metaphors:");
y = bulletItem(y, "The Storm — dark atmospheric imagery representing the invisible cellular forces accumulating before symptoms. God-rays breaking through storm clouds represent the moment of detection — light reaching through darkness.");
y = bulletItem(y, "Cellular Architecture — molecular silhouettes, biological floating structures, the suggestion of microscopic intelligence visible to those who look closely.");
y = bulletItem(y, "Gold as Signal — gold is used sparingly and intentionally. It marks what matters: the wordmark, the Body Score indicator, key data points, CTAs. It is the color of the warning light, the alert, the intelligence surfacing.");
y += 4;

y = sectionHead(y, "Key Brand Phrases");
y = bulletItem(y, '"Your body warns every day. Excreet translates that warning."');
y = bulletItem(y, '"The storm builds long before you feel the first drop."');
y = bulletItem(y, '"By the time a doctor sees you, the storm has already broken."');

pageFooter(FOOTER_LEFT, "Page 10 of 11");

// ════════════════════════════════════════════════════════════════════════════
// CH10 — LONG-TERM VISION
// ════════════════════════════════════════════════════════════════════════════
newPage();
y = 50;
y = chapterLabel(y, "Chapter 10");
y = chapterTitle(y, "The Long-Term Vision", "The Standard of Pre-Clinical Intelligence");

y = sectionHead(y, "Near Term");
y = bodyText(y, "The platform is live. The full member journey — from homepage through intake, Body Check, Ministry of Healing, and Provider Report — is operational. Membership checkout is active via Paid Memberships Pro with Stripe. Every public-facing page reflects the visual and philosophical standard the brand demands: the Botanical Healing palette on member onboarding pages, the Dark Precision palette on intelligence and data pages.");
y = bodyText(y, "The immediate priorities are growing the founding member base, completing the affiliate network activation, and ensuring that every member who joins experiences the full intelligence loop — from first Body Check submission through to their first Ministry conversation and first Body Score trend visible on their dashboard.");

y = sectionHead(y, "Medium Term");
y = bodyText(y, "As the member base grows, the Body Score dataset becomes increasingly powerful. Aggregate anonymized data begins to reveal population-level biochemical patterns — correlations between environmental factors, lifestyle inputs, and cellular health trajectories that no individual dataset could surface alone.");
y = bodyText(y, "The affiliate network compounds. Members who experience results share the platform. The word-of-mouth vector is not marketing — it is the natural behavior of people who have found something that works and who earn real income for sharing it.");

y = sectionHead(y, "Long Term");
y = bodyText(y, "Excreet aspires to be the standard of pre-clinical health monitoring for the modern world: a platform where the Body Score becomes as routine as a blood pressure reading, where AI-interpreted body signal trends can be shared directly with forward-thinking clinicians, and where the supplement protocol evolves alongside each member's biological data.");

y = pullQuote(y, "The moat Excreet builds is not chemical — it is informational and relational. A member who has two years of Body Score history, Ministry of Healing conversation context, and a personalized supplement response profile does not simply leave. They have, in effect, a continuous biological record that becomes more valuable over time. Excreet becomes part of their health identity.");

pageFooter(FOOTER_LEFT, "Page 11 of 11");

// ════════════════════════════════════════════════════════════════════════════
// MISSION PAGE
// ════════════════════════════════════════════════════════════════════════════
newPage(BG_DARK);

// Chapter label
doc.fillColor(GOLD).fillOpacity(0.65).fontSize(7).font("Helvetica-Bold")
   .text("CHAPTER 11  ·  MISSION STATEMENT", ML, 120, { characterSpacing: 2.5, align: "center", width: CW });
doc.fillOpacity(1);

// Title
doc.fillColor("#ffffff").fillOpacity(0.5).fontSize(13).font("Helvetica")
   .text("The Reason Excreet Exists", ML, 154, { align: "center", width: CW, characterSpacing: 0.5 });
doc.fillOpacity(1);

// Mission text
doc.fillColor("#ffffff").fontSize(16).font("Helvetica")
   .text(
     "Excreet exists to give every person a fighting\nchance against the invisible forces eroding their\nhealth — to stand between modern civilization's\ntoxic tide and the human body, and to sound the\nalarm early enough that it still means something.",
     ML, 210, { align: "center", width: CW, lineGap: 6 }
   );

// Wordmark
doc.fillColor("#ffffff").fontSize(30).font("Helvetica-Bold")
   .text("EXCREET", ML, 390, { align: "center", width: CW, characterSpacing: 8 });

// Tagline
doc.fillColor("#ffffff").fillOpacity(0.28).fontSize(7.5).font("Helvetica")
   .text("C  L  E  A  N  S          C  O  M  P  L  E  T  E", ML, 432, { align: "center", width: CW, characterSpacing: 3 });
doc.fillOpacity(1);

// Gold line
doc.rect(ML + CW * 0.3, 460, CW * 0.4, 0.5).fill(GOLD);

// Footer
doc.fillColor("#ffffff").fillOpacity(0.25).fontSize(7).font("Helvetica")
   .text("Confidential  ·  May 2026  ·  excreet.com", ML, H - 42, { align: "center", width: CW });
doc.fillOpacity(1);

// ── Finalise ─────────────────────────────────────────────────────────────────
doc.end();
doc.on("end", () => {
  const kb = Math.round(fs.statSync(OUT).size / 1024);
  console.log(`✅  PDF written → ${OUT}  (${kb} KB)`);
});
