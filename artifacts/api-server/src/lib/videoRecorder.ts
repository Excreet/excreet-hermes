import { chromium } from "playwright";
import { execFile } from "child_process";
import { promisify } from "util";
import fs from "fs";
import path from "path";

const execFileAsync = promisify(execFile);

const OUTPUT_DIR = "/tmp/excreet-video-render";
const MP4_PATH = path.join(OUTPUT_DIR, "excreet-tiktok.mp4");
const LOCK_PATH = path.join(OUTPUT_DIR, "render.lock");

if (!fs.existsSync(OUTPUT_DIR)) fs.mkdirSync(OUTPUT_DIR, { recursive: true });

const VIDEO_DURATION_MS = 22000; // 21s animation + 1s buffer

let rendering = false;

export function isRendering() {
  return rendering;
}

export function mp4Exists() {
  return fs.existsSync(MP4_PATH);
}

export function getMP4Path() {
  return MP4_PATH;
}

export async function renderVideo(videoUrl: string): Promise<string> {
  if (rendering) throw new Error("Render already in progress");
  rendering = true;
  fs.writeFileSync(LOCK_PATH, new Date().toISOString());

  const webmDir = path.join(OUTPUT_DIR, "frames");
  if (!fs.existsSync(webmDir)) fs.mkdirSync(webmDir, { recursive: true });

  try {
    const browser = await chromium.launch({
      headless: true,
      args: [
        "--no-sandbox",
        "--disable-setuid-sandbox",
        "--disable-dev-shm-usage",
        "--disable-gpu",
        "--autoplay-policy=no-user-gesture-required",
      ],
    });

    const context = await browser.newContext({
      viewport: { width: 720, height: 1280 },
      recordVideo: {
        dir: webmDir,
        size: { width: 720, height: 1280 },
      },
    });

    const page = await context.newPage();
    await page.goto(videoUrl, { waitUntil: "networkidle" });

    // Wait for full video duration
    await page.waitForTimeout(VIDEO_DURATION_MS);

    await context.close();
    await browser.close();

    // Find the recorded webm file
    const files = fs.readdirSync(webmDir).filter(f => f.endsWith(".webm"));
    if (files.length === 0) throw new Error("No WebM recorded");

    const webmPath = path.join(webmDir, files[0]);

    // Convert to MP4 with ffmpeg
    await execFileAsync("ffmpeg", [
      "-y",
      "-i", webmPath,
      "-c:v", "libx264",
      "-preset", "fast",
      "-crf", "20",
      "-pix_fmt", "yuv420p",
      "-movflags", "+faststart",
      "-an",
      MP4_PATH,
    ]);

    // Clean up webm
    fs.unlink(webmPath, () => {});

    return MP4_PATH;
  } finally {
    rendering = false;
    fs.unlink(LOCK_PATH, () => {});
  }
}
