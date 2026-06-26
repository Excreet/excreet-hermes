<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.1.5
 * Description: Product Image Importer — one-time run on init.
 *              Imports excreet-formula-bottle.png from /wp-content/uploads/
 *              into the WP media library and sets it as the featured image
 *              for the Excreet Signature Formula product.
 *              Safe to re-run — skips if image already attached.
 *
 * Version: 3.1.5
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX315_DONE_OPTION', 'excreet_315_image_imported_v1' );

add_action( 'init', 'excreet_315_maybe_import_image', 25 );

function excreet_315_maybe_import_image(): void {
    if ( get_option( EX315_DONE_OPTION ) ) { return; }
    if ( ! function_exists( 'media_sideload_image' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $product_post = get_page_by_path( 'excreet-signature-formula', OBJECT, 'product' );
    if ( ! $product_post ) { return; }

    $product_id = (int) $product_post->ID;

    if ( has_post_thumbnail( $product_id ) ) {
        delete_post_meta( $product_id, '_ex314_needs_image' );
        update_option( EX315_DONE_OPTION, '1' );
        return;
    }

    $image_url = home_url( '/wp-content/uploads/excreet-formula-bottle.png' );

    $attachment_id = media_sideload_image(
        $image_url,
        $product_id,
        'Excreet Cell Ready Minerals — 32 FL OZ',
        'id'
    );

    if ( is_wp_error( $attachment_id ) ) { return; }

    set_post_thumbnail( $product_id, $attachment_id );
    delete_post_meta( $product_id, '_ex314_needs_image' );
    update_option( EX315_DONE_OPTION, '1' );
}
