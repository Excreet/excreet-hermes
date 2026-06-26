import { db, memberSmsPrefsTable } from "@workspace/db";
import { eq } from "drizzle-orm";
import { sendMemberReminder, smsConfigured, type Channel } from "./smsService.js";
import { logger } from "../lib/logger.js";

const SEND_HOUR_ET = 7;

function msUntilNextSend(): number {
  const now = new Date();
  const etOffset = -5 * 60;
  const utcMinutes = now.getUTCHours() * 60 + now.getUTCMinutes();
  const etMinutes = (utcMinutes + etOffset + 1440) % 1440;
  const targetMinutes = SEND_HOUR_ET * 60;
  let diffMinutes = targetMinutes - etMinutes;
  if (diffMinutes <= 0) diffMinutes += 1440;
  return diffMinutes * 60 * 1000;
}

async function runMorningReminders(): Promise<void> {
  if (!smsConfigured()) {
    logger.info("sms-scheduler: Twilio not configured, skipping");
    return;
  }

  const prefs = await db
    .select()
    .from(memberSmsPrefsTable)
    .where(eq(memberSmsPrefsTable.optedIn, true));

  logger.info({ count: prefs.length }, "sms-scheduler: sending morning reminders");

  for (const pref of prefs) {
    const channel = (pref.channel ?? "sms") as Channel;
    await sendMemberReminder(pref.phoneNumber, pref.memberId, channel);
    await new Promise(r => setTimeout(r, 200));
  }

  logger.info("sms-scheduler: morning reminders complete");
}

export function startMorningReminderScheduler(): void {
  const delay = msUntilNextSend();
  const hh = Math.floor(delay / 3600000);
  const mm = Math.floor((delay % 3600000) / 60000);
  logger.info({ nextIn: `${hh}h ${mm}m` }, "sms-scheduler: started, first run scheduled");

  setTimeout(function tick() {
    runMorningReminders().catch(err =>
      logger.error({ err }, "sms-scheduler: run failed"),
    );
    setTimeout(tick, 24 * 60 * 60 * 1000);
  }, delay);
}
