<?php
/**
 * Plugin Name: Excreet Hermes — Patch 351 (Doctor Visit Summary)
 * Description: Phase 16 — When a member's Body Check result is tier "protocol"
 *              or "alarm" (Vitality Score ≤ 50), surfaces a "Prepare Doctor
 *              Visit Summary" button on the HCC page. On click, fetches the
 *              clinical summary from Hermes and renders a formatted, printable
 *              report the member can bring to their provider.
 * Version: 3.5.1
 * Author: Excreet Engineering
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EX351_HCC_PAGE_ID',  257 );
define( 'EX351_HERMES_BASE',  'https://excreet.com/api/hermes' );
define( 'EX351_PURPLE',       '#6B2FA0' );
define( 'EX351_PURPLE_DARK',  '#3D1060' );
define( 'EX351_GOLD',         '#F5C518' );

add_action( 'wp_footer',  'ex351_inject_button',  85 );
add_action( 'wp_head',    'ex351_styles'              );
add_action( 'wp_footer',  'ex351_modal_and_script', 99 );
add_action( 'wp_ajax_excreet_351_clinical_summary', 'ex351_ajax_handler' );

/* ── Styles ──────────────────────────────────────────────────────────────── */

function ex351_styles(): void {
    if ( ! is_page( EX351_HCC_PAGE_ID ) ) return;
    ?>
<style id="ex351-styles">
/* ── Doctor Summary trigger button ── */
.ex351-trigger-wrap {
    margin: 28px auto 0;
    text-align: center;
}
.ex351-trigger-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, <?php echo EX351_PURPLE; ?>, <?php echo EX351_PURPLE_DARK; ?>);
    color: #fff !important;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 0.04em;
    padding: 13px 28px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    box-shadow: 0 4px 18px rgba(107,47,160,0.35);
    transition: opacity .2s, transform .2s;
    text-decoration: none !important;
}
.ex351-trigger-btn:hover { opacity: .88; transform: translateY(-1px); }
.ex351-trigger-btn svg { flex-shrink: 0; }

/* ── Modal overlay ── */
.ex351-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10,4,20,.72);
    z-index: 99990;
    overflow-y: auto;
    padding: 40px 16px;
}
.ex351-overlay.open { display: flex; align-items: flex-start; justify-content: center; }

/* ── Modal card ── */
.ex351-modal {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 760px;
    padding: 40px 44px;
    position: relative;
    font-family: 'DM Sans', sans-serif;
    color: #1a0a2e;
}
.ex351-modal-close {
    position: absolute;
    top: 16px; right: 18px;
    background: none;
    border: none;
    font-size: 26px;
    color: #888;
    cursor: pointer;
    line-height: 1;
}
.ex351-modal-close:hover { color: #333; }

/* ── Report header ── */
.ex351-report-header {
    border-bottom: 2px solid <?php echo EX351_GOLD; ?>;
    padding-bottom: 18px;
    margin-bottom: 24px;
}
.ex351-wordmark {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 28px;
    font-weight: 700;
    color: <?php echo EX351_PURPLE; ?>;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin: 0 0 4px;
}
.ex351-report-meta {
    font-size: 13px;
    color: #666;
}

/* ── Score badge ── */
.ex351-score-row {
    display: flex;
    align-items: center;
    gap: 18px;
    background: #f9f5ff;
    border-left: 4px solid <?php echo EX351_PURPLE; ?>;
    border-radius: 6px;
    padding: 14px 18px;
    margin-bottom: 22px;
}
.ex351-score-number {
    font-size: 42px;
    font-weight: 700;
    color: <?php echo EX351_PURPLE; ?>;
    line-height: 1;
    font-family: 'Cormorant Garamond', Georgia, serif;
}
.ex351-score-label { font-size: 13px; color: #555; }
.ex351-tier-pill {
    margin-left: auto;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
}
.ex351-tier-protocol { background: #fff3cd; color: #856404; }
.ex351-tier-alarm    { background: #f8d7da; color: #842029; }

/* ── Section headings ── */
.ex351-section {
    margin-bottom: 24px;
}
.ex351-section-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 17px;
    font-weight: 700;
    color: <?php echo EX351_PURPLE; ?>;
    text-transform: uppercase;
    letter-spacing: .06em;
    border-bottom: 1px solid #e8e0f0;
    padding-bottom: 6px;
    margin: 0 0 12px;
}
.ex351-trajectory {
    font-size: 15px;
    line-height: 1.7;
    color: #2d1a4a;
    font-style: italic;
    margin: 0;
}

/* ── Lists ── */
.ex351-list {
    margin: 0;
    padding-left: 0;
    list-style: none;
}
.ex351-list li {
    padding: 7px 0 7px 22px;
    border-bottom: 1px solid #f0ebf8;
    font-size: 14px;
    line-height: 1.6;
    position: relative;
}
.ex351-list li:last-child { border-bottom: none; }
.ex351-list li::before {
    content: '→';
    position: absolute;
    left: 0;
    color: <?php echo EX351_GOLD; ?>;
    font-weight: 700;
}
.ex351-list.red li::before { content: '⚠'; color: #dc3545; }

/* ── Ministry prompt ── */
.ex351-ministry-prompt {
    background: linear-gradient(135deg, <?php echo EX351_PURPLE_DARK; ?>, <?php echo EX351_PURPLE; ?>);
    color: #fff;
    border-radius: 8px;
    padding: 20px 24px;
    margin-top: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.ex351-ministry-prompt p {
    margin: 0;
    font-size: 14px;
    line-height: 1.6;
    flex: 1;
}
.ex351-ministry-link {
    background: <?php echo EX351_GOLD; ?>;
    color: #1a0a2e !important;
    font-weight: 700;
    font-size: 13px;
    padding: 10px 20px;
    border-radius: 6px;
    white-space: nowrap;
    text-decoration: none !important;
}

/* ── Disclaimer ── */
.ex351-disclaimer {
    font-size: 11px;
    color: #999;
    border-top: 1px solid #eee;
    padding-top: 14px;
    margin-top: 20px;
    line-height: 1.6;
}

/* ── Action row ── */
.ex351-action-row {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid #eee;
    flex-wrap: wrap;
}
.ex351-print-btn {
    background: <?php echo EX351_GOLD; ?>;
    color: #1a0a2e !important;
    font-weight: 700;
    font-size: 14px;
    padding: 11px 26px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.ex351-print-btn:hover { opacity: .88; }

/* ── Loading state ── */
.ex351-loading {
    text-align: center;
    padding: 48px 0;
    color: <?php echo EX351_PURPLE; ?>;
    font-size: 15px;
}

/* ── Print styles ── */
@media print {
    .ex351-overlay { display: block !important; position: static; padding: 0; background: none; }
    .ex351-modal { box-shadow: none; padding: 0; max-width: 100%; }
    .ex351-modal-close,
    .ex351-trigger-wrap,
    .ex351-action-row,
    .ex351-ministry-prompt { display: none !important; }
    body > *:not(.ex351-overlay) { display: none !important; }
}
</style>
    <?php
}

/* ── Trigger button injection ─────────────────────────────────────────────── */

function ex351_inject_button(): void {
    if ( ! is_page( EX351_HCC_PAGE_ID ) ) return;
    if ( ! is_user_logged_in() ) return;
    ?>
<div class="ex351-trigger-wrap" id="ex351-trigger-wrap" style="display:none;">
    <button class="ex351-trigger-btn" id="ex351-open-btn" type="button">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
        </svg>
        Prepare Doctor Visit Summary
    </button>
    <p style="font-size:12px;color:#aaa;margin-top:8px;">
        Your result pattern warrants a clinical conversation. This report formats your findings for your provider.
    </p>
</div>
    <?php
}

/* ── Modal markup ─────────────────────────────────────────────────────────── */

function ex351_modal_and_script(): void {
    if ( ! is_page( EX351_HCC_PAGE_ID ) ) return;
    if ( ! is_user_logged_in() ) return;

    $member_id = get_current_user_id();
    $nonce     = wp_create_nonce( 'ex351_clinical_nonce' );
    ?>
<div class="ex351-overlay" id="ex351-overlay" role="dialog" aria-modal="true" aria-label="Doctor Visit Summary">
    <div class="ex351-modal" id="ex351-modal">
        <button class="ex351-modal-close" id="ex351-close-btn" aria-label="Close">&times;</button>
        <div id="ex351-report-content">
            <div class="ex351-loading">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#6B2FA0" stroke-width="2"
                     style="animation:spin 1s linear infinite;display:inline-block">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                </svg>
                <p style="margin-top:12px;">Preparing your clinical summary&hellip;</p>
            </div>
        </div>
    </div>
</div>

<style>@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}</style>

<script>
(function() {
    var memberId = <?php echo (int) $member_id; ?>;
    var nonce    = <?php echo wp_json_encode( $nonce ); ?>;
    var loaded   = false;

    function openModal() {
        document.getElementById('ex351-overlay').classList.add('open');
        document.body.style.overflow = 'hidden';
        if (!loaded) { loadReport(); }
    }

    function closeModal() {
        document.getElementById('ex351-overlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    document.getElementById('ex351-close-btn').addEventListener('click', closeModal);
    document.getElementById('ex351-overlay').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    var openBtn = document.getElementById('ex351-open-btn');
    if (openBtn) {
        openBtn.addEventListener('click', openModal);
    }

    function loadReport() {
        var fd = new FormData();
        fd.append('action', 'excreet_351_clinical_summary');
        fd.append('nonce',  nonce);
        fd.append('member_id', memberId);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                loaded = true;
                if (data.success) {
                    renderReport(data.data);
                } else {
                    document.getElementById('ex351-report-content').innerHTML =
                        '<p style="color:#c00;text-align:center;padding:32px;">' +
                        (data.data || 'Could not load your summary. Please try again later.') + '</p>';
                }
            })
            .catch(function() {
                document.getElementById('ex351-report-content').innerHTML =
                    '<p style="color:#c00;text-align:center;padding:32px;">Network error. Please try again.</p>';
            });
    }

    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function listItems(arr, cls) {
        if (!arr || !arr.length) return '<li>None on file</li>';
        return arr.map(function(s) { return '<li>' + esc(s) + '</li>'; }).join('');
    }

    function renderReport(d) {
        var date = new Date(d.submittedAt).toLocaleDateString('en-US',
            { year: 'numeric', month: 'long', day: 'numeric' });

        var tierClass  = d.tier === 'alarm' ? 'ex351-tier-alarm' : 'ex351-tier-protocol';
        var tierLabel  = d.tier === 'alarm' ? 'Priority Flag' : 'Clinical Pattern';

        var medSection = '';
        if (d.medicalPath) {
            medSection = '<div class="ex351-section">' +
                '<h3 class="ex351-section-title">Questions to Bring to Your Provider</h3>' +
                '<ul class="ex351-list">' + listItems(d.medicalPath.questionsToAsk) + '</ul>' +
            '</div>' +
            '<div class="ex351-section">' +
                '<h3 class="ex351-section-title">Lab Tests to Request by Name</h3>' +
                '<ul class="ex351-list">' + listItems(d.medicalPath.labTestsToRequest) + '</ul>' +
            '</div>' +
            '<div class="ex351-section">' +
                '<h3 class="ex351-section-title">Red Flags — Seek Urgent Care If You Notice</h3>' +
                '<ul class="ex351-list red">' + listItems(d.medicalPath.redFlagsToWatch) + '</ul>' +
            '</div>';
        }

        var ministrySection = '';
        if (d.ministryPath) {
            ministrySection = '<div class="ex351-ministry-prompt">' +
                '<p><strong>Ministry of Healing recommendation:</strong> ' +
                esc(d.ministryPath.signalCategory) + ' — ' +
                (d.ministryPath.approach || []).map(esc).join(' ') + '</p>' +
                '<a href="/healing-command-center/#ministry" class="ex351-ministry-link">Open Ministry &rarr;</a>' +
            '</div>';
        }

        var html =
            '<div class="ex351-report-header">' +
                '<p class="ex351-wordmark">Excreet</p>' +
                '<p class="ex351-report-meta">Doctor Visit Summary &nbsp;·&nbsp; Generated ' +
                esc(new Date(d.generatedAt).toLocaleDateString('en-US',
                    { year: 'numeric', month: 'long', day: 'numeric' })) +
                ' &nbsp;·&nbsp; Based on check-in from ' + esc(date) + '</p>' +
            '</div>' +
            '<div class="ex351-score-row">' +
                '<div>' +
                    '<div class="ex351-score-number">' + d.vitalityScore + '</div>' +
                    '<div class="ex351-score-label">Vitality Score (0–100)</div>' +
                '</div>' +
                '<span class="ex351-tier-pill ' + tierClass + '">' + tierLabel + '</span>' +
            '</div>' +
            '<div class="ex351-section">' +
                '<h3 class="ex351-section-title">Pattern Reading</h3>' +
                '<p class="ex351-trajectory">' + esc(d.trajectoryRead) + '</p>' +
            '</div>' +
            medSection +
            ministrySection +
            '<p class="ex351-disclaimer">' + esc(d.disclaimer) + '</p>' +
            '<div class="ex351-action-row">' +
                '<button class="ex351-print-btn" onclick="window.print()">' +
                    '<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                    '<path stroke-linecap="round" stroke-linejoin="round"' +
                    ' d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm1-11V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2"/>' +
                    '</svg>' +
                    'Print / Save as PDF' +
                '</button>' +
            '</div>';

        document.getElementById('ex351-report-content').innerHTML = html;
    }

    /* ── Watch for HCC result tier and surface the button ── */
    function checkTierAndReveal() {
        var wrap = document.getElementById('ex351-trigger-wrap');
        if (!wrap) return;

        /* Look for tier indicators already rendered by existing HCC patches */
        var bodyText = document.body.innerText.toLowerCase();
        var flaggedTiers = ['protocol', 'alarm'];
        var isFlagged = flaggedTiers.some(function(t) {
            return bodyText.indexOf('tier: ' + t) !== -1 ||
                   document.querySelector('[data-tier="' + t + '"]') !== null;
        });

        /* Always show button — server will confirm tier; avoids client-side spoofing */
        wrap.style.display = 'block';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkTierAndReveal);
    } else {
        checkTierAndReveal();
    }
})();
</script>
    <?php
}

/* ── AJAX handler ─────────────────────────────────────────────────────────── */

function ex351_ajax_handler(): void {
    check_ajax_referer( 'ex351_clinical_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Authentication required.' );
    }

    $member_id = (int) ( $_POST['member_id'] ?? get_current_user_id() );
    if ( $member_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Access denied.' );
    }

    $hermes_url = EX351_HERMES_BASE . '/report/clinical-summary/' . $member_id;
    $api_key    = defined( 'HERMES_API_KEY' ) ? HERMES_API_KEY : get_option( '_hermes_api_key', '' );

    $response = wp_remote_get( $hermes_url, [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Accept'        => 'application/json',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Could not reach health server. Please try again.' );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code === 404 ) {
        wp_send_json_error( 'No completed health check found. Please complete a body check first.' );
    }

    if ( $code !== 200 || empty( $body ) ) {
        wp_send_json_error( 'Server returned an unexpected response. Please try again.' );
    }

    /* Only surface for flagged tiers */
    $tier = $body['tier'] ?? '';
    if ( ! in_array( $tier, [ 'protocol', 'alarm' ], true ) ) {
        wp_send_json_error(
            'Your current Vitality Score (' . intval( $body['vitalityScore'] ?? 0 ) .
            '/100) is in a healthy range. A doctor summary is generated when your pattern ' .
            'warrants clinical attention. Keep checking in daily.'
        );
    }

    wp_send_json_success( $body );
}
