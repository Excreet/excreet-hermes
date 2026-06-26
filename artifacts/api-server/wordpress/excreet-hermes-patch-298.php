<?php
/**
 * Plugin Name: Excreet Hermes Patch 2.9.8
 * Description: Body Check v2 — 5-step daily wizard (wellbeing +
 *              hydration/digestion + urine + vitals + 4 required photos)
 *              submitted to Hermes for full AI pattern analysis.
 *              Renders on Body Check page (page 257).
 *
 * Version:    2.9.8j
 * Depends on: excreet-hermes-client.php  (EXCREET_HERMES_API_KEY, EXCREET_HERMES_URL)
 *             excreet-hermes-patch-296.php (excreet_296_is_member)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── Constants ────────────────────────────────────────────────────────────── */

define( 'EX298_HCC_PAGE_ID',    257 );
define( 'EX298_PAGE_SETUP_OPT', '_excreet_298_page_setup' );

if ( ! defined( 'EXCREET_HERMES_BODY_SNAPSHOT_URL' ) ) {
    $hermes_base = defined( 'EXCREET_HERMES_URL' )
        ? rtrim( (string) EXCREET_HERMES_URL, '/' )
        : 'https://core-status-check.replit.app/api/hermes/intake';
    $body_url = preg_replace( '#/api/hermes/intake$#', '/api/hermes/body-snapshot', $hermes_base );
    if ( $body_url === $hermes_base ) {
        $parsed   = parse_url( $hermes_base );
        $body_url = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? 'core-status-check.replit.app' ) . '/api/hermes/body-snapshot';
    }
    define( 'EXCREET_HERMES_BODY_SNAPSHOT_URL', $body_url );
}

/* ── Hooks ────────────────────────────────────────────────────────────────── */

add_action( 'init',                         'excreet_298_setup',         1   );
add_action( 'template_redirect',            'excreet_298_gate',          20  );
add_action( 'wp_ajax_excreet_body_snapshot',         'excreet_298_ajax_snapshot' );
add_action( 'wp_ajax_excreet_body_snapshot_history', 'excreet_298_ajax_history'  );
add_shortcode( 'excreet_body_snapshot',      'excreet_298_shortcode'           );

// Belt-and-suspenders: override whatever Elementor renders with our shortcode
// on this specific page. Priority 999 runs after Elementor's own the_content hook.
add_filter( 'the_content', 'excreet_298_force_content', 999 );

function excreet_298_force_content( string $content ): string {
    if ( ! is_page( EX298_HCC_PAGE_ID ) ) {
        return $content;
    }
    return do_shortcode( '[excreet_body_snapshot]' );
}

/* ── Setup: strip Elementor data so our shortcode controls the page ──────── */
// Uses _v2 option name so it re-runs even if the old option was already set.

function excreet_298_setup(): void {
    if ( get_option( '_excreet_298_page_setup_v2' ) ) {
        return;
    }

    // Remove Elementor's stored layout — without this, Elementor ignores
    // post_content and renders its own JSON instead of our shortcode.
    delete_post_meta( EX298_HCC_PAGE_ID, '_elementor_data' );
    delete_post_meta( EX298_HCC_PAGE_ID, '_elementor_edit_mode' );

    wp_update_post( [
        'ID'           => EX298_HCC_PAGE_ID,
        'post_content' => '[excreet_body_snapshot]',
        'post_status'  => 'publish',
    ] );

    update_option( '_excreet_298_page_setup_v2', '1' );
}

/* ── Gate: logged-in only ─────────────────────────────────────────────────── */

function excreet_298_gate(): void {
    if ( ! is_page( EX298_HCC_PAGE_ID ) ) { return; }
    if ( is_user_logged_in() ) { return; }
    wp_redirect( home_url( '/mp-login/' ) );
    exit;
}

/* ════════════════════════════════════════════════════════════════════════════
   AJAX HANDLER
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_298_ajax_snapshot(): void {
    check_ajax_referer( 'excreet_body_snapshot', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'code' => 'not_logged_in' ], 401 );
    }

    $is_member = function_exists( 'excreet_296_is_member' )
        ? excreet_296_is_member()
        : ( function_exists( 'excreet_291_is_member' ) && excreet_291_is_member() );

    if ( ! $is_member ) {
        wp_send_json_error( [ 'code' => 'not_member', 'message' => 'Active membership required.' ], 403 );
    }

    if ( ! defined( 'EXCREET_HERMES_API_KEY' ) ) {
        wp_send_json_error( [ 'code' => 'config_error', 'message' => 'Hermes key not configured.' ], 500 );
    }

    $user      = wp_get_current_user();
    $member_id = $user->user_login ?: (string) get_current_user_id();

    /* ── Photos: accept, validate, compress check ── */
    $max_bytes    = 12 * 1024 * 1024;
    $allowed_mime = [ 'image/jpeg', 'image/png', 'image/webp' ];
    $photos       = [];
    $photo_keys   = [ 'bowel', 'urine', 'urineStrip', 'salivaStrip' ];
    $photo_labels = [
        'bowel'       => 'Bowel Movement',
        'urine'       => 'Urine Sample',
        'urineStrip'  => 'Urine pH Strip',
        'salivaStrip' => 'Saliva pH Strip',
    ];

    foreach ( $photo_keys as $key ) {
        $raw = isset( $_POST[ 'photo_' . $key ] ) ? wp_unslash( (string) $_POST[ 'photo_' . $key ] ) : '';
        if ( $raw === '' ) { continue; }
        if ( preg_match( '#^data:(image/[a-z]+);base64,#', $raw, $m ) ) {
            if ( ! in_array( $m[1], $allowed_mime, true ) ) { continue; }
            $raw = preg_replace( '#^data:image/[a-z]+;base64,#', '', $raw );
        }
        $decoded = base64_decode( $raw, true );
        if ( $decoded === false || strlen( $decoded ) > $max_bytes ) { continue; }
        $photos[ $key ] = $raw;
    }

    $is_admin_test = current_user_can( 'manage_options' );
    $snapshot_mode = in_array(
        sanitize_text_field( wp_unslash( $_POST['snapshot_mode'] ?? '' ) ),
        [ 'quick', 'full' ],
        true
    ) ? sanitize_text_field( wp_unslash( $_POST['snapshot_mode'] ) ) : 'quick';

    if ( ! $is_admin_test ) {
        $required_photos = ( $snapshot_mode === 'full' )
            ? $photo_keys
            : [ 'bowel', 'urine' ];
        foreach ( $required_photos as $key ) {
            if ( empty( $photos[ $key ] ) ) {
                wp_send_json_error( [
                    'code'    => 'missing_photo',
                    'message' => ( $photo_labels[ $key ] ?? $key ) . ' photo is required. Please go back and upload it.',
                ], 422 );
            }
        }
    }

    /* ── Questionnaire ── */
    $s = function ( string $key ): string {
        return sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
    };

    $hermes_payload = [
        'member_id' => $member_id,
        'photos'    => $photos,
        'questionnaire' => [
            'snapshotMode'       => $snapshot_mode,
            'energyLevel'        => $s( 'energy_level' ),
            'mood'               => $s( 'mood' ),
            'symptomIntensity'   => $s( 'symptom_intensity' ),
            'waterOz'            => $s( 'water_oz' ),
            'bowelToday'         => $s( 'bowel_today' ),
            'bowelMinutes'       => $s( 'bowel_minutes' ),
            'bowelUncomfortable' => $s( 'bowel_uncomfortable' ),
            'stoolOdor'          => $s( 'stool_odor' ),
            'urineOdor'          => $s( 'urine_odor' ),
            'urineUncomfortable' => $s( 'urine_uncomfortable' ),
            'urineColor'         => $s( 'urine_color' ),
            'bodyTemp'           => $s( 'body_temp' ),
            'zipCode'            => $s( 'zip_code' ),
        ],
    ];

    $response = wp_remote_post( EXCREET_HERMES_BODY_SNAPSHOT_URL, [
        'timeout' => 90,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY,
        ],
        'body' => wp_json_encode( $hermes_payload ),
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [
            'code'    => 'hermes_error',
            'message' => 'Could not reach the analysis engine. Please try again.',
        ], 502 );
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code !== 200 || ! isset( $data['result'] ) ) {
        wp_send_json_error( [
            'code'        => 'analysis_failed',
            'hermes_code' => $code,
            'hermes_body' => is_array( $data ) ? $data : wp_remote_retrieve_body( $response ),
            'message'     => 'The analysis could not be completed. Please try again. (Hermes: ' . $code . ')',
        ], 500 );
    }

    $user_id = get_current_user_id();
    if ( $user_id ) {
        update_user_meta( $user_id, 'excreet_body_snapshot_latest', wp_json_encode( $data['result'] ) );
        update_user_meta( $user_id, 'excreet_body_snapshot_date',   gmdate( 'Y-m-d' ) );
    }

    wp_send_json_success( [ 'result' => $data['result'] ] );
}

/* ════════════════════════════════════════════════════════════════════════════
   AJAX — SCORE HISTORY
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_298_ajax_history(): void {
    check_ajax_referer( 'excreet_body_snapshot', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'code' => 'not_logged_in' ], 401 );
    }

    if ( ! defined( 'EXCREET_HERMES_API_KEY' ) || ! defined( 'EXCREET_HERMES_BODY_SNAPSHOT_URL' ) ) {
        wp_send_json_error( [ 'code' => 'config_error' ], 500 );
    }

    $user      = wp_get_current_user();
    $member_id = $user->user_login ?: (string) get_current_user_id();
    $hist_url  = rtrim( EXCREET_HERMES_BODY_SNAPSHOT_URL, '/' ) . '/history/' . rawurlencode( $member_id );

    $response = wp_remote_get( $hist_url, [
        'timeout' => 8,
        'headers' => [ 'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY ],
    ]);

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'code' => 'hermes_error' ], 502 );
        return;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    wp_send_json_success( [ 'history' => $data['history'] ?? [] ] );
}

/* ════════════════════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_298_yn( string $key ): string {
    return '<div class="ex298-yesno" data-key="' . esc_attr( $key ) . '">'
         . '<button class="ex298-yn-btn" data-val="yes" type="button">Yes</button>'
         . '<button class="ex298-yn-btn" data-val="no"  type="button">No</button>'
         . '</div>';
}

function excreet_298_rating_btns( string $key ): string {
    $html = '<div class="ex298-rating-btns" data-rating="' . esc_attr( $key ) . '">';
    for ( $i = 1; $i <= 10; $i++ ) {
        $html .= '<button class="ex298-rb" data-val="' . $i . '" type="button">' . $i . '</button>';
    }
    $html .= '</div>';
    return $html;
}

/* ════════════════════════════════════════════════════════════════════════════
   SHORTCODE  [excreet_body_snapshot]
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_298_shortcode(): string {
    if ( ! is_user_logged_in() ) {
        return '<p style="color:#fff;text-align:center;padding:3rem;">Please <a href="' . esc_url( home_url( '/login/' ) ) . '" style="color:#C9A84C;">log in</a> to access your Body Check.</p>';
    }

    $user_id       = get_current_user_id();
    $is_admin      = current_user_can( 'manage_options' );
    $nonce         = wp_create_nonce( 'excreet_body_snapshot' );
    $ajax_url      = admin_url( 'admin-ajax.php' );
    $today         = wp_date( 'l, F j, Y' );
    $stored_date   = (string) get_user_meta( $user_id, 'excreet_body_snapshot_date',   true );
    $stored_result = (string) get_user_meta( $user_id, 'excreet_body_snapshot_latest', true );
    $has_today     = ( $stored_date === gmdate( 'Y-m-d' ) && $stored_result !== '' );

    /* ── Phase 5: Hermes DB fallback (cross-device, survives cache clears) ── *
     * If WP user-meta doesn't have today's snapshot (e.g. first login on a   *
     * new device, or meta was cleared), check the Hermes PostgreSQL store.    *
     * On a hit, hydrate WP meta so the next page load is instant.             */
    if ( ! $has_today && defined( 'EXCREET_HERMES_API_KEY' ) && defined( 'EXCREET_HERMES_BODY_SNAPSHOT_URL' ) ) {
        $user      = wp_get_current_user();
        $member_id = $user->user_login ?: (string) $user_id;
        $today_url = rtrim( EXCREET_HERMES_BODY_SNAPSHOT_URL, '/' ) . '/today/' . rawurlencode( $member_id );

        $hermes_resp = wp_remote_get( $today_url, [
            'timeout' => 6,
            'headers' => [ 'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY ],
        ]);

        if ( ! is_wp_error( $hermes_resp ) && (int) wp_remote_retrieve_response_code( $hermes_resp ) === 200 ) {
            $hermes_data = json_decode( wp_remote_retrieve_body( $hermes_resp ), true );
            if ( isset( $hermes_data['result'] ) && is_array( $hermes_data['result'] ) ) {
                $stored_result = (string) wp_json_encode( $hermes_data['result'] );
                $has_today     = true;
                /* Hydrate WP meta so next load is instant */
                update_user_meta( $user_id, 'excreet_body_snapshot_latest', $stored_result );
                update_user_meta( $user_id, 'excreet_body_snapshot_date',   gmdate( 'Y-m-d' ) );
            }
        }
    }

    $colors = [
        'pale straw / very dilute' => [ 'label' => 'Pale / Clear', 'hex' => '#FFFFF0' ],
        'normal yellow'            => [ 'label' => 'Yellow',       'hex' => '#FFD700' ],
        'dark yellow'              => [ 'label' => 'Dark Yellow',   'hex' => '#C8A800' ],
        'amber'                    => [ 'label' => 'Amber',         'hex' => '#DAA520' ],
        'orange'                   => [ 'label' => 'Orange',        'hex' => '#FF8C00' ],
        'cloudy / milky'           => [ 'label' => 'Cloudy',        'hex' => '#D3D3C0' ],
        'pink or red tinged'       => [ 'label' => 'Pink / Red',    'hex' => '#FFB0B0' ],
    ];

    ob_start();
    ?>
    <div class="ex298-wrap" id="ex298-wrap">

        <!-- Header -->
        <div class="ex298-header">
            <div class="ex298-logo-mark">
                <img src="https://excreet.com/wp-content/uploads/2026/05/excreet-hero-logo.png" alt="Excreet" width="42" height="42" style="border-radius:50%;object-fit:cover;display:block;">
            </div>
            <div>
                <div class="ex298-title">Body Check</div>
                <div class="ex298-date"><?php echo esc_html( $today ); ?></div>
            </div>
            <a href="<?php echo esc_url( home_url( '/member-dashboard/' ) ); ?>" class="ex298-back">← Dashboard</a>
        </div>

        <!-- Admin test mode banner -->
        <?php if ( $is_admin ) : ?>
        <div class="ex298-admin-banner">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="#f39c12" stroke-width="1.5"/><path d="M10 6v4M10 14h.01" stroke="#f39c12" stroke-width="1.5" stroke-linecap="round"/></svg>
            Admin Test Mode — all validation &amp; photo requirements bypassed.
            <button class="ex298-admin-autofill" id="ex298-autofill" type="button">Auto-fill &amp; jump to photos →</button>
        </div>
        <?php endif; ?>

        <!-- Already submitted today -->
        <?php if ( $has_today ) : ?>
        <div class="ex298-already-done" id="ex298-already-done">
            <div class="ex298-done-msg">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#C9A84C" stroke-width="1.5"/><path d="M7 12l3.5 3.5L17 9" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Today's snapshot is complete.
            </div>
            <button class="ex298-redo-btn" id="ex298-redo-btn" type="button">Submit a new one</button>
            <!-- Trend chart loads async after page render -->
            <div id="ex298-trend-area" class="ex298-trend-area"></div>
            <div id="ex298-today-result">
                <?php echo excreet_298_render_result( json_decode( $stored_result, true ) ); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 5-step wizard -->
        <div class="ex298-form-wrap" id="ex298-form-wrap"<?php echo $has_today ? ' style="display:none"' : ''; ?>>

            <!-- ── Mode Selector ─────────────────────────────────────────── -->
            <div id="ex298-mode-select">
                <div class="ex298-mode-heading">How much time do you have this morning?</div>
                <div class="ex298-mode-sub">Both sessions run all 5 check-in steps. The difference is what you photograph in Step 5.</div>
                <div class="ex298-mode-cards">

                    <button class="ex298-mode-card" type="button" onclick="exSelectMode('quick')">
                        <div class="ex298-mode-pill quick">QUICK</div>
                        <div class="ex298-mode-mins">~4 min</div>
                        <div class="ex298-mode-name">Quick Body Check</div>
                        <div class="ex298-mode-pct">80% signal read</div>
                        <div class="ex298-mode-items">
                            <div>✓ Full 5-step check-in</div>
                            <div>✓ Bowel movement photo</div>
                            <div>✓ Urine sample photo</div>
                            <div class="ex298-mode-skip">— pH strips: today's pass</div>
                        </div>
                        <div class="ex298-mode-ideal">Best for daily habit building</div>
                    </button>

                    <button class="ex298-mode-card ex298-mode-card-full" type="button" onclick="exSelectMode('full')">
                        <div class="ex298-mode-pill full">FULL</div>
                        <div class="ex298-mode-mins">~10 min</div>
                        <div class="ex298-mode-name">Full Body Check</div>
                        <div class="ex298-mode-pct">Complete 100% read</div>
                        <div class="ex298-mode-items">
                            <div>✓ Full 5-step check-in</div>
                            <div>✓ Bowel movement photo</div>
                            <div>✓ Urine sample photo</div>
                            <div>✓ Urine pH strip</div>
                            <div>✓ Saliva pH strip</div>
                        </div>
                        <div class="ex298-mode-ideal">Recommended at least 3× per week</div>
                    </button>

                </div>
            </div><!-- /#ex298-mode-select -->

            <!-- ── Wizard (shown after mode selected) ──────────────────────── -->
            <div id="ex298-wizard" style="display:none;">

            <!-- Progress -->
            <div class="ex298-progress">
                <div class="ex298-dots">
                    <div class="ex298-dot active" data-step="1">1</div>
                    <div class="ex298-connector" data-conn="1"></div>
                    <div class="ex298-dot" data-step="2">2</div>
                    <div class="ex298-connector" data-conn="2"></div>
                    <div class="ex298-dot" data-step="3">3</div>
                    <div class="ex298-connector" data-conn="3"></div>
                    <div class="ex298-dot" data-step="4">4</div>
                    <div class="ex298-connector" data-conn="4"></div>
                    <div class="ex298-dot" data-step="5">5</div>
                </div>
                <div class="ex298-step-lbl" id="ex298-step-lbl">Step 1 of 5 — How Are You Feeling?</div>
            </div>

            <!-- Step error -->
            <div class="ex298-step-error" id="ex298-step-error" style="display:none;"></div>

            <!-- ── Step 1: Wellbeing ─────────────────────────────────────── -->
            <div class="ex298-step" id="ex298-step-1">
                <div class="ex298-step-title">How Are You Feeling Today?</div>
                <p class="ex298-step-desc">Rate your current state before starting your day. Be honest — this builds your baseline pattern.</p>

                <div class="ex298-rating-group">
                    <div class="ex298-rating-label">Energy Level</div>
                    <div class="ex298-rating-sub">1 = exhausted &nbsp;·&nbsp; 10 = excellent</div>
                    <?php echo excreet_298_rating_btns( 'energy' ); ?>
                </div>

                <div class="ex298-rating-group">
                    <div class="ex298-rating-label">Mood</div>
                    <div class="ex298-rating-sub">1 = very low &nbsp;·&nbsp; 10 = excellent</div>
                    <?php echo excreet_298_rating_btns( 'mood' ); ?>
                </div>

                <div class="ex298-rating-group">
                    <div class="ex298-rating-label">Symptom Intensity</div>
                    <div class="ex298-rating-sub">1 = none &nbsp;·&nbsp; 10 = severe</div>
                    <?php echo excreet_298_rating_btns( 'symptoms' ); ?>
                </div>

                <div class="ex298-nav-row">
                    <span></span>
                    <button class="ex298-next-btn" type="button" onclick="exStep(2)">Continue →</button>
                </div>
            </div>

            <!-- ── Step 2: Hydration & Digestion ─────────────────────────── -->
            <div class="ex298-step" id="ex298-step-2" style="display:none;">
                <div class="ex298-step-title">Hydration & Digestion</div>
                <p class="ex298-step-desc">Record yesterday's fluids and this morning's bowel movement. These are core signals.</p>

                <div class="ex298-q-block">
                    <label class="ex298-q-label" for="q-water-oz">Total water / fluids yesterday (oz) <span class="ex298-req">*</span></label>
                    <input type="number" id="q-water-oz" class="ex298-num-input" placeholder="e.g. 48" min="0" max="500" inputmode="numeric">
                </div>

                <div class="ex298-q-block">
                    <div class="ex298-q-label">Did you have a bowel movement today? <span class="ex298-req">*</span></div>
                    <?php echo excreet_298_yn( 'bowelToday' ); ?>
                </div>

                <!-- Bowel detail — shown conditionally when bowelToday = yes -->
                <div class="ex298-conditional" id="ex298-bowel-details">

                    <div class="ex298-q-block">
                        <label class="ex298-q-label" for="q-bowel-minutes">How many minutes did it take? <span class="ex298-req">*</span></label>
                        <input type="number" id="q-bowel-minutes" class="ex298-num-input" placeholder="e.g. 5" min="1" max="120" inputmode="numeric">
                    </div>

                    <div class="ex298-q-block">
                        <div class="ex298-q-label">Was the bowel movement uncomfortable? <span class="ex298-req">*</span></div>
                        <?php echo excreet_298_yn( 'bowelUncomfortable' ); ?>
                    </div>

                    <div class="ex298-q-block">
                        <div class="ex298-q-label">Did the stool have notable odor? <span class="ex298-req">*</span></div>
                        <?php echo excreet_298_yn( 'stoolOdor' ); ?>
                    </div>

                </div><!-- /#ex298-bowel-details -->

                <div class="ex298-nav-row">
                    <button class="ex298-back-btn" type="button" onclick="exStep(1)">← Back</button>
                    <button class="ex298-next-btn" type="button" onclick="exStep(3)">Continue →</button>
                </div>
            </div>

            <!-- ── Step 3: Morning Urine ──────────────────────────────────── -->
            <div class="ex298-step" id="ex298-step-3" style="display:none;">
                <div class="ex298-step-title">Morning Urine</div>
                <p class="ex298-step-desc">Urine is one of the body's clearest daily signals. Answer before uploading your photo.</p>

                <div class="ex298-q-block">
                    <div class="ex298-q-label">Did your morning urine have notable odor? <span class="ex298-req">*</span></div>
                    <?php echo excreet_298_yn( 'urineOdor' ); ?>
                </div>

                <div class="ex298-q-block">
                    <div class="ex298-q-label">Was urination uncomfortable or painful? <span class="ex298-req">*</span></div>
                    <?php echo excreet_298_yn( 'urineUncomfortable' ); ?>
                </div>

                <div class="ex298-q-block">
                    <div class="ex298-q-label">Select your urine color <span class="ex298-req">*</span></div>
                    <div class="ex298-color-grid">
                        <?php foreach ( $colors as $val => $info ) : ?>
                        <button class="ex298-color-btn" data-val="<?php echo esc_attr( $val ); ?>" type="button">
                            <span class="ex298-color-swatch" style="background:<?php echo esc_attr( $info['hex'] ); ?>;"></span>
                            <span class="ex298-color-name"><?php echo esc_html( $info['label'] ); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ex298-nav-row">
                    <button class="ex298-back-btn" type="button" onclick="exStep(2)">← Back</button>
                    <button class="ex298-next-btn" type="button" onclick="exStep(4)">Continue →</button>
                </div>
            </div>

            <!-- ── Step 4: Vitals ─────────────────────────────────────────── -->
            <div class="ex298-step" id="ex298-step-4" style="display:none;">
                <div class="ex298-step-title">Vitals & Location</div>
                <p class="ex298-step-desc">Your location gives Hermes environmental context. Temperature is optional but adds precision.</p>

                <div class="ex298-q-block">
                    <label class="ex298-q-label" for="q-body-temp">Morning body temperature (°F) <span style="opacity:.55;font-weight:400;">— optional</span></label>
                    <input type="number" id="q-body-temp" class="ex298-num-input" placeholder="e.g. 98.4" min="95" max="106" step="0.1" inputmode="decimal">
                    <div class="ex298-q-hint">Take before eating, drinking, or getting up. Normal range: 97.8–99.1°F.</div>
                </div>

                <div class="ex298-q-block">
                    <label class="ex298-q-label" for="q-zip">Postal / Zip Code <span class="ex298-req">*</span></label>
                    <input type="text" id="q-zip" class="ex298-num-input" placeholder="e.g. 90210" maxlength="10" inputmode="numeric">
                    <div class="ex298-q-hint">Used for environmental context (climate, air quality, altitude). Never shared.</div>
                </div>

                <div class="ex298-nav-row">
                    <button class="ex298-back-btn" type="button" onclick="exStep(3)">← Back</button>
                    <button class="ex298-next-btn" type="button" onclick="exStep(5)">Continue →</button>
                </div>
            </div>

            <!-- ── Step 5: Photo Evidence ─────────────────────────────────── -->
            <div class="ex298-step" id="ex298-step-5" style="display:none;">
                <div class="ex298-step-title">Photo Evidence</div>
                <p class="ex298-step-desc" id="ex298-step5-desc">Two clear photos required — bowel movement and urine sample. Good lighting, close up.</p>

                <!-- Always required: bowel + urine -->
                <div class="ex298-photo-grid">

                    <div class="ex298-photo-zone" id="zone-bowel">
                        <input type="file" id="photo-bowel" accept="image/*" capture="environment" class="ex298-file-input">
                        <label for="photo-bowel" class="ex298-photo-label" id="label-bowel">
                            <svg width="32" height="32" viewBox="0 0 36 36" fill="none"><rect x="4" y="8" width="28" height="22" rx="3" stroke="#C9A84C" stroke-width="1.5"/><circle cx="18" cy="19" r="6" stroke="#C9A84C" stroke-width="1.5"/><path d="M14 8V6M22 8V6" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/></svg>
                            <span class="ex298-photo-name">Bowel Movement</span>
                            <span class="ex298-photo-badge">Required</span>
                        </label>
                        <img id="preview-bowel" class="ex298-preview" alt="">
                        <div class="ex298-photo-tip">Clear photo, good light. No identifying features needed.</div>
                    </div>

                    <div class="ex298-photo-zone" id="zone-urine">
                        <input type="file" id="photo-urine" accept="image/*" capture="environment" class="ex298-file-input">
                        <label for="photo-urine" class="ex298-photo-label" id="label-urine">
                            <svg width="32" height="32" viewBox="0 0 36 36" fill="none"><rect x="4" y="8" width="28" height="22" rx="3" stroke="#C9A84C" stroke-width="1.5"/><circle cx="18" cy="19" r="6" stroke="#C9A84C" stroke-width="1.5"/><path d="M14 8V6M22 8V6" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/></svg>
                            <span class="ex298-photo-name">Urine Sample</span>
                            <span class="ex298-photo-badge">Required</span>
                        </label>
                        <img id="preview-urine" class="ex298-preview" alt="">
                        <div class="ex298-photo-tip">Pour first-morning urine into a clear cup. Photograph in natural light.</div>
                    </div>

                </div><!-- /.ex298-photo-grid -->

                <!-- Full mode only: pH strips -->
                <div class="ex298-strip-zone" id="ex298-strip-zone" style="display:none;">
                    <div class="ex298-strip-header">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><rect x="3" y="2" width="14" height="16" rx="2" stroke="#C9A84C" stroke-width="1.5"/><path d="M6 7h8M6 10h8M6 13h5" stroke="#C9A84C" stroke-width="1.2" stroke-linecap="round"/></svg>
                        pH Strip Analysis
                        <span class="ex298-strip-sub">Allow 30–60 sec per strip to develop before photographing</span>
                    </div>
                    <div class="ex298-photo-grid">

                        <div class="ex298-photo-zone" id="zone-urine-strip">
                            <input type="file" id="photo-urine-strip" accept="image/*" capture="environment" class="ex298-file-input">
                            <label for="photo-urine-strip" class="ex298-photo-label" id="label-urine-strip">
                                <svg width="32" height="32" viewBox="0 0 36 36" fill="none"><rect x="4" y="8" width="28" height="22" rx="3" stroke="#C9A84C" stroke-width="1.5"/><circle cx="18" cy="19" r="6" stroke="#C9A84C" stroke-width="1.5"/><path d="M14 8V6M22 8V6" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/></svg>
                                <span class="ex298-photo-name">Urine pH Strip</span>
                                <span class="ex298-photo-badge">Required</span>
                            </label>
                            <img id="preview-urine-strip" class="ex298-preview" alt="">
                            <div class="ex298-photo-tip">Dip Multistix 10 SG into urine. Wait 60 sec. Photograph all color pads in bright light.</div>
                        </div>

                        <div class="ex298-photo-zone" id="zone-saliva-strip">
                            <input type="file" id="photo-saliva-strip" accept="image/*" capture="environment" class="ex298-file-input">
                            <label for="photo-saliva-strip" class="ex298-photo-label" id="label-saliva-strip">
                                <svg width="32" height="32" viewBox="0 0 36 36" fill="none"><rect x="4" y="8" width="28" height="22" rx="3" stroke="#C9A84C" stroke-width="1.5"/><circle cx="18" cy="19" r="6" stroke="#C9A84C" stroke-width="1.5"/><path d="M14 8V6M22 8V6" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/></svg>
                                <span class="ex298-photo-name">Saliva pH Strip</span>
                                <span class="ex298-photo-badge">Required</span>
                            </label>
                            <img id="preview-saliva-strip" class="ex298-preview" alt="">
                            <div class="ex298-photo-tip">Collect saliva on a spoon. Dip strip 5 sec. Wait 30 sec. Photograph color result.</div>
                        </div>

                    </div><!-- /.ex298-photo-grid (strips) -->
                </div><!-- /#ex298-strip-zone -->

                <div class="ex298-nav-row" style="margin-top:2rem;">
                    <button class="ex298-back-btn" type="button" onclick="exStep(4)">← Back</button>
                    <button class="ex298-submit-btn" id="ex298-submit" type="button">Analyze My Body Check →</button>
                </div>
            </div>

            </div><!-- /#ex298-wizard -->
        </div><!-- /#ex298-form-wrap -->

        <!-- Loading -->
        <div class="ex298-loading" id="ex298-loading" style="display:none;">
            <div class="ex298-spinner"></div>
            <div class="ex298-loading-text">Reading your daily signals…</div>
            <div class="ex298-loading-sub">Analyzing all 5 data streams. Takes about 20–40 seconds.</div>
        </div>

        <!-- Result -->
        <div id="ex298-result-area" style="display:none;"></div>
        <!-- Trend chart for the fresh-submission path -->
        <div id="ex298-trend-area" class="ex298-trend-area" style="display:none;"></div>

        <!-- Error -->
        <div class="ex298-error" id="ex298-error" style="display:none;"></div>

    </div><!-- /.ex298-wrap -->

    <?php excreet_298_styles(); ?>
    <?php excreet_298_script( $ajax_url, $nonce, $is_admin ); ?>
    <?php

    return ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   RESULT RENDERER (server-side, for cached/stored results)
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_298_render_result( ?array $r ): string {
    if ( empty( $r ) ) { return ''; }

    /* ── unicode-escape sequences that Claude may emit (e.g. \u2014 for —) ── */
    $decode = function( string $s ): string {
        return preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            fn( $m ) => mb_convert_encoding( pack( 'H*', $m[1] ), 'UTF-8', 'UCS-2BE' ),
            $s
        ) ?? $s;
    };

    $score = isset( $r['bodyScore'] ) ? (int) $r['bodyScore'] : 0;
    $tier  = isset( $r['tier'] )     ? esc_html( (string) $r['tier'] ) : 'nudge';

    $tier_colors = [
        'nudge'    => [ 'bg' => '#1a4a2a', 'border' => '#2ecc71', 'label' => 'NUDGE' ],
        'checkin'  => [ 'bg' => '#1a3a4a', 'border' => '#3498db', 'label' => 'CHECK-IN' ],
        'protocol' => [ 'bg' => '#4a3a1a', 'border' => '#f39c12', 'label' => 'PROTOCOL' ],
        'alarm'    => [ 'bg' => '#4a1a1a', 'border' => '#e74c3c', 'label' => 'ATTENTION' ],
    ];
    $tc = $tier_colors[ $tier ] ?? $tier_colors['nudge'];

    /* 5-tier score gradient */
    if ( $score >= 85 )      { $score_color = '#2ecc71'; $score_label = 'Optimized'; }
    elseif ( $score >= 70 )  { $score_color = '#C9A84C'; $score_label = 'Good';      }
    elseif ( $score >= 55 )  { $score_color = '#f39c12'; $score_label = 'Fair';      }
    elseif ( $score >= 35 )  { $score_color = '#e67e22'; $score_label = 'Low';       }
    else                     { $score_color = '#e74c3c'; $score_label = 'Concern';   }

    ob_start();
    ?>
    <div class="ex298-result">

        <div class="ex298-score-row">
            <div class="ex298-score-ring">
                <svg width="96" height="96" viewBox="0 0 96 96">
                    <circle cx="48" cy="48" r="40" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="8"/>
                    <circle cx="48" cy="48" r="40" fill="none" stroke="<?php echo esc_attr( $score_color ); ?>" stroke-width="8"
                        stroke-dasharray="<?php echo round( 251.2 * $score / 100, 1 ); ?> 251.2"
                        stroke-linecap="round" transform="rotate(-90 48 48)"/>
                    <text x="48" y="47" text-anchor="middle" fill="#fff" font-size="22" font-family="Georgia,serif" font-weight="bold"><?php echo esc_html( $score ); ?></text>
                    <text x="48" y="59" text-anchor="middle" fill="<?php echo esc_attr( $score_color ); ?>" font-size="8.5" font-family="sans-serif" font-weight="600" letter-spacing="0.04em"><?php echo esc_html( strtoupper( $score_label ) ); ?></text>
                    <text x="48" y="70" text-anchor="middle" fill="rgba(255,255,255,.4)" font-size="7" font-family="Georgia,serif">GUT SCORE</text>
                </svg>
            </div>
            <div class="ex298-score-info">
                <div class="ex298-tier-badge" style="background:<?php echo esc_attr( $tc['bg'] ); ?>;border-color:<?php echo esc_attr( $tc['border'] ); ?>;">
                    <?php echo esc_html( $tc['label'] ); ?>
                </div>
                <?php if ( ! empty( $r['snapshotDate'] ) ) : ?>
                <div class="ex298-result-date"><?php echo esc_html( $decode( (string) $r['snapshotDate'] ) ); ?></div>
                <?php endif; ?>
                <div class="ex298-trajectory"><?php echo esc_html( $decode( (string) ( $r['trajectoryRead'] ?? '' ) ) ); ?></div>
                <button class="ex298-listen-btn" id="ex298-listen-btn" type="button" aria-label="Listen to snapshot">
                    <svg class="ex298-listen-icon" id="ex298-listen-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 5L6 9H2v6h4l5 4V5z" fill="currentColor"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span id="ex298-listen-label">Listen</span>
                </button>
            </div>
        </div>

        <?php
        $wb = isset( $r['wellbeingAnalysis'] ) && is_array( $r['wellbeingAnalysis'] ) ? $r['wellbeingAnalysis'] : [];
        if ( ! empty( $wb ) ) :
        ?>
        <div class="ex298-section">
            <div class="ex298-section-head">Today's Wellbeing Pattern</div>
            <div class="ex298-details-grid">
                <?php foreach ( [ 'energySummary' => 'Energy', 'moodCorrelation' => 'Mood', 'symptomPattern' => 'Symptoms' ] as $key => $lbl ) : ?>
                <?php if ( ! empty( $wb[ $key ] ) ) : ?>
                <div class="ex298-detail-item">
                    <span class="ex298-detail-label"><?php echo esc_html( $lbl ); ?></span>
                    <span class="ex298-detail-val"><?php echo esc_html( $decode( (string) $wb[ $key ] ) ); ?></span>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $ua = isset( $r['urineAnalysis'] ) && is_array( $r['urineAnalysis'] ) ? $r['urineAnalysis'] : [];
        if ( ! empty( $ua ) ) :
        ?>
        <div class="ex298-section">
            <div class="ex298-section-head">Urine Analysis</div>
            <div class="ex298-details-grid">
                <?php foreach ( [ 'colorObservation' => 'Color', 'clarityObservation' => 'Clarity', 'odorAssessment' => 'Odor', 'stripReadingSummary' => 'Urine Strip', 'hydrationStatus' => 'Hydration' ] as $key => $lbl ) : ?>
                <?php if ( ! empty( $ua[ $key ] ) ) : ?>
                <div class="ex298-detail-item">
                    <span class="ex298-detail-label"><?php echo esc_html( $lbl ); ?></span>
                    <span class="ex298-detail-val"><?php echo esc_html( $decode( (string) $ua[ $key ] ) ); ?></span>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $sa = isset( $r['salivaAnalysis'] ) && is_array( $r['salivaAnalysis'] ) ? $r['salivaAnalysis'] : [];
        if ( ! empty( $sa ) ) :
        ?>
        <div class="ex298-section">
            <div class="ex298-section-head">Saliva pH Analysis</div>
            <div class="ex298-details-grid">
                <?php foreach ( [ 'phLevel' => 'pH Level', 'stripObservation' => 'Observation', 'interpretation' => 'Interpretation' ] as $key => $lbl ) : ?>
                <?php if ( ! empty( $sa[ $key ] ) ) : ?>
                <div class="ex298-detail-item">
                    <span class="ex298-detail-label"><?php echo esc_html( $lbl ); ?></span>
                    <span class="ex298-detail-val"><?php echo esc_html( $decode( (string) $sa[ $key ] ) ); ?></span>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $ba = isset( $r['bowelAnalysis'] ) && is_array( $r['bowelAnalysis'] ) ? $r['bowelAnalysis'] : [];
        if ( ! empty( $ba ) ) :
        ?>
        <div class="ex298-section">
            <div class="ex298-section-head">Bowel Analysis</div>
            <div class="ex298-details-grid">
                <?php foreach ( [ 'formObservation' => 'Form', 'colorObservation' => 'Color', 'patternInsight' => 'Pattern Insight' ] as $key => $lbl ) : ?>
                <?php if ( ! empty( $ba[ $key ] ) ) : ?>
                <div class="ex298-detail-item">
                    <span class="ex298-detail-label"><?php echo esc_html( $lbl ); ?></span>
                    <span class="ex298-detail-val"><?php echo esc_html( $decode( (string) $ba[ $key ] ) ); ?></span>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $r['hydrationInsight'] ) ) : ?>
        <div class="ex298-insight-block">
            <div class="ex298-insight-label">Hydration Insight</div>
            <div><?php echo esc_html( $decode( (string) $r['hydrationInsight'] ) ); ?></div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $r['environmentalContext'] ) ) : ?>
        <div class="ex298-insight-block">
            <div class="ex298-insight-label">Environmental Context</div>
            <div><?php echo esc_html( $decode( (string) $r['environmentalContext'] ) ); ?></div>
        </div>
        <?php endif; ?>

        <?php
        $actions = isset( $r['quickActions'] ) && is_array( $r['quickActions'] ) ? $r['quickActions'] : [];
        if ( ! empty( $actions ) ) :
        ?>
        <div class="ex298-section">
            <div class="ex298-section-head">Today's Quick Actions</div>
            <ul class="ex298-actions-list">
                <?php foreach ( $actions as $action ) : ?>
                <li class="ex298-action-item">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="#C9A84C" stroke-width="1"/><path d="M5 8l2 2 4-4" stroke="#C9A84C" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?php echo esc_html( $decode( (string) $action ) ); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $r['disclaimer'] ) ) : ?>
        <div class="ex298-disclaimer"><?php echo esc_html( (string) $r['disclaimer'] ); ?></div>
        <?php endif; ?>

        <div class="ex298-result-footer">
            <?php if ( $tier === 'alarm' || $tier === 'protocol' || $score < 55 ) : ?>
            <div class="ex298-redflag-cta">
                <div class="ex298-redflag-icon">⚠</div>
                <div class="ex298-redflag-text">
                    <strong>Signals found that need attention.</strong>
                    Your Ministry of Healing session is ready to walk you through what they mean.
                </div>
                <a href="<?php echo esc_url( home_url( '/ask-the-healer/' ) ); ?>" class="ex298-redflag-btn">
                    Go to Ministry of Healing →
                </a>
            </div>
            <?php else : ?>
            <a href="<?php echo esc_url( home_url( '/ask-the-healer/' ) ); ?>" class="ex298-moh-link">Ask the Ministry of Healing about this →</a>
            <?php endif; ?>
        </div>

        <?php /* ── Update health baseline ── */ ?>
        <div class="ex298-rebaseline-wrap" id="ex298-rebaseline-wrap">
            <button type="button" class="ex298-rebaseline-toggle" id="ex298-rebaseline-toggle"
                aria-expanded="false" aria-controls="ex298-rebaseline-panel">
                Update My Health Baseline
            </button>
            <div class="ex298-rebaseline-panel" id="ex298-rebaseline-panel" hidden>
                <p class="ex298-rebaseline-desc">
                    Use this if your health situation has changed — new medications, a diagnosis, after completing a protocol, or simply a new year of life. Your existing conversation history in the Ministry of Healing will be kept, but your Clinical Pattern Report will be refreshed so the AI works from an accurate picture.
                </p>
                <a href="<?php echo esc_url( home_url( '/member-intake-form/?rebaseline=1' ) ); ?>"
                   class="ex298-rebaseline-confirm">
                    Confirm — Re-submit My Intake
                </a>
            </div>
        </div>

    </div>
    <?php
    return ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   JAVASCRIPT
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_298_script( string $ajax_url, string $nonce, bool $is_admin = false ): void { ?>
<script>
(function() {
    'use strict';

    var EX298_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
    var EX298_MODE  = '';

    var EX = {
        step: 1,
        ratings: { energy: 0, mood: 0, symptoms: 0 },
        q: {
            bowelToday: '', waterOz: '', bowelMinutes: '',
            bowelUncomfortable: '', stoolOdor: '',
            urineOdor: '', urineUncomfortable: '', urineColor: '',
            bodyTemp: '', zipCode: ''
        },
        photos: { bowel: '', urine: '', urineStrip: '', salivaStrip: '' }
    };

    var STEP_LABELS = [
        '',
        'How Are You Feeling?',
        'Hydration & Digestion',
        'Morning Urine',
        'Vitals & Location',
        'Photo Evidence'
    ];

    window.exSelectMode = function(mode) {
        EX298_MODE = mode;
        var modeSelect = document.getElementById('ex298-mode-select');
        var wizard     = document.getElementById('ex298-wizard');
        var stripZone  = document.getElementById('ex298-strip-zone');
        var step5Desc  = document.getElementById('ex298-step5-desc');
        if (modeSelect) modeSelect.style.display = 'none';
        if (wizard)     wizard.style.display     = 'block';
        if (stripZone)  stripZone.style.display  = (mode === 'full') ? 'block' : 'none';
        if (step5Desc) {
            step5Desc.textContent = (mode === 'full')
                ? 'All four photos required. pH strips need 30–60 sec to develop — take your time.'
                : 'Two clear photos required — bowel movement and urine sample. Good lighting, close up.';
        }
        exUpdateProgress();
    };

    window.exStep = function(n) {
        if (n > EX.step) {
            var err = exValidate(EX.step);
            if (err) { exShowErr(err); return; }
        }
        exHideErr();
        document.getElementById('ex298-step-' + EX.step).style.display = 'none';
        EX.step = n;
        document.getElementById('ex298-step-' + n).style.display = 'block';
        exUpdateProgress();
        var wrap = document.getElementById('ex298-form-wrap');
        if (wrap) { wrap.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    };

    function exValidate(s) {
        if (EX298_ADMIN) return null;
        if (s === 1) {
            if (!EX.ratings.energy)   return 'Please rate your Energy Level (1–10).';
            if (!EX.ratings.mood)     return 'Please rate your Mood (1–10).';
            if (!EX.ratings.symptoms) return 'Please rate your Symptom Intensity (1–10).';
        }
        if (s === 2) {
            if (!EX.q.waterOz.trim()) return 'Please enter your water / fluid intake.';
            if (!EX.q.bowelToday)     return 'Please answer the bowel movement question.';
            if (EX.q.bowelToday === 'yes') {
                if (!EX.q.bowelMinutes.trim()) return 'Please enter how many minutes it took.';
                if (!EX.q.bowelUncomfortable)  return 'Please answer whether it was uncomfortable.';
                if (!EX.q.stoolOdor)           return 'Please answer the stool odor question.';
            }
        }
        if (s === 3) {
            if (!EX.q.urineOdor)          return 'Please answer the urine odor question.';
            if (!EX.q.urineUncomfortable) return 'Please answer the urination comfort question.';
            if (!EX.q.urineColor)         return 'Please select your urine color.';
        }
        if (s === 4) {
            if (!EX.q.zipCode.trim()) return 'Please enter your postal code.';
        }
        if (s === 5) {
            if (!EX.photos.bowel)  return 'Please upload your Bowel Movement photo.';
            if (!EX.photos.urine)  return 'Please upload your Urine Sample photo.';
            if (EX298_MODE === 'full') {
                if (!EX.photos.urineStrip)  return 'Please upload your Urine pH Strip photo.';
                if (!EX.photos.salivaStrip) return 'Please upload your Saliva pH Strip photo.';
            }
        }
        return null;
    }

    function exUpdateProgress() {
        for (var i = 1; i <= 5; i++) {
            var dot  = document.querySelector('.ex298-dot[data-step="' + i + '"]');
            var conn = document.querySelector('.ex298-connector[data-conn="' + i + '"]');
            if (dot) dot.className = 'ex298-dot' + (i < EX.step ? ' done' : i === EX.step ? ' active' : '');
            if (conn) conn.className = 'ex298-connector' + (i < EX.step ? ' done' : '');
        }
        var lbl = document.getElementById('ex298-step-lbl');
        if (lbl) lbl.textContent = 'Step ' + EX.step + ' of 5 — ' + STEP_LABELS[EX.step];
    }

    function exShowErr(msg) {
        var el = document.getElementById('ex298-step-error');
        if (el) { el.textContent = msg; el.style.display = 'block'; el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
    }
    function exHideErr() {
        var el = document.getElementById('ex298-step-error');
        if (el) el.style.display = 'none';
    }

    /* ── Rating buttons ── */
    document.querySelectorAll('.ex298-rating-btns').forEach(function(grp) {
        var key = grp.dataset.rating;
        grp.querySelectorAll('.ex298-rb').forEach(function(btn) {
            btn.addEventListener('click', function() {
                grp.querySelectorAll('.ex298-rb').forEach(function(b) { b.classList.remove('sel'); });
                btn.classList.add('sel');
                EX.ratings[key] = parseInt(btn.dataset.val);
            });
        });
    });

    /* ── Yes/No toggles ── */
    document.querySelectorAll('.ex298-yesno').forEach(function(grp) {
        var key = grp.dataset.key;
        grp.querySelectorAll('.ex298-yn-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                grp.querySelectorAll('.ex298-yn-btn').forEach(function(b) { b.classList.remove('sel'); });
                btn.classList.add('sel');
                EX.q[key] = btn.dataset.val;
                if (key === 'bowelToday') {
                    var det = document.getElementById('ex298-bowel-details');
                    if (det) det.className = 'ex298-conditional' + (btn.dataset.val === 'yes' ? ' visible' : '');
                }
            });
        });
    });

    /* ── Urine color picker ── */
    document.querySelectorAll('.ex298-color-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.ex298-color-btn').forEach(function(b) { b.classList.remove('sel'); });
            btn.classList.add('sel');
            EX.q.urineColor = btn.dataset.val;
        });
    });

    /* ── Text / number inputs ── */
    [
        ['q-water-oz',      'waterOz'],
        ['q-bowel-minutes', 'bowelMinutes'],
        ['q-body-temp',     'bodyTemp'],
        ['q-zip',           'zipCode'],
    ].forEach(function(pair) {
        var el = document.getElementById(pair[0]);
        if (el) el.addEventListener('input', function() { EX.q[pair[1]] = this.value; });
    });

    /* ── Photo upload with client-side compression ── */
    function compressPhoto(file, cb) {
        var MAX = 1400, Q = 0.80;
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = new Image();
            img.onload = function() {
                var ratio  = Math.min(MAX / img.width, MAX / img.height, 1);
                var canvas = document.createElement('canvas');
                canvas.width  = Math.round(img.width  * ratio);
                canvas.height = Math.round(img.height * ratio);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                cb(canvas.toDataURL('image/jpeg', Q));
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function setupPhoto(inputId, previewId, zoneId, key) {
        var inp  = document.getElementById(inputId);
        var prev = document.getElementById(previewId);
        var zone = document.getElementById(zoneId);
        if (!inp) return;
        inp.addEventListener('change', function() {
            var file = inp.files[0];
            if (!file) return;
            compressPhoto(file, function(dataUrl) {
                EX.photos[key] = dataUrl.replace(/^data:image\/jpeg;base64,/, '');
                if (prev) { prev.src = dataUrl; prev.style.display = 'block'; }
                if (zone) zone.classList.add('has-photo');
            });
        });
    }

    setupPhoto('photo-bowel',       'preview-bowel',       'zone-bowel',       'bowel');
    setupPhoto('photo-urine',       'preview-urine',       'zone-urine',       'urine');
    setupPhoto('photo-urine-strip', 'preview-urine-strip', 'zone-urine-strip', 'urineStrip');
    setupPhoto('photo-saliva-strip','preview-saliva-strip','zone-saliva-strip','salivaStrip');

    /* ── Submit ── */
    var submitBtn = document.getElementById('ex298-submit');
    if (submitBtn) {
        submitBtn.addEventListener('click', function() {
            var err = exValidate(5);
            if (err) { exShowErr(err); return; }
            exHideErr();

            var formWrap  = document.getElementById('ex298-form-wrap');
            var loading   = document.getElementById('ex298-loading');
            var resultEl  = document.getElementById('ex298-result-area');
            var errorEl   = document.getElementById('ex298-error');

            if (formWrap) formWrap.style.display  = 'none';
            if (loading)  loading.style.display   = 'block';
            if (resultEl) resultEl.style.display  = 'none';
            if (errorEl)  errorEl.style.display   = 'none';

            var fd = new FormData();
            fd.append('action',           'excreet_body_snapshot');
            fd.append('nonce',            '<?php echo esc_js( $nonce ); ?>');
            fd.append('energy_level',     String(EX.ratings.energy));
            fd.append('mood',             String(EX.ratings.mood));
            fd.append('symptom_intensity',String(EX.ratings.symptoms));
            fd.append('water_oz',         EX.q.waterOz);
            fd.append('bowel_today',      EX.q.bowelToday);
            fd.append('bowel_minutes',    EX.q.bowelMinutes);
            fd.append('bowel_uncomfortable', EX.q.bowelUncomfortable);
            fd.append('stool_odor',       EX.q.stoolOdor);
            fd.append('urine_odor',       EX.q.urineOdor);
            fd.append('urine_uncomfortable', EX.q.urineUncomfortable);
            fd.append('urine_color',      EX.q.urineColor);
            fd.append('body_temp',        EX.q.bodyTemp);
            fd.append('zip_code',         EX.q.zipCode);
            fd.append('snapshot_mode',    EX298_MODE);
            fd.append('photo_bowel',      EX.photos.bowel);
            fd.append('photo_urine',      EX.photos.urine);
            fd.append('photo_urineStrip', EX.photos.urineStrip);
            fd.append('photo_salivaStrip',EX.photos.salivaStrip);

            fetch('<?php echo esc_js( $ajax_url ); ?>', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (loading) loading.style.display = 'none';
                    if (!data.success) {
                        var msg = (data.data && data.data.message) ? data.data.message : 'Analysis could not be completed. Please try again.';
                        if (errorEl)  { errorEl.textContent = msg; errorEl.style.display = 'block'; }
                        if (formWrap) formWrap.style.display = 'block';
                        return;
                    }
                    if (resultEl) {
                        resultEl.innerHTML = exRenderResult(data.data.result);
                        resultEl.style.display = 'block';
                        resultEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    exLoadHistory();
                })
                .catch(function() {
                    if (loading)  loading.style.display = 'none';
                    if (errorEl)  { errorEl.textContent = 'A network error occurred. Check your connection and try again.'; errorEl.style.display = 'block'; }
                    if (formWrap) formWrap.style.display = 'block';
                });
        });
    }

    /* ── "Submit a new one" button ── */
    var redoBtn = document.getElementById('ex298-redo-btn');
    if (redoBtn) {
        redoBtn.addEventListener('click', function() {
            var done = document.getElementById('ex298-already-done');
            var form = document.getElementById('ex298-form-wrap');
            if (done) done.style.display = 'none';
            if (form) form.style.display = 'block';
        });
    }

    /* ── Admin: auto-fill button ── */
    var autoFill = document.getElementById('ex298-autofill');
    if (autoFill && EX298_ADMIN) {
        autoFill.addEventListener('click', function() {
            // Set mode to quick and show wizard
            EX298_MODE = 'quick';
            var modeSelect = document.getElementById('ex298-mode-select');
            var wizard     = document.getElementById('ex298-wizard');
            if (modeSelect) modeSelect.style.display = 'none';
            if (wizard)     wizard.style.display     = 'block';
            // Pre-fill all questionnaire data with test values
            EX.ratings.energy   = 7;
            EX.ratings.mood     = 7;
            EX.ratings.symptoms = 3;
            EX.q.waterOz            = '64';
            EX.q.bowelToday         = 'yes';
            EX.q.bowelMinutes       = '5';
            EX.q.bowelUncomfortable = 'no';
            EX.q.stoolOdor          = 'no';
            EX.q.urineOdor          = 'no';
            EX.q.urineUncomfortable = 'no';
            EX.q.urineColor         = 'normal yellow';
            EX.q.bodyTemp           = '98.6';
            EX.q.zipCode            = '90210';
            // Visually highlight the auto-filled buttons
            document.querySelectorAll('.ex298-rating-btns[data-rating="energy"] .ex298-rb[data-val="7"]').forEach(function(b) { b.classList.add('sel'); });
            document.querySelectorAll('.ex298-rating-btns[data-rating="mood"] .ex298-rb[data-val="7"]').forEach(function(b) { b.classList.add('sel'); });
            document.querySelectorAll('.ex298-rating-btns[data-rating="symptoms"] .ex298-rb[data-val="3"]').forEach(function(b) { b.classList.add('sel'); });
            // Jump straight to step 5
            document.getElementById('ex298-step-' + EX.step).style.display = 'none';
            EX.step = 5;
            document.getElementById('ex298-step-5').style.display = 'block';
            exUpdateProgress();
        });
    }

    /* ── Result renderer ── */
    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    /* Decode \uXXXX sequences that Claude may emit as literal text */
    function decodeUnicode(s) {
        return String(s).replace(/\\u([0-9a-fA-F]{4})/gi, function(m, code) {
            return String.fromCharCode(parseInt(code, 16));
        });
    }
    function du(s) { return decodeUnicode(s || ''); }

    function scoreColor(n) {
        return n >= 85 ? '#2ecc71' : n >= 70 ? '#C9A84C' : n >= 55 ? '#f39c12' : n >= 35 ? '#e67e22' : '#e74c3c';
    }
    function scoreLabel(n) {
        return n >= 85 ? 'OPTIMIZED' : n >= 70 ? 'GOOD' : n >= 55 ? 'FAIR' : n >= 35 ? 'LOW' : 'CONCERN';
    }
    function tierCfg(t) {
        var m = {
            nudge:    { bg:'#1a4a2a', border:'#2ecc71', label:'NUDGE' },
            checkin:  { bg:'#1a3a4a', border:'#3498db', label:'CHECK-IN' },
            protocol: { bg:'#4a3a1a', border:'#f39c12', label:'PROTOCOL' },
            alarm:    { bg:'#4a1a1a', border:'#e74c3c', label:'ATTENTION' }
        };
        return m[t] || m.nudge;
    }

    /* ── Audio morning briefing ── */
    var exSpeech = { utt: null, playing: false, paused: false, data: null };

    function exBuildScript(r) {
        var wb = r.wellbeingAnalysis || {}, ua = r.urineAnalysis || {};
        var score = parseInt(r.bodyScore) || 0;
        var sl = score >= 85 ? 'Optimized' : score >= 70 ? 'Good' : score >= 55 ? 'Fair' : score >= 35 ? 'Low' : 'Concern';
        var parts = [];
        parts.push('Good morning. Here is your Body Check for ' + du(r.snapshotDate || 'today') + '.');
        parts.push('Your Body Score is ' + score + ' — ' + sl + '.');
        if (r.trajectoryRead) parts.push(du(r.trajectoryRead));
        if (wb.energySummary)   parts.push('Energy: ' + du(wb.energySummary));
        if (wb.moodCorrelation) parts.push('Mood: ' + du(wb.moodCorrelation));
        if (wb.symptomPattern)  parts.push('Symptoms: ' + du(wb.symptomPattern));
        if (ua.hydrationStatus) parts.push('Hydration: ' + du(ua.hydrationStatus));
        if (r.hydrationInsight) parts.push(du(r.hydrationInsight));
        var acts = Array.isArray(r.quickActions) ? r.quickActions : [];
        if (acts.length) {
            parts.push('Today\'s quick actions: ' + acts.map(function(a){ return du(a); }).join('. ') + '.');
        }
        parts.push('That\'s your Excreet Body Check. Have a great day.');
        return parts.join(' ');
    }

    function exUpdateListenBtn(state) {
        var btn   = document.getElementById('ex298-listen-btn');
        var label = document.getElementById('ex298-listen-label');
        if (!btn || !label) return;
        if (state === 'playing') {
            btn.classList.add('ex298-listen-active');
            label.textContent = 'Pause';
            btn.querySelector('.ex298-listen-icon').innerHTML = '<rect x="6" y="4" width="4" height="16" fill="currentColor"/><rect x="14" y="4" width="4" height="16" fill="currentColor"/>';
        } else if (state === 'paused') {
            btn.classList.remove('ex298-listen-active');
            label.textContent = 'Resume';
            btn.querySelector('.ex298-listen-icon').innerHTML = '<polygon points="5,3 19,12 5,21" fill="currentColor"/>';
        } else {
            btn.classList.remove('ex298-listen-active');
            label.textContent = 'Listen';
            btn.querySelector('.ex298-listen-icon').innerHTML = '<path d="M11 5L6 9H2v6h4l5 4V5z" fill="currentColor"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';
        }
    }

    function exListenClick() {
        if (!window.speechSynthesis) { alert('Audio not supported in this browser.'); return; }
        if (exSpeech.playing && !exSpeech.paused) {
            window.speechSynthesis.pause();
            exSpeech.paused = true; exSpeech.playing = false;
            exUpdateListenBtn('paused');
            return;
        }
        if (exSpeech.paused) {
            window.speechSynthesis.resume();
            exSpeech.paused = false; exSpeech.playing = true;
            exUpdateListenBtn('playing');
            return;
        }
        window.speechSynthesis.cancel();
        if (!exSpeech.data) return;
        var script = exBuildScript(exSpeech.data);
        var utt = new SpeechSynthesisUtterance(script);
        utt.rate = 0.93; utt.pitch = 1.0; utt.volume = 1.0;
        utt.onstart = function() { exSpeech.playing = true; exSpeech.paused = false; exUpdateListenBtn('playing'); };
        utt.onend   = function() { exSpeech.playing = false; exSpeech.paused = false; exUpdateListenBtn('idle'); };
        utt.onerror = function() { exSpeech.playing = false; exSpeech.paused = false; exUpdateListenBtn('idle'); };
        exSpeech.utt = utt;
        window.speechSynthesis.speak(utt);
    }

    function exRenderResult(r) {
        if (!r) return '';
        exSpeech.data = r;
        var score = parseInt(r.bodyScore) || 0;
        var sc = scoreColor(score), sl = scoreLabel(score), tc = tierCfg(r.tier || 'nudge');
        var dash = Math.round(251.2 * score / 100 * 10) / 10;
        var h = '<div class="ex298-result">';

        h += '<div class="ex298-score-row">'
           + '<div class="ex298-score-ring"><svg width="96" height="96" viewBox="0 0 96 96">'
           + '<circle cx="48" cy="48" r="40" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="8"/>'
           + '<circle cx="48" cy="48" r="40" fill="none" stroke="' + sc + '" stroke-width="8"'
           + ' stroke-dasharray="' + dash + ' 251.2" stroke-linecap="round" transform="rotate(-90 48 48)"/>'
           + '<text x="48" y="47" text-anchor="middle" fill="#fff" font-size="22" font-family="Georgia,serif" font-weight="bold">' + score + '</text>'
           + '<text x="48" y="59" text-anchor="middle" fill="' + sc + '" font-size="8.5" font-family="sans-serif" font-weight="600" letter-spacing="0.04em">' + sl + '</text>'
           + '<text x="48" y="70" text-anchor="middle" fill="rgba(255,255,255,.4)" font-size="7" font-family="Georgia,serif">BODY SCORE</text>'
           + '</svg></div>'
           + '<div class="ex298-score-info">'
           + '<div class="ex298-tier-badge" style="background:' + tc.bg + ';border-color:' + tc.border + ';">' + esc(tc.label) + '</div>';
        if (r.snapshotDate)   h += '<div class="ex298-result-date">' + esc(du(r.snapshotDate)) + '</div>';
        if (r.trajectoryRead) h += '<div class="ex298-trajectory">' + esc(du(r.trajectoryRead)) + '</div>';
        h += '<button class="ex298-listen-btn" id="ex298-listen-btn" type="button" onclick="exListenClick()">'
           + '<svg class="ex298-listen-icon" id="ex298-listen-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 5L6 9H2v6h4l5 4V5z" fill="currentColor"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
           + '<span id="ex298-listen-label">Listen</span>'
           + '</button>';
        h += '</div></div>';

        function section(title, rows) {
            if (!rows.length) return '';
            var s = '<div class="ex298-section"><div class="ex298-section-head">' + esc(title) + '</div><div class="ex298-details-grid">';
            rows.forEach(function(row) {
                s += '<div class="ex298-detail-item"><span class="ex298-detail-label">' + esc(row[0]) + '</span><span class="ex298-detail-val">' + esc(du(row[1])) + '</span></div>';
            });
            s += '</div></div>';
            return s;
        }

        var wb = r.wellbeingAnalysis || {};
        h += section('Today\'s Wellbeing Pattern', [
            wb.energySummary   ? ['Energy',   wb.energySummary]   : null,
            wb.moodCorrelation ? ['Mood',     wb.moodCorrelation] : null,
            wb.symptomPattern  ? ['Symptoms', wb.symptomPattern]  : null,
        ].filter(Boolean));

        var ua = r.urineAnalysis || {};
        h += section('Urine Analysis', [
            ua.colorObservation    ? ['Color',       ua.colorObservation]    : null,
            ua.clarityObservation  ? ['Clarity',     ua.clarityObservation]  : null,
            ua.odorAssessment      ? ['Odor',        ua.odorAssessment]      : null,
            ua.stripReadingSummary ? ['Urine Strip', ua.stripReadingSummary] : null,
            ua.hydrationStatus     ? ['Hydration',   ua.hydrationStatus]     : null,
        ].filter(Boolean));

        var sa = r.salivaAnalysis || {};
        h += section('Saliva pH Analysis', [
            sa.phLevel          ? ['pH Level',      sa.phLevel]          : null,
            sa.stripObservation ? ['Observation',   sa.stripObservation] : null,
            sa.interpretation   ? ['Interpretation',sa.interpretation]   : null,
        ].filter(Boolean));

        var ba = r.bowelAnalysis || {};
        h += section('Bowel Analysis', [
            ba.formObservation  ? ['Form',           ba.formObservation]  : null,
            ba.colorObservation ? ['Color',          ba.colorObservation] : null,
            ba.patternInsight   ? ['Pattern Insight',ba.patternInsight]   : null,
        ].filter(Boolean));

        if (r.hydrationInsight)     h += '<div class="ex298-insight-block"><div class="ex298-insight-label">Hydration Insight</div><div>' + esc(du(r.hydrationInsight)) + '</div></div>';
        if (r.environmentalContext) h += '<div class="ex298-insight-block"><div class="ex298-insight-label">Environmental Context</div><div>' + esc(du(r.environmentalContext)) + '</div></div>';

        var acts = Array.isArray(r.quickActions) ? r.quickActions : [];
        if (acts.length) {
            h += '<div class="ex298-section"><div class="ex298-section-head">Today\'s Quick Actions</div><ul class="ex298-actions-list">';
            acts.forEach(function(a) { h += '<li class="ex298-action-item">' + esc(du(a)) + '</li>'; });
            h += '</ul></div>';
        }

        if (r.disclaimer) h += '<div class="ex298-disclaimer">' + esc(du(r.disclaimer)) + '</div>';
        var isRedFlag = (r.tier === 'alarm' || r.tier === 'protocol' || score < 55);
        if (isRedFlag) {
            h += '<div class="ex298-result-footer">'
               + '<div class="ex298-redflag-cta">'
               + '<div class="ex298-redflag-icon">⚠</div>'
               + '<div class="ex298-redflag-text"><strong>Signals found that need attention.</strong> Your Ministry of Healing session is ready to walk you through what they mean.</div>'
               + '<a href="/ask-the-healer/" class="ex298-redflag-btn">Go to Ministry of Healing →</a>'
               + '</div></div>';
        } else {
            h += '<div class="ex298-result-footer"><a href="/ask-the-healer/" class="ex298-moh-link">Ask the Ministry of Healing about this →</a></div>';
        }
        h += '</div>';
        return h;
    }

    /* ── Score trend chart ── */
    function exRenderTrendChart(history) {
        /* history: [{snapshotDate, bodyScore, tier}] newest-first — flip to oldest-first */
        var data = history.slice(0, 14).reverse();
        if (data.length < 2) {
            return '<p class="ex298-trend-empty">Keep tracking — your score trend appears after your second snapshot.</p>';
        }
        var W = 400, H = 110, padL = 26, padR = 6, padT = 14, padB = 24;
        var cW = W - padL - padR, cH = H - padT - padB;
        var n = data.length, slot = cW / n, barW = Math.max(8, Math.floor(slot * 0.6));
        var tiers = [35, 55, 70, 85];
        var svg = '<svg width="100%" viewBox="0 0 ' + W + ' ' + H + '" xmlns="http://www.w3.org/2000/svg">';

        /* tier grid lines */
        tiers.forEach(function(s) {
            var y = (padT + cH - (s / 100 * cH)).toFixed(1);
            svg += '<line x1="' + padL + '" y1="' + y + '" x2="' + (W - padR) + '" y2="' + y + '" stroke="rgba(255,255,255,.07)" stroke-dasharray="3,3" stroke-width="1"/>';
            svg += '<text x="' + (padL - 3) + '" y="' + (parseFloat(y) + 3.5).toFixed(1) + '" text-anchor="end" fill="rgba(255,255,255,.28)" font-size="7" font-family="sans-serif">' + s + '</text>';
        });

        /* bars */
        data.forEach(function(d, i) {
            var score = parseInt(d.bodyScore) || 0;
            var color = scoreColor(score);
            var barH  = Math.max(3, score / 100 * cH);
            var x     = (padL + i * slot + (slot - barW) / 2).toFixed(1);
            var y     = (padT + cH - barH).toFixed(1);
            var isToday = (i === data.length - 1);

            svg += '<rect x="' + x + '" y="' + y + '" width="' + barW + '" height="' + barH.toFixed(1) + '"'
                +  ' rx="2" fill="' + color + '" opacity="' + (isToday ? '1' : '0.68') + '">'
                +  '<title>' + esc(d.snapshotDate || '') + ': ' + score + '</title>'
                +  '</rect>';

            /* score label — always on today, only if ≤7 bars otherwise */
            if (isToday || n <= 7) {
                var lx = (parseFloat(x) + barW / 2).toFixed(1);
                var ly = (parseFloat(y) - 3).toFixed(1);
                svg += '<text x="' + lx + '" y="' + ly + '" text-anchor="middle" fill="' + color + '" font-size="8" font-family="sans-serif" font-weight="600">' + score + '</text>';
            }

            /* today marker dot */
            if (isToday) {
                var cx2 = (parseFloat(x) + barW / 2).toFixed(1);
                svg += '<circle cx="' + cx2 + '" cy="' + (parseFloat(y) - 1.5).toFixed(1) + '" r="2.5" fill="' + color + '"/>';
            }

            /* date label */
            var dl = d.snapshotDate ? d.snapshotDate.slice(5).replace('-', '/') : '';
            svg += '<text x="' + (parseFloat(x) + barW / 2).toFixed(1) + '" y="' + (H - 4) + '"'
                +  ' text-anchor="middle" fill="rgba(255,255,255,' + (isToday ? '.65' : '.3') + ')"'
                +  ' font-size="6.5" font-family="sans-serif">' + esc(dl) + '</text>';
        });

        svg += '</svg>';
        return svg;
    }

    /* ── Streak calculator ── */
    function exCalcStreak(history) {
        /* history is newest-first [{snapshotDate: "YYYY-MM-DD"}, …] */
        if (!history || !history.length) return 0;

        var dateSet = {};
        history.forEach(function(d) { if (d.snapshotDate) dateSet[d.snapshotDate] = true; });

        /* start from today; if today is missing start from yesterday */
        function pad(n) { return n < 10 ? '0' + n : '' + n; }
        function fmtDate(d) {
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }
        var cursor = new Date();
        var todayStr = fmtDate(cursor);
        if (!dateSet[todayStr]) {
            cursor.setDate(cursor.getDate() - 1);
        }

        var streak = 0;
        while (dateSet[fmtDate(cursor)]) {
            streak++;
            cursor.setDate(cursor.getDate() - 1);
        }
        return streak;
    }

    function exRenderStreakBadge(streak) {
        if (streak < 1) return '';
        var label, cls;
        if      (streak >= 30) { label = streak + '-Day Streak';  cls = 'ex298-streak-gold';   }
        else if (streak >= 14) { label = streak + '-Day Streak';  cls = 'ex298-streak-purple'; }
        else if (streak >= 7)  { label = streak + '-Day Streak';  cls = 'ex298-streak-green';  }
        else if (streak >= 2)  { label = streak + '-Day Streak';  cls = 'ex298-streak-base';   }
        else                   { label = 'Day 1 — keep going';    cls = 'ex298-streak-base';   }

        var fire = streak >= 7 ? '<span class="ex298-streak-fire">🔥</span>' : '';
        return '<div class="ex298-streak-badge ' + cls + '">' + fire
             + '<span class="ex298-streak-num">' + (streak >= 2 ? streak : '') + '</span>'
             + '<span class="ex298-streak-label">' + label + '</span>'
             + '</div>';
    }

    /* ── Score delta vs yesterday ── */
    function exCalcDelta(history) {
        /* history is newest-first; today = [0], yesterday = [1] */
        if (!history || history.length < 2) return null;
        var today = history[0], prev = history[1];
        if (!today.snapshotDate || !prev.snapshotDate) return null;
        /* only compare if dates are exactly 1 day apart */
        var ms = new Date(today.snapshotDate) - new Date(prev.snapshotDate);
        if (Math.round(ms / 86400000) !== 1) return null;
        return parseInt(today.bodyScore) - parseInt(prev.bodyScore);
    }

    function exRenderDeltaBadge(delta) {
        if (delta === null) return '';
        var abs = Math.abs(delta);
        if (abs <= 2) {
            return '<span class="ex298-delta ex298-delta-flat">→ same</span>';
        }
        if (delta > 0) {
            return '<span class="ex298-delta ex298-delta-up">▲ +' + delta + ' from yesterday</span>';
        }
        return '<span class="ex298-delta ex298-delta-down">▼ ' + delta + ' from yesterday</span>';
    }

    function exLoadHistory() {
        var area = document.getElementById('ex298-trend-area');
        if (!area) return;
        area.style.display = 'block';
        area.innerHTML = '<div class="ex298-trend-loading">Loading trend…</div>';

        var fd = new FormData();
        fd.append('action', 'excreet_body_snapshot_history');
        fd.append('nonce',  '<?php echo esc_js( $nonce ); ?>');

        fetch('<?php echo esc_js( $ajax_url ); ?>', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !Array.isArray(data.data.history)) {
                    area.innerHTML = ''; area.style.display = 'none'; return;
                }
                var history = data.data.history;
                var streak  = exCalcStreak(history);

                var html = exRenderStreakBadge(streak);

                if (history.length < 2) {
                    html += '<p class="ex298-trend-empty">Keep tracking — your score trend appears after your second snapshot.</p>';
                    area.innerHTML = html;
                    return;
                }
                var delta = exCalcDelta(history);
                html += '<div class="ex298-trend-head">'
                      + '<span>Score Trend</span>'
                      + '<span style="display:flex;align-items:center;gap:.55rem">'
                      + exRenderDeltaBadge(delta)
                      + '<span class="ex298-trend-count">' + history.length + ' day' + (history.length === 1 ? '' : 's') + '</span>'
                      + '</span>'
                      + '</div>'
                      + exRenderTrendChart(history);
                area.innerHTML = html;
            })
            .catch(function() { area.innerHTML = ''; area.style.display = 'none'; });
    }

    /* Expose listen handler globally for static PHP-rendered result card */
    window.exListenClick = exListenClick;

    /* Wire listen button on static PHP-rendered cards (already-done view) */
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('#ex298-listen-btn');
        if (btn) exListenClick();
    });

    /* ── Re-baseline toggle ── */
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('#ex298-rebaseline-toggle');
        if (!toggle) return;
        var panel  = document.getElementById('ex298-rebaseline-panel');
        if (!panel) return;
        var open = !panel.hidden;
        panel.hidden = open;
        toggle.setAttribute('aria-expanded', String(!open));
    });

    /* Load trend chart if the already-done result is showing on page load */
    (function() {
        var done = document.getElementById('ex298-already-done');
        if (done && done.style.display !== 'none' && getComputedStyle(done).display !== 'none') {
            exLoadHistory();
        }
    })();

    exUpdateProgress();
})();
</script>
<?php }

/* ════════════════════════════════════════════════════════════════════════════
   STYLES
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_298_styles(): void { ?>
<style>
/* ── Page ── */
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> {
    background:
        linear-gradient(160deg,rgba(13,1,32,.48) 0%,rgba(26,5,53,.18) 28%,rgba(26,5,53,.12) 58%,rgba(13,1,32,.46) 100%),
        url("https://excreet.com/wp-content/uploads/healer-bg-<?php echo str_pad((int)date('n'),2,'0',STR_PAD_LEFT); ?>.jpg")
        center/cover no-repeat fixed #0c0115 !important;
    background-blend-mode: normal, normal !important;
    min-height:100vh !important;
}
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> .site-header,
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> .site-footer,
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> .elementor-location-header,
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> .elementor-location-footer,
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> .search-form,
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> .search-field,
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> .search-submit,
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> form[role="search"],
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> .widget_search,
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> .wp-block-search,
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> [class*="elementor-search"],
body.page-id-<?php echo EX298_HCC_PAGE_ID; ?> .elementor-widget-search-form { display:none !important; }

/* ── Wrap ── */
.ex298-wrap { max-width:700px; margin:0 auto; padding:2rem 1.25rem 5rem; font-family:'Georgia',serif; color:#fff; }

/* ── Header ── */
.ex298-header { display:flex; align-items:center; gap:.85rem; margin-bottom:2rem; padding-bottom:1.25rem; border-bottom:1px solid rgba(201,168,76,.2); }
.ex298-logo-mark { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,#6B2FA0,#3D1060); border:2px solid rgba(201,168,76,.5); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ex298-title { font-size:1.25rem; color:#F5D97A; font-weight:700; margin:0; }
.ex298-date  { font-size:.82rem; color:rgba(255,255,255,.82); font-family:sans-serif; margin-top:.1rem; }
.ex298-back  { margin-left:auto; color:rgba(201,168,76,.75); text-decoration:none; font-family:sans-serif; font-size:.82rem; flex-shrink:0; }
.ex298-back:hover { color:#C9A84C; }

/* ── Already done ── */
.ex298-already-done { background:rgba(201,168,76,.07); border:1px solid rgba(201,168,76,.25); border-radius:12px; padding:1.25rem; margin-bottom:1.5rem; }
.ex298-done-msg { display:flex; align-items:center; gap:.65rem; font-family:sans-serif; font-size:.9rem; color:#C9A84C; margin-bottom:.85rem; }
.ex298-redo-btn { background:transparent; border:1px solid rgba(255,255,255,.2); color:rgba(255,255,255,.6); font-family:sans-serif; font-size:.78rem; padding:.4rem .9rem; border-radius:6px; cursor:pointer; }
.ex298-redo-btn:hover { border-color:rgba(201,168,76,.5); color:#C9A84C; }

/* ── Progress ── */
.ex298-progress { margin-bottom:2rem; }
.ex298-dots { display:flex; align-items:center; justify-content:center; margin-bottom:.65rem; }
.ex298-dot { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.08); border:2px solid rgba(255,255,255,.15); color:rgba(255,255,255,.35); font-family:sans-serif; font-size:.8rem; font-weight:700; display:flex; align-items:center; justify-content:center; transition:all .25s; flex-shrink:0; }
.ex298-dot.active { background:linear-gradient(135deg,#6B2FA0,#3D1060); border-color:#C9A84C; color:#C9A84C; box-shadow:0 0 12px rgba(201,168,76,.3); }
.ex298-dot.done { background:rgba(201,168,76,.15); border-color:rgba(201,168,76,.6); color:#C9A84C; }
.ex298-connector { flex:1; height:2px; background:rgba(255,255,255,.12); max-width:48px; transition:background .25s; }
.ex298-connector.done { background:rgba(201,168,76,.45); }
.ex298-step-lbl { text-align:center; font-family:sans-serif; font-size:.88rem; color:rgba(255,255,255,.85); letter-spacing:.06em; }

/* ── Step error ── */
.ex298-step-error { background:rgba(231,76,60,.12); border:1px solid rgba(231,76,60,.35); border-radius:8px; padding:.7rem 1rem; font-family:sans-serif; font-size:.85rem; color:#e74c3c; margin-bottom:1rem; }

/* ── Step ── */
.ex298-step { }
.ex298-step-title { font-size:1.15rem; color:#C9A84C; margin-bottom:.35rem; }
.ex298-step-desc { font-family:sans-serif; font-size:.97rem; color:rgba(255,255,255,.9); margin:0 0 1.75rem; line-height:1.6; }

/* ── Rating buttons ── */
.ex298-rating-group { margin-bottom:1.6rem; }
.ex298-rating-label { font-family:sans-serif; font-size:.97rem; color:#fff; margin-bottom:.2rem; font-weight:700; }
.ex298-rating-sub { font-family:sans-serif; font-size:.82rem; color:rgba(255,255,255,.82); margin-bottom:.6rem; }
.ex298-rating-btns { display:flex; gap:.35rem; flex-wrap:wrap; }
.ex298-rb { min-width:38px; height:40px; padding:0 .3rem; border-radius:8px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.14); color:rgba(255,255,255,.65); font-family:sans-serif; font-size:.9rem; cursor:pointer; transition:all .15s; display:flex; align-items:center; justify-content:center; flex:1; max-width:52px; }
.ex298-rb:hover { border-color:rgba(201,168,76,.5); color:#C9A84C; }
.ex298-rb.sel { background:rgba(201,168,76,.2); border-color:#C9A84C; color:#C9A84C; font-weight:700; }

/* ── Yes/No ── */
.ex298-yesno { display:flex; gap:.75rem; margin-top:.4rem; }
.ex298-yn-btn { flex:1; padding:.7rem; border-radius:8px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.14); color:rgba(255,255,255,.7); font-family:sans-serif; font-size:.92rem; cursor:pointer; transition:all .15s; }
.ex298-yn-btn:hover { border-color:rgba(201,168,76,.45); }
.ex298-yn-btn.sel { background:rgba(201,168,76,.18); border-color:#C9A84C; color:#C9A84C; font-weight:700; }

/* ── Q block ── */
.ex298-q-block { margin-bottom:1.5rem; }
.ex298-q-label { font-family:sans-serif; font-size:.95rem; color:rgba(255,255,255,.95); margin-bottom:.4rem; display:block; }
.ex298-req { color:#C9A84C; }
.ex298-q-hint { font-family:sans-serif; font-size:.82rem; color:rgba(255,255,255,.78); margin-top:.35rem; line-height:1.5; }
.ex298-num-input { width:100%; background:rgba(255,255,255,.07); border:1px solid rgba(201,168,76,.3); border-radius:8px; color:#fff; font-family:sans-serif; font-size:.92rem; padding:.6rem .9rem; box-sizing:border-box; -webkit-appearance:none; }
.ex298-num-input::placeholder { color:rgba(255,255,255,.28); }
.ex298-num-input:focus { outline:none; border-color:#C9A84C; background:rgba(255,255,255,.1); }

/* ── Conditional ── */
.ex298-conditional { display:none; }
.ex298-conditional.visible { display:block; }

/* ── Color picker ── */
.ex298-color-grid { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.5rem; }
.ex298-color-btn { display:flex; flex-direction:column; align-items:center; gap:.35rem; background:rgba(255,255,255,.06); border:2px solid rgba(255,255,255,.1); border-radius:10px; padding:.5rem .45rem; cursor:pointer; transition:all .15s; min-width:52px; }
.ex298-color-btn:hover { border-color:rgba(201,168,76,.4); }
.ex298-color-btn.sel { border-color:#C9A84C; background:rgba(201,168,76,.12); }
.ex298-color-swatch { width:30px; height:30px; border-radius:50%; border:2px solid rgba(0,0,0,.25); display:block; }
.ex298-color-name { font-family:sans-serif; font-size:.78rem; color:rgba(255,255,255,.88); text-align:center; line-height:1.3; }

/* ── Nav row ── */
.ex298-nav-row { display:flex; align-items:center; justify-content:space-between; margin-top:2rem; gap:1rem; }
.ex298-next-btn { background:linear-gradient(135deg,#6B2FA0,#3D1060); border:1px solid rgba(201,168,76,.5); color:#C9A84C; font-family:'Georgia',serif; font-size:1rem; padding:.85rem 1.75rem; border-radius:10px; cursor:pointer; transition:opacity .2s; }
.ex298-next-btn:hover { opacity:.88; }
.ex298-back-btn { background:transparent; border:1px solid rgba(255,255,255,.18); color:rgba(255,255,255,.58); font-family:sans-serif; font-size:.88rem; padding:.85rem 1.25rem; border-radius:10px; cursor:pointer; transition:all .15s; }
.ex298-back-btn:hover { border-color:rgba(255,255,255,.35); color:rgba(255,255,255,.85); }

/* ── Photo grid ── */
.ex298-photo-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; }
@media (max-width:420px) { .ex298-photo-grid { grid-template-columns:1fr; } }
.ex298-photo-zone { position:relative; }
.ex298-file-input { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; z-index:2; }
.ex298-photo-label { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.45rem; border:2px dashed rgba(201,168,76,.35); border-radius:12px; padding:1.4rem .75rem 1rem; min-height:120px; cursor:pointer; transition:border-color .2s,background .2s; text-align:center; }
.ex298-photo-label:hover { border-color:#C9A84C; background:rgba(201,168,76,.06); }
.ex298-photo-zone.has-photo .ex298-photo-label { border-style:solid; border-color:#C9A84C; background:rgba(201,168,76,.08); }
.ex298-photo-name { font-size:.82rem; color:#C9A84C; }
.ex298-photo-badge { font-family:sans-serif; font-size:.6rem; letter-spacing:.08em; text-transform:uppercase; color:rgba(201,168,76,.6); border:1px solid rgba(201,168,76,.3); border-radius:4px; padding:.15rem .4rem; }
.ex298-photo-tip { font-family:sans-serif; font-size:.82rem; color:rgba(255,255,255,.82); margin-top:.45rem; text-align:center; line-height:1.45; }
.ex298-preview { display:none; width:100%; border-radius:10px; margin-top:.5rem; max-height:110px; object-fit:cover; }

/* ── Submit button ── */
.ex298-submit-btn { background:linear-gradient(135deg,#6B2FA0,#3D1060); border:1px solid rgba(201,168,76,.55); color:#C9A84C; font-family:'Georgia',serif; font-size:1rem; padding:.9rem 1.75rem; border-radius:10px; cursor:pointer; transition:opacity .2s; white-space:nowrap; }
.ex298-submit-btn:hover { opacity:.88; }

/* ── Loading ── */
.ex298-loading { text-align:center; padding:4rem 2rem; }
.ex298-spinner { width:48px; height:48px; border:3px solid rgba(201,168,76,.18); border-top-color:#C9A84C; border-radius:50%; animation:ex298spin .9s linear infinite; margin:0 auto 1.5rem; }
@keyframes ex298spin { to { transform:rotate(360deg); } }
.ex298-loading-text { font-size:1.05rem; color:#C9A84C; margin-bottom:.4rem; }
.ex298-loading-sub  { font-size:.92rem; color:rgba(255,255,255,.82); font-family:sans-serif; }

/* ── Error ── */
.ex298-error { background:rgba(231,76,60,.12); border:1px solid rgba(231,76,60,.35); border-radius:10px; padding:1rem 1.25rem; font-family:sans-serif; font-size:.88rem; color:#e74c3c; margin-top:1rem; }

/* ── Result card ── */
.ex298-result { background:rgba(255,255,255,.04); border:1px solid rgba(201,168,76,.18); border-radius:16px; padding:1.75rem; margin-top:1.5rem; }
.ex298-score-row { display:flex; align-items:flex-start; gap:1.5rem; margin-bottom:1.75rem; }
@media (max-width:440px) { .ex298-score-row { flex-direction:column; align-items:center; } }
.ex298-score-ring svg { display:block; }
.ex298-score-info { flex:1; }
.ex298-tier-badge { display:inline-block; border:1px solid; border-radius:6px; font-size:.62rem; letter-spacing:.12em; font-family:sans-serif; padding:.3rem .7rem; margin-bottom:.6rem; }
.ex298-result-date { font-size:.88rem; color:rgba(255,255,255,.82); font-family:sans-serif; margin-bottom:.5rem; }
.ex298-trajectory { font-size:.88rem; color:rgba(255,255,255,.85); line-height:1.6; }
/* ── Score delta badge ── */
.ex298-delta { font-size:.68rem; font-family:sans-serif; font-weight:600; letter-spacing:.04em; border-radius:10px; padding:.15rem .55rem; }
.ex298-delta-up   { background:rgba(34,197,94,.13);   color:#22c55e; }
.ex298-delta-down { background:rgba(239,68,68,.13);   color:#f87171; }
.ex298-delta-flat { background:rgba(255,255,255,.06); color:rgba(255,255,255,.4); }

/* ── Streak badge ── */
.ex298-streak-badge { display:inline-flex; align-items:center; gap:.45rem; border-radius:20px; padding:.3rem .85rem .3rem .7rem; margin-bottom:.85rem; font-family:sans-serif; border:1px solid; }
.ex298-streak-base   { background:rgba(255,255,255,.06); border-color:rgba(255,255,255,.15); color:rgba(255,255,255,.65); }
.ex298-streak-green  { background:rgba(62,180,137,.12);  border-color:rgba(62,180,137,.35);  color:#3eb489; }
.ex298-streak-purple { background:rgba(107,47,160,.2);   border-color:rgba(107,47,160,.5);   color:#b07fe0; }
.ex298-streak-gold   { background:rgba(201,168,76,.15);  border-color:rgba(201,168,76,.45);  color:#C9A84C; }
.ex298-streak-fire   { font-size:.85rem; line-height:1; }
.ex298-streak-num    { font-size:1.05rem; font-weight:700; line-height:1; }
.ex298-streak-label  { font-size:.65rem; letter-spacing:.07em; text-transform:uppercase; line-height:1; }

/* ── Score trend chart ── */
.ex298-trend-area { margin:.85rem 0 1.1rem; }
.ex298-trend-head { display:flex; align-items:baseline; justify-content:space-between; font-size:.63rem; letter-spacing:.1em; text-transform:uppercase; color:#C9A84C; font-family:sans-serif; margin-bottom:.55rem; }
.ex298-trend-count { font-size:.78rem; color:rgba(255,255,255,.75); letter-spacing:.06em; }
.ex298-trend-area svg { display:block; overflow:visible; }
.ex298-trend-loading { font-family:sans-serif; font-size:.85rem; color:rgba(255,255,255,.78); padding:.5rem 0; }
.ex298-trend-empty  { font-family:sans-serif; font-size:.88rem; color:rgba(255,255,255,.82); line-height:1.55; margin:.4rem 0; }

.ex298-listen-btn { display:inline-flex; align-items:center; gap:.4rem; margin-top:.75rem; background:rgba(201,168,76,.1); border:1px solid rgba(201,168,76,.35); color:#C9A84C; font-family:sans-serif; font-size:.72rem; letter-spacing:.06em; padding:.35rem .75rem; border-radius:20px; cursor:pointer; transition:all .2s; }
.ex298-listen-btn:hover { background:rgba(201,168,76,.2); border-color:#C9A84C; }
.ex298-listen-btn.ex298-listen-active { background:rgba(201,168,76,.25); border-color:#C9A84C; box-shadow:0 0 8px rgba(201,168,76,.3); }
.ex298-listen-icon { flex-shrink:0; color:#C9A84C; }

.ex298-section { margin-bottom:1.25rem; }
.ex298-section-head { font-size:.68rem; letter-spacing:.1em; text-transform:uppercase; color:#C9A84C; font-family:sans-serif; margin-bottom:.7rem; padding-bottom:.45rem; border-bottom:1px solid rgba(201,168,76,.15); }
.ex298-details-grid { display:grid; gap:.5rem; }
.ex298-detail-item { display:grid; grid-template-columns:120px 1fr; gap:.5rem; font-size:.84rem; }
.ex298-detail-label { color:rgba(255,255,255,.82); font-family:sans-serif; font-size:.88rem; }
.ex298-detail-val   { color:rgba(255,255,255,.9); }

.ex298-insight-block { background:rgba(201,168,76,.06); border-left:3px solid rgba(201,168,76,.45); border-radius:0 8px 8px 0; padding:.8rem 1rem; margin-bottom:1rem; font-size:.86rem; line-height:1.55; color:rgba(255,255,255,.85); }
.ex298-insight-label { font-size:.66rem; letter-spacing:.1em; text-transform:uppercase; color:#C9A84C; font-family:sans-serif; margin-bottom:.3rem; }

.ex298-actions-list { list-style:none; padding:0; margin:0; display:grid; gap:.6rem; }
.ex298-action-item { display:flex; align-items:flex-start; gap:.6rem; font-size:.86rem; line-height:1.5; color:rgba(255,255,255,.9); font-family:sans-serif; }

.ex298-disclaimer { font-size:.8rem; color:rgba(255,255,255,.62); line-height:1.5; border-top:1px solid rgba(255,255,255,.07); padding-top:1rem; margin-top:1.25rem; font-family:sans-serif; }
.ex298-result-footer { text-align:center; margin-top:1.25rem; }
.ex298-moh-link { color:rgba(201,168,76,.78); font-family:sans-serif; font-size:.83rem; text-decoration:none; }
.ex298-moh-link:hover { color:#C9A84C; }
.ex298-redflag-cta { display:flex; flex-direction:column; align-items:center; gap:.75rem; padding:1.1rem 1.3rem; background:rgba(127,29,29,.28); border:1px solid rgba(231,76,60,.45); border-radius:14px; text-align:center; }
.ex298-redflag-icon { font-size:1.5rem; line-height:1; }
.ex298-redflag-text { font-family:sans-serif; font-size:.88rem; color:rgba(255,220,220,.88); line-height:1.55; }
.ex298-redflag-text strong { color:#fff; }
.ex298-redflag-btn { display:inline-block; background:linear-gradient(135deg,#6B2FA0,#A80CA0); border:2px solid rgba(255,255,255,.55); color:#fff; border-radius:26px; padding:10px 26px; font-family:sans-serif; font-size:.88rem; font-weight:700; text-decoration:none; letter-spacing:.04em; transition:opacity .2s,transform .15s; }
.ex298-redflag-btn:hover { opacity:.88; transform:translateY(-2px); color:#fff; }
/* ── Re-baseline widget ── */
.ex298-rebaseline-wrap { margin-top:1.5rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,.06); }
.ex298-rebaseline-toggle { background:none; border:1px solid rgba(255,255,255,.12); color:rgba(255,255,255,.35); border-radius:8px; font-size:.72rem; font-family:sans-serif; letter-spacing:.05em; padding:.45rem 1rem; cursor:pointer; width:100%; text-align:left; transition:color .2s,border-color .2s; }
.ex298-rebaseline-toggle:hover { border-color:rgba(255,255,255,.25); color:rgba(255,255,255,.6); }
.ex298-rebaseline-panel { margin-top:.8rem; background:rgba(255,255,255,.025); border:1px solid rgba(255,255,255,.07); border-radius:8px; padding:.9rem 1rem; }
.ex298-rebaseline-desc { font-family:sans-serif; font-size:.9rem; color:rgba(255,255,255,.82); line-height:1.65; margin:0 0 .8rem; }
.ex298-rebaseline-confirm { display:inline-block; background:rgba(201,168,76,.1); border:1px solid rgba(201,168,76,.35); color:#C9A84C; font-family:sans-serif; font-size:.73rem; font-weight:700; letter-spacing:.04em; border-radius:6px; padding:.45rem 1rem; text-decoration:none; transition:background .2s,border-color .2s; }
.ex298-rebaseline-confirm:hover { background:rgba(201,168,76,.2); border-color:#C9A84C; }

/* ── Admin banner ── */
.ex298-admin-banner { display:flex; align-items:center; flex-wrap:wrap; gap:.6rem; background:rgba(243,156,18,.1); border:1px solid rgba(243,156,18,.4); border-radius:8px; padding:.65rem 1rem; font-family:sans-serif; font-size:.78rem; color:rgba(243,156,18,.9); margin-bottom:1.25rem; }
.ex298-admin-autofill { margin-left:auto; background:rgba(243,156,18,.2); border:1px solid rgba(243,156,18,.5); color:#f39c12; font-family:sans-serif; font-size:.75rem; padding:.35rem .8rem; border-radius:6px; cursor:pointer; white-space:nowrap; }
.ex298-admin-autofill:hover { background:rgba(243,156,18,.3); }

/* ── Mode selector ── */
#ex298-mode-select { padding:.25rem 0 .5rem; }
.ex298-mode-heading { font-size:1.15rem; color:#fff; text-align:center; margin-bottom:.35rem; }
.ex298-mode-sub { font-family:sans-serif; font-size:.88rem; color:rgba(255,255,255,.88); text-align:center; margin-bottom:1.75rem; line-height:1.5; }
.ex298-mode-cards { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media (max-width:480px) { .ex298-mode-cards { grid-template-columns:1fr; } }
.ex298-mode-card { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:1.5rem 1.25rem; text-align:left; cursor:pointer; transition:border-color .2s,background .2s,transform .15s; font-family:inherit; color:#fff; width:100%; }
.ex298-mode-card:hover { border-color:rgba(201,168,76,.45); background:rgba(255,255,255,.07); transform:translateY(-2px); }
.ex298-mode-card:active { transform:translateY(0); }
.ex298-mode-pill { display:inline-block; font-family:sans-serif; font-size:.6rem; letter-spacing:.14em; text-transform:uppercase; font-weight:700; padding:.22rem .6rem; border-radius:4px; margin-bottom:.8rem; }
.ex298-mode-pill.quick { background:rgba(39,174,96,.18); color:#27ae60; border:1px solid rgba(39,174,96,.35); }
.ex298-mode-pill.full  { background:rgba(201,168,76,.18); color:#C9A84C; border:1px solid rgba(201,168,76,.35); }
.ex298-mode-mins { font-size:2.1rem; font-family:'Georgia',serif; color:#fff; line-height:1; margin-bottom:.2rem; }
.ex298-mode-name { font-size:.95rem; color:rgba(255,255,255,.9); margin-bottom:.15rem; }
.ex298-mode-pct  { font-family:sans-serif; font-size:.82rem; color:rgba(255,255,255,.82); margin-bottom:1rem; }
.ex298-mode-items { font-family:sans-serif; font-size:.9rem; color:rgba(255,255,255,.9); display:grid; gap:.3rem; margin-bottom:1rem; line-height:1.4; }
.ex298-mode-skip { color:rgba(255,255,255,.78); }
.ex298-mode-ideal { font-family:sans-serif; font-size:.8rem; color:rgba(255,255,255,.82); border-top:1px solid rgba(255,255,255,.15); padding-top:.7rem; }
.ex298-mode-card-full:hover { border-color:rgba(201,168,76,.5); }

/* ── Strip zone (Full mode) ── */
.ex298-strip-zone { margin-top:1.5rem; }
.ex298-strip-header { display:flex; align-items:center; gap:.45rem; font-family:sans-serif; font-size:.7rem; letter-spacing:.1em; text-transform:uppercase; color:#C9A84C; margin-bottom:.85rem; padding-bottom:.5rem; border-bottom:1px solid rgba(201,168,76,.18); }
.ex298-strip-sub { font-size:.68rem; letter-spacing:0; text-transform:none; color:rgba(255,255,255,.35); margin-left:.25rem; }
</style>
<?php }
