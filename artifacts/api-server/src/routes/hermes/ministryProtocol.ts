import { Router, type IRouter } from "express";
import { z } from "zod/v4";
import { anthropic } from "../../services/ai/anthropicClient.js";
import type Anthropic from "@anthropic-ai/sdk";
import { saveProtocol, getProtocolHistory } from "../../lib/protocolStore.js";

const router: IRouter = Router();

/* ── Per-member rate limiter ─────────────────────────────────────────────── */
// Max 3 protocol generations per member per rolling hour.
// In-memory — resets on server restart, acceptable for abuse prevention.

const RATE_LIMIT_MAX    = 3;
const RATE_LIMIT_WINDOW = 60 * 60 * 1000; // 1 hour ms

interface RateEntry { count: number; windowStart: number; }
const rateLimiter = new Map<string, RateEntry>();

function checkRateLimit(memberId: string): { allowed: boolean; retryAfterMs: number } {
  const now   = Date.now();
  const entry = rateLimiter.get(memberId);

  if (!entry || now - entry.windowStart >= RATE_LIMIT_WINDOW) {
    rateLimiter.set(memberId, { count: 1, windowStart: now });
    return { allowed: true, retryAfterMs: 0 };
  }

  if (entry.count >= RATE_LIMIT_MAX) {
    return { allowed: false, retryAfterMs: RATE_LIMIT_WINDOW - (now - entry.windowStart) };
  }

  entry.count++;
  return { allowed: true, retryAfterMs: 0 };
}

/* ── Attachment schema (mirrors ministry.ts) ─────────────────────────────── */

const ALLOWED_MIME_TYPES = [
  "application/pdf",
  "image/jpeg",
  "image/png",
  "image/webp",
  "image/gif",
] as const;

const AttachmentSchema = z.object({
  name:      z.string().max(255),
  mime_type: z.enum(ALLOWED_MIME_TYPES),
  data:      z.string().max(14_000_000), // base64 ~10 MB raw
});

/* ── Input schema ─────────────────────────────────────────────────────────── */

const IntakeDataSchema = z.object({
  age:         z.string().default(""),
  sex:         z.string().default(""),
  symptoms:    z.string().default(""),
  medications: z.string().default(""),
  concerns:    z.string().default(""),
  surgeries:   z.string().default(""),
  alias:       z.string().default(""),
});

const ProtocolRequestSchema = z.object({
  member_id:       z.string().min(1),
  current_concern: z.string().min(1).max(6000),
  intake_data:     IntakeDataSchema.optional().default(() => ({
    age: "", sex: "", symptoms: "", medications: "", concerns: "", surgeries: "", alias: "",
  })),
  attachments: z.array(AttachmentSchema).max(3).default([]),
});

/* ── Output schema ────────────────────────────────────────────────────────── */

const ProtocolOutputSchema = z.object({
  title:            z.string(),
  vitality_read:    z.string(),
  root_pattern:     z.string(),
  healing_approach: z.array(z.string()),
  dietary_protocol: z.array(z.string()),
  supplement_stack: z.array(z.string()),
  lifestyle_shifts: z.array(z.string()),
  labs_to_request:  z.array(z.string()),
  red_flags:        z.array(z.string()),
  follow_up:        z.string(),
  disclaimer:       z.string(),
});

export type ProtocolOutput = z.infer<typeof ProtocolOutputSchema>;

/* ── System prompt ────────────────────────────────────────────────────────── */

const PROTOCOL_SYSTEM_PROMPT = `
You are the Excreet Protocol Engine — the clinical intelligence core of Excreet WHealth.

Your task is to generate a complete, personalized Healing Protocol for an Excreet member by combining:
1. Their comprehensive intake history (age, sex, symptom patterns, current medications, health concerns, surgical history)
2. Their current presenting concern submitted today
3. Any documents, lab results, or images they have attached (if present, read them carefully and reference specific findings)

This protocol replaces a $450 in-person consultation. It must be specific, thorough, and immediately actionable. Vague or generic recommendations destroy its value. Every recommendation must be evidently tailored to THIS member, not a template.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OUTPUT SCHEMA — respond ONLY with valid JSON, no prose outside it
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

{
  "title": "Protocol name — specific to their pattern, not generic. E.g., 'Adrenal Recovery & Gut Restoration Protocol' not 'Wellness Plan'",

  "vitality_read": "2-3 sentences in Excreet pattern language. What the body appears to be navigating. Use: 'suggests', 'points toward', 'the pattern emerging', 'your system appears to be'. Never say: disease, disorder, condition, diagnosis, 'you have X'.",

  "root_pattern": "1-2 sentences identifying the systemic imbalance driving the presenting concern. Connect intake history to current concern. If labs were attached, reference specific values.",

  "healing_approach": ["Array of 3-5 sentences. Each is ONE sentence on a distinct strategic angle of the healing approach, chosen specifically for this member. Not generic wellness advice."],

  "dietary_protocol": ["Array of 6-8 specific dietary recommendations. Each is ONE complete instruction. Not 'eat healthy' — 'Remove all gluten and dairy for 21 days, then reintroduce one at a time to identify reactivity'. Make them specific enough to act on today."],

  "supplement_stack": ["Array of 4-7 supplements. Each entry: 'Supplement name (exact form matters — e.g., magnesium glycinate not magnesium oxide) — Dose and timing — Why it is indicated for this member'. One string per supplement."],

  "lifestyle_shifts": ["Array of 5-7 specific behavioral changes. Include timing and rationale. E.g., 'Morning sunlight exposure within 30 minutes of waking (10-15 minutes minimum) — anchors cortisol rhythm disrupted by the adrenal pattern in your intake'. Each is ONE complete instruction."],

  "labs_to_request": ["Array of 4-8 specific tests by exact clinical name. Include any relevant sub-panels. E.g., 'Full thyroid panel: TSH, Free T3, Free T4, Reverse T3, TPO antibodies, TgAb'. If labs were attached, prioritize follow-up tests based on what was out of range."],

  "red_flags": ["Array of 3-4 specific signs that mean seek immediate medical attention — relevant to their presenting concern. Concrete and specific, not generic."],

  "follow_up": "Specific timeline for reassessment and next steps. Reference both Excreet tools by their correct names: the Gut Snapshot dashboard (at excreet.com/healing-command-center) for tracking daily inputs and Healing Score progress, and the Ministry of Healing chat (same page as this protocol) for protocol questions or adjustments. E.g., 'Log your daily inputs in the Gut Snapshot dashboard for the next 21 days — your Healing Score will reflect the shift. If [specific symptom] persists past day 14, bring it to the Ministry of Healing and describe what has changed.'",

  "disclaimer": "This Healing Protocol is for educational and self-awareness purposes only. It does not constitute medical advice or establish a provider-patient relationship. Consult a qualified health professional before beginning any new supplement or dietary protocol, particularly if you are pregnant, nursing, or taking prescription medications."
}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TONE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Warm, expert, and empowering. Speak as a trusted health intelligence ally who has read their full file.
Reference their specific intake details in the protocol — it should feel unmistakably personal.
Be specific and actionable. The member is paying for precision, not general wellness advice.
`.trim();

/* ── Route ────────────────────────────────────────────────────────────────── */

/**
 * POST /api/hermes/ministry/protocol
 *
 * Generates a complete, personalized $29 Healing Protocol by combining:
 *   - member's baseline intake history (age, sex, symptoms, meds, concerns, surgeries)
 *   - their structured current intake (concern, symptoms timeline, what they've tried, etc.)
 *   - optional file attachments (lab results, reports, images)
 *
 * Synchronous — returns the full protocol directly (not a queued job).
 *
 * Body:    { member_id, current_concern, intake_data?, attachments? }
 * Returns: { protocol: ProtocolOutput, member_id, generated_at }
 */
router.post("/ministry/protocol", async (req, res) => {
  const parsed = ProtocolRequestSchema.safeParse(req.body);

  if (!parsed.success) {
    req.log.warn({ issues: parsed.error.issues }, "Protocol: invalid request body");
    res.status(400).json({
      error:   "validation_error",
      message: "Request body is invalid.",
      issues:  parsed.error.issues,
    });
    return;
  }

  const { member_id, current_concern, intake_data, attachments } = parsed.data;

  const { allowed, retryAfterMs } = checkRateLimit(member_id);
  if (!allowed) {
    const retryAfterSecs = Math.ceil(retryAfterMs / 1000);
    req.log.warn({ member_id, retryAfterSecs }, "Protocol: rate limit exceeded");
    res.status(429)
      .set("Retry-After", String(retryAfterSecs))
      .json({
        error:               "rate_limit_exceeded",
        message:             `Protocol generation limit reached (${RATE_LIMIT_MAX} per hour). Please try again in ${Math.ceil(retryAfterSecs / 60)} minutes.`,
        retry_after_seconds: retryAfterSecs,
      });
    return;
  }

  const memberContext = `
MEMBER INTAKE HISTORY
━━━━━━━━━━━━━━━━━━━━
Age:              ${intake_data.age       || "Not provided"}
Sex:              ${intake_data.sex       || "Not provided"}
Reported symptoms: ${intake_data.symptoms  || "Not provided"}
Current medications / supplements: ${intake_data.medications || "None reported"}
Health concerns (from intake):     ${intake_data.concerns    || "Not provided"}
Surgical history: ${intake_data.surgeries || "None reported"}

CURRENT PRESENTING CONCERN (today)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${current_concern}
`.trim();

  req.log.info(
    { member_id, has_intake: !!intake_data.age, attachments: attachments.length },
    "Protocol generation — calling Anthropic",
  );

  // Build content blocks — text + any attached files
  type ContentBlock = Anthropic.Messages.ContentBlockParam;

  let userContent: string | ContentBlock[];

  if (attachments.length === 0) {
    userContent = `${PROTOCOL_SYSTEM_PROMPT}\n\n${memberContext}`;
  } else {
    const blocks: ContentBlock[] = [];

    for (const att of attachments) {
      if (att.mime_type === "application/pdf") {
        blocks.push({
          type:   "document",
          source: {
            type:       "base64",
            media_type: "application/pdf",
            data:       att.data,
          },
        } as ContentBlock);
      } else {
        blocks.push({
          type:   "image",
          source: {
            type:       "base64",
            media_type: att.mime_type as "image/jpeg" | "image/png" | "image/webp" | "image/gif",
            data:       att.data,
          },
        });
      }
    }

    blocks.push({ type: "text", text: `${PROTOCOL_SYSTEM_PROMPT}\n\n${memberContext}` });
    userContent = blocks;
  }

  try {
    const aiResponse = await anthropic.messages.create({
      model:      "claude-opus-4-5",
      max_tokens: 4096,
      messages: [
        { role: "user", content: userContent },
      ],
    });

    const block = aiResponse.content[0];
    if (!block || block.type !== "text") {
      throw new Error("Claude returned empty or non-text response");
    }

    const raw = block.text.trim();
    const jsonStr = raw.startsWith("```")
      ? raw.replace(/^```(?:json)?\n?/, "").replace(/\n?```$/, "").trim()
      : raw;

    let parsed_json: unknown;
    try {
      parsed_json = JSON.parse(jsonStr);
    } catch {
      throw new Error(`Claude response was not valid JSON: ${jsonStr.slice(0, 200)}`);
    }

    const validated = ProtocolOutputSchema.safeParse(parsed_json);
    if (!validated.success) {
      throw new Error(
        `Protocol response did not match schema: ${JSON.stringify(validated.error.issues)}`,
      );
    }

    const generatedAt = new Date();

    req.log.info(
      {
        member_id,
        input_tokens:  aiResponse.usage.input_tokens,
        output_tokens: aiResponse.usage.output_tokens,
      },
      "Protocol generation — complete",
    );

    res.json({
      protocol:     validated.data,
      member_id,
      generated_at: generatedAt.toISOString(),
    });

    // Persist to DB after response is sent — fire and forget
    const concernLabel = current_concern.slice(0, 500);
    saveProtocol(
      member_id,
      concernLabel,
      validated.data as unknown as Record<string, unknown>,
      generatedAt,
    ).catch((err: unknown) => {
      req.log.warn({ member_id, err }, "Protocol DB save failed (non-fatal)");
    });
  } catch (err: unknown) {
    req.log.error({ member_id, err }, "Protocol generation — error");
    res.status(502).json({
      error:   "protocol_error",
      message: "Protocol generation is temporarily unavailable. Please try again.",
    });
  }
});

/**
 * GET /api/hermes/ministry/protocol/history/:memberId
 *
 * Returns up to 20 stored protocols for a member, newest first.
 * Each entry: { id, memberId, concern, protocol, generatedAt, createdAt }
 */
router.get("/ministry/protocol/history/:memberId", async (req, res) => {
  const { memberId } = req.params;
  if (!memberId) {
    res.status(400).json({ error: "missing_member_id" });
    return;
  }

  try {
    const rows = await getProtocolHistory(memberId);
    res.json({ history: rows, member_id: memberId });
  } catch (err: unknown) {
    req.log.error({ memberId, err }, "Protocol history fetch failed");
    res.status(502).json({ error: "db_error", message: "Could not retrieve protocol history." });
  }
});

export default router;
