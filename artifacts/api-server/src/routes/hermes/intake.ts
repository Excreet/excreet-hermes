import { Router, type IRouter } from "express";
import { z } from "zod/v4";
import { createJob, getActiveJobForMember } from "../../lib/jobStore.js";
import { processJob } from "../../services/pipeline.js";

const router: IRouter = Router();

const IntakeSchema = z.object({
  member_id:     z.string().min(1),
  workflow_type: z.string().min(1),
  payload:       z.record(z.string(), z.unknown()),
});

/**
 * POST /api/hermes/intake
 *
 * Entry point for all WordPress → Hermes submissions.
 * Validates the request, creates a job in PostgreSQL, fires AI pipeline async,
 * and returns the jobId immediately (202 Accepted).
 */
router.post("/intake", async (req, res) => {
  const parsed = IntakeSchema.safeParse(req.body);

  if (!parsed.success) {
    req.log.warn({ issues: parsed.error.issues }, "Invalid intake request");
    res.status(400).json({
      error:   "validation_error",
      message: "Request body is invalid.",
      issues:  parsed.error.issues,
    });
    return;
  }

  const { member_id, workflow_type, payload } = parsed.data;

  // Idempotency: if a job for this member+workflow is already running,
  // return the existing job instead of spawning a duplicate pipeline.
  const activeJob = await getActiveJobForMember(member_id, workflow_type);
  if (activeJob) {
    req.log.info(
      { jobId: activeJob.jobId, member_id, workflow_type },
      "Intake deduplicated — active job already exists",
    );
    res.status(202).json({
      jobId:   activeJob.jobId,
      status:  activeJob.status,
      message: "Job already in progress. Poll /api/hermes/result/:jobId for your result.",
    });
    return;
  }

  const job = await createJob(member_id, workflow_type, payload);

  req.log.info(
    { jobId: job.jobId, member_id, workflow_type },
    "Intake accepted — AI pipeline starting",
  );

  // Fire AI pipeline async — do not await, respond immediately (202)
  processJob(job).catch((err: unknown) => {
    req.log.error({ jobId: job.jobId, err }, "Unhandled error in AI pipeline");
  });

  res.status(202).json({
    jobId:   job.jobId,
    status:  job.status,
    message: "Intake accepted. Poll /api/hermes/result/:jobId for your result.",
  });
});

export default router;
