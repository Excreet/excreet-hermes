import { Router } from "express";
import { db, memberSmsPrefsTable } from "@workspace/db";
import { eq } from "drizzle-orm";
import {
  sendOwnerAlert,
  sendMemberReminder,
  smsConfigured,
  whatsappConfigured,
  type Channel,
} from "../../services/smsService.js";
import { runSiteHealthChecks } from "../../services/siteHealthService.js";
import { logger } from "../../lib/logger.js";

const router = Router();

/**
 * POST /api/hermes/sms/opt-in
 * WordPress calls this when a member saves their phone number.
 * Body: { memberId, phoneNumber, channel?, timezone? }
 * channel: "sms" (default, US only) | "whatsapp" (international)
 */
router.post("/sms/opt-in", async (req, res) => {
  const { memberId, phoneNumber, channel = "sms", timezone } = req.body as {
    memberId?: string;
    phoneNumber?: string;
    channel?: string;
    timezone?: string;
  };

  if (!memberId || !phoneNumber) {
    res.status(400).json({ ok: false, error: "memberId and phoneNumber required" });
    return;
  }

  const cleaned = phoneNumber.replace(/\D/g, "");
  if (cleaned.length < 10) {
    res.status(400).json({ ok: false, error: "Invalid phone number — must be at least 10 digits" });
    return;
  }
  const e164 = cleaned.startsWith("1") ? `+${cleaned}` : `+1${cleaned}`;
  const resolvedChannel: Channel = channel === "whatsapp" ? "whatsapp" : "sms";

  await db
    .insert(memberSmsPrefsTable)
    .values({
      memberId,
      phoneNumber: e164,
      optedIn: true,
      channel: resolvedChannel,
      timezone: timezone ?? "America/New_York",
    })
    .onConflictDoUpdate({
      target: memberSmsPrefsTable.memberId,
      set: {
        phoneNumber: e164,
        optedIn: true,
        channel: resolvedChannel,
        timezone: timezone ?? "America/New_York",
        updatedAt: new Date(),
      },
    });

  logger.info({ memberId, channel: resolvedChannel }, "sms: member opted in");
  res.json({ ok: true, channel: resolvedChannel });
});

/**
 * POST /api/hermes/sms/opt-out
 * Body: { memberId }
 */
router.post("/sms/opt-out", async (req, res) => {
  const { memberId } = req.body as { memberId?: string };
  if (!memberId) {
    res.status(400).json({ ok: false, error: "memberId required" });
    return;
  }
  await db
    .update(memberSmsPrefsTable)
    .set({ optedIn: false, updatedAt: new Date() })
    .where(eq(memberSmsPrefsTable.memberId, memberId));

  logger.info({ memberId }, "sms: member opted out");
  res.json({ ok: true });
});

/**
 * GET /api/hermes/sms/status/:memberId
 * Returns the member's current SMS/WhatsApp preferences.
 */
router.get("/sms/status/:memberId", async (req, res) => {
  const { memberId } = req.params;
  const rows = await db
    .select()
    .from(memberSmsPrefsTable)
    .where(eq(memberSmsPrefsTable.memberId, memberId))
    .limit(1);

  if (rows.length === 0) {
    res.json({ ok: true, enrolled: false });
    return;
  }
  const pref = rows[0]!;
  res.json({
    ok: true,
    enrolled: true,
    optedIn: pref.optedIn,
    channel: pref.channel,
    timezone: pref.timezone,
  });
});

/**
 * POST /api/hermes/sms/capabilities
 * Returns which channels are currently configured on the server.
 */
router.post("/sms/capabilities", (_req, res) => {
  res.json({
    sms: smsConfigured(),
    whatsapp: whatsappConfigured(),
  });
});

/**
 * POST /api/hermes/sms/test-alert
 * Admin: fire a test SMS/WhatsApp to the owner right now.
 */
router.post("/sms/test-alert", async (_req, res) => {
  if (!smsConfigured()) {
    res.status(503).json({ ok: false, error: "Twilio not configured" });
    return;
  }
  await sendOwnerAlert("✅ EXCREET TEST ALERT — Twilio is working. Your monitoring SMS is active.");
  res.json({ ok: true, message: "Test alert sent to owner phone" });
});

/**
 * POST /api/hermes/sms/test-reminder
 * Admin: fire a test morning reminder to a specific number.
 * Body: { phoneNumber, channel? }
 */
router.post("/sms/test-reminder", async (req, res) => {
  const { phoneNumber, channel = "sms" } = req.body as {
    phoneNumber?: string;
    channel?: string;
  };
  if (!phoneNumber) {
    res.status(400).json({ ok: false, error: "phoneNumber required" });
    return;
  }
  if (!smsConfigured()) {
    res.status(503).json({ ok: false, error: "Twilio not configured" });
    return;
  }
  const resolvedChannel: Channel = channel === "whatsapp" ? "whatsapp" : "sms";
  await sendMemberReminder(phoneNumber, "", resolvedChannel);
  res.json({ ok: true, message: `Test reminder sent via ${resolvedChannel}` });
});

/**
 * POST /api/hermes/sms/trigger-health-check
 * Admin: run health check right now and alert if failing.
 */
router.post("/sms/trigger-health-check", async (_req, res) => {
  const results = await runSiteHealthChecks();
  const failing = results.filter(r => r.status !== "ok");
  res.json({
    ok: true,
    total: results.length,
    failing: failing.length,
    pages: results,
  });
});

export default router;
