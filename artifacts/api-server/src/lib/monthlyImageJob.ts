import { execFileSync } from "child_process";
import { writeFileSync, unlinkSync, existsSync } from "fs";
import { tmpdir } from "os";
import { join } from "path";
import OpenAI from "openai";
import { logger } from "./logger.js";

/**
 * Monthly bathroom background image rotation.
 *
 * On the 1st of each month at 06:00 UTC the scheduler:
 *   1. Calls DALL-E 3 with a month-specific prompt
 *   2. Downloads the generated image
 *   3. SCPs it to SiteGround as healer-bg-MM.jpg
 *
 * Every WordPress page uses healer-bg-MM.jpg (monthly rotation already
 * wired into patches 290, 297, 300, 301, 309 and index.php).
 *
 * Manual trigger: POST /api/hermes/admin/rotate-background
 *   Body (optional): { "month": 6 }   ← override which month to generate
 */

// ── 12 distinct bathroom scene prompts, one per month ──────────────────────
const MONTHLY_PROMPTS: Record<number, string> = {
  1:  "Ultra-luxury spa bathroom, crisp January morning light through frosted glass, deep white porcelain soaking tub, polished gold fixtures, white marble floor, fresh orchids, clinical serenity meets old-world warmth, professional interior photography, photorealistic, 8K, no people",
  2:  "Minimalist modern luxury bathroom, soft winter light, freestanding sculptural white tub, brushed gold faucet and hardware, cream marble countertop, single rose in bud vase, aspirational wellness sanctuary, professional interior photography, photorealistic, 8K, no people",
  3:  "Spring renewal spa bathroom, natural morning light, soaking tub near large window with garden bloom view, polished gold shower fixtures, white and sage tile, fresh eucalyptus branch, clinical calm, professional interior photography, photorealistic, 8K, no people",
  4:  "Japanese wabi-sabi luxury bathroom, warm afternoon light, deep square cedar soaking tub, brushed gold hardware, smooth river stone floor, bamboo detail, single blossom, clinical tranquility, professional interior photography, photorealistic, 8K, no people",
  5:  "Southern plantation luxury bathroom, golden hour light through plantation shutters, freestanding porcelain tub, antique gold fixtures, white wainscoting, lush fiddle-leaf fig, rich warm wood tones, clinical elegance, professional interior photography, photorealistic, 8K, no people",
  6:  "Coastal Mediterranean luxury bathroom, bright summer light, sculptural white oval soaking tub, aged gold fixtures, white herringbone tile, natural linen towels, sea glass detail, aspirational wellness space, professional interior photography, photorealistic, 8K, no people",
  7:  "Contemporary dark-luxury spa bathroom, warm summer evening, deep oval soaking tub, matte black body with polished gold fixtures, dramatic pendant lighting, black and white marble, clinical sophistication, professional interior photography, photorealistic, 8K, no people",
  8:  "Italian villa luxury bathroom, late summer golden light, clawfoot porcelain tub, ornate antique gold fixtures, arched window with courtyard garden view, terracotta and ivory mosaic floor, professional interior photography, photorealistic, 8K, no people",
  9:  "Transitional autumn spa bathroom, warm amber afternoon light, freestanding marble tub, burnished gold fixtures, reclaimed wood accent wall, dried botanical arrangement, cozy clinical elegance, professional interior photography, photorealistic, 8K, no people",
  10: "Moody jewel-tone luxury bathroom, deep autumn atmosphere, forest green lacquered walls, sculptural white freestanding tub, polished gold hardware, vintage brass mirror, candlelight warmth, clinical sophistication, professional interior photography, photorealistic, 8K, no people",
  11: "Scandinavian hygge luxury bathroom, cool winter morning light, white freestanding tub, warm brushed gold fixtures, pale birch wood accent, heated grey stone floor, minimal clinical warmth, professional interior photography, photorealistic, 8K, no people",
  12: "Holiday sanctuary bathroom, soft candlelit winter evening, deep soaking tub, antique gold fixtures, white marble floor, white orchids and pine bough, quiet clinical luxury, professional interior photography, photorealistic, 8K, no people",
};

const REMOTE_UPLOADS = "/home/customer/www/excreet.com/public_html/wp-content/uploads";

// Track last-run month in memory to prevent double-firing
let lastRunMonth = -1;

function normalizeKey(raw: string): string {
  return raw
    .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
    .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
    .replace(
      /-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/,
      (_, body) => {
        const clean = body.replace(/\s+/g, "");
        const lines = clean.match(/.{1,64}/g) ?? [];
        return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
      },
    );
}

export async function runMonthlyImageJob(forMonth?: number): Promise<{ month: number; file: string }> {
  const deployKey = process.env["SITEGROUND_DEPLOY_KEY"] ?? "";
  const sshHost   = process.env["SITEGROUND_SSH_HOST"]   ?? "";
  const sshUser   = process.env["SITEGROUND_SSH_USER"]   ?? "";
  const sshPort   = process.env["SITEGROUND_SSH_PORT"]   ?? "22";

  if (!deployKey || !sshHost || !sshUser) {
    throw new Error("Missing SiteGround SSH credentials (SITEGROUND_DEPLOY_KEY / SSH_HOST / SSH_USER)");
  }
  if (!process.env["OPENAI_API_KEY"]) {
    throw new Error("Missing OPENAI_API_KEY");
  }

  const month    = forMonth ?? (new Date().getMonth() + 1);
  const monthPad = String(month).padStart(2, "0");
  const prompt   = MONTHLY_PROMPTS[month] ?? MONTHLY_PROMPTS[1]!;
  const fileName = `healer-bg-${monthPad}.jpg`;

  logger.info({ month, monthPad }, "Monthly image job: starting gpt-image-1 generation");

  // ── 1. Generate with gpt-image-1 ──────────────────────────────────────────
  const openai = new OpenAI({ apiKey: process.env["OPENAI_API_KEY"] });
  const response = await openai.images.generate({
    model:           "gpt-image-1",
    prompt,
    n:               1,
    size:            "1536x1024",
    quality:         "high",
  });

  // gpt-image-1 returns base64 JSON; fall back to URL if present
  const item     = response.data?.[0];
  if (!item) throw new Error("gpt-image-1 returned no image data");

  let buffer: Buffer;
  if (item.b64_json) {
    buffer = Buffer.from(item.b64_json, "base64");
    logger.info({ month, bytes: buffer.length }, "Monthly image job: base64 decoded — uploading to SiteGround");
  } else if (item.url) {
    logger.info({ month, url: item.url }, "Monthly image job: image generated — downloading");
    const imgResponse = await fetch(item.url);
    if (!imgResponse.ok) throw new Error(`Image download failed: ${imgResponse.status}`);
    buffer = Buffer.from(await imgResponse.arrayBuffer());
    logger.info({ month, bytes: buffer.length }, "Monthly image job: download complete — uploading to SiteGround");
  } else {
    throw new Error("gpt-image-1 returned neither b64_json nor url");
  }

  // ── 3. Write temp files and SCP ───────────────────────────────────────────
  const tmpImg = join(tmpdir(), `healer-bg-${monthPad}-${Date.now()}.jpg`);
  const tmpKey = join(tmpdir(), `sg-key-monthly-${Date.now()}`);

  writeFileSync(tmpImg, buffer);
  writeFileSync(tmpKey, normalizeKey(deployKey) + "\n", { mode: 0o600 });

  try {
    execFileSync("scp", [
      "-i", tmpKey,
      "-o", "StrictHostKeyChecking=no",
      "-o", "BatchMode=yes",
      "-P", sshPort,
      tmpImg,
      `${sshUser}@${sshHost}:${REMOTE_UPLOADS}/${fileName}`,
    ], { stdio: "pipe" });
  } finally {
    if (existsSync(tmpImg)) unlinkSync(tmpImg);
    if (existsSync(tmpKey)) unlinkSync(tmpKey);
  }

  lastRunMonth = month;
  logger.info({ month, fileName }, "Monthly image job: complete — site background updated");

  return { month, file: fileName };
}

// ── Scheduler ─────────────────────────────────────────────────────────────────

export function startMonthlyImageScheduler(): void {
  const missing = ["SITEGROUND_DEPLOY_KEY", "SITEGROUND_SSH_HOST", "SITEGROUND_SSH_USER", "OPENAI_API_KEY"]
    .filter((k) => !process.env[k]);

  if (missing.length > 0) {
    logger.warn({ missing }, "Monthly image scheduler: disabled — missing env vars");
    return;
  }

  logger.info("Monthly image scheduler: active — runs on the 1st of each month at 06:00 UTC");

  // Check every hour; fire on 1st of month at 06:xx UTC if not yet run this month
  setInterval(() => {
    const now   = new Date();
    const month = now.getUTCMonth() + 1;
    const day   = now.getUTCDate();
    const hour  = now.getUTCHours();

    if (day === 1 && hour === 6 && lastRunMonth !== month) {
      logger.info({ month }, "Monthly image scheduler: firing job");
      runMonthlyImageJob(month).catch((err) => {
        logger.error({ err, month }, "Monthly image scheduler: job failed");
      });
    }
  }, 60 * 60 * 1000);
}
