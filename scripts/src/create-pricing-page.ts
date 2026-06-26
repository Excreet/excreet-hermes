import { execFileSync, execSync } from "child_process";
import { writeFileSync, unlinkSync, existsSync } from "fs";

function requireEnv(key: string): string {
  const val = process.env[key];
  if (!val) { console.error(`ERROR: ${key} not set`); process.exit(1); }
  return val;
}

const DEPLOY_KEY = requireEnv("SITEGROUND_DEPLOY_KEY");
const SSH_HOST   = requireEnv("SITEGROUND_SSH_HOST");
const SSH_USER   = requireEnv("SITEGROUND_SSH_USER");
const SSH_PORT   = requireEnv("SITEGROUND_SSH_PORT");
const WP_ROOT    = "/home/customer/www/excreet.com/public_html";
const TMP_KEY    = "/tmp/deploy-ssh-key-create-page";

const normalizedKey = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, body) => {
    const clean = body.replace(/\s+/g, "");
    const lines = clean.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });

writeFileSync(TMP_KEY, normalizedKey + "\n", { encoding: "utf8", mode: 0o600 });

const sshArgs = [
  "-i", TMP_KEY,
  "-o", "StrictHostKeyChecking=no",
  "-o", "BatchMode=yes",
  "-p", SSH_PORT,
  `${SSH_USER}@${SSH_HOST}`,
];

try {
  // 1. Create the page via WP-CLI
  console.log("Creating /membership-options/ page via WP-CLI...");
  const wpResult = execFileSync("ssh", [
    ...sshArgs,
    `cd ${WP_ROOT} && wp post list --post_type=page --name=membership-options --field=ID 2>/dev/null`,
  ], { encoding: "utf8" }).trim();

  if (wpResult && parseInt(wpResult) > 0) {
    console.log(`Page already exists with ID: ${wpResult}`);
    // Make sure shortcode is in it
    execFileSync("ssh", [
      ...sshArgs,
      `cd ${WP_ROOT} && wp post update ${wpResult} --post_content='[excreet_pricing]' --post_status=publish 2>/dev/null && wp option update _excreet_354_pricing_page_id ${wpResult}`,
    ], { stdio: "inherit" });
  } else {
    console.log("Page not found — creating...");
    const newId = execFileSync("ssh", [
      ...sshArgs,
      `cd ${WP_ROOT} && wp post create --post_type=page --post_title='Membership Options' --post_name=membership-options --post_content='[excreet_pricing]' --post_status=publish --porcelain 2>/dev/null`,
    ], { encoding: "utf8" }).trim();
    console.log(`Created page ID: ${newId}`);
    execFileSync("ssh", [
      ...sshArgs,
      `cd ${WP_ROOT} && wp option update _excreet_354_pricing_page_id ${newId} && wp option update pmpro_levels_page_id ${newId}`,
    ], { stdio: "inherit" });
  }

  // 2. Flush caches
  console.log("Flushing caches...");
  execFileSync("ssh", [
    ...sshArgs,
    `curl -s -X PURGE http://localhost/membership-options/ -H "Host: excreet.com" && curl -s -X PURGE http://localhost/ -H "Host: excreet.com"`,
  ], { stdio: "inherit" });

  console.log("\n✅ /membership-options/ page is live.");
} finally {
  if (existsSync(TMP_KEY)) unlinkSync(TMP_KEY);
}
