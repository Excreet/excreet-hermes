<?php
/**
 * Excreet Patch 364 — Membership Level AJAX (v3.6.4)
 *
 * Provides a lightweight AJAX endpoint used by card.html to determine whether
 * the current browser session belongs to a Premium member. This gates the
 * Tongue Map Check (TMC) and Nail Map Check (NMC) features on the card page.
 *
 * Endpoint: GET /wp-admin/admin-ajax.php?action=excreet_364_membership
 * Response: { "level": 0 | 1 | 2 }
 *   0 = not logged in or not a member
 *   1 = Starter member (any active PMPro level that is not Premium)
 *   2 = Premium member
 *
 * No nonce required — this is a read-only session check.
 * CORS header added so card.html (served from WP root) can call it cross-origin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_excreet_364_membership',        'excreet_364_ajax_membership' );
add_action( 'wp_ajax_nopriv_excreet_364_membership', 'excreet_364_ajax_membership' );

function excreet_364_ajax_membership() {
    // Allow card.html (same domain) to read this via fetch with credentials
    $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( $_SERVER['HTTP_ORIGIN'] ) : '';
    if ( $origin ) {
        header( 'Access-Control-Allow-Origin: ' . $origin );
        header( 'Access-Control-Allow-Credentials: true' );
    }
    header( 'Content-Type: application/json' );

    if ( ! is_user_logged_in() ) {
        echo wp_json_encode( array( 'level' => 0 ) );
        wp_die();
    }

    $user_id = get_current_user_id();

    if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
        // PMPro not active — grant level 1 to any logged-in user as fallback
        echo wp_json_encode( array( 'level' => 1 ) );
        wp_die();
    }

    $premium_level_id = (int) get_option( '_excreet_293_premium_product', 2 );

    if ( pmpro_hasMembershipLevel( $premium_level_id, $user_id ) ) {
        echo wp_json_encode( array( 'level' => 2 ) );
        wp_die();
    }

    if ( pmpro_hasMembershipLevel( null, $user_id ) ) {
        echo wp_json_encode( array( 'level' => 1 ) );
        wp_die();
    }

    echo wp_json_encode( array( 'level' => 0 ) );
    wp_die();
}
