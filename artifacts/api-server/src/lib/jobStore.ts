import { randomUUID } from "crypto";
import { and, eq, inArray, desc } from "drizzle-orm";
import { db, hermesJobsTable, type HermesJob } from "@workspace/db";

/**
 * jobStore.ts — Phase 5: PostgreSQL-backed job store.
 *
 * Same interface as the Phase 3 in-memory store, all functions now async.
 * Swap back to in-memory by replacing this file — callers don't change.
 */

export type JobStatus = "pending" | "processing" | "completed" | "failed";

export type Job = {
  jobId:        string;
  status:       JobStatus;
  memberId:     string;
  workflowType: string;
  payload:      Record<string, unknown>;
  result:       unknown | null;
  error:        string | null;
  createdAt:    string;
  updatedAt:    string;
};

function rowToJob(row: HermesJob): Job {
  return {
    jobId:        row.jobId,
    status:       row.status as JobStatus,
    memberId:     row.memberId,
    workflowType: row.workflowType,
    payload:      row.payload as Record<string, unknown>,
    result:       row.result ?? null,
    error:        row.error ?? null,
    createdAt:    row.createdAt.toISOString(),
    updatedAt:    row.updatedAt.toISOString(),
  };
}

export async function createJob(
  memberId:     string,
  workflowType: string,
  payload:      Record<string, unknown>,
): Promise<Job> {
  const jobId = randomUUID();
  const now   = new Date();

  const [row] = await db
    .insert(hermesJobsTable)
    .values({
      jobId,
      status:       "pending",
      memberId,
      workflowType,
      payload,
      result:       null,
      error:        null,
      createdAt:    now,
      updatedAt:    now,
    })
    .returning();

  if (!row) throw new Error("Failed to create job — DB returned no row");
  return rowToJob(row);
}

export async function getJob(jobId: string): Promise<Job | undefined> {
  const [row] = await db
    .select()
    .from(hermesJobsTable)
    .where(eq(hermesJobsTable.jobId, jobId))
    .limit(1);

  return row ? rowToJob(row) : undefined;
}

export async function updateJob(
  jobId: string,
  patch: Partial<Pick<Job, "status" | "result" | "error">>,
): Promise<Job | undefined> {
  const [row] = await db
    .update(hermesJobsTable)
    .set({ ...patch, updatedAt: new Date() })
    .where(eq(hermesJobsTable.jobId, jobId))
    .returning();

  return row ? rowToJob(row) : undefined;
}

/**
 * Returns the most recent pending or processing job for a member+workflow,
 * or undefined if none exists. Used to prevent duplicate pipeline runs when
 * WordPress fires the webhook more than once (e.g. network retries).
 */
export async function getActiveJobForMember(
  memberId:     string,
  workflowType: string,
): Promise<Job | undefined> {
  const [row] = await db
    .select()
    .from(hermesJobsTable)
    .where(
      and(
        eq(hermesJobsTable.memberId,     memberId),
        eq(hermesJobsTable.workflowType, workflowType),
        inArray(hermesJobsTable.status,  ["pending", "processing"]),
      ),
    )
    .orderBy(desc(hermesJobsTable.createdAt))
    .limit(1);

  return row ? rowToJob(row) : undefined;
}

export async function listJobs(): Promise<Job[]> {
  const rows = await db
    .select()
    .from(hermesJobsTable)
    .orderBy(hermesJobsTable.createdAt);
  return rows.map(rowToJob);
}
