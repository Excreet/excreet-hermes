import { jsonb, pgTable, serial, text, timestamp } from "drizzle-orm/pg-core";

export interface ChatMessage {
  role:    "user" | "assistant";
  content: string;
}

/**
 * ministry_chat_history — persists full conversation thread per member.
 *
 * One row per member (upserted on every chat turn). Capped at 40 entries
 * (~20 back-and-forth turns) so context stays manageable and token costs
 * remain predictable.
 */
export const ministryChatHistoryTable = pgTable("ministry_chat_history", {
  id:        serial("id").primaryKey(),
  memberId:  text("member_id").notNull().unique(),
  messages:  jsonb("messages").$type<ChatMessage[]>().notNull().default([]),
  updatedAt: timestamp("updated_at", { withTimezone: true }).notNull().defaultNow(),
});

export type MinistryChatHistory = typeof ministryChatHistoryTable.$inferSelect;
