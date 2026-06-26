import { execFileSync } from "child_process";
import { existsSync, writeFileSync, unlinkSync, chmodSync } from "fs";

/**
 * deploy-mu-plugin.ts
 *
 * SOURCE OF TRUTH: GitHub — Excreet/excreet-wordpress-bridge
 * Path in repo:    wp-content/mu-plugins/excreet-hermes-client.php
 *
 * This script:
 *   1. Fetches the plugin from GitHub main (never from the local Replit copy)
 *   2. Reads the Version header from the GitHub file
 *   3. Reads the live version from the WordPress REST endpoint
 *   4. ABORTS if source version < live version (prevents downgrade)
 *   5. Prints a full summary of source/live/target before any upload
 *   6. In --dry-run mode, stops after the summary (no upload)
 *   7. Uploads via SSH/SCP to SiteGround mu-plugins directory
 *   8. Deletes the OPcache .bin file so PHP recompiles from the new source
 *   9. Verifies the live REST endpoint reflects the new version
 *
 * Required secrets / env vars:
 *   GITHUB_TOKEN              — classic PAT with repo scope
 *   SITEGROUND_DEPLOY_KEY     — SSH private key (PEM, full content including headers)
 *   SITEGROUND_SSH_HOST       — ssh.excreet.com
 *   SITEGROUND_SSH_USER       — e.g. u2198-g6bobebgdwk2
 *   SITEGROUND_SSH_PORT       — e.g. 18765
 *
 * Usage:
 *   pnpm --filter @workspace/scripts run deploy:mu-plugin             # live deploy
 *   pnpm --filter @workspace/scripts run deploy:mu-plugin:dry-run     # dry run
 *
 * Never logs or prints credentials.
 */

const DRY_RUN = process.argv.includes("--dry-run");

// ─── Config ──────────────────────────────────────────────────────────────────

// Use the GitHub API endpoint — raw.githubusercontent.com is CDN-cached and stale after commits.
// The API endpoint with Accept: application/vnd.github.v3.raw always returns the latest committed content.
const GITHUB_RAW_URL =
  "https://api.github.com/repos/Excreet/excreet-wordpress-bridge/contents/wp-content/mu-plugins/excreet-hermes-client.php";

const LIVE_VERSION_URL = "https://excreet.com/wp-json/excreet/v1/intake";

const REMOTE_MU_PLUGINS =
  "/home/customer/www/excreet.com/public_html/wp-content/mu-plugins";

const REMOTE_FILENAME = "excreet-hermes-client.php";
const TMP_PLUGIN      = `/tmp/deploy-${REMOTE_FILENAME}`;
const TMP_KEY         = `/tmp/deploy-ssh-key`;

// OPcache path for PHP 8.2 on SiteGround — update if PHP version changes
const OPCACHE_BASE =
  "/home/customer/.opcache/8.2.30-Dec 18 2025-16:29:25-23d3f3e759bf1884a90d8c8be6a27edd";
const OPCACHE_BIN =
  `${OPCACHE_BASE}${REMOTE_MU_PLUGINS}/${REMOTE_FILENAME}.bin`;

// ─── Helpers ─────────────────────────────────────────────────────────────────

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

function curlGet(url: string, extraArgs: string[] = []): string {
  return execFileSync(
    "curl",
    ["--silent", "--show-error", "--fail", "--max-time", "15", ...extraArgs, url],
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

// ─── Read required env vars ───────────────────────────────────────────────────

const GITHUB_TOKEN = requireEnv("GITHUB_TOKEN");
const DEPLOY_KEY   = requireEnv("SITEGROUND_DEPLOY_KEY");
const SSH_HOST     = requireEnv("SITEGROUND_SSH_HOST");
const SSH_USER     = requireEnv("SITEGROUND_SSH_USER");
const SSH_PORT     = requireEnv("SITEGROUND_SSH_PORT");

// ─── Step 1: Fetch plugin from GitHub ────────────────────────────────────────

console.log("=== Excreet MU Plugin Deploy ===");
if (DRY_RUN) console.log("    MODE: DRY RUN — no files will be uploaded");
console.log("");
console.log("Step 1/5 — Fetching plugin from GitHub (source of truth)...");
console.log(`  Repo   : Excreet/excreet-wordpress-bridge`);
console.log(`  Branch : main`);
console.log(`  Path   : wp-content/mu-plugins/${REMOTE_FILENAME}`);

let pluginContent: string;
try {
  pluginContent = curlGet(GITHUB_RAW_URL, [
    "-H", `Authorization: Bearer ${GITHUB_TOKEN}`,
    "-H", "Accept: application/vnd.github.v3.raw",
    "-H", "X-GitHub-Api-Version: 2022-11-28",
    "-H", "User-Agent: excreet-deploy-script",
  ]);
} catch {
  console.error("");
  console.error("ERROR: Failed to fetch plugin from GitHub.");
  console.error("  Possible causes:");
  console.error("  - GITHUB_TOKEN is missing or expired");
  console.error("  - Token lacks repo scope on Excreet/excreet-wordpress-bridge");
  console.error("  - Branch 'main' or the file path has changed");
  process.exit(1);
}

const sourceVersion = parsePluginVersion(pluginContent);
if (!sourceVersion) {
  console.error("ERROR: Could not parse 'Version:' header from the GitHub file.");
  process.exit(1);
}
console.log(`  Version: ${sourceVersion}`);

// ─── Step 2: Check live WordPress version ────────────────────────────────────

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
console.log(`  Version  : ${liveVersion ?? "(unreachable — guard skipped)"}`);

// ─── Step 3: Version guard ────────────────────────────────────────────────────

console.log("");
console.log("Step 3/5 — Version guard...");

if (liveVersion) {
  const cmp = compareVersions(sourceVersion, liveVersion);
  if (cmp < 0) {
    console.error("");
    console.error(`ABORTED — Source (${sourceVersion}) is LOWER than live (${liveVersion}).`);
    console.error("  GitHub repo is behind the live site. Update the repo first.");
    process.exit(1);
  }
  if (cmp === 0) {
    console.log(`  ${sourceVersion} == ${liveVersion} — re-deploying same version.`);
  } else {
    console.log(`  ${sourceVersion} > ${liveVersion} — upgrade confirmed.`);
  }
} else {
  console.log("  Live version unreachable — version guard skipped.");
}

// ─── Step 4: Summary + dry-run gate ──────────────────────────────────────────

console.log("");
console.log("Step 4/5 — Deploy summary");
console.log("  ┌─────────────────────────────────────────────────────────────");
console.log(`  │ Source  : GitHub Excreet/excreet-wordpress-bridge @ main`);
console.log(`  │ Version : ${sourceVersion} (GitHub)  →  ${liveVersion ?? "unknown"} (live)`);
console.log(`  │ Target  : ${SSH_USER}@${SSH_HOST}:${REMOTE_MU_PLUGINS}/${REMOTE_FILENAME}`);
console.log(`  │ OPcache : ${OPCACHE_BIN}`);
console.log(`  │ Mode    : ${DRY_RUN ? "DRY RUN — no upload will occur" : "LIVE DEPLOY"}`);
console.log("  └─────────────────────────────────────────────────────────────");
console.log("");

if (DRY_RUN) {
  console.log("Dry run complete. No files were uploaded.");
  process.exit(0);
}

// ─── Step 5: SSH deploy ───────────────────────────────────────────────────────

console.log("Step 5/5 — Deploying via SSH...");

// Write plugin file and SSH key to tmp (cleaned up in finally)
// Normalize key: Replit Secrets may collapse newlines to spaces.
// Reconstruct proper PEM format with real line breaks.
const normalizedKey = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, body) => {
    // body may be a single long base64 string with spaces — split into 64-char lines
    const clean = body.replace(/\s+/g, "");
    const lines = clean.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });
writeFileSync(TMP_PLUGIN, pluginContent, "utf8");
writeFileSync(TMP_KEY, normalizedKey + "\n", { encoding: "utf8", mode: 0o600 });

try {
  // 5a — SCP upload
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

  // 5b — Clear OPcache: delete .bin file + call opcache_invalidate() in-process
  console.log("  Clearing OPcache compiled bytecode...");

  const REMOTE_PHP_PATH = `${REMOTE_MU_PLUGINS}/${REMOTE_FILENAME}`;
  const FLUSH_SCRIPT_NAME = `_excreet_opcache_flush_${Date.now()}.php`;
  const FLUSH_SCRIPT_PATH = `/home/customer/www/excreet.com/public_html/${FLUSH_SCRIPT_NAME}`;
  const FLUSH_SCRIPT_URL  = `https://excreet.com/${FLUSH_SCRIPT_NAME}`;

  // PHP script that resets ALL OPcache in-memory bytecode AND deletes itself.
  // opcache_invalidate() can fail on CloudLinux/CageFS when called cross-file;
  // opcache_reset() clears the full per-process cache and always succeeds.
  const flushPhp = `<?php
$reset = function_exists('opcache_reset') ? opcache_reset() : false;
$inv   = function_exists('opcache_invalidate') ? opcache_invalidate('${REMOTE_PHP_PATH}', true) : false;
@unlink(__FILE__);
echo json_encode(['reset' => $reset, 'invalidated' => $inv, 'ts' => time()]);
`;

  try {
    // Delete disk .bin and write flush script in one SSH command
    sshRun(
      SSH_HOST, SSH_USER, SSH_PORT, TMP_KEY,
      `rm -f '${OPCACHE_BIN}' ; cat > '${FLUSH_SCRIPT_PATH}' << 'PHPEOF'\n${flushPhp}\nPHPEOF`,
    );

    // Trigger the flush by requesting the script via HTTP
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

    // Clean up — script deletes itself, but SSH rm is a safety net
    try {
      sshRun(SSH_HOST, SSH_USER, SSH_PORT, TMP_KEY, `rm -f '${FLUSH_SCRIPT_PATH}'`);
    } catch { /* ignore */ }

    console.log("  OPcache cleared");
  } catch {
    console.warn("  WARNING: OPcache clear failed — PHP may serve stale bytecode until next restart.");
  }

  // 5c — Verify live version updated
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
    } catch {
      // retry
    }
  }

  if (postDeployVersion === sourceVersion) {
    console.log(`  Live endpoint now reports: ${postDeployVersion} ✅`);
    console.log("");
    console.log(`Deploy SUCCESSFUL — v${sourceVersion} is live on excreet.com`);
  } else if (postDeployVersion) {
    console.warn(`  Live endpoint reports: ${postDeployVersion} (expected ${sourceVersion})`);
    console.warn("  OPcache may still be warming up — check again in 30 seconds.");
  } else {
    console.warn("  Could not verify live version — check manually:");
    console.warn(`  curl ${LIVE_VERSION_URL}`);
  }
} finally {
  if (existsSync(TMP_PLUGIN)) unlinkSync(TMP_PLUGIN);
  if (existsSync(TMP_KEY)) unlinkSync(TMP_KEY);
}
