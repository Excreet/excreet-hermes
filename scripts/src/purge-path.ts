import { execFileSync } from "child_process";
import { writeFileSync, unlinkSync } from "fs";

/**
 * purge-path.ts
 * PURGEs one or more nginx-cached paths on SiteGround via SSH.
 * Also removes any stale files passed with --rm=<remote-path>.
 *
 * Usage:
 *   pnpm --filter @workspace/scripts run purge -- /explore/ /explore
 *   pnpm --filter @workspace/scripts run purge -- /explore/ --rm=/path/to/file
 */

function requireEnv(key: string): string {
  const val = process.env[key];
  if (!val) { console.error(`ERROR: ${key} not set`); process.exit(1); }
  return val;
}

const DEPLOY_KEY = requireEnv("SITEGROUND_DEPLOY_KEY");
const SSH_HOST   = requireEnv("SITEGROUND_SSH_HOST");
const SSH_USER   = requireEnv("SITEGROUND_SSH_USER");
const SSH_PORT   = requireEnv("SITEGROUND_SSH_PORT");

const args     = process.argv.slice(2).filter(a => !a.startsWith("--"));
const rmFlags  = process.argv.slice(2).filter(a => a.startsWith("--rm=")).map(a => a.replace("--rm=",""));
const paths    = args.length ? args : ["/"];

const normalized = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, b) => {
    const lines = b.replace(/\s+/g, "").match(/.{1,64}/g)!.join("\n");
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines}\n-----END OPENSSH PRIVATE KEY-----`;
  });

const TMP_KEY = `/tmp/sg_key_purge_${Date.now()}`;
writeFileSync(TMP_KEY, normalized + "\n", { encoding: "utf8", mode: 0o600 });

try {
  const cmds: string[] = [];
  for (const p of paths) {
    cmds.push(`curl -s -X PURGE 'http://localhost${p}' -H 'Host: excreet.com'`);
  }
  for (const f of rmFlags) {
    cmds.push(`rm -f '${f}'`);
  }
  cmds.push("echo PURGE_DONE");

  const result = execFileSync("ssh", [
    "-i", TMP_KEY,
    "-o", "StrictHostKeyChecking=no",
    "-o", "BatchMode=yes",
    "-p", SSH_PORT,
    `${SSH_USER}@${SSH_HOST}`,
    cmds.join(" && "),
  ], { encoding: "utf8" });

  console.log("Purged:", paths.join(", "));
  if (rmFlags.length) console.log("Removed:", rmFlags.join(", "));
  const ok = result.includes("Successful purge") || result.includes("PURGE_DONE");
  console.log(ok ? "✅ Done" : result.trim());
} finally {
  try { unlinkSync(TMP_KEY); } catch { /* ignore */ }
}
