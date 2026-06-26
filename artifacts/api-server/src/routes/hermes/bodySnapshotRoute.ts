import { Router, type IRouter } from "express";
import { z } from "zod/v4";
import { bodySnapshot } from "../../services/ai/workflows/bodySnapshot.js";
import {
  saveSnapshot,
  getTodaySnapshot,
  getSnapshotHistory,
} from "../../lib/bodySnapshotStore.js";

const router: IRouter = Router();

const PhotosSchema = z.object({
  urine:        z.string().optional(),
  bowel:        z.string().optional(),
  reagentStrip: z.string().optional(),
  urineStrip:   z.string().optional(),
  salivaStrip:  z.string().optional(),
}).default({});

const QuestionnaireSchema = z.object({
  energyLevel:         z.string().max(3).optional(),
  mood:                z.string().max(3).optional(),
  symptomIntensity:    z.string().max(3).optional(),
  waterOz:             z.string().max(20).optional(),
  bowelToday:          z.string().max(5).optional(),
  bowelMinutes:        z.string().max(10).optional(),
  bowelUncomfortable:  z.string().max(5).optional(),
  stoolOdor:           z.string().max(5).optional(),
  urineOdor:           z.string().max(5).optional(),
  urineUncomfortable:  z.string().max(5).optional(),
  urineColor:          z.string().max(50).optional(),
  bodyTemp:            z.string().max(10).optional(),
  zipCode:             z.string().max(10).optional(),
  odor:                z.string().max(200).optional(),
  liquidType:          z.string().max(100).optional(),
  liquidVolume:        z.string().max(100).optional(),
  snapshotMode:        z.enum(['quick', 'full']).optional(),
}).default({});

const BodySnapshotSchema = z.object({
  member_id:     z.string().min(1),
  photos:        PhotosSchema,
  questionnaire: QuestionnaireSchema,
});

/**
 * GET /api/hermes/body-snapshot/today/:memberId
 *
 * Returns the member's body snapshot for today (UTC) if one exists.
 * WordPress calls this on page load to check before showing the wizard.
 * Returns 200 + { result, member_id, cached: true } if found, 404 if not.
 */
router.get("/body-snapshot/today/:memberId", async (req, res) => {
  const memberId = req.params.memberId?.trim();
  if (!memberId) {
    res.status(400).json({ error: "missing_member_id" });
    return;
  }

  const snapshot = await getTodaySnapshot(memberId).catch((err) => {
    req.log.error({ err, memberId }, "body-snapshot/today — DB error");
    return undefined;
  });

  if (!snapshot) {
    res.status(404).json({ error: "not_found", message: "No snapshot for today." });
    return;
  }

  req.log.info({ memberId, bodyScore: snapshot.bodyScore }, "body-snapshot/today — served from DB");
  res.json({ result: snapshot.result, member_id: memberId, cached: true });
});

/**
 * GET /api/hermes/body-snapshot/history/:memberId
 *
 * Returns up to 30 past snapshots for a member, newest first.
 * Each item includes: snapshotDate, bodyScore, tier (not full result).
 */
router.get("/body-snapshot/history/:memberId", async (req, res) => {
  const memberId = req.params.memberId?.trim();
  if (!memberId) {
    res.status(400).json({ error: "missing_member_id" });
    return;
  }

  const rows = await getSnapshotHistory(memberId, 30).catch((err) => {
    req.log.error({ err, memberId }, "body-snapshot/history — DB error");
    return [];
  });

  const history = rows.map((r) => ({
    snapshotDate: r.snapshotDate,
    bodyScore:    r.bodyScore,
    tier:         r.tier,
    createdAt:    r.createdAt,
  }));

  res.json({ history, member_id: memberId });
});

/**
 * POST /api/hermes/body-snapshot
 *
 * 24/7 Body Snapshot — 5-step data collection:
 *   Step 1 — Wellbeing ratings (energy, mood, symptoms 1–10)
 *   Step 2 — Hydration & digestion (water intake, bowel movement details)
 *   Step 3 — Morning urine (odor, comfort, color)
 *   Step 4 — Vitals (body temperature, postal code)
 *   Step 5 — Photos (bowel, urine, urine pH strip, saliva pH strip — all required)
 *
 * Idempotent: if today's snapshot already exists for this member, the cached
 * result is returned immediately without re-running the AI.
 *
 * Protected by API key (requireApiKey applied in index.ts).
 */
router.post("/body-snapshot", async (req, res) => {
  const parsed = BodySnapshotSchema.safeParse(req.body);

  if (!parsed.success) {
    req.log.warn({ issues: parsed.error.issues }, "Body snapshot: invalid request body");
    res.status(400).json({
      error:   "validation_error",
      message: "Request body is invalid.",
      issues:  parsed.error.issues,
    });
    return;
  }

  const { member_id, photos, questionnaire } = parsed.data;

  const existing = await getTodaySnapshot(member_id).catch(() => undefined);
  if (existing) {
    req.log.info(
      { member_id, bodyScore: existing.bodyScore, tier: existing.tier },
      "Body snapshot — returning cached result from DB",
    );
    res.json({ result: existing.result, member_id, cached: true });
    return;
  }

  const hasPhotos = !!(
    photos.urine      ||
    photos.bowel      ||
    photos.urineStrip ||
    photos.salivaStrip ||
    photos.reagentStrip
  );

  req.log.info(
    {
      member_id,
      hasPhotos,
      hasWellbeing:     !!(questionnaire.energyLevel),
      hasQuestionnaire: Object.keys(questionnaire).length > 0,
    },
    "Body snapshot — calling AI",
  );

  let result;
  try {
    result = await bodySnapshot({ photos, questionnaire });
  } catch (err: unknown) {
    req.log.error({ member_id, err }, "Body snapshot — AI error");
    res.status(502).json({
      error:   "ai_error",
      message: "The body snapshot analysis is temporarily unavailable. Please try again.",
    });
    return;
  }

  try {
    await saveSnapshot(member_id, result);
    req.log.info({ member_id, bodyScore: result.bodyScore, tier: result.tier }, "Body snapshot — saved to DB");
  } catch (err) {
    req.log.error({ member_id, err }, "Body snapshot — DB save failed (result still returned)");
  }

  res.json({ result, member_id });
});

export default router;
