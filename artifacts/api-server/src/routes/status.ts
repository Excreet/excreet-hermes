import { Router, type Request, type Response } from "express";

const router = Router();

router.get("/", (_req: Request, res: Response) => {
  const uptime = process.uptime();
  const hours = Math.floor(uptime / 3600);
  const minutes = Math.floor((uptime % 3600) / 60);
  const seconds = Math.floor(uptime % 60);
  const uptimeStr = `${hours}h ${minutes}m ${seconds}s`;

  res.setHeader("Content-Type", "text/html; charset=utf-8");
  res.send(`<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hermes — Excreet Intelligence Backend</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #f0f4f8;
      color: #243b53;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      padding: 40px 48px;
      max-width: 560px;
      width: 100%;
      box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    }
    .logo {
      font-size: 13px;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: #6d28d9;
      margin-bottom: 20px;
    }
    h1 {
      font-size: 28px;
      font-weight: 800;
      color: #102a43;
      line-height: 1.25;
      margin-bottom: 8px;
    }
    .tagline {
      font-size: 15px;
      color: #486581;
      line-height: 1.6;
      margin-bottom: 32px;
    }
    .status-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 14px 18px;
      border-radius: 10px;
      margin-bottom: 12px;
      font-size: 14px;
      font-weight: 600;
    }
    .status-ok { background: #e3fcec; color: #137333; }
    .status-dot {
      width: 9px; height: 9px;
      border-radius: 50%;
      background: currentColor;
      flex-shrink: 0;
    }
    .meta {
      margin-top: 28px;
      padding-top: 20px;
      border-top: 1px solid #e6edf3;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .meta-item label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #829ab1;
      margin-bottom: 4px;
    }
    .meta-item span {
      font-size: 14px;
      color: #243b53;
      font-weight: 600;
    }
    .footer {
      margin-top: 28px;
      font-size: 12px;
      color: #b0c4d8;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">Excreet · Hermes</div>
    <h1>Health Intelligence Backend</h1>
    <p class="tagline">The AI processing layer that powers Excreet.com's member health triage, Vitality Scoring, and Ministry of Healing pathways.</p>

    <div class="status-row status-ok">
      <span class="status-dot"></span>
      API operational — all systems normal
    </div>
    <div class="status-row status-ok">
      <span class="status-dot"></span>
      Hermes intake endpoint ready
    </div>
    <div class="status-row status-ok">
      <span class="status-dot"></span>
      AI pipeline connected
    </div>

    <div class="meta">
      <div class="meta-item">
        <label>Uptime</label>
        <span>${uptimeStr}</span>
      </div>
      <div class="meta-item">
        <label>Environment</label>
        <span>${process.env["NODE_ENV"] ?? "development"}</span>
      </div>
      <div class="meta-item">
        <label>Health check</label>
        <span><a href="/api/healthz" style="color:#6d28d9;">/api/healthz</a></span>
      </div>
      <div class="meta-item">
        <label>Region</label>
        <span>Auto</span>
      </div>
    </div>

    <div class="footer">This is a private backend service. Member-facing features are at <strong>excreet.com</strong></div>
  </div>
</body>
</html>`);
});

export default router;
