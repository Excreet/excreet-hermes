<?php
/**
 * Excreet patch-363 v3.6.3b
 * EMC (Eye Map Check) WordPress REST API proxy
 *
 * Registers POST /wp-json/excreet/v1/emc — public, no auth.
 * Forwards the base64 image to Hermes /api/hermes/emc/analyze
 * using the server-side API key so it never appears client-side.
 *
 * Uses REST API (not admin-ajax) because SiteGround nginx blocks
 * external POST requests to wp-admin at the infrastructure level.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'excreet_363_register_emc_route' );

function excreet_363_register_emc_route(): void {
    register_rest_route( 'excreet/v1', '/emc', [
        'methods'             => 'POST',
        'callback'            => 'excreet_363_emc_handler',
        'permission_callback' => '__return_true',
        'args'                => [],
    ] );
}

function excreet_363_emc_handler( WP_REST_Request $request ): WP_REST_Response {

    /* ── Extract image from JSON body ────────────────────────── */
    $image = $request->get_param( 'image' );

    if ( empty( $image ) || ! is_string( $image ) ) {
        return new WP_REST_Response(
            [ 'error' => 'Invalid request. Expected { image: "<base64 string>" }.' ],
            400
        );
    }

    /* ── Resolve Hermes base URL + API key ───────────────────── */
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
        return new WP_REST_Response(
            [ 'error' => 'Connection error reaching analysis server.' ],
            502
        );
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $json   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! is_array( $json ) ) {
        return new WP_REST_Response(
            [ 'error' => 'Analysis failed. Please try again.' ],
            502
        );
    }

    return new WP_REST_Response( $json, $status );
}
