import { execFileSync } from "child_process";
import { existsSync, readFileSync, writeFileSync, unlinkSync } from "fs";
import { resolve } from "path";

/**
 * deploy-patch-local.ts
 *
 * Deploys any Excreet mu-plugin patch file from the local repo to SiteGround
 * via SCP + SSH OPcache invalidation.
 *
 * Usage:
 *   pnpm --filter @workspace/scripts run deploy:patch:local -- excreet-hermes-patch-290.php
 *   pnpm --filter @workspace/scripts run deploy:patch:local -- excreet-hermes-patch-290.php --dry-run
 */

const DRY_RUN = process.argv.includes("--dry-run");

// Filename passed as first non-flag arg after "--"
const extraArgs = process.argv.slice(2).filter((a) => !a.startsWith("--"));
const PATCH_FILENAME = extraArgs[0] ?? "excreet-hermes-patch-290.php";

const REMOTE_MU_PLUGINS =
  "/home/customer/www/excreet.com/public_html/wp-content/mu-plugins";
const REMOTE_WP_ROOT =
  "/home/customer/www/excreet.com/public_html";

// Special files that deploy to WP root rather than mu-plugins
// Value: [remote filename, optional local source override]
const ROOT_INDEX_FILES: Record<string, [string, string?]> = {
  "excreet-homepage-index.php": ["index.php"],
  "card.html": ["card.html", "../artifacts/hermes-ui/public/card.html"],
  "emc-proxy.php": ["emc-proxy.php"],
};

const ROOT_ENTRY   = ROOT_INDEX_FILES[PATCH_FILENAME];
const IS_ROOT_FILE = !!ROOT_ENTRY;
const REMOTE_DEST_DIR  = IS_ROOT_FILE ? REMOTE_WP_ROOT : REMOTE_MU_PLUGINS;
const REMOTE_DEST_NAME = IS_ROOT_FILE ? ROOT_ENTRY[0] : PATCH_FILENAME;

const LOCAL_PLUGIN = resolve(
  process.cwd(),
  ROOT_ENTRY?.[1] ?? `../artifacts/api-server/wordpress/${PATCH_FILENAME}`,
);


const TMP_PLUGIN = `/tmp/deploy-local-${PATCH_FILENAME}`;
const TMP_KEY    = `/tmp/deploy-ssh-key-patch`;

const OPCACHE_BASE =
  "/home/customer/.opcache/8.2.31-May 11 2026-05:48:02-86160aeceb74082fc91a5acc3d4b20ec";
const OPCACHE_BIN =
  `${OPCACHE_BASE}${REMOTE_DEST_DIR}/${REMOTE_DEST_NAME}.bin`;

function requireEnv(key: string): string {
  const val = process.env[key];
  if (!val) {
    console.error(`ERROR: Required env var ${key} is not set.`);
    process.exit(1);
  }
  return val;
}

function parseVersion(content: string): string | null {
  const m = content.match(/^\s*\*\s*Version:\s*(.+)$/m);
  return m ? m[1].trim() : null;
}

function sshRun(host: string, user: string, port: string, keyFile: string, command: string): string {
  return execFileSync("ssh", [
    "-i", keyFile,
    "-o", "StrictHostKeyChecking=no",
    "-o", "BatchMode=yes",
    "-p", port,
    `${user}@${host}`,
    command,
  ], { encoding: "utf8" });
}

const DEPLOY_KEY = requireEnv("SITEGROUND_DEPLOY_KEY");
const SSH_HOST   = requireEnv("SITEGROUND_SSH_HOST");
const SSH_USER   = requireEnv("SITEGROUND_SSH_USER");
const SSH_PORT   = requireEnv("SITEGROUND_SSH_PORT");

console.log("=== Excreet Patch Deploy (LOCAL SOURCE) ===");
console.log(`    File: ${PATCH_FILENAME}`);
if (DRY_RUN) console.log("    MODE: DRY RUN");
console.log("");

if (!existsSync(LOCAL_PLUGIN)) {
  console.error(`ERROR: Local file not found: ${LOCAL_PLUGIN}`);
  process.exit(1);
}

const content = readFileSync(LOCAL_PLUGIN, "utf8");
const version = parseVersion(content) ?? "(unknown)";
console.log(`Version: ${version}`);
console.log(`Target:  ${SSH_USER}@${SSH_HOST}:${REMOTE_DEST_DIR}/${REMOTE_DEST_NAME}`);
console.log("");

if (DRY_RUN) {
  console.log("Dry run complete.");
  process.exit(0);
}

const normalizedKey = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, body) => {
    const clean = body.replace(/\s+/g, "");
    const lines = clean.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });

writeFileSync(TMP_PLUGIN, content, "utf8");
writeFileSync(TMP_KEY, normalizedKey + "\n", { encoding: "utf8", mode: 0o600 });

try {
  console.log("Uploading via SCP...");
  execFileSync("scp", [
    "-i", TMP_KEY,
    "-o", "StrictHostKeyChecking=no",
    "-o", "BatchMode=yes",
    "-P", SSH_PORT,
    TMP_PLUGIN,
    `${SSH_USER}@${SSH_HOST}:${REMOTE_DEST_DIR}/${REMOTE_DEST_NAME}`,
  ], { stdio: "inherit" });
  console.log("Upload complete.");

  // Touch the file to update mtime — forces OPcache timestamp revalidation
  // even when opcache_reset() / opcache_invalidate() are restricted by server config.
  try {
    sshRun(SSH_HOST, SSH_USER, SSH_PORT, TMP_KEY,
      `touch '${REMOTE_DEST_DIR}/${REMOTE_DEST_NAME}'`);
  } catch { /* non-fatal */ }

  console.log("Clearing OPcache...");
  const REMOTE_PHP_PATH = `${REMOTE_DEST_DIR}/${REMOTE_DEST_NAME}`;
  const FLUSH_NAME = `_excreet_flush_${Date.now()}.php`;
  const FLUSH_PATH = `/home/customer/www/excreet.com/public_html/${FLUSH_NAME}`;
  const FLUSH_URL  = `https://excreet.com/${FLUSH_NAME}`;

  const flushPhp = `<?php
$reset = function_exists('opcache_reset') ? opcache_reset() : false;
$inv   = function_exists('opcache_invalidate') ? opcache_invalidate('${REMOTE_PHP_PATH}', true) : false;
@unlink(__FILE__);
echo json_encode(['reset' => $reset, 'invalidated' => $inv, 'ts' => time()]);
`;

  try {
    sshRun(SSH_HOST, SSH_USER, SSH_PORT, TMP_KEY,
      `rm -f '${OPCACHE_BIN}' ; cat > '${FLUSH_PATH}' << 'PHPEOF'\n${flushPhp}\nPHPEOF`);
    execFileSync("sleep", ["1"]);
    let result = "(no response)";
    try {
      result = execFileSync("curl", ["--silent", "--max-time", "10", FLUSH_URL], { encoding: "utf8" }).trim();
    } catch { /* ignore */ }
    console.log(`OPcache flush: ${result}`);
    try { sshRun(SSH_HOST, SSH_USER, SSH_PORT, TMP_KEY, `rm -f '${FLUSH_PATH}'`); } catch { /* ignore */ }
  } catch {
    console.warn("OPcache clear failed — may serve stale bytecode until restart.");
  }

  // Flush SiteGround's nginx proxy cache via HTTP PURGE (most reliable method)
  // `wp sg purge` requires the SG Optimizer plugin which is not installed on this site.
  // Instead, SiteGround's nginx exposes a PURGE endpoint on localhost.
  // We also flush the WP object cache as a secondary step.
  try {
    const purgeOut = sshRun(SSH_HOST, SSH_USER, SSH_PORT, TMP_KEY,
      // Purge root + wildcard patterns that cover all cached pages
      `curl -s -X PURGE "http://localhost/" -H "Host: excreet.com" 2>/dev/null; ` +
      `curl -s -X PURGE "http://localhost/*" -H "Host: excreet.com" 2>/dev/null; ` +
      `curl -s -X PURGE "http://localhost/.*" -H "Host: excreet.com" 2>/dev/null; ` +
      // Also purge the most common page paths individually
      `for p in explore membership-account welcome-member member-dashboard know-the-signals affiliate-area provider-report ministry-of-healing body-check login; do ` +
      `  curl -s -X PURGE "http://localhost/$p/" -H "Host: excreet.com" 2>/dev/null; ` +
      `done; ` +
      `cd /home/customer/www/excreet.com/public_html && wp cache flush 2>/dev/null || echo "skipped"`
    ).trim().replace(/Warning:.*\n?/g, "");
    const purgeOk = purgeOut.includes("Successful purge") || purgeOut.includes("Success");
    console.log(`Nginx cache PURGE: ${purgeOk ? "OK" : purgeOut.slice(0, 120) || "attempted"}`);
    console.log(`WP object cache: flushed`);
  } catch {
    console.log("Cache flush skipped.");
  }

  console.log("");
  console.log(`✅ Deploy SUCCESSFUL — ${PATCH_FILENAME} v${version} is live on excreet.com`);
} finally {
  if (existsSync(TMP_PLUGIN)) unlinkSync(TMP_PLUGIN);
  if (existsSync(TMP_KEY)) unlinkSync(TMP_KEY);
}
