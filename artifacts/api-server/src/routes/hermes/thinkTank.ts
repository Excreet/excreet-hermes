/**
 * Think Tank routes — /api/hermes/think-tank
 *
 * The Excreet Think Tank is an evolving knowledge base of research articles,
 * clinical protocols, and member outcome patterns. It feeds the Ministry of
 * Healing AI context so every response grows smarter over time.
 *
 * Articles:
 *   GET    /api/hermes/think-tank/articles          — list all (summary view)
 *   GET    /api/hermes/think-tank/articles/:id      — full article
 *   POST   /api/hermes/think-tank/articles          — create / upsert
 *   DELETE /api/hermes/think-tank/articles/:id      — remove
 *
 * Outcomes (member testimonials + protocol results):
 *   GET    /api/hermes/think-tank/outcomes          — list all
 *   POST   /api/hermes/think-tank/outcomes          — record new outcome
 *
 * Context preview:
 *   GET    /api/hermes/think-tank/context           — shows exactly what Hermes sees
 */

import { Router, type IRouter } from "express";
import { z } from "zod/v4";
import { randomUUID } from "crypto";
import {
  getAllArticles,
  getArticleById,
  upsertArticle,
  deleteArticle,
  getAllOutcomes,
  insertOutcome,
  buildThinkTankContext,
} from "../../lib/thinkTankStore.js";

const router: IRouter = Router();

// ─── Articles ─────────────────────────────────────────────────────────────────

const ArticleUpsertSchema = z.object({
  id:             z.string().optional(),
  title:          z.string().min(1).max(500),
  summary:        z.string().min(1).max(2000),
  content:        z.string().min(1),
  category:       z.enum(["research", "article", "protocol", "testimonial_pattern"]),
  tags:           z.array(z.string()).default([]),
  source_url:     z.string().url().optional().or(z.literal("")),
  author:         z.string().max(200).optional(),
  published_date: z.string().max(50).optional(),
});

router.get("/think-tank/articles", async (req, res) => {
  try {
    const articles = await getAllArticles();
    const slim = articles.map((a) => ({
      id:             a.id,
      title:          a.title,
      summary:        a.summary,
      category:       a.category,
      tags:           a.tags,
      source_url:     a.sourceUrl,
      author:         a.author,
      published_date: a.publishedDate,
      created_at:     a.createdAt,
    }));
    res.json({ articles: slim, total: slim.length });
  } catch (err: unknown) {
    req.log.error({ err }, "Think Tank: failed to list articles");
    res.status(500).json({ error: "Failed to load articles" });
  }
});

router.get("/think-tank/articles/:id", async (req, res) => {
  try {
    const article = await getArticleById(req.params.id);
    if (!article) {
      res.status(404).json({ error: "Article not found" });
      return;
    }
    res.json({ article });
  } catch (err: unknown) {
    req.log.error({ id: req.params.id, err }, "Think Tank: failed to fetch article");
    res.status(500).json({ error: "Failed to fetch article" });
  }
});

router.post("/think-tank/articles", async (req, res) => {
  const parsed = ArticleUpsertSchema.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  const data = parsed.data;
  const id   = data.id ?? randomUUID();

  try {
    await upsertArticle({
      id,
      title:         data.title,
      summary:       data.summary,
      content:       data.content,
      category:      data.category,
      tags:          data.tags,
      sourceUrl:     data.source_url || null,
      author:        data.author    || null,
      publishedDate: data.published_date || null,
    });
    req.log.info({ id, title: data.title }, "Think Tank: article upserted");
    res.status(201).json({ ok: true, id });
  } catch (err: unknown) {
    req.log.error({ err }, "Think Tank: failed to upsert article");
    res.status(500).json({ error: "Failed to save article" });
  }
});

router.delete("/think-tank/articles/:id", async (req, res) => {
  try {
    await deleteArticle(req.params.id);
    req.log.info({ id: req.params.id }, "Think Tank: article deleted");
    res.json({ ok: true });
  } catch (err: unknown) {
    req.log.error({ id: req.params.id, err }, "Think Tank: failed to delete article");
    res.status(500).json({ error: "Failed to delete article" });
  }
});

// ─── Outcomes ─────────────────────────────────────────────────────────────────

const OutcomeSchema = z.object({
  member_id:        z.string().min(1),
  protocol_ref:     z.string().optional(),
  article_id:       z.string().optional(),
  outcome_type:     z.enum(["improvement", "no_change", "regression", "testimonial"]),
  body_score_before: z.number().int().min(0).max(100).optional(),
  body_score_after:  z.number().int().min(0).max(100).optional(),
  timeframe_days:    z.number().int().min(1).optional(),
  concern:           z.string().max(500).optional(),
  protocol_summary:  z.string().max(2000).optional(),
  outcome_notes:     z.string().max(3000).optional(),
  testimonial_text:  z.string().max(2000).optional(),
  recorded_by:       z.enum(["system", "admin", "member"]).default("admin"),
});

router.get("/think-tank/outcomes", async (req, res) => {
  try {
    const outcomes = await getAllOutcomes();
    res.json({ outcomes, total: outcomes.length });
  } catch (err: unknown) {
    req.log.error({ err }, "Think Tank: failed to list outcomes");
    res.status(500).json({ error: "Failed to load outcomes" });
  }
});

router.post("/think-tank/outcomes", async (req, res) => {
  const parsed = OutcomeSchema.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  const d = parsed.data;
  const id = randomUUID();

  try {
    await insertOutcome({
      id,
      memberId:        d.member_id,
      protocolRef:     d.protocol_ref     ?? null,
      articleId:       d.article_id       ?? null,
      outcomeType:     d.outcome_type,
      bodyScoreBefore: d.body_score_before ?? null,
      bodyScoreAfter:  d.body_score_after  ?? null,
      timeframeDays:   d.timeframe_days    ?? null,
      concern:         d.concern           ?? null,
      protocolSummary: d.protocol_summary  ?? null,
      outcomeNotes:    d.outcome_notes     ?? null,
      testimonialText: d.testimonial_text  ?? null,
      recordedBy:      d.recorded_by,
    });
    req.log.info({ id, member_id: d.member_id, type: d.outcome_type }, "Think Tank: outcome recorded");
    res.status(201).json({ ok: true, id });
  } catch (err: unknown) {
    req.log.error({ err }, "Think Tank: failed to record outcome");
    res.status(500).json({ error: "Failed to record outcome" });
  }
});

// ─── Context preview ──────────────────────────────────────────────────────────

router.get("/think-tank/context", async (req, res) => {
  try {
    const context = await buildThinkTankContext();
    res.json({ context, char_count: context.length });
  } catch (err: unknown) {
    req.log.error({ err }, "Think Tank: failed to build context");
    res.status(500).json({ error: "Failed to build context" });
  }
});

export default router;
