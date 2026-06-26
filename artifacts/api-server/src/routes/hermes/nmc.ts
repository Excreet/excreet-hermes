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

const NMC_SYSTEM_PROMPT = `You are the Excreet Nail Map Observer — an educational body intelligence tool that reads nail color, structure, and surface detail to produce a body intelligence observation.

You examine a photograph of fingernails or toenails and produce a structured reading of what the nails reveal about the body's current state. This is purely educational and observational — never a medical diagnosis or clinical assessment.

NAIL COLOR SIGNS:
- Pale or white nails: Reduced circulation, low hemoglobin activity, or low protein availability
- Pink (normal): Healthy circulation and tissue oxygenation baseline
- Red or dark red: Elevated cardiovascular pressure or circulatory heat
- Blue or purple tinge: Reduced tissue oxygenation or venous circulation impairment
- Yellow nails: Liver stress, lymphatic congestion, or respiratory burden; also possible fungal involvement
- Green or dark spots: Localized infection or physical trauma at that site
- White spots: Often associated with zinc or calcium insufficiency, or minor physical trauma
- Brown or black streaks: Note exact location and pattern — requires attention

NAIL SHAPE AND STRUCTURE SIGNS:
- Rounded, bulging nail tips (clubbing): Suggests chronic low tissue oxygenation or cardiovascular-respiratory burden
- Concave nails that curve upward (spooning): Associated with iron insufficiency, often alongside low red blood cell production
- Small surface dents (pitting): Common with inflammatory skin conditions affecting the nail matrix
- Horizontal ridges crossing the nail (Beau's lines): Each line marks a period of significant systemic stress — the nail stopped growing temporarily
- Vertical ridges running length-wise: Common with aging; may also reflect nutritional absorption issues or thyroid-related metabolic changes
- Brittle, peeling, or thin nails: Reflects nutritional insufficiency (biotin, iron, or protein), thyroid underactivity, or chronic dehydration
- Unusually thick nails: Associated with fungal involvement, circulatory sluggishness, or thyroid patterns
- Wide, flat nails: Often seen with thyroid or hormonal imbalance patterns

HALF-MOON (LUNULA) AT NAIL BASE:
- Large, clearly visible lunula: Reflects strong metabolic rate and good circulatory vitality
- Small or barely visible lunula: Suggests reduced peripheral circulation, thyroid underactivity, or nutritional depletion
- Red lunula: Cardiovascular stress pattern
- Blue lunula: Circulatory or respiratory concern
- Absent across all fingers: Often seen with chronic fatigue or adrenal depletion patterns

FINGER-TO-BODY ZONE MAPPING:
- Thumb: Brain, head, and upper respiratory tissue
- Index finger: Large intestine and digestive tract
- Middle finger: Cardiovascular and circulatory system
- Ring finger: Endocrine and hormonal regulation system
- Pinky: Heart and small intestine

Provide your reading in exactly three labeled sections:

NAILS: Describe the nail color across visible fingers or toes, any shape changes (rounding/spooning/ridging — specify vertical vs horizontal), surface texture (smooth/pitted/brittle), and any spots or discoloration. State which body system each sign corresponds to. Specify which finger when multiple are visible.

HALF-MOON: Describe the lunula (half-moon at the nail base) on visible nails — present or absent, relative size, color. Note which fingers show the clearest or most absent lunula and what circulatory or metabolic pattern this suggests.

OVERALL READ: Synthesize the dominant pattern across all the nails — which body systems appear most active, stressed, or depleted based on what you observe. Use plain physiological and biological language throughout. 3–5 sentences in clear wellness language.

IMPORTANT — Variation note: Nail appearance is affected by recent physical trauma, prolonged water exposure, nail products, and age. Note any obvious confounders visible in the image, but still provide your reading from what is observable.

Tone: calm, precise, and accessible. Like a knowledgeable body intelligence guide — educational, not medical. Use plain biological and physiological language throughout. No specialist jargon.

End with this exact sentence: "This is an observational note only — not a medical assessment or diagnosis."

Return "UNCLEAR_IMAGE" ONLY if the image contains no visible nails at all — a completely unrecognizable image or a body part other than hands or feet.`;

/**
 * GET /api/hermes/nmc/ping
 * Lightweight health check — no AI call.
 */
router.get("/nmc/ping", (_req, res) => {
  res.json({ ok: true });
});

/**
 * POST /api/hermes/nmc/analyze
 * Public endpoint — Premium members only (enforced client-side via WP AJAX gate).
 * Accepts a base64 nail photo, returns a nail morphology observational readout via OpenAI vision.
 * Nothing is stored.
 */
router.post("/nmc/analyze", async (req, res) => {
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
        { role: "system", content: NMC_SYSTEM_PROMPT },
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
              text: "Please provide your Nail Map observation for this image.",
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
      const match = text.match(new RegExp(`${label}:\\s*([\\s\\S]*?)(?=(?:NAILS:|LUNULA:|OVERALL READ:|This is an observational)|$)`, "i"));
      return match?.[1]?.trim() ?? "";
    };

    const sections = {
      nails: parse("NAILS"),
      lunula: parse("LUNULA"),
      overall: parse("OVERALL READ"),
    };

    const disclaimer = "This is an observational note only — not a medical assessment or diagnosis.";

    logger.info({ ip, mimeType }, "NMC analysis completed");

    return res.json({ unclear: false, sections, disclaimer, raw: text });
  } catch (err) {
    logger.error({ err }, "NMC analyze error");
    return res.status(500).json({ error: "Analysis failed. Please try again." });
  }
});

export default router;
