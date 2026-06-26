import { pgTable, uuid, text, integer, timestamp, jsonb } from "drizzle-orm/pg-core";

export const siteHealthChecksTable = pgTable("site_health_checks", {
  id:           uuid("id").defaultRandom().primaryKey(),
  page:         text("page").notNull(),
  url:          text("url").notNull(),
  status:       text("status").notNull(),
  httpStatus:   integer("http_status"),
  responseMs:   integer("response_ms"),
  failedChecks: jsonb("failed_checks").$type<string[]>(),
  checkedAt:    timestamp("checked_at").defaultNow().notNull(),
});
