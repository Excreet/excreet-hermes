import { Router, type IRouter } from "express";
import { z } from "zod/v4";
import {
  registerReferral,
  assignReferral,
  getAffiliateDashboard,
  runMonthlyCredit,
  triggerPayouts,
  markW9Completed,
  ensureAffiliateAccount,
  resolveReferralCode,
  getAffiliateCode,
  backfillReferralCodes,
} from "../../lib/affiliateStore.js";

const router: IRouter = Router();

/**
 * POST /api/hermes/affiliate/provision
 *
 * Called by WordPress immediately after a successful PMPro checkout.
 * Creates (or returns existing) affiliate account + referral code.
 * Idempotent — safe to call on every checkout.
 *
 * Body: { member_id }
 */
router.post("/affiliate/provision", async (req, res) => {
  const schema = z.object({ member_id: z.string().min(1) });
  const parsed = schema.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  try {
    const { referralCode, shareUrl } = await getAffiliateCode(parsed.data.member_id);
    req.log.info({ member_id: parsed.data.member_id, referralCode }, "Affiliate: account provisioned");
    res.status(200).json({ ok: true, referral_code: referralCode, share_url: shareUrl });
  } catch (err: unknown) {
    req.log.error({ err }, "Affiliate provision: DB error");
    res.status(500).json({ error: "internal_error" });
  }
});

/**
 * GET /api/hermes/affiliate/code/:memberId
 *
 * Returns the referral code and shareable URL for a member.
 * Creates the affiliate account (and code) if one doesn't exist yet.
 */
router.get("/affiliate/code/:memberId", async (req, res) => {
  const { memberId } = req.params;
  if (!memberId) {
    res.status(400).json({ error: "member_id required" });
    return;
  }

  try {
    const { referralCode, shareUrl } = await getAffiliateCode(memberId);
    res.json({ member_id: memberId, referral_code: referralCode, share_url: shareUrl });
  } catch (err: unknown) {
    req.log.error({ memberId, err }, "Affiliate code: DB error");
    res.status(500).json({ error: "internal_error" });
  }
});

/**
 * POST /api/hermes/affiliate/resolve-code
 *
 * Resolves a referral code to the referrer's memberId.
 * WordPress calls this during checkout to turn a code into a member ID
 * before calling /affiliate/register.
 *
 * Body: { referral_code }
 */
router.post("/affiliate/resolve-code", async (req, res) => {
  const schema = z.object({ referral_code: z.string().min(1) });
  const parsed = schema.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  try {
    const memberId = await resolveReferralCode(parsed.data.referral_code);
    if (!memberId) {
      res.status(404).json({ error: "code_not_found" });
      return;
    }
    res.json({ ok: true, member_id: memberId });
  } catch (err: unknown) {
    req.log.error({ err }, "Affiliate resolve-code: DB error");
    res.status(500).json({ error: "internal_error" });
  }
});

/**
 * POST /api/hermes/affiliate/register
 *
 * Called by WordPress immediately after a successful PMPro checkout
 * when the new member supplied a referral code.
 *
 * Body: { referrer_member_id, referred_member_id, referred_level }
 */
router.post("/affiliate/register", async (req, res) => {
  const schema = z.object({
    referrer_member_id: z.string().min(1),
    referred_member_id: z.string().min(1),
    referred_level:     z.number().int().min(1).max(2),
  });

  const parsed = schema.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  const { referrer_member_id, referred_member_id, referred_level } = parsed.data;

  if (referrer_member_id === referred_member_id) {
    res.status(400).json({ error: "self_referral_not_allowed" });
    return;
  }

  try {
    const ok = await registerReferral(referrer_member_id, referred_member_id, referred_level);
    if (!ok) {
      req.log.warn({ referred_member_id }, "Affiliate register: referred member already has a referral");
      res.status(409).json({ error: "already_referred" });
      return;
    }

    req.log.info({ referrer_member_id, referred_member_id, referred_level }, "Affiliate: referral registered");
    res.status(201).json({ ok: true });
  } catch (err: unknown) {
    req.log.error({ err }, "Affiliate register: DB error");
    res.status(500).json({ error: "internal_error" });
  }
});

/**
 * GET /api/hermes/affiliate/dashboard/:memberId
 *
 * Returns full affiliate dashboard data for a member.
 */
router.get("/affiliate/dashboard/:memberId", async (req, res) => {
  const { memberId } = req.params;
  if (!memberId) {
    res.status(400).json({ error: "member_id required" });
    return;
  }

  try {
    const data = await getAffiliateDashboard(memberId);

    res.json({
      member_id:            memberId,
      referral_code:        data.account.referralCode ?? null,
      share_url:            data.account.referralCode
                              ? `https://excreet.com/?ref=${data.account.referralCode}`
                              : null,
      w9_status:            data.account.w9Status,
      payout_balance_cents: data.account.payoutBalanceCents,
      total_earned_cents:   data.account.totalEarnedCents,
      referrals: data.referrals.map((r) => ({
        referred_member_id: r.referredMemberId,
        referred_level:     r.referredLevel,
        status:             r.status,
        joined_at:          r.joinedAt,
        credit_cleared_at:  r.creditClearedAt,
      })),
      payouts: data.payouts.map((p) => ({
        amount_cents:  p.amountCents,
        status:        p.status,
        period_start:  p.periodStart,
        period_end:    p.periodEnd,
        created_at:    p.createdAt,
      })),
    });
  } catch (err: unknown) {
    req.log.error({ memberId, err }, "Affiliate dashboard: DB error");
    res.status(500).json({ error: "internal_error" });
  }
});

/**
 * POST /api/hermes/affiliate/w9/complete
 */
router.post("/affiliate/w9/complete", async (req, res) => {
  const schema = z.object({ member_id: z.string().min(1) });
  const parsed = schema.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  try {
    await markW9Completed(parsed.data.member_id);
    req.log.info({ member_id: parsed.data.member_id }, "Affiliate: W-9 marked completed");
    res.json({ ok: true });
  } catch (err: unknown) {
    req.log.error({ err }, "Affiliate W-9 complete: DB error");
    res.status(500).json({ error: "internal_error" });
  }
});

/**
 * POST /api/hermes/affiliate/credit/batch
 *
 * Monthly credit run. Called by WordPress cron.
 */
router.post("/affiliate/credit/batch", async (req, res) => {
  const schema = z.object({
    active_member_ids: z.array(z.string().min(1)).min(1),
  });

  const parsed = schema.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  try {
    const result = await runMonthlyCredit(parsed.data.active_member_ids);
    req.log.info(result, "Affiliate: monthly credit run complete");
    res.json({ ok: true, ...result });
  } catch (err: unknown) {
    req.log.error({ err }, "Affiliate credit batch: error");
    res.status(500).json({ error: "internal_error" });
  }
});

/**
 * POST /api/hermes/affiliate/payout/trigger
 *
 * Bi-weekly payout trigger. Called by WordPress cron.
 */
router.post("/affiliate/payout/trigger", async (req, res) => {
  const schema = z.object({
    period_start: z.string().min(1),
    period_end:   z.string().min(1),
  });

  const parsed = schema.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  const periodStart = new Date(parsed.data.period_start);
  const periodEnd   = new Date(parsed.data.period_end);

  if (isNaN(periodStart.getTime()) || isNaN(periodEnd.getTime())) {
    res.status(400).json({ error: "invalid_dates" });
    return;
  }

  try {
    const result = await triggerPayouts(periodStart, periodEnd);
    req.log.info(result, "Affiliate: payout trigger complete");
    res.json({ ok: true, ...result });
  } catch (err: unknown) {
    req.log.error({ err }, "Affiliate payout trigger: error");
    res.status(500).json({ error: "internal_error" });
  }
});

/**
 * POST /api/hermes/affiliate/assign-referral
 *
 * Admin-only retroactive referral assignment. Skips the 30-day hold.
 */
router.post("/affiliate/assign-referral", async (req, res) => {
  const schema = z.object({
    new_member_id:  z.string().min(1),
    referrer_id:    z.string().min(1),
    referred_level: z.number().int().min(1).max(2).default(1),
  });

  const parsed = schema.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  const { new_member_id, referrer_id, referred_level } = parsed.data;

  try {
    const result = await assignReferral(referrer_id, new_member_id, referred_level);
    req.log.info({ referrer_id, new_member_id, referred_level, ...result }, "Affiliate: admin referral assignment");
    res.json({ ok: true, ...result });
  } catch (err: unknown) {
    req.log.error({ err }, "Affiliate assign-referral: error");
    res.status(500).json({ error: "internal_error" });
  }
});

/**
 * POST /api/hermes/affiliate/backfill-codes
 *
 * Admin-only: assigns referral codes to any existing accounts that don't
 * have one yet (accounts created before codes were introduced).
 */
router.post("/affiliate/backfill-codes", async (req, res) => {
  try {
    const updated = await backfillReferralCodes();
    req.log.info({ updated }, "Affiliate: backfill codes complete");
    res.json({ ok: true, updated });
  } catch (err: unknown) {
    req.log.error({ err }, "Affiliate backfill-codes: error");
    res.status(500).json({ error: "internal_error" });
  }
});

export default router;
