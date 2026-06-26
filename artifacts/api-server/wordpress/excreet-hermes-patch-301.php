<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.0.1
 * Description: "Share with My Provider" — printable one-page triage primer.
 *
 *   Renders a sanitized, provider-facing health summary that members can
 *   print or save as a PDF from their browser. Framed as "member reports…"
 *   — never adversarial to any physician, insurer, or manufacturer.
 *   Data remains inside the SaaS; no raw export is provided.
 *
 *   On first run, creates /provider-report/ page and injects the shortcode.
 *   Also adds a "Share with My Provider" link on the HCC page (/healing-command-center/).
 *
 *   Shortcode: [excreet_provider_report]
 *
 * Version: 3.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ── Constants ────────────────────────────────────────────────────────────── */

define( 'EX301_SETUP_OPT',      '_excreet_301_setup' );
define( 'EX301_PAGE_ID_OPT',    '_excreet_301_page_id' );
define( 'EX301_HCC_PAGE_ID',    257 );
define( 'EX301_PURPLE',         '#6B2FA0' );
define( 'EX301_PURPLE_DARK',    '#3D1060' );
define( 'EX301_GOLD',           '#C9A84C' );

/* ── Hooks ────────────────────────────────────────────────────────────────── */

add_action( 'init',             'excreet_301_setup',         5 );
add_action( 'wp_head',          'excreet_301_print_styles'      );
add_action( 'wp_footer',        'excreet_301_hcc_link',      90 );
add_shortcode( 'excreet_provider_report', 'excreet_301_shortcode' );

/* ════════════════════════════════════════════════════════════════════════════
   SETUP — create /provider-report/ page once
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_301_setup(): void {
    if ( get_option( EX301_SETUP_OPT ) ) {
        return;
    }

    $existing_id = get_option( EX301_PAGE_ID_OPT );
    if ( $existing_id && get_post( $existing_id ) ) {
        update_option( EX301_SETUP_OPT, '1' );
        return;
    }

    $page_id = wp_insert_post( [
        'post_title'   => 'Share with My Provider',
        'post_name'    => 'provider-report',
        'post_content' => '[excreet_provider_report]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'meta_input'   => [ '_wp_page_template' => 'elementor_canvas' ],
    ] );

    if ( ! is_wp_error( $page_id ) ) {
        update_option( EX301_PAGE_ID_OPT, $page_id );
    }

    update_option( EX301_SETUP_OPT, '1' );
}

/* ════════════════════════════════════════════════════════════════════════════
   PRINT STYLES — injected only on the provider-report page
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_301_print_styles(): void {
    $page_id = (int) get_option( EX301_PAGE_ID_OPT );
    if ( ! $page_id || ! is_page( $page_id ) ) {
        return;
    }
    ?>
    <style>
    /* ── Screen styles ── */
    body.page-id-<?php echo $page_id; ?> {
        background: url('https://excreet.com/wp-content/uploads/healer-bg-<?php echo str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT ); ?>.jpg') center/cover no-repeat fixed #0c0115 !important;
        min-height: 100vh;
    }
    body.page-id-<?php echo $page_id; ?> .site-header,
    body.page-id-<?php echo $page_id; ?> .site-footer,
    body.page-id-<?php echo $page_id; ?> .elementor-location-header,
    body.page-id-<?php echo $page_id; ?> .elementor-location-footer,
    body.page-id-<?php echo $page_id; ?> #wpadminbar { display: none !important; }

    .ex301-page-wrap {
        max-width: 780px;
        margin: 2rem auto;
        padding: 0 1rem 4rem;
    }

    /* Print actions bar — hidden when printing */
    .ex301-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 0 1.5rem;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .ex301-back-link {
        font-size: 0.82rem;
        color: <?php echo EX301_PURPLE; ?>;
        text-decoration: none;
        font-family: Georgia, serif;
        letter-spacing: 0.04em;
    }
    .ex301-print-btn {
        background: linear-gradient(135deg, <?php echo EX301_PURPLE_DARK; ?>, <?php echo EX301_PURPLE; ?>);
        color: #ffffff;
        border: none;
        border-radius: 10px;
        padding: 0.65rem 1.6rem;
        font-family: Georgia, serif;
        font-size: 0.85rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        cursor: pointer;
        box-shadow: 0 3px 12px rgba(107,47,160,0.3);
    }
    .ex301-print-hint {
        font-size: 0.72rem;
        color: #8896a4;
        font-style: italic;
    }

    /* Report card */
    .ex301-report {
        background: #ffffff;
        border: 1px solid #D5C5E8;
        border-radius: 16px;
        box-shadow: 0 6px 32px rgba(30,10,60,0.12), 0 2px 8px rgba(30,10,60,0.06);
        overflow: hidden;
        font-family: Georgia, 'Times New Roman', serif;
        color: #1A0A2E;
    }

    /* Report header */
    .ex301-header {
        background: linear-gradient(135deg, <?php echo EX301_PURPLE_DARK; ?> 0%, <?php echo EX301_PURPLE; ?> 100%);
        padding: 2rem 2.4rem;
        color: #ffffff;
    }
    .ex301-header-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .ex301-brand {
        font-size: 0.72rem;
        letter-spacing: 0.22em;
        text-transform: uppercase;
        color: <?php echo EX301_GOLD; ?>;
        font-weight: 700;
    }
    .ex301-doc-type {
        font-size: 0.68rem;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.6);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        padding: 0.2rem 0.75rem;
    }
    .ex301-report-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 0 0.35rem;
    }
    .ex301-report-subtitle {
        font-size: 0.88rem;
        color: rgba(255,255,255,0.72);
        line-height: 1.5;
        max-width: 600px;
    }

    /* Meta row */
    .ex301-meta {
        background: #f7f4fc;
        border-bottom: 1px solid #D5C5E8;
        padding: 0.9rem 2.4rem;
        display: flex;
        gap: 2.5rem;
        flex-wrap: wrap;
        font-size: 0.8rem;
        color: #6B7A8D;
    }
    .ex301-meta strong { color: #1A0A2E; }

    /* Report body */
    .ex301-body { padding: 2rem 2.4rem; }

    /* Score ring row */
    .ex301-score-row {
        display: flex;
        align-items: center;
        gap: 1.4rem;
        margin-bottom: 1.8rem;
        padding-bottom: 1.6rem;
        border-bottom: 1px solid #ede4f5;
    }
    .ex301-score-label-group { flex: 1; }
    .ex301-score-section-title {
        font-size: 0.65rem;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #9a9a9a;
        margin-bottom: 0.3rem;
    }
    .ex301-score-val { font-size: 2rem; font-weight: 700; margin-bottom: 0.2rem; }
    .ex301-score-desc { font-size: 0.8rem; color: #6B7A8D; line-height: 1.4; }

    /* Section */
    .ex301-section { margin-bottom: 1.6rem; }
    .ex301-section-title {
        font-size: 0.65rem;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: <?php echo EX301_PURPLE; ?>;
        margin-bottom: 0.6rem;
        padding-bottom: 0.4rem;
        border-bottom: 1px solid #ede4f5;
        font-weight: 700;
    }
    .ex301-section p {
        font-size: 0.88rem;
        color: #334e68;
        line-height: 1.75;
        margin: 0 0 0.6rem;
    }
    .ex301-flag-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .ex301-flag-list li {
        font-size: 0.86rem;
        color: #334e68;
        padding: 0.4rem 0 0.4rem 1.2rem;
        position: relative;
        line-height: 1.5;
        border-bottom: 1px solid #f5eeff;
    }
    .ex301-flag-list li::before {
        content: '›';
        position: absolute;
        left: 0;
        color: <?php echo EX301_GOLD; ?>;
        font-weight: 700;
    }
    .ex301-empty-note {
        font-size: 0.82rem;
        color: #9a9a9a;
        font-style: italic;
    }

    /* Legal footer */
    .ex301-legal {
        background: #f9f7fc;
        border-top: 1px solid #D5C5E8;
        padding: 1.2rem 2.4rem;
        font-size: 0.72rem;
        color: #8896a4;
        line-height: 1.7;
    }
    .ex301-legal strong { color: <?php echo EX301_PURPLE; ?>; font-size: 0.7rem; }

    /* ── Print overrides ── */
    @media print {
        .ex301-actions { display: none !important; }
        .ex301-report {
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
        }
        .ex301-header {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body { background: #ffffff !important; }
        @page {
            margin: 1.2cm 1.5cm;
        }
    }
    </style>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   HCC LINK INJECTION — adds "Share with My Provider →" link on HCC page
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_301_hcc_link(): void {
    // Intentionally removed from HCC page — the provider report is now surfaced
    // at the close of a Ministry of Healing session, after the member has processed
    // their signals with the AI. See excreet_301_ministry_link() below.
}

/* ════════════════════════════════════════════════════════════════════════════
   SHORTCODE — [excreet_provider_report]
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_301_shortcode(): string {
    if ( ! is_user_logged_in() ) {
        return '<p style="text-align:center;padding:3rem;color:#6B2FA0;">
            Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" style="color:#6B2FA0;">log in</a> to view your provider report.
        </p>';
    }

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();
    $name    = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name ?: 'Member';
    $today   = wp_date( 'F j, Y' );
    $hcc_url = esc_url( home_url( '/healing-command-center/' ) );

    // ── Fetch today's Body Check from Hermes ───────────────────────────────
    $body_score   = null;
    $body_summary = '';
    $body_flags   = [];

    if ( defined( 'EXCREET_HERMES_BODY_SNAPSHOT_URL' ) && defined( 'EXCREET_HERMES_API_KEY' ) ) {
        $score_url  = rtrim( (string) EXCREET_HERMES_BODY_SNAPSHOT_URL, '/' )
                    . '/today/' . rawurlencode( (string) $user_id );
        $score_resp = wp_remote_get( $score_url, [
            'headers'   => [ 'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY ],
            'timeout'   => 6,
            'sslverify' => true,
        ] );
        if ( ! is_wp_error( $score_resp ) && 200 === wp_remote_retrieve_response_code( $score_resp ) ) {
            $sdata = json_decode( wp_remote_retrieve_body( $score_resp ), true );
            if ( isset( $sdata['result'] ) ) {
                $r          = $sdata['result'];
                $body_score = isset( $r['bodyScore'] ) ? (int) $r['bodyScore'] : null;
                // Pull top-level summary text — try common fields
                foreach ( [ 'summary', 'clinicalSummary', 'overallAssessment' ] as $key ) {
                    if ( ! empty( $r[ $key ] ) && is_string( $r[ $key ] ) ) {
                        $body_summary = $r[ $key ];
                        break;
                    }
                }
                // Pull red flag items
                foreach ( [ 'redFlags', 'flags', 'urgentSignals', 'concerns' ] as $key ) {
                    if ( ! empty( $r[ $key ] ) && is_array( $r[ $key ] ) ) {
                        $body_flags = array_slice( $r[ $key ], 0, 6 );
                        break;
                    }
                }
            }
        }
    }

    // ── Pull Clinical Pattern Report from user meta ────────────────────────
    $cpr_raw    = get_user_meta( $user_id, 'excreet_hcc_result', true );
    $cpr        = is_array( $cpr_raw ) ? $cpr_raw : ( is_string( $cpr_raw ) ? json_decode( $cpr_raw, true ) : null );
    $cpr_flags  = [];
    $cpr_meds   = '';
    $cpr_summary = '';

    if ( is_array( $cpr ) ) {
        foreach ( [ 'redFlags', 'flags', 'concernFlags' ] as $key ) {
            if ( ! empty( $cpr[ $key ] ) && is_array( $cpr[ $key ] ) ) {
                $cpr_flags = array_slice( $cpr[ $key ], 0, 6 );
                break;
            }
        }
        foreach ( [ 'clinicalSummary', 'summary', 'overallAssessment' ] as $key ) {
            if ( ! empty( $cpr[ $key ] ) && is_string( $cpr[ $key ] ) ) {
                $cpr_summary = $cpr[ $key ];
                break;
            }
        }
        $cpr_meds = ! empty( $cpr['medications'] ) && is_string( $cpr['medications'] )
            ? $cpr['medications']
            : '';
    }

    // ── Score ring SVG ─────────────────────────────────────────────────────
    if ( $body_score !== null ) {
        $r_val   = 32;
        $circ    = round( 2 * M_PI * $r_val, 1 );
        $dash    = round( $circ * $body_score / 100, 1 );
        $s_color = $body_score >= 75 ? '#2e7d32' : ( $body_score >= 50 ? EX301_GOLD : '#c62828' );
    }

    ob_start();
    ?>
    <div class="ex301-page-wrap">

        <!-- Actions bar (hidden on print) -->
        <div class="ex301-actions">
            <a href="<?php echo $hcc_url; ?>" class="ex301-back-link">← Back to My Dashboard</a>
            <div>
                <button class="ex301-print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>
                <div class="ex301-print-hint">Use your browser's "Save as PDF" option.</div>
            </div>
        </div>

        <!-- Report card -->
        <div class="ex301-report">

            <!-- Header -->
            <div class="ex301-header">
                <div class="ex301-header-top">
                    <span class="ex301-brand">Excreet™ — WHealth Intelligence</span>
                    <span class="ex301-doc-type">Provider Triage Primer — Not for Clinical Use</span>
                </div>
                <h1 class="ex301-report-title">Member Health Overview</h1>
                <p class="ex301-report-subtitle">
                    This document was prepared by the member for informational discussion with their healthcare provider.
                    It reflects self-reported patterns and AI-assisted signal analysis — not a clinical diagnosis.
                </p>
            </div>

            <!-- Meta -->
            <div class="ex301-meta">
                <span><strong>Member:</strong> <?php echo esc_html( $name ); ?></span>
                <span><strong>Report Date:</strong> <?php echo esc_html( $today ); ?></span>
                <span><strong>Source:</strong> Excreet.com — Member-initiated</span>
            </div>

            <!-- Body -->
            <div class="ex301-body">

                <?php if ( $body_score !== null ) : ?>
                <!-- Body Score -->
                <div class="ex301-score-row">
                    <svg width="84" height="84" viewBox="0 0 84 84" style="flex-shrink:0;">
                        <circle cx="42" cy="42" r="<?php echo $r_val; ?>" fill="none" stroke="#ede4f5" stroke-width="8"/>
                        <circle cx="42" cy="42" r="<?php echo $r_val; ?>" fill="none"
                            stroke="<?php echo esc_attr( $s_color ); ?>"
                            stroke-width="8"
                            stroke-linecap="round"
                            stroke-dasharray="<?php echo $dash; ?> <?php echo $circ; ?>"
                            transform="rotate(-90 42 42)"/>
                        <text x="42" y="47" text-anchor="middle" font-size="17" font-weight="700"
                              fill="<?php echo esc_attr( $s_color ); ?>" font-family="Georgia,serif">
                            <?php echo $body_score; ?>
                        </text>
                    </svg>
                    <div class="ex301-score-label-group">
                        <div class="ex301-score-section-title">Today's Body Score</div>
                        <div class="ex301-score-val" style="color:<?php echo esc_attr( $s_color ); ?>;">
                            <?php echo $body_score; ?> / 100
                        </div>
                        <div class="ex301-score-desc">
                            <?php
                            if ( $body_score >= 75 )     echo 'Signals within normal range today.';
                            elseif ( $body_score >= 50 ) echo 'Moderate signals noted — some patterns warrant discussion.';
                            else                          echo 'Elevated signals today — the member recommends review.';
                            ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $body_summary ) : ?>
                <!-- Today's observation summary -->
                <div class="ex301-section">
                    <div class="ex301-section-title">Today's Body Check — Member's Observations</div>
                    <p><?php echo esc_html( $body_summary ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( $body_flags ) : ?>
                <!-- Body snapshot flags -->
                <div class="ex301-section">
                    <div class="ex301-section-title">Signals the Member Wishes to Discuss</div>
                    <ul class="ex301-flag-list">
                        <?php foreach ( $body_flags as $flag ) : ?>
                        <li><?php echo esc_html( is_array( $flag ) ? ( $flag['description'] ?? $flag['flag'] ?? implode( ' ', (array) $flag ) ) : (string) $flag ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( $cpr_meds ) : ?>
                <!-- Medications reported -->
                <div class="ex301-section">
                    <div class="ex301-section-title">Medications Reported by Member</div>
                    <p><?php echo esc_html( $cpr_meds ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( $cpr_summary ) : ?>
                <!-- Clinical pattern summary -->
                <div class="ex301-section">
                    <div class="ex301-section-title">Clinical Pattern Report — Summary</div>
                    <p><?php echo esc_html( $cpr_summary ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( $cpr_flags ) : ?>
                <!-- CPR flags -->
                <div class="ex301-section">
                    <div class="ex301-section-title">Pattern Flags from Intake Analysis</div>
                    <ul class="ex301-flag-list">
                        <?php foreach ( $cpr_flags as $flag ) : ?>
                        <li><?php echo esc_html( is_array( $flag ) ? ( $flag['description'] ?? $flag['flag'] ?? implode( ' ', (array) $flag ) ) : (string) $flag ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( ! $body_score && ! $cpr_summary && ! $body_summary ) : ?>
                <div class="ex301-section">
                    <p class="ex301-empty-note">
                        No report data is available yet. Please complete your Health Intake and submit at least one Body Check before generating a provider report.
                    </p>
                    <p><a href="<?php echo $hcc_url; ?>" style="color:<?php echo EX301_PURPLE; ?>;">← Back to My Dashboard</a></p>
                </div>
                <?php endif; ?>

            </div><!-- /.ex301-body -->

            <!-- Legal footer -->
            <div class="ex301-legal">
                <strong>IMPORTANT — PROVIDER AND MEMBER NOTICE:</strong>
                This document is a member-prepared triage primer intended to support conversation with a licensed healthcare provider.
                It is not a clinical diagnosis, medical advice, or treatment recommendation.
                All content reflects self-reported observations and AI-assisted pattern analysis from Excreet.com.
                Excreet™ does not diagnose, treat, prescribe, or replace any physician, pharmacist, or clinical professional.
                This report may not be used as evidence in any legal, regulatory, insurance, malpractice, or administrative proceeding.
                <br><br>
                <strong>Excreet™ — WHealth Intelligence · excreet.com · <?php echo esc_html( wp_date( 'Y' ) ); ?></strong>
            </div>

        </div><!-- /.ex301-report -->

    </div><!-- /.ex301-page-wrap -->
    <?php
    return ob_get_clean();
}
