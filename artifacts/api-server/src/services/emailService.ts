import { Resend } from "resend";
import { logger } from "../lib/logger.js";

/**
 * Email alert service for Excreet Hermes — uses Resend.
 *
 * Required secrets:
 *   RESEND_API_KEY  — API key from resend.com (free tier: 100 emails/day)
 *   OWNER_EMAIL     — address that receives alerts (e.g. daytoheal@yahoo.com)
 *
 * Sign up at resend.com → API Keys → Create Key → paste here.
 * No SMTP, no Yahoo settings, no app passwords needed.
 */

const RESEND_API_KEY = process.env["RESEND_API_KEY"] ?? "";
const OWNER_EMAIL    = process.env["OWNER_EMAIL"]    ?? "";

export function emailConfigured(): boolean {
  return !!(RESEND_API_KEY && OWNER_EMAIL);
}

export async function sendOwnerEmail(subject: string, body: string): Promise<void> {
  if (!emailConfigured()) {
    logger.warn("email: not configured — skipping alert (set RESEND_API_KEY and OWNER_EMAIL)");
    return;
  }

  const html = `
    <div style="font-family:sans-serif;max-width:600px;margin:0 auto;background:#0a0318;color:#fff;padding:24px;border-radius:10px;">
      <div style="border-bottom:1px solid rgba(245,197,24,0.3);padding-bottom:12px;margin-bottom:20px;">
        <span style="color:#F5C518;font-size:1.1rem;font-weight:600;letter-spacing:0.05em;">EXCREET HERMES</span>
        <span style="color:rgba(255,255,255,0.4);font-size:0.85rem;margin-left:10px;">Site Monitor</span>
      </div>
      <pre style="font-family:monospace;font-size:0.92rem;line-height:1.6;white-space:pre-wrap;color:#eee;">${body}</pre>
      <div style="margin-top:24px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.08);font-size:0.75rem;color:rgba(255,255,255,0.3);">
        Automated alert from Hermes • excreet.com
      </div>
    </div>`;

  try {
    const resend = new Resend(RESEND_API_KEY);
    const { error } = await resend.emails.send({
      from:    "Excreet Hermes <onboarding@resend.dev>",
      to:      [OWNER_EMAIL],
      subject,
      text:    body,
      html,
    });
    if (error) {
      logger.error({ error }, "email: Resend returned an error");
    } else {
      logger.info({ to: OWNER_EMAIL }, "email: owner alert sent via Resend");
    }
  } catch (err) {
    logger.error({ err }, "email: failed to send owner alert");
  }
}
