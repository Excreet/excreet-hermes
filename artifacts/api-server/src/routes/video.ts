import { Router, type IRouter } from "express";
import multer from "multer";
import path from "path";
import fs from "fs";
import crypto from "crypto";
import { execFile } from "child_process";
import { promisify } from "util";

const execFileAsync = promisify(execFile);

const router: IRouter = Router();

const TMP = "/tmp/excreet-video-export";
if (!fs.existsSync(TMP)) fs.mkdirSync(TMP, { recursive: true });

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => cb(null, TMP),
  filename: (_req, _file, cb) => cb(null, `${crypto.randomUUID()}.webm`),
});

const upload = multer({
  storage,
  limits: { fileSize: 200 * 1024 * 1024 },
  fileFilter: (_req, file, cb) => {
    if (file.mimetype.startsWith("video/")) cb(null, true);
    else cb(new Error("Only video files accepted"));
  },
});

/**
 * POST /api/video/export
 *
 * Public — accepts a WebM file, converts to H.264 MP4 via ffmpeg, returns the file.
 */
router.post(
  "/video/export",
  upload.single("video"),
  async (req, res) => {
    if (!req.file) {
      res.status(400).json({ error: "no_file", message: "No video file received." });
      return;
    }

    const inputPath = req.file.path;
    const outputPath = path.join(TMP, `${crypto.randomUUID()}.mp4`);

    try {
      await execFileAsync("ffmpeg", [
        "-y",
        "-i", inputPath,
        "-c:v", "libx264",
        "-preset", "fast",
        "-crf", "23",
        "-pix_fmt", "yuv420p",
        "-movflags", "+faststart",
        "-an",
        outputPath,
      ]);

      res.setHeader("Content-Type", "video/mp4");
      res.setHeader("Content-Disposition", 'attachment; filename="excreet-tiktok.mp4"');

      const stream = fs.createReadStream(outputPath);
      stream.pipe(res);
      stream.on("end", () => {
        fs.unlink(inputPath, () => {});
        fs.unlink(outputPath, () => {});
      });
    } catch (err) {
      fs.unlink(inputPath, () => {});
      fs.unlink(outputPath, () => {});
      req.log.error({ err }, "ffmpeg conversion failed");
      res.status(500).json({ error: "conversion_failed", message: "ffmpeg conversion failed." });
    }
  },
);

export default router;
