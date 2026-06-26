import { eq } from "drizzle-orm";
import { db, ministryChatHistoryTable } from "@workspace/db";
import type { ChatMessage } from "@workspace/db";

/**
 * Deletes the member's entire Ministry chat history, giving them a clean slate.
 * Called by the POST /ministry/history/reset endpoint.
 */
export async function resetChatHistory(memberId: string): Promise<void> {
  await db
    .delete(ministryChatHistoryTable)
    .where(eq(ministryChatHistoryTable.memberId, memberId));
}

/** Maximum stored turns (user + assistant combined). */
const MAX_HISTORY = 40;

/**
 * Returns the full conversation history for a member, newest last.
 * Returns [] if no history exists yet.
 */
export async function getChatHistory(memberId: string): Promise<ChatMessage[]> {
  const [row] = await db
    .select()
    .from(ministryChatHistoryTable)
    .where(eq(ministryChatHistoryTable.memberId, memberId))
    .limit(1);

  return (row?.messages as ChatMessage[]) ?? [];
}

/**
 * Appends a user+assistant turn to the member's stored history and upserts.
 * Trims to MAX_HISTORY entries so the table never grows unbounded.
 *
 * Called fire-and-forget after every successful chat response.
 */
export async function appendChatHistory(
  memberId:     string,
  userMsg:      string,
  assistantMsg: string,
): Promise<void> {
  const existing = await getChatHistory(memberId);

  const updated = ([
    ...existing,
    { role: "user"      as const, content: userMsg      },
    { role: "assistant" as const, content: assistantMsg },
  ] as ChatMessage[]).slice(-MAX_HISTORY);

  await db
    .insert(ministryChatHistoryTable)
    .values({ memberId, messages: updated })
    .onConflictDoUpdate({
      target: ministryChatHistoryTable.memberId,
      set: {
        messages:  updated,
        updatedAt: new Date(),
      },
    });
}
