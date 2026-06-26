<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.1.8
 * Description: Store Go-Live — publishes the 6 draft Amazon affiliate products,
 *              confirms shop page is set in WooCommerce, and clears store notice.
 * Version: 3.1.8
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX318_DONE_OPTION', 'excreet_318_golive_done' );

add_action( 'init', 'excreet_318_go_live', 25 );

function excreet_318_go_live(): void {
    if ( get_option( EX318_DONE_OPTION ) ) { return; }
    if ( ! function_exists( 'wc_get_product' ) ) { return; }

    /* 1. Publish all draft products created by patch-314 */
    $drafts = get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'draft',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'   => '_ex314_needs_setup',
                'value' => '1',
            ],
        ],
        'fields' => 'ids',
    ] );

    foreach ( $drafts as $pid ) {
        wp_update_post( [
            'ID'          => (int) $pid,
            'post_status' => 'publish',
        ] );
        /* Remove the needs-setup flag so admin notice clears */
        delete_post_meta( (int) $pid, '_ex314_needs_setup' );
    }

    /* 2. Ensure WooCommerce shop page option is set */
    $shop_page = get_page_by_path( 'shop' );
    if ( $shop_page && ! get_option( 'woocommerce_shop_page_id' ) ) {
        update_option( 'woocommerce_shop_page_id', $shop_page->ID );
    }

    /* 3. Disable WooCommerce coming-soon / store notice if still on */
    update_option( 'woocommerce_store_notice_dismiss', 'true' );
    update_option( 'woocommerce_coming_soon', 'no' );

    update_option( EX318_DONE_OPTION, '1' );
}
