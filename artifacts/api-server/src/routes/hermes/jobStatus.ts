import { Router, type IRouter } from "express";
import { getJob } from "../../lib/jobStore.js";

const router: IRouter = Router();

/**
 * GET /api/hermes/job-status/:jobId
 *
 * WordPress polls this endpoint after submitting to /intake.
 * Requires API key (applied in index.ts via requireApiKey).
 */
router.get("/job-status/:jobId", async (req, res) => {
  const { jobId } = req.params;

  if (!jobId) {
    res.status(400).json({
      error:   "bad_request",
      message: "jobId path parameter is required.",
    });
    return;
  }

  const job = await getJob(jobId);

  if (!job) {
    req.log.warn({ jobId }, "Job not found");
    res.status(404).json({
      error:   "not_found",
      message: `No job found with id: ${jobId}`,
    });
    return;
  }

  res.json({
    jobId:        job.jobId,
    status:       job.status,
    workflowType: job.workflowType,
    result:       job.result,
    error:        job.error,
    createdAt:    job.createdAt,
    updatedAt:    job.updatedAt,
  });
});

export default router;
