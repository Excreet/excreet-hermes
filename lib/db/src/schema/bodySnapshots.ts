import { pgTable, text, jsonb, timestamp, integer, date, uniqueIndex } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const bodySnapshotsTable = pgTable(
  "body_snapshots",
  {
    id:           text("id").primaryKey(),
    memberId:     text("member_id").notNull(),
    snapshotDate: date("snapshot_date").notNull(),
    bodyScore:    integer("body_score").notNull(),
    tier:         text("tier").notNull(),
    result:       jsonb("result").notNull(),
    createdAt:    timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
    updatedAt:    timestamp("updated_at", { withTimezone: true }).notNull().defaultNow(),
  },
  (t) => [
    uniqueIndex("body_snapshots_member_date_uidx").on(t.memberId, t.snapshotDate),
  ],
);

export const insertBodySnapshotSchema = createInsertSchema(bodySnapshotsTable).omit({
  createdAt: true,
  updatedAt: true,
});

export type InsertBodySnapshot = z.infer<typeof insertBodySnapshotSchema>;
export type BodySnapshot      = typeof bodySnapshotsTable.$inferSelect;
