import { execFileSync } from "child_process";
import { writeFileSync, unlinkSync } from "fs";

/**
 * wp-cli.ts
 *
 * Run any WP-CLI command on the SiteGround server via SSH.
 * Handles key normalization automatically.
 *
 * Usage:
 *   pnpm --filter @workspace/scripts run wp -- option get admin_email
 *   pnpm --filter @workspace/scripts run wp -- user list --fields=ID,user_email
 *   pnpm --filter @workspace/scripts run wp -- eval 'echo get_user_meta(1, "_excreet_member_intake", true);'
 */

const WP_ROOT = "/home/customer/www/excreet.com/public_html";
const TMP_KEY = "/tmp/excreet-wp-ssh-key";

function requireEnv(key: string): string {
  const val = process.env[key];
  if (!val) {
    console.error(`ERROR: Required env var ${key} is not set.`);
    process.exit(1);
  }
  return val;
}

function normalizeKey(raw: string): string {
  return raw
    .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
    .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
    .replace(
      /-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/,
      (_, body) => {
        const clean = body.replace(/\s+/g, "");
        const lines = clean.match(/.{1,64}/g) ?? [];
        return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
      },
    );
}

const DEPLOY_KEY = requireEnv("SITEGROUND_DEPLOY_KEY");
const SSH_HOST   = requireEnv("SITEGROUND_SSH_HOST");
const SSH_USER   = requireEnv("SITEGROUND_SSH_USER");
const SSH_PORT   = requireEnv("SITEGROUND_SSH_PORT");

// Everything after "--" in the pnpm invocation becomes the WP-CLI subcommand
// pnpm passes the "--" separator itself as argv[2], so strip it if present
const rawArgs = process.argv.slice(2);
const wpArgs  = rawArgs[0] === "--" ? rawArgs.slice(1) : rawArgs;

if (wpArgs.length === 0) {
  console.error("Usage: pnpm --filter @workspace/scripts run wp -- <wp-cli command>");
  console.error("Example: pnpm --filter @workspace/scripts run wp -- option get admin_email");
  process.exit(1);
}

// Compose the remote command: cd into WP root, suppress WP-CLI color codes in pipes
const remote = `cd ${WP_ROOT} && wp --no-color ${wpArgs.map(a => `'${a.replace(/'/g, "'\\''")}'`).join(" ")}`;

writeFileSync(TMP_KEY, normalizeKey(DEPLOY_KEY) + "\n", { encoding: "utf8", mode: 0o600 });

try {
  const output = execFileSync(
    "ssh",
    [
      "-i", TMP_KEY,
      "-o", "StrictHostKeyChecking=no",
      "-o", "BatchMode=yes",
      "-p", SSH_PORT,
      `${SSH_USER}@${SSH_HOST}`,
      remote,
    ],
    { encoding: "utf8", stdio: ["inherit", "pipe", "pipe"] },
  );

  // Filter out PHP constant-redefinition warnings from mu-plugins (cosmetic noise)
  const clean = output
    .split("\n")
    .filter(line => !line.startsWith("Warning: Constant "))
    .join("\n")
    .trimEnd();

  console.log(clean);
} catch (err: unknown) {
  const e = err as { stdout?: string; stderr?: string; status?: number };
  const stdout = (e.stdout ?? "")
    .split("\n")
    .filter(l => !l.startsWith("Warning: Constant "))
    .join("\n")
    .trimEnd();
  const stderr = (e.stderr ?? "")
    .split("\n")
    .filter(l => !l.startsWith("Warning: Constant "))
    .join("\n")
    .trimEnd();
  if (stdout) console.log(stdout);
  if (stderr) console.error(stderr);
  process.exit(e.status ?? 1);
} finally {
  try { unlinkSync(TMP_KEY); } catch (_) {}
}
