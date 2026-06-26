<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.2.4
 * Description: Definitive product image attach — uses local filesystem path (not HTTP)
 *              to import uploaded product images into WP media and set featured image.
 *              Runs fresh regardless of prior patch flags.
 * Version: 3.2.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX324_DONE_OPTION', 'excreet_324_img_attach_v1' );

add_action( 'init', 'excreet_324_attach_from_filesystem', 30 );

function excreet_324_attach_from_filesystem(): void {
    if ( get_option( EX324_DONE_OPTION ) ) { return; }
    if ( ! function_exists( 'wc_get_product' ) ) { return; }

    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $upload_dir = wp_upload_dir();
    $base_path  = $upload_dir['basedir']; /* e.g. .../wp-content/uploads */

    $map = [
        'amazon-vitaminc-doctorsbest'             => 'prod-B00HNS1E0W.jpg',
        'amazon-heritage-atomidine-iodine'        => 'prod-B00CQ7S1QK.jpg',
        'amazon-california-olive-ranch-olive-oil' => 'prod-B00CO1YXL0.jpg',
        'amazon-barleans-organic-flax-oil'        => 'prod-B00N55ASK4.jpg',
        'amazon-viva-naturals-coconut-oil'        => 'prod-B00DS842HS.jpg',
        'amazon-barleans-flaxseed-omega-369'      => 'prod-B002VLZ8DU.jpg',
        'amazon-enzymedica-digest-basic'          => 'prod-B001W44AV8.jpg',
        'amazon-enduracin-niacin-extended-release'=> 'prod-B014831ICS.jpg',
        'amazon-nutricost-niacin-500mg'           => 'prod-B01IIDB6JE.jpg',
    ];

    foreach ( $map as $slug => $filename ) {
        $post = get_page_by_path( $slug, OBJECT, 'product' );
        if ( ! $post ) { continue; }

        $file_path = $base_path . '/' . $filename;
        if ( ! file_exists( $file_path ) ) { continue; }

        /* Remove existing thumbnail */
        $old = get_post_thumbnail_id( $post->ID );
        if ( $old ) {
            wp_delete_attachment( $old, true );
            delete_post_thumbnail( $post->ID );
        }

        /* Insert from local file — no HTTP needed */
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $file_path,
        ];

        /* Copy to temp so WP can move it */
        $tmp = wp_tempnam( $filename );
        copy( $file_path, $tmp );
        $file_array['tmp_name'] = $tmp;

        $att_id = media_handle_sideload( $file_array, $post->ID, $post->post_title );

        if ( ! is_wp_error( $att_id ) ) {
            set_post_thumbnail( $post->ID, $att_id );
        }
    }

    update_option( EX324_DONE_OPTION, '1' );
}
