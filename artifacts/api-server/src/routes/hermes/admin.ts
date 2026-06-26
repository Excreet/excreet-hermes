import { Router, type IRouter } from "express";
import { count, countDistinct, desc, eq } from "drizzle-orm";
import {
  db,
  bodySnapshotsTable,
  memberProtocolsTable,
  hermesJobsTable,
  siteHealthChecksTable,
} from "@workspace/db";
import { runMonthlyImageJob } from "../../lib/monthlyImageJob.js";
import { runSiteHealthChecks, getLatestHealthResults } from "../../services/siteHealthService.js";
import { sendOwnerEmail, emailConfigured } from "../../services/emailService.js";

const router: IRouter = Router();

/**
 * GET /api/hermes/admin/stats
 * Aggregate counts across all three tables.
 */
router.get("/admin/stats", async (req, res) => {
  try {
    const [snapTotal]     = await db.select({ n: count()                                      }).from(bodySnapshotsTable);
    const [snapMembers]   = await db.select({ n: countDistinct(bodySnapshotsTable.memberId)    }).from(bodySnapshotsTable);
    const [protoTotal]    = await db.select({ n: count()                                      }).from(memberProtocolsTable);
    const [protoMembers]  = await db.select({ n: countDistinct(memberProtocolsTable.memberId) }).from(memberProtocolsTable);
    const [jobTotal]      = await db.select({ n: count()                                      }).from(hermesJobsTable);
    const [jobCompleted]  = await db.select({ n: count() }).from(hermesJobsTable).where(eq(hermesJobsTable.status, "completed"));
    const [jobFailed]     = await db.select({ n: count() }).from(hermesJobsTable).where(eq(hermesJobsTable.status, "failed"));
    const [jobPending]    = await db.select({ n: count() }).from(hermesJobsTable).where(eq(hermesJobsTable.status, "pending"));
    const [jobProcessing] = await db.select({ n: count() }).from(hermesJobsTable).where(eq(hermesJobsTable.status, "processing"));

    res.json({
      snapshots: {
        total:          Number(snapTotal?.n   ?? 0),
        unique_members: Number(snapMembers?.n ?? 0),
      },
      protocols: {
        total:          Number(protoTotal?.n   ?? 0),
        unique_members: Number(protoMembers?.n ?? 0),
      },
      jobs: {
        total:      Number(jobTotal?.n      ?? 0),
        completed:  Number(jobCompleted?.n  ?? 0),
        failed:     Number(jobFailed?.n     ?? 0),
        pending:    Number(jobPending?.n    ?? 0),
        processing: Number(jobProcessing?.n ?? 0),
      },
    });
  } catch (err) {
    req.log.error({ err }, "admin/stats error");
    res.status(500).json({ error: "db_error" });
  }
});

/**
 * GET /api/hermes/admin/snapshots?limit=50
 * Recent body snapshots, newest first. Excludes full result JSONB.
 */
router.get("/admin/snapshots", async (req, res) => {
  const limit = Math.min(100, Math.max(1, Number(req.query.limit) || 50));
  try {
    const rows = await db
      .select({
        id:           bodySnapshotsTable.id,
        memberId:     bodySnapshotsTable.memberId,
        snapshotDate: bodySnapshotsTable.snapshotDate,
        bodyScore:    bodySnapshotsTable.bodyScore,
        tier:         bodySnapshotsTable.tier,
        createdAt:    bodySnapshotsTable.createdAt,
      })
      .from(bodySnapshotsTable)
      .orderBy(desc(bodySnapshotsTable.createdAt))
      .limit(limit);
    res.json({ snapshots: rows });
  } catch (err) {
    req.log.error({ err }, "admin/snapshots error");
    res.status(500).json({ error: "db_error" });
  }
});

/**
 * GET /api/hermes/admin/protocols?limit=50
 * Recent protocols, newest first. Excludes full protocol JSONB.
 */
router.get("/admin/protocols", async (req, res) => {
  const limit = Math.min(100, Math.max(1, Number(req.query.limit) || 50));
  try {
    const rows = await db
      .select({
        id:          memberProtocolsTable.id,
        memberId:    memberProtocolsTable.memberId,
        concern:     memberProtocolsTable.concern,
        generatedAt: memberProtocolsTable.generatedAt,
        createdAt:   memberProtocolsTable.createdAt,
      })
      .from(memberProtocolsTable)
      .orderBy(desc(memberProtocolsTable.generatedAt))
      .limit(limit);
    res.json({ protocols: rows });
  } catch (err) {
    req.log.error({ err }, "admin/protocols error");
    res.status(500).json({ error: "db_error" });
  }
});

/**
 * GET /api/hermes/admin/jobs?limit=50
 * Recent jobs, newest first. Excludes payload and result JSONB.
 */
router.get("/admin/jobs", async (req, res) => {
  const limit = Math.min(100, Math.max(1, Number(req.query.limit) || 50));
  try {
    const rows = await db
      .select({
        jobId:        hermesJobsTable.jobId,
        status:       hermesJobsTable.status,
        memberId:     hermesJobsTable.memberId,
        workflowType: hermesJobsTable.workflowType,
        error:        hermesJobsTable.error,
        createdAt:    hermesJobsTable.createdAt,
        updatedAt:    hermesJobsTable.updatedAt,
      })
      .from(hermesJobsTable)
      .orderBy(desc(hermesJobsTable.createdAt))
      .limit(limit);
    res.json({ jobs: rows });
  } catch (err) {
    req.log.error({ err }, "admin/jobs error");
    res.status(500).json({ error: "db_error" });
  }
});

/**
 * POST /api/hermes/admin/rotate-background
 * Manually trigger the monthly bathroom background generation for any month.
 * Body (optional): { "month": 6 }  — defaults to current month if omitted.
 * Protected by the standard HERMES_API_KEY bearer auth middleware.
 */
router.post("/admin/rotate-background", async (req, res) => {
  const rawMonth = req.body?.month;
  const month = rawMonth !== undefined ? Number(rawMonth) : undefined;

  if (month !== undefined && (isNaN(month) || month < 1 || month > 12)) {
    res.status(400).json({ error: "month must be 1–12" });
    return;
  }

  req.log.info({ month }, "admin/rotate-background: triggered");

  try {
    const result = await runMonthlyImageJob(month);
    res.json({
      ok:    true,
      month: result.month,
      file:  result.file,
      msg:   `healer-bg-${String(result.month).padStart(2, "0")}.jpg deployed to SiteGround`,
    });
  } catch (err) {
    req.log.error({ err }, "admin/rotate-background: failed");
    res.status(500).json({ error: "image_job_failed", detail: String(err) });
  }
});

/**
 * GET /api/hermes/admin/site-health
 * Returns the latest health check result for every monitored page.
 */
router.get("/admin/site-health", async (req, res) => {
  try {
    const results = await getLatestHealthResults();
    const failing = results.filter(r => r.status !== "ok").length;
    res.json({ results, summary: { total: results.length, failing } });
  } catch (err) {
    req.log.error({ err }, "admin/site-health error");
    res.status(500).json({ error: "db_error" });
  }
});

/**
 * POST /api/hermes/admin/site-health/run
 * Triggers a fresh health check of all monitored pages right now.
 */
router.post("/admin/site-health/run", async (req, res) => {
  req.log.info("admin/site-health/run: triggered");
  try {
    const results = await runSiteHealthChecks();
    const failing = results.filter(r => r.status !== "ok").length;
    res.json({ ok: true, results, summary: { total: results.length, failing } });
  } catch (err) {
    req.log.error({ err }, "admin/site-health/run error");
    res.status(500).json({ error: "check_failed", detail: String(err) });
  }
});

/**
 * GET /api/hermes/admin/site-health/history?page=Membership+Levels&limit=20
 * Returns the last N check results for a specific page, newest first.
 */
router.get("/admin/site-health/history", async (req, res) => {
  const page  = String(req.query.page ?? "");
  const limit = Math.min(50, Math.max(1, Number(req.query.limit) || 20));
  if (!page) {
    res.status(400).json({ error: "page query param required" });
    return;
  }
  try {
    const rows = await db
      .select()
      .from(siteHealthChecksTable)
      .where(eq(siteHealthChecksTable.page, page))
      .orderBy(desc(siteHealthChecksTable.checkedAt))
      .limit(limit);
    res.json({ history: rows });
  } catch (err) {
    req.log.error({ err }, "admin/site-health/history error");
    res.status(500).json({ error: "db_error" });
  }
});

/* ── In-memory member count cache ── */
interface MemberLevel { id: number; name: string; count: number }
interface MemberCounts {
  total: number;
  new_last_30: number;
  by_level: MemberLevel[];
  fetched_at: string;
  cached_at: string;
}
let _memberCache: MemberCounts | null = null;
let _memberCacheMs = 0;
const MEMBER_CACHE_TTL = 5 * 60 * 1000; // 5 minutes

/**
 * GET /api/hermes/admin/members
 * Fetches live PMPro member counts from WordPress, cached 5 minutes.
 */
router.get("/admin/members", async (req, res) => {
  const now = Date.now();
  if (_memberCache && now - _memberCacheMs < MEMBER_CACHE_TTL) {
    res.json(_memberCache);
    return;
  }

  const wpKey = process.env.EXCREET_WP_MEMBER_KEY;
  const wpUrl = "https://excreet.com/wp-json/excreet/v1/members/count";

  if (!wpKey) {
    req.log.warn("EXCREET_WP_MEMBER_KEY not set — cannot fetch member counts");
    res.status(503).json({ error: "not_configured", message: "WP member key not configured." });
    return;
  }

  try {
    const r = await fetch(wpUrl, {
      headers: { Authorization: `Bearer ${wpKey}` },
      signal: AbortSignal.timeout(8000),
    });

    if (!r.ok) {
      const body = await r.text();
      req.log.warn({ status: r.status, body }, "WP member count endpoint returned non-200");
      if (_memberCache) { res.json(_memberCache); return; }
      res.status(502).json({ error: "wp_error", status: r.status });
      return;
    }

    const data = await r.json() as Omit<MemberCounts, "cached_at">;
    _memberCache = { ...data, cached_at: new Date().toISOString() };
    _memberCacheMs = now;
    req.log.info({ total: data.total }, "member counts refreshed from WP");
    res.json(_memberCache);
  } catch (err) {
    req.log.error({ err }, "admin/members fetch error");
    if (_memberCache) { res.json(_memberCache); return; }
    res.status(502).json({ error: "fetch_failed", detail: String(err) });
  }
});

/**
 * POST /api/hermes/admin/test-email
 * Sends a test alert email to OWNER_EMAIL to verify Resend is configured.
 */
router.post("/admin/test-email", async (req, res) => {
  if (!emailConfigured()) {
    res.status(503).json({
      ok: false,
      error: "not_configured",
      message: "RESEND_API_KEY or OWNER_EMAIL secret is missing.",
    });
    return;
  }
  try {
    await sendOwnerEmail(
      "✅ Excreet Hermes — Email Alerts Working",
      `This is a test alert from Excreet Hermes.\n\nEmail alerts are configured correctly.\nSite health issues will be sent to this address.\n\nTime: ${new Date().toUTCString()}`,
    );
    res.json({ ok: true, message: "Test email sent — check your inbox." });
  } catch (err) {
    req.log.error({ err }, "admin/test-email: failed");
    res.status(500).json({ ok: false, error: "send_failed", detail: String(err) });
  }
});

export default router;

