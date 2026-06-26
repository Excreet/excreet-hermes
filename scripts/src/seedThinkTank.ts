/**
 * seedThinkTank.ts
 *
 * Pre-loads the Excreet Think Tank knowledge base with founding articles.
 * Safe to re-run — uses upsert (no duplicates).
 *
 * Run with:
 *   pnpm --filter @workspace/scripts run seed:think-tank
 */

import { db } from "@workspace/db";
import { thinkTankTable } from "@workspace/db";

const articles: (typeof thinkTankTable.$inferInsert)[] = [
  {
    id:            "tt-001-cellular-voltage-fatigue",
    title:         "Fatigue Is Not a Caffeine Deficiency",
    summary:
      "Every cell runs on an electrochemical voltage of -70 to -90 millivolts maintained by ion pumps that require ionic minerals. Modern mineral depletion causes voltage drop → mitochondria default to glycolysis (Warburg effect) → oxygen cannot be used → CO2 cannot clear → lactic acid accumulates → excess mucus forms as a pH buffer. The complete chain: mineral deficiency → pH depression → voltage drop → mitochondrial impairment → accelerated cellular aging. Reversible with ionic minerals, cleared elimination pathways, and restored cellular electrochemistry.",
    content: `FATIGUE IS NOT A CAFFEINE DEFICIENCY

A voltage deficiency is a mineral problem — hence a systemic pre-aging pandemic.

Every cell in your body maintains an electrochemical charge across its membrane — a voltage. In a healthy, well-functioning cell, that membrane potential sits at approximately -70 to -90 millivolts. This charge drives energy production, nutrient absorption, waste elimination, cellular communication, and repair.

This voltage is maintained by ion pumps — most critically the sodium-potassium ATPase pump — that continuously move minerals across the cell membrane to sustain the gradient. These pumps require ionic minerals to function.

Modern food systems, depleted soils, processed diets, and chronic environmental exposures have stripped these minerals from daily intake at a population level. The pumps are running dry. When the pumps run dry, voltage drops.

THE VOLTAGE-pH RELATIONSHIP
The Nernst equation quantifies it precisely: for every one unit change in pH, there is approximately a 59-61 millivolt shift in membrane potential. As cellular acidity increases, voltage falls. The mitochondria operate on their own electrochemical gradient at -120 to -180 millivolts. When the cellular environment drops in voltage, mitochondrial potential drops with it.

Unable to run proper oxidative metabolism, cells default to glycolysis — an older, less efficient energy pathway that produces lactic acid instead of CO2 and water. Lactic acid further acidifies the cellular environment. This is the Warburg effect — documented by Nobel Prize winner Otto Warburg in the 1930s.

CO2 CLEARANCE FAILURE
Carbon dioxide is transported out of cells via the carbonic anhydrase enzyme system. This conversion is pH-sensitive. In an already acidic cellular environment, CO2 cannot be efficiently converted for transport. It accumulates intracellularly, further acidifying the environment.

THE MUCUS RESPONSE
Goblet cells produce bicarbonate-rich mucus to coat and protect underlying tissue from acidic environments. As cellular pH drops, the body produces more mucus. This is why chronically mineral-deficient individuals present with persistent post-nasal drip, chronic throat clearing, excess morning phlegm, and congestion with no infection present. These are voltage conditions wearing a respiratory mask.

THE COMPLETE CHAIN
Mineral deficiency → pH depression → voltage drop → mitochondrial impairment → oxygen cannot be utilized → CO2 cannot be cleared → lactic acid accumulates → deeper acidification → excess mucus production → accelerated cellular aging.

Every step is reversible with ionic minerals in bioavailable form, cleared elimination pathways, and restored cellular electrochemistry.

RESTORATION PRINCIPLE
Restoring cellular voltage is not a one-size prescription. Each person carries a different systemic hostile environment. The greater that hostile environment, the more dramatically the body will respond when genuine cellular cleansing begins. Begin conservatively, observe the body's response, and raise intake according to the depth of personal systemic burden.

THE MORNING WINDOW
During sleep, the body produces mucus as a buffering response to the day's accumulated acid load. By morning, the burden is at its most concentrated and most visible. The morning is the most powerful moment for cellular restoration — addressing voltage, minerals, and elimination first thing allows the body to operate from a position of recovery rather than perpetual catch-up.`,
    category:      "article",
    tags:          ["cellular voltage", "fatigue", "minerals", "Warburg effect", "mitochondria", "pH", "mucus", "ionic minerals", "pre-aging"],
    sourceUrl:     "https://www.linkedin.com/pulse/fatigue-caffeine-deficiency-excreet-naturopathy-uikrc/",
    author:        "Odis Cherrington, Naturopath — Founder, Excreet Health Platform",
    publishedDate: "2026-06-18",
  },
  {
    id:            "tt-002-pesticides-breast-cancer",
    title:         "Breast Cancer and Pesticides — Michigan State University County-Level Study (2026)",
    summary:
      "A Michigan State University study of 2,457 US counties found modest but statistically significant positive associations between pesticide use and breast cancer incidence in rural counties, with a 6% higher incidence in the highest-use tertile. Neonicotinoids and phosphonates (including glyphosate) showed the strongest associations. Glyphosate mimics estrogen (17β-estradiol), altering estrogen receptor α binding and driving cancer cell proliferation. The Excreet connection: glyphosate is a mineral chelator — it depletes the ionic minerals that power cellular voltage, creating the exact low-voltage, high-acid environment where cancer cells thrive. The 5-15 year latency between exposure and diagnosis is the pre-clinical window where Excreet monitoring operates.",
    content: `BREAST CANCER AND PESTICIDES — MICHIGAN STATE UNIVERSITY STUDY (2026)
Published in Cancer Causes & Control. Originally reported by Beyond Pesticides / Children's Health Defense, June 18, 2026.

METHODOLOGY
Study: 2,457 US counties, USGS Pesticide National Synthesis Project data, cumulative average pesticide use 2001-2015.
38 pesticides across 8 chemical classes: carbamates, neonicotinoids, organochlorines, organophosphates, phosphonates (glyphosate, glufosinate), pyrethroids, triazines, miscellaneous.
Breast cancer incidence: National Cancer Institute State Cancer Profiles, 2016-2020.
Latency period: 5-15 years built into methodology.
Confounders controlled: smoking, unemployment, residential mobility, poverty, education.

KEY FINDINGS
- Rural counties: 2% higher breast cancer incidence overall (adjusted rate ratio 1.02)
- Highest pesticide use tertile: 6% higher breast cancer incidence (adjusted rate ratio 1.06)
- Neonicotinoids and phosphonates (glyphosate/glufosinate): statistically significant positive association after confounding adjustment
- Individual pesticides: thiamethoxam (neonicotinoid) and chlorpyrifos (organophosphate) show statistically significant relationships
- Trend: neonicotinoid, phosphonate, and pyrethroid use increasing 2001-2015

GLYPHOSATE MECHANISM
Glyphosate-based herbicides at high concentrations mimic estrogen (17β-estradiol), altering binding activity to estrogen receptor α sites, causing fundamental changes in breast cancer cell proliferation. Source: Chemosphere.

EXCREET RELEVANCE
Glyphosate is a mineral chelator — it binds to and strips ionic minerals from the body. Mineral depletion leads to voltage drop, which creates the Warburg-effect cellular environment where cancer cells thrive. The 5-15 year latency period between pesticide exposure and cancer diagnosis is exactly the pre-clinical window that Excreet monitoring is designed to occupy. Rural agricultural communities — the most affected population — have the least access to sophisticated healthcare, making mobile daily cellular health monitoring directly relevant.

ADDITIONAL SUPPORTING RESEARCH
- PLOS One (Brazil): Women with occupational pesticide exposure had higher prevalence (32.83%) of more aggressive Luminal B breast cancer subtype, higher disease recurrence, and chemoresistance.
- Immunopharmacology and Immunotoxicology: Occupational pesticide exposure modifies clinical presentation of breast cancer, affecting cytokine production.
- Ecotoxicology and Environmental Safety: Atrazine promotes breast cancer development through suppression of immune cell stimulation and upregulation of tumor-promoting enzymes.
- Science of the Total Environment (May 2024): 10 of 11 population-based studies reported at least one significant association between pesticide exposure and breast cancer risk.`,
    category:      "research",
    tags:          ["pesticides", "breast cancer", "glyphosate", "neonicotinoids", "endocrine disruption", "mineral depletion", "cellular voltage", "rural health", "women's health", "pre-clinical"],
    sourceUrl:     "https://childrenshealthdefense.org/defender/breast-cancer-pesticides-study/",
    author:        "Michigan State University / Beyond Pesticides",
    publishedDate: "2026-06-18",
  },
];

async function seed() {
  console.log("Seeding Think Tank knowledge base...");

  for (const article of articles) {
    await db
      .insert(thinkTankTable)
      .values(article)
      .onConflictDoUpdate({
        target: thinkTankTable.id,
        set: {
          title:         article.title,
          summary:       article.summary,
          content:       article.content,
          category:      article.category,
          tags:          article.tags,
          sourceUrl:     article.sourceUrl,
          author:        article.author,
          publishedDate: article.publishedDate,
          updatedAt:     new Date(),
        },
      });
    console.log(`  ✓ ${article.id}: ${article.title}`);
  }

  console.log(`\nThink Tank seeded with ${articles.length} founding articles.`);
  process.exit(0);
}

seed().catch((err) => {
  console.error("Seed failed:", err);
  process.exit(1);
});
