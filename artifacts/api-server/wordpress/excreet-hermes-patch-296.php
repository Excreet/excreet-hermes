<?php
/**
 * Plugin Name: Excreet Hermes Patch 2.9.6
 * Description: Flow repair + Language picker.
 *
 *   FIX 1 — patch-272 hardcoded /intake-processing/ as the post-intake redirect.
 *            That page was deleted. Overrides Forminator AJAX response to /welcome-member/.
 *
 *   FIX 2 — patch-291 redirected non-members to /membership-payment-page/ (deleted).
 *            EX296_PAYMENT_URL updated from deleted MemberPress /register/ URL
 *            to PMPro /membership-checkout/?level=1.
 *
 *   FIX 3 — Removes deleted 'intake-processing' from protected slugs list.
 *
 *   FIX 4 — Branded MemberPress login page CSS (superseded by patch-307 full override).
 *
 *   FIX 5 — Language button: wires homepage Language buttons (widget IDs f8029f1,
 *            7d1cae6) to an elegant Excreet-branded language picker dropdown.
 *            Supports English (default) and Spanish (/es/).
 *            Hides the TranslatePress bottom-right floater (button is the UI instead).
 *
 * Version: 2.9.6a
 * Load order: loads after patch-295 (alphabetically last mu-plugin).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Constants ─────────────────────────────────────────────────────────────────

define( 'EX296_PAYMENT_URL',    '/membership-checkout/?level=1' );
define( 'EX296_WELCOME_URL',    '/welcome-member/' );
// EX296_PRODUCT_ID (legacy MemberPress post ID 171) — no longer used;
// PMPro membership check uses pmpro_hasMembershipLevel(null, $user_id) instead.

define( 'EX296_PROTECTED_SLUGS', serialize( [
    'member-intake-form',
    'member-dashboard',
    'healing-command-center',
] ) );

// ── FIX 1: Override patch-272 Forminator AJAX redirect ───────────────────────

add_action( 'wp_loaded', function (): void {
    remove_filter(
        'forminator_custom_form_ajax_submit_response',
        'excreet_patch_fix_response',
        999
    );
}, 5 );

add_filter( 'forminator_custom_form_ajax_submit_response', 'excreet_296_fix_response', 999, 2 );

function excreet_296_fix_response( $response, $module_id ) {
    if ( (int) $module_id !== EXCREET_FORM_ID ) {
        return $response;
    }

    return [
        'success' => true,
        'type'    => 'success',
        'form_id' => EXCREET_FORM_ID,
        'message' => '',
        'behav'   => 'behaviour-redirect',
        'url'     => home_url( EX296_WELCOME_URL ),
        'newtab'  => 'sametab',
    ];
}

// ── FIX 2 & 3: Override patch-291 member gating ──────────────────────────────

add_action( 'wp_loaded', function (): void {
    remove_action( 'template_redirect', 'excreet_291_gate_pages', 1 );
}, 5 );

add_action( 'template_redirect', 'excreet_296_gate_pages', 1 );

function excreet_296_is_member(): bool {
    if ( ! is_user_logged_in() ) {
        return false;
    }
    // WordPress admins always pass — never bounce them to the payment page.
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }
    // PMPro not installed — hard block (same as MemberPress behaviour)
    if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
        return false;
    }
    // null = any active PMPro membership level
    return (bool) pmpro_hasMembershipLevel( null, get_current_user_id() );
}

function excreet_296_gate_pages(): void {
    if ( ! is_singular( 'page' ) ) {
        return;
    }

    $slug      = get_post_field( 'post_name', get_queried_object_id() );
    $protected = unserialize( EX296_PROTECTED_SLUGS );

    if ( ! in_array( $slug, $protected, true ) ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( get_permalink() ) );
        exit;
    }

    if ( ! excreet_296_is_member() ) {
        wp_safe_redirect( home_url( EX296_PAYMENT_URL ) );
        exit;
    }
}

// ── FIX 4: Branded MemberPress login page ────────────────────────────────────

add_action( 'wp_head', 'excreet_296_login_styles' );

function excreet_296_login_styles(): void {
    // Scoped to body.pmpro_login — PMPro adds this class on its login page.
    // No conditional page check needed; CSS is scoped and only applies there.
    ?>
    <style>
    body.pmpro_login {
        background: linear-gradient(135deg, #3D1060 0%, #6B2FA0 60%, #1a0535 100%) !important;
        min-height: 100vh;
    }
    body.pmpro_login .pmpro_content,
    body.pmpro_login .pmpro_login_wrap,
    body.pmpro_login #loginform,
    body.pmpro_login .pmpro_form {
        background: rgba(255,255,255,0.06) !important;
        border: 1px solid rgba(201,168,76,0.3) !important;
        border-radius: 16px !important;
        padding: 2.5rem !important;
        max-width: 440px !important;
        margin: 4rem auto !important;
        backdrop-filter: blur(12px) !important;
    }
    body.pmpro_login label,
    body.pmpro_login .pmpro_form label {
        color: rgba(255,255,255,0.8) !important;
        font-family: 'Georgia', serif !important;
        font-size: 0.85rem !important;
        letter-spacing: 0.05em !important;
        text-transform: uppercase !important;
    }
    body.pmpro_login input[type="text"],
    body.pmpro_login input[type="email"],
    body.pmpro_login input[type="password"] {
        background: rgba(255,255,255,0.1) !important;
        border: 1px solid rgba(201,168,76,0.4) !important;
        border-radius: 8px !important;
        color: #ffffff !important;
        padding: 0.75rem 1rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
        font-size: 1rem !important;
    }
    body.pmpro_login input[type="text"]::placeholder,
    body.pmpro_login input[type="password"]::placeholder {
        color: rgba(255,255,255,0.35) !important;
    }
    body.pmpro_login input[type="submit"],
    body.pmpro_login .pmpro_btn-submit,
    body.pmpro_login .button-primary,
    body.pmpro_login input[name="wp-submit"] {
        background: linear-gradient(135deg, #C9A84C, #a8873a) !important;
        color: #1a0535 !important;
        border: none !important;
        border-radius: 50px !important;
        padding: 0.85rem 2.5rem !important;
        font-weight: 700 !important;
        font-size: 1rem !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
        cursor: pointer !important;
        width: 100% !important;
        margin-top: 1rem !important;
        transition: opacity 0.2s !important;
    }
    body.pmpro_login input[type="submit"]:hover,
    body.pmpro_login .button-primary:hover { opacity: 0.88 !important; }
    body.pmpro_login a { color: #C9A84C !important; }
    body.pmpro_login .entry-title,
    body.pmpro_login h1.page-title {
        color: #ffffff !important;
        text-align: center !important;
        font-family: 'Georgia', serif !important;
        letter-spacing: 0.1em !important;
    }
    body.pmpro_login .site-header,
    body.pmpro_login .site-footer,
    body.pmpro_login .elementor-location-header,
    body.pmpro_login .elementor-location-footer { display: none !important; }
    body.pmpro_login .pmpro_content::before,
    body.pmpro_login .pmpro_login_wrap::before,
    body.pmpro_login #loginform::before {
        content: 'EXCREET';
        display: block;
        text-align: center;
        color: #C9A84C;
        font-family: 'Georgia', serif;
        font-size: 1.6rem;
        letter-spacing: 0.4em;
        margin-bottom: 1.5rem;
        font-weight: 400;
    }
    </style>
    <?php
}

// ── FIX 5: Language picker ────────────────────────────────────────────────────
//
// Wires the two homepage Language buttons (Elementor widget IDs f8029f1 / 7d1cae6)
// to a branded dropdown. Detects current language from TRP cookie/URL and shows
// the opposite language as the switch target.
// Also hides the TRP bottom-right floating switcher (button is the canonical UI).

add_action( 'wp_head', 'excreet_296_hide_trp_floater' );

function excreet_296_hide_trp_floater(): void {
    ?>
    <style>
    /* Hide default TranslatePress floating switcher — Language button is the UI */
    #trp-floater-language-switcher,
    .trp-floater,
    [id^="trp-floater"] { display: none !important; }
    </style>
    <?php
}

add_action( 'wp_footer', 'excreet_296_language_picker' );

function excreet_296_language_picker(): void {
    // Build language data from TRP settings
    $trp      = get_option( 'trp_settings' );
    $slugs    = $trp['url-slugs'] ?? [ 'en_US' => 'en', 'es_ES' => 'es' ];
    $default  = $trp['default-language'] ?? 'en_US';
    $langs    = $trp['translation-languages'] ?? [ 'en_US', 'es_ES' ];

    // Language metadata
    $meta = [
        'en_US' => [ 'label' => 'English',  'native' => 'English',  'flag' => '🇺🇸', 'slug' => $slugs['en_US'] ?? 'en' ],
        'es_ES' => [ 'label' => 'Spanish',  'native' => 'Español',  'flag' => '🇪🇸', 'slug' => $slugs['es_ES'] ?? 'es' ],
        'fr_FR' => [ 'label' => 'French',   'native' => 'Français', 'flag' => '🇫🇷', 'slug' => $slugs['fr_FR'] ?? 'fr' ],
        'pt_BR' => [ 'label' => 'Portuguese','native' => 'Português','flag' => '🇧🇷', 'slug' => $slugs['pt_BR'] ?? 'pt' ],
        'de_DE' => [ 'label' => 'German',   'native' => 'Deutsch',  'flag' => '🇩🇪', 'slug' => $slugs['de_DE'] ?? 'de' ],
        'zh_CN' => [ 'label' => 'Chinese',  'native' => '中文',     'flag' => '🇨🇳', 'slug' => $slugs['zh_CN'] ?? 'zh' ],
    ];

    // Build items list (only configured languages)
    $items_json = [];
    foreach ( $langs as $code ) {
        $m    = $meta[ $code ] ?? [ 'label' => $code, 'native' => $code, 'flag' => '🌐', 'slug' => 'en' ];
        $url  = ( $code === $default )
            ? home_url( '/' )
            : home_url( '/' . $m['slug'] . '/' );
        $items_json[] = [
            'code'   => $code,
            'flag'   => $m['flag'],
            'native' => $m['native'],
            'label'  => $m['label'],
            'url'    => $url,
        ];
    }

    $json = wp_json_encode( $items_json );
    ?>
    <style>
    .excreet-lang-picker {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 99998;
        display: none;
        align-items: flex-start;
        justify-content: flex-start;
        padding: 120px 0 0 24px;
    }
    .excreet-lang-picker.open { display: flex; }
    .excreet-lang-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.45);
        backdrop-filter: blur(4px);
        z-index: -1;
    }
    .excreet-lang-dropdown {
        background: rgba(27, 5, 50, 0.96);
        border: 1px solid rgba(201,168,76,0.4);
        border-radius: 14px;
        padding: 0.5rem;
        min-width: 200px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        animation: excreetLangFadeIn 0.18s ease;
    }
    @keyframes excreetLangFadeIn {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .excreet-lang-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0.7rem 1rem;
        border-radius: 9px;
        cursor: pointer;
        transition: background 0.15s;
        text-decoration: none !important;
        color: rgba(255,255,255,0.9) !important;
        font-family: 'Georgia', serif;
        font-size: 0.95rem;
        letter-spacing: 0.03em;
    }
    .excreet-lang-item:hover {
        background: rgba(201,168,76,0.15);
        color: #C9A84C !important;
    }
    .excreet-lang-item.active {
        color: #C9A84C !important;
        font-weight: 600;
    }
    .excreet-lang-item .lang-flag { font-size: 1.3rem; line-height: 1; }
    .excreet-lang-item .lang-name { display: flex; flex-direction: column; }
    .excreet-lang-item .lang-native { font-size: 0.95rem; }
    .excreet-lang-item .lang-label { font-size: 0.72rem; opacity: 0.55; letter-spacing: 0.05em; text-transform: uppercase; }
    .excreet-lang-item .lang-check { margin-left: auto; color: #C9A84C; font-size: 0.8rem; }
    .excreet-lang-divider {
        height: 1px;
        background: rgba(201,168,76,0.15);
        margin: 0.3rem 0.5rem;
    }
    .excreet-lang-header {
        padding: 0.5rem 1rem 0.3rem;
        font-size: 0.68rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(201,168,76,0.6);
        font-family: 'Georgia', serif;
    }
    </style>

    <div class="excreet-lang-picker" id="excreetLangPicker" role="dialog" aria-label="Select Language">
        <div class="excreet-lang-overlay" id="excreetLangOverlay"></div>
        <div class="excreet-lang-dropdown">
            <div class="excreet-lang-header">Select Language</div>
            <div class="excreet-lang-divider"></div>
            <div id="excreetLangItems"></div>
        </div>
    </div>

    <script>
    (function() {
        var LANGUAGES = <?php echo $json; ?>;
        var CURRENT_URL = window.location.href;

        function getCurrentLang() {
            for (var i = 0; i < LANGUAGES.length; i++) {
                var lang = LANGUAGES[i];
                if (lang.url && CURRENT_URL.indexOf('/' + (lang.slug || '') + '/') !== -1 && lang.slug !== 'en') {
                    return lang.code;
                }
            }
            return LANGUAGES[0] ? LANGUAGES[0].code : 'en_US';
        }

        function buildItems() {
            var container = document.getElementById('excreetLangItems');
            if (!container) return;
            var currentCode = getCurrentLang();
            var html = '';
            for (var i = 0; i < LANGUAGES.length; i++) {
                var lang = LANGUAGES[i];
                var isActive = lang.code === currentCode;
                html += '<a href="' + lang.url + '" class="excreet-lang-item' + (isActive ? ' active' : '') + '" data-lang="' + lang.code + '">' +
                    '<span class="lang-flag">' + lang.flag + '</span>' +
                    '<span class="lang-name">' +
                        '<span class="lang-native">' + lang.native + '</span>' +
                        '<span class="lang-label">' + lang.label + '</span>' +
                    '</span>' +
                    (isActive ? '<span class="lang-check">✓</span>' : '') +
                    '</a>';
                if (i < LANGUAGES.length - 1) {
                    html += '<div class="excreet-lang-divider"></div>';
                }
            }
            container.innerHTML = html;
        }

        function openPicker() {
            buildItems();
            document.getElementById('excreetLangPicker').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closePicker() {
            document.getElementById('excreetLangPicker').classList.remove('open');
            document.body.style.overflow = '';
        }

        function wireLanguageButtons() {
            // Target by Elementor widget ID classes (desktop + mobile variants)
            var selectors = [
                '.elementor-element-f8029f1 .elementor-button',
                '.elementor-element-7d1cae6 .elementor-button',
            ];
            selectors.forEach(function(sel) {
                var btns = document.querySelectorAll(sel);
                btns.forEach(function(btn) {
                    btn.style.cursor = 'pointer';
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openPicker();
                    });
                });
            });
            // Also target any button whose text is exactly "Language"
            document.querySelectorAll('.elementor-button').forEach(function(btn) {
                var txt = btn.textContent ? btn.textContent.trim() : '';
                if (txt === 'Language') {
                    btn.style.cursor = 'pointer';
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openPicker();
                    });
                }
            });
        }

        // Close on overlay click or Escape
        document.addEventListener('DOMContentLoaded', function() {
            var overlay = document.getElementById('excreetLangOverlay');
            if (overlay) overlay.addEventListener('click', closePicker);

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closePicker();
            });

            wireLanguageButtons();
        });

        // Also try after Elementor frontend is ready (handles lazy-loaded sections)
        document.addEventListener('DOMContentLoaded', function() {
            if (window.elementorFrontend) {
                elementorFrontend.hooks.addAction('frontend/element_ready/button.default', function() {
                    wireLanguageButtons();
                });
            }
        });
    })();
    </script>
    <?php
}
