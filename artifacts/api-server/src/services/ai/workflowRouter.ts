import { healthIntake } from "./workflows/healthIntake.js";
import { pharmaceuticalIntake } from "./workflows/pharmaceuticalIntake.js";

type WorkflowHandler = (
  payload: Record<string, unknown>,
) => Promise<unknown>;

/**
 * Dispatch map: workflow_type → handler function.
 *
 * Registered workflows:
 *   health_intake          — General symptom/lifestyle triage (v2 tier schema)
 *   pharmaceutical_intake  — Pharmaceutical drug interaction Clinical Pattern Report
 */
const WORKFLOWS: Record<string, WorkflowHandler> = {
  health_intake: healthIntake,
  pharmaceutical_intake: pharmaceuticalIntake,
};

export type WorkflowRouterResult =
  | { success: true; result: unknown }
  | { success: false; error: string };

export async function routeWorkflow(
  workflowType: string,
  payload: Record<string, unknown>,
): Promise<WorkflowRouterResult> {
  const handler = WORKFLOWS[workflowType];

  if (!handler) {
    return {
      success: false,
      error: `Unknown workflow_type: "${workflowType}". Registered: ${Object.keys(WORKFLOWS).join(", ")}.`,
    };
  }

  try {
    const result = await handler(payload);
    return { success: true, result };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    return { success: false, error: message };
  }
}
