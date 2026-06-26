import { listJobs, getJob } from "../lib/jobStore.js";
import { processJob } from "./pipeline.js";
import { logger } from "../lib/logger.js";

const POLL_MS = Number(process.env["WORKER_POLL_MS"] ?? 2000);

/**
 * Background worker — polls for pending jobs and processes them.
 *
 * Guards against double-processing by re-checking job status
 * immediately before handing it to the pipeline. Jobs fired inline
 * from intake.ts will already be "processing" by the time this runs,
 * so this serves as a fallback for any jobs that slipped through.
 */
async function tick(): Promise<void> {
  try {
    const pending = (await listJobs()).filter((j) => j.status === "pending");

    for (const job of pending) {
      const fresh = await getJob(job.jobId);
      if (!fresh || fresh.status !== "pending") continue;

      processJob(fresh).catch((err) => {
        logger.error({ jobId: job.jobId, err }, "Worker: unhandled error in processJob");
      });
    }
  } catch (err) {
    logger.error({ err }, "Worker: error in tick");
  }
}

export function startWorker(): void {
  logger.info({ pollMs: POLL_MS }, "Hermes worker started");
  setInterval(() => { void tick(); }, POLL_MS);
}
