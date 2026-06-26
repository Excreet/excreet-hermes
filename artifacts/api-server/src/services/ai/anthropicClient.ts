import Anthropic from "@anthropic-ai/sdk";
import { logger } from "../../lib/logger.js";

if (!process.env["AI_INTEGRATIONS_ANTHROPIC_BASE_URL"]) {
  logger.error(
    "AI_INTEGRATIONS_ANTHROPIC_BASE_URL is not set — AI workflows will fail until the Anthropic integration is provisioned",
  );
}

if (!process.env["AI_INTEGRATIONS_ANTHROPIC_API_KEY"]) {
  logger.error(
    "AI_INTEGRATIONS_ANTHROPIC_API_KEY is not set — AI workflows will fail until the Anthropic integration is provisioned",
  );
}

export const anthropic = new Anthropic({
  apiKey: process.env["AI_INTEGRATIONS_ANTHROPIC_API_KEY"],
  baseURL: process.env["AI_INTEGRATIONS_ANTHROPIC_BASE_URL"],
});
