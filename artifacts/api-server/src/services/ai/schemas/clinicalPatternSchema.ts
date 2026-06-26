import { z } from "zod/v4";

/**
 * Clinical Pattern Report schema — v1
 *
 * Produced by the pharmaceutical_intake workflow.
 * Models the full Clinical Pattern Report: prescribed pharmaceuticals,
 * drug interaction loops, lab marker triggers, red flag summary,
 * observable signals, and Excreet interpretation.
 *
 * Mirrors the visual design of the branded Clinical Pattern Report PDF.
 */

const stringOrArray = z
  .union([z.array(z.string()), z.string()])
  .transform((v) => (Array.isArray(v) ? v : [v]));

// ─── Member Profile ───────────────────────────────────────────────────────────

const MemberProfileSchema = z.object({
  age: z.string(),
  sex: z.string(),
  exposureDuration: z.string(),
  assessmentDate: z.string(),
});

// ─── Prescribed Pharmaceuticals ───────────────────────────────────────────────

const PharmaceuticalSchema = z.object({
  name: z.string(),
  dosage: z.string(),
  frequency: z.string(),
});

// ─── Red Flag Summary ─────────────────────────────────────────────────────────

const RedFlagLevelSchema = z.enum([
  "HIGH_RISK",
  "MODERATE_RISK",
  "AWARENESS",
]);

const RedFlagSchema = z.object({
  level: RedFlagLevelSchema,
  title: z.string(),
  description: z.string(),
});

// ─── Drug Interaction Loops ───────────────────────────────────────────────────

const InteractionLoopSchema = z.object({
  name: z.string(),
  medications: stringOrArray,
  mechanism: z.string(),
  effects: stringOrArray,
  severity: z.enum(["HIGH", "MODERATE", "LOW"]),
});

// ─── Lab Marker Triggers ──────────────────────────────────────────────────────

const LabMarkerSchema = z.object({
  riskArea: z.string(),
  labMarker: z.string(),
  whatItIndicates: z.string(),
  targetAlertLevel: z.string(),
  action: z.enum(["Alert", "Monitor", "Optimize"]),
});

// ─── Root Schema ──────────────────────────────────────────────────────────────

export const ClinicalPatternReportSchema = z.object({
  memberProfile: MemberProfileSchema,
  prescribedPharmaceuticals: z.array(PharmaceuticalSchema),
  redFlagSummary: z.array(RedFlagSchema),
  drugInteractionLoops: z.array(InteractionLoopSchema),
  labMarkerTriggers: z.array(LabMarkerSchema),
  expectedObservableSignals: stringOrArray,
  excreetInterpretation: z.string(),
  recommendationSummary: z.string(),
  excreetPrinciple: z.string(),
  disclaimer: z.string(),
});

export type ClinicalPatternReport = z.infer<typeof ClinicalPatternReportSchema>;
export type RedFlagLevel = z.infer<typeof RedFlagLevelSchema>;
export type Pharmaceutical = z.infer<typeof PharmaceuticalSchema>;
export type LabMarker = z.infer<typeof LabMarkerSchema>;
export type InteractionLoop = z.infer<typeof InteractionLoopSchema>;
export type RedFlag = z.infer<typeof RedFlagSchema>;
