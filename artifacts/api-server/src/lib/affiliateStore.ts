import { and, eq, gte, isNull, lt, sql } from "drizzle-orm";
import {
  db,
  affiliateAccountsTable,
  affiliateReferralsTable,
  affiliatePayoutsTable,
} from "@workspace/db";

export const STARTER_REWARD_CENTS = 500;   // $5.00 per month
export const PREMIUM_REWARD_CENTS = 1000;  // $10.00 per month
export const PAYOUT_THRESHOLD_CENTS = 5000; // $50.00
export const HOLD_DAYS = 30;

const CODE_CHARS = "ABCDEFGHJKMNPQRSTUVWXYZ23456789"; // no 0/O/1/I/L

/** Generates a unique-enough referral code like EXCA3K9MZ. */
function generateReferralCode(): string {
  let code = "EXC";
  for (let i = 0; i < 6; i++) {
    code += CODE_CHARS[Math.floor(Math.random() * CODE_CHARS.length)];
  }
  return code;
}

/** Returns reward in cents based on PMPro level ID. */
export function rewardForLevel(level: number): number {
  return level === 2 ? PREMIUM_REWARD_CENTS : STARTER_REWARD_CENTS;
}

/**
 * Ensures an affiliate_accounts row exists for a member.
 * Generates and stores a unique referral code if one is not already set.
 * Safe to call multiple times — idempotent.
 */
export async function ensureAffiliateAccount(memberId: string) {
  await db
    .insert(affiliateAccountsTable)
    .values({ memberId, referralCode: generateReferralCode() })
    .onConflictDoNothing();

  const [account] = await db
    .select()
    .from(affiliateAccountsTable)
    .where(eq(affiliateAccountsTable.memberId, memberId))
    .limit(1);

  // Backfill: if this row was created before codes were introduced, assign one now.
  if (account && !account.referralCode) {
    let code: string | null = null;
    for (let attempts = 0; attempts < 10; attempts++) {
      const candidate = generateReferralCode();
      const result = await db
        .update(affiliateAccountsTable)
        .set({ referralCode: candidate, updatedAt: new Date() })
        .where(
          and(
            eq(affiliateAccountsTable.memberId, memberId),
            isNull(affiliateAccountsTable.referralCode),
          ),
        )
        .returning({ referralCode: affiliateAccountsTable.referralCode });
      if (result.length > 0) { code = candidate; break; }
    }
    return { ...account, referralCode: code };
  }

  return account!;
}

/**
 * Resolves a referral code to its owner's memberId.
 * Returns null if the code is not found.
 */
export async function resolveReferralCode(referralCode: string): Promise<string | null> {
  const [account] = await db
    .select({ memberId: affiliateAccountsTable.memberId })
    .from(affiliateAccountsTable)
    .where(eq(affiliateAccountsTable.referralCode, referralCode.toUpperCase()))
    .limit(1);
  return account?.memberId ?? null;
}

/**
 * Returns the referral code and share URL for a member.
 * Generates one if the member doesn't have one yet.
 */
export async function getAffiliateCode(memberId: string): Promise<{
  referralCode: string;
  shareUrl: string;
}> {
  const account = await ensureAffiliateAccount(memberId);
  const code = account.referralCode!;
  return {
    referralCode: code,
    shareUrl: `https://excreet.com/?ref=${code}`,
  };
}

/**
 * Backfills referral codes for all existing accounts that are missing one.
 * Safe to run multiple times. Returns count of rows updated.
 */
export async function backfillReferralCodes(): Promise<number> {
  const missing = await db
    .select({ memberId: affiliateAccountsTable.memberId })
    .from(affiliateAccountsTable)
    .where(isNull(affiliateAccountsTable.referralCode));

  let updated = 0;
  for (const { memberId } of missing) {
    await ensureAffiliateAccount(memberId);
    updated++;
  }
  return updated;
}

/**
 * Registers a new referral. Returns `true` on success, `false` if the
 * referred member already has a referral record (duplicate blocked by
 * UNIQUE constraint on referred_member_id).
 */
export async function registerReferral(
  referrerMemberId: string,
  referredMemberId: string,
  referredLevel: number,
): Promise<boolean> {
  await ensureAffiliateAccount(referrerMemberId);

  try {
    await db.insert(affiliateReferralsTable).values({
      referrerMemberId,
      referredMemberId,
      referredLevel,
    });
    return true;
  } catch {
    return false;
  }
}

/**
 * Returns full affiliate dashboard data for a member:
 * account balance, referral list, payout history.
 */
export async function getAffiliateDashboard(memberId: string) {
  const account = await ensureAffiliateAccount(memberId);

  const referrals = await db
    .select()
    .from(affiliateReferralsTable)
    .where(eq(affiliateReferralsTable.referrerMemberId, memberId));

  const payouts = await db
    .select()
    .from(affiliatePayoutsTable)
    .where(eq(affiliatePayoutsTable.affiliateMemberId, memberId));

  return { account, referrals, payouts };
}

/**
 * Monthly credit run — called by WordPress cron with the list of currently
 * active PMPro member IDs. Hermes does not query PMPro directly; WP owns
 * that check.
 */
export async function runMonthlyCredit(activeMemberIds: string[]): Promise<{
  cleared: number;
  credited: number;
}> {
  const now = new Date();
  const holdCutoff = new Date(now.getTime() - HOLD_DAYS * 24 * 60 * 60 * 1000);

  const pendingReferrals = await db
    .select()
    .from(affiliateReferralsTable)
    .where(
      and(
        eq(affiliateReferralsTable.status, "pending"),
        lt(affiliateReferralsTable.joinedAt, holdCutoff),
      ),
    );

  let cleared = 0;
  for (const ref of pendingReferrals) {
    if (!activeMemberIds.includes(ref.referredMemberId)) continue;

    await db
      .update(affiliateReferralsTable)
      .set({ status: "cleared", creditClearedAt: now })
      .where(eq(affiliateReferralsTable.id, ref.id));

    await db
      .update(affiliateAccountsTable)
      .set({ w9Status: "pending", updatedAt: now })
      .where(
        and(
          eq(affiliateAccountsTable.memberId, ref.referrerMemberId),
          eq(affiliateAccountsTable.w9Status, "not_required"),
        ),
      );

    cleared++;
  }

  const clearedReferrals = await db
    .select()
    .from(affiliateReferralsTable)
    .where(eq(affiliateReferralsTable.status, "cleared"));

  let credited = 0;
  for (const ref of clearedReferrals) {
    if (!activeMemberIds.includes(ref.referredMemberId)) {
      await db
        .update(affiliateReferralsTable)
        .set({ status: "revoked" })
        .where(eq(affiliateReferralsTable.id, ref.id));
      continue;
    }

    const reward = rewardForLevel(ref.referredLevel);

    await db
      .update(affiliateAccountsTable)
      .set({
        payoutBalanceCents: sql`${affiliateAccountsTable.payoutBalanceCents} + ${reward}`,
        totalEarnedCents:   sql`${affiliateAccountsTable.totalEarnedCents} + ${reward}`,
        updatedAt:          now,
      })
      .where(eq(affiliateAccountsTable.memberId, ref.referrerMemberId));

    credited++;
  }

  return { cleared, credited };
}

/**
 * Bi-weekly payout trigger.
 */
export async function triggerPayouts(periodStart: Date, periodEnd: Date): Promise<{
  payoutsCreated: number;
  totalCents: number;
}> {
  const eligible = await db
    .select()
    .from(affiliateAccountsTable)
    .where(
      and(
        gte(affiliateAccountsTable.payoutBalanceCents, PAYOUT_THRESHOLD_CENTS),
        eq(affiliateAccountsTable.w9Status, "completed"),
      ),
    );

  let payoutsCreated = 0;
  let totalCents = 0;

  for (const account of eligible) {
    const amount = account.payoutBalanceCents;

    await db.insert(affiliatePayoutsTable).values({
      affiliateMemberId: account.memberId,
      amountCents:       amount,
      periodStart,
      periodEnd,
    });

    await db
      .update(affiliateAccountsTable)
      .set({
        payoutBalanceCents: sql`${affiliateAccountsTable.payoutBalanceCents} - ${amount}`,
        updatedAt: new Date(),
      })
      .where(eq(affiliateAccountsTable.memberId, account.memberId));

    payoutsCreated++;
    totalCents += amount;
  }

  return { payoutsCreated, totalCents };
}

/**
 * Admin: manually assign (or reassign) a referral.
 */
export async function assignReferral(
  referrerMemberId: string,
  referredMemberId: string,
  referredLevel: number,
): Promise<{ action: "created" | "updated" }> {
  await ensureAffiliateAccount(referrerMemberId);

  const [existing] = await db
    .select()
    .from(affiliateReferralsTable)
    .where(eq(affiliateReferralsTable.referredMemberId, referredMemberId))
    .limit(1);

  const now = new Date();

  if (existing) {
    await db
      .update(affiliateReferralsTable)
      .set({
        referrerMemberId,
        referredLevel,
        status: "cleared",
        creditClearedAt: now,
      })
      .where(eq(affiliateReferralsTable.id, existing.id));
    return { action: "updated" };
  }

  await db.insert(affiliateReferralsTable).values({
    referrerMemberId,
    referredMemberId,
    referredLevel,
    status:          "cleared",
    creditClearedAt: now,
  });
  return { action: "created" };
}

/** Mark W-9 as completed for a member. */
export async function markW9Completed(memberId: string): Promise<void> {
  await db
    .update(affiliateAccountsTable)
    .set({ w9Status: "completed", updatedAt: new Date() })
    .where(eq(affiliateAccountsTable.memberId, memberId));
}
