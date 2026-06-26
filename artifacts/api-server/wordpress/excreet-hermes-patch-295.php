<?php
/**
 * Excreet Hermes Patch 295 — DEPRECATED (no-op stub)
 * Version: 1.2.0
 *
 * Originally: daily tracking sliders (energy/mood/symptoms/food/notes),
 * healing score widget, file upload panel, file history, healer notes.
 *
 * Deprecated: May 2026 — superseded by the Body Check (patch-298 HCC),
 * which handles all daily intake, scoring, trend tracking, and results
 * in a single, AI-powered flow connected to Hermes. The slider-based
 * approach was disconnected and never wired into the Body Snapshot pipeline.
 *
 * All shortcodes now return an empty string. All AJAX handlers are no-ops.
 * Safe to leave in mu-plugins — produces zero output, zero side effects.
 */
defined( 'ABSPATH' ) || exit;

add_shortcode( 'excreet_daily_input',   '__return_empty_string' );
add_shortcode( 'excreet_healing_score', '__return_empty_string' );
add_shortcode( 'excreet_daily_uploads', '__return_empty_string' );
add_shortcode( 'excreet_file_history',  '__return_empty_string' );
add_shortcode( 'excreet_healer_notes',  '__return_empty_string' );

function excreet_295_save_daily(): void  { wp_send_json_error( [ 'message' => 'Deprecated.' ], 410 ); }
function excreet_295_upload_file(): void { wp_send_json_error( [ 'message' => 'Deprecated.' ], 410 ); }
function excreet_295_delete_file(): void { wp_send_json_error( [ 'message' => 'Deprecated.' ], 410 ); }

add_action( 'wp_ajax_excreet_295_save_daily',  'excreet_295_save_daily' );
add_action( 'wp_ajax_excreet_295_upload_file', 'excreet_295_upload_file' );
add_action( 'wp_ajax_excreet_295_delete_file', 'excreet_295_delete_file' );
