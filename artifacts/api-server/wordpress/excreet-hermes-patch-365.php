<?php
/**
 * Plugin Name: Excreet Hermes Card Proxy (patch-365)
 * Version:     3.6.6
 * Description: Proxies EMC / TMC / NMC analyze calls from card.html through
 *              WordPress so the Hermes API key stays server-side and is never
 *              exposed in browser JavaScript.
 *              Also schedules a keep-alive ping every 5 minutes so the Replit
 *              production server never cold-starts between card uses.
 *
 * AJAX actions (nopriv = accessible without login):
 *   excreet_card_emc_proxy
 *   excreet_card_tmc_proxy
 *   excreet_card_nmc_proxy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EXCREET_365_HERMES_BASE', 'https://core-status-check.replit.app' );
define( 'EXCREET_365_KEEPALIVE_HOOK', 'excreet_365_hermes_keepalive' );
define( 'EXCREET_365_KEEPALIVE_INTERVAL', 'excreet_five_minutes' );

// ── API key ───────────────────────────────────────────────────────────────────

function excreet_365_hermes_key(): string {
    return defined( 'HERMES_API_KEY' )
        ? HERMES_API_KEY
        : (string) get_option( '_hermes_api_key', '' );
}

// ── Keep-alive cron ───────────────────────────────────────────────────────────
// Registers a custom 5-minute interval and schedules a lightweight ping to
// /api/hermes/health so the Replit server never idles between card uses.

add_filter( 'cron_schedules', static function ( array $schedules ): array {
    if ( ! isset( $schedules[ EXCREET_365_KEEPALIVE_INTERVAL ] ) ) {
        $schedules[ EXCREET_365_KEEPALIVE_INTERVAL ] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => 'Every 5 minutes',
        ];
    }
    return $schedules;
} );

add_action( EXCREET_365_KEEPALIVE_HOOK, static function (): void {
    $key = excreet_365_hermes_key();
    if ( $key === '' ) {
        return;
    }
    wp_remote_get( EXCREET_365_HERMES_BASE . '/api/hermes/health', [
        'timeout'   => 10,
        'sslverify' => true,
        'headers'   => [ 'Authorization' => 'Bearer ' . $key ],
        'blocking'  => false,
    ] );
} );

// Schedule on first load if not already scheduled.
if ( ! wp_next_scheduled( EXCREET_365_KEEPALIVE_HOOK ) ) {
    wp_schedule_event( time(), EXCREET_365_KEEPALIVE_INTERVAL, EXCREET_365_KEEPALIVE_HOOK );
}

// ── Proxy helper ──────────────────────────────────────────────────────────────

/**
 * Proxy a raw JSON POST body to a Hermes endpoint, adding the API key header.
 * Retries once on 502 / 503 / connection failure (handles Replit cold-starts).
 * Relays the exact HTTP status code and JSON body back to the browser.
 */
function excreet_365_proxy( string $endpoint ): void {
    $raw = (string) file_get_contents( 'php://input' );

    if ( $raw === '' ) {
        http_response_code( 400 );
        header( 'Content-Type: application/json' );
        echo wp_json_encode( [ 'error' => 'No request body received' ] );
        exit;
    }

    $api_key = excreet_365_hermes_key();

    if ( $api_key === '' ) {
        http_response_code( 500 );
        header( 'Content-Type: application/json' );
        echo wp_json_encode( [ 'error' => 'server_misconfiguration — API key not set' ] );
        exit;
    }

    $url     = EXCREET_365_HERMES_BASE . $endpoint;
    $args    = [
        'timeout'   => 90,
        'sslverify' => true,
        'headers'   => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body'      => $raw,
    ];

    // First attempt
    $response = wp_remote_post( $url, $args );
    $code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

    // Retry once if Replit is cold-starting (connection error, 502, or 503)
    if ( is_wp_error( $response ) || $code === 502 || $code === 503 ) {
        sleep( 3 );
        $response = wp_remote_post( $url, $args );
        $code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
    }

    if ( is_wp_error( $response ) ) {
        http_response_code( 502 );
        header( 'Content-Type: application/json' );
        echo wp_json_encode( [
            'error'  => 'Hermes server unavailable — please try again in a moment',
            'detail' => $response->get_error_message(),
        ] );
        exit;
    }

    $body = (string) wp_remote_retrieve_body( $response );

    http_response_code( $code );
    header( 'Content-Type: application/json' );
    echo $body;
    exit;
}

// ── EMC ───────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_excreet_card_emc_proxy', static function () {
    excreet_365_proxy( '/api/hermes/emc/analyze' );
} );
add_action( 'wp_ajax_excreet_card_emc_proxy', static function () {
    excreet_365_proxy( '/api/hermes/emc/analyze' );
} );

// ── TMC ───────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_excreet_card_tmc_proxy', static function () {
    excreet_365_proxy( '/api/hermes/tmc/analyze' );
} );
add_action( 'wp_ajax_excreet_card_tmc_proxy', static function () {
    excreet_365_proxy( '/api/hermes/tmc/analyze' );
} );

// ── NMC ───────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_excreet_card_nmc_proxy', static function () {
    excreet_365_proxy( '/api/hermes/nmc/analyze' );
} );
add_action( 'wp_ajax_excreet_card_nmc_proxy', static function () {
    excreet_365_proxy( '/api/hermes/nmc/analyze' );
} );
