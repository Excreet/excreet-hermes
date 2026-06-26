import { execFileSync, execSync } from "child_process";
import { writeFileSync, unlinkSync, existsSync } from "fs";

/**
 * inject-hcc.ts
 *
 * Injects Excreet shortcode widgets into the Healing Command Center
 * Elementor page on excreet.com via SSH + WP-CLI.
 *
 * Usage:
 *   pnpm --filter @workspace/scripts run inject:hcc
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
const WP_ROOT    = "/home/customer/www/excreet.com/public_html";
const TMP_KEY    = "/tmp/hcc-inject-key";
const TMP_SCRIPT = "/tmp/hcc-inject-script.php";
const REMOTE_SCRIPT = `${WP_ROOT}/excreet-hcc-inject-tmp.php`;

// ── Write normalised key ─────────────────────────────────────────────────────
const normalizedKey = DEPLOY_KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, body) => {
    const clean = body.replace(/\s+/g, "");
    const lines = clean.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${lines.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });

writeFileSync(TMP_KEY, normalizedKey + "\n", { encoding: "utf8", mode: 0o600 });

function sshRun(cmd: string): string {
  return execFileSync("ssh", [
    "-i", TMP_KEY,
    "-o", "StrictHostKeyChecking=no",
    "-o", "BatchMode=yes",
    "-p", SSH_PORT,
    `${SSH_USER}@${SSH_HOST}`,
    cmd,
  ], { encoding: "utf8" });
}

function scpTo(local: string, remote: string): void {
  execFileSync("scp", [
    "-i", TMP_KEY,
    "-o", "StrictHostKeyChecking=no",
    "-o", "BatchMode=yes",
    "-P", SSH_PORT,
    local,
    `${SSH_USER}@${SSH_HOST}:${remote}`,
  ], { stdio: "inherit" });
}

// ── PHP injection script ─────────────────────────────────────────────────────
const phpScript = String.raw`<?php
/**
 * HCC Elementor shortcode injector — run via WP-CLI eval-file, then deleted.
 */
defined('ABSPATH') || require_once dirname(__FILE__) . '/wp-load.php';

$shortcode_map = [
    'healing score'        => '[excreet_healing_score]',
    'daily input'          => '[excreet_daily_input]',
    'daily uploads'        => '[excreet_daily_uploads]',
    'lab & file history'   => '[excreet_file_history]',
    'lab and file history' => '[excreet_file_history]',
    'healer notes'         => '[excreet_healer_notes]',
];

// Find page
$pages = get_pages(['post_status' => 'publish']);
$pid   = null;
foreach ( $pages as $p ) {
    if ( $p->post_name === 'healing-command-center' ) { $pid = $p->ID; break; }
}
if ( ! $pid ) {
    echo "ERROR: healing-command-center page not found.\n";
    echo "Available slugs: " . implode(', ', array_column($pages, 'post_name')) . "\n";
    exit(1);
}
echo "Found page ID: {$pid}\n";

$raw  = get_post_meta( $pid, '_elementor_data', true );
$data = json_decode( $raw, true );
if ( ! $data ) {
    echo "ERROR: Could not parse Elementor data (length=" . strlen($raw) . ").\n";
    exit(1);
}

$changes = 0;

function hcc_make_widget( string $shortcode ): array {
    return [
        'id'         => substr( md5( $shortcode . microtime() ), 0, 7 ),
        'elType'     => 'widget',
        'widgetType' => 'shortcode',
        'settings'   => [ 'shortcode' => $shortcode ],
        'elements'   => [],
    ];
}

function hcc_walk( array &$elements, array $map, int &$changes ): void {
    foreach ( $elements as &$el ) {
        $type = $el['elType'] ?? '';

        // A column: look for a heading inside it and inject after it
        if ( $type === 'column' || $type === 'container' ) {
            $first_heading_text = '';
            $last_heading_idx   = -1;

            foreach ( $el['elements'] as $i => $child ) {
                $wt = $child['widgetType'] ?? '';
                if ( $wt === 'heading' || $wt === 'theme-post-title' ) {
                    $t = strtolower( trim( strip_tags( $child['settings']['title'] ?? $child['settings']['text'] ?? '' ) ) );
                    if ( $first_heading_text === '' ) { $first_heading_text = $t; }
                    $last_heading_idx = $i;
                }
            }
            $heading_text = $first_heading_text;
            $heading_idx  = $last_heading_idx;

            if ( $heading_text ) {
                foreach ( $map as $needle => $shortcode ) {
                    if ( strpos( $heading_text, $needle ) !== false ) {
                        // Check not already injected
                        $already = false;
                        foreach ( $el['elements'] as $child ) {
                            if ( ( $child['widgetType'] ?? '' ) === 'shortcode'
                                && strpos( $child['settings']['shortcode'] ?? '', $shortcode ) !== false ) {
                                $already = true;
                                break;
                            }
                        }
                        if ( ! $already ) {
                            // Insert shortcode widget after the heading
                            array_splice( $el['elements'], $heading_idx + 1, 0, [ hcc_make_widget( $shortcode ) ] );
                            echo "  ✓ Injected {$shortcode} into column with heading \"{$heading_text}\"\n";
                            $changes++;
                        } else {
                            echo "  – {$shortcode} already present in \"{$heading_text}\" column, skipped.\n";
                        }
                        break;
                    }
                }
            }
        }

        if ( ! empty( $el['elements'] ) ) {
            hcc_walk( $el['elements'], $map, $changes );
        }
    }
}

hcc_walk( $data, $shortcode_map, $changes );

if ( $changes > 0 ) {
    update_post_meta( $pid, '_elementor_data', wp_slash( json_encode( $data ) ) );

    // Clear Elementor CSS/file cache
    if ( class_exists( '\Elementor\Core\Files\Manager' ) ) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
        echo "Elementor cache cleared.\n";
    }
    // Clear any page caching
    if ( function_exists( 'wp_cache_flush' ) ) { wp_cache_flush(); }

    echo "\nDone — {$changes} shortcode(s) injected into page {$pid}.\n";
} else {
    echo "\nNo changes made. Check that heading text matches the map keys.\n";

    // Debug: print all heading texts found on the page
    echo "\nDEBUG — all heading text found on this page:\n";
    function hcc_dump_headings( array $elements, int $depth = 0 ): void {
        foreach ( $elements as $el ) {
            $wt = $el['widgetType'] ?? '';
            if ( in_array( $wt, ['heading', 'theme-post-title'], true ) ) {
                $t = strip_tags( $el['settings']['title'] ?? $el['settings']['text'] ?? '' );
                echo str_repeat('  ', $depth) . "[{$wt}] " . trim($t) . "\n";
            }
            if ( ! empty( $el['elements'] ) ) {
                hcc_dump_headings( $el['elements'], $depth + 1 );
            }
        }
    }
    hcc_dump_headings( $data );
}
`;

// ── Main ─────────────────────────────────────────────────────────────────────
console.log("=== Excreet HCC Shortcode Injector ===\n");

try {
  // 1. Write PHP script locally and SCP to server
  writeFileSync(TMP_SCRIPT, phpScript, "utf8");
  console.log("Uploading injection script…");
  scpTo(TMP_SCRIPT, REMOTE_SCRIPT);
  console.log("Uploaded.\n");

  // 2. Run via WP-CLI eval-file
  console.log("Running WP-CLI injection…\n");
  const output = sshRun(
    `cd ${WP_ROOT} && wp eval-file ${REMOTE_SCRIPT} 2>&1`
  );
  console.log(output);

  // 3. Clean up remote file
  sshRun(`rm -f ${REMOTE_SCRIPT}`);
  console.log("Remote script cleaned up.\n");

  // 4. Check if no changes — print debug guidance
  if (output.includes("No changes made")) {
    console.log("⚠️  Headings didn't match. See DEBUG output above.");
    console.log("Update the shortcode_map keys in inject-hcc.ts to match exactly.");
    process.exit(1);
  } else {
    console.log("✅ HCC page updated successfully.");
  }

} finally {
  if (existsSync(TMP_KEY))    unlinkSync(TMP_KEY);
  if (existsSync(TMP_SCRIPT)) unlinkSync(TMP_SCRIPT);
}
