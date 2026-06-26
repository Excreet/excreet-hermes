import { Router, type IRouter } from "express";
import fs from "fs";
import { renderVideo, isRendering, mp4Exists, getMP4Path } from "../lib/videoRecorder.js";

const router: IRouter = Router();

const VIDEO_URL = "http://localhost:80/excreet-prep-video/";

/**
 * GET /api/video/render-status
 * Returns current render status and whether the MP4 is ready.
 */
router.get("/video/render-status", (_req, res) => {
  res.json({
    rendering: isRendering(),
    ready: mp4Exists(),
  });
});

/**
 * POST /api/video/render
 * Triggers a headless render of the TikTok video. Non-blocking — returns immediately.
 */
router.post("/video/render", (req, res) => {
  if (isRendering()) {
    res.status(202).json({ status: "rendering", message: "Already rendering, please wait." });
    return;
  }

  req.log.info("Starting headless video render");
  renderVideo(VIDEO_URL).catch(err => {
    req.log.error({ err }, "Video render failed");
  });

  res.json({ status: "started", message: "Render started. Poll /api/video/render-status." });
});

/**
 * GET /api/video/download
 * Streams the rendered MP4 for direct download.
 */
router.get("/video/download", (req, res) => {
  const mp4Path = getMP4Path();
  if (!mp4Exists()) {
    res.status(404).json({ error: "not_ready", message: "Video not yet rendered. POST /api/video/render first." });
    return;
  }

  const stat = fs.statSync(mp4Path);
  res.setHeader("Content-Type", "video/mp4");
  res.setHeader("Content-Disposition", 'attachment; filename="excreet-tiktok.mp4"');
  res.setHeader("Content-Length", stat.size);
  req.log.info({ size: stat.size }, "Serving rendered MP4");
  fs.createReadStream(mp4Path).pipe(res);
});

export default router;
