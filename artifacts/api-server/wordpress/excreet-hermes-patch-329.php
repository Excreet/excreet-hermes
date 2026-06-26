<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.2.9
 * Description: Shop page — hide breadcrumb, page title, "All Products",
 *              and result count. CSS + PHP hooks for complete removal.
 * Version: 3.2.9b
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── PHP: suppress WooCommerce page title output entirely on shop ── */
add_filter( 'woocommerce_show_page_title',    '__return_false', 99 );
add_filter( 'woocommerce_page_title',         '__return_empty_string', 99 );

/* Remove the archive title action that prints "All Products" */
add_action( 'wp', function () {
    if ( ! function_exists( 'is_shop' ) ) { return; }
    if ( is_shop() || is_product_category() || is_product_tag() ) {
        remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb',        20 );
        remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
        remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description', 10 );
        /* Some themes output their own archive title — remove common hooks */
        remove_action( 'woocommerce_before_main_content', 'woocommerce_page_title',        10 );
        add_filter( 'woocommerce_show_page_title', '__return_false', 99 );
    }
} );

/* ── CSS: belt-and-suspenders hide for anything that slips through ── */
add_action( 'wp_enqueue_scripts', 'excreet_329_hide_shop_labels', 103 );

function excreet_329_hide_shop_labels(): void {
    if ( ! function_exists( 'is_woocommerce' ) ) { return; }
    if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) { return; }
    ?>
    <style id="ex329-hide-labels">

    /* Breadcrumb */
    body.post-type-archive-product .woocommerce-breadcrumb,
    body.post-type-archive-product nav.woocommerce-breadcrumb { display: none !important; }

    /* Page title / "Shop" / "All Products" — every possible selector */
    body.post-type-archive-product .woocommerce-products-header,
    body.post-type-archive-product .woocommerce-products-header__title,
    body.post-type-archive-product h1.page-title,
    body.post-type-archive-product .entry-title,
    body.post-type-archive-product .page-header,
    body.post-type-archive-product .woocommerce-archive-header,
    body.post-type-archive-product .archive-header,
    body.post-type-archive-product .archive-title { display: none !important; }

    /* Taxonomy / archive description */
    body.post-type-archive-product .term-description,
    body.post-type-archive-product .woocommerce-products-header .page-description,
    body.post-type-archive-product .woocommerce-products-header p { display: none !important; }

    /* "Showing all N results" */
    body.post-type-archive-product .woocommerce-result-count { display: none !important; }

    </style>
    <?php
}
