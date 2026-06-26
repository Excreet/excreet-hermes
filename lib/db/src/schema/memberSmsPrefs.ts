import { pgTable, uuid, text, boolean, timestamp } from "drizzle-orm/pg-core";

export const memberSmsPrefsTable = pgTable("member_sms_prefs", {
  id:          uuid("id").defaultRandom().primaryKey(),
  memberId:    text("member_id").notNull().unique(),
  phoneNumber: text("phone_number").notNull(),
  optedIn:     boolean("opted_in").notNull().default(true),
  channel:     text("channel").notNull().default("sms"),
  timezone:    text("timezone").notNull().default("America/New_York"),
  createdAt:   timestamp("created_at").defaultNow().notNull(),
  updatedAt:   timestamp("updated_at").defaultNow().notNull(),
});
