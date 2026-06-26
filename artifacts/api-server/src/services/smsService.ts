import twilio from "twilio";
import { logger } from "../lib/logger.js";

export type Channel = "sms" | "whatsapp";

function getClient() {
  const sid   = process.env["TWILIO_ACCOUNT_SID"];
  const token = process.env["TWILIO_AUTH_TOKEN"];
  if (!sid || !token) return null;
  return twilio(sid, token);
}

function fromAddress(channel: Channel): string {
  const base = process.env["TWILIO_FROM_NUMBER"] ?? "";
  return channel === "whatsapp" ? `whatsapp:${base}` : base;
}

function toAddress(phone: string, channel: Channel): string {
  return channel === "whatsapp" ? `whatsapp:${phone}` : phone;
}

const OWNER       = process.env["OWNER_PHONE_NUMBER"] ?? "";
const OWNER_CHAN  = (process.env["OWNER_CHANNEL"] ?? "sms") as Channel;

export async function sendOwnerAlert(message: string): Promise<void> {
  if (!OWNER) {
    logger.warn("sms: OWNER_PHONE_NUMBER not set — skipping alert");
    return;
  }
  const client = getClient();
  if (!client) {
    logger.warn("sms: Twilio credentials not set — skipping alert");
    return;
  }
  try {
    await client.messages.create({
      body: message,
      from: fromAddress(OWNER_CHAN),
      to:   toAddress(OWNER, OWNER_CHAN),
    });
    logger.info({ to: OWNER, channel: OWNER_CHAN }, "sms: owner alert sent");
  } catch (err) {
    logger.error({ err }, "sms: failed to send owner alert");
  }
}

export async function sendMemberReminder(
  toPhone: string,
  memberName: string,
  channel: Channel = "sms",
): Promise<void> {
  const client = getClient();
  if (!client) {
    logger.warn("sms: Twilio credentials not set — skipping member reminder");
    return;
  }

  const greeting = memberName ? `, ${memberName}` : "";
  const body =
    channel === "whatsapp"
      ? `*Good morning${greeting}!* 🌿\n\nYour body left you a message this morning.\n\nTime for your Excreet body check 👇\nhttps://excreet.com/body-check/`
      : `Good morning${greeting}! Time for your Excreet body check — your body left you a message. https://excreet.com/body-check/`;

  try {
    await client.messages.create({
      body,
      from: fromAddress(channel),
      to:   toAddress(toPhone, channel),
    });
    logger.info({ to: toPhone, channel }, "sms: morning reminder sent");
  } catch (err) {
    logger.error({ err, to: toPhone, channel }, "sms: failed to send member reminder");
  }
}

export async function sendMessage(
  toPhone: string,
  body: string,
  channel: Channel = "sms",
): Promise<void> {
  const client = getClient();
  if (!client) return;
  try {
    await client.messages.create({
      body,
      from: fromAddress(channel),
      to:   toAddress(toPhone, channel),
    });
    logger.info({ to: toPhone, channel }, "sms: message sent");
  } catch (err) {
    logger.error({ err, to: toPhone, channel }, "sms: failed to send message");
  }
}

export function smsConfigured(): boolean {
  return !!(
    process.env["TWILIO_ACCOUNT_SID"] &&
    process.env["TWILIO_AUTH_TOKEN"] &&
    process.env["TWILIO_FROM_NUMBER"]
  );
}

export function whatsappConfigured(): boolean {
  return smsConfigured() && !!(process.env["TWILIO_WHATSAPP_ENABLED"] === "true");
}
