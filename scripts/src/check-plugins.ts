import { execFileSync } from "child_process";
import { writeFileSync } from "fs";

function requireEnv(key: string): string {
  const val = process.env[key];
  if (!val) { console.error(`ERROR: ${key} not set`); process.exit(1); }
  return val;
}

const DEPLOY_KEY = requireEnv("SITEGROUND_DEPLOY_KEY");
const SSH_HOST   = requireEnv("SITEGROUND_SSH_HOST");
const SSH_USER   = requireEnv("SITEGROUND_SSH_USER");
const SSH_PORT   = requireEnv("SITEGROUND_SSH_PORT");
const TMP_KEY    = "/tmp/sg-check-key";

const normalizedKey = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, body) => {
    const clean = body.replace(/\s+/g, "");
    const lines = clean.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });

writeFileSync(TMP_KEY, normalizedKey + "\n", { encoding: "utf8", mode: 0o600 });

const result = execFileSync("ssh", [
  "-i", TMP_KEY,
  "-o", "StrictHostKeyChecking=no",
  "-o", "BatchMode=yes",
  "-p", SSH_PORT,
  `${SSH_USER}@${SSH_HOST}`,
  "cd /home/customer/www/excreet.com/public_html && wp plugin list --fields=name,status,version --format=table",
], { encoding: "utf8", timeout: 30000 });

console.log(result);
