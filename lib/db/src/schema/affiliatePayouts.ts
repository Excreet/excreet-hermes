import { integer, pgEnum, pgTable, serial, text, timestamp } from "drizzle-orm/pg-core";

export const payoutStatusEnum = pgEnum("affiliate_payout_status", [
  "pending",
  "processing",
  "paid",
  "failed",
]);

/**
 * affiliate_payouts — one row per bi-weekly payout batch per affiliate.
 *
 * Created when an affiliate's payout_balance_cents crosses $50 (5000 cents)
 * during the bi-weekly payout run.  amount_cents is debited from the
 * affiliate_accounts balance at creation time.
 */
export const affiliatePayoutsTable = pgTable("affiliate_payouts", {
  id:                serial("id").primaryKey(),
  affiliateMemberId: text("affiliate_member_id").notNull(),
  amountCents:       integer("amount_cents").notNull(),
  status:            payoutStatusEnum("status").notNull().default("pending"),
  periodStart:       timestamp("period_start", { withTimezone: true }).notNull(),
  periodEnd:         timestamp("period_end",   { withTimezone: true }).notNull(),
  stripePayoutId:    text("stripe_payout_id"),
  createdAt:         timestamp("created_at",   { withTimezone: true }).notNull().defaultNow(),
  updatedAt:         timestamp("updated_at",   { withTimezone: true }).notNull().defaultNow(),
});

export type AffiliatePayout = typeof affiliatePayoutsTable.$inferSelect;
