import { execFileSync } from "child_process";
import { writeFileSync, unlinkSync, existsSync } from "fs";

const DEPLOY_KEY = process.env.SITEGROUND_DEPLOY_KEY ?? "";
const SSH_HOST   = process.env.SITEGROUND_SSH_HOST   ?? "";
const SSH_USER   = process.env.SITEGROUND_SSH_USER   ?? "";
const SSH_PORT   = process.env.SITEGROUND_SSH_PORT   ?? "18765";
const TMP_KEY    = "/tmp/deploy-ssh-key-log";

const normalizedKey = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, body) => {
    const clean = body.replace(/\s+/g, "");
    const lines = clean.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });

writeFileSync(TMP_KEY, normalizedKey + "\n", { encoding: "utf8", mode: 0o600 });

const cmd = [
  "echo '=== WC FATAL LOG ===' ;",
  "cat /home/customer/www/excreet.com/public_html/wp-content/uploads/wc-logs/fatal-errors-2026-05-17-801e5bce869f7749d71d0767c4022e3b.log 2>/dev/null ;",
  "echo '=== WC LOGGER ===' ;",
  "tail -60 /home/customer/www/excreet.com/public_html/wp-content/uploads/wc-logs/wc_logger-2026-05-18-5128e1c4eb32156d92dc2d9162eaf17d.log 2>/dev/null ;",
  "echo '=== PHP ERRORLOG ===' ;",
  "tail -60 /home/customer/www/excreet.com/public_html/php_errorlog 2>/dev/null || tail -60 ~/php_errorlog 2>/dev/null || echo 'not found' ;",
  "echo '=== FIND php_errorlog ===' ;",
  "find /home/customer -name 'php_errorlog' 2>/dev/null | head -5 ;",
].join(" ");

try {
  const out = execFileSync("ssh", [
    "-i", TMP_KEY,
    "-o", "StrictHostKeyChecking=no",
    "-o", "BatchMode=yes",
    "-p", SSH_PORT,
    `${SSH_USER}@${SSH_HOST}`,
    cmd,
  ], { encoding: "utf8", maxBuffer: 2 * 1024 * 1024 });
  console.log(out);
} catch (e: any) {
  console.error(e.message ?? e);
} finally {
  if (existsSync(TMP_KEY)) unlinkSync(TMP_KEY);
}
