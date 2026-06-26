<?php
/**
 * Plugin Name: Excreet Patch 312 — Know the Signals Link Fix
 * Description: Fixes /know-the-signals/ (page ID 553) broken and stale links.
 *
 *   FIX 1 — Dead links from old MemberPress era:
 *              /my-account/           → /membership-account/
 *              /register/{slug}/       → /membership-checkout/?level=1
 *              /membership-payment-page/ → /membership-checkout/?level=1
 *              /mp-login/             → /login/
 *              /affiliate-login/      → /login/
 *
 *   FIX 2 — Stale internal links:
 *              /gut-snapshot/         → /healing-command-center/  (slug retained)
 *              /member-login/         → /login/   (PMPro canonical login)
 *
 *   FIX 3 — "Gut" → "Body" label corrections on this page to match
 *              the Phase 12 rename that updated Dashboard and Welcome Member.
 *
 *   FIX 4 — Ensure bathroom background renders on page 553 (catches any
 *              cases where patch-309 global is overridden by the theme).
 *
 * Version: 3.1.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Background guarantee ──────────────────────────────────────────────────────
add_action( 'wp_head', 'excreet_312_kts_bg', 99 );

function excreet_312_kts_bg(): void {
    if ( (int) get_queried_object_id() !== 553 ) {
        return;
    }
    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url = esc_url( 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg' );
    ?>
<style id="ex312-kts-bg">
html, body.page-id-553 {
    background: url("<?php echo $bg_url; ?>") center/cover no-repeat fixed #0c0115 !important;
}
body.page-id-553 #page,
body.page-id-553 .site-content,
body.page-id-553 #content,
body.page-id-553 #main,
body.page-id-553 .site-main,
body.page-id-553 .elementor-section,
body.page-id-553 .elementor-container,
body.page-id-553 .e-con,
body.page-id-553 .e-con-inner,
body.page-id-553 article.page {
    background: transparent !important;
}
body.page-id-553 h1,
body.page-id-553 h2,
body.page-id-553 h3,
body.page-id-553 h4 {
    color: #f0e8ff !important;
    text-shadow: 0 2px 8px rgba(0,0,0,0.55) !important;
}
body.page-id-553 p,
body.page-id-553 li {
    color: #e0d4f7 !important;
}
body.page-id-553 a:not(.elementor-button) {
    color: #C9A84C !important;
}
body.page-id-553 .elementor-widget-icon-box,
body.page-id-553 .elementor-widget-image-box,
body.page-id-553 .elementor-widget-icon-list {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(201,168,76,0.2) !important;
    border-radius: 10px !important;
    padding: 1.2rem !important;
    backdrop-filter: blur(5px) !important;
}
</style>
    <?php
}

// ── Link & label fixes ────────────────────────────────────────────────────────
add_action( 'wp_footer', 'excreet_312_kts_fixes', 99 );

function excreet_312_kts_fixes(): void {
    if ( (int) get_queried_object_id() !== 553 ) {
        return;
    }
    ?>
<script id="ex312-kts-fixes">
(function () {
    'use strict';

    // Old URL → new URL (exact string matches in href attributes)
    var LINK_MAP = [
        [ '/my-account/',                  '/membership-account/'          ],
        [ '/my-account',                   '/membership-account/'          ],
        [ '/membership-payment-page/',     '/membership-checkout/?level=1' ],
        [ '/membership-payment-page',      '/membership-checkout/?level=1' ],
        [ '/mp-login/',                    '/login/'                       ],
        [ '/mp-login',                     '/login/'                       ],
        [ '/affiliate-login/',             '/login/'                       ],
        [ '/affiliate-login',              '/login/'                       ],
        [ '/gut-snapshot/',                '/healing-command-center/'      ],
        [ '/gut-snapshot',                 '/healing-command-center/'      ],
        [ '/member-login/',                '/login/'                       ],
        [ '/member-login',                 '/login/'                       ],
    ];

    // Regex pattern for /register/<anything>/ MemberPress URLs
    var REGISTER_RE = /\/register\/[^\s"'?#]*/g;

    var TEXT_REPLACEMENTS = [
        [ /\bGut Snapshot\b/g,     'Body Snapshot'     ],
        [ /\bGut Health\b/g,       'Body Health'       ],
        [ /\bGut Intelligence\b/g, 'Body Intelligence' ],
        [ /\b24\/7 Gut\b/g,        '24/7 Body'         ],
    ];

    function fixLinks( root ) {
        root.querySelectorAll( 'a[href]' ).forEach( function ( a ) {
            var href = a.getAttribute('href') || '';
            // Regex replace for /register/…/
            href = href.replace( REGISTER_RE, '/membership-checkout/?level=1' );
            // Exact string replacements
            LINK_MAP.forEach( function (pair) {
                if ( href.indexOf( pair[0] ) !== -1 ) {
                    href = href.replace( pair[0], pair[1] );
                }
            });
            a.setAttribute( 'href', href );
        });
    }

    function fixText( root ) {
        var walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT, null, false );
        var node;
        while ( ( node = walker.nextNode() ) ) {
            var val = node.nodeValue;
            if ( !val || !val.trim() ) continue;
            TEXT_REPLACEMENTS.forEach( function (pair) { val = val.replace( pair[0], pair[1] ); });
            node.nodeValue = val;
        }
    }

    function applyFixes() {
        fixLinks( document.body );
        fixText( document.body );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', applyFixes );
    } else {
        applyFixes();
    }

    if ( window.elementorFrontend ) {
        elementorFrontend.hooks.addAction( 'frontend/element_ready/global', applyFixes );
    }
})();
</script>
    <?php
}
