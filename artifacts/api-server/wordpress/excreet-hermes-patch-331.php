<?php
/**
 * Plugin Name: Excreet Patch 331 — Global Brand Stylesheet
 * Description: Comprehensive Excreet brand treatment for ALL WordPress pages.
 *              Covers: navigation, typography, content cards, Elementor sections,
 *              PMPro pages (membership-levels, checkout, account), WP blocks,
 *              forms (inputs, selects, textareas), buttons, admin bar, scrollbars,
 *              tables, notices, and footer.
 *
 *              Pairs with patch-309 (which sets the bathroom background image).
 *              Fires at priority 100 so dedicated patches (297, 303, 296, 298,
 *              299, 310, 311, 312) still win on their specific pages.
 *
 * Version: 3.3.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_head', 'excreet_331_global_brand', 100 );

function excreet_331_global_brand(): void {
    if ( is_admin() || is_feed() ) { return; }

    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url = esc_url( 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg' );
    ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&display=swap" rel="stylesheet">
<style id="ex331-global-brand">

/* ═══════════════════════════════════════════════════════════════════════════
   EXCREET GLOBAL BRAND — v3.3.1
   Palette:
     Dark bg  : #0c0115
     Purple   : #6B21A8  /  #3D1060
     Gold     : #C9A84C  /  #F5D97A
     Text     : #f0e8ff
     Muted    : rgba(240,232,255,.65)
     Glass bg : rgba(12,2,26,.76)
     Glass bdr: rgba(201,168,76,.22)
═══════════════════════════════════════════════════════════════════════════ */

/* ── 1. BASE RESET & FONT ─────────────────────────────────────────────── */
html, body {
    font-family: 'Poppins', sans-serif !important;
    color: #f0e8ff !important;
    background-color: #0c0115 !important;
}

/* ── 2. FULL-PAGE SCRIM (sits over bg, under content) ─────────────────── */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background:
        linear-gradient(to bottom,
            rgba(12,1,21,.70) 0%,
            rgba(12,1,21,.10) 30%,
            rgba(12,1,21,.10) 68%,
            rgba(12,1,21,.72) 100%),
        linear-gradient(to right,
            rgba(12,1,21,.42) 0%,
            transparent 50%);
}

/* ── 3. TYPOGRAPHY ────────────────────────────────────────────────────── */
h1, h2, h3, h4, h5, h6,
.entry-title,
.page-title,
.wp-block-heading {
    font-family: 'Poppins', sans-serif !important;
    color: #C9A84C !important;
    text-shadow: 0 0 18px rgba(201,168,76,.25), 0 2px 6px rgba(0,0,0,.8) !important;
}
h1 { font-size: clamp(1.6rem, 3.5vw, 2.6rem) !important; font-weight: 700 !important; }
h2 { font-size: clamp(1.3rem, 2.8vw, 2rem) !important;   font-weight: 600 !important; }
h3 { font-size: clamp(1.1rem, 2.2vw, 1.5rem) !important; font-weight: 600 !important; }

p, li, td, th, label, span, div {
    font-family: 'Poppins', sans-serif !important;
}
p, li {
    color: #f0e8ff !important;
    line-height: 1.72 !important;
}

a {
    color: #C9A84C !important;
    text-decoration: none !important;
    transition: color .2s, opacity .2s !important;
}
a:hover { color: #F5D97A !important; opacity: .88 !important; }

strong, b { color: #fff !important; }
em, i     { color: rgba(240,232,255,.78) !important; }

/* ── PMPro checkout: dynamic level/price values sit on a light card ─── */
/* Override the global strong/b rule so they're readable, not invisible  */
#pmpro_form strong,
#pmpro_form b,
.pmpro_content_message strong,
.pmpro_content_message b,
.pmpro_level_cost strong,
.pmpro_level_cost b {
    color: #56075E !important;  /* matches the purple headings on the card */
    font-weight: 700 !important;
}

/* ── 4. ADMIN BAR ─────────────────────────────────────────────────────── */
#wpadminbar,
#wpadminbar * {
    background: #0c0115 !important;
    color: #C9A84C !important;
    font-family: 'Poppins', sans-serif !important;
}
#wpadminbar a, #wpadminbar a:hover { color: #F5D97A !important; }

/* ── 5. SITE HEADER & NAVIGATION ─────────────────────────────────────── */
.site-header,
.elementor-location-header,
header.site-header,
#site-header,
#masthead {
    background: rgba(10,2,20,.88) !important;
    backdrop-filter: blur(14px) !important;
    -webkit-backdrop-filter: blur(14px) !important;
    border-bottom: 1px solid rgba(201,168,76,.18) !important;
    position: relative !important;
    z-index: 100 !important;
}

/* Site title / logo text */
.site-title,
.site-title a,
.site-branding .site-title a {
    color: #C9A84C !important;
    font-family: 'Poppins', sans-serif !important;
    font-weight: 700 !important;
    letter-spacing: .12em !important;
    text-decoration: none !important;
}
.site-description { color: rgba(201,168,76,.6) !important; font-size: .75rem !important; }

/* Primary navigation menu */
.main-navigation,
.nav-primary,
#site-navigation,
nav.site-navigation {
    background: transparent !important;
}

/* Menu items */
.main-navigation ul li a,
.nav-primary ul li a,
#site-navigation ul li a,
.menu-item a,
nav ul li a {
    font-family: 'Poppins', sans-serif !important;
    font-size: .88rem !important;
    font-weight: 500 !important;
    color: rgba(240,232,255,.88) !important;
    padding: 6px 16px !important;
    border-radius: 20px !important;
    transition: background .2s, color .2s !important;
    text-decoration: none !important;
    white-space: nowrap !important;
}
.main-navigation ul li a:hover,
.nav-primary ul li a:hover,
#site-navigation ul li a:hover,
.menu-item a:hover,
nav ul li a:hover {
    background: rgba(201,168,76,.18) !important;
    color: #F5D97A !important;
}
.main-navigation ul li.current-menu-item > a,
.menu-item.current-menu-item > a,
.menu-item.current_page_item > a {
    background: rgba(107,33,168,.35) !important;
    color: #F5D97A !important;
    border: 1px solid rgba(201,168,76,.4) !important;
}

/* Dropdown sub-menus */
.main-navigation ul ul,
nav ul ul {
    background: rgba(12,1,21,.95) !important;
    border: 1px solid rgba(201,168,76,.25) !important;
    border-radius: 10px !important;
    padding: 6px 0 !important;
    box-shadow: 0 12px 32px rgba(0,0,0,.7) !important;
}

/* Mobile hamburger */
.menu-toggle,
button.menu-toggle {
    background: rgba(107,33,168,.5) !important;
    color: #F5D97A !important;
    border: 1px solid rgba(201,168,76,.4) !important;
    border-radius: 8px !important;
    font-family: 'Poppins', sans-serif !important;
}

/* Elementor header nav widget */
.elementor-nav-menu a,
.elementor-nav-menu--main .elementor-item {
    color: rgba(240,232,255,.88) !important;
    font-family: 'Poppins', sans-serif !important;
}
.elementor-nav-menu--main .elementor-item:hover,
.elementor-nav-menu--main .elementor-item.elementor-item-active {
    color: #F5D97A !important;
}

/* ── 6. CONTENT AREA — transparent containers ─────────────────────────── */
#page,
.site,
.site-content,
#content,
#main,
.site-main,
.wp-site-blocks {
    background: transparent !important;
    position: relative !important;
    z-index: 1 !important;
}

/* Elementor outer wrappers */
.elementor-section-wrap,
.e-container,
.e-con,
.elementor-container,
.elementor-section,
.elementor-column,
.elementor-widget-wrap,
.elementor-element,
article.page,
article.post {
    background: transparent !important;
}

/* WP block groups / covers that add white bg */
.wp-block-group,
.wp-block-column,
.wp-block-columns,
.wp-block-cover {
    background: transparent !important;
}

/* ── 7. CONTENT CARDS — dark glass panels ─────────────────────────────── */
.entry-content,
.post-content,
.page-content {
    background: rgba(12,2,26,.72) !important;
    border: 1px solid rgba(201,168,76,.15) !important;
    border-radius: 16px !important;
    padding: 2rem 2.4rem !important;
    backdrop-filter: blur(10px) !important;
    -webkit-backdrop-filter: blur(10px) !important;
    color: #f0e8ff !important;
    position: relative !important;
    z-index: 2 !important;
}

/* Don't double-card shortcode wrappers inside entry-content */
.entry-content .ex-card,
.entry-content [class*="ex2"] {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
}

/* ── 8. GLOBAL BUTTONS ────────────────────────────────────────────────── */
button:not(#wp-admin-bar-root-default *):not(.menu-toggle),
input[type="submit"],
input[type="button"],
a.button,
.button,
.wp-block-button__link,
.btn {
    font-family: 'Poppins', sans-serif !important;
    font-weight: 600 !important;
    border-radius: 26px !important;
    padding: 10px 26px !important;
    cursor: pointer !important;
    transition: opacity .2s, transform .15s !important;
    text-decoration: none !important;
    display: inline-block !important;
    background: linear-gradient(90deg,#7B24B8,#A80CA0) !important;
    color: #fff !important;
    border: 2px solid rgba(255,255,255,.5) !important;
    letter-spacing: .04em !important;
    font-size: .88rem !important;
}
button:hover:not(#wp-admin-bar-root-default *):not(.menu-toggle),
input[type="submit"]:hover,
input[type="button"]:hover,
a.button:hover,
.button:hover,
.wp-block-button__link:hover {
    opacity: .88 !important;
    transform: translateY(-2px) !important;
}

/* Gold CTA variant */
a.button.gold, .button.gold,
.ex-btn-gold,
.pmpro_checkout input[type="submit"],
.pmpro-checkout-btn {
    background: linear-gradient(90deg,#B8730A,#C9A84C) !important;
    color: #fff !important;
    border-color: rgba(255,255,255,.6) !important;
}

/* ── 9. FORMS — dark glass inputs ─────────────────────────────────────── */
input[type="text"],
input[type="email"],
input[type="password"],
input[type="number"],
input[type="tel"],
input[type="url"],
input[type="search"],
select,
textarea {
    font-family: 'Poppins', sans-serif !important;
    background: rgba(255,255,255,.07) !important;
    border: 1px solid rgba(201,168,76,.30) !important;
    border-radius: 9px !important;
    color: #ffffff !important;
    padding: .62rem 1rem !important;
    font-size: .9rem !important;
    width: 100% !important;
    transition: border-color .2s !important;
    outline: none !important;
}
input[type="text"]:focus,
input[type="email"]:focus,
input[type="password"]:focus,
input[type="number"]:focus,
select:focus,
textarea:focus {
    border-color: #C9A84C !important;
    background: rgba(255,255,255,.11) !important;
    box-shadow: 0 0 0 3px rgba(201,168,76,.15) !important;
}
input::placeholder,
textarea::placeholder { color: rgba(255,255,255,.38) !important; }
select option { background: #1a0535 !important; color: #f0e8ff !important; }

label {
    color: rgba(240,232,255,.78) !important;
    font-size: .82rem !important;
    font-weight: 500 !important;
    letter-spacing: .06em !important;
    text-transform: uppercase !important;
    margin-bottom: .35rem !important;
    display: block !important;
}

/* ── 10. TABLES ───────────────────────────────────────────────────────── */
table {
    border-collapse: collapse !important;
    width: 100% !important;
    background: rgba(12,2,26,.65) !important;
    border: 1px solid rgba(201,168,76,.2) !important;
    border-radius: 10px !important;
}
th {
    background: rgba(107,33,168,.45) !important;
    color: #F5D97A !important;
    padding: .65rem 1rem !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: .8rem !important;
    letter-spacing: .08em !important;
    text-transform: uppercase !important;
    border-bottom: 1px solid rgba(201,168,76,.3) !important;
}
td {
    color: #f0e8ff !important;
    padding: .55rem 1rem !important;
    border-bottom: 1px solid rgba(201,168,76,.09) !important;
    font-size: .88rem !important;
    vertical-align: top !important;
}
tr:hover td { background: rgba(107,33,168,.12) !important; }

/* ── 11. PMPro PAGES ──────────────────────────────────────────────────── */

/* Membership Levels page */
.pmpro_levels_short,
.pmpro_level,
.pmpro-levels-page .pmpro_content,
#pmpro_levels_table,
.pmpro_box {
    background: rgba(12,2,26,.78) !important;
    border: 1px solid rgba(201,168,76,.22) !important;
    border-radius: 16px !important;
    color: #f0e8ff !important;
    backdrop-filter: blur(10px) !important;
    -webkit-backdrop-filter: blur(10px) !important;
}

.pmpro_level h2,
.pmpro_level .pmpro_level_name,
.pmpro_box h2 {
    color: #C9A84C !important;
    font-family: 'Poppins', sans-serif !important;
}
.pmpro_level .pmpro_level_cost,
.pmpro_box .pmpro_price {
    color: #F5D97A !important;
    font-size: 1.6rem !important;
    font-weight: 700 !important;
}
.pmpro_level ul li,
.pmpro_box ul li { color: rgba(240,232,255,.88) !important; }

/* PMPro Checkout */
.pmpro_checkout,
#pmpro_checkout,
.pmpro_checkout_section,
.pmpro_checkout_section .pmpro_checkout_section_inner {
    background: rgba(12,2,26,.8) !important;
    border: 1px solid rgba(201,168,76,.22) !important;
    border-radius: 14px !important;
    padding: 1.6rem !important;
    color: #f0e8ff !important;
}
.pmpro_checkout h2,
#pmpro_checkout h2 {
    color: #C9A84C !important;
    font-family: 'Poppins', sans-serif !important;
}
.pmpro_btn-submit-checkout,
input#pmpro_submit_btn {
    background: linear-gradient(90deg,#B8730A,#C9A84C) !important;
    color: #fff !important;
    border: 2px solid rgba(255,255,255,.6) !important;
    border-radius: 26px !important;
    font-family: 'Poppins', sans-serif !important;
    font-weight: 700 !important;
    font-size: 1rem !important;
    padding: 12px 32px !important;
    cursor: pointer !important;
    width: auto !important;
}

/* PMPro Account / My Account */
.pmpro_account,
#pmpro_account,
.pmpro_account_section,
.pmpro_account .pmpro_account_section_inner {
    background: rgba(12,2,26,.76) !important;
    border: 1px solid rgba(201,168,76,.18) !important;
    border-radius: 14px !important;
    padding: 1.4rem !important;
    color: #f0e8ff !important;
    margin-bottom: 1.4rem !important;
}
.pmpro_account h2,
.pmpro_account h3 {
    color: #C9A84C !important;
    font-family: 'Poppins', sans-serif !important;
}
.pmpro_account a { color: #C9A84C !important; }
.pmpro_account a:hover { color: #F5D97A !important; }

/* PMPro Member Login form */
body.pmpro_login .login-form,
body.pmpro_login #loginform,
body.pmpro_login .pmpro_form {
    background: rgba(12,2,26,.82) !important;
    border: 1px solid rgba(201,168,76,.3) !important;
    border-radius: 16px !important;
    padding: 2.4rem 2rem !important;
    backdrop-filter: blur(14px) !important;
    -webkit-backdrop-filter: blur(14px) !important;
}

/* PMPro general notices */
.pmpro_message,
.pmpro_error,
.pmpro_success {
    border-radius: 10px !important;
    padding: 1rem 1.2rem !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: .88rem !important;
}
.pmpro_error  { background: rgba(127,29,29,.55) !important; border: 1px solid #f87171 !important; color: #fca5a5 !important; }
.pmpro_success{ background: rgba(22,101,52,.55) !important; border: 1px solid #4ade80 !important; color: #86efac !important; }
.pmpro_message{ background: rgba(107,33,168,.35) !important; border: 1px solid rgba(201,168,76,.4) !important; color: #f0e8ff !important; }

/* ── 12. WP NOTICES / ALERTS ──────────────────────────────────────────── */
.notice,
.updated,
.error,
div.update-nag,
.wp-die-message,
.wpcf7-response-output {
    font-family: 'Poppins', sans-serif !important;
    border-radius: 10px !important;
    padding: .85rem 1.1rem !important;
    background: rgba(107,33,168,.3) !important;
    border: 1px solid rgba(201,168,76,.3) !important;
    color: #f0e8ff !important;
}

/* ── 13. SITE FOOTER ──────────────────────────────────────────────────── */
.site-footer,
.elementor-location-footer,
footer.site-footer,
#colophon {
    background: rgba(8,1,16,.94) !important;
    border-top: 1px solid rgba(201,168,76,.18) !important;
    color: rgba(240,232,255,.55) !important;
    padding: 2rem 1.5rem !important;
    position: relative !important;
    z-index: 2 !important;
}
.site-footer *,
.elementor-location-footer *,
#colophon * {
    color: rgba(240,232,255,.55) !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: .82rem !important;
}
.site-footer a,
.elementor-location-footer a,
#colophon a {
    color: rgba(201,168,76,.7) !important;
}
.site-footer a:hover,
#colophon a:hover { color: #C9A84C !important; }

/* ── 14. SCROLLBAR ────────────────────────────────────────────────────── */
::-webkit-scrollbar { width: 7px; height: 7px; }
::-webkit-scrollbar-track { background: rgba(12,1,21,.8); }
::-webkit-scrollbar-thumb {
    background: linear-gradient(to bottom, #6B21A8, #C9A84C);
    border-radius: 8px;
}
::-webkit-scrollbar-thumb:hover { background: #C9A84C; }

/* ── 15. TEXT SELECTION ───────────────────────────────────────────────── */
::selection     { background: rgba(201,168,76,.35); color: #fff; }
::-moz-selection{ background: rgba(201,168,76,.35); color: #fff; }

/* ── 16. IMAGES ───────────────────────────────────────────────────────── */
img.attachment-thumbnail,
.wp-post-image,
.attachment-post-thumbnail {
    border-radius: 10px !important;
    border: 1px solid rgba(201,168,76,.22) !important;
}

/* ── 17. ELEMENTOR WIDGET TEXT OVERRIDES ──────────────────────────────── */
.elementor-widget-text-editor p,
.elementor-widget-text-editor li,
.elementor-widget-text-editor span {
    color: #f0e8ff !important;
}
.elementor-widget-heading .elementor-heading-title {
    color: #C9A84C !important;
    font-family: 'Poppins', sans-serif !important;
}
.elementor-widget-icon-list .elementor-icon-list-text,
.elementor-icon-list-item { color: #f0e8ff !important; }

/* ── 18. BREADCRUMBS ──────────────────────────────────────────────────── */
.breadcrumb,
.breadcrumbs,
.woocommerce-breadcrumb,
nav.breadcrumb {
    color: rgba(240,232,255,.55) !important;
    font-size: .78rem !important;
}
.breadcrumb a,
.breadcrumbs a,
.woocommerce-breadcrumb a { color: rgba(201,168,76,.7) !important; }

/* ── 19. PAGINATION ───────────────────────────────────────────────────── */
.page-numbers,
.pagination a,
.nav-links a {
    background: rgba(107,33,168,.3) !important;
    color: #f0e8ff !important;
    border: 1px solid rgba(201,168,76,.25) !important;
    border-radius: 8px !important;
    padding: 5px 12px !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: .82rem !important;
}
.page-numbers.current,
.pagination a.current { background: rgba(201,168,76,.3) !important; color: #F5D97A !important; }

/* ── 20. HORIZONTAL RULES ─────────────────────────────────────────────── */
hr,
.wp-block-separator { border-color: rgba(201,168,76,.25) !important; }

/* ── 21. BLOCKQUOTES ──────────────────────────────────────────────────── */
blockquote,
.wp-block-quote {
    border-left: 3px solid #C9A84C !important;
    padding-left: 1.2rem !important;
    color: rgba(240,232,255,.75) !important;
    font-style: italic !important;
    background: rgba(107,33,168,.12) !important;
    border-radius: 0 8px 8px 0 !important;
}

/* ── 22. CODE BLOCKS ──────────────────────────────────────────────────── */
code, pre {
    background: rgba(12,2,26,.85) !important;
    border: 1px solid rgba(201,168,76,.2) !important;
    color: #C9A84C !important;
    border-radius: 6px !important;
    font-size: .82rem !important;
    padding: .15em .45em !important;
}
pre { padding: 1rem !important; overflow-x: auto !important; }

/* ── 23. MOBILE NAV ───────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .main-navigation ul.nav-menu,
    .main-navigation ul.menu {
        background: rgba(10,2,20,.96) !important;
        border: 1px solid rgba(201,168,76,.2) !important;
        border-radius: 12px !important;
        padding: 1rem !important;
    }
    .entry-content {
        padding: 1.2rem 1rem !important;
    }
    .pmpro_checkout,
    .pmpro_account {
        padding: 1rem !important;
    }
}

/* ── 24. PRINT OVERRIDE (don't bleed dark bg onto paper) ─────────────── */
@media print {
    html, body { background: #fff !important; color: #000 !important; }
    body::before { display: none !important; }
}

</style>
<?php
}

/* ─────────────────────────────────────────────────────────────────────────── */
/* Hide WP admin bar for non-admins to keep pages clean                        */
/* ─────────────────────────────────────────────────────────────────────────── */
add_action( 'after_setup_theme', 'excreet_331_admin_bar', 1 );

function excreet_331_admin_bar(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        show_admin_bar( false );
    }
}

/* ─────────────────────────────────────────────────────────────────────────── */
/* Inject Poppins into WP login page head (covers /wp-login.php)               */
/* ─────────────────────────────────────────────────────────────────────────── */
add_action( 'login_head', 'excreet_331_login_font' );

function excreet_331_login_font(): void {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body.login { font-family: "Poppins",sans-serif !important; background: #0c0115 url("https://excreet.com/wp-content/uploads/healer-bg-' . str_pad((int)date('n'),2,'0',STR_PAD_LEFT) . '.jpg") center/cover no-repeat fixed !important; }
body.login::before { content:""; position:fixed; inset:0; background:rgba(12,1,21,.72); z-index:0; }
#login { position:relative; z-index:1; }
#loginform, .login form { background:rgba(12,2,26,.82) !important; border:1px solid rgba(201,168,76,.3) !important; border-radius:16px !important; padding:2rem !important; }
#login h1 a { filter: brightness(1.2); }
label { color:rgba(240,232,255,.75) !important; font-family:"Poppins",sans-serif !important; font-size:.82rem !important; letter-spacing:.05em !important; }
input[type="text"], input[type="password"] { background:rgba(255,255,255,.08) !important; border:1px solid rgba(201,168,76,.3) !important; border-radius:9px !important; color:#fff !important; font-family:"Poppins",sans-serif !important; }
input[type="submit"].button-primary { background:linear-gradient(90deg,#B8730A,#C9A84C) !important; border:none !important; border-radius:26px !important; color:#fff !important; font-family:"Poppins",sans-serif !important; font-weight:700 !important; padding:10px 28px !important; }
.login a { color:#C9A84C !important; }
.login a:hover { color:#F5D97A !important; }
#backtoblog a, #nav a { color:rgba(201,168,76,.7) !important; }
</style>' . "\n";
}
