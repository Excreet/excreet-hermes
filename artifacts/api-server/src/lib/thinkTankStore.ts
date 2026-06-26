/**
 * thinkTankStore.ts
 *
 * Query helpers for the Excreet Think Tank — the knowledge base that feeds
 * the Hermes AI with research, protocols, and outcome patterns.
 *
 * Design principle:
 *   - summaries are injected into every Ministry system prompt (lightweight)
 *   - full content is available on demand via endpoints
 *   - outcomes are anonymised before injection so member privacy is preserved
 */

import { db } from "@workspace/db";
import { thinkTankTable, thinkTankOutcomesTable } from "@workspace/db";
import { eq, desc } from "drizzle-orm";

// ─── Articles ────────────────────────────────────────────────────────────────

export async function getAllArticles() {
  return db
    .select()
    .from(thinkTankTable)
    .orderBy(desc(thinkTankTable.createdAt));
}

export async function getArticleById(id: string) {
  const rows = await db
    .select()
    .from(thinkTankTable)
    .where(eq(thinkTankTable.id, id))
    .limit(1);
  return rows[0] ?? null;
}

export async function upsertArticle(
  data: typeof thinkTankTable.$inferInsert,
) {
  await db
    .insert(thinkTankTable)
    .values(data)
    .onConflictDoUpdate({
      target: thinkTankTable.id,
      set: {
        title:         data.title,
        summary:       data.summary,
        content:       data.content,
        category:      data.category,
        tags:          data.tags,
        sourceUrl:     data.sourceUrl,
        author:        data.author,
        publishedDate: data.publishedDate,
        updatedAt:     new Date(),
      },
    });
}

export async function deleteArticle(id: string) {
  await db.delete(thinkTankTable).where(eq(thinkTankTable.id, id));
}

// ─── Outcomes ─────────────────────────────────────────────────────────────────

export async function getAllOutcomes() {
  return db
    .select()
    .from(thinkTankOutcomesTable)
    .orderBy(desc(thinkTankOutcomesTable.createdAt));
}

export async function insertOutcome(
  data: typeof thinkTankOutcomesTable.$inferInsert,
) {
  await db.insert(thinkTankOutcomesTable).values(data);
}

// ─── Context Builder (for Ministry system prompt injection) ──────────────────

/**
 * Builds the Think Tank context block to inject into the Ministry of Healing
 * system prompt. Includes:
 *  - summaries of all research articles and protocols
 *  - anonymised outcome patterns (improvements, regressions)
 *
 * Designed to be concise enough not to bloat the context window while giving
 * Hermes enough signal to draw on accumulated knowledge.
 */
export async function buildThinkTankContext(): Promise<string> {
  const [articles, outcomes] = await Promise.all([
    getAllArticles(),
    getAllOutcomes(),
  ]);

  if (articles.length === 0 && outcomes.length === 0) {
    return "";
  }

  const lines: string[] = [];

  lines.push(
    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
    "EXCREET THINK TANK — ACCUMULATED KNOWLEDGE BASE",
    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
    "",
    "The following is the Excreet Think Tank — a curated, evolving knowledge",
    "base of research, published articles, and clinical protocols. Draw on",
    "this when answering member questions. Reference specific titles when",
    "relevant. Do not repeat this verbatim to the member.",
    "",
  );

  // ── Research & Articles ──
  const researchArticles = articles.filter(
    (a) => a.category === "research" || a.category === "article" || a.category === "protocol",
  );
  if (researchArticles.length > 0) {
    lines.push("── RESEARCH & PROTOCOLS ──", "");
    for (const a of researchArticles) {
      const tags = a.tags && a.tags.length > 0 ? ` [${a.tags.join(", ")}]` : "";
      const src  = a.sourceUrl ? ` | Source: ${a.sourceUrl}` : "";
      const auth = a.author ? ` | Author: ${a.author}` : "";
      lines.push(`▸ ${a.title}${tags}${src}${auth}`);
      lines.push(`  ${a.summary}`);
      lines.push("");
    }
  }

  // ── Outcome Patterns ──
  const improvements = outcomes.filter((o) => o.outcomeType === "improvement");
  const regressions  = outcomes.filter((o) => o.outcomeType === "regression");
  const testimonials = outcomes.filter((o) => o.outcomeType === "testimonial");

  if (outcomes.length > 0) {
    lines.push("── MEMBER OUTCOME PATTERNS (anonymised) ──", "");

    if (improvements.length > 0) {
      lines.push(`Documented improvements (${improvements.length} records):`);
      for (const o of improvements.slice(0, 10)) {
        const delta =
          o.bodyScoreBefore != null && o.bodyScoreAfter != null
            ? ` | Score: ${o.bodyScoreBefore} → ${o.bodyScoreAfter}`
            : "";
        const tf = o.timeframeDays ? ` over ${o.timeframeDays} days` : "";
        lines.push(
          `  • ${o.concern ?? "General concern"}${delta}${tf}: ${o.outcomeNotes ?? o.protocolSummary ?? "Improvement recorded."}`,
        );
      }
      lines.push("");
    }

    if (regressions.length > 0) {
      lines.push(`Documented regressions (${regressions.length} records — use to avoid repeating ineffective paths):`);
      for (const o of regressions.slice(0, 5)) {
        lines.push(
          `  • ${o.concern ?? "General concern"}: ${o.outcomeNotes ?? "Regression recorded."}`,
        );
      }
      lines.push("");
    }

    if (testimonials.length > 0) {
      lines.push(`Member testimonials (${testimonials.length} records):`);
      for (const o of testimonials.slice(0, 5)) {
        if (o.testimonialText) {
          lines.push(`  • "${o.testimonialText}"`);
        }
      }
      lines.push("");
    }
  }

  lines.push("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

  return lines.join("\n");
}
