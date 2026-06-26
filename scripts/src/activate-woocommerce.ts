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
const TMP_KEY    = "/tmp/sg-woo-key";

const normalizedKey = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, body) => {
    const clean = body.replace(/\s+/g, "");
    const lines = clean.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });

writeFileSync(TMP_KEY, normalizedKey + "\n", { encoding: "utf8", mode: 0o600 });

function ssh(command: string): string {
  return execFileSync("ssh", [
    "-i", TMP_KEY,
    "-o", "StrictHostKeyChecking=no",
    "-o", "BatchMode=yes",
    "-p", SSH_PORT,
    `${SSH_USER}@${SSH_HOST}`,
    command,
  ], { encoding: "utf8", timeout: 60000 });
}

const WP = "cd /home/customer/www/excreet.com/public_html &&";

console.log("Activating WooCommerce...");
const activate = ssh(`${WP} wp plugin activate woocommerce`);
console.log(activate);

console.log("Running WooCommerce setup...");
try {
  const setup = ssh(`${WP} wp wc tool run install_pages --user=1`);
  console.log(setup);
} catch (e: any) {
  console.log("Setup pages note:", e.stdout ?? e.message);
}

console.log("Checking WooCommerce status...");
const status = ssh(`${WP} wp plugin status woocommerce`);
console.log(status);

console.log("Listing WooCommerce pages...");
const pages = ssh(`${WP} wp post list --post_type=page --fields=ID,post_title,post_status,post_name --format=table`);
console.log(pages);
