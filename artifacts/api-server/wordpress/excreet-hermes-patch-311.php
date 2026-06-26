<?php
/**
 * Plugin Name: Excreet Patch 311 — Welcome Member Label & Link Fix
 * Description: Fixes /welcome-member/ (page ID 366) after the Phase 12 rename.
 *
 *   FIX 1 — Label rename: "Gut Snapshot" → "Body Snapshot" everywhere on the page.
 *            Also replaces "Gut Health" → "Body Health" and "Gut Intelligence"
 *            → "Body Intelligence" for consistency with the dashboard rename.
 *
 *   FIX 2 — Dead link repair: /my-account/ → /membership-account/ (PMPro page).
 *            /register/ → /membership-checkout/ for any residual MemberPress links.
 *            /healing-command-center/  kept as-is (slug correct, just Elementor label).
 *
 *   FIX 3 — Background: ensure botanical bg applied on page 366 regardless of
 *            theme's section backgrounds (override patch-309 default for this page).
 *
 * Version: 3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Background consistency ────────────────────────────────────────────────────
add_action( 'wp_head', 'excreet_311_welcome_bg', 99 );

function excreet_311_welcome_bg(): void {
    if ( (int) get_queried_object_id() !== 366 ) {
        return;
    }
    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url = esc_url( 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg' );
    ?>
<style id="ex311-welcome-bg">
html, body.page-id-366 {
    background: url("<?php echo $bg_url; ?>") center/cover no-repeat fixed #0c0115 !important;
}
body.page-id-366 #page,
body.page-id-366 .site-content,
body.page-id-366 #content,
body.page-id-366 #main,
body.page-id-366 .site-main,
body.page-id-366 .elementor-section,
body.page-id-366 .elementor-container,
body.page-id-366 .e-con,
body.page-id-366 .e-con-inner,
body.page-id-366 article.page {
    background: transparent !important;
}
</style>
    <?php
}

// ── Label rename + link fix (JS, fires after DOM ready) ───────────────────────
add_action( 'wp_footer', 'excreet_311_welcome_fixes', 99 );

function excreet_311_welcome_fixes(): void {
    if ( (int) get_queried_object_id() !== 366 ) {
        return;
    }
    ?>
<script id="ex311-welcome-fixes">
(function () {
    'use strict';

    // ── Text replacements ──────────────────────────────────────────────────────
    var TEXT_REPLACEMENTS = [
        [ /\bGut Snapshot\b/g,        'Body Snapshot'     ],
        [ /\bGut Health\b/g,          'Body Health'       ],
        [ /\bGut Intelligence\b/g,    'Body Intelligence' ],
        [ /\bYour Gut\b/g,            'Your Body'         ],
        [ /\b24\/7 Gut\b/g,           '24/7 Body'         ],
    ];

    // ── URL redirects (old → new) ─────────────────────────────────────────────
    var LINK_MAP = [
        [ '/my-account/',           '/membership-account/'           ],
        [ '/my-account',            '/membership-account/'           ],
        [ /\/register\/[^"']*/g,    '/membership-checkout/?level=1'  ],
    ];

    function fixTextNode( node ) {
        var orig = node.nodeValue;
        if ( ! orig || orig.trim() === '' ) return;
        var val = orig;
        TEXT_REPLACEMENTS.forEach(function (pair) {
            val = val.replace( pair[0], pair[1] );
        });
        if ( val !== orig ) node.nodeValue = val;
    }

    function walkText( root ) {
        var walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        var node;
        while ( ( node = walker.nextNode() ) ) {
            fixTextNode( node );
        }
    }

    function fixLinks( root ) {
        root.querySelectorAll( 'a[href]' ).forEach(function ( a ) {
            var href = a.getAttribute('href') || '';
            LINK_MAP.forEach(function (pair) {
                if ( typeof pair[0] === 'string' ) {
                    if ( href.indexOf( pair[0] ) !== -1 ) {
                        a.setAttribute( 'href', href.replace( pair[0], pair[1] ) );
                    }
                } else {
                    a.setAttribute( 'href', href.replace( pair[0], pair[1] ) );
                }
            });
        });
    }

    function applyFixes() {
        walkText( document.body );
        fixLinks( document.body );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', applyFixes );
    } else {
        applyFixes();
    }

    // Re-run after Elementor lazy-renders sections
    if ( window.elementorFrontend ) {
        elementorFrontend.hooks.addAction( 'frontend/element_ready/global', applyFixes );
    }
})();
</script>
    <?php
}
