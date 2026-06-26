<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.2.1
 * Description: Attaches Amazon product images to all affiliate products by ASIN.
 *              Sideloads from uploaded files into WP media library and sets featured image.
 * Version: 3.2.1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX321_DONE_OPTION', 'excreet_321_images_done_v1' );

add_action( 'init', 'excreet_321_attach_images', 30 );

function excreet_321_attach_images(): void {
    if ( get_option( EX321_DONE_OPTION ) ) { return; }
    if ( ! function_exists( 'wc_get_product' ) ) { return; }

    if ( ! function_exists( 'media_sideload_image' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    /* Map: product slug => ASIN */
    $map = [
        'amazon-vitaminc-doctorsbest'          => 'B00HNS1E0W',
        'amazon-heritage-atomidine-iodine'     => 'B00CQ7S1QK',
        'amazon-california-olive-ranch-olive-oil' => 'B00CO1YXL0',
        'amazon-barleans-organic-flax-oil'     => 'B00N55ASK4',
        'amazon-viva-naturals-coconut-oil'     => 'B00DS842HS',
        'amazon-barleans-flaxseed-omega-369'   => 'B002VLZ8DU',
        'amazon-enzymedica-digest-basic'       => 'B001W44AV8',
        'amazon-enduracin-niacin-extended-release' => 'B014831ICS',
        'amazon-nutricost-niacin-500mg'        => 'B01IIDB6JE',
        'amazon-b0859ls4r4'                    => 'B0859LS4R4',
        'amazon-b0dtjc6d72'                    => 'B0DTJC6D72',
    ];

    foreach ( $map as $slug => $asin ) {
        $post = get_page_by_path( $slug, OBJECT, 'product' );
        if ( ! $post ) { continue; }

        /* Skip if already has a thumbnail */
        if ( has_post_thumbnail( $post->ID ) ) { continue; }

        $img_url = home_url( '/wp-content/uploads/product-' . $asin . '.jpg' );
        $att_id  = media_sideload_image( $img_url, $post->ID, $post->post_title, 'id' );

        if ( ! is_wp_error( $att_id ) ) {
            set_post_thumbnail( $post->ID, $att_id );
        }
    }

    update_option( EX321_DONE_OPTION, '1' );
}
