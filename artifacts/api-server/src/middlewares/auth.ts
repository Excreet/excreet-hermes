import { type Request, type Response, type NextFunction } from "express";

/**
 * Hermes API key authentication middleware.
 *
 * WordPress (and any authorized caller) must send:
 *   Authorization: Bearer <HERMES_API_KEY>
 *
 * Set HERMES_API_KEY in your environment/secrets manager.
 * Never expose it in frontend code or logs.
 */
export function requireApiKey(
  req: Request,
  res: Response,
  next: NextFunction,
): void {
  const apiKey = process.env["HERMES_API_KEY"];

  if (!apiKey) {
    req.log.error("HERMES_API_KEY is not configured on this server");
    res.status(500).json({
      error: "server_misconfiguration",
      message: "Hermes API key is not configured.",
    });
    return;
  }

  const authHeader = req.headers["authorization"];
  const providedKey =
    authHeader?.startsWith("Bearer ") ? authHeader.slice(7) : null;

  if (!providedKey || providedKey !== apiKey) {
    req.log.warn({ ip: req.ip }, "Unauthorized request — invalid API key");
    res.status(401).json({
      error: "unauthorized",
      message: "Invalid or missing API key.",
    });
    return;
  }

  next();
}
