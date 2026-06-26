import { Router, type IRouter } from "express";
import { z } from "zod/v4";
import { anthropic } from "../../services/ai/anthropicClient.js";
import { checkSession, incrementSession, normaliseTier } from "../../lib/sessionLedger.js";
import { getChatHistory, appendChatHistory, resetChatHistory } from "../../lib/ministryChatStore.js";
import { buildThinkTankContext } from "../../lib/thinkTankStore.js";
import type Anthropic from "@anthropic-ai/sdk";

const router: IRouter = Router();

const MessageSchema = z.object({
  role:    z.enum(["user", "assistant"]),
  content: z.string().min(1).max(4000),
});

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

const MinistryChatSchema = z.object({
  member_id:            z.string().min(1),
  message:              z.string().min(1).max(3000),
  conversation_history: z.array(MessageSchema).max(40).default([]),
  attachments:          z.array(AttachmentSchema).max(3).default([]),
  /**
   * tier: passed by WordPress after checking MemberPress subscriptions.
   * Values: "starter" | "premium" | "unlimited"
   * Hermes treats this as advisory for limit lookup; the authoritative
   * enforcement is the server-side session ledger (PostgreSQL).
   */
  tier:                 z.string().optional(),
  /**
   * baseline_context: the member's onboarding Clinical Pattern Report
   * (pharmaceutical_intake result), serialized as a JSON string.
   * Injected into the Ministry of Healing system prompt so every session
   * starts with the member's full health baseline — never a cold start.
   */
  baseline_context:     z.string().optional(),
});

const BASE_MINISTRY_PROMPT = `You are the Excreet Ministry of Healing — a private, compassionate health intelligence guide for Excreet members.

Your role is to help members navigate complex health concerns, including:
- Red flag symptoms and whether immediate medical attention is warranted (unexplained rashes, sudden weight loss, chronic fatigue, neurological changes, pressure or pain patterns)
- Dietary protocols for specific situations: severe pediatric allergies, pregnancy preparation, autoimmune management, occupational health considerations (pilots, shift workers, physically demanding roles)
- Self-administered healing protocols and how to track progress alongside daily Gut Snapshot findings
- Lab report interpretation — what markers are trending, what falls outside optimal range, and what protocol adjustments make sense
- Pharmaceutical and supplement interactions, drug-nutrient depletions, and timing considerations
- Gut microbiome health, elimination protocols, and restorative nutrition strategies
- Chronic condition management: understanding patterns, triggers, and restorative approaches

Communication guidelines:
- Be warm, thorough, and non-judgmental — members come here because they often feel unheard by conventional medicine
- You provide health intelligence and pattern recognition, not medical diagnosis or prescription
- For truly acute or emergency presentations (chest pain, stroke signs, anaphylaxis, suicidal ideation, severe trauma), always direct to emergency services immediately before any other response
- Suggest concrete, actionable next steps — protocol ideas, questions to bring to their doctor, labs worth requesting, lifestyle pivots
- Responses should be substantive and readable — short paragraphs, bullet points, and clear structure where helpful
- Do not ask for or reference any personally identifiable information beyond what the member volunteers in this session
- When the member shares lab results, images, or documents, analyse them carefully and reference specific values or findings in your response

You are a trusted health intelligence partner, not a replacement for a physician.`;

async function buildMinistrySystemPrompt(baselineContext?: string): Promise<string> {
  // Fetch Think Tank knowledge base (never blocks — falls back to empty on error)
  let thinkTankBlock = "";
  try {
    thinkTankBlock = await buildThinkTankContext();
  } catch {
    // Think Tank unavailable — Hermes still answers, just without accumulated knowledge
  }

  const baselineBlock = (() => {
    if (!baselineContext) {
      return "\n\nNo onboarding baseline is available for this member yet.";
    }
    let baseline: unknown;
    try {
      baseline = JSON.parse(baselineContext);
    } catch {
      return "\n\nNo onboarding baseline is available for this member yet.";
    }
    return `

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MEMBER HEALTH BASELINE (from onboarding Clinical Pattern Report)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

The following is this member's onboarding Clinical Pattern Report — their
complete health picture at the time they joined Excreet. Use this as your
foundational context for all responses. Reference it when relevant. Do not
repeat it verbatim to the member unless they ask, but let it inform every
answer you give. This member is not starting cold — you know their baseline.

${JSON.stringify(baseline, null, 2)}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`;
  })();

  const parts = [BASE_MINISTRY_PROMPT, baselineBlock];
  if (thinkTankBlock) parts.push("\n" + thinkTankBlock);
  return parts.join("");
}

/**
 * POST /api/hermes/ministry/history/mark
 *
 * Appends a system-level note to a member's Ministry chat history.
 * Called by WordPress after a successful health baseline re-submission,
 * so the AI knows the member's Clinical Pattern Report has been refreshed.
 *
 * Body: { member_id: string, note?: string }
 */
router.post("/ministry/history/mark", async (req, res) => {
  const schema = z.object({
    member_id: z.string().min(1),
    note:      z.string().max(1000).optional(),
  });

  const parsed = schema.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  const { member_id, note } = parsed.data;
  const dateStr = new Date().toISOString().split("T")[0];
  const systemNote = note ??
    `[Health Baseline Updated — ${dateStr}] This member has re-submitted their health intake. ` +
    `Their Clinical Pattern Report is now current. Previous conversation context predates this update — ` +
    `weigh any prior health details against what they share going forward.`;

  try {
    await appendChatHistory(member_id, systemNote, "Understood. I've noted that your health baseline has been refreshed. I'll draw on your updated Clinical Pattern Report in our conversations going forward.");
    req.log.info({ member_id }, "Ministry history: rebaseline marker added");
    res.json({ ok: true });
  } catch (err: unknown) {
    req.log.error({ member_id, err }, "Ministry history: failed to add rebaseline marker");
    res.status(500).json({ error: "Failed to mark history" });
  }
});

/**
 * GET /api/hermes/ministry/history/:memberId
 *
 * Returns the persisted conversation history for a member (newest last).
 * Called by WordPress on page load to hydrate the chat UI.
 */
router.get("/ministry/history/:memberId", async (req, res) => {
  const { memberId } = req.params;
  if (!memberId) {
    res.status(400).json({ error: "member_id required" });
    return;
  }
  try {
    const messages = await getChatHistory(memberId);
    res.json({ messages });
  } catch (err: unknown) {
    req.log.error({ memberId, err }, "Ministry history: fetch error");
    res.status(500).json({ error: "Failed to load history" });
  }
});

/**
 * POST /api/hermes/ministry/chat
 *
 * One-on-one private AI health session for Ministry of Healing members.
 * Synchronous — returns the AI response directly (not a queued job).
 *
 * Body: { member_id, message, conversation_history?, attachments?, tier? }
 * Returns: { response, member_id, remaining, is_unlimited }
 *
 * Session enforcement:
 *   - Server-side ledger (PostgreSQL) is the authoritative gate.
 *   - WordPress also enforces via user meta, but this is the backstop.
 *   - tier is passed by WordPress; unknown values default to "starter".
 */
router.post("/ministry/chat", async (req, res) => {
  const parsed = MinistryChatSchema.safeParse(req.body);

  if (!parsed.success) {
    req.log.warn({ issues: parsed.error.issues }, "Ministry chat: invalid request body");
    res.status(400).json({
      error:   "validation_error",
      message: "Request body is invalid.",
      issues:  parsed.error.issues,
    });
    return;
  }

  const { member_id, message, conversation_history, attachments, tier: rawTier, baseline_context } = parsed.data;
  const tier = normaliseTier(rawTier);
  const systemPrompt = await buildMinistrySystemPrompt(baseline_context);

  // ── Server-side session gate ──────────────────────────────────────────────
  let sessionCheck;
  try {
    sessionCheck = await checkSession(member_id, tier);
  } catch (err: unknown) {
    req.log.error({ member_id, err }, "Ministry chat: session ledger error");
    // Fail open — if ledger is down, let WordPress be the gate rather than
    // blocking all members. Log so we can investigate.
    sessionCheck = { allowed: true, remaining: null as number | null, count: 0 };
  }

  if (!sessionCheck.allowed) {
    req.log.warn({ member_id, tier }, "Ministry chat: session limit reached (server-side)");
    res.status(429).json({
      error:        "session_limit_reached",
      message:      "You have used all sessions in your current period.",
      remaining:    0,
      is_unlimited: false,
    });
    return;
  }

  // ── Build Anthropic message payload ──────────────────────────────────────
  type ContentBlock = Anthropic.Messages.ContentBlockParam;

  let lastUserContent: string | ContentBlock[];

  if (attachments.length === 0) {
    lastUserContent = message;
  } else {
    const blocks: ContentBlock[] = [];

    for (const att of attachments) {
      if (att.mime_type === "application/pdf") {
        blocks.push({
          type: "document",
          source: {
            type:       "base64",
            media_type: "application/pdf",
            data:       att.data,
          },
        } as ContentBlock);
      } else {
        blocks.push({
          type: "image",
          source: {
            type:       "base64",
            media_type: att.mime_type as
              "image/jpeg" | "image/png" | "image/webp" | "image/gif",
            data:       att.data,
          },
        });
      }
    }

    blocks.push({ type: "text", text: message });
    lastUserContent = blocks;
  }

  const messages: Anthropic.Messages.MessageParam[] = [
    ...conversation_history.map((m) => ({
      role:    m.role as "user" | "assistant",
      content: m.content,
    })),
    { role: "user", content: lastUserContent },
  ];

  req.log.info(
    { member_id, tier, turns: messages.length, attachments: attachments.length },
    "Ministry chat — calling Anthropic",
  );

  // ── Call Claude ───────────────────────────────────────────────────────────
  let responseText: string;
  try {
    const aiResponse = await anthropic.messages.create({
      model:      "claude-opus-4-5",
      max_tokens: 2000,
      system:     systemPrompt,
      messages,
    });

    responseText = aiResponse.content[0]?.type === "text" ? aiResponse.content[0].text : "";

    req.log.info(
      {
        member_id,
        input_tokens:  aiResponse.usage.input_tokens,
        output_tokens: aiResponse.usage.output_tokens,
      },
      "Ministry chat — response generated",
    );
  } catch (err: unknown) {
    req.log.error({ member_id, err }, "Ministry chat — Anthropic error");
    res.status(502).json({
      error:   "ai_error",
      message: "The healing guide is temporarily unavailable. Please try again.",
    });
    return;
  }

  // ── Persist conversation history (fire-and-forget) ───────────────────────
  appendChatHistory(member_id, message, responseText).catch((err: unknown) => {
    req.log.error({ member_id, err }, "Ministry chat: failed to persist conversation history");
  });

  // ── Increment session ledger (after successful AI response) ───────────────
  let remaining: number | null = sessionCheck.remaining;
  try {
    remaining = await incrementSession(member_id, tier);
  } catch (err: unknown) {
    // Non-fatal — response already generated; log but don't fail the request
    req.log.error({ member_id, tier, err }, "Ministry chat: failed to increment session ledger");
  }

  res.json({
    response:     responseText,
    member_id,
    remaining,
    is_unlimited: tier === "unlimited",
  });
});

/**
 * POST /ministry/history/reset
 *
 * Deletes the member's entire Ministry chat history so they get a clean slate.
 * WordPress calls this when the member confirms "Start New Session".
 * Body: { member_id: string }
 */
router.post("/ministry/history/reset", async (req, res) => {
  const { member_id } = req.body as { member_id?: string };

  if (!member_id?.trim()) {
    res.status(400).json({ error: "missing_member_id" });
    return;
  }

  await resetChatHistory(member_id.trim());
  req.log.info({ member_id }, "Ministry chat history reset by member");
  res.json({ success: true, member_id });
});

export default router;
