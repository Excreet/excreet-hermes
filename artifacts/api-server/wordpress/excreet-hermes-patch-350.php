<?php
/**
 * Plugin Name: Excreet Hermes — Patch 350 (Shop Page Styling)
 * Description: Styles the WooCommerce shop page title in bold gold Cormorant
 *              and removes the WooCommerce archive intro paragraph.
 * Version: 3.5.0
 * Author: Excreet Engineering
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Remove WooCommerce archive/shop intro paragraph ───────────────────────────
add_action( 'init', function () {
    remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description', 10 );
} );

// ── Override the shop page title with bold gold styling ───────────────────────
add_filter( 'woocommerce_page_title', function ( $title ) {
    if ( is_shop() ) return 'EXCREET SHOP';
    return $title;
} );

// ── Inject CSS: gold bold title on shop page ──────────────────────────────────
add_action( 'wp_head', function () {
    if ( ! is_shop() ) return;
    ?>
<style id="ex350-shop-styles">
/* ── Shop page title — white bold Cormorant with purple outline ── */
.woocommerce-products-header__title.page-title,
h1.woocommerce-products-header__title,
.woocommerce .woocommerce-products-header h1,
.page-title,
h1.entry-title,
.shop-title,
body.post-type-archive-product h1 {
    font-family: 'Cormorant Garamond', Georgia, serif !important;
    font-size: clamp(32px, 5vw, 62px) !important;
    font-weight: 700 !important;
    color: #ffffff !important;
    -webkit-text-stroke: 2px #56075E !important;
    text-shadow:
        -2px -2px 0 #56075E,
         2px -2px 0 #56075E,
        -2px  2px 0 #56075E,
         2px  2px 0 #56075E !important;
    letter-spacing: 0.08em !important;
    text-transform: uppercase !important;
    margin-bottom: 0.4em !important;
    line-height: 1.1 !important;
}

/* ── Hide archive description block entirely ── */
.woocommerce-products-header__description,
.woocommerce-product-archive-description,
.term-description,
p.woocommerce-result-count ~ .woocommerce-products-header__description {
    display: none !important;
}
</style>
    <?php
} );
