<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.1.9
 * Description: Adds Doctor's Best Vitamin C Powder as a published affiliate product
 *              with ASIN B00HNS1E0W, affiliate tag excreetshop06-20, and product image.
 * Version: 3.1.9
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX319_DONE_OPTION', 'excreet_319_vitaminc_done' );

add_action( 'init', 'excreet_319_add_vitaminc', 25 );

function excreet_319_add_vitaminc(): void {
    if ( get_option( EX319_DONE_OPTION ) ) { return; }
    if ( ! function_exists( 'wc_get_product' ) ) { return; }

    $slug = 'amazon-vitaminc-doctorsbest';
    $asin = 'B00HNS1E0W';
    $tag  = 'excreetshop06-20';
    $url  = 'https://www.amazon.com/dp/' . $asin . '?tag=' . $tag;

    /* Create or update the product */
    $existing = get_page_by_path( $slug, OBJECT, 'product' );
    if ( $existing ) {
        $post_id = $existing->ID;
        wp_update_post( [
            'ID'          => $post_id,
            'post_status' => 'publish',
        ] );
    } else {
        $post_id = wp_insert_post( [
            'post_title'   => "Doctor's Best Vitamin C Powder — 250g",
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_type'    => 'product',
            'post_content' => '<p>Doctor\'s Best Vitamin C Powder uses Quali-C, a premium Vitamin C sourced from Scotland and manufactured to the highest quality standards. Supports immune health, collagen synthesis, antioxidant protection, and cellular energy — a foundational daily supplement for the Excreet health protocol.</p><ul><li>250g per container — approximately 250 servings at 1g/day</li><li>Quali-C source — pharmaceutical-grade Vitamin C from Scotland</li><li>Unflavoured, mixes easily in water or juice</li><li>No fillers, binders, or artificial additives</li></ul>',
            'post_excerpt' => "Doctor's Best Vitamin C Powder with Quali-C — pure, unflavoured, 250g. Immune, cellular, and antioxidant support.",
        ] );
    }

    if ( ! $post_id || is_wp_error( $post_id ) ) {
        update_option( EX319_DONE_OPTION, 'error' );
        return;
    }

    wp_set_object_terms( $post_id, [ 'partner-picks', 'cellular-health' ], 'product_cat' );
    wp_set_object_terms( $post_id, 'external', 'product_type' );

    update_post_meta( $post_id, '_price',         '22.99' );
    update_post_meta( $post_id, '_regular_price', '22.99' );
    update_post_meta( $post_id, '_product_url',   $url );
    update_post_meta( $post_id, '_button_text',   'View on Amazon →' );
    update_post_meta( $post_id, '_stock_status',  'instock' );
    update_post_meta( $post_id, '_visibility',    'visible' );
    update_post_meta( $post_id, '_ex314_asin',    $asin );

    /* Import image into WP media library and set as featured image */
    $img_url    = home_url( '/wp-content/uploads/vitaminc-doctorsbest.png' );
    $upload_dir = wp_upload_dir();

    if ( ! function_exists( 'media_sideload_image' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $attachment_id = media_sideload_image( $img_url, $post_id, "Doctor's Best Vitamin C Powder", 'id' );
    if ( ! is_wp_error( $attachment_id ) ) {
        set_post_thumbnail( $post_id, $attachment_id );
    }

    update_option( EX319_DONE_OPTION, '1' );
}
