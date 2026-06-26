import { execFileSync } from "child_process";
import { writeFileSync, unlinkSync } from "fs";

/**
 * clean-hcc-orphans.ts
 *
 * Removes the orphaned static Elementor widgets from the HCC page:
 *   - "— / 100" heading + "Based on today's inputs" text in the Healing Score column
 *     (now superseded by our dynamic shortcode)
 *   - "Start Today" button widget in the Daily Input column
 *     (now superseded by the Daily Check-In shortcode)
 */

function requireEnv(k: string) {
  const v = process.env[k];
  if (!v) { console.error(k + " not set"); process.exit(1); }
  return v;
}

const KEY  = requireEnv("SITEGROUND_DEPLOY_KEY");
const HOST = requireEnv("SITEGROUND_SSH_HOST");
const USER = requireEnv("SITEGROUND_SSH_USER");
const PORT = requireEnv("SITEGROUND_SSH_PORT");
const WP   = "/home/customer/www/excreet.com/public_html";
const TMP_KEY    = "/tmp/hcc_clean_key";
const TMP_SCRIPT = "/tmp/hcc_clean_script.php";
const REMOTE     = `${WP}/hcc_clean_tmp.php`;

const norm = KEY
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\s*/, "-----BEGIN OPENSSH PRIVATE KEY-----\n")
  .replace(/\s*-----END OPENSSH PRIVATE KEY-----/, "\n-----END OPENSSH PRIVATE KEY-----\n")
  .replace(/-----BEGIN OPENSSH PRIVATE KEY-----\n([\s\S]+?)\n-----END OPENSSH PRIVATE KEY-----/, (_, b) => {
    const c = b.replace(/\s+/g, ""); const l = c.match(/.{1,64}/g) ?? [];
    return `-----BEGIN OPENSSH PRIVATE KEY-----\n${l.join("\n")}\n-----END OPENSSH PRIVATE KEY-----`;
  });

writeFileSync(TMP_KEY, norm + "\n", { encoding: "utf8", mode: 0o600 });

function ssh(cmd: string) {
  return execFileSync("ssh", ["-i", TMP_KEY, "-o", "StrictHostKeyChecking=no", "-o", "BatchMode=yes", "-p", PORT, `${USER}@${HOST}`, cmd], { encoding: "utf8" });
}
function scp(local: string, remote: string) {
  execFileSync("scp", ["-i", TMP_KEY, "-o", "StrictHostKeyChecking=no", "-o", "BatchMode=yes", "-P", PORT, local, `${USER}@${HOST}:${remote}`], { stdio: "inherit" });
}

const php = `<?php
defined('ABSPATH') || require_once dirname(__FILE__) . '/wp-load.php';

$pid = 257; // healing-command-center page

$raw  = get_post_meta( $pid, '_elementor_data', true );
$data = json_decode( $raw, true );

// Widget text patterns to remove from Elementor
// These are static placeholders now replaced by shortcodes
$remove_heading_texts = [
    '— / 100',
    '—/100',
    'Based on today',   // "Based on today's inputs"
];
$remove_button_texts = [
    'Start Today',
    'Start today',
];
$remove_subheading_texts = [
    'Based on today',
];

$removed = 0;

function hcc_clean( array &$elements, array $h_patterns, array $btn_patterns, int &$removed ): void {
    $to_remove = [];
    foreach ( $elements as $i => $el ) {
        $wt = $el['widgetType'] ?? '';
        $removed_this = false;

        // Heading widgets with orphan text
        if ( $wt === 'heading' ) {
            $t = strip_tags( $el['settings']['title'] ?? '' );
            foreach ( $h_patterns as $pat ) {
                if ( strpos( $t, $pat ) !== false ) {
                    $to_remove[] = $i;
                    echo "  🗑 Removing heading: \\"" . trim($t) . "\\"\\n";
                    $removed_this = true;
                    $removed++;
                    break;
                }
            }
        }

        // Button widgets: "Start Today"
        if ( ! $removed_this && $wt === 'button' ) {
            $t = strip_tags( $el['settings']['text'] ?? $el['settings']['button_text'] ?? '' );
            foreach ( $btn_patterns as $pat ) {
                if ( stripos( $t, $pat ) !== false ) {
                    $to_remove[] = $i;
                    echo "  🗑 Removing button: \\"" . trim($t) . "\\"\\n";
                    $removed++;
                    break;
                }
            }
        }

        // Text/editor widgets below the score (subheadings like "Based on today's inputs")
        if ( ! $removed_this && in_array( $wt, ['text-editor', 'theme-post-excerpt', 'text'], true ) ) {
            $t = strip_tags( $el['settings']['editor'] ?? $el['settings']['text'] ?? '' );
            foreach ( $h_patterns as $pat ) {
                if ( strpos( $t, $pat ) !== false ) {
                    $to_remove[] = $i;
                    echo "  🗑 Removing text: \\"" . trim(substr($t,0,60)) . "\\"\\n";
                    $removed++;
                    break;
                }
            }
        }

        if ( ! empty( $el['elements'] ) ) {
            hcc_clean( $elements[$i]['elements'], $h_patterns, $btn_patterns, $removed );
        }
    }

    // Remove in reverse order to preserve indices
    foreach ( array_reverse( $to_remove ) as $idx ) {
        array_splice( $elements, $idx, 1 );
    }
}

hcc_clean( $data, $remove_heading_texts, $remove_button_texts, $removed );

if ( $removed > 0 ) {
    update_post_meta( $pid, '_elementor_data', wp_slash( json_encode( $data ) ) );
    if ( class_exists( '\\\\Elementor\\\\Plugin' ) ) {
        \\Elementor\\Plugin::\\$instance->files_manager->clear_cache();
        echo "Elementor cache cleared.\\n";
    }
    echo "\\n✅ Removed {\\$removed} orphaned widget(s) from page {\\$pid}.\\n";
} else {
    echo "\\nNothing to remove — printing all widget text for debug:\\n";
    function hcc_dump( array \\$els, int \\$d = 0 ): void {
        foreach ( \\$els as \\$el ) {
            \\$wt = \\$el['widgetType'] ?? \\$el['elType'] ?? '';
            \\$t  = strip_tags( \\$el['settings']['title'] ?? \\$el['settings']['text'] ?? \\$el['settings']['editor'] ?? \\$el['settings']['button_text'] ?? '' );
            if ( \\$t ) echo str_repeat('  ',\\$d)."[{\\$wt}] ".trim(substr(\\$t,0,60))."\\n";
            if ( ! empty( \\$el['elements'] ) ) hcc_dump( \\$el['elements'], \\$d+1 );
        }
    }
    hcc_dump( \\$data );
}
`;

console.log("=== HCC Orphan Cleaner ===\n");
try {
  writeFileSync(TMP_SCRIPT, php, "utf8");
  console.log("Uploading script…");
  scp(TMP_SCRIPT, REMOTE);
  console.log("Running…\n");
  const out = ssh(`cd ${WP} && wp eval-file ${REMOTE} 2>&1 && rm -f ${REMOTE}`);
  console.log(out);
} finally {
  try { unlinkSync(TMP_KEY); } catch {}
  try { unlinkSync(TMP_SCRIPT); } catch {}
}
