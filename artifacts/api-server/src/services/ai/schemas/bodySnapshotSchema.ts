import { z } from "zod/v4";

export const WellbeingAnalysisSchema = z.object({
  energySummary:   z.string(),
  moodCorrelation: z.string(),
  symptomPattern:  z.string(),
});

export const UrineAnalysisSchema = z.object({
  colorObservation:    z.string(),
  clarityObservation:  z.string(),
  odorAssessment:      z.string(),
  stripReadingSummary: z.string(),
  hydrationStatus:     z.string(),
});

export const BowelAnalysisSchema = z.object({
  formObservation:  z.string(),
  colorObservation: z.string(),
  patternInsight:   z.string(),
});

export const SalivaAnalysisSchema = z.object({
  phLevel:          z.string(),
  stripObservation: z.string(),
  interpretation:   z.string(),
});

export const BodySnapshotResultSchema = z.object({
  snapshotDate:         z.string(),
  bodyScore:            z.number().int().min(0).max(100),
  tier:                 z.enum(["nudge", "checkin", "protocol", "alarm"]),
  wellbeingAnalysis:    WellbeingAnalysisSchema,
  urineAnalysis:        UrineAnalysisSchema,
  bowelAnalysis:        BowelAnalysisSchema,
  salivaAnalysis:       SalivaAnalysisSchema,
  hydrationInsight:     z.string(),
  environmentalContext: z.string(),
  trajectoryRead:       z.string(),
  quickActions:         z.array(z.string()),
  disclaimer:           z.string(),
});

export type BodySnapshotResult  = z.infer<typeof BodySnapshotResultSchema>;
export type WellbeingAnalysis   = z.infer<typeof WellbeingAnalysisSchema>;
export type UrineAnalysis       = z.infer<typeof UrineAnalysisSchema>;
export type BowelAnalysis       = z.infer<typeof BowelAnalysisSchema>;
export type SalivaAnalysis      = z.infer<typeof SalivaAnalysisSchema>;
