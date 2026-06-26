import { integer, pgTable, serial, text, timestamp } from "drizzle-orm/pg-core";

/**
 * ministry_sessions — server-side session ledger for Ministry of Healing chat.
 *
 * One row per member per 30-day billing window.
 * `period_start` marks when the window opened; a new row is created when the
 * current row's period has expired (> 30 days old).
 *
 * product_tier values:
 *   "starter"   — product 860, limit 10
 *   "premium"   — product 861, limit 20
 *   "unlimited" — product 862, no limit
 */
export const ministrySessionsTable = pgTable("ministry_sessions", {
  id:          serial("id").primaryKey(),
  memberId:    text("member_id").notNull(),
  productTier: text("product_tier").notNull().default("starter"),
  count:       integer("count").notNull().default(0),
  periodStart: timestamp("period_start", { withTimezone: true }).notNull().defaultNow(),
  updatedAt:   timestamp("updated_at",  { withTimezone: true }).notNull().defaultNow(),
});

export type MinistrySession = typeof ministrySessionsTable.$inferSelect;
