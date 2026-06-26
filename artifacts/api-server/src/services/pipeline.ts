import { type Job, updateJob } from "../lib/jobStore.js";
import { routeWorkflow } from "./ai/workflowRouter.js";
import { logger } from "../lib/logger.js";

/**
 * Processes a single job through the AI pipeline.
 *
 * Flow:
 *   pending → processing → completed | failed
 *
 * Never throws — all errors are caught and written to the job record.
 * Safe to call from the background worker or directly from intake route.
 */
export async function processJob(job: Job): Promise<void> {
  logger.info({ jobId: job.jobId, workflowType: job.workflowType }, "Pipeline: starting job");

  await updateJob(job.jobId, { status: "processing" });

  const outcome = await routeWorkflow(job.workflowType, job.payload);

  if (outcome.success) {
    await updateJob(job.jobId, { status: "completed", result: outcome.result as Record<string, unknown> });
    logger.info({ jobId: job.jobId }, "Pipeline: job completed");
  } else {
    await updateJob(job.jobId, { status: "failed", error: outcome.error });
    logger.error({ jobId: job.jobId, error: outcome.error }, "Pipeline: job failed");
  }
}
