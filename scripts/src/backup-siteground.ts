#!/usr/bin/env tsx
/**
 * backup-siteground.ts
 *
 * Pulls a full disaster-recovery backup of excreet.com from SiteGround:
 *   1. MySQL database dump via WP-CLI (gzipped)
 *   2. Compressed archive of wp-content/uploads, themes, languages + wp-config.php
 *
 * Output: backups/excreet-YYYYMMDD/
 *
 * Usage:
 *   pnpm --filter @workspace/scripts run backup:siteground
 */

import { execFileSync, execSync } from "child_process";
import { existsSync, mkdirSync, writeFileSync, unlinkSync } from "fs";
import { resolve } from "path";

function requireEnv(key: string): string {
  const val = process.env[key];
  if (!val) { console.error(`ERROR: ${key} is not set`); process.exit(1); }
  return val;
}

const DEPLOY_KEY  = requireEnv("SITEGROUND_DEPLOY_KEY");
const SSH_HOST    = requireEnv("SITEGROUND_SSH_HOST");
const SSH_USER    = requireEnv("SITEGROUND_SSH_USER");
const SSH_PORT    = requireEnv("SITEGROUND_SSH_PORT");
const WP_ROOT     = "/home/customer/www/excreet.com/public_html";
const TMP_KEY     = "/tmp/sg_backup_key";

const date        = new Date().toISOString().slice(0, 10).replace(/-/g, "");
const BACKUP_DIR  = resolve(process.cwd(), `../backups/excreet-${date}`);

// Normalize SSH key
const normalized  = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, body) => {
    const clean = body.replace(/\s+/g, "");
    const lines  = clean.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });
writeFileSync(TMP_KEY, normalized + "\n", { encoding: "utf8", mode: 0o600 });

const sshArgs = ["-i", TMP_KEY, "-o", "StrictHostKeyChecking=no", "-o", "BatchMode=yes", "-p", SSH_PORT, `${SSH_USER}@${SSH_HOST}`];

function ssh(cmd: string): string {
  return execFileSync("ssh", [...sshArgs, cmd], { encoding: "utf8" });
}
function scp(remote: string, local: string): void {
  execFileSync("scp", ["-i", TMP_KEY, "-o", "StrictHostKeyChecking=no", "-P", SSH_PORT, `${SSH_USER}@${SSH_HOST}:${remote}`, local], { stdio: "inherit" });
}

mkdirSync(BACKUP_DIR, { recursive: true });
console.log(`\n=== Excreet SiteGround Backup ===`);
console.log(`Output: ${BACKUP_DIR}\n`);

// 1. Database dump
console.log("1/2  Dumping database via WP-CLI...");
ssh(`wp db export /tmp/excreet_db.sql --path=${WP_ROOT} --quiet && gzip -f /tmp/excreet_db.sql`);
scp("/tmp/excreet_db.sql.gz", `${BACKUP_DIR}/excreet_db_backup.sql.gz`);
ssh("rm -f /tmp/excreet_db.sql.gz");
console.log("     Database dump complete.\n");

// 2. Files archive
console.log("2/2  Archiving uploads, themes, languages, wp-config...");
ssh(`tar czf /tmp/excreet_files.tar.gz -C ${WP_ROOT} wp-config.php wp-content/uploads wp-content/themes wp-content/languages`);
scp("/tmp/excreet_files.tar.gz", `${BACKUP_DIR}/excreet_files_backup.tar.gz`);
ssh("rm -f /tmp/excreet_files.tar.gz");
console.log("     File archive complete.\n");

// Cleanup
try { unlinkSync(TMP_KEY); } catch (_) {}

console.log("✅  Backup COMPLETE");
console.log(`    DB:    ${BACKUP_DIR}/excreet_db_backup.sql.gz`);
console.log(`    Files: ${BACKUP_DIR}/excreet_files_backup.tar.gz\n`);
