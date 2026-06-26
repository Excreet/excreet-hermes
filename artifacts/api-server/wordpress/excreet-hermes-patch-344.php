<?php
/**
 * Plugin Name: Excreet Hermes Patch 344 — Member Count REST Endpoint
 * Version: 3.4.4
 * Description: Exposes PMPro active member counts to Hermes via a protected REST endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Store the shared key on first run ── */
add_action( 'admin_init', function () {
    if ( ! get_option( '_excreet_344_member_key' ) ) {
        // Key must be seeded externally via WP-CLI / SSH after deployment
    }
} );

/* ── REST endpoint: GET /wp-json/excreet/v1/members/count ── */
add_action( 'rest_api_init', function () {
    register_rest_route( 'excreet/v1', '/members/count', [
        'methods'             => 'GET',
        'callback'            => 'excreet_344_member_counts',
        'permission_callback' => 'excreet_344_check_key',
    ] );
} );

function excreet_344_check_key( WP_REST_Request $request ): bool {
    $stored = get_option( '_excreet_344_member_key', '' );
    if ( ! $stored ) return false;

    $auth = $request->get_header( 'authorization' ) ?? '';
    if ( ! preg_match( '/^Bearer\s+(\S+)$/i', $auth, $m ) ) return false;

    return hash_equals( $stored, $m[1] );
}

function excreet_344_member_counts(): WP_REST_Response {
    global $wpdb;
    $t = $wpdb->prefix . 'pmpro_memberships_users';

    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$t} WHERE status='active'"
    );
    $new_30 = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$t} WHERE status='active' AND startdate >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );

    $levels_raw = $wpdb->get_results(
        "SELECT ml.id, ml.name, COUNT(mu.id) AS cnt
         FROM {$wpdb->prefix}pmpro_membership_levels ml
         LEFT JOIN {$t} mu ON mu.membership_id = ml.id AND mu.status = 'active'
         GROUP BY ml.id
         ORDER BY ml.id"
    );

    $levels = array_map( fn( $r ) => [
        'id'    => (int) $r->id,
        'name'  => $r->name,
        'count' => (int) $r->cnt,
    ], $levels_raw );

    return new WP_REST_Response( [
        'total'      => $total,
        'new_last_30' => $new_30,
        'by_level'   => $levels,
        'fetched_at' => gmdate( 'c' ),
    ], 200 );
}
