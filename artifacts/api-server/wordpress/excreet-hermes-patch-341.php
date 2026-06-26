<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.4.1
 * Description: Member Intake Form — full page rebuild to match homepage aesthetic.
 *
 *   Structure:
 *     • Full-screen bathroom healer-bg with homepage-matching dark overlay
 *     • Scrollable narrow column (640px) centered on the page
 *     • DARK header section: EXCREET wordmark + logo + tagline + Back to Home
 *       — identical visual language to the homepage hero
 *     • DARK welcome section: "Welcome." in gold, descriptive paragraphs in white
 *     • WHITE form card: all Forminator questionnaire fields on a bright white
 *       clinical card — gold top accent bar, dark labels, gold focus rings,
 *       gold submit pill — maximum contrast for member data entry
 *
 *   Overrides: patches 297-A (intake CSS), 340 (header inject) on this page only.
 *
 * Version: 3.4.1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── Load Poppins if not already present ─────────────────────────────────── */
add_action( 'wp_head', 'excreet_341_fonts', 1 );
function excreet_341_fonts(): void {
    if ( ! is_page( 'member-intake-form' ) ) { return; }
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">' . "\n";
}

/* ════════════════════════════════════════════════════════════════════════════
   FULL-PAGE CSS — wins over all prior patches via specificity + !important
   ════════════════════════════════════════════════════════════════════════════ */
add_action( 'wp_head', 'excreet_341_css', 10000 );
function excreet_341_css(): void {
    if ( ! is_page( 'member-intake-form' ) ) { return; }

    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url = 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg';
    ?>
<style id="excreet-341-intake-rebuild">

/* ══════════════════════════════════════════════
   1 — PAGE CANVAS  (matches homepage exactly)
   ══════════════════════════════════════════════ */
html, body {
    font-family: 'Poppins', sans-serif !important;
    background:
        linear-gradient(160deg, rgba(13,1,32,.38) 0%, rgba(26,5,53,.12) 30%,
                                rgba(26,5,53,.10) 65%, rgba(13,1,32,.38) 100%),
        url("<?php echo esc_url( $bg_url ); ?>") center/cover no-repeat fixed #0c0115 !important;
    min-height: 100vh !important;
    color: #f0e8ff !important;
}

/* ══════════════════════════════════════════════
   2 — STRIP WORDPRESS / ELEMENTOR CHROME
   ══════════════════════════════════════════════ */
.site-header, .site-footer,
.entry-title, .entry-header,
.elementor-location-header,
.elementor-location-footer { display: none !important; }

/* Elementor outer wrappers — transparent */
.elementor-element-2fd07fa9,
.elementor-element-2fd07fa9 > .e-con-inner,
.elementor-section-wrap,
.e-con, .elementor-inner,
.elementor-container,
.elementor-widget-wrap,
.elementor-section,
.elementor-top-section {
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
    max-width: 100% !important;
}

/* ── Kill patch-297 + patch-340 pseudo/header injections ── */
.elementor-element-54e25be::before { content: none !important; display: none !important; }
.elementor-element-cc9e57e { display: none !important; }

/* ══════════════════════════════════════════════
   3 — COLUMN CONTAINER
   ══════════════════════════════════════════════ */
.elementor-element-54e25be,
.elementor-element-54e25be > .e-con-inner {
    background: transparent !important;
    max-width: 640px !important;
    width: 100% !important;
    margin: 0 auto !important;
    padding: 0 1.5rem 6rem !important;
}

/* ══════════════════════════════════════════════
   4 — NEW HEADER BLOCK  (#ex341-header)
   ══════════════════════════════════════════════ */
#ex341-header {
    text-align: center;
    padding: 3rem 0 2.2rem;
}

/* Wordmark — matches homepage EXCREET style */
#ex341-wordmark {
    font-family: 'Poppins', sans-serif;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: .55em;
    text-transform: uppercase;
    color: #C9A84C;
    margin: 0 0 1.6rem;
    line-height: 1;
}

/* Logo ring — matches homepage circular logo */
#ex341-logo {
    width: 82px;
    height: 82px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    margin: 0 auto 1.1rem;
    box-shadow:
        0 0 0 2px rgba(201,168,76,.55),
        0 0 36px rgba(201,168,76,.30),
        0 8px 24px rgba(0,0,0,.55);
}
#ex341-logo-err { display: none; }

/* Tagline — matches "A PRE-CLINICAL WARNING SYSTEM." from homepage */
#ex341-tagline {
    font-family: 'Poppins', sans-serif;
    font-size: .65rem;
    font-weight: 600;
    letter-spacing: .28em;
    text-transform: uppercase;
    color: rgba(255,255,255,.55);
    margin: 0 0 .9rem;
    border-top: 1px solid rgba(201,168,76,.30);
    border-bottom: 1px solid rgba(201,168,76,.30);
    padding: .38em 1.4em;
    display: inline-block;
}

/* Back to Home pill */
#ex341-back-wrap { margin-top: 1.4rem; }
#ex341-back {
    display: inline-flex;
    align-items: center;
    gap: .38em;
    font-family: 'Poppins', sans-serif;
    font-size: .75rem;
    font-weight: 500;
    letter-spacing: .05em;
    color: rgba(201,168,76,.65);
    text-decoration: none;
    border: 1px solid rgba(201,168,76,.22);
    padding: .42rem 1.1rem;
    border-radius: 100px;
    transition: color .2s, border-color .2s, background .2s;
}
#ex341-back:hover {
    color: #C9A84C;
    border-color: rgba(201,168,76,.55);
    background: rgba(201,168,76,.07);
}
#ex341-back svg { flex-shrink:0; transition: transform .2s; }
#ex341-back:hover svg { transform: translateX(-3px); }

/* ══════════════════════════════════════════════
   5 — WELCOME SECTION  (dark, white text)
   ══════════════════════════════════════════════ */

/* Collapse any empty Elementor gap widgets between header and welcome */
.elementor-element-54e25be > .e-con-inner > *:not(#ex341-header):empty,
.elementor-element-54e25be > .e-con-inner > .elementor-widget:not(.elementor-widget-text-editor):not(.elementor-widget-shortcode):empty {
    display: none !important;
}

/* Tighten spacing so welcome text sits directly under the header */
#ex341-header { padding-bottom: 1rem; }

.elementor-element-497295a {
    padding: 0 0 1.8rem !important;
    margin-top: 0 !important;
    background: transparent !important;
}
.elementor-element-497295a p,
.elementor-element-497295a * {
    color: rgba(255,255,255,.75) !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: .92rem !important;
    font-weight: 300 !important;
    line-height: 1.8 !important;
}
.elementor-element-497295a h2,
.elementor-element-497295a h3,
.elementor-element-497295a h1 {
    color: #C9A84C !important;
    font-weight: 600 !important;
    font-size: 1.35rem !important;
    letter-spacing: .02em !important;
    margin-bottom: .6rem !important;
    line-height: 1.3 !important;
}

/* ══════════════════════════════════════════════
   6 — WHITE FORM CARD
   ══════════════════════════════════════════════ */
.forminator-custom-form,
.forminator-ui {
    background: #ffffff !important;
    border: none !important;
    border-radius: 20px !important;
    padding: 0 !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    margin-top: 0 !important;
    box-shadow:
        0 24px 64px rgba(0,0,0,.45),
        0 0 0 1px rgba(201,168,76,.25) !important;
    overflow: hidden !important;
}

/* Gold accent bar at top of card */
.forminator-custom-form::before,
.forminator-ui::before {
    content: '' !important;
    display: block !important;
    height: 4px !important;
    background: linear-gradient(90deg, #3D1060 0%, #C9A84C 40%, #f5d97a 55%, #C9A84C 70%, #3D1060 100%) !important;
    width: 100% !important;
}

/* Inner padding after the gold bar */
.forminator-custom-form .forminator-row:first-of-type,
.forminator-ui .forminator-row:first-of-type { margin-top: 2rem !important; }

.forminator-custom-form > *:not(::before),
.forminator-ui > .forminator-form-row,
.forminator-pagination,
.forminator-field,
.forminator-row,
.forminator-submit-rightside {
    padding-left: 2.2rem !important;
    padding-right: 2.2rem !important;
}

/* ── Field labels (dark, clinical) ── */
.forminator-label,
.forminator-field-label,
.forminator-row label,
.forminator-custom-form label {
    color: #1a0535 !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: .72rem !important;
    font-weight: 600 !important;
    letter-spacing: .12em !important;
    text-transform: uppercase !important;
    margin-bottom: .35rem !important;
    display: block !important;
}

/* ── Field descriptions / hints (muted, clinical gray) ── */
.forminator-description,
.forminator-field-option-description,
.forminator-input-description {
    color: #6b5c80 !important;
    font-size: .76rem !important;
    font-family: 'Poppins', sans-serif !important;
    font-style: italic !important;
    font-weight: 400 !important;
}

/* ── Text inputs, email, number, tel, select, textarea ── */
.forminator-custom-form input[type="text"],
.forminator-custom-form input[type="email"],
.forminator-custom-form input[type="number"],
.forminator-custom-form input[type="tel"],
.forminator-custom-form input[type="url"],
.forminator-custom-form input[type="password"],
.forminator-custom-form select,
.forminator-custom-form textarea,
.forminator-field input,
.forminator-field select,
.forminator-field textarea {
    background: #f9f7fd !important;
    border: 1px solid #d4c4e8 !important;
    border-radius: 10px !important;
    color: #1a0535 !important;
    padding: .75rem 1rem !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: .92rem !important;
    font-weight: 400 !important;
    width: 100% !important;
    box-sizing: border-box !important;
    transition: border-color .2s, box-shadow .2s, background .2s !important;
    -webkit-appearance: none !important;
}
.forminator-custom-form input::placeholder,
.forminator-custom-form textarea::placeholder {
    color: #b0a0c0 !important;
    font-weight: 300 !important;
}
.forminator-custom-form input:focus,
.forminator-custom-form select:focus,
.forminator-custom-form textarea:focus {
    border-color: #C9A84C !important;
    background: #fff !important;
    outline: none !important;
    box-shadow: 0 0 0 3px rgba(201,168,76,.14) !important;
}

/* ── Select arrow (dark purple) ── */
.forminator-custom-form select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%233D1060'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right .9rem center !important;
    padding-right: 2.2rem !important;
    cursor: pointer !important;
}
.forminator-custom-form select option {
    background: #fff !important;
    color: #1a0535 !important;
}

/* ── Radio / checkbox labels (dark) ── */
.forminator-custom-form .forminator-checkbox label,
.forminator-custom-form .forminator-radio label {
    color: #2d1a4a !important;
    font-size: .88rem !important;
    font-weight: 400 !important;
    text-transform: none !important;
    letter-spacing: 0 !important;
}
.forminator-custom-form input[type="radio"],
.forminator-custom-form input[type="checkbox"] {
    accent-color: #C9A84C !important;
    width: auto !important;
}

/* ── Section headings inside form ── */
.forminator-custom-form .forminator-title h1,
.forminator-custom-form .forminator-title h2,
.forminator-custom-form .forminator-title h3 {
    color: #3D1060 !important;
    font-family: 'Poppins', sans-serif !important;
    font-weight: 700 !important;
    font-size: 1rem !important;
    text-align: left !important;
    letter-spacing: .04em !important;
}
.forminator-custom-form .forminator-subtitle {
    color: #6b5c80 !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: .82rem !important;
}

/* ── Section dividers ── */
.forminator-row {
    border-bottom: 1px solid rgba(61,16,96,.07) !important;
    padding-top: 1.1rem !important;
    padding-bottom: 1.1rem !important;
}
.forminator-row:last-child { border-bottom: none !important; }

/* ── Progress bar (gold on light track) ── */
.forminator-pagination .forminator-pagination--title {
    color: #3D1060 !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: .75rem !important;
    font-weight: 600 !important;
    letter-spacing: .08em !important;
}
.forminator-pagination--bar .forminator-step {
    background: #ede4f7 !important;
}
.forminator-pagination--bar .forminator-step--active { background: #C9A84C !important; }
.forminator-pagination--bar .forminator-step--completed { background: rgba(201,168,76,.45) !important; }
.forminator-pagination--nav .forminator-step { color: #b0a0c0 !important; }
.forminator-pagination--nav .forminator-step.forminator-step--completed,
.forminator-pagination--nav .forminator-step.forminator-step--active { color: #C9A84C !important; }
.forminator-ui.forminator-loaded .forminator-pagination--bar--fill { background: #C9A84C !important; }

/* ── SUBMIT button (gold pill) ── */
.forminator-btn,
.forminator-custom-form .forminator-btn-submit,
.forminator-custom-form button[type="submit"] {
    background: linear-gradient(135deg, #C9A84C 0%, #a8873a 100%) !important;
    color: #1a0535 !important;
    border: none !important;
    border-radius: 50px !important;
    padding: .9rem 3rem !important;
    font-weight: 700 !important;
    font-size: .88rem !important;
    letter-spacing: .12em !important;
    text-transform: uppercase !important;
    cursor: pointer !important;
    font-family: 'Poppins', sans-serif !important;
    transition: opacity .2s, transform .15s, box-shadow .2s !important;
    box-shadow: 0 4px 22px rgba(201,168,76,.40) !important;
}
.forminator-btn:hover,
.forminator-custom-form .forminator-btn-submit:hover {
    opacity: .9 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 28px rgba(201,168,76,.55) !important;
}

/* ── Previous / ghost button ── */
.forminator-btn-back,
.forminator-btn--ghost {
    background: transparent !important;
    border: 1px solid rgba(61,16,96,.35) !important;
    color: #3D1060 !important;
    border-radius: 50px !important;
    padding: .7rem 1.8rem !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: .82rem !important;
    letter-spacing: .06em !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    transition: border-color .2s, background .2s !important;
}
.forminator-btn-back:hover,
.forminator-btn--ghost:hover {
    border-color: #C9A84C !important;
    color: #C9A84C !important;
}

/* ── Form footer padding ── */
.forminator-submit-rightside,
.forminator-submit-container {
    padding: 1.5rem 2.2rem 2.2rem !important;
}

/* ── Validation errors ── */
.forminator-error .forminator-input-errors,
.forminator-input-errors .forminator-error {
    color: #c0392b !important;
    font-size: .75rem !important;
    font-family: 'Poppins', sans-serif !important;
}
.forminator-has-error input,
.forminator-has-error textarea,
.forminator-has-error select {
    border-color: rgba(192,57,43,.55) !important;
    background: #fff5f5 !important;
}

/* ── Success message ── */
.forminator-response-output {
    background: #f0faf4 !important;
    border: 1px solid #a8d5b5 !important;
    border-radius: 10px !important;
    color: #1a5c35 !important;
    padding: 1rem 1.4rem !important;
    font-family: 'Poppins', sans-serif !important;
    margin: 1rem 2.2rem !important;
}

/* ── Mobile ── */
@media (max-width: 640px) {
    .elementor-element-54e25be,
    .elementor-element-54e25be > .e-con-inner {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
    }
    #ex341-header { padding-top: 2rem; }
    #ex341-logo { width: 68px; height: 68px; }
}

</style>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   HEADER INJECT — replaces patch-340's injection with the homepage-matched version
   ════════════════════════════════════════════════════════════════════════════ */
add_action( 'wp_footer', 'excreet_341_header_inject', 100 );
function excreet_341_header_inject(): void {
    if ( ! is_page( 'member-intake-form' ) ) { return; }

    $logo_url = 'https://excreet.com/wp-content/uploads/excreet-hero-logo.png';
    $home_url = esc_url( home_url( '/' ) );
    ?>
<script id="excreet-341-inject">
(function () {
    'use strict';

    var LOGO = <?php echo wp_json_encode( $logo_url ); ?>;
    var HOME = <?php echo wp_json_encode( $home_url ); ?>;

    function inject() {
        /* Remove any header injected by patch-340 */
        var old = document.getElementById('ex340-intake-header');
        if (old) { old.remove(); }

        /* Target the Elementor inner column that holds the form */
        var col = document.querySelector(
            '.elementor-element-54e25be > .e-con-inner'
        ) || document.querySelector('.elementor-element-54e25be');

        if (!col) { return; }
        if (document.getElementById('ex341-header')) { return; }

        var h = document.createElement('div');
        h.id = 'ex341-header';
        h.innerHTML =
            /* Wordmark */
            '<p id="ex341-wordmark">E X C R E E T</p>' +

            /* Logo */
            '<img id="ex341-logo" src="' + LOGO + '" alt="Excreet" ' +
                 'onerror="this.style.display=\'none\'">' +

            /* Tagline — same wording as homepage hero */
            '<p id="ex341-tagline">A&nbsp;Pre&#8209;Clinical&nbsp;Warning&nbsp;System</p>' +

            /* Back to Home */
            '<div id="ex341-back-wrap">' +
                '<a id="ex341-back" href="' + HOME + '">' +
                    '<svg width="13" height="13" viewBox="0 0 16 16" fill="none">' +
                        '<path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.6" ' +
                              'stroke-linecap="round" stroke-linejoin="round"/>' +
                    '</svg>' +
                    'Back to Home' +
                '</a>' +
            '</div>';

        col.insertBefore(h, col.firstChild);
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
