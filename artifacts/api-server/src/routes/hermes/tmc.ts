import { Router } from "express";
import { z } from "zod/v4";
import { openai } from "../../services/ai/openaiClient.js";
import { logger } from "../../lib/logger.js";

const router = Router();

const MAX_IMAGE_BYTES = 6_000_000;
const RATE_LIMIT_WINDOW_MS = 60 * 60 * 1000;
const RATE_LIMIT_MAX = 5;

const ipHits = new Map<string, { count: number; resetAt: number }>();

function checkRateLimit(ip: string): boolean {
  const now = Date.now();
  const entry = ipHits.get(ip);
  if (!entry || now > entry.resetAt) {
    ipHits.set(ip, { count: 1, resetAt: now + RATE_LIMIT_WINDOW_MS });
    return true;
  }
  if (entry.count >= RATE_LIMIT_MAX) return false;
  entry.count++;
  return true;
}

const BodySchema = z.object({
  image: z.string().min(100).max(MAX_IMAGE_BYTES * 1.4),
});

const TMC_SYSTEM_PROMPT = `You are the Excreet Tongue Map Observer — an educational body intelligence tool that reads the visible tissue, color, and surface of the tongue to produce a structured body observation.

You examine a photograph of a tongue and produce a zone-by-zone reading of what the tissue reveals about the body's current state. This is purely educational and observational — never a medical diagnosis or clinical assessment.

TONGUE ZONE MAP (what region reflects which body system):
- Tip: Cardiovascular and upper respiratory zone — redness or coating at the tip suggests circulatory or lung-related activity
- Front third (behind tip): Upper respiratory and bronchial tissue zone
- Middle: Stomach and digestive system — the primary digestive reflection zone
- Sides: Liver and gallbladder zone — redness, swelling, or tissue indentation along the sides suggests digestive-liver stress
- Root (back): Kidney and lower body zone — heavy coating at the root reflects lower digestive or fluid-elimination burden
- Center line (midline): Digestive centerline — a deep central groove reflects long-term stomach or digestive tissue stress

TISSUE COLOR SIGNS:
- Pale or light pink: Reduced circulation, low red blood cell activity, or sluggish metabolism
- Normal pink-red: Healthy baseline circulation and tissue oxygenation
- Red or deep red: Elevated metabolic heat or inflammatory activity
- Purple or dusky: Circulatory congestion, poor venous return, or oxygenation deficit
- Blue tinge: Significant circulatory impairment or low tissue oxygenation

SURFACE COATING SIGNS:
- White thin coat: Normal baseline or mild digestive slowdown
- White thick coat: Fluid or mucous accumulation, digestive congestion
- Yellow thin coat: Early-stage digestive heat or mild inflammatory activity
- Yellow thick coat: More concentrated digestive inflammation or stagnation
- No coat (bare, smooth, shiny surface): Tissue fluid depletion, mucosal thinning, often with dryness
- Greasy or slick coat: Excess mucous or fluid accumulation in the digestive tract

STRUCTURAL SIGNS:
- Swollen or puffy body: Fluid retention, lymphatic burden, or digestive underactivity
- Thin or narrow body: Tissue fluid depletion or low nutritional reserves
- Scalloped edges (wavy indentations along the sides): The tongue is pressing against the teeth — reflects digestive underactivity or mild swelling
- Cracks: A central midline crack reflects digestive tissue stress; multiple surface cracks reflect fluid depletion throughout; side cracks reflect liver-digestive stress
- Visible trembling (if apparent in photo): Nervous system tension or fatigue
- Deviated or pulled to one side: Asymmetrical muscular or neurological tension

Provide your reading in exactly three labeled sections:

TONGUE TISSUE: Describe the tongue body — its color (pale/normal/red/purple/dusky), size (normal/swollen/thin), and any structural signs (cracks, scalloped edges, tip color, side condition). State which body system or zone each observation corresponds to. Be specific and zone-precise.

SURFACE COATING: Describe the tongue coating — its color (white/yellow/grey/none), thickness (thin/thick/absent), distribution (root-only/full/patchy), and moisture level (wet/dry/greasy). Map each coating pattern to the corresponding body zone and what it suggests about that system's current state.

OVERALL READ: Synthesize the dominant pattern — which body systems appear most active, congested, or depleted based on what you see. Use plain physiological language: describe what the tissue is showing, not specialist terminology. 3–5 sentences in clear wellness language.

IMPORTANT — Variation note: Tongue appearance varies with hydration, recent food and drink, lighting, and time of day. A recently eaten meal, coffee, or strongly colored foods can temporarily shift coating and color. Acknowledge this when relevant but still provide your reading from what is visible.

Tone: calm, precise, and accessible. Like a knowledgeable body intelligence guide — educational, not medical. Use plain biological and physiological language throughout. No specialist or system-specific jargon.

End with this exact sentence: "This is an observational note only — not a medical assessment or diagnosis."

Return "UNCLEAR_IMAGE" ONLY if the image contains no visible tongue at all — a completely unrecognizable image, a random object, or a body part other than a tongue.`;

/**
 * GET /api/hermes/tmc/ping
 * Lightweight health check — no AI call.
 */
router.get("/tmc/ping", (_req, res) => {
  res.json({ ok: true });
});

/**
 * POST /api/hermes/tmc/analyze
 * Public endpoint — Premium members only (enforced client-side via WP AJAX gate).
 * Accepts a base64 tongue photo, returns a TCM observational readout via OpenAI vision.
 * Nothing is stored.
 */
router.post("/tmc/analyze", async (req, res) => {
  const ip =
    (req.headers["x-forwarded-for"] as string | undefined)?.split(",")[0]?.trim() ??
    req.socket.remoteAddress ??
    "unknown";

  if (!checkRateLimit(ip)) {
    return res.status(429).json({ error: "Too many requests. Please try again in an hour." });
  }

  const parsed = BodySchema.safeParse(req.body);
  if (!parsed.success) {
    return res.status(400).json({ error: "Invalid request. Expected { image: '<base64 string>' }." });
  }

  const rawImage = parsed.data.image;
  const base64Data = rawImage.includes(",") ? rawImage.split(",")[1]! : rawImage;
  const mimeType = rawImage.startsWith("data:image/png") ? "image/png"
    : rawImage.startsWith("data:image/webp") ? "image/webp"
    : "image/jpeg";

  try {
    const response = await openai.chat.completions.create({
      model: "gpt-4o",
      max_tokens: 600,
      messages: [
        { role: "system", content: TMC_SYSTEM_PROMPT },
        {
          role: "user",
          content: [
            {
              type: "image_url",
              image_url: {
                url: `data:${mimeType};base64,${base64Data}`,
                detail: "high",
              },
            },
            {
              type: "text",
              text: "Please provide your Tongue Map observation for this image.",
            },
          ],
        },
      ],
    });

    const text = response.choices[0]?.message?.content ?? "";

    if (text.trim() === "UNCLEAR_IMAGE") {
      return res.json({ unclear: true, observation: null });
    }

    const parse = (label: string) => {
      const match = text.match(new RegExp(`${label}:\\s*([\\s\\S]*?)(?=(?:BODY:|COATING:|OVERALL READ:|This is an observational)|$)`, "i"));
      return match?.[1]?.trim() ?? "";
    };

    const sections = {
      body: parse("BODY"),
      coating: parse("COATING"),
      overall: parse("OVERALL READ"),
    };

    const disclaimer = "This is an observational note only — not a medical assessment or diagnosis.";

    logger.info({ ip, mimeType }, "TMC analysis completed");

    return res.json({ unclear: false, sections, disclaimer, raw: text });
  } catch (err) {
    logger.error({ err }, "TMC analyze error");
    return res.status(500).json({ error: "Analysis failed. Please try again." });
  }
});

export default router;
