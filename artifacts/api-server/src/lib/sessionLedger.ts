import { and, desc, eq } from "drizzle-orm";
import { db, ministrySessionsTable, type MinistrySession } from "@workspace/db";
import { logger } from "./logger.js";

/**
 * sessionLedger.ts — server-side Ministry of Healing session counter.
 *
 * Mirrors the WordPress user-meta counter (patch-293) but lives in Postgres,
 * so it cannot be bypassed by calling the Hermes API directly.
 *
 * Tier limits (must match WordPress constants):
 *   starter   (product 860) → 10 per 30-day window
 *   premium   (product 861) → 20 per 30-day window
 *   unlimited (product 862) → no limit, no counter
 *
 * Membership tier is passed by WordPress in each request (the `tier` field
 * in the request body). Hermes trusts the API key for auth; the tier is used
 * only to look up the limit — the actual block decision is made server-side.
 */

export type SessionTier = "starter" | "premium" | "unlimited";

const TIER_LIMITS: Record<SessionTier, number | null> = {
  starter:   10,
  premium:   20,
  unlimited: null, // null = no limit
};

const PERIOD_MS = 30 * 24 * 60 * 60 * 1000; // 30 days in ms

/** Normalise any tier string to a known SessionTier (default: starter). */
export function normaliseTier(raw: string | undefined): SessionTier {
  if (raw === "premium" || raw === "unlimited") return raw;
  return "starter";
}

/** Return the session limit for a tier, or null for unlimited. */
export function tierLimit(tier: SessionTier): number | null {
  return TIER_LIMITS[tier];
}

/**
 * Fetch the active session row for a member, creating or resetting it as needed.
 * A row is "active" if its period_start is within the last 30 days.
 */
async function getOrCreateRow(memberId: string, tier: SessionTier): Promise<MinistrySession> {
  const [existing] = await db
    .select()
    .from(ministrySessionsTable)
    .where(eq(ministrySessionsTable.memberId, memberId))
    .orderBy(desc(ministrySessionsTable.periodStart))
    .limit(1);

  const now = Date.now();

  if (existing) {
    const age = now - existing.periodStart.getTime();

    if (age <= PERIOD_MS) {
      // Active window — tier may have changed (upgrade/downgrade mid-period)
      if (existing.productTier !== tier) {
        const [updated] = await db
          .update(ministrySessionsTable)
          .set({ productTier: tier, updatedAt: new Date() })
          .where(eq(ministrySessionsTable.id, existing.id))
          .returning();
        if (!updated) throw new Error("sessionLedger: failed to update tier");
        return updated;
      }
      return existing;
    }
  }

  // No row or expired — open a fresh 30-day window
  const [created] = await db
    .insert(ministrySessionsTable)
    .values({
      memberId,
      productTier: tier,
      count:       0,
      periodStart: new Date(),
    })
    .returning();

  if (!created) throw new Error("sessionLedger: failed to create new period row");
  return created;
}

export type SessionCheck =
  | { allowed: true;  remaining: number | null; count: number }
  | { allowed: false; remaining: 0;             count: number };

/**
 * Check whether a member is allowed another session.
 * Returns `remaining: null` for unlimited members.
 * Does NOT increment — call `incrementSession` after a successful AI response.
 */
export async function checkSession(memberId: string, tier: SessionTier): Promise<SessionCheck> {
  if (tier === "unlimited") {
    return { allowed: true, remaining: null, count: 0 };
  }

  const limit = TIER_LIMITS[tier]!;
  const row   = await getOrCreateRow(memberId, tier);

  if (row.count >= limit) {
    logger.warn({ memberId, tier, count: row.count, limit }, "Session limit reached");
    return { allowed: false, remaining: 0, count: row.count };
  }

  return { allowed: true, remaining: limit - row.count, count: row.count };
}

/**
 * Increment the session count for a member after a successful AI response.
 * Returns the new remaining count (null for unlimited).
 */
export async function incrementSession(
  memberId: string,
  tier: SessionTier,
): Promise<number | null> {
  if (tier === "unlimited") return null;

  const limit = TIER_LIMITS[tier]!;
  const row   = await getOrCreateRow(memberId, tier);

  const newCount = row.count + 1;

  await db
    .update(ministrySessionsTable)
    .set({ count: newCount, updatedAt: new Date() })
    .where(and(
      eq(ministrySessionsTable.memberId, memberId),
      eq(ministrySessionsTable.id, row.id),
    ));

  const remaining = Math.max(0, limit - newCount);
  logger.info({ memberId, tier, newCount, remaining }, "Session incremented");
  return remaining;
}
