import { logger } from "../../lib/logger.js";

interface EpaWaterSystem {
  PWSID?:            string;
  PWS_NAME?:         string;
  CITY_NAME?:        string;
  STATE_CODE?:       string;
  POPULATION_SERVED_COUNT?: number;
  PRIMARY_SOURCE_CODE?: string;
  [key: string]: unknown;
}

interface EpaViolation {
  PWSID?:                 string;
  PWS_NAME?:              string;
  VIOLATION_NAME?:        string;
  CONTAMINANT_NAME?:      string;
  IS_HEALTH_BASED_IND?:   string;
  COMPL_PER_BEGIN_DATE?:  string;
  COMPL_PER_END_DATE?:    string;
  RTC_DATE?:              string;
  [key: string]: unknown;
}

const SOURCE_LABELS: Record<string, string> = {
  GW: "groundwater",
  SW: "surface water (river/reservoir)",
  GU: "groundwater under surface water influence",
  SW_Purchase: "purchased surface water",
  GW_Purchase: "purchased groundwater",
};

export async function fetchWaterQualityContext(zipCode: string): Promise<string> {
  if (!zipCode || !/^\d{5}$/.test(zipCode.trim())) {
    return "No zip code provided — municipal water quality data unavailable.";
  }

  const zip = zipCode.trim();

  try {
    const [systemsRes, violationsRes] = await Promise.allSettled([
      fetch(
        `https://data.epa.gov/efservice/WATER_SYSTEM/ZIP_CODE/${zip}/JSON`,
        { signal: AbortSignal.timeout(6000) },
      ),
      fetch(
        `https://data.epa.gov/efservice/SDWA_VIOLATIONS_ENFORCEMENT/ZIP_CODE/${zip}/JSON`,
        { signal: AbortSignal.timeout(6000) },
      ),
    ]);

    const systems: EpaWaterSystem[] =
      systemsRes.status === "fulfilled" && systemsRes.value.ok
        ? ((await systemsRes.value.json().catch(() => [])) as EpaWaterSystem[])
        : [];

    const violations: EpaViolation[] =
      violationsRes.status === "fulfilled" && violationsRes.value.ok
        ? ((await violationsRes.value.json().catch(() => [])) as EpaViolation[])
        : [];

    const primarySystem = systems[0];
    const systemName    = primarySystem?.PWS_NAME ?? "Unknown system";
    const source        = SOURCE_LABELS[primarySystem?.PRIMARY_SOURCE_CODE ?? ""] ?? "unknown source";
    const population    = primarySystem?.POPULATION_SERVED_COUNT
      ? `serving approximately ${primarySystem.POPULATION_SERVED_COUNT.toLocaleString()} people`
      : "";

    const healthViolations = violations.filter(
      v => v.IS_HEALTH_BASED_IND === "Y",
    );

    const recentViolations = healthViolations.slice(0, 5);

    const contaminants = [
      ...new Set(
        recentViolations
          .map(v => v.CONTAMINANT_NAME ?? v.VIOLATION_NAME)
          .filter(Boolean),
      ),
    ].join(", ");

    const lines: string[] = [];

    lines.push(`EPA Water System: ${systemName} (${source}${population ? ", " + population : ""})`);

    if (recentViolations.length > 0) {
      lines.push(`Health-based violations on record: ${recentViolations.length}`);
      lines.push(`Contaminants flagged: ${contaminants}`);
    } else if (violations.length > 0) {
      lines.push(`Violations on record: ${violations.length} (no recent health-based violations in retrieved data)`);
    } else {
      lines.push("No violation records retrieved from EPA SDWIS for this zip code.");
    }

    return lines.join("\n");
  } catch (err) {
    logger.warn({ err, zipCode }, "EPA water quality fetch failed — using fallback");
    return `EPA water data fetch failed for zip ${zipCode} — Claude should assess based on geographic knowledge.`;
  }
}
