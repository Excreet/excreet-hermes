import { Router, type IRouter } from "express";

const router: IRouter = Router();

const startTime = Date.now();

/**
 * GET /api/hermes/health
 *
 * Hermes-specific health check. Does NOT require authentication so that
 * monitoring tools can poll it without a key.
 */
router.get("/health", (_req, res) => {
  res.json({
    service: "hermes",
    status: "ok",
    version: "0.1.0",
    phase: "scaffold",
    uptimeSeconds: Math.floor((Date.now() - startTime) / 1000),
    timestamp: new Date().toISOString(),
  });
});

export default router;
