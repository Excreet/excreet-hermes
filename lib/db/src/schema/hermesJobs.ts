import { pgTable, text, jsonb, timestamp, pgEnum } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const jobStatusEnum = pgEnum("hermes_job_status", [
  "pending",
  "processing",
  "completed",
  "failed",
]);

export const hermesJobsTable = pgTable("hermes_jobs", {
  jobId:        text("job_id").primaryKey(),
  status:       jobStatusEnum("status").notNull().default("pending"),
  memberId:     text("member_id").notNull(),
  workflowType: text("workflow_type").notNull(),
  payload:      jsonb("payload").notNull(),
  result:       jsonb("result"),
  error:        text("error"),
  createdAt:    timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
  updatedAt:    timestamp("updated_at", { withTimezone: true }).notNull().defaultNow(),
});

export const insertHermesJobSchema = createInsertSchema(hermesJobsTable).omit({
  createdAt: true,
  updatedAt: true,
});

export type InsertHermesJob = z.infer<typeof insertHermesJobSchema>;
export type HermesJob = typeof hermesJobsTable.$inferSelect;
