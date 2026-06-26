<?php
/**
 * Plugin Name: Excreet Hermes Patch 2.9.7
 * Description: Three simultaneous builds.
 *
 *   A — Intake Form Styling
 *       Full Excreet brand treatment for /member-intake-form/.
 *       Purple gradient background, glass card container, gold-accented
 *       Forminator fields, styled submit button, hidden WP chrome.
 *
 *   B — Member Dashboard  [excreet_member_dashboard]
 *       Personalized member hub at /member-dashboard/ (page #772).
 *       Shows greeting, last activity stats, and four action cards:
 *       Gut Snapshot · Ministry of Healing · Healing Protocol · My Account.
 *       Injects shortcode into page 772 on first run.
 *
 *   C — Ministry of Healing page repair + styling
 *       Ensures page #231 has both shortcodes. Adds full-page brand CSS
 *       wrapper over the Ministry chat and Healing Protocol sections.
 *       Fixes the setup-option conflict between patch-293 and patch-294
 *       (both wrote different content on first run; whichever ran second wins).
 *
 * Version: 2.9.7b
 *
 * v2.9.7a fixes:
 *   - "Today's Gut Snapshot" → "Today's Body Snapshot" (rename carry-through)
 *   - Removed MeprUser reference (MemberPress deleted); uses $user->user_registered
 *   - Account link: /my-account/ → /membership-account/ (PMPro page)
 *   - Body Score banner: server-side fetch from Hermes on dashboard load
 *   - Added "Clinical Pattern Report" card → /welcome-member/
 *   - Legal page styling: Botanical palette for Terms, Privacy, Refund pages
 *   - Ministry session reset: "Start New Session" button injected on Ministry page
 *
 * v2.9.7b adds:
 *   - "Excreet Store" dashboard card → /shop/ (WooCommerce, activated Phase 14)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ─────────────────────────────────────────────────────────────────────────── */
/*  CONSTANTS                                                                   */
/* ─────────────────────────────────────────────────────────────────────────── */

define( 'EX297_PURPLE',      '#6B2FA0' );
define( 'EX297_PURPLE_DARK', '#3D1060' );
define( 'EX297_GOLD',        '#C9A84C' );
define( 'EX297_DASHBOARD_PAGE_ID', 772 );
define( 'EX297_MINISTRY_PAGE_ID',  231 );
define( 'EX297_SETUP_OPT',   '_excreet_297_setup' );

/* ─────────────────────────────────────────────────────────────────────────── */
/*  INIT — one-time setup                                                       */
/* ─────────────────────────────────────────────────────────────────────────── */

add_action( 'init', 'excreet_297_setup', 5 );

function excreet_297_setup(): void {
    if ( get_option( EX297_SETUP_OPT ) ) {
        return;
    }

    // B: Inject Member Dashboard shortcode into page 772
    $dashboard = get_post( EX297_DASHBOARD_PAGE_ID );
    if ( $dashboard && empty( trim( $dashboard->post_content ) ) ) {
        wp_update_post( [
            'ID'           => EX297_DASHBOARD_PAGE_ID,
            'post_content' => '[excreet_member_dashboard]',
            'post_status'  => 'publish',
        ] );
    }

    // Ministry of Healing page (#231) is intentionally left alone.
    // The consolidated single-form [excreet_ministry_of_healing] already
    // integrates the protocol button inside the chat interface (patch-293).
    // Do not write page content here — patch-293 and patch-294 manage it.

    update_option( EX297_SETUP_OPT, '1' );
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  A — INTAKE FORM STYLING                                                     */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_head', 'excreet_297_intake_styles' );

function excreet_297_intake_styles(): void {
    if ( ! is_page( 'member-intake-form' ) ) {
        return;
    }
    ?>
    <style>
    /* ── Page canvas ── */
    html, body {
        background:
            linear-gradient(160deg,rgba(13,1,32,.52) 0%,rgba(26,5,53,.20) 28%,rgba(26,5,53,.15) 62%,rgba(13,1,32,.50) 100%),
            url("https://excreet.com/wp-content/uploads/healer-bg-<?php echo str_pad((int)date('n'),2,'0',STR_PAD_LEFT); ?>.jpg")
            center/cover no-repeat fixed #0c0115 !important;
        min-height: 100vh !important;
    }

    /* ── Hide WP / Elementor chrome ── */
    .site-header, .site-footer, .entry-title, .entry-header,
    .elementor-location-header, .elementor-location-footer,
    #wpadminbar + .elementor-page { padding-top: 0 !important; }

    /* ── Override Elementor outer container white bg ── */
    .elementor-element-2fd07fa9,
    .elementor-element-2fd07fa9 > .e-con-inner {
        background: transparent !important;
        padding: 0 !important;
    }

    /* ── Inner column — make it a narrow, centered card area ── */
    .elementor-element-54e25be,
    .elementor-element-54e25be > .e-con-inner {
        background: transparent !important;
        max-width: 680px !important;
        margin: 0 auto !important;
        padding: 3rem 1.5rem 5rem !important;
    }

    /* ── "← Back to Home" link ── */
    .elementor-element-cc9e57e p,
    .elementor-element-cc9e57e a {
        color: rgba(201,168,76,0.75) !important;
        font-size: 0.82rem !important;
        letter-spacing: 0.07em !important;
        text-decoration: none !important;
        font-family: 'Georgia', serif !important;
    }
    .elementor-element-cc9e57e a:hover { color: #C9A84C !important; }

    /* ── Welcome text block ── */
    .elementor-element-497295a p,
    .elementor-element-497295a h2,
    .elementor-element-497295a * {
        color: rgba(255,255,255,0.88) !important;
        font-family: 'Georgia', serif !important;
    }
    .elementor-element-497295a h2,
    .elementor-element-497295a h3 {
        color: #C9A84C !important;
        letter-spacing: 0.08em !important;
        font-size: 1.4rem !important;
    }

    /* ── Forminator form wrapper ── */
    .forminator-custom-form,
    .forminator-ui {
        background: rgba(255,255,255,0.055) !important;
        border: 1px solid rgba(201,168,76,0.28) !important;
        border-radius: 18px !important;
        padding: 2rem 2rem 2.5rem !important;
        backdrop-filter: blur(14px) !important;
        -webkit-backdrop-filter: blur(14px) !important;
        margin-top: 1.5rem !important;
    }

    /* ── Field labels ── */
    .forminator-label,
    .forminator-field-label,
    .forminator-row label,
    .forminator-custom-form label {
        color: rgba(255,255,255,0.75) !important;
        font-family: 'Georgia', serif !important;
        font-size: 0.78rem !important;
        letter-spacing: 0.1em !important;
        text-transform: uppercase !important;
        margin-bottom: 0.4rem !important;
        display: block !important;
    }

    /* ── Field descriptions / hints ── */
    .forminator-description,
    .forminator-field-option-description,
    .forminator-input-description {
        color: rgba(255,255,255,0.82) !important;
        font-size: 0.9rem !important;
        font-style: italic !important;
    }

    /* ── Text inputs, email, number ── */
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
        background: rgba(255,255,255,0.08) !important;
        border: 1px solid rgba(201,168,76,0.3) !important;
        border-radius: 9px !important;
        color: #ffffff !important;
        padding: 0.7rem 1rem !important;
        font-family: 'Georgia', serif !important;
        font-size: 0.9rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
        transition: border-color 0.2s, background 0.2s !important;
        -webkit-appearance: none !important;
    }
    .forminator-custom-form input::placeholder,
    .forminator-custom-form textarea::placeholder {
        color: rgba(255,255,255,0.28) !important;
    }
    .forminator-custom-form input:focus,
    .forminator-custom-form select:focus,
    .forminator-custom-form textarea:focus {
        border-color: rgba(201,168,76,0.65) !important;
        background: rgba(255,255,255,0.12) !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1) !important;
    }

    /* ── Select arrow ── */
    .forminator-custom-form select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23C9A84C'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.9rem center !important;
        padding-right: 2.2rem !important;
    }
    .forminator-custom-form select option { background: #1a0535 !important; color: #fff !important; }

    /* ── Radio / checkbox ── */
    .forminator-custom-form .forminator-checkbox label,
    .forminator-custom-form .forminator-radio label {
        color: rgba(255,255,255,0.8) !important;
        font-size: 0.88rem !important;
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
    .forminator-custom-form .forminator-title h3,
    .forminator-custom-form .forminator-subtitle {
        color: #C9A84C !important;
        font-family: 'Georgia', serif !important;
        text-align: left !important;
        letter-spacing: 0.04em !important;
    }

    /* ── Pagination dots / nav ── */
    .forminator-pagination--nav .forminator-button--previous,
    .forminator-pagination--bar .forminator-step--completed {
        background: rgba(201,168,76,0.25) !important;
    }
    .forminator-pagination--bar .forminator-step--active { background: #C9A84C !important; }
    .forminator-pagination--nav .forminator-step { color: rgba(255,255,255,0.82) !important; }
    .forminator-pagination--nav .forminator-step.forminator-step--completed,
    .forminator-pagination--nav .forminator-step.forminator-step--active { color: #C9A84C !important; }

    /* ── Submit button ── */
    .forminator-btn,
    .forminator-custom-form .forminator-btn-submit,
    .forminator-custom-form button[type="submit"] {
        background: linear-gradient(135deg, #C9A84C 0%, #a8873a 100%) !important;
        color: #1a0535 !important;
        border: none !important;
        border-radius: 50px !important;
        padding: 0.9rem 2.8rem !important;
        font-weight: 700 !important;
        font-size: 0.88rem !important;
        letter-spacing: 0.12em !important;
        text-transform: uppercase !important;
        cursor: pointer !important;
        font-family: 'Georgia', serif !important;
        transition: opacity 0.2s, transform 0.15s !important;
        box-shadow: 0 4px 20px rgba(201,168,76,0.35) !important;
    }
    .forminator-btn:hover,
    .forminator-custom-form .forminator-btn-submit:hover {
        opacity: 0.88 !important;
        transform: translateY(-1px) !important;
    }

    /* ── Previous button ── */
    .forminator-btn-back,
    .forminator-btn--ghost {
        background: transparent !important;
        border: 1px solid rgba(201,168,76,0.4) !important;
        color: rgba(201,168,76,0.8) !important;
        border-radius: 50px !important;
        padding: 0.7rem 1.8rem !important;
        font-family: 'Georgia', serif !important;
        letter-spacing: 0.08em !important;
    }

    /* ── Validation errors ── */
    .forminator-error .forminator-input-errors,
    .forminator-input-errors .forminator-error {
        color: #ff8a8a !important;
        font-size: 0.78rem !important;
    }
    .forminator-has-error input,
    .forminator-has-error textarea,
    .forminator-has-error select {
        border-color: rgba(255,100,100,0.5) !important;
    }

    /* ── Progress bar ── */
    .forminator-pagination .forminator-pagination--title { color: rgba(255,255,255,0.88) !important; }
    .forminator-ui.forminator-loaded .forminator-pagination--bar--fill { background: #C9A84C !important; }

    /* ── Top Excreet wordmark injection via pseudo ── */
    .elementor-element-54e25be::before {
        content: 'EXCREET';
        display: block;
        text-align: center;
        color: #C9A84C;
        font-family: 'Georgia', serif;
        font-size: 1.5rem;
        letter-spacing: 0.5em;
        margin-bottom: 2rem;
        opacity: 0.9;
    }

    /* ── Dividers between form rows ── */
    .forminator-row { border-bottom: 1px solid rgba(255,255,255,0.04) !important; }
    .forminator-row:last-child { border-bottom: none !important; }
    </style>
    <?php
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  B — MEMBER DASHBOARD SHORTCODE                                              */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_shortcode( 'excreet_member_dashboard', 'excreet_297_dashboard' );

function excreet_297_dashboard(): string {
    if ( ! is_user_logged_in() ) {
        return '<p style="color:#C9A84C;text-align:center;padding:3rem;">
            Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '"
            style="color:#C9A84C;">log in</a> to access your dashboard.
        </p>';
    }

    $user_id   = get_current_user_id();
    $user      = wp_get_current_user();
    $first     = $user->first_name ?: ( explode( ' ', $user->display_name )[0] ?: 'Member' );

    // Greeting by time of day
    $hour = (int) ( new DateTime( 'now', new DateTimeZone( 'America/New_York' ) ) )->format( 'H' );
    $greeting = $hour < 12 ? 'Good morning' : ( $hour < 17 ? 'Good afternoon' : 'Good evening' );

    // Last activity
    $last_job_time = get_user_meta( $user_id, 'excreet_latest_job_time', true );
    $last_active   = $last_job_time
        ? human_time_diff( (int) $last_job_time, time() ) . ' ago'
        : 'Not yet recorded';

    // Protocol credits
    $credits  = (int) get_user_meta( $user_id, '_excreet_protocol_credits', true );
    $history  = get_user_meta( $user_id, '_excreet_protocol_history', true );
    $protocols_run = is_array( $history ) ? count( $history ) : 0;

    // Ministry of Healing usage
    $moh_usage = get_user_meta( $user_id, '_excreet_moh_usage', true );
    $moh_used  = 0;
    if ( is_array( $moh_usage ) ) {
        foreach ( $moh_usage as $period_data ) {
            $moh_used = max( $moh_used, (int) ( $period_data['count'] ?? 0 ) );
        }
    }

    // Membership join date (PMPro or WP user_registered)
    $pmpro_start = '';
    if ( function_exists( 'pmpro_getMemberStartDate' ) ) {
        $pmpro_ts = pmpro_getMemberStartDate( $user_id );
        if ( $pmpro_ts ) {
            $pmpro_start = date( 'F Y', (int) $pmpro_ts );
        }
    }
    $join_date = $pmpro_start ?: date( 'F Y', strtotime( $user->user_registered ) );

    // Body Score — fetch latest snapshot from Hermes (server-side, 4s timeout)
    $body_score      = null;
    $body_score_date = null;
    if ( defined( 'EXCREET_HERMES_BODY_SNAPSHOT_URL' ) && defined( 'EXCREET_HERMES_API_KEY' ) ) {
        $score_url = rtrim( (string) EXCREET_HERMES_BODY_SNAPSHOT_URL, '/' )
                   . '/today/' . rawurlencode( (string) $user_id );
        $score_resp = wp_remote_get( $score_url, [
            'headers'   => [ 'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY ],
            'timeout'   => 4,
            'sslverify' => true,
        ] );
        if ( ! is_wp_error( $score_resp ) && 200 === wp_remote_retrieve_response_code( $score_resp ) ) {
            $score_data = json_decode( wp_remote_retrieve_body( $score_resp ), true );
            if ( isset( $score_data['result']['bodyScore'] ) ) {
                $body_score      = (int) $score_data['result']['bodyScore'];
                $body_score_date = wp_date( 'F j', time() );
            }
        }
    }

    // Body Score ring colour
    $score_color = '#9a9a9a'; // muted grey when no score
    if ( $body_score !== null ) {
        if ( $body_score >= 75 )      $score_color = '#4caf50';
        elseif ( $body_score >= 50 )  $score_color = '#C9A84C';
        else                           $score_color = '#e57373';
    }

    ob_start();
    ?>
    <div class="ex297-dashboard">

        <!-- Header -->
        <div class="ex297-header">
            <div class="ex297-logo-mark">E</div>
            <div class="ex297-greeting">
                <span class="ex297-greeting-sub"><?php echo esc_html( $greeting ); ?>,</span>
                <span class="ex297-greeting-name"><?php echo esc_html( $first ); ?></span>
            </div>
            <div class="ex297-member-badge">Active Member</div>
        </div>

        <!-- Stats row -->
        <div class="ex297-stats">
            <div class="ex297-stat">
                <div class="ex297-stat-value"><?php echo $last_job_time ? esc_html( $last_active ) : '—'; ?></div>
                <div class="ex297-stat-label">Last Check-in</div>
            </div>
            <div class="ex297-stat-divider"></div>
            <div class="ex297-stat">
                <div class="ex297-stat-value"><?php echo $moh_used; ?></div>
                <div class="ex297-stat-label">Ministry Queries</div>
            </div>
            <div class="ex297-stat-divider"></div>
            <div class="ex297-stat">
                <div class="ex297-stat-value"><?php echo $protocols_run ?: ( $credits > 0 ? $credits . ' credit' . ( $credits > 1 ? 's' : '' ) : '0' ); ?></div>
                <div class="ex297-stat-label">Protocols Run</div>
            </div>
            <div class="ex297-stat-divider"></div>
            <div class="ex297-stat">
                <div class="ex297-stat-value"><?php echo esc_html( $join_date ?: '—' ); ?></div>
                <div class="ex297-stat-label">Member Since</div>
            </div>
        </div>

        <!-- Body Score banner -->
        <div class="ex297-score-banner">
            <?php if ( $body_score !== null ) : ?>
            <div class="ex297-score-ring" style="--score-color: <?php echo esc_attr( $score_color ); ?>;">
                <svg width="72" height="72" viewBox="0 0 72 72">
                    <circle cx="36" cy="36" r="28" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="7"/>
                    <circle cx="36" cy="36" r="28" fill="none"
                        stroke="<?php echo esc_attr( $score_color ); ?>"
                        stroke-width="7"
                        stroke-linecap="round"
                        stroke-dasharray="<?php echo round( 2 * M_PI * 28 * $body_score / 100, 1 ); ?> <?php echo round( 2 * M_PI * 28, 1 ); ?>"
                        transform="rotate(-90 36 36)"/>
                    <text x="36" y="41" text-anchor="middle" font-size="16" font-weight="700"
                          fill="<?php echo esc_attr( $score_color ); ?>" font-family="Georgia,serif">
                        <?php echo $body_score; ?>
                    </text>
                </svg>
            </div>
            <div class="ex297-score-details">
                <div class="ex297-score-label">Today's Body Score</div>
                <div class="ex297-score-date"><?php echo esc_html( $body_score_date ?? '' ); ?></div>
            </div>
            <a href="<?php echo esc_url( home_url( '/healing-command-center/' ) ); ?>" class="ex297-score-link">View full analysis →</a>
            <?php else : ?>
            <div class="ex297-score-empty">
                <div class="ex297-score-label">Body Score</div>
                <div class="ex297-score-date">No snapshot submitted today</div>
            </div>
            <a href="<?php echo esc_url( home_url( '/healing-command-center/' ) ); ?>" class="ex297-score-link ex297-score-link--cta">Submit today's snapshot →</a>
            <?php endif; ?>
        </div>

        <!-- Section label -->
        <div class="ex297-section-label">Where would you like to go?</div>

        <!-- Action cards -->
        <div class="ex297-cards">

            <a href="<?php echo esc_url( home_url( '/healing-command-center/' ) ); ?>" class="ex297-card ex297-card--primary">
                <div class="ex297-card-icon">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <circle cx="16" cy="16" r="14" stroke="#C9A84C" stroke-width="1.5" fill="none"/>
                        <path d="M8 16 Q11 10 14 16 Q17 22 20 16 Q23 10 24 14" stroke="#C9A84C" stroke-width="2" fill="none" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="ex297-card-body">
                    <div class="ex297-card-title">Today's Body Check</div>
                    <div class="ex297-card-desc">Submit today's saliva, urine, and bowel observations — Hermes returns your Body Score and full pattern analysis.</div>
                </div>
                <div class="ex297-card-arrow">→</div>
            </a>

            <a href="<?php echo esc_url( home_url( '/ask-the-healer/' ) ); ?>" class="ex297-card">
                <div class="ex297-card-icon">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <path d="M6 8h20v14a2 2 0 01-2 2H8a2 2 0 01-8-2z" stroke="#C9A84C" stroke-width="1.5" fill="none"/>
                        <circle cx="11" cy="15" r="1.5" fill="#C9A84C"/>
                        <circle cx="16" cy="15" r="1.5" fill="#C9A84C"/>
                        <circle cx="21" cy="15" r="1.5" fill="#C9A84C"/>
                        <path d="M10 8V6a2 2 0 014 0v2M18 8V6a2 2 0 014 0v2" stroke="#C9A84C" stroke-width="1.5"/>
                    </svg>
                </div>
                <div class="ex297-card-body">
                    <div class="ex297-card-title">Ministry of Healing</div>
                    <div class="ex297-card-desc">Private AI health intelligence. Ask anything about your patterns, concerns, or results.</div>
                </div>
                <div class="ex297-card-arrow">→</div>
            </a>

            <a href="<?php echo esc_url( home_url( '/ask-the-healer/#healing-protocol' ) ); ?>" class="ex297-card">
                <div class="ex297-card-icon">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <rect x="7" y="4" width="18" height="24" rx="3" stroke="#C9A84C" stroke-width="1.5" fill="none"/>
                        <path d="M11 11h10M11 15h10M11 19h6" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/>
                        <circle cx="24" cy="25" r="5" fill="#3D1060" stroke="#C9A84C" stroke-width="1.5"/>
                        <path d="M22 25h4M24 23v4" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="ex297-card-body">
                    <div class="ex297-card-title">Healing Protocol</div>
                    <div class="ex297-card-desc">Generate your full personalized protocol — dietary, supplement, lifestyle, and lab guidance.</div>
                </div>
                <div class="ex297-card-arrow">→</div>
            </a>

            <a href="<?php echo esc_url( home_url( '/welcome-member/' ) ); ?>" class="ex297-card">
                <div class="ex297-card-icon">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <rect x="5" y="3" width="22" height="26" rx="3" stroke="#C9A84C" stroke-width="1.5" fill="none"/>
                        <path d="M10 10h12M10 15h12M10 20h8" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="ex297-card-body">
                    <div class="ex297-card-title">Clinical Pattern Report</div>
                    <div class="ex297-card-desc">View your full pharmaceutical pattern analysis, red flags, drug interactions, and lab marker findings.</div>
                </div>
                <div class="ex297-card-arrow">→</div>
            </a>

            <a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>" class="ex297-card">
                <div class="ex297-card-icon">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <path d="M4 6h2.5l3.5 14h14l3-10H9" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        <circle cx="13" cy="25" r="2" fill="#C9A84C"/>
                        <circle cx="23" cy="25" r="2" fill="#C9A84C"/>
                    </svg>
                </div>
                <div class="ex297-card-body">
                    <div class="ex297-card-title ex297-card-title--store">Excreet Store</div>
                    <div class="ex297-card-desc">Shop the Excreet Signature Formula and curated health products — trusted by the Excreet team.</div>
                </div>
                <div class="ex297-card-arrow">→</div>
            </a>

            <a href="<?php echo esc_url( home_url( '/membership-account/' ) ); ?>" class="ex297-card">
                <div class="ex297-card-icon">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <circle cx="16" cy="11" r="5" stroke="#C9A84C" stroke-width="1.5" fill="none"/>
                        <path d="M6 27c0-5.523 4.477-10 10-10s10 4.477 10 10" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                    </svg>
                </div>
                <div class="ex297-card-body">
                    <div class="ex297-card-title">My Account</div>
                    <div class="ex297-card-desc">Membership details, billing, and profile settings.</div>
                </div>
                <div class="ex297-card-arrow">→</div>
            </a>

            <a href="<?php echo esc_url( home_url( '/record-my-story/' ) ); ?>" class="ex297-card ex297-card--story">
                <div class="ex297-card-icon">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <circle cx="16" cy="16" r="13" stroke="#C9A84C" stroke-width="1.5" fill="none"/>
                        <circle cx="16" cy="16" r="5" fill="#C9A84C" opacity=".9"/>
                        <circle cx="16" cy="16" r="2" fill="#1a0430"/>
                    </svg>
                </div>
                <div class="ex297-card-body">
                    <div class="ex297-card-title ex297-card-title--story">Share Your Story</div>
                    <div class="ex297-card-desc">Record a 90-second video testimonial. Your experience could be what someone else needs to hear.</div>
                </div>
                <div class="ex297-card-arrow">→</div>
            </a>

        </div>

        <!-- Footer note -->
        <div class="ex297-footer-note">
            Your data is private and used solely to support your awareness as an Excreet member.
        </div>

    </div>

    <style>
    /* ── Dashboard page background ── */
    body.page-id-<?php echo EX297_DASHBOARD_PAGE_ID; ?> {
        background:
            linear-gradient(160deg,rgba(13,1,32,.52) 0%,rgba(26,5,53,.20) 28%,rgba(26,5,53,.15) 62%,rgba(13,1,32,.50) 100%),
            url("https://excreet.com/wp-content/uploads/healer-bg-<?php echo str_pad((int)date('n'),2,'0',STR_PAD_LEFT); ?>.jpg")
            center/cover no-repeat fixed #0c0115 !important;
        min-height: 100vh !important;
    }
    body.page-id-<?php echo EX297_DASHBOARD_PAGE_ID; ?> .site-header,
    body.page-id-<?php echo EX297_DASHBOARD_PAGE_ID; ?> .site-footer,
    body.page-id-<?php echo EX297_DASHBOARD_PAGE_ID; ?> .elementor-location-header,
    body.page-id-<?php echo EX297_DASHBOARD_PAGE_ID; ?> .elementor-location-footer { display: none !important; }

    .ex297-dashboard {
        max-width: 820px;
        margin: 0 auto;
        padding: 3rem 1.5rem 5rem;
        font-family: 'Georgia', serif;
        color: #ffffff;
    }

    /* Header */
    .ex297-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid rgba(201,168,76,0.2);
    }
    .ex297-logo-mark {
        width: 52px; height: 52px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6B2FA0, #3D1060);
        border: 2px solid rgba(201,168,76,0.5);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; color: #C9A84C; font-family: 'Georgia', serif;
        flex-shrink: 0;
    }
    .ex297-greeting { flex: 1; }
    .ex297-greeting-sub { display: block; font-size: 0.88rem; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.82); }
    .ex297-greeting-name { font-size: 1.6rem; color: #ffffff; letter-spacing: 0.03em; }
    .ex297-member-badge {
        background: rgba(201,168,76,0.15);
        border: 1px solid rgba(201,168,76,0.4);
        color: #C9A84C;
        border-radius: 20px;
        padding: 0.3rem 0.9rem;
        font-size: 0.7rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    /* Stats */
    .ex297-stats {
        display: flex;
        align-items: center;
        justify-content: space-around;
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(201,168,76,0.18);
        border-radius: 14px;
        padding: 1.4rem 1rem;
        margin-bottom: 2.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .ex297-stat { text-align: center; min-width: 100px; }
    .ex297-stat-value { font-size: 1.15rem; color: #C9A84C; margin-bottom: 0.2rem; }
    .ex297-stat-label { font-size: 0.82rem; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.82); }
    .ex297-stat-divider { width: 1px; height: 36px; background: rgba(201,168,76,0.2); flex-shrink: 0; }
    @media (max-width: 600px) { .ex297-stat-divider { display: none; } }

    /* Section label */
    .ex297-section-label {
        font-size: 0.85rem;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.82);
        margin-bottom: 1rem;
    }

    /* Cards */
    .ex297-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 580px) { .ex297-cards { grid-template-columns: 1fr; } }

    .ex297-card {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1.4rem;
        background: rgba(255,255,255,0.055);
        border: 1px solid rgba(201,168,76,0.2);
        border-radius: 14px;
        text-decoration: none !important;
        color: inherit;
        transition: background 0.2s, border-color 0.2s, transform 0.15s;
        cursor: pointer;
    }
    .ex297-card:hover {
        background: rgba(255,255,255,0.09);
        border-color: rgba(201,168,76,0.45);
        transform: translateY(-2px);
        color: inherit;
    }
    .ex297-card--primary {
        border-color: rgba(201,168,76,0.4);
        background: rgba(201,168,76,0.07);
        grid-column: 1 / -1;
    }
    .ex297-card--primary:hover { background: rgba(201,168,76,0.12); }
    .ex297-card-icon { flex-shrink: 0; margin-top: 2px; }
    .ex297-card-body { flex: 1; }
    .ex297-card-title { font-size: 1rem; color: #ffffff; margin-bottom: 0.35rem; }
    .ex297-card--primary .ex297-card-title { color: #C9A84C; font-size: 1.05rem; }
    .ex297-card-title--store { color: #FFD700 !important; font-weight: 700; }
    .ex297-card-title--story { color: #F5C518 !important; font-weight: 700; }
    .ex297-card--story { border-color: rgba(245,197,24,.3) !important; }
    .ex297-card-desc { font-size: 0.92rem; color: rgba(255,255,255,0.88); line-height: 1.5; }
    .ex297-card-arrow { color: rgba(201,168,76,0.5); font-size: 1.1rem; align-self: center; flex-shrink: 0; transition: transform 0.15s; }
    .ex297-card:hover .ex297-card-arrow { transform: translateX(3px); color: #C9A84C; }

    /* Body Score banner */
    .ex297-score-banner {
        display: flex;
        align-items: center;
        gap: 1.2rem;
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(201,168,76,0.22);
        border-radius: 14px;
        padding: 1.2rem 1.6rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    .ex297-score-ring { flex-shrink: 0; }
    .ex297-score-details, .ex297-score-empty { flex: 1; min-width: 120px; }
    .ex297-score-label {
        font-size: 0.82rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.82);
        margin-bottom: 0.25rem;
    }
    .ex297-score-date { font-size: 0.92rem; color: rgba(255,255,255,0.82); }
    .ex297-score-link {
        margin-left: auto;
        font-size: 0.78rem;
        color: rgba(201,168,76,0.7);
        text-decoration: none;
        letter-spacing: 0.04em;
        white-space: nowrap;
        transition: color 0.15s;
    }
    .ex297-score-link:hover { color: #C9A84C; }
    .ex297-score-link--cta {
        background: rgba(201,168,76,0.12);
        border: 1px solid rgba(201,168,76,0.3);
        border-radius: 8px;
        padding: 0.45rem 1rem;
        color: #C9A84C;
    }
    .ex297-score-link--cta:hover { background: rgba(201,168,76,0.2); }

    /* Footer */
    .ex297-footer-note {
        margin-top: 2.5rem;
        text-align: center;
        font-size: 0.82rem;
        color: rgba(255,255,255,0.6);
        letter-spacing: 0.04em;
        font-style: italic;
    }
    </style>
    <?php
    return ob_get_clean();
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  C — LEGAL PAGE STYLING                                                      */
/*  Botanical palette (white card on monthly rotating nature background) for   */
/*  Terms & Waiver (ID 7), Privacy Policy (ID 3), Refund Policy (ID 177).      */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_head', 'excreet_297_legal_styles' );

function excreet_297_legal_styles(): void {
    $legal_ids = [ 7, 3, 177 ];
    if ( ! is_singular( 'page' ) || ! in_array( (int) get_the_ID(), $legal_ids, true ) ) {
        return;
    }
    $month = (int) wp_date( 'n' );
    $bg_url = 'https://excreet.com/wp-content/uploads/healer-bg-' . str_pad( $month, 2, '0', STR_PAD_LEFT ) . '.jpg';
    ?>
    <style>
    /* Legal page — Botanical full-page background */
    html, body.page-id-<?php echo (int) get_the_ID(); ?> {
        background: url('<?php echo esc_url( $bg_url ); ?>') center center / cover no-repeat fixed #1a0a30 !important;
        min-height: 100vh !important;
    }
    body.page-id-<?php echo (int) get_the_ID(); ?>::before {
        content: '';
        position: fixed;
        inset: 0;
        background: rgba(10,3,28,0.55);
        z-index: 0;
        pointer-events: none;
    }
    body.page-id-<?php echo (int) get_the_ID(); ?> .site-header,
    body.page-id-<?php echo (int) get_the_ID(); ?> .site-footer,
    body.page-id-<?php echo (int) get_the_ID(); ?> .elementor-location-header,
    body.page-id-<?php echo (int) get_the_ID(); ?> .elementor-location-footer {
        position: relative;
        z-index: 1;
    }

    /* Floating white card container */
    body.page-id-<?php echo (int) get_the_ID(); ?> .entry-content,
    body.page-id-<?php echo (int) get_the_ID(); ?> .elementor-widget-container,
    body.page-id-<?php echo (int) get_the_ID(); ?> .wp-block-post-content,
    body.page-id-<?php echo (int) get_the_ID(); ?> article.page {
        position: relative;
        z-index: 1;
        background: #ffffff !important;
        border: 1px solid #D5C5E8 !important;
        box-shadow: 0 8px 48px rgba(30,10,60,0.22), 0 2px 12px rgba(30,10,60,0.10) !important;
        border-radius: 20px !important;
        max-width: 780px !important;
        margin: 3rem auto 5rem !important;
        padding: 3rem 3.5rem !important;
        font-family: Georgia, 'Times New Roman', serif !important;
        color: #1A0A2E !important;
        line-height: 1.85 !important;
    }

    /* Headings */
    body.page-id-<?php echo (int) get_the_ID(); ?> .entry-content h1,
    body.page-id-<?php echo (int) get_the_ID(); ?> .entry-content h2,
    body.page-id-<?php echo (int) get_the_ID(); ?> .entry-content h3,
    body.page-id-<?php echo (int) get_the_ID(); ?> .entry-title {
        color: #6B2FA0 !important;
        font-family: Georgia, serif !important;
        letter-spacing: 0.02em !important;
    }

    /* Links */
    body.page-id-<?php echo (int) get_the_ID(); ?> .entry-content a {
        color: #6B2FA0 !important;
        text-decoration: underline !important;
    }

    /* Excreet legal wordmark */
    body.page-id-<?php echo (int) get_the_ID(); ?> .entry-content::before {
        content: 'EXCREET';
        display: block;
        text-align: center;
        color: #C9A84C;
        font-family: Georgia, serif;
        font-size: 1.1rem;
        letter-spacing: 0.45em;
        margin-bottom: 2.5rem;
        opacity: 0.85;
    }

    @media (max-width: 700px) {
        body.page-id-<?php echo (int) get_the_ID(); ?> .entry-content,
        body.page-id-<?php echo (int) get_the_ID(); ?> article.page {
            padding: 2rem 1.5rem !important;
            margin: 1.5rem 1rem 4rem !important;
            border-radius: 14px !important;
        }
    }
    </style>
    <?php
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  D — MINISTRY SESSION RESET                                                  */
/*  Injects a "Start New Session" button into the Ministry of Healing page.    */
/*  Member clicks → confirms in dialog → WP AJAX → Hermes reset endpoint.     */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_excreet_297_reset_ministry', 'excreet_297_ajax_reset_ministry' );

function excreet_297_ajax_reset_ministry(): void {
    check_ajax_referer( 'excreet_297_reset', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error( [ 'message' => 'Not authenticated.' ], 401 );
    }

    // Derive the base host from EXCREET_HERMES_URL (e.g. https://host/api/hermes/intake)
    // then swap the path for /api/hermes/ministry/history/reset.
    $hermes_base = defined( 'EXCREET_HERMES_URL' )
        ? rtrim( (string) EXCREET_HERMES_URL, '/' )
        : 'https://core-status-check.replit.app/api/hermes/intake';
    $reset_url   = preg_replace( '#/api/hermes/intake$#', '/api/hermes/ministry/history/reset', $hermes_base );
    if ( $reset_url === $hermes_base ) {
        $parsed    = parse_url( $hermes_base );
        $reset_url = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? 'core-status-check.replit.app' ) . '/api/hermes/ministry/history/reset';
    }

    $resp = wp_remote_post( $reset_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . ( defined( 'EXCREET_HERMES_API_KEY' ) ? EXCREET_HERMES_API_KEY : '' ),
            'Content-Type'  => 'application/json',
        ],
        'body'      => wp_json_encode( [ 'member_id' => (string) $user_id ] ),
        'timeout'   => 8,
        'sslverify' => true,
    ] );

    if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
        wp_send_json_error( [ 'message' => 'Could not reset session. Please try again.' ] );
    }

    wp_send_json_success( [ 'message' => 'Session cleared. Starting fresh.' ] );
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  E — STARTER PROMPT BUTTON REPAIR                                            */
/* ═══════════════════════════════════════════════════════════════════════════ */
// Patch-293's inline IIFE wires the starter button at parse time. Something
// on the page (Elementor frontend JS or another listener) strips or blocks
// that binding by the time the user interacts. This footer hook runs AFTER
// every other script has loaded, clones the button to wipe all accumulated
// listeners, and re-wires it with a class-based toggle backed by !important
// so no CSS rule can defeat it.

add_action( 'wp_footer', 'excreet_297_ministry_reset_btn', 98 );

function excreet_297_ministry_reset_btn(): void {
    if ( ! is_page( 'ask-the-healer' ) || ! is_user_logged_in() ) {
        return;
    }
    $user_id  = get_current_user_id();
    $ajax_url = esc_js( admin_url( 'admin-ajax.php' ) );
    $nonce    = wp_create_nonce( 'excreet_297_reset' );
    ?>
    <style>
    #ex297-reset-wrap {
        position: fixed;
        bottom: 1.2rem;
        right: 1.4rem;
        z-index: 9999;
    }
    #ex297-reset-btn {
        background: rgba(30,10,60,0.85);
        border: 1px solid rgba(201,168,76,0.35);
        color: rgba(201,168,76,0.7);
        border-radius: 10px;
        padding: 0.45rem 0.95rem;
        font-size: 0.72rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        cursor: pointer;
        font-family: Georgia, serif;
        backdrop-filter: blur(8px);
        transition: background 0.2s, border-color 0.2s, color 0.2s;
    }
    #ex297-reset-btn:hover {
        background: rgba(60,20,100,0.9);
        border-color: rgba(201,168,76,0.65);
        color: #C9A84C;
    }
    #ex297-reset-confirm {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(5,2,15,0.7);
        backdrop-filter: blur(4px);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    #ex297-reset-confirm.visible { display: flex; }
    #ex297-reset-dialog {
        background: #1a0535;
        border: 1px solid rgba(201,168,76,0.4);
        border-radius: 16px;
        padding: 2rem 2.2rem;
        max-width: 400px;
        width: calc(100% - 2rem);
        font-family: Georgia, serif;
        text-align: center;
        color: #e8e0d5;
    }
    #ex297-reset-dialog h3 {
        color: #C9A84C;
        font-size: 1.05rem;
        margin: 0 0 0.75rem;
        letter-spacing: 0.05em;
    }
    #ex297-reset-dialog p {
        font-size: 0.83rem;
        color: rgba(232,224,213,0.7);
        line-height: 1.6;
        margin: 0 0 1.4rem;
    }
    .ex297-reset-actions { display: flex; gap: 0.75rem; justify-content: center; }
    .ex297-reset-cancel {
        background: none;
        border: 1px solid rgba(201,168,76,0.3);
        color: rgba(201,168,76,0.7);
        border-radius: 8px;
        padding: 0.55rem 1.4rem;
        font-family: Georgia, serif;
        font-size: 0.82rem;
        cursor: pointer;
    }
    .ex297-reset-go {
        background: rgba(201,168,76,0.15);
        border: 1px solid rgba(201,168,76,0.5);
        color: #C9A84C;
        border-radius: 8px;
        padding: 0.55rem 1.4rem;
        font-family: Georgia, serif;
        font-size: 0.82rem;
        cursor: pointer;
        font-weight: 700;
    }
    .ex297-reset-go:hover { background: rgba(201,168,76,0.25); }
    </style>

    <div id="ex297-reset-wrap">
        <button id="ex297-reset-btn" type="button">Start New Session</button>
    </div>

    <div id="ex297-reset-confirm">
        <div id="ex297-reset-dialog">
            <h3>Start a New Session?</h3>
            <p>This will clear your full Ministry of Healing conversation history. Your Clinical Pattern Report and Body Check data are not affected.</p>
            <div class="ex297-reset-actions">
                <button class="ex297-reset-cancel" type="button">Cancel</button>
                <button class="ex297-reset-go" type="button">Yes, Clear History</button>
            </div>
            <div id="ex297-reset-status" style="font-size:0.78rem;margin-top:0.9rem;color:rgba(201,168,76,0.8);display:none;"></div>
        </div>
    </div>

    <script>
    (function () {
        var btn     = document.getElementById('ex297-reset-btn');
        var modal   = document.getElementById('ex297-reset-confirm');
        var cancel  = modal.querySelector('.ex297-reset-cancel');
        var confirm = modal.querySelector('.ex297-reset-go');
        var status  = document.getElementById('ex297-reset-status');

        btn.addEventListener('click', function () {
            modal.classList.add('visible');
        });
        cancel.addEventListener('click', function () {
            modal.classList.remove('visible');
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.classList.remove('visible');
        });

        confirm.addEventListener('click', function () {
            confirm.disabled = true;
            cancel.disabled  = true;
            status.textContent = 'Clearing history…';
            status.style.display = 'block';

            var fd = new FormData();
            fd.append('action', 'excreet_297_reset_ministry');
            fd.append('nonce',  <?php echo wp_json_encode( $nonce ); ?>);
            fd.append('user_id', <?php echo wp_json_encode( (string) $user_id ); ?>);

            fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    status.textContent = '✓ Session cleared. Reloading…';
                    setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    status.textContent = (data.data && data.data.message) || 'Something went wrong.';
                    confirm.disabled = false;
                    cancel.disabled  = false;
                }
            })
            .catch(function () {
                status.textContent = 'Connection error. Please try again.';
                confirm.disabled = false;
                cancel.disabled  = false;
            });
        });
    })();
    </script>
    <?php
}

add_action( 'wp_footer', 'excreet_297_fix_starter_btn', 99 );

function excreet_297_fix_starter_btn(): void {
    if ( ! is_page( 'ask-the-healer' ) ) {
        return;
    }
    ?>
    <style>
    #excreet-moh-starter-tip.moh-tip-visible {
        display: block !important;
    }
    </style>
    <script>
    (function () {
        function wireBtnOnce() {
            var btn = document.getElementById('excreet-moh-starter-btn');
            var tip = document.getElementById('excreet-moh-starter-tip');
            if ( !btn || !tip ) { return; }

            // Clone → replaceChild strips ALL existing event listeners from btn
            var fresh = btn.cloneNode(true);
            btn.parentNode.replaceChild(fresh, btn);

            fresh.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = tip.classList.contains('moh-tip-visible');
                tip.classList.toggle('moh-tip-visible', !isOpen);
                fresh.textContent = isOpen ? '📋 Starter Prompt' : '✕ Hide template';
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', wireBtnOnce);
        } else {
            wireBtnOnce();
        }
    })();
    </script>
    <?php
}
