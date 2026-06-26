<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.2.7
 * Description: Shop page — plain white background, 4-per-row borderless product grid.
 *              Exact layout of reference: large floating image, name, price.
 *              Overrides all prior tile/background styles on shop only.
 * Version: 3.2.7
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', 'excreet_327_shop_styles', 101 );

function excreet_327_shop_styles(): void {
    if ( ! function_exists( 'is_woocommerce' ) ) { return; }
    if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) { return; }
    ?>
    <style id="ex327-shop">

    /* ── 1. Plain white background — overrides bathroom bg on shop only ── */
    body.post-type-archive-product,
    body.post-type-archive-product #page,
    body.post-type-archive-product .site,
    body.post-type-archive-product #content,
    body.post-type-archive-product #main,
    body.post-type-archive-product .site-content,
    body.post-type-archive-product .site-main,
    body.post-type-archive-product .woocommerce {
        background: #ffffff !important;
        background-image: none !important;
        background-color: #ffffff !important;
        color: #111111 !important;
    }

    /* ── 2. Full-width white wrapper ── */
    body.post-type-archive-product .woocommerce {
        max-width: 1280px !important;
        margin: 0 auto !important;
        padding: 2rem 2rem 4rem !important;
        box-shadow: none !important;
        border: none !important;
    }

    /* ── 3. Grid: 4 columns ── */
    body.post-type-archive-product ul.products {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 0 !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        list-style: none !important;
    }
    @media (max-width: 860px) {
        body.post-type-archive-product ul.products {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
    @media (max-width: 480px) {
        body.post-type-archive-product ul.products {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }

    /* ── 4. Each product cell — no card, no border, no shadow ── */
    body.post-type-archive-product ul.products li.product {
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        padding: 1.5rem 1.25rem 2rem !important;
        margin: 0 !important;
        width: 100% !important;
        float: none !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        transition: none !important;
        cursor: pointer !important;
    }
    body.post-type-archive-product ul.products li.product:hover {
        transform: none !important;
        box-shadow: none !important;
    }

    /* ── 5. Image link block ── */
    body.post-type-archive-product ul.products li.product
    a.woocommerce-loop-product__link {
        display: block !important;
        width: 100% !important;
        text-decoration: none !important;
        background: transparent !important;
    }

    /* ── 6. Product image — large, centered, no border ── */
    body.post-type-archive-product ul.products li.product
    a.woocommerce-loop-product__link img {
        display: block !important;
        width: auto !important;
        max-width: 100% !important;
        height: 240px !important;
        object-fit: contain !important;
        object-position: center bottom !important;
        background: transparent !important;
        margin: 0 auto 1.25rem !important;
        padding: 0 !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    /* ── 7. Product name ── */
    body.post-type-archive-product ul.products li.product
    h2.woocommerce-loop-product__title {
        color: #111111 !important;
        font-size: 0.92rem !important;
        font-weight: 600 !important;
        line-height: 1.4 !important;
        margin: 0 0 0.35rem !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
    }

    /* ── 8. Price ── */
    body.post-type-archive-product ul.products li.product .price {
        color: #2a7a2a !important;
        font-size: 0.92rem !important;
        font-weight: 600 !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        display: block !important;
    }
    body.post-type-archive-product ul.products li.product .price del {
        color: #999 !important;
        font-size: 0.82rem !important;
        font-weight: 400 !important;
        margin-right: 4px !important;
    }

    /* ── 9. Hide the "Add to cart" / "Shop Partner" button from grid view ── */
    body.post-type-archive-product ul.products li.product .button,
    body.post-type-archive-product ul.products li.product a.button,
    body.post-type-archive-product ul.products li.product .ex-partner-badge {
        display: none !important;
    }

    /* ── 10. Sale badge ── */
    body.post-type-archive-product ul.products li.product .onsale {
        background: #e44 !important;
        color: #fff !important;
        border-radius: 3px !important;
        font-size: 0.7rem !important;
        font-weight: 700 !important;
        top: 1.5rem !important;
        left: 1.25rem !important;
        z-index: 5 !important;
        padding: 2px 8px !important;
    }

    /* ── 11. WooCommerce notice strip ── */
    body.post-type-archive-product .woocommerce-result-count,
    body.post-type-archive-product .woocommerce-ordering {
        color: #555 !important;
        background: transparent !important;
        border: none !important;
    }
    body.post-type-archive-product .woocommerce-ordering select {
        background: #f5f5f5 !important;
        color: #111 !important;
        border: 1px solid #ddd !important;
        border-radius: 4px !important;
    }

    </style>
    <?php
}
