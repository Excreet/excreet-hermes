import { randomUUID } from "crypto";
import { and, eq, desc } from "drizzle-orm";
import { db, bodySnapshotsTable, type BodySnapshot } from "@workspace/db";
import type { BodySnapshotResult } from "../services/ai/schemas/bodySnapshotSchema.js";

/**
 * bodySnapshotStore — PostgreSQL-backed 24/7 Body Snapshot persistence.
 *
 * One row per member per calendar day (UTC). Attempting to save a second
 * snapshot for the same member on the same day is a no-op — the existing
 * row is returned unchanged (idempotent upsert).
 */

function todayUtc(): string {
  return new Date().toISOString().slice(0, 10); // "YYYY-MM-DD"
}

export async function saveSnapshot(
  memberId: string,
  result:   BodySnapshotResult,
): Promise<BodySnapshot> {
  const id           = randomUUID();
  const snapshotDate = todayUtc();

  const [row] = await db
    .insert(bodySnapshotsTable)
    .values({
      id,
      memberId,
      snapshotDate,
      bodyScore: result.bodyScore,
      tier:      result.tier,
      result:    result as unknown as Record<string, unknown>,
      createdAt: new Date(),
      updatedAt: new Date(),
    })
    .onConflictDoNothing()
    .returning();

  if (row) return row;

  // Row already existed — fetch and return it
  const [existing] = await db
    .select()
    .from(bodySnapshotsTable)
    .where(
      and(
        eq(bodySnapshotsTable.memberId,     memberId),
        eq(bodySnapshotsTable.snapshotDate, snapshotDate),
      ),
    )
    .limit(1);

  if (!existing) throw new Error("bodySnapshotStore: upsert inconsistency");
  return existing;
}

export async function getTodaySnapshot(
  memberId: string,
): Promise<BodySnapshot | undefined> {
  const [row] = await db
    .select()
    .from(bodySnapshotsTable)
    .where(
      and(
        eq(bodySnapshotsTable.memberId,     memberId),
        eq(bodySnapshotsTable.snapshotDate, todayUtc()),
      ),
    )
    .limit(1);

  return row;
}

export async function getSnapshotHistory(
  memberId: string,
  limit = 30,
): Promise<BodySnapshot[]> {
  return db
    .select()
    .from(bodySnapshotsTable)
    .where(eq(bodySnapshotsTable.memberId, memberId))
    .orderBy(desc(bodySnapshotsTable.snapshotDate))
    .limit(limit);
}
