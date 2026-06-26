<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.2.6
 * Description: Shop tile redesign v2 — minimal clean 4-col grid matching
 *              acceleratedhealthproducts.com reference: large floating image,
 *              white card, compact dark text below, no heavy borders.
 *              Supersedes patch-303 and patch-325 card styles.
 * Version: 3.2.6
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', 'excreet_326_tile_styles', 100 );

function excreet_326_tile_styles(): void {
    if ( ! function_exists( 'is_woocommerce' ) ) { return; }
    if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) { return; }
    ?>
    <style id="ex326-shop-tiles">

    /* ══════════════════════════════════════════════════
       GRID — 4 cols desktop → 2 tablet → 1 mobile
       ══════════════════════════════════════════════════ */
    body.post-type-archive-product ul.products {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 1.5rem !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        list-style: none !important;
    }
    @media (max-width: 900px) {
        body.post-type-archive-product ul.products {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 1.2rem !important;
        }
    }
    @media (max-width: 480px) {
        body.post-type-archive-product ul.products {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 0.9rem !important;
        }
    }

    /* ══════════════════════════════════════════════════
       CARD SHELL — pure white, clean shadow, no thick border
       ══════════════════════════════════════════════════ */
    body.post-type-archive-product ul.products li.product {
        position: relative !important;
        display: flex !important;
        flex-direction: column !important;
        background: #ffffff !important;
        border: none !important;
        border-radius: 8px !important;
        overflow: hidden !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        float: none !important;
        box-shadow: 0 2px 12px rgba(0,0,0,0.14), 0 1px 4px rgba(0,0,0,0.08) !important;
        transition: box-shadow 0.2s ease, transform 0.2s ease !important;
        cursor: pointer !important;
    }
    body.post-type-archive-product ul.products li.product:hover {
        box-shadow: 0 8px 32px rgba(0,0,0,0.22), 0 2px 8px rgba(0,0,0,0.1) !important;
        transform: translateY(-3px) !important;
    }

    /* ══════════════════════════════════════════════════
       IMAGE AREA — large, white, full-width, no padding bleed
       ══════════════════════════════════════════════════ */
    body.post-type-archive-product ul.products li.product
    a.woocommerce-loop-product__link {
        display: block !important;
        text-decoration: none !important;
        background: #ffffff !important;
    }

    body.post-type-archive-product ul.products li.product
    a.woocommerce-loop-product__link img {
        display: block !important;
        width: 100% !important;
        height: 260px !important;
        object-fit: contain !important;
        object-position: center center !important;
        background: #ffffff !important;
        padding: 20px !important;
        box-sizing: border-box !important;
        margin: 0 !important;
        border-radius: 0 !important;
        border: none !important;
    }

    /* ══════════════════════════════════════════════════
       INFO SECTION — light grey bottom strip
       ══════════════════════════════════════════════════ */
    body.post-type-archive-product ul.products li.product
    h2.woocommerce-loop-product__title {
        color: #1a0a2e !important;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        line-height: 1.4 !important;
        margin: 0 !important;
        padding: 0.75rem 0.9rem 0.25rem !important;
        background: #f7f7f7 !important;
        border-top: 1px solid #ececec !important;
    }

    /* ── Price ── */
    body.post-type-archive-product ul.products li.product .price {
        color: #6B2FA0 !important;
        font-size: 0.95rem !important;
        font-weight: 700 !important;
        margin: 0 !important;
        padding: 0.1rem 0.9rem 0.55rem !important;
        display: block !important;
        background: #f7f7f7 !important;
    }
    body.post-type-archive-product ul.products li.product .price del {
        color: #aaa !important;
        font-size: 0.8rem !important;
        font-weight: 400 !important;
        margin-right: 4px !important;
    }

    /* ── CTA button — full-width gold bar ── */
    body.post-type-archive-product ul.products li.product .button,
    body.post-type-archive-product ul.products li.product a.button {
        display: block !important;
        width: 100% !important;
        background: #2a0a4a !important;
        color: #C9A84C !important;
        border: none !important;
        border-radius: 0 0 8px 8px !important;
        padding: 0.7rem 0.9rem !important;
        font-size: 0.72rem !important;
        font-weight: 800 !important;
        letter-spacing: 0.1em !important;
        text-transform: uppercase !important;
        text-align: center !important;
        margin: auto 0 0 !important;
        transition: background 0.15s, color 0.15s !important;
        box-sizing: border-box !important;
    }
    body.post-type-archive-product ul.products li.product .button:hover,
    body.post-type-archive-product ul.products li.product a.button:hover {
        background: #C9A84C !important;
        color: #1a0a2e !important;
    }

    /* ── Sale badge ── */
    body.post-type-archive-product ul.products li.product .onsale {
        background: #C9A84C !important;
        color: #1a0a2e !important;
        border-radius: 4px !important;
        font-weight: 700 !important;
        font-size: 0.72rem !important;
        top: 8px !important;
        left: 8px !important;
        z-index: 5 !important;
    }

    /* ── Affiliate partner badge ── */
    body.post-type-archive-product ul.products li.product .ex-partner-badge {
        position: absolute !important;
        top: 8px !important;
        right: 8px !important;
        background: rgba(201,168,76,0.92) !important;
        color: #1a0a2e !important;
        font-size: 0.62rem !important;
        font-weight: 800 !important;
        letter-spacing: 0.06em !important;
        text-transform: uppercase !important;
        padding: 2px 7px !important;
        border-radius: 3px !important;
        z-index: 10 !important;
    }

    /* ══════════════════════════════════════════════════
       WOOCOMMERCE WRAPPER — ensure shop content has room
       ══════════════════════════════════════════════════ */
    body.post-type-archive-product .woocommerce {
        max-width: 1200px !important;
        margin: 0 auto !important;
        padding: 1.5rem !important;
    }

    </style>
    <?php
}
