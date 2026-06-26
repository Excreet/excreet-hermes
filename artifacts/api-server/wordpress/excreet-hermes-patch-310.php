<?php
/**
 * Plugin Name: Excreet Patch 310 — Explore Page Layout Fix
 * Description: Fixes /explore/ (page ID 26) layout after the bathroom background
 *              was applied globally by patch-309. Elementor sections had solid
 *              dark backgrounds that completely hide the background image.
 *              This patch makes those containers semi-transparent so the
 *              bathroom scene breathes through, matching the homepage aesthetic.
 * Version: 3.1.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── SERVER-SIDE HTML rewrite via output buffer (runs before any JS/CSS) ──
add_action( 'template_redirect', function() {
    if ( (int) get_queried_object_id() !== 26 ) {
        return;
    }
    $logo_img = '<img src="https://excreet.com/wp-content/uploads/2026/05/excreet-hero-logo.png"'
        . ' alt="Excreet"'
        . ' style="width:48px;height:48px;object-fit:contain;vertical-align:middle;'
        . 'filter:drop-shadow(0 0 10px rgba(245,217,122,.7));">';

    ob_start( function( $html ) use ( $logo_img ) {
        // 1. Replace "SEE IT IN ACTION" (any casing)
        $html = preg_replace( '/SEE\s+IT\s+IN\s+ACTION/i', '<span class="ex-preclinical-tag">A Pre-Clinical Warning System.</span>', $html );

        // 2. Replace the bare word EXCREET that is the only content of an <a> or <span>
        //    Targets:  <a ...>EXCREET</a>  or  <span ...>EXCREET</span>
        $html = preg_replace(
            '|(<(?:a|span)[^>]*>)\s*EXCREET\s*(</(?:a|span)>)|i',
            '$1' . $logo_img . '$2',
            $html
        );

        return $html;
    } );
}, 1 );

add_action( 'wp_head', 'excreet_310_explore_styles', 99 );

function excreet_310_explore_styles(): void {
    if ( (int) get_queried_object_id() !== 26 ) {
        return;
    }
    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url = esc_url( 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg' );
    ?>
<style id="ex310-explore">
/* ── /explore/ layout fix — bathroom bg breathes through ── */
html, body.page-id-26 {
    background: url("<?php echo $bg_url; ?>") center/cover no-repeat fixed #0c0115 !important;
}
/* Make all Elementor containers transparent so the bg shows */
body.page-id-26 .elementor-section,
body.page-id-26 .elementor-container,
body.page-id-26 .e-con,
body.page-id-26 .e-con-inner,
body.page-id-26 #page,
body.page-id-26 .site-content,
body.page-id-26 #content,
body.page-id-26 #main,
body.page-id-26 .site-main,
body.page-id-26 article.page {
    background: transparent !important;
}
/* Sections that need a semi-transparent dark overlay for readability */
body.page-id-26 .elementor-section[data-settings] {
    background: rgba(15, 3, 32, 0.65) !important;
}
/* White/light sections → botanical card style */
body.page-id-26 .elementor-section.elementor-section-boxed > .elementor-container,
body.page-id-26 .elementor-top-section:not(:first-child) {
    background: rgba(15, 3, 32, 0.55) !important;
}
/* Hero / first section — more transparent so bg image is vivid */
body.page-id-26 .elementor-top-section:first-of-type,
body.page-id-26 .elementor-top-section:first-of-type .elementor-container {
    background: rgba(0, 0, 0, 0.25) !important;
}
/* Pre-Clinical tagline — matches homepage style */
.ex-preclinical-tag {
    display: inline-block;
    font-size: clamp(11px,1vw,15px);
    font-weight: 700;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: rgba(255,255,255,.92) !important;
    text-shadow: 0 1px 8px rgba(0,0,0,.9), 0 0 24px rgba(0,0,0,.7);
    border-top: 1px solid rgba(201,168,76,.5);
    border-bottom: 1px solid rgba(201,168,76,.5);
    padding: .35em 1.2em;
    white-space: nowrap;
}
/* Text legibility */
body.page-id-26 h1,
body.page-id-26 h2,
body.page-id-26 h3,
body.page-id-26 h4 {
    color: #f0e8ff !important;
    text-shadow: 0 2px 8px rgba(0,0,0,0.6) !important;
}
body.page-id-26 p,
body.page-id-26 li,
body.page-id-26 .elementor-widget-text-editor {
    color: #e0d4f7 !important;
}
body.page-id-26 a:not(.elementor-button) {
    color: #C9A84C !important;
}
/* Buttons: keep Elementor button colours but add legibility lift */
body.page-id-26 .elementor-button {
    box-shadow: 0 4px 16px rgba(0,0,0,0.4) !important;
}
/* Site header semi-opaque over bg */
body.page-id-26 .site-header,
body.page-id-26 .elementor-location-header {
    background: rgba(15, 3, 32, 0.88) !important;
}
/* Cards / feature boxes — float as white botanical cards */
body.page-id-26 .elementor-widget-icon-box,
body.page-id-26 .elementor-widget-image-box {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(201,168,76,0.50) !important;
    border-radius: 12px !important;
    padding: 1.5rem !important;
    backdrop-filter: blur(6px) !important;
}

/* ── Logo swap: Elementor site-title widget + classic theme site-title ── */
body.page-id-26 .elementor-widget-site-title .elementor-site-title,
body.page-id-26 .elementor-widget-site-title .elementor-site-title a,
body.page-id-26 .site-title a,
body.page-id-26 .site-branding .site-title a {
    font-size: 0 !important;
    color: transparent !important;
    line-height: 0 !important;
}
body.page-id-26 .elementor-widget-site-title .elementor-site-title::after,
body.page-id-26 .elementor-widget-site-title .elementor-site-title a::after,
body.page-id-26 .site-title a::after,
body.page-id-26 .site-branding .site-title a::after {
    content: '' !important;
    display: inline-block !important;
    width: 48px !important;
    height: 48px !important;
    background: url('https://excreet.com/wp-content/uploads/2026/05/excreet-hero-logo.png') center/contain no-repeat !important;
    filter: drop-shadow(0 0 10px rgba(245,217,122,.6)) !important;
    vertical-align: middle !important;
}
</style>
    <?php

    // ── JS: robust text-node replacement via TreeWalker ──
    add_action( 'wp_footer', function() {
        if ( (int) get_queried_object_id() !== 26 ) { return; }
        $logo = esc_url( 'https://excreet.com/wp-content/uploads/2026/05/excreet-hero-logo.png' );
        ?>
<script>
(function(){
    var LOGO_URL = '<?php echo $logo; ?>';

    function walkTextNodes(root, cb) {
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
        var node, nodes = [];
        while ((node = walker.nextNode())) { nodes.push(node); }
        nodes.forEach(cb);
    }

    function applyReplacements() {
        walkTextNodes(document.body, function(node) {
            var txt = node.textContent;

            // 1. Replace "SEE IT IN ACTION" (any case/spacing) with styled span
            if (/see\s+it\s+in\s+action/i.test(txt)) {
                var span = document.createElement('span');
                span.className = 'ex-preclinical-tag';
                span.textContent = 'A Pre-Clinical Warning System.';
                if (node.parentElement) { node.parentElement.replaceChild(span, node); }
            }

            // 2. Replace bare "EXCREET" text nodes in the header with the hero logo img
            if (/^EXCREET$/.test(txt.trim())) {
                var parent = node.parentElement;
                if (parent) {
                    var img = document.createElement('img');
                    img.src = LOGO_URL;
                    img.alt = 'Excreet';
                    img.style.cssText = 'width:48px;height:48px;object-fit:contain;vertical-align:middle;filter:drop-shadow(0 0 10px rgba(245,217,122,.7));';
                    parent.replaceChild(img, node);
                }
            }
        });
    }

    // Run after DOM is ready and again after Elementor may finish rendering
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyReplacements);
    } else {
        applyReplacements();
    }
    window.addEventListener('load', applyReplacements);
    setTimeout(applyReplacements, 800);
    setTimeout(applyReplacements, 2000);
})();
</script>
        <?php
    }, 20 );
}
