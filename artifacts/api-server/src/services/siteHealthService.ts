import { db, siteHealthChecksTable } from "@workspace/db";
import { desc } from "drizzle-orm";
import { logger } from "../lib/logger.js";
import { sendOwnerAlert, smsConfigured } from "./smsService.js";
import { sendOwnerEmail, emailConfigured } from "./emailService.js";

let lastAlertSentAt = 0;
const ALERT_COOLDOWN_MS = 30 * 60 * 1000;

export interface PageCheck {
  page: string;
  url: string;
  expects: string[];
}

export interface PageCheckResult {
  page: string;
  url: string;
  status: "ok" | "fail" | "error";
  httpStatus: number | null;
  responseMs: number | null;
  failedChecks: string[];
  checkedAt: string;
}

const INTERNAL_PORT = process.env["PORT"] ?? "8080";
const INTERNAL_BASE = `http://localhost:${INTERNAL_PORT}`;

const ENDPOINT_CHECKS: PageCheck[] = [
  {
    page: "EMC Endpoint",
    url: `${INTERNAL_BASE}/api/hermes/emc/ping`,
    expects: ['"ok"'],
  },
  {
    page: "TMC Endpoint",
    url: `${INTERNAL_BASE}/api/hermes/tmc/ping`,
    expects: ['"ok"'],
  },
  {
    page: "NMC Endpoint",
    url: `${INTERNAL_BASE}/api/hermes/nmc/ping`,
    expects: ['"ok"'],
  },
];

// Functional auth check: POST a tiny sentinel to the analyze endpoint.
// A 400 (bad image) means auth passed — endpoint is reachable by card users.
// A 401 means auth middleware is incorrectly blocking card traffic.
async function checkEmcAuth(): Promise<PageCheckResult> {
  const url = `${INTERNAL_BASE}/api/hermes/emc/analyze`;
  const start = Date.now();
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 10_000);
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ image: "sentinel-health-check" }),
      signal: controller.signal,
    });
    clearTimeout(timer);
    const responseMs = Date.now() - start;
    const httpStatus = res.status;
    const failedChecks: string[] = [];
    if (httpStatus === 401) {
      failedChecks.push("EMC analyze returned 401 — auth is blocking card users");
    }
    return {
      page: "EMC Auth Check",
      url,
      status: failedChecks.length === 0 ? "ok" : "fail",
      httpStatus,
      responseMs,
      failedChecks,
      checkedAt: new Date().toISOString(),
    };
  } catch (err) {
    return {
      page: "EMC Auth Check",
      url,
      status: "error",
      httpStatus: null,
      responseMs: Date.now() - start,
      failedChecks: [`Fetch failed: ${String(err)}`],
      checkedAt: new Date().toISOString(),
    };
  }
}

// Same check for TMC
async function checkTmcAuth(): Promise<PageCheckResult> {
  const url = `${INTERNAL_BASE}/api/hermes/tmc/analyze`;
  const start = Date.now();
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 10_000);
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ image: "sentinel-health-check" }),
      signal: controller.signal,
    });
    clearTimeout(timer);
    const responseMs = Date.now() - start;
    const httpStatus = res.status;
    const failedChecks: string[] = [];
    if (httpStatus === 401) {
      failedChecks.push("TMC analyze returned 401 — auth is blocking card users");
    }
    return {
      page: "TMC Auth Check",
      url,
      status: failedChecks.length === 0 ? "ok" : "fail",
      httpStatus,
      responseMs,
      failedChecks,
      checkedAt: new Date().toISOString(),
    };
  } catch (err) {
    return {
      page: "TMC Auth Check",
      url,
      status: "error",
      httpStatus: null,
      responseMs: Date.now() - start,
      failedChecks: [`Fetch failed: ${String(err)}`],
      checkedAt: new Date().toISOString(),
    };
  }
}

// Same check for NMC
async function checkNmcAuth(): Promise<PageCheckResult> {
  const url = `${INTERNAL_BASE}/api/hermes/nmc/analyze`;
  const start = Date.now();
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 10_000);
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ image: "sentinel-health-check" }),
      signal: controller.signal,
    });
    clearTimeout(timer);
    const responseMs = Date.now() - start;
    const httpStatus = res.status;
    const failedChecks: string[] = [];
    if (httpStatus === 401) {
      failedChecks.push("NMC analyze returned 401 — auth is blocking card users");
    }
    return {
      page: "NMC Auth Check",
      url,
      status: failedChecks.length === 0 ? "ok" : "fail",
      httpStatus,
      responseMs,
      failedChecks,
      checkedAt: new Date().toISOString(),
    };
  } catch (err) {
    return {
      page: "NMC Auth Check",
      url,
      status: "error",
      httpStatus: null,
      responseMs: Date.now() - start,
      failedChecks: [`Fetch failed: ${String(err)}`],
      checkedAt: new Date().toISOString(),
    };
  }
}

const PAGE_CHECKS: PageCheck[] = [
  {
    page: "Homepage",
    url: "https://excreet.com/",
    expects: ["excreet", "Become a Member"],
  },
  {
    page: "Explore",
    url: "https://excreet.com/explore/",
    expects: ["explore", "excreet"],
  },
  {
    page: "Membership Levels",
    url: "https://excreet.com/membership-levels/",
    expects: ["Starter", "Premium", "15.00", "25.00"],
  },
  {
    page: "Membership Checkout",
    url: "https://excreet.com/membership-checkout/?pmpro_level=1",
    expects: ["pmpro_checkout", "Starter"],
  },
  {
    page: "Member Login",
    url: "https://excreet.com/member-login/",
    expects: ["password", "login"],
  },
  {
    page: "Know the Signals",
    url: "https://excreet.com/know-the-signals/",
    expects: ["signal", "excreet"],
  },
  {
    page: "Welcome Member",
    url: "https://excreet.com/welcome-member/",
    expects: ["welcome", "member"],
  },
  {
    page: "Ministry of Healing",
    url: "https://excreet.com/?page_id=231",
    expects: ["excreet"],
  },
  {
    page: "Member Dashboard",
    url: "https://excreet.com/?page_id=772",
    expects: ["excreet"],
  },
  {
    page: "Terms of Use",
    url: "https://excreet.com/?page_id=7",
    expects: ["excreet"],
  },
  {
    page: "Privacy Policy",
    url: "https://excreet.com/?page_id=3",
    expects: ["excreet"],
  },
  {
    page: "Affiliate Area",
    url: "https://excreet.com/affiliate-area/",
    expects: ["affiliate", "referral"],
  },
  {
    page: "Provider Report",
    url: "https://excreet.com/provider-report/",
    expects: ["provider", "report"],
  },
];

async function checkPage(check: PageCheck): Promise<PageCheckResult> {
  const start = Date.now();
  const failedChecks: string[] = [];
  let httpStatus: number | null = null;
  let responseMs: number | null = null;

  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 15_000);

    let body = "";
    try {
      const res = await fetch(check.url, {
        signal: controller.signal,
        headers: { "User-Agent": "ExcreetHealthMonitor/1.0" },
      });
      clearTimeout(timer);
      responseMs = Date.now() - start;
      httpStatus = res.status;

      if (res.status !== 200) {
        failedChecks.push(`HTTP ${res.status} (expected 200)`);
      } else {
        body = await res.text();
      }
    } catch (fetchErr) {
      clearTimeout(timer);
      responseMs = Date.now() - start;
      const msg = fetchErr instanceof Error ? fetchErr.message : String(fetchErr);
      failedChecks.push(`Fetch failed: ${msg}`);
      return {
        page: check.page,
        url: check.url,
        status: "error",
        httpStatus: null,
        responseMs,
        failedChecks,
        checkedAt: new Date().toISOString(),
      };
    }

    if (body) {
      const lower = body.toLowerCase();
      for (const term of check.expects) {
        if (!lower.includes(term.toLowerCase())) {
          failedChecks.push(`Missing: "${term}"`);
        }
      }
    }

    const status = failedChecks.length === 0 ? "ok" : "fail";

    return {
      page: check.page,
      url: check.url,
      status,
      httpStatus,
      responseMs,
      failedChecks,
      checkedAt: new Date().toISOString(),
    };
  } catch (err) {
    return {
      page: check.page,
      url: check.url,
      status: "error",
      httpStatus,
      responseMs: responseMs ?? Date.now() - start,
      failedChecks: [`Unexpected error: ${String(err)}`],
      checkedAt: new Date().toISOString(),
    };
  }
}

export async function runSiteHealthChecks(): Promise<PageCheckResult[]> {
  logger.info("site-health: starting checks");
  const [pageResults, emcAuth, tmcAuth, nmcAuth] = await Promise.all([
    Promise.all([...PAGE_CHECKS, ...ENDPOINT_CHECKS].map(checkPage)),
    checkEmcAuth(),
    checkTmcAuth(),
    checkNmcAuth(),
  ]);
  const results = [...pageResults, emcAuth, tmcAuth, nmcAuth];

  const failing = results.filter(r => r.status !== "ok");
  logger.info(
    { total: results.length, failing: failing.length },
    "site-health: checks complete",
  );
  if (failing.length > 0) {
    for (const f of failing) {
      logger.warn({ page: f.page, issues: f.failedChecks }, "site-health: page issue detected");
    }
    const now = Date.now();
    if (now - lastAlertSentAt > ALERT_COOLDOWN_MS) {
      lastAlertSentAt = now;
      const pageList = failing.map(f => `• ${f.page}: ${f.failedChecks.join(", ")}`).join("\n");
      const subject = `🚨 Excreet Alert — ${failing.length} issue(s) detected`;
      const msg = `EXCREET SITE MONITOR\n\n${failing.length} check(s) failing:\n\n${pageList}\n\nCheck: excreet.com\nTime: ${new Date().toUTCString()}`;

      if (emailConfigured()) {
        sendOwnerEmail(subject, msg).catch(err =>
          logger.error({ err }, "site-health: email alert failed"),
        );
      }

      if (smsConfigured()) {
        const smsMsg = `🚨 EXCREET ALERT\n${failing.length} issue(s):\n${pageList}\nexcreet.com`;
        sendOwnerAlert(smsMsg).catch(err =>
          logger.error({ err }, "site-health: sms alert failed"),
        );
      }

      if (!emailConfigured() && !smsConfigured()) {
        logger.warn("site-health: issues detected but no alert channel configured (set OWNER_EMAIL + SMTP_USER + SMTP_PASS)");
      }
    }
  }

  await db.insert(siteHealthChecksTable).values(
    results.map(r => ({
      page:         r.page,
      url:          r.url,
      status:       r.status,
      httpStatus:   r.httpStatus,
      responseMs:   r.responseMs,
      failedChecks: r.failedChecks,
    })),
  );

  return results;
}

export async function getLatestHealthResults(): Promise<PageCheckResult[]> {
  const rows = await db
    .select()
    .from(siteHealthChecksTable)
    .orderBy(desc(siteHealthChecksTable.checkedAt))
    .limit(500);

  if (rows.length === 0) return [];

  const latestByPage = new Map<string, typeof rows[0]>();
  for (const row of rows) {
    if (!latestByPage.has(row.page)) {
      latestByPage.set(row.page, row);
    }
  }

  return Array.from(latestByPage.values()).map(r => ({
    page:         r.page,
    url:          r.url,
    status:       r.status as "ok" | "fail" | "error",
    httpStatus:   r.httpStatus,
    responseMs:   r.responseMs,
    failedChecks: (r.failedChecks as string[]) ?? [],
    checkedAt:    r.checkedAt.toISOString(),
  }));
}

export function startSiteHealthScheduler(): void {
  const INTERVAL_MS = 30 * 60 * 1000;

  setTimeout(async () => {
    try {
      await runSiteHealthChecks();
    } catch (err) {
      logger.error({ err }, "site-health: scheduled check failed");
    }
  }, 5 * 60 * 1000);

  setInterval(async () => {
    try {
      await runSiteHealthChecks();
    } catch (err) {
      logger.error({ err }, "site-health: scheduled check failed");
    }
  }, INTERVAL_MS);

  logger.info("site-health: scheduler started (interval=30m, first-run=5m)");
}

export { PAGE_CHECKS };
