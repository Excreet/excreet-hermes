import { z } from "zod/v4";

/**
 * FRONTEND CONTRACT — DO NOT RENAME FIELDS WITHOUT A MIGRATION PLAN
 *
 * v2 Excreet schema — replaces the original 4-field summary/signals/cautions/suggestions.
 * Tier-based triage model reflecting the Excreet vision:
 *   nudge    → acute, self-resolvable signal (e.g. dehydration)
 *   checkin  → persistent mild signal, Ministry of Healing check-in warranted
 *   protocol → systemic pattern, full Ministry of Healing protocol recommended
 *   alarm    → warrants both medical navigation AND healing support
 */

// Coerce string → single-element array so the schema tolerates Claude
// returning a prose paragraph instead of a JSON array.
const stringOrArray = z
  .union([z.array(z.string()), z.string()])
  .transform((v) => (Array.isArray(v) ? v : [v]));

const MedicalPathSchema = z.object({
  questionsToAsk: stringOrArray,
  labTestsToRequest: stringOrArray,
  redFlagsToWatch: stringOrArray,
});

const MinistryPathSchema = z.object({
  signalCategory: z.string(),
  approach: stringOrArray,
  powerMoves: stringOrArray,
});

export const HealthIntakeResultSchema = z.object({
  tier: z.enum(["nudge", "checkin", "protocol", "alarm"]),
  vitalityScore: z.number().int().min(0).max(100),
  trajectoryRead: z.string(),
  quickActions: z.array(z.string()),
  medicalPath: MedicalPathSchema.nullable(),
  ministryPath: MinistryPathSchema.nullable(),
  disclaimer: z.string(),
});

export type HealthIntakeResult = z.infer<typeof HealthIntakeResultSchema>;
export type MedicalPath = z.infer<typeof MedicalPathSchema>;
export type MinistryPath = z.infer<typeof MinistryPathSchema>;
