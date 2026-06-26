<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.3.7
 * Description: Ensures Starter (Level 1) initial_payment and billing_amount
 *              are always $15.00. Runs on every init — idempotent DB write.
 *              Permanently neutralizes patch-334 test price.
 * Version: 3.3.7b
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', 'excreet_337_reset_price', 5 );
function excreet_337_reset_price(): void {
    if ( ! function_exists( 'pmpro_getLevel' ) ) { return; }

    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'pmpro_membership_levels',
        [
            'initial_payment' => '15.00',
            'billing_amount'  => '15.00',
        ],
        [ 'id' => 1 ],
        [ '%s', '%s' ],
        [ '%d' ]
    );

    // Keep patch-334 permanently disabled
    delete_option( '_excreet_334_done' );
}
