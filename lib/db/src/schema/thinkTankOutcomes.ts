import { pgTable, text, integer, timestamp, index, pgEnum } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const outcomeTypeEnum = pgEnum("think_tank_outcome_type", [
  "improvement",   // member reported measurable improvement
  "no_change",     // protocol ran, no notable change
  "regression",    // condition worsened (important for learning)
  "testimonial",   // qualitative member statement
]);

/**
 * think_tank_outcomes
 *
 * Records protocol outcomes and member testimonials.
 * Links back to a member's protocol (via protocolRef) and optionally
 * to a think tank article that informed the protocol (via articleId).
 *
 * This is the closing leg of the feedback loop:
 *   Intake → Protocol → Outcome → Think Tank → Next Protocol
 */
export const thinkTankOutcomesTable = pgTable(
  "think_tank_outcomes",
  {
    id:              text("id").primaryKey(),
    memberId:        text("member_id").notNull(),
    protocolRef:     text("protocol_ref"),           // member_protocols.id, if available
    articleId:       text("article_id"),             // think_tank.id that informed this protocol
    outcomeType:     outcomeTypeEnum("outcome_type").notNull(),
    bodyScoreBefore: integer("body_score_before"),   // 0-100
    bodyScoreAfter:  integer("body_score_after"),    // 0-100 (recorded at follow-up)
    timeframeDays:   integer("timeframe_days"),      // how long the protocol ran
    concern:         text("concern"),                // the health concern being addressed
    protocolSummary: text("protocol_summary"),       // what was prescribed / recommended
    outcomeNotes:    text("outcome_notes"),          // free text — what changed
    testimonialText: text("testimonial_text"),       // member's own words (optional)
    recordedBy:      text("recorded_by").notNull().default("system"), // "system" | "admin" | "member"
    createdAt:       timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
  },
  (t) => [
    index("think_tank_outcomes_member_idx").on(t.memberId),
    index("think_tank_outcomes_type_idx").on(t.outcomeType),
    index("think_tank_outcomes_article_idx").on(t.articleId),
  ],
);

export const insertThinkTankOutcomeSchema = createInsertSchema(thinkTankOutcomesTable).omit({
  createdAt: true,
});

export type InsertThinkTankOutcome = z.infer<typeof insertThinkTankOutcomeSchema>;
export type ThinkTankOutcome       = typeof thinkTankOutcomesTable.$inferSelect;
