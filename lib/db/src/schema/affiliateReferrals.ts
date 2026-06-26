import { integer, pgEnum, pgTable, serial, text, timestamp } from "drizzle-orm/pg-core";

export const referralStatusEnum = pgEnum("affiliate_referral_status", [
  "pending",   // within 30-day hold — no credit yet
  "cleared",   // 30 days paid — monthly credit active
  "revoked",   // referred member cancelled / lapsed — credit stops
]);

/**
 * affiliate_referrals — one row per referred member.
 *
 * referred_member_id is UNIQUE: one person can only be referred once.
 * referred_level mirrors the PMPro level ID at time of signup:
 *   1 = Starter  → $5/month reward
 *   2 = Premium  → $10/month reward
 *
 * 90-day grace rule: enforced on the WordPress side before calling
 * /affiliate/register — if the referrer's membership lapsed more than
 * 90 days ago, WordPress rejects the code before it ever reaches Hermes.
 */
export const affiliateReferralsTable = pgTable("affiliate_referrals", {
  id:               serial("id").primaryKey(),
  referrerMemberId: text("referrer_member_id").notNull(),
  referredMemberId: text("referred_member_id").notNull().unique(),
  referredLevel:    integer("referred_level").notNull(),
  status:           referralStatusEnum("status").notNull().default("pending"),
  joinedAt:         timestamp("joined_at",        { withTimezone: true }).notNull().defaultNow(),
  creditClearedAt:  timestamp("credit_cleared_at", { withTimezone: true }),
  createdAt:        timestamp("created_at",        { withTimezone: true }).notNull().defaultNow(),
});

export type AffiliateReferral = typeof affiliateReferralsTable.$inferSelect;
