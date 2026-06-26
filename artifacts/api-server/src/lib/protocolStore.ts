import { randomUUID } from "crypto";
import { eq, desc } from "drizzle-orm";
import { db, memberProtocolsTable, type MemberProtocol } from "@workspace/db";

/**
 * protocolStore — PostgreSQL-backed protocol persistence.
 *
 * Each row is one generated Healing Protocol for one member.
 * No uniqueness constraint — members can generate multiple protocols
 * over time (each $29 purchase = 1 credit = 1 row).
 */

export async function saveProtocol(
  memberId:    string,
  concern:     string,
  protocol:    Record<string, unknown>,
  generatedAt: Date,
): Promise<MemberProtocol> {
  const [row] = await db
    .insert(memberProtocolsTable)
    .values({
      id:          randomUUID(),
      memberId,
      concern,
      protocol,
      generatedAt,
    })
    .returning();

  if (!row) throw new Error("protocolStore: insert returned no row");
  return row;
}

export async function getProtocolHistory(
  memberId: string,
  limit = 20,
): Promise<MemberProtocol[]> {
  return db
    .select()
    .from(memberProtocolsTable)
    .where(eq(memberProtocolsTable.memberId, memberId))
    .orderBy(desc(memberProtocolsTable.generatedAt))
    .limit(limit);
}
