<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.3.9
 * Description: Global Design Unification — botanical dark-card palette applied uniformly.
 *
 *   Problem: Several pages/components retained light or solid-purple themes
 *   (Ministry of Healing chat, Protocol Session form/doc, Dashboard progress bar,
 *   affiliate table, tier result cards, gray placeholder text across patches).
 *
 *   Fix:  One stylesheet loaded on every frontend page with !important overrides
 *   for all CSS-class-based rogue styles, plus a DOMContentLoaded JS pass that
 *   repairs critical inline-style offenders inside known containers.
 *
 *   Canonical palette:
 *     Background deep:  #0c0115
 *     Card background:  rgba(12,2,26,.82)
 *     Card alt:         rgba(26,5,53,.60)
 *     Card border:      rgba(201,168,76,.22)
 *     Gold:             #C9A84C
 *     Purple rgba:      rgba(107,47,160, ...)
 *     Body text:        #f0e8ff
 *     Muted text:       rgba(240,232,255,.55)
 *     Dim text:         rgba(255,255,255,.38)
 *
 * Version: 3.3.9
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', 'excreet_339_enqueue', 9999 );
function excreet_339_enqueue(): void {
    // Load on all logged-in frontend pages; harmless on public pages.
    if ( is_admin() ) { return; }
    add_action( 'wp_head', 'excreet_339_css', 9999 );
    add_action( 'wp_footer', 'excreet_339_js', 9999 );
}

/* ════════════════════════════════════════════════════════════════════════════
   CSS overrides — loaded in <head> so they cascade after each patch's <style>
   ════════════════════════════════════════════════════════════════════════════ */
function excreet_339_css(): void {
    ?>
<style id="excreet-339-design-unification">

/* ── MINISTRY OF HEALING  (#excreet-moh)  patch-293 ──────────────────────── */

/* Usage / session bar */
#excreet-moh-usage {
    background: rgba(12,2,26,.95) !important;
    border-bottom: 1px solid rgba(201,168,76,.15) !important;
    color: #f0e8ff !important;
}

/* Message area */
#excreet-moh-messages {
    background: rgba(8,1,20,.98) !important;
    border-bottom: 1px solid rgba(201,168,76,.08) !important;
}

/* AI bubble */
.moh-ai {
    background: rgba(26,5,53,.70) !important;
    border-left: 3px solid rgba(201,168,76,.40) !important;
    border-radius: 4px 12px 12px 4px !important;
    color: #f0e8ff !important;
}
.moh-ai .moh-label { color: rgba(201,168,76,.60) !important; opacity: 1 !important; }

/* User bubble — was solid purple, now rgba */
.moh-user {
    background: rgba(107,47,160,.50) !important;
    border: 1px solid rgba(107,47,160,.65) !important;
    border-radius: 12px 4px 4px 12px !important;
    color: #f0e8ff !important;
}
.moh-user .moh-label { color: rgba(201,168,76,.50) !important; opacity: 1 !important; }

/* Typing indicator */
#excreet-moh-typing {
    background: rgba(26,5,53,.70) !important;
    border-left: 3px solid rgba(201,168,76,.40) !important;
    color: rgba(201,168,76,.80) !important;
}

/* Session separator lines */
.moh-history-sep { color: rgba(201,168,76,.50) !important; }
.moh-history-sep::before,
.moh-history-sep::after { background: rgba(201,168,76,.15) !important; }

/* Starter tip banner */
#excreet-moh-starter-tip {
    background: rgba(107,47,160,.12) !important;
    border: 1px dashed rgba(107,47,160,.40) !important;
    color: rgba(240,232,255,.75) !important;
}
#excreet-moh-starter-tip strong { color: rgba(201,168,76,.90) !important; }

/* Input area */
#excreet-moh-input-area {
    background: rgba(10,2,22,.98) !important;
    border-top: 1px solid rgba(201,168,76,.15) !important;
}
#excreet-moh-textarea {
    background: rgba(255,255,255,.06) !important;
    border-color: rgba(201,168,76,.30) !important;
    color: #f0e8ff !important;
    caret-color: #C9A84C !important;
}
#excreet-moh-textarea::placeholder { color: rgba(240,232,255,.30) !important; }
#excreet-moh-textarea:focus {
    border-color: rgba(201,168,76,.65) !important;
    box-shadow: 0 0 0 3px rgba(201,168,76,.08) !important;
    background: rgba(255,255,255,.08) !important;
}

/* Toolbar chip buttons */
.moh-tool-btn {
    background: rgba(107,47,160,.12) !important;
    border-color: rgba(107,47,160,.38) !important;
    color: rgba(201,168,76,.80) !important;
}
.moh-tool-btn:hover {
    background: rgba(107,47,160,.50) !important;
    border-color: rgba(107,47,160,.70) !important;
    color: #f0e8ff !important;
}

/* Error banner */
#excreet-moh-error {
    background: rgba(192,57,43,.15) !important;
    color: #ff8a8a !important;
    border: 1px solid rgba(192,57,43,.35) !important;
}

/* Footer privacy line */
#excreet-moh-privacy {
    background: rgba(8,1,20,.98) !important;
    border-top: 1px solid rgba(201,168,76,.10) !important;
    color: rgba(255,255,255,.28) !important;
}

/* New-session button */
#ex297-reset-btn {
    background: rgba(12,2,26,.90) !important;
    border: 1px solid rgba(201,168,76,.25) !important;
    color: rgba(201,168,76,.80) !important;
}
#ex297-reset-btn:hover {
    border-color: rgba(201,168,76,.60) !important;
    color: #C9A84C !important;
}

/* ── PROTOCOL SESSION FORM  (#excreet-protocol-body / .excreet-intake-field)  patch-294 ── */

#excreet-protocol-body {
    background: rgba(12,2,26,.90) !important;
    color: #f0e8ff !important;
}
.excreet-intake-field {
    background: rgba(255,255,255,.06) !important;
    border-color: rgba(201,168,76,.30) !important;
    color: #f0e8ff !important;
}
.excreet-intake-field::placeholder { color: rgba(240,232,255,.30) !important; }
.excreet-intake-field:focus {
    border-color: rgba(201,168,76,.65) !important;
    box-shadow: 0 0 0 2px rgba(201,168,76,.10) !important;
    background: rgba(255,255,255,.09) !important;
}
.excreet-intake-label {
    color: rgba(201,168,76,.80) !important;
}
#excreet-protocol-card {
    box-shadow: 0 8px 40px rgba(0,0,0,.55) !important;
    border: 1px solid rgba(201,168,76,.18) !important;
}

/* ── MEMBER DASHBOARD  (.ex313-*)  patch-313 ──────────────────────────────── */

.ex313-prog-wrap {
    background: rgba(12,2,26,.80) !important;
    border: 1px solid rgba(201,168,76,.18) !important;
    border-radius: 10px !important;
}
.ex313-prog-header {
    color: rgba(240,232,255,.58) !important;
}
.ex313-prog-header strong { color: #f0e8ff !important; }
.ex313-prog-track {
    background: rgba(255,255,255,.10) !important;
}
.ex313-prog-note {
    color: rgba(255,255,255,.35) !important;
}
.ex313-sdate {
    color: rgba(240,232,255,.38) !important;
}
.ex313-scur .ex313-sdate {
    color: #C9A84C !important;
}

/* ── AFFILIATE DASHBOARD tables / cards  patch-313 upgrades ──────────────── */

.ex299-dashboard table {
    background: rgba(12,2,26,.80) !important;
    border: 1px solid rgba(201,168,76,.15) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}
.ex299-dashboard table thead tr {
    background: rgba(26,5,53,.80) !important;
}
.ex299-dashboard table thead th {
    color: rgba(201,168,76,.70) !important;
}
.ex299-card {
    background: rgba(12,2,26,.82) !important;
    border: 1px solid rgba(201,168,76,.20) !important;
    border-radius: 12px !important;
}
.ex299-card:hover {
    border-color: rgba(201,168,76,.45) !important;
}

/* ── HCC BODY CHECK tier result cards  patch-298 ─────────────────────────── */
/* Replace solid mono-color backgrounds with rgba semantic overlays */
.ex298-tier-badge[style*="background:#1a4a2a"],
.ex298-tier-badge[style*="background: #1a4a2a"] {
    background: rgba(46,204,113,.12) !important;
    border-color: rgba(46,204,113,.50) !important;
}
.ex298-tier-badge[style*="background:#1a3a4a"],
.ex298-tier-badge[style*="background: #1a3a4a"] {
    background: rgba(52,152,219,.12) !important;
    border-color: rgba(52,152,219,.50) !important;
}
.ex298-tier-badge[style*="background:#4a3a1a"],
.ex298-tier-badge[style*="background: #4a3a1a"] {
    background: rgba(243,156,18,.12) !important;
    border-color: rgba(243,156,18,.50) !important;
}
.ex298-tier-badge[style*="background:#4a1a1a"],
.ex298-tier-badge[style*="background: #4a1a1a"] {
    background: rgba(231,76,60,.12) !important;
    border-color: rgba(231,76,60,.50) !important;
}

/* ── GLOBAL: muted-text color normalization ───────────────────────────────── */
/* Targets classes that hard-code #888 / #999 */
.ex313-prog-header,
.ex313-prog-note,
.ex313-sdate,
.ex298-date { color: rgba(240,232,255,.45) !important; }

/* ── Re-baseline panel  patch-298 ────────────────────────────────────────── */
.ex298-rebaseline-panel {
    background: rgba(12,2,26,.88) !important;
    border: 1px solid rgba(201,168,76,.22) !important;
    color: #f0e8ff !important;
}
.ex298-rebaseline-desc { color: rgba(240,232,255,.65) !important; }

</style>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   JS DOM fixup — runs at DOMContentLoaded to repair inline-style offenders
   that cannot be overridden by pure CSS.
   ════════════════════════════════════════════════════════════════════════════ */
function excreet_339_js(): void {
    if ( is_admin() ) { return; }
    ?>
<script id="excreet-339-dom-fix">
(function () {
    'use strict';

    /* ── Colour tokens ── */
    var CARD_BG      = 'rgba(12,2,26,0.88)';
    var CARD_ALT     = 'rgba(26,5,53,0.65)';
    var CARD_BORDER  = 'rgba(201,168,76,0.20)';
    var TEXT_MAIN    = '#f0e8ff';
    var TEXT_MUTED   = 'rgba(240,232,255,0.55)';
    var TEXT_DIM     = 'rgba(255,255,255,0.35)';
    var TRACK_BG     = 'rgba(255,255,255,0.10)';
    var INPUT_BG     = 'rgba(255,255,255,0.06)';
    var INPUT_BORDER = 'rgba(201,168,76,0.30)';
    var ERR_BG       = 'rgba(192,57,43,0.15)';

    /* ── Light colours to replace ── */
    var LIGHT_BG = [
        '#f7f4fc', '#fff', '#ffffff', '#faf5ff', '#fafafa',
        '#f9f6ff', '#fdf0f0', '#f8f4ff', '#f0faf0', '#fff8f8',
        '#fff9f9', '#fffbf0', '#fff8f0', '#f5f0fb', '#faf9ff',
        'rgb(255, 255, 255)',
        'rgb(247, 244, 252)',
        'rgb(250, 245, 255)',
        'rgb(250, 250, 250)',
        'rgb(249, 246, 255)',
        'rgb(240, 250, 240)',
        'rgb(255, 248, 248)'
    ];
    var LIGHT_TEXT = [
        '#888', '#666', '#999', '#444', '#555', '#777',
        '#2a1040', '#1a1a1a', '#222', '#333', '#4c1d95',
        'rgb(136, 136, 136)', 'rgb(102, 102, 102)',
        'rgb(153, 153, 153)', 'rgb(68, 68, 68)'
    ];
    var SEPARATOR_BG = ['#e0d0ee', 'rgb(224, 208, 238)', '#c9b0e0', 'rgb(201, 176, 224)'];
    var SEPARATOR_TEXT = ['rgba(0, 0, 0, 0.3)', 'rgba(0,0,0,.3)', 'rgba(0,0,0,0.1)', 'rgba(0,0,0,.1)'];

    function normalise(v) {
        return (v || '').toLowerCase().replace(/\s+/g, '');
    }
    function inList(v, list) {
        var n = normalise(v);
        for (var i = 0; i < list.length; i++) {
            if (n === normalise(list[i])) { return true; }
        }
        return false;
    }

    /* Fix a single element's inline background + color */
    function fixEl(el, opts) {
        opts = opts || {};
        var bg = el.style.backgroundColor || el.style.background || '';
        if (inList(bg, LIGHT_BG)) {
            el.style.setProperty('background', opts.bg || CARD_BG, 'important');
            el.style.setProperty('background-color', '', '');
        }
        if (inList(bg, SEPARATOR_BG)) {
            el.style.setProperty('background', TRACK_BG, 'important');
        }
        var col = el.style.color || '';
        if (inList(col, LIGHT_TEXT)) {
            el.style.setProperty('color', opts.color || TEXT_MUTED, 'important');
        }
        if (inList(col, SEPARATOR_TEXT)) {
            el.style.setProperty('color', 'rgba(201,168,76,0.45)', 'important');
        }
        var bd = el.style.borderColor || '';
        if (bd === '#ede4f5' || bd === '#e0d0ee' || bd === '#e0d4f0') {
            el.style.setProperty('border-color', CARD_BORDER, 'important');
        }
    }

    /* Walk all [style] children of a root element */
    function walkContainer(root, opts) {
        if (!root) { return; }
        var els = root.querySelectorAll('[style]');
        for (var i = 0; i < els.length; i++) { fixEl(els[i], opts); }
        fixEl(root, opts); // also fix root itself
    }

    function run() {

        /* ── 1. Ministry of Healing — inline bits inside usage bar ── */
        var mohUsage = document.getElementById('excreet-moh-usage');
        if (mohUsage) {
            walkContainer(mohUsage, { color: TEXT_MUTED });
            // progress bar track (fixed height div, light lavender)
            mohUsage.querySelectorAll('[style]').forEach(function(el) {
                if (el.style.height === '6px') {
                    var bg = el.style.background || el.style.backgroundColor || '';
                    if (inList(bg, SEPARATOR_BG) || bg.toLowerCase().indexOf('e0d0ee') !== -1) {
                        el.style.setProperty('background', TRACK_BG, 'important');
                    }
                }
            });
            // Upgrade / add-session link — was purple-on-white border pill
            mohUsage.querySelectorAll('a[style]').forEach(function(a) {
                var col = (a.style.color || '').toLowerCase();
                if (col === '#6b2fa0' || col === 'rgb(107, 47, 160)') {
                    a.style.setProperty('color', '#C9A84C', 'important');
                    a.style.setProperty('border-color', 'rgba(201,168,76,0.45)', 'important');
                }
            });
        }

        /* ── 2. Protocol document view — section blocks with inline whites ── */
        var protoDoc = document.getElementById('excreet-protocol-doc');
        if (protoDoc) {
            // Section divs
            protoDoc.querySelectorAll('[style]').forEach(function(el) {
                var bg = (el.style.background || el.style.backgroundColor || '').toLowerCase();
                if (bg && (
                    inList(bg, LIGHT_BG) ||
                    bg.indexOf('#f7f4fc') !== -1 ||
                    bg.indexOf('#fff') !== -1
                )) {
                    el.style.setProperty('background', CARD_BG, 'important');
                    // fix text color in these sections
                    var col = el.style.color || '';
                    if (!col || inList(col, LIGHT_TEXT) || col.toLowerCase() === '#2a1040') {
                        el.style.setProperty('color', TEXT_MAIN, 'important');
                    }
                }
                // Section border-bottoms
                var bd = el.style.borderBottom || '';
                if (bd && bd.indexOf('#ede4f5') !== -1) {
                    el.style.borderBottom = '1px solid rgba(201,168,76,0.12)';
                }
                // purple-dark colour section labels
                var colEl = el.style.color || '';
                if (colEl === '#3D1060' || colEl === 'rgb(61, 16, 96)') {
                    el.style.setProperty('color', 'rgba(201,168,76,0.75)', 'important');
                }
                // List item purple bullets
                if (colEl === '#6B2FA0' || colEl === 'rgb(107, 47, 160)') {
                    el.style.setProperty('color', '#C9A84C', 'important');
                }
                // Gray muted text
                if (inList(colEl, LIGHT_TEXT)) {
                    el.style.setProperty('color', TEXT_MUTED, 'important');
                }
                // List separator lines
                var bdBot = el.style.borderBottom || '';
                if (bdBot.indexOf('#f0e8f8') !== -1) {
                    el.style.borderBottom = '1px solid rgba(201,168,76,0.08)';
                }
            });
        }

        /* ── 3. Protocol intake form (on HCC or dedicated page) ── */
        var protoCard = document.getElementById('excreet-protocol-card');
        if (protoCard) {
            walkContainer(protoCard);
        }

        /* ── 4. Protocol Session generation form ── */
        var protoWrap = document.getElementById('excreet-protocol-wrap');
        if (protoWrap) {
            // Warning / info banners (#fff8f0, #fffbf0 backgrounds)
            protoWrap.querySelectorAll('[style]').forEach(function(el) {
                var bg = (el.style.background || el.style.backgroundColor || '').toLowerCase();
                if (bg && inList(bg, LIGHT_BG)) {
                    el.style.setProperty('background', 'rgba(201,168,76,0.08)', 'important');
                    el.style.setProperty('border-color', 'rgba(201,168,76,0.30)', 'important');
                    var col = el.style.color || '';
                    if (col && (inList(col, LIGHT_TEXT) || col.toLowerCase().indexOf('7a5a00') !== -1 || col.toLowerCase().indexOf('6b4f00') !== -1)) {
                        el.style.setProperty('color', 'rgba(201,168,76,0.90)', 'important');
                    }
                }
            });
        }

        /* ── 5. Ministry MOAT modals — inline buttons injected by JS ── */
        // These appear dynamically, so watch for them with a short-lived observer
        var mohRoot = document.getElementById('excreet-moh');
        if (mohRoot) {
            var fixMoat = function() {
                mohRoot.querySelectorAll('[style]').forEach(function(el) {
                    var bg = (el.style.background || el.style.backgroundColor || '').toLowerCase();
                    if (bg && inList(bg, LIGHT_BG)) {
                        el.style.setProperty('background', 'rgba(107,47,160,0.15)', 'important');
                        el.style.setProperty('color', 'rgba(201,168,76,0.90)', 'important');
                        el.style.setProperty('border-color', 'rgba(107,47,160,0.40)', 'important');
                    }
                });
            };
            // Run once immediately, then watch for injected content
            fixMoat();
            if (window.MutationObserver) {
                var obs = new MutationObserver(function(muts) {
                    muts.forEach(function(m) {
                        if (m.addedNodes.length) { fixMoat(); }
                    });
                });
                obs.observe(mohRoot, { childList: true, subtree: true });
                // Disconnect after 10 minutes (session has ended)
                setTimeout(function() { obs.disconnect(); }, 600000);
            }
        }

        /* ── 6. Affiliate dashboard referral input (patch-299) ── */
        var refInput = document.getElementById('ex299_referral_code');
        if (refInput && refInput.style.background === '#1a1a1a') {
            refInput.style.setProperty('background', INPUT_BG, 'important');
            refInput.style.setProperty('border-color', INPUT_BORDER, 'important');
            refInput.style.setProperty('color', TEXT_MAIN, 'important');
        }

        /* ── 7. Any stray page-level light elements within .entry-content ── */
        var entry = document.querySelector('.entry-content, .page-content');
        if (entry) {
            entry.querySelectorAll('[style]').forEach(function(el) {
                // Only touch clearly light backgrounds — skip images, SVGs
                if (el.tagName === 'IMG' || el.tagName === 'SVG' || el.tagName === 'CANVAS') { return; }
                var bg = (el.style.background || el.style.backgroundColor || '');
                if (inList(bg, LIGHT_BG) && !el.id.startsWith('excreet-protocol')) {
                    // Check it's not a colour-swatch button (HCC body check)
                    if (!el.classList.contains('ex298-color-swatch')) {
                        el.style.setProperty('background', CARD_BG, 'important');
                    }
                }
                // Muted text
                var col = el.style.color || '';
                if (inList(col, LIGHT_TEXT)) {
                    if (!el.closest('#excreet-protocol-doc')) {
                        el.style.setProperty('color', TEXT_MUTED, 'important');
                    }
                }
            });
        }

    } /* end run() */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

})();
</script>
    <?php
}
