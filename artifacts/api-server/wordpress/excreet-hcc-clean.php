<?php
/**
 * HCC Orphan Cleaner — removes static Elementor widgets superseded by shortcodes.
 * Run via: wp eval-file excreet-hcc-clean.php
 * Delete after use.
 */
defined( 'ABSPATH' ) || require_once dirname( __FILE__ ) . '/wp-load.php';

$pid = 257; // healing-command-center

$raw  = get_post_meta( $pid, '_elementor_data', true );
$data = json_decode( $raw, true );
if ( ! $data ) { echo "ERROR: Could not parse Elementor data.\n"; exit(1); }

// Heading text patterns to remove (these are static placeholders now replaced by shortcodes)
$remove_headings = [ '— / 100', '—/100', '– / 100' ];

// Sub-text patterns to remove
$remove_text = [ "Based on today" ];

// Button text patterns to remove (Start Today is now inside the Daily Check-In card)
$remove_buttons = [ 'Start Today', 'Start today' ];

$removed = 0;

function hcc_clean_elements( array &$elements, array $headings, array $texts, array $buttons, int &$removed ): void {
    $to_remove = [];

    foreach ( $elements as $i => $el ) {
        $wt       = $el['widgetType'] ?? '';
        $settings = $el['settings']   ?? [];
        $mark     = false;

        if ( $wt === 'heading' ) {
            $t = strip_tags( $settings['title'] ?? '' );
            foreach ( $headings as $pat ) {
                if ( strpos( $t, $pat ) !== false ) {
                    echo "  🗑  Removing heading: \"" . trim($t) . "\"\n";
                    $mark = true; $removed++; break;
                }
            }
        }

        if ( ! $mark && $wt === 'button' ) {
            $t = strip_tags( $settings['text'] ?? $settings['button_text'] ?? '' );
            foreach ( $buttons as $pat ) {
                if ( stripos( $t, $pat ) !== false ) {
                    echo "  🗑  Removing button: \"" . trim($t) . "\"\n";
                    $mark = true; $removed++; break;
                }
            }
        }

        if ( ! $mark && in_array( $wt, [ 'text-editor', 'text' ], true ) ) {
            $t = strip_tags( $settings['editor'] ?? $settings['text'] ?? '' );
            foreach ( $texts as $pat ) {
                if ( strpos( $t, $pat ) !== false ) {
                    echo "  🗑  Removing text widget: \"" . trim( substr($t, 0, 60) ) . "\"\n";
                    $mark = true; $removed++; break;
                }
            }
        }

        if ( $mark ) {
            $to_remove[] = $i;
        } elseif ( ! empty( $el['elements'] ) ) {
            hcc_clean_elements( $elements[$i]['elements'], $headings, $texts, $buttons, $removed );
        }
    }

    foreach ( array_reverse( $to_remove ) as $idx ) {
        array_splice( $elements, $idx, 1 );
    }
}

hcc_clean_elements( $data, $remove_headings, $remove_text, $remove_buttons, $removed );

if ( $removed > 0 ) {
    update_post_meta( $pid, '_elementor_data', wp_slash( json_encode( $data ) ) );
    if ( class_exists( '\Elementor\Plugin' ) ) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
        echo "  Elementor cache cleared.\n";
    }
    echo "\nDone — removed {$removed} orphaned widget(s) from page {$pid}.\n";
} else {
    echo "Nothing matched. Dumping all widget text for debug:\n";
    function hcc_dump( array $els, int $d = 0 ): void {
        foreach ( $els as $el ) {
            $wt = $el['widgetType'] ?? $el['elType'] ?? '';
            $t  = strip_tags(
                $el['settings']['title']  ?? $el['settings']['text']   ??
                $el['settings']['editor'] ?? $el['settings']['button_text'] ?? ''
            );
            if ( $t ) echo str_repeat('  ', $d) . "[{$wt}] " . trim( substr($t,0,80) ) . "\n";
            if ( ! empty( $el['elements'] ) ) hcc_dump( $el['elements'], $d+1 );
        }
    }
    hcc_dump( $data );
}
