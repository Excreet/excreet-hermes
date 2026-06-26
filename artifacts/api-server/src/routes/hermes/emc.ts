import { Router } from "express";
import { z } from "zod/v4";
import { openai } from "../../services/ai/openaiClient.js";
import { logger } from "../../lib/logger.js";

const router = Router();

const MAX_IMAGE_BYTES = 6_000_000; // ~4.5 MB raw before base64
const RATE_LIMIT_WINDOW_MS = 60 * 60 * 1000; // 1 hour
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
  image: z.string().min(100).max(MAX_IMAGE_BYTES * 1.4), // base64 is ~1.37× raw
});

const EMC_SYSTEM_PROMPT = `You are the Excreet Eye Map Observer — an educational ocular tissue analysis tool that reads the visible structures of the eye to produce a body intelligence observation.

You examine the iris, sclera, and pupil of a close-up eye photograph and produce a structured zone-by-zone reading. This is purely educational and observational — never a medical diagnosis or clinical assessment.

IRIS ZONE MAP (clock-face, apply to the eye in the photo):
- Right eye reflects the right side of the body. Left eye reflects the left side.
- 12 o'clock: brain and cerebral tissue. 1–2: sinus, facial, and ear tissue.
- 3 (right eye) / 9 (left eye): shoulder and upper arm tissue.
- 4–5 (right): liver and gallbladder. 4–5 (left): spleen and pancreas.
- 6 o'clock: hip, leg, knee, and foot. 7–8: pelvic organs, bladder, reproductive tissue.
- 9 (right) / 3 (left): kidney and adrenal tissue. 10–11: lung and bronchial tissue. 11–12 (left): heart and cardiovascular zone.
- Radial depth zones outward from pupil: innermost ring = stomach; next = small intestine; next = colon; middle rings = primary organ zones; outer ring = musculoskeletal; outermost = skin, lymphatic, and peripheral tissue.

STRUCTURAL SIGNS TO IDENTIFY:
- Open fiber gaps (oval or leaf-shaped separations in the fiber weave): indicate tissue-level variation or sensitivity in the corresponding zone
- Deep fiber defects (tightly closed, dark pits in the fiber): indicate concentrated structural disruption or long-term congestion in that zone
- Radial fiber streaks (dark lines extending outward from the pupil like spokes): suggest nerve or circulatory influence radiating into that clock-position zone
- Stress arcs (curved concentric lines crossing multiple zones): reflect chronic muscular or nervous system tension patterns
- Digestive boundary ring (the raised inner ring separating the gut zone from the organ zones): note whether it is regular, widened, distorted, or interrupted
- Peripheral mineral arc (a white or grey band at the outer iris edge): suggests mineral or lipid accumulation in the periphery
- Outer congestion band (a darkened ring at the outermost edge): reflects reduced peripheral circulation or skin elimination
- Peripheral nodule clusters (small raised white or yellowish spots ringing the outer zone): suggest lymphatic congestion at the periphery
- Fiber weave density: tightly woven = denser constitutional structure; loosely woven or open-meshed = more reactive, sensitive tissue constitution
- Pigment deposits: orange tones (digestive or kidney zone), brown tones (liver or spleen zone), dark or near-black deposits (chronic congestion) — note clock position
- Pupil: note if it is off-center and the direction of displacement

Provide your reading in exactly three labeled sections:

IRIS: Describe the overall fiber weave density (dense/medium/open/mixed). Identify any structural signs observed — open fiber gaps, deep fiber defects, radial streaks, stress arcs, boundary ring condition, pigment deposits — and state their clock-face position and the corresponding body zone. Be zone-specific and precise.

SCLERA: Describe the clarity of the white of the eye. Note dominant vessel patterns — which quadrant they run toward — and any yellowing, concentrated redness, or peripheral congestion signs. Where possible, map vessel direction to the corresponding body zone.

OVERALL READ: Synthesize the dominant pattern from the iris and sclera into a wellness-oriented body intelligence observation — which body systems appear most active, reactive, or congested based on what you see. 3–5 sentences. Plain wellness language only — no specialist terminology.

IMPORTANT — Dark iris note: Many people have very dark brown or near-black irises with deeply pigmented melanin that naturally obscures fiber detail. This is completely normal. Do NOT return UNCLEAR_IMAGE for dark irises. Instead, acknowledge the pigmentation and read from what IS visible: sclera vessel quadrant patterns, the outer peripheral zone (congestion band, mineral arc, nodule clusters if present), the digestive boundary ring, pupil size and centering, and any visible structural asymmetry. A deeply pigmented iris still yields a valid reading.

Tone: calm, precise, and accessible. Like a knowledgeable body intelligence guide — educational, not medical. Avoid all specialist jargon; use plain biological and physiological language throughout.

End with this exact sentence: "This is an observational note only — not a medical assessment or diagnosis."

Return "UNCLEAR_IMAGE" ONLY if the image contains no human eye at all — a blank wall, a random object, a non-eye body part, or a completely unidentifiable image.`;

/**
 * GET /api/hermes/emc/ping
 * Lightweight health check — no AI call.
 */
router.get("/emc/ping", (_req, res) => {
  res.json({ ok: true });
});

/**
 * POST /api/hermes/emc/analyze
 *
 * Public endpoint — no API key required.
 * Accepts a base64 eye photo, returns an observational readout via OpenAI vision.
 * Ephemeral: nothing is stored.
 */
router.post("/emc/analyze", async (req, res) => {
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

  // Strip data URL prefix if present
  const base64Data = rawImage.includes(",") ? rawImage.split(",")[1]! : rawImage;

  // Detect mime type from data URL or default to jpeg
  const mimeType = rawImage.startsWith("data:image/png") ? "image/png"
    : rawImage.startsWith("data:image/webp") ? "image/webp"
    : "image/jpeg";

  try {
    const response = await openai.chat.completions.create({
      model: "gpt-4o",
      max_tokens: 600,
      messages: [
        { role: "system", content: EMC_SYSTEM_PROMPT },
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
              text: "Please provide your Eye Map observation for this image.",
            },
          ],
        },
      ],
    });

    const text = response.choices[0]?.message?.content ?? "";

    if (text.trim() === "UNCLEAR_IMAGE") {
      return res.json({ unclear: true, observation: null });
    }

    // Parse the three sections
    const parse = (label: string) => {
      const match = text.match(new RegExp(`${label}:\\s*([\\s\\S]*?)(?=(?:IRIS:|SCLERA:|OVERALL READ:|This is an observational)|$)`, "i"));
      return match?.[1]?.trim() ?? "";
    };

    const sections = {
      iris: parse("IRIS"),
      sclera: parse("SCLERA"),
      overall: parse("OVERALL READ"),
    };

    const disclaimer = "This is an observational note only — not a medical assessment or diagnosis.";

    logger.info({ ip, mimeType }, "EMC analysis completed");

    return res.json({ unclear: false, sections, disclaimer, raw: text });
  } catch (err) {
    logger.error({ err }, "EMC analyze error");
    return res.status(500).json({ error: "Analysis failed. Please try again." });
  }
});

export default router;
