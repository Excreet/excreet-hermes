import { integer, pgEnum, pgTable, serial, text, timestamp } from "drizzle-orm/pg-core";

export const w9StatusEnum = pgEnum("affiliate_w9_status", [
  "not_required",
  "pending",
  "completed",
]);

/**
 * affiliate_accounts — one row per affiliate member.
 *
 * Created automatically on PMPro checkout completion via /affiliate/provision.
 * referral_code is a short unique code (e.g. EXCA3K9MZ) generated at account
 * creation time. Members share this code as a URL: excreet.com/?ref=EXCA3K9MZ
 * All monetary values stored in cents to avoid floating-point issues.
 */
export const affiliateAccountsTable = pgTable("affiliate_accounts", {
  id:                     serial("id").primaryKey(),
  memberId:               text("member_id").notNull().unique(),
  referralCode:           text("referral_code").unique(),
  w9Status:               w9StatusEnum("w9_status").notNull().default("not_required"),
  payoutBalanceCents:     integer("payout_balance_cents").notNull().default(0),
  totalEarnedCents:       integer("total_earned_cents").notNull().default(0),
  stripeConnectAccountId: text("stripe_connect_account_id"),
  w9AlertSentAt:          timestamp("w9_alert_sent_at",  { withTimezone: true }),
  createdAt:              timestamp("created_at",        { withTimezone: true }).notNull().defaultNow(),
  updatedAt:              timestamp("updated_at",        { withTimezone: true }).notNull().defaultNow(),
});

export type AffiliateAccount = typeof affiliateAccountsTable.$inferSelect;
