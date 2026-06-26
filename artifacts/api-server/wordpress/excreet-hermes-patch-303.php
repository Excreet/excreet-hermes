<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.0.3
 * Description: Excreet Store — WooCommerce member-gated storefront.
 *
 *   A — Member gate
 *       Redirects non-logged-in visitors on any WooCommerce page to /login/.
 *       Non-members (no PMPro level) are redirected to /membership-levels/.
 *
 *   B — Botanical palette styling
 *       Dark purple/gold Excreet brand applied to: shop, single product,
 *       cart, checkout, my-account (WooCommerce), and order confirmation.
 *
 *   C — Shop page hero
 *       Injects a branded hero banner above the product loop on /shop/.
 *       Tagline, gold wordmark, and botanical texture consistent with site.
 *
 *   D — Product card enhancements
 *       Hover effects, gold "Add to Cart" / "Buy Now" buttons, affiliate
 *       badge on External/Affiliate products ("Shop Partner →").
 *
 *   E — Cart & Checkout styling
 *       Dark card treatment for order summary, input fields, place-order button.
 *
 *   F — WooCommerce My Account styling
 *       Orders, downloads, addresses in Excreet palette. Tabs styled as
 *       gold underline nav. Distinct from /membership-account/ (PMPro).
 *
 * Version: 3.0.3a
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ─────────────────────────────────────────────────────────────────────────── */
/*  CONSTANTS                                                                   */
/* ─────────────────────────────────────────────────────────────────────────── */

define( 'EX303_PURPLE',      '#6B2FA0' );
define( 'EX303_PURPLE_DARK', '#3D1060' );
define( 'EX303_PURPLE_MID',  '#1a0a2e' );
define( 'EX303_GOLD',        '#C9A84C' );
define( 'EX303_GOLD_LIGHT',  '#e8c96a' );

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  A — MEMBER GATE                                                             */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'template_redirect', 'excreet_303_member_gate', 1 );

function excreet_303_member_gate(): void {
    if ( ! function_exists( 'is_woocommerce' ) ) { return; }
    if ( ! ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) { return; }

    /* Shop listing page is public — anyone can browse partner picks */
    if ( is_shop() ) { return; }

    /* Formula product page (ID 890) is public — has its own inline member gate */
    if ( is_product() && get_the_ID() === 890 ) { return; }

    /* Admins and shop managers bypass the gate */
    if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) { return; }

    if ( ! is_user_logged_in() ) {
        $login_url = function_exists( 'pmpro_url' )
            ? pmpro_url( 'login' )
            : home_url( '/member-login/' );
        wp_safe_redirect( add_query_arg( 'redirect_to', urlencode( get_permalink() ), $login_url ) );
        exit;
    }

    if ( function_exists( 'pmpro_hasMembershipLevel' ) ) {
        if ( ! pmpro_hasMembershipLevel( null, get_current_user_id() ) ) {
            wp_safe_redirect( home_url( '/membership-levels/' ) );
            exit;
        }
    }
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  B — BOTANICAL PALETTE STYLING                                               */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_head', 'excreet_303_woo_styles', 99 );

function excreet_303_woo_styles(): void {
    if ( ! function_exists( 'is_woocommerce' ) ) { return; }
    if ( ! ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) { return; }

    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url = esc_url( 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg' );
    ?>
    <style id="ex303-woo-styles">

    /* ── Bathroom background on all WooCommerce pages ── */
    html,
    body.woocommerce,
    body.woocommerce-page {
        background: url("<?php echo $bg_url; ?>") center/cover no-repeat fixed #0c0115 !important;
        min-height: 100vh;
        color: #f0e8ff !important;
    }

    /* ── Strip opaque theme wrappers so background shows through ── */
    body.woocommerce #page,
    body.woocommerce-page #page,
    body.woocommerce .site,
    body.woocommerce-page .site,
    body.woocommerce #content,
    body.woocommerce-page #content,
    body.woocommerce #main,
    body.woocommerce-page #main,
    body.woocommerce .site-content,
    body.woocommerce-page .site-content,
    body.woocommerce .site-main,
    body.woocommerce-page .site-main {
        background: transparent !important;
    }

    /* ── Dark overlay on the main WooCommerce content column ── */
    body.woocommerce .woocommerce,
    body.woocommerce-page .woocommerce {
        background: rgba(12, 1, 21, 0.72) !important;
        border-radius: 18px;
        padding: 1.5rem;
        backdrop-filter: blur(2px);
    }

    /* ── Hide theme site header (black bar with pink Excreet text) on all WooCommerce pages ── */
    body.woocommerce .site-header,
    body.woocommerce-page .site-header,
    body.woocommerce header.site-header,
    body.woocommerce-page header.site-header,
    body.woocommerce #masthead,
    body.woocommerce-page #masthead,
    body.woocommerce .header-main,
    body.woocommerce-page .header-main,
    body.woocommerce .site-branding,
    body.woocommerce-page .site-branding { display: none !important; }

    /* ── Hide default WP page title on shop ── */
    body.post-type-archive-product .entry-header,
    body.post-type-archive-product .page-header { display: none !important; }

    /* ── WooCommerce notices ── */
    .woocommerce-message,
    .woocommerce-info {
        background: rgba(107,47,160,0.25) !important;
        border-top-color: #C9A84C !important;
        color: #f0e8ff !important;
    }
    .woocommerce-error {
        background: rgba(160,47,47,0.25) !important;
        border-top-color: #e05555 !important;
        color: #f0e8ff !important;
    }

    /* ════════════════════════════════════════════════
       SHOP PAGE ONLY — large white tile product cards
       Scoped to body.post-type-archive-product so
       single product / cart / checkout are unaffected.
       ════════════════════════════════════════════════ */

    /* ── Force 2-column grid, large tiles ── */
    body.post-type-archive-product ul.products {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 2.2rem !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    @media (max-width: 640px) {
        body.post-type-archive-product ul.products {
            grid-template-columns: 1fr !important;
        }
    }

    /* ── Large white tile card ── */
    body.post-type-archive-product ul.products li.product {
        background: #ffffff !important;
        border: 4px solid #2a0a4a !important;
        border-radius: 4px !important;
        padding: 0 !important;
        width: 100% !important;
        float: none !important;
        margin: 0 !important;
        transition: transform 0.18s, box-shadow 0.22s !important;
        box-shadow:
            6px 6px 0 #2a0a4a,
            0 0 0 8px rgba(201,168,76,0.2),
            0 12px 40px rgba(0,0,0,0.5) !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
    }

    body.post-type-archive-product ul.products li.product:hover {
        transform: translateY(-5px) !important;
        box-shadow:
            6px 6px 0 #2a0a4a,
            0 0 0 8px rgba(201,168,76,0.5),
            0 20px 50px rgba(0,0,0,0.6) !important;
    }

    /* ── Tile image — tall and full-width ── */
    body.post-type-archive-product ul.products li.product a.woocommerce-loop-product__link {
        display: block !important;
        flex: 1 !important;
    }

    body.post-type-archive-product ul.products li.product a.woocommerce-loop-product__link img {
        border-radius: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        height: 340px !important;
        object-fit: contain !important;
        object-position: center center !important;
        display: block !important;
        background: #ffffff !important;
        padding: 12px !important;
        box-sizing: border-box !important;
        border-bottom: 4px solid #2a0a4a !important;
    }

    /* ── Tile title ── */
    body.post-type-archive-product ul.products li.product .woocommerce-loop-product__title {
        color: #1a0a2e !important;
        font-size: 1.15rem !important;
        font-weight: 800 !important;
        margin: 1.1rem 1.25rem 0.3rem !important;
        line-height: 1.3 !important;
    }

    /* ── Tile price ── */
    body.post-type-archive-product ul.products li.product .price {
        color: #6B2FA0 !important;
        font-size: 1.3rem !important;
        font-weight: 800 !important;
        display: block !important;
        margin: 0 1.25rem 0.5rem !important;
    }

    body.post-type-archive-product ul.products li.product .price del {
        color: rgba(107,47,160,0.35) !important;
        font-weight: 400 !important;
        font-size: 1rem !important;
    }

    /* ── Sale badge ── */
    body.post-type-archive-product ul.products li.product .onsale {
        background: #C9A84C !important;
        color: #1a0a2e !important;
        border-radius: 0 !important;
        font-weight: 700 !important;
        top: 0 !important;
        left: 0 !important;
    }

    /* ── Tile CTA button — full width, bold dark bar ── */
    body.post-type-archive-product ul.products li.product .button,
    body.post-type-archive-product ul.products li.product a.button {
        background: #2a0a4a !important;
        color: #C9A84C !important;
        border: none !important;
        border-top: 4px solid #2a0a4a !important;
        border-radius: 0 !important;
        padding: 1rem 1.25rem !important;
        font-size: 0.88rem !important;
        font-weight: 800 !important;
        letter-spacing: 0.1em !important;
        text-transform: uppercase !important;
        transition: background 0.15s, color 0.15s !important;
        width: 100% !important;
        text-align: center !important;
        margin: auto 0 0 !important;
        display: block !important;
    }
    body.post-type-archive-product ul.products li.product .button:hover,
    body.post-type-archive-product ul.products li.product a.button:hover {
        background: #C9A84C !important;
        color: #1a0a2e !important;
    }

    /* ── Single product page ── */
    .single-product div.product {
        background: rgba(255,255,255,0.04) !important;
        border: 1px solid rgba(201,168,76,0.18) !important;
        border-radius: 16px !important;
        padding: 2rem !important;
        box-shadow: 0 4px 32px rgba(0,0,0,0.4) !important;
    }

    .single-product .product_title {
        color: #ffffff !important;
        font-size: 1.7rem !important;
        font-weight: 700 !important;
    }

    .single-product p.price,
    .single-product span.price {
        color: #C9A84C !important;
        font-size: 1.5rem !important;
        font-weight: 700 !important;
    }

    .single-product .woocommerce-product-details__short-description,
    .single-product .woocommerce-tabs .panel p {
        color: rgba(240,232,255,0.75) !important;
        line-height: 1.7 !important;
    }

    .single-product .woocommerce-tabs ul.tabs {
        border-bottom: 1px solid rgba(201,168,76,0.25) !important;
        padding: 0 !important;
        margin-bottom: 1.5rem !important;
    }

    .single-product .woocommerce-tabs ul.tabs li {
        background: transparent !important;
        border: none !important;
    }

    .single-product .woocommerce-tabs ul.tabs li a {
        color: rgba(240,232,255,0.55) !important;
        font-size: 0.9rem !important;
        padding: 0.6rem 1.2rem !important;
    }

    .single-product .woocommerce-tabs ul.tabs li.active a {
        color: #C9A84C !important;
        border-bottom: 2px solid #C9A84C !important;
    }

    /* ── Single product buttons ── */
    .single-product .single_add_to_cart_button,
    .single-product a.button.product_type_external {
        background: linear-gradient(135deg, #C9A84C, #e8c96a) !important;
        color: #1a0a2e !important;
        border: none !important;
        border-radius: 10px !important;
        padding: 0.85rem 2rem !important;
        font-size: 1rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.04em !important;
        box-shadow: 0 4px 18px rgba(201,168,76,0.35) !important;
        transition: transform 0.15s, box-shadow 0.15s !important;
    }

    .single-product .single_add_to_cart_button:hover,
    .single-product a.button.product_type_external:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 28px rgba(201,168,76,0.5) !important;
    }

    /* ── Quantity field ── */
    .single-product .quantity input[type="number"] {
        background: rgba(255,255,255,0.06) !important;
        border: 1px solid rgba(201,168,76,0.3) !important;
        border-radius: 8px !important;
        color: #ffffff !important;
        padding: 0.55rem 0.75rem !important;
    }

    /* ── Cart page ── */
    .woocommerce-cart .woocommerce {
        background: rgba(255,255,255,0.03) !important;
        border-radius: 16px !important;
        padding: 1.5rem !important;
    }

    table.shop_table {
        background: transparent !important;
        border: none !important;
    }

    table.shop_table thead tr th {
        background: rgba(107,47,160,0.25) !important;
        color: #C9A84C !important;
        font-size: 0.8rem !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
        border: none !important;
        padding: 0.75rem 1rem !important;
    }

    table.shop_table tbody tr td {
        background: rgba(255,255,255,0.03) !important;
        border-bottom: 1px solid rgba(255,255,255,0.06) !important;
        color: #f0e8ff !important;
        padding: 1rem !important;
        vertical-align: middle !important;
    }

    table.shop_table .product-name a { color: #ffffff !important; font-weight: 600 !important; }
    table.shop_table .product-price,
    table.shop_table .product-subtotal { color: #C9A84C !important; font-weight: 700 !important; }

    /* ── Cart totals ── */
    .cart_totals {
        background: rgba(255,255,255,0.04) !important;
        border: 1px solid rgba(201,168,76,0.2) !important;
        border-radius: 14px !important;
        padding: 1.5rem !important;
    }

    .cart_totals h2 {
        color: #ffffff !important;
        font-size: 1.1rem !important;
        margin-bottom: 1rem !important;
    }

    .cart_totals table.shop_table th { color: rgba(240,232,255,0.6) !important; }
    .cart_totals table.shop_table td { color: #C9A84C !important; font-weight: 700 !important; }

    .cart_totals .wc-proceed-to-checkout a.checkout-button {
        background: linear-gradient(135deg, #C9A84C, #e8c96a) !important;
        color: #1a0a2e !important;
        border: none !important;
        border-radius: 10px !important;
        font-size: 1rem !important;
        font-weight: 700 !important;
        padding: 0.9rem 2rem !important;
        display: block !important;
        text-align: center !important;
        box-shadow: 0 4px 18px rgba(201,168,76,0.35) !important;
        transition: transform 0.15s !important;
    }
    .cart_totals .wc-proceed-to-checkout a.checkout-button:hover {
        transform: translateY(-2px) !important;
    }

    /* ── Checkout ── */
    .woocommerce-checkout #order_review_heading,
    .woocommerce-checkout h3 {
        color: #C9A84C !important;
        font-size: 1rem !important;
        letter-spacing: 0.05em !important;
        text-transform: uppercase !important;
        border-bottom: 1px solid rgba(201,168,76,0.2) !important;
        padding-bottom: 0.5rem !important;
        margin-bottom: 1.25rem !important;
    }

    .woocommerce-checkout .woocommerce-billing-fields,
    .woocommerce-checkout .woocommerce-shipping-fields,
    #order_review {
        background: rgba(255,255,255,0.04) !important;
        border: 1px solid rgba(201,168,76,0.18) !important;
        border-radius: 14px !important;
        padding: 1.5rem !important;
        margin-bottom: 1.5rem !important;
    }

    .woocommerce-checkout .form-row label {
        color: rgba(240,232,255,0.7) !important;
        font-size: 0.82rem !important;
        margin-bottom: 0.3rem !important;
    }

    .woocommerce-checkout .form-row input,
    .woocommerce-checkout .form-row select,
    .woocommerce-checkout .form-row textarea {
        background: rgba(255,255,255,0.06) !important;
        border: 1px solid rgba(201,168,76,0.25) !important;
        border-radius: 8px !important;
        color: #ffffff !important;
        padding: 0.65rem 0.85rem !important;
    }

    .woocommerce-checkout .form-row input:focus,
    .woocommerce-checkout .form-row select:focus {
        border-color: #C9A84C !important;
        outline: none !important;
        box-shadow: 0 0 0 2px rgba(201,168,76,0.15) !important;
    }

    #place_order {
        background: linear-gradient(135deg, #C9A84C, #e8c96a) !important;
        color: #1a0a2e !important;
        border: none !important;
        border-radius: 10px !important;
        font-size: 1.05rem !important;
        font-weight: 700 !important;
        padding: 1rem 2rem !important;
        width: 100% !important;
        box-shadow: 0 4px 18px rgba(201,168,76,0.35) !important;
        transition: transform 0.15s, box-shadow 0.15s !important;
    }
    #place_order:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 28px rgba(201,168,76,0.5) !important;
    }

    /* ── Order review table (checkout) ── */
    #order_review table.shop_table tfoot tr.order-total td {
        color: #C9A84C !important;
        font-size: 1.1rem !important;
        font-weight: 700 !important;
    }

    /* ── My Account (WooCommerce) ── */
    .woocommerce-account .woocommerce-MyAccount-navigation {
        background: rgba(255,255,255,0.04) !important;
        border: 1px solid rgba(201,168,76,0.15) !important;
        border-radius: 12px !important;
        padding: 1rem 0 !important;
        margin-bottom: 1.5rem !important;
    }

    .woocommerce-account .woocommerce-MyAccount-navigation ul { margin: 0 !important; padding: 0 !important; list-style: none !important; }

    .woocommerce-account .woocommerce-MyAccount-navigation ul li a {
        display: block !important;
        color: rgba(240,232,255,0.65) !important;
        padding: 0.6rem 1.25rem !important;
        font-size: 0.88rem !important;
        border-left: 3px solid transparent !important;
        transition: color 0.15s, border-color 0.15s, background 0.15s !important;
    }

    .woocommerce-account .woocommerce-MyAccount-navigation ul li.is-active a,
    .woocommerce-account .woocommerce-MyAccount-navigation ul li a:hover {
        color: #C9A84C !important;
        border-left-color: #C9A84C !important;
        background: rgba(201,168,76,0.07) !important;
    }

    .woocommerce-account .woocommerce-MyAccount-content {
        background: rgba(255,255,255,0.04) !important;
        border: 1px solid rgba(201,168,76,0.15) !important;
        border-radius: 12px !important;
        padding: 1.5rem !important;
    }

    .woocommerce-account .woocommerce-MyAccount-content p { color: rgba(240,232,255,0.75) !important; }
    .woocommerce-account .woocommerce-MyAccount-content a { color: #C9A84C !important; }

    /* ── Headings inside account content ── */
    .woocommerce-account h2,
    .woocommerce-account h3 {
        color: #ffffff !important;
        border-bottom: 1px solid rgba(201,168,76,0.15) !important;
        padding-bottom: 0.4rem !important;
        margin-bottom: 1rem !important;
    }

    /* ── Order status pills ── */
    .woocommerce-order-status { border-radius: 20px !important; padding: 0.25rem 0.7rem !important; font-size: 0.75rem !important; font-weight: 600 !important; }
    .woocommerce-order-status.status-processing { background: rgba(107,47,160,0.4) !important; color: #c4a0f0 !important; }
    .woocommerce-order-status.status-completed  { background: rgba(201,168,76,0.25) !important; color: #C9A84C !important; }
    .woocommerce-order-status.status-on-hold    { background: rgba(255,200,80,0.15) !important; color: #f5c842 !important; }
    .woocommerce-order-status.status-cancelled  { background: rgba(200,50,50,0.2) !important; color: #e08080 !important; }

    /* ── General WooCommerce links & helpers ── */
    .woocommerce a.button,
    .woocommerce button.button {
        background: rgba(201,168,76,0.15) !important;
        color: #C9A84C !important;
        border: 1px solid rgba(201,168,76,0.4) !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        transition: background 0.15s, color 0.15s !important;
    }
    .woocommerce a.button:hover,
    .woocommerce button.button:hover {
        background: #C9A84C !important;
        color: #1a0a2e !important;
    }

    /* ── Breadcrumbs ── */
    .woocommerce-breadcrumb {
        color: rgba(240,232,255,0.4) !important;
        font-size: 0.78rem !important;
        margin-bottom: 1.5rem !important;
    }
    .woocommerce-breadcrumb a { color: rgba(201,168,76,0.65) !important; }
    .woocommerce-breadcrumb a:hover { color: #C9A84C !important; }

    /* ── Result count ── */
    .woocommerce-result-count { color: rgba(240,232,255,0.45) !important; font-size: 0.8rem !important; }
    .woocommerce-ordering select {
        background: rgba(255,255,255,0.06) !important;
        border: 1px solid rgba(201,168,76,0.25) !important;
        border-radius: 8px !important;
        color: #f0e8ff !important;
        padding: 0.4rem 0.75rem !important;
    }

    /* ── Affiliate product badge ── */
    .ex303-affiliate-badge {
        display: inline-block;
        background: rgba(107,47,160,0.35);
        border: 1px solid rgba(107,47,160,0.6);
        color: #c4a0f0;
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 0.2rem 0.55rem;
        border-radius: 4px;
        margin-bottom: 0.4rem;
    }

    </style>
    <?php
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  C — SHOP PAGE HERO                                                          */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'woocommerce_before_main_content', 'excreet_303_shop_hero', 5 );

function excreet_303_shop_hero(): void {
    if ( ! is_shop() ) { return; }
    ?>
    <div class="ex303-shop-hero">
        <div class="ex303-shop-hero-inner">
            <div class="ex303-shop-wordmark">Excreet</div>
            <h1 class="ex303-shop-title">The Excreet Store</h1>
            <p class="ex303-shop-sub">
                Carefully selected products to support your healing journey —<br>
                from our signature formula to trusted partner brands.
            </p>
        </div>
    </div>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Anton&display=swap');
    .ex303-shop-hero {
        background: linear-gradient(135deg, rgba(107,47,160,0.35) 0%, rgba(61,16,96,0.45) 100%);
        border: 1px solid rgba(201,168,76,0.2);
        border-radius: 16px;
        padding: 0.6rem 2rem 0.5rem;
        margin-bottom: 1rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .ex303-shop-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse at 50% 0%, rgba(201,168,76,0.08) 0%, transparent 70%);
        pointer-events: none;
    }
    .ex303-shop-hero-inner { position: relative; z-index: 1; }
    .ex303-shop-wordmark { display: none; }
    .ex303-shop-title {
        font-family: 'Anton', 'Impact', sans-serif;
        font-size: clamp(1.4rem, 4.5vw, 2.75rem);
        font-weight: 400;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        background: none;
        -webkit-background-clip: unset;
        background-clip: unset;
        color: #ffffff;
        -webkit-text-fill-color: #ffffff;
        -webkit-text-stroke: 0;
        /* Outer gold border via multi-angle shadows — keeps white face clean */
        text-shadow:
            -3px -3px 0 #56075E,  0px -3px 0 #56075E,  3px -3px 0 #56075E,
            -3px  0px 0 #56075E,                         3px  0px 0 #56075E,
            -3px  3px 0 #56075E,  0px  3px 0 #56075E,  3px  3px 0 #56075E,
            -2px -2px 0 #56075E,  2px -2px 0 #56075E,
            -2px  2px 0 #56075E,  2px  2px 0 #56075E,
            /* purple glow */
            0 0 12px rgba(86,7,94,0.55),
            /* 3D lift shadow */
            4px 7px 0   #2d003a,
            8px 14px 0  rgba(40,0,50,0.45),
            10px 18px 20px rgba(0,0,0,0.65);
        filter: none;
        margin: 0 0 1rem;
        line-height: 1.1;
    }
    .ex303-shop-sub {
        font-size: 0.95rem;
        color: #ffffff;
        font-weight: 700;
        line-height: 1.7;
        max-width: 520px;
        margin: 0 auto;
        text-shadow: 0 1px 8px rgba(0,0,0,0.7);
    }
    </style>
    <?php
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  D — AFFILIATE BADGE ON EXTERNAL PRODUCTS                                   */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'woocommerce_before_shop_loop_item_title', 'excreet_303_affiliate_badge', 15 );

function excreet_303_affiliate_badge(): void {
    global $product;
    if ( ! $product ) { return; }
    if ( $product->get_type() === 'external' ) {
        echo '<div class="ex303-affiliate-badge">Partner Product</div>';
    }
}

/* ── Relabel the external product button ── */
add_filter( 'woocommerce_product_single_add_to_cart_text', 'excreet_303_external_btn_label', 10, 1 );
add_filter( 'woocommerce_product_add_to_cart_text',        'excreet_303_external_btn_label', 10, 1 );

function excreet_303_external_btn_label( string $label ): string {
    global $product;
    if ( $product && $product->get_type() === 'external' ) {
        return 'Shop Partner →';
    }
    return $label;
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  E — SECTION DIVIDERS ON SHOP PAGE                                          */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'woocommerce_before_shop_loop', 'excreet_303_shop_section_header', 5 );

function excreet_303_shop_section_header(): void {
    if ( ! is_shop() ) { return; }
    ?>
    <div class="ex303-section-label">All Products</div>
    <style>
    .ex303-section-label {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: rgba(201,168,76,0.65);
        border-bottom: 1px solid rgba(201,168,76,0.15);
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
    }
    </style>
    <?php
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  F — ORDER CONFIRMATION PAGE STYLING                                         */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_head', 'excreet_303_thankyou_styles', 99 );

function excreet_303_thankyou_styles(): void {
    if ( ! function_exists( 'is_order_received_page' ) ) { return; }
    if ( ! is_order_received_page() ) { return; }
    ?>
    <style id="ex303-thankyou-styles">
    .woocommerce-order-received h2,
    .woocommerce-order-received h3 { color: #C9A84C !important; }

    .woocommerce-order-received .woocommerce-order {
        background: rgba(255,255,255,0.04) !important;
        border: 1px solid rgba(201,168,76,0.2) !important;
        border-radius: 16px !important;
        padding: 2rem !important;
    }

    .woocommerce-order-received ul.woocommerce-order-overview {
        background: rgba(107,47,160,0.2) !important;
        border: 1px solid rgba(107,47,160,0.35) !important;
        border-radius: 12px !important;
        padding: 1rem 1.5rem !important;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        list-style: none !important;
        margin-bottom: 1.5rem !important;
    }

    .woocommerce-order-received ul.woocommerce-order-overview li {
        color: rgba(240,232,255,0.6) !important;
        font-size: 0.82rem !important;
    }

    .woocommerce-order-received ul.woocommerce-order-overview li strong {
        display: block;
        color: #C9A84C !important;
        font-size: 1rem !important;
    }

    .woocommerce-order-received .woocommerce-notice--success {
        background: rgba(201,168,76,0.12) !important;
        border-left: 4px solid #C9A84C !important;
        border-radius: 10px !important;
        color: #f0e8ff !important;
        padding: 1rem 1.5rem !important;
        margin-bottom: 1.5rem !important;
    }
    </style>
    <?php
}
