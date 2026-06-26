<?php
/**
 * emc-proxy.php — Excreet Eye Map Check proxy
 *
 * Standalone file at WP root. Loads WordPress then forwards the
 * base64 image to Hermes /api/hermes/emc/analyze with the server-side
 * API key. Uses ob_start() to swallow any WP output/warnings that
 * would corrupt the JSON response.
 */

// Buffer everything — WordPress can print notices; we want pure JSON
ob_start();
require_once __DIR__ . '/wp-load.php';
ob_end_clean();

// Pure JSON from here on
header( 'Content-Type: application/json; charset=utf-8' );

if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
    http_response_code( 200 );
    exit;
}

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    echo wp_json_encode( [ 'error' => 'Method not allowed.' ] );
    exit;
}

/* ── Extract image ───────────────────────────────────────── */
$raw   = (string) file_get_contents( 'php://input' );
$body  = json_decode( $raw, true );
$image = is_array( $body ) && isset( $body['image'] ) && is_string( $body['image'] )
    ? $body['image']
    : '';

if ( $image === '' ) {
    http_response_code( 400 );
    echo wp_json_encode( [ 'error' => 'Invalid request. Expected { image: "<base64>" }.' ] );
    exit;
}

/* ── Hermes config ───────────────────────────────────────── */
$hermes_base = defined( 'EXCREET_HERMES_BASE_URL' )
    ? rtrim( EXCREET_HERMES_BASE_URL, '/' )
    : 'https://core-status-check.replit.app';

$api_key  = defined( 'EXCREET_HERMES_API_KEY' ) ? (string) EXCREET_HERMES_API_KEY : '';
$endpoint = $hermes_base . '/api/hermes/emc/analyze';

/* ── Forward to Hermes ───────────────────────────────────── */
$response = wp_remote_post( $endpoint, [
    'timeout'     => 60,
    'redirection' => 0,
    'headers'     => [
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
    ],
    'body' => wp_json_encode( [ 'image' => $image ] ),
] );

if ( is_wp_error( $response ) ) {
    http_response_code( 502 );
    echo wp_json_encode( [ 'error' => 'Connection error reaching analysis server.' ] );
    exit;
}

$status = (int) wp_remote_retrieve_response_code( $response );
$json   = json_decode( wp_remote_retrieve_body( $response ), true );

if ( ! is_array( $json ) ) {
    http_response_code( 502 );
    echo wp_json_encode( [ 'error' => 'Analysis failed. Please try again.' ] );
    exit;
}

http_response_code( $status );
echo wp_json_encode( $json );
