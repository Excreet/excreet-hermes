<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.2.3
 * Description: Removes two unknown placeholder products. Re-attaches full product
 *              images using .01.L.jpg (reliable for all ASINs). Fixes image display.
 * Version: 3.2.3
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX323_DONE_OPTION', 'excreet_323_cleanup_v2' );

add_action( 'init', 'excreet_323_cleanup_and_reimages', 30 );

function excreet_323_cleanup_and_reimages(): void {
    if ( get_option( EX323_DONE_OPTION ) ) { return; }
    if ( ! function_exists( 'wc_get_product' ) ) { return; }

    /* ── 1. Force-delete the two unknown placeholder products ── */
    foreach ( [ 'amazon-b0859ls4r4', 'amazon-b0dtjc6d72' ] as $slug ) {
        $post = get_page_by_path( $slug, OBJECT, 'product' );
        if ( $post ) { wp_delete_post( $post->ID, true ); }
    }

    /* ── 2. Re-attach full product images for the 9 real products ── */
    if ( ! function_exists( 'media_sideload_image' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $map = [
        'amazon-vitaminc-doctorsbest'             => 'B00HNS1E0W',
        'amazon-heritage-atomidine-iodine'        => 'B00CQ7S1QK',
        'amazon-california-olive-ranch-olive-oil' => 'B00CO1YXL0',
        'amazon-barleans-organic-flax-oil'        => 'B00N55ASK4',
        'amazon-viva-naturals-coconut-oil'        => 'B00DS842HS',
        'amazon-barleans-flaxseed-omega-369'      => 'B002VLZ8DU',
        'amazon-enzymedica-digest-basic'          => 'B001W44AV8',
        'amazon-enduracin-niacin-extended-release'=> 'B014831ICS',
        'amazon-nutricost-niacin-500mg'           => 'B01IIDB6JE',
    ];

    foreach ( $map as $slug => $asin ) {
        $post = get_page_by_path( $slug, OBJECT, 'product' );
        if ( ! $post ) { continue; }

        $old = get_post_thumbnail_id( $post->ID );
        if ( $old ) {
            wp_delete_attachment( $old, true );
            delete_post_thumbnail( $post->ID );
        }

        $img_url = 'https://images-na.ssl-images-amazon.com/images/P/' . $asin . '.01.L.jpg';
        $att_id  = media_sideload_image( $img_url, $post->ID, $post->post_title, 'id' );
        if ( ! is_wp_error( $att_id ) ) {
            set_post_thumbnail( $post->ID, $att_id );
        }
    }

    update_option( EX323_DONE_OPTION, '1' );
}
