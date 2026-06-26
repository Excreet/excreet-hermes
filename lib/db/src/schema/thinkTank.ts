import { pgTable, text, timestamp, index, pgEnum } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const thinkTankCategoryEnum = pgEnum("think_tank_category", [
  "research",    // peer-reviewed study or external science
  "article",     // published editorial / blog / analysis
  "protocol",    // Excreet-authored member protocol
  "testimonial_pattern", // anonymised protocol outcome pattern
]);

export const thinkTankTable = pgTable(
  "think_tank",
  {
    id:            text("id").primaryKey(),
    title:         text("title").notNull(),
    summary:       text("summary").notNull(),   // 3-5 sentence distillation injected into AI context
    content:       text("content").notNull(),   // full text, stored for reference
    category:      thinkTankCategoryEnum("category").notNull(),
    tags:          text("tags").array().notNull().default([]),
    sourceUrl:     text("source_url"),
    author:        text("author"),
    publishedDate: text("published_date"),      // ISO date string or free-form
    createdAt:     timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
    updatedAt:     timestamp("updated_at", { withTimezone: true }).notNull().defaultNow(),
  },
  (t) => [
    index("think_tank_category_idx").on(t.category),
  ],
);

export const insertThinkTankSchema = createInsertSchema(thinkTankTable).omit({
  createdAt: true,
  updatedAt: true,
});

export type InsertThinkTankArticle = z.infer<typeof insertThinkTankSchema>;
export type ThinkTankArticle       = typeof thinkTankTable.$inferSelect;
