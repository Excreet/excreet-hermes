import { Router, type IRouter } from "express";
import { getJob } from "../../lib/jobStore.js";

const router: IRouter = Router();

/**
 * GET /api/hermes/result/:jobId
 *
 * PUBLIC endpoint — no API key required.
 * jobId (UUID v4) acts as an opaque access token (128-bit entropy).
 *
 * Returns only safe frontend-facing fields.
 * Never exposes: memberId, payload, workflowType, timestamps, raw errors.
 */
router.get("/result/:jobId", async (req, res) => {
  const { jobId } = req.params;

  const UUID_RE =
    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

  if (!jobId || !UUID_RE.test(jobId)) {
    res.status(400).json({ status: "not_found" });
    return;
  }

  const job = await getJob(jobId);

  if (!job) {
    res.status(404).json({ status: "not_found" });
    return;
  }

  switch (job.status) {
    case "pending":
      res.json({ status: "pending" });
      return;

    case "processing":
      res.json({ status: "processing" });
      return;

    case "completed": {
      const r = job.result as Record<string, unknown> | null;
      if (r && (
        typeof r["tier"] === "string" ||              // health_intake schema (v2)
        r["memberProfile"] !== undefined ||            // pharmaceutical clinical schema
        r["prescribedPharmaceuticals"] !== undefined ||
        r["drugInteractionLoops"] !== undefined
      )) {
        res.json({ status: "completed", result: r });
        return;
      }
      res.json({ status: "failed", error: "Processing could not be completed." });
      return;
    }

    case "failed":
      res.json({ status: "failed", error: job.error ?? "Processing could not be completed." });
      return;

    default:
      res.json({ status: "not_found" });
  }
});

export default router;
