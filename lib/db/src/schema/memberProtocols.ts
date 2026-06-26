import { pgTable, text, jsonb, timestamp, index } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const memberProtocolsTable = pgTable(
  "member_protocols",
  {
    id:          text("id").primaryKey(),
    memberId:    text("member_id").notNull(),
    concern:     text("concern").notNull(),
    protocol:    jsonb("protocol").notNull(),
    generatedAt: timestamp("generated_at", { withTimezone: true }).notNull(),
    createdAt:   timestamp("created_at",   { withTimezone: true }).notNull().defaultNow(),
  },
  (t) => [
    index("member_protocols_member_id_idx").on(t.memberId),
  ],
);

export const insertMemberProtocolSchema = createInsertSchema(memberProtocolsTable).omit({
  createdAt: true,
});

export type InsertMemberProtocol = z.infer<typeof insertMemberProtocolSchema>;
export type MemberProtocol      = typeof memberProtocolsTable.$inferSelect;
