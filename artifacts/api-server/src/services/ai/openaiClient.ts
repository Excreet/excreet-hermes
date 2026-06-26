import OpenAI from "openai";
import { logger } from "../../lib/logger.js";

const apiKey = process.env["OPENAI_API_KEY"];

if (!apiKey) {
  logger.error(
    "OPENAI_API_KEY is not configured — AI workflows will fail until it is set",
  );
}

export const openai = new OpenAI({ apiKey });
