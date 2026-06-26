<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.2.5
 * Description: Shop tile redesign — image-dominant cards.
 *              Image fills the full card face; compact dark info bar pins to bottom.
 *              Overrides patch-303 card styles only.
 * Version: 3.2.5
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', 'excreet_325_tile_styles', 99 );

function excreet_325_tile_styles(): void {
    if ( ! function_exists( 'is_woocommerce' ) ) { return; }
    if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) { return; }
    ?>
    <style id="ex325-shop-tiles">

    /* ── Grid: 2 cols desktop, 1 col mobile ── */
    body.post-type-archive-product ul.products {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 2rem !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        list-style: none !important;
    }
    @media (max-width: 640px) {
        body.post-type-archive-product ul.products {
            grid-template-columns: 1fr !important;
            gap: 1.4rem !important;
        }
    }

    /* ── Card shell — flex column, fixed height ── */
    body.post-type-archive-product ul.products li.product {
        position: relative !important;
        display: flex !important;
        flex-direction: column !important;
        background: #ffffff !important;
        border: 3px solid #2a0a4a !important;
        border-radius: 6px !important;
        overflow: hidden !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        float: none !important;
        min-height: 480px !important;
        box-shadow:
            5px 5px 0 #2a0a4a,
            0 0 0 6px rgba(201,168,76,0.18),
            0 10px 36px rgba(0,0,0,0.45) !important;
        transition: transform 0.18s ease, box-shadow 0.22s ease !important;
    }
    body.post-type-archive-product ul.products li.product:hover {
        transform: translateY(-4px) !important;
        box-shadow:
            5px 5px 0 #2a0a4a,
            0 0 0 6px rgba(201,168,76,0.45),
            0 18px 48px rgba(0,0,0,0.55) !important;
    }

    /* ── Image link — grows to fill all available space ── */
    body.post-type-archive-product ul.products li.product
    a.woocommerce-loop-product__link {
        display: flex !important;
        flex-direction: column !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
        text-decoration: none !important;
    }

    /* ── Image — fills the link area edge-to-edge ── */
    body.post-type-archive-product ul.products li.product
    a.woocommerce-loop-product__link img {
        display: block !important;
        width: 100% !important;
        height: 100% !important;
        min-height: 340px !important;
        flex: 1 1 auto !important;
        object-fit: contain !important;
        object-position: center center !important;
        background: #ffffff !important;
        padding: 16px !important;
        box-sizing: border-box !important;
        margin: 0 !important;
        border-radius: 0 !important;
        border-bottom: 3px solid #2a0a4a !important;
    }

    /* ── Title — inside the link, sits just above info bar ── */
    body.post-type-archive-product ul.products li.product
    a.woocommerce-loop-product__link
    .woocommerce-loop-product__title {
        display: none !important; /* hidden here — shown in info bar below */
    }

    /* ── Inject title into the info bar via the standalone h2 ── */
    body.post-type-archive-product ul.products li.product
    h2.woocommerce-loop-product__title {
        background: #2a0a4a !important;
        color: #f0e8ff !important;
        font-size: 0.95rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.02em !important;
        line-height: 1.35 !important;
        margin: 0 !important;
        padding: 0.75rem 1rem 0.5rem !important;
        border-top: 2px solid rgba(201,168,76,0.3) !important;
    }

    /* ── Price ── */
    body.post-type-archive-product ul.products li.product .price {
        background: #2a0a4a !important;
        color: #C9A84C !important;
        font-size: 1.1rem !important;
        font-weight: 800 !important;
        margin: 0 !important;
        padding: 0.1rem 1rem 0.65rem !important;
        display: block !important;
    }
    body.post-type-archive-product ul.products li.product .price del {
        color: rgba(201,168,76,0.4) !important;
        font-size: 0.85rem !important;
        font-weight: 400 !important;
    }

    /* ── Sale badge ── */
    body.post-type-archive-product ul.products li.product .onsale {
        background: #C9A84C !important;
        color: #1a0a2e !important;
        border-radius: 0 !important;
        font-weight: 700 !important;
        top: 0 !important;
        left: 0 !important;
        z-index: 5 !important;
    }

    /* ── CTA button — full-width gold bar at very bottom ── */
    body.post-type-archive-product ul.products li.product .button,
    body.post-type-archive-product ul.products li.product a.button {
        display: block !important;
        width: 100% !important;
        background: #C9A84C !important;
        color: #1a0a2e !important;
        border: none !important;
        border-top: 2px solid rgba(42,10,74,0.6) !important;
        border-radius: 0 !important;
        padding: 0.85rem 1rem !important;
        font-size: 0.78rem !important;
        font-weight: 900 !important;
        letter-spacing: 0.12em !important;
        text-transform: uppercase !important;
        text-align: center !important;
        margin: 0 !important;
        transition: background 0.15s, color 0.15s !important;
        box-sizing: border-box !important;
    }
    body.post-type-archive-product ul.products li.product .button:hover,
    body.post-type-archive-product ul.products li.product a.button:hover {
        background: #e8c760 !important;
        color: #1a0a2e !important;
    }

    /* ── Affiliate partner badge (from patch-303 section D) ── */
    body.post-type-archive-product ul.products li.product .ex-partner-badge {
        position: absolute !important;
        top: 8px !important;
        right: 8px !important;
        background: rgba(201,168,76,0.92) !important;
        color: #1a0a2e !important;
        font-size: 0.68rem !important;
        font-weight: 800 !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
        padding: 3px 8px !important;
        border-radius: 3px !important;
        z-index: 10 !important;
    }

    </style>
    <?php
}
