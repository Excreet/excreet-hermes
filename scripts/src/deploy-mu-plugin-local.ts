import { execFileSync } from "child_process";
import { existsSync, readFileSync, writeFileSync, unlinkSync } from "fs";
import { resolve } from "path";

/**
 * deploy-mu-plugin-local.ts
 *
 * Variant of deploy-mu-plugin.ts that deploys from the LOCAL Replit file
 * rather than fetching from GitHub. Use this when the local file is ahead
 * of GitHub (e.g. during active development before a GitHub push).
 *
 * Usage:
 *   pnpm --filter @workspace/scripts run deploy:mu-plugin:local
 *   pnpm --filter @workspace/scripts run deploy:mu-plugin:local:dry-run
 */

const DRY_RUN = process.argv.includes("--dry-run");

const LOCAL_PLUGIN = resolve(
  process.cwd(),
  "../artifacts/api-server/wordpress/excreet-hermes-client.php",
);

const LIVE_VERSION_URL = "https://excreet.com/wp-json/excreet/v1/intake";

const REMOTE_MU_PLUGINS =
  "/home/customer/www/excreet.com/public_html/wp-content/mu-plugins";

const REMOTE_FILENAME = "excreet-hermes-client.php";
const TMP_PLUGIN      = `/tmp/deploy-local-${REMOTE_FILENAME}`;
const TMP_KEY         = `/tmp/deploy-ssh-key-local`;

const OPCACHE_BASE =
  "/home/customer/.opcache/8.2.30-Dec 18 2025-16:29:25-23d3f3e759bf1884a90d8c8be6a27edd";
const OPCACHE_BIN =
  `${OPCACHE_BASE}${REMOTE_MU_PLUGINS}/${REMOTE_FILENAME}.bin`;

function requireEnv(key: string): string {
  const val = process.env[key];
  if (!val) {
    console.error(`ERROR: Required env var ${key} is not set.`);
    process.exit(1);
  }
  return val;
}

function parsePluginVersion(content: string): string | null {
  const m = content.match(/^\s*\*\s*Version:\s*(.+)$/m);
  return m ? m[1].trim() : null;
}

function compareVersions(a: string, b: string): number {
  const pa = a.split(".").map(Number);
  const pb = b.split(".").map(Number);
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    const diff = (pa[i] ?? 0) - (pb[i] ?? 0);
    if (diff !== 0) return diff;
  }
  return 0;
}

function curlGet(url: string): string {
  return execFileSync(
    "curl",
    ["--silent", "--show-error", "--fail", "--max-time", "15", url],
    { encoding: "utf8" },
  );
}

function sshRun(
  host: string,
  user: string,
  port: string,
  keyFile: string,
  command: string,
): string {
  return execFileSync(
    "ssh",
    [
      "-i", keyFile,
      "-o", "StrictHostKeyChecking=no",
      "-o", "BatchMode=yes",
      "-p", port,
      `${user}@${host}`,
      command,
    ],
    { encoding: "utf8" },
  );
}

const DEPLOY_KEY = requireEnv("SITEGROUND_DEPLOY_KEY");
const SSH_HOST   = requireEnv("SITEGROUND_SSH_HOST");
const SSH_USER   = requireEnv("SITEGROUND_SSH_USER");
const SSH_PORT   = requireEnv("SITEGROUND_SSH_PORT");

console.log("=== Excreet MU Plugin Deploy (LOCAL SOURCE) ===");
if (DRY_RUN) console.log("    MODE: DRY RUN — no files will be uploaded");
console.log("");

// Step 1: Read local file
console.log("Step 1/5 — Reading local plugin file...");
console.log(`  Path: ${LOCAL_PLUGIN}`);

if (!existsSync(LOCAL_PLUGIN)) {
  console.error(`ERROR: Local plugin file not found at ${LOCAL_PLUGIN}`);
  process.exit(1);
}

const pluginContent = readFileSync(LOCAL_PLUGIN, "utf8");
const sourceVersion = parsePluginVersion(pluginContent);

if (!sourceVersion) {
  console.error("ERROR: Could not parse 'Version:' header from local file.");
  process.exit(1);
}
console.log(`  Version: ${sourceVersion}`);

// Step 2: Check live version
console.log("");
console.log("Step 2/5 — Reading live WordPress plugin version...");

let liveVersion: string | null = null;
try {
  const body = curlGet(LIVE_VERSION_URL);
  const parsed = JSON.parse(body) as Record<string, unknown>;
  if (typeof parsed["version"] === "string") liveVersion = parsed["version"];
} catch {
  console.warn("  WARNING: Live version endpoint unreachable. Version guard skipped.");
}
console.log(`  Live version: ${liveVersion ?? "(unreachable — guard skipped)"}`);

// Step 3: Version guard
console.log("");
console.log("Step 3/5 — Version guard...");

if (liveVersion) {
  const cmp = compareVersions(sourceVersion, liveVersion);
  if (cmp < 0) {
    console.error(`ABORTED — Source (${sourceVersion}) is LOWER than live (${liveVersion}).`);
    process.exit(1);
  }
  if (cmp === 0) {
    console.log(`  ${sourceVersion} == ${liveVersion} — re-deploying same version.`);
  } else {
    console.log(`  ${sourceVersion} > ${liveVersion} — upgrade confirmed.`);
  }
} else {
  console.log("  Guard skipped.");
}

// Step 4: Summary
console.log("");
console.log("Step 4/5 — Deploy summary");
console.log("  ┌─────────────────────────────────────────────────────────────");
console.log(`  │ Source  : LOCAL ${LOCAL_PLUGIN}`);
console.log(`  │ Version : ${sourceVersion} (local)  →  ${liveVersion ?? "unknown"} (live)`);
console.log(`  │ Target  : ${SSH_USER}@${SSH_HOST}:${REMOTE_MU_PLUGINS}/${REMOTE_FILENAME}`);
console.log(`  │ OPcache : ${OPCACHE_BIN}`);
console.log(`  │ Mode    : ${DRY_RUN ? "DRY RUN — no upload will occur" : "LIVE DEPLOY"}`);
console.log("  └─────────────────────────────────────────────────────────────");

if (DRY_RUN) {
  console.log("");
  console.log("Dry run complete. No files were uploaded.");
  process.exit(0);
}

// Step 5: SSH deploy
console.log("");
console.log("Step 5/5 — Deploying via SSH...");

const normalizedKey = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, body) => {
    const clean = body.replace(/\s+/g, "");
    const lines = clean.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });

writeFileSync(TMP_PLUGIN, pluginContent, "utf8");
writeFileSync(TMP_KEY, normalizedKey + "\n", { encoding: "utf8", mode: 0o600 });

try {
  console.log("  Uploading via SCP...");
  execFileSync(
    "scp",
    [
      "-i", TMP_KEY,
      "-o", "StrictHostKeyChecking=no",
      "-o", "BatchMode=yes",
      "-P", SSH_PORT,
      TMP_PLUGIN,
      `${SSH_USER}@${SSH_HOST}:${REMOTE_MU_PLUGINS}/${REMOTE_FILENAME}`,
    ],
    { stdio: "inherit" },
  );
  console.log("  Upload complete.");

  console.log("  Clearing OPcache...");

  const REMOTE_PHP_PATH = `${REMOTE_MU_PLUGINS}/${REMOTE_FILENAME}`;
  const FLUSH_SCRIPT_NAME = `_excreet_opcache_flush_${Date.now()}.php`;
  const FLUSH_SCRIPT_PATH = `/home/customer/www/excreet.com/public_html/${FLUSH_SCRIPT_NAME}`;
  const FLUSH_SCRIPT_URL  = `https://excreet.com/${FLUSH_SCRIPT_NAME}`;

  const flushPhp = `<?php
$reset = function_exists('opcache_reset') ? opcache_reset() : false;
$inv   = function_exists('opcache_invalidate') ? opcache_invalidate('${REMOTE_PHP_PATH}', true) : false;
@unlink(__FILE__);
echo json_encode(['reset' => $reset, 'invalidated' => $inv, 'ts' => time()]);
`;

  try {
    sshRun(
      SSH_HOST, SSH_USER, SSH_PORT, TMP_KEY,
      `rm -f '${OPCACHE_BIN}' ; cat > '${FLUSH_SCRIPT_PATH}' << 'PHPEOF'\n${flushPhp}\nPHPEOF`,
    );

    execFileSync("sleep", ["1"]);
    let flushResult = "(no response)";
    try {
      flushResult = execFileSync(
        "curl",
        ["--silent", "--max-time", "10", FLUSH_SCRIPT_URL],
        { encoding: "utf8" },
      ).trim();
    } catch { /* ignore */ }
    console.log(`  OPcache flush result: ${flushResult}`);

    try {
      sshRun(SSH_HOST, SSH_USER, SSH_PORT, TMP_KEY, `rm -f '${FLUSH_SCRIPT_PATH}'`);
    } catch { /* ignore */ }

    console.log("  OPcache cleared.");
  } catch {
    console.warn("  WARNING: OPcache clear failed — PHP may serve stale bytecode until restart.");
  }

  console.log("  Verifying live endpoint...");
  let postDeployVersion: string | null = null;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      execFileSync("sleep", ["2"]);
      const body = curlGet(LIVE_VERSION_URL);
      const parsed = JSON.parse(body) as Record<string, unknown>;
      if (typeof parsed["version"] === "string") {
        postDeployVersion = parsed["version"];
        break;
      }
    } catch { /* retry */ }
  }

  if (postDeployVersion === sourceVersion) {
    console.log(`  Live endpoint now reports: ${postDeployVersion} ✅`);
    console.log("");
    console.log(`Deploy SUCCESSFUL — v${sourceVersion} is live on excreet.com`);
  } else if (postDeployVersion) {
    console.warn(`  Live endpoint reports: ${postDeployVersion} (expected ${sourceVersion})`);
    console.warn("  OPcache may still be warming — check in 30s.");
  } else {
    console.warn("  Could not verify live version — check manually:");
    console.warn(`  curl ${LIVE_VERSION_URL}`);
  }
} finally {
  if (existsSync(TMP_PLUGIN)) unlinkSync(TMP_PLUGIN);
  if (existsSync(TMP_KEY)) unlinkSync(TMP_KEY);
}
