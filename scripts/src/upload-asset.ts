import { execFileSync } from "child_process";
import { existsSync, writeFileSync, unlinkSync } from "fs";
import { resolve, basename } from "path";

/**
 * upload-asset.ts
 *
 * Uploads any local file to SiteGround via SCP.
 *
 * Usage:
 *   pnpm --filter @workspace/scripts run upload:asset -- <localFilePath> <remoteRelativePath>
 *
 * Example:
 *   pnpm --filter @workspace/scripts run upload:asset -- \
 *     attached_assets/my-image.png \
 *     wp-content/uploads/2026/05/my-image.png
 */

const extraArgs = process.argv.slice(2).filter((a) => !a.startsWith("--"));
const LOCAL_PATH  = extraArgs[0];
const REMOTE_REL  = extraArgs[1];

if (!LOCAL_PATH || !REMOTE_REL) {
  console.error("Usage: upload:asset -- <localFilePath> <remoteRelativePath>");
  process.exit(1);
}

function requireEnv(key: string): string {
  const val = process.env[key];
  if (!val) { console.error(`ERROR: Required env var ${key} is not set.`); process.exit(1); }
  return val;
}

const DEPLOY_KEY = requireEnv("SITEGROUND_DEPLOY_KEY");
const SSH_HOST   = requireEnv("SITEGROUND_SSH_HOST");
const SSH_USER   = requireEnv("SITEGROUND_SSH_USER");
const SSH_PORT   = requireEnv("SITEGROUND_SSH_PORT");

const LOCAL_ABS = resolve(process.cwd(), LOCAL_PATH);
if (!existsSync(LOCAL_ABS)) {
  console.error(`ERROR: Local file not found: ${LOCAL_ABS}`);
  process.exit(1);
}

const REMOTE_ROOT = "/home/customer/www/excreet.com/public_html";
const REMOTE_DEST = `${REMOTE_ROOT}/${REMOTE_REL}`;
const TMP_KEY     = `/tmp/deploy-ssh-key-asset`;

const normalizedKey = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, body) => {
    const clean = body.replace(/\s+/g, "");
    const lines = clean.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });

writeFileSync(TMP_KEY, normalizedKey + "\n", { encoding: "utf8", mode: 0o600 });

console.log(`=== Excreet Asset Upload ===`);
console.log(`    Local:  ${LOCAL_ABS}`);
console.log(`    Remote: ${SSH_USER}@${SSH_HOST}:${REMOTE_DEST}`);
console.log("");

try {
  execFileSync("scp", [
    "-i", TMP_KEY,
    "-o", "StrictHostKeyChecking=no",
    "-o", "BatchMode=yes",
    "-P", SSH_PORT,
    LOCAL_ABS,
    `${SSH_USER}@${SSH_HOST}:${REMOTE_DEST}`,
  ], { stdio: "inherit" });
  console.log(`\n✅ Upload SUCCESSFUL — ${basename(LOCAL_ABS)} is live on SiteGround`);
} finally {
  if (existsSync(TMP_KEY)) unlinkSync(TMP_KEY);
}
