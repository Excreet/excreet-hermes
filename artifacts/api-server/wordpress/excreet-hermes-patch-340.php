<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.4.0
 * Description: Intake Form Header Rebuild — replaces the broken CSS-pseudo wordmark and
 *              non-functional Elementor "Back to Home" widget with a proper injected header:
 *
 *                EXCREET  (gold, centered, letterspace)
 *                [Hero logo — centered, round, gold glow]
 *                A Pre-Clinical Warning System  (tagline, centered)
 *                ← Back to Home  (working link, centered, muted gold)
 *
 *              Also hides the orphaned Elementor Back-to-Home text widget and
 *              removes the old CSS ::before pseudo wordmark so there is no duplication.
 *
 * Version: 3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── CSS: kill old pseudo wordmark + hide broken Elementor widget ──────── */
add_action( 'wp_head', 'excreet_340_intake_head', 9999 );
function excreet_340_intake_head(): void {
    if ( ! is_page( 'member-intake-form' ) ) { return; }
    ?>
<style id="excreet-340-intake-header">

/* Remove old CSS ::before pseudo wordmark */
.elementor-element-54e25be::before {
    content: none !important;
    display: none !important;
}

/* Hide the broken Elementor Back-to-Home text widget */
.elementor-element-cc9e57e {
    display: none !important;
}

/* ── New header block ── */
#ex340-intake-header {
    text-align: center;
    padding: 2.8rem 1.5rem 0.8rem;
    position: relative;
}

/* Wordmark */
#ex340-wordmark {
    font-family: 'Poppins', 'Georgia', serif;
    font-size: 1.15rem;
    font-weight: 700;
    letter-spacing: .55em;
    text-transform: uppercase;
    color: #C9A84C;
    margin: 0 0 1.4rem;
    line-height: 1;
}

/* Logo ring */
#ex340-logo-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    margin-bottom: 1rem;
}
#ex340-logo-img {
    width: 78px;
    height: 78px;
    border-radius: 50%;
    object-fit: cover;
    box-shadow:
        0 0 0 2px rgba(201,168,76,.50),
        0 0 32px rgba(201,168,76,.30),
        0 6px 20px rgba(0,0,0,.55);
    display: block;
}

/* Tagline */
#ex340-tagline {
    font-family: 'Poppins', 'Georgia', serif;
    font-size: clamp(11px,1vw,15px);
    font-weight: 700;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: rgba(255,255,255,.92);
    text-shadow: 0 1px 8px rgba(0,0,0,.9), 0 0 24px rgba(0,0,0,.7);
    border-top: 1px solid rgba(201,168,76,.5);
    border-bottom: 1px solid rgba(201,168,76,.5);
    padding: .35em 1.2em;
    display: inline-block;
    margin: 1rem 0 0;
    line-height: 1;
}

/* Thin gold rule — hidden now that borders are on the tagline */
#ex340-rule { display: none; }

/* Back to Home */
#ex340-back {
    display: inline-flex;
    align-items: center;
    gap: .35em;
    margin-top: 1.4rem;
    font-family: 'Poppins', 'Georgia', serif;
    font-size: .78rem;
    font-weight: 500;
    letter-spacing: .06em;
    color: rgba(201,168,76,.65);
    text-decoration: none;
    border: 1px solid rgba(201,168,76,.20);
    padding: .42rem 1.1rem;
    border-radius: 100px;
    transition: color .2s, border-color .2s, background .2s;
}
#ex340-back:hover {
    color: #C9A84C;
    border-color: rgba(201,168,76,.50);
    background: rgba(201,168,76,.06);
}
#ex340-back svg {
    flex-shrink: 0;
    transition: transform .2s;
}
#ex340-back:hover svg {
    transform: translateX(-3px);
}

/* Pad the welcome text block so it doesn't crowd the header */
.elementor-element-54e25be > .e-con-inner,
.elementor-element-54e25be {
    padding-top: 0 !important;
}

</style>
    <?php
}

/* ── Inject the header HTML just before the Elementor inner column ────── */
add_action( 'wp_footer', 'excreet_340_intake_header_inject', 97 );
function excreet_340_intake_header_inject(): void {
    if ( ! is_page( 'member-intake-form' ) ) { return; }

    $logo_url = 'https://excreet.com/wp-content/uploads/excreet-hero-logo.png';
    $home_url = esc_url( home_url( '/' ) );
    ?>
<script id="excreet-340-inject">
(function () {
    'use strict';

    var LOGO = <?php echo wp_json_encode( $logo_url ); ?>;
    var HOME = <?php echo wp_json_encode( $home_url ); ?>;

    function inject() {
        /* Find the inner Elementor column that wraps the form */
        var col = document.querySelector(
            '.elementor-element-54e25be > .e-con-inner, ' +
            '.elementor-element-54e25be'
        );
        if (!col) {
            /* Fallback: prepend to .entry-content */
            col = document.querySelector('.entry-content, .page-content, .elementor-section-wrap');
        }
        if (!col) { return; }

        /* Don't inject twice */
        if (document.getElementById('ex340-intake-header')) { return; }

        var header = document.createElement('div');
        header.id = 'ex340-intake-header';
        header.innerHTML =
            '<p id="ex340-wordmark">E X C R E E T</p>' +
            '<div id="ex340-logo-wrap">' +
                '<img id="ex340-logo-img" src="' + LOGO + '" alt="Excreet" ' +
                     'onerror="this.style.display=\'none\'">' +
            '</div>' +
            '<p id="ex340-tagline">A Pre&#8209;Clinical Warning System</p>' +
            '<div id="ex340-rule"></div>' +
            '<div style="text-align:center;margin-top:1.4rem;">' +
                '<a id="ex340-back" href="' + HOME + '">' +
                    '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" ' +
                         'xmlns="http://www.w3.org/2000/svg">' +
                        '<path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.6" ' +
                              'stroke-linecap="round" stroke-linejoin="round"/>' +
                    '</svg>' +
                    'Back to Home' +
                '</a>' +
            '</div>';

        col.insertBefore(header, col.firstChild);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inject);
    } else {
        inject();
    }
})();
</script>
    <?php
}
