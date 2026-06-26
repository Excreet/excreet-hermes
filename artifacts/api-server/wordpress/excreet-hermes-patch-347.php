<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.4.7
 * Description: Global language selector v3.4.7
 *
 *   A — Google Translate widget on all WP-served pages
 *       Injects the Google Translate dropdown into every page header
 *       via wp_head / admin_bar workaround.
 *       Styled to match Excreet brand palette.
 *       Suppresses Google's top-of-page translation bar.
 *
 *   B — TranslatePress coexistence
 *       TranslatePress continues to serve high-quality EN/ES routes
 *       (/es/ prefix). Google Translate handles all other languages
 *       as an overlay on all WP-served pages.
 *
 * Version: 3.4.7
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ═══════════════════════════════════════════════════════════════════════════
   A — INJECT GOOGLE TRANSLATE ON ALL WP PAGES
   ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_head', 'excreet_347_translate_styles', 100 );
function excreet_347_translate_styles(): void {
    ?>
<style id="ex347-translate">
/* ── Google Translate widget ─────────────────────────── */
.goog-te-gadget{font-size:0!important;line-height:0!important}
.goog-logo-link,.goog-te-gadget>span{display:none!important}
.goog-te-combo{
  appearance:none;-webkit-appearance:none;
  display:inline-block;
  padding:6px 14px;
  background:rgba(255,255,255,.93);
  color:#56075E;
  border:2px solid #56075E;
  border-radius:30px;
  font-size:13px;
  font-weight:600;
  cursor:pointer;
  outline:none;
  font-family:inherit;
  white-space:nowrap;
  max-width:160px;
  transition:background .2s;
}
.goog-te-combo:hover{background:#f5d6ff}
/* Suppress Google's translation toolbar */
.goog-te-banner-frame.skiptranslate{display:none!important}
body.translated-ltr,body.translated-rtl{margin-top:0!important;top:0!important}
/* Floating language button — bottom-right corner */
#ex347-lang-float{
  position:fixed;
  bottom:24px;
  right:24px;
  z-index:99999;
  display:flex;
  align-items:center;
  gap:8px;
  background:linear-gradient(135deg,#1E0538,#3A0A75);
  border:1.5px solid rgba(201,168,76,.7);
  border-radius:50px;
  padding:8px 16px 8px 12px;
  box-shadow:0 4px 24px rgba(10,2,22,.65),0 0 0 1px rgba(201,168,76,.2);
  cursor:default;
}
#ex347-lang-float svg{flex-shrink:0;opacity:.85}
#ex347-lang-float .goog-te-combo{
  background:transparent;
  color:rgba(240,232,255,.9);
  border:none;
  padding:0 4px;
  font-size:13px;
  max-width:140px;
}
#ex347-lang-float .goog-te-combo option{color:#1a0535;background:#fff}
#ex347-lang-float .goog-te-combo:hover{background:transparent}
/* Hide Google widget attribution inside the float */
#ex347-lang-float .goog-te-gadget>span,
#ex347-lang-float .goog-logo-link{display:none!important}
</style>
    <?php
}

add_action( 'wp_footer', 'excreet_347_translate_widget', 100 );
function excreet_347_translate_widget(): void {
    ?>
<div id="ex347-lang-float" aria-label="Select language">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(201,168,76,0.9)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="10"/>
    <line x1="2" y1="12" x2="22" y2="12"/>
    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
  </svg>
  <div id="ex347-translate-wp"></div>
</div>
<script>
function googleTranslateElementInit(){
  new google.translate.TranslateElement(
    {pageLanguage:'en', autoDisplay:false},
    'ex347-translate-wp'
  );
}
</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async></script>
    <?php
}
