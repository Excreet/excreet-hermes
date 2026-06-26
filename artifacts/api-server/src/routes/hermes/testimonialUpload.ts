import { Router, type IRouter } from "express";
import multer from "multer";
import path from "path";
import fs from "fs";
import crypto from "crypto";
import { sendOwnerAlert } from "../../services/smsService.js";

const router: IRouter = Router();

const TESTIMONIAL_DIR = path.join(process.cwd(), "testimonials");
if (!fs.existsSync(TESTIMONIAL_DIR)) fs.mkdirSync(TESTIMONIAL_DIR, { recursive: true });

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => cb(null, TESTIMONIAL_DIR),
  filename: (_req, file, cb) => {
    const id   = crypto.randomUUID();
    const ext  = path.extname(file.originalname).toLowerCase() || ".webm";
    cb(null, `${id}${ext}`);
  },
});

const upload = multer({
  storage,
  limits: { fileSize: 200 * 1024 * 1024 },
  fileFilter: (_req, file, cb) => {
    if (file.mimetype.startsWith("video/") || file.mimetype === "application/octet-stream") {
      cb(null, true);
    } else {
      cb(new Error(`Not a video: ${file.mimetype}`));
    }
  },
});

/**
 * POST /api/hermes/testimonial/upload
 *
 * Public — no API key required.
 * Accepts a recorded member testimonial video.
 * Saves to /testimonials/, appends to manifest.jsonl, fires SMS to owner.
 */
router.post("/testimonial/upload", upload.single("video"), async (req, res) => {
  if (!req.file) {
    res.status(400).json({ error: "no_video", message: "No video received." });
    return;
  }

  const memberName  = String(req.body["memberName"]  ?? "Unknown").slice(0, 80);
  const memberCity  = String(req.body["memberCity"]  ?? "Unknown").slice(0, 80);
  const submittedAt = new Date().toISOString();

  const manifest = path.join(TESTIMONIAL_DIR, "manifest.jsonl");
  fs.appendFileSync(
    manifest,
    JSON.stringify({ file: req.file.filename, memberName, memberCity, size: req.file.size, submittedAt }) + "\n",
  );

  req.log.info(
    { file: req.file.filename, memberName, memberCity, sizeMb: (req.file.size / 1048576).toFixed(1) },
    "testimonial: video received",
  );

  const sizeMB = (req.file.size / 1048576).toFixed(1);
  await sendOwnerAlert(
    `🎥 New Excreet testimonial!\nFrom: ${memberName}, ${memberCity}\nSize: ${sizeMB} MB\nFile: ${req.file.filename}\nAt: ${submittedAt}`,
  );

  res.status(200).json({ ok: true, message: "Your story has been received. Thank you." });
});

export default router;
