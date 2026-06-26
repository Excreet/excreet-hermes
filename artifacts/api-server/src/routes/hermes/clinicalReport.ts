import { Router } from "express";
import { db, hermesJobsTable } from "@workspace/db";
import { eq, and, desc } from "drizzle-orm";
import type { HealthIntakeResult } from "../../services/ai/schemas/healthIntakeSchema.js";

const router = Router();

/**
 * GET /api/hermes/report/clinical-summary/:memberId
 *
 * Phase 16 — Doctor Visit Summary
 *
 * Fetches the most recent completed health_intake job for a member.
 * Returns structured clinical data formatted for a printable doctor visit summary.
 * Only meaningful for "protocol" or "alarm" tier results (vitalityScore ≤ ~50).
 */
router.get("/report/clinical-summary/:memberId", async (req, res) => {
  const { memberId } = req.params;

  if (!memberId || typeof memberId !== "string") {
    return res.status(400).json({ error: "memberId is required." });
  }

  const [row] = await db
    .select()
    .from(hermesJobsTable)
    .where(
      and(
        eq(hermesJobsTable.memberId, memberId),
        eq(hermesJobsTable.workflowType, "health_intake"),
        eq(hermesJobsTable.status, "completed"),
      ),
    )
    .orderBy(desc(hermesJobsTable.createdAt))
    .limit(1);

  if (!row) {
    return res
      .status(404)
      .json({ error: "No completed health intake found for this member." });
  }

  const result = row.result as HealthIntakeResult | null;

  if (!result || !result.tier) {
    return res
      .status(404)
      .json({ error: "Job result is missing or malformed." });
  }

  const FLAGGED_TIERS = ["protocol", "alarm"] as const;
  const isFlagged = (FLAGGED_TIERS as readonly string[]).includes(result.tier);

  return res.json({
    memberId,
    jobId: row.jobId,
    generatedAt: new Date().toISOString(),
    submittedAt: row.createdAt.toISOString(),
    tier: result.tier,
    vitalityScore: result.vitalityScore,
    isFlagged,
    trajectoryRead: result.trajectoryRead,
    medicalPath: result.medicalPath ?? null,
    ministryPath: result.ministryPath ?? null,
    disclaimer: result.disclaimer,
  });
});

export default router;
