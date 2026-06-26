<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.1.7
 * Description: One-time price correction — sets Excreet Signature Formula to $65.00.
 *              Runs once on init, skips if already done.
 *
 * Version: 3.1.7
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX317_DONE_OPTION', 'excreet_317_price_set_v1' );

add_action( 'init', 'excreet_317_set_price', 25 );

function excreet_317_set_price(): void {
    if ( get_option( EX317_DONE_OPTION ) ) { return; }
    if ( ! function_exists( 'wc_get_product' ) ) { return; }

    $post = get_page_by_path( 'excreet-signature-formula', OBJECT, 'product' );
    if ( ! $post ) { return; }

    $product = wc_get_product( $post->ID );
    if ( ! $product ) { return; }

    $product->set_regular_price( '65.00' );
    $product->set_price( '65.00' );
    $product->save();

    update_option( EX317_DONE_OPTION, '1' );
}
