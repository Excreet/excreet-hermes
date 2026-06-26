import { Router, type IRouter } from "express";
import multer from "multer";
import path from "path";
import fs from "fs";
import crypto from "crypto";

const router: IRouter = Router();

const UPLOAD_DIR = "/tmp/excreet-uploads";
if (!fs.existsSync(UPLOAD_DIR)) fs.mkdirSync(UPLOAD_DIR, { recursive: true });

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => cb(null, UPLOAD_DIR),
  filename: (_req, file, cb) => {
    const id = crypto.randomUUID();
    const ext = path.extname(file.originalname).toLowerCase();
    cb(null, `${id}${ext}`);
  },
});

const ALLOWED_MIME = new Set([
  "image/jpeg", "image/png", "image/webp", "image/gif",
  "application/pdf",
  "text/plain",
  "application/msword",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
]);

const upload = multer({
  storage,
  limits: { fileSize: 10 * 1024 * 1024 }, // 10 MB
  fileFilter: (_req, file, cb) => {
    if (ALLOWED_MIME.has(file.mimetype)) {
      cb(null, true);
    } else {
      cb(new Error(`File type not allowed: ${file.mimetype}`));
    }
  },
});

/**
 * POST /api/hermes/intake/upload
 *
 * Public endpoint — accepts a single file upload from the member intake form.
 * Returns a fileId that can be referenced in the intake payload.
 * Max size: 10 MB. Allowed: images, PDF, plain text, Word docs.
 */
router.post(
  "/intake/upload",
  upload.single("file"),
  (req, res) => {
    if (!req.file) {
      res.status(400).json({ error: "no_file", message: "No file received." });
      return;
    }
    req.log.info(
      { filename: req.file.filename, size: req.file.size, mime: req.file.mimetype },
      "Intake file uploaded",
    );
    res.status(200).json({
      fileId:       req.file.filename,
      originalName: req.file.originalname,
      size:         req.file.size,
      mime:         req.file.mimetype,
      message:      "File received.",
    });
  },
);

export default router;
