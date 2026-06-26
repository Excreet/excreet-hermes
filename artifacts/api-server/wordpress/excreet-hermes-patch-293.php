<?php
/**
 * Plugin Name: Excreet Hermes Patch 2.9.3
 * Description: Ministry of Healing — private one-on-one AI health intelligence chat.
 *
 *   Starter  $15/30 days  (product 860)  →  10 AI responses / 30-day period
 *   Premium  $25/30 days  (product 861)  →  20 AI responses / 30-day period
 *   Unlimited Add-On      (product 862)  →  unlimited responses (bypasses counter)
 *
 *   Counter is silent until 4 responses remain, then shows loud warnings each turn.
 *   Hard block at 0 remaining. Unlimited add-on holders see no counter at all.
 *
 * Version:    2.9.3b
 * Depends on: excreet-hermes-patch-291.php  (excreet_291_is_member)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── Constants ────────────────────────────────────────────────────────────── */

define( 'EX293_MOH_PAGE_ID',          231  );
define( 'EX293_BASE_PRODUCT_ID',      860  );   // Starter $15 / 30 days — 10 sessions
define( 'EX293_PREMIUM_PRODUCT_ID',   861  );   // Premium $25 / 30 days — 20 sessions
// EX293_UNLIMITED_PRODUCT_ID (legacy MemberPress post ID 862) — no longer used;
// the PMPro level ID is stored in the EX293_UNLIMITED_PRODUCT_OPT option instead.
define( 'EX293_BASE_LIMIT',           10   );   // responses per 30-day period (Starter)
define( 'EX293_PREMIUM_LIMIT',        20   );   // responses per 30-day period (Premium)
define( 'EX293_WARN_THRESHOLD',       4    );   // show loud warnings when this many remain
define( 'EX293_USAGE_META',          '_excreet_moh_usage'            );
define( 'EX293_PREMIUM_PRODUCT_OPT',   '_excreet_293_premium_product'  );  // stores PMPro level ID of Premium level
define( 'EX293_UNLIMITED_PRODUCT_OPT', '_excreet_293_unlimited_product' );  // stores PMPro level ID of Unlimited level
define( 'EX293_PAGE_SETUP_OPT',        '_excreet_293_page_setup'       );
define( 'EX293_PURPLE',              '#6B2FA0' );
define( 'EX293_PURPLE_DARK',         '#3D1060' );
define( 'EX293_GOLD',                '#C9A84C' );

if ( ! defined( 'EXCREET_HERMES_MINISTRY_URL' ) ) {
    define( 'EXCREET_HERMES_MINISTRY_URL', 'https://core-status-check.replit.app/api/hermes/ministry/chat' );
}
if ( ! defined( 'EXCREET_HERMES_MINISTRY_HISTORY_URL' ) ) {
    define( 'EXCREET_HERMES_MINISTRY_HISTORY_URL', 'https://core-status-check.replit.app/api/hermes/ministry/history' );
}

/* ── Hooks ────────────────────────────────────────────────────────────────── */

add_action( 'init',                     'excreet_293_setup',      1  );
add_action( 'template_redirect',        'excreet_293_gate',       20 );
add_action( 'wp_ajax_excreet_moh_chat',           'excreet_293_ajax_chat'           );
add_action( 'wp_ajax_excreet_moh_load_history',   'excreet_293_ajax_load_history'   );
add_action( 'wp_ajax_excreet_moh_mark_rebaseline','excreet_293_ajax_mark_rebaseline');
add_shortcode( 'excreet_ministry_of_healing', 'excreet_293_shortcode' );

/* ════════════════════════════════════════════════════════════════════════════
   SETUP  (idempotent — runs once per option key)
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_293_setup(): void {
    if ( ! get_option( EX293_PAGE_SETUP_OPT ) ) {
        wp_update_post( [
            'ID'           => EX293_MOH_PAGE_ID,
            'post_content' => '[excreet_ministry_of_healing]',
            'post_status'  => 'publish',
        ] );
        update_option( EX293_PAGE_SETUP_OPT, '1' );
    }

    excreet_293_ensure_premium_product();
    excreet_293_ensure_unlimited_product();
}

/**
 * Creates the $25/month Premium level in PMPro (once).
 * After creation, configure the checkout page in WP Admin →
 * Memberships → Membership Levels → "Ministry of Healing — Premium".
 */
function excreet_293_ensure_premium_product(): void {
    if ( get_option( EX293_PREMIUM_PRODUCT_OPT ) ) {
        return;
    }
    if ( ! function_exists( 'pmpro_addMembershipLevel' ) ) {
        return;
    }

    $level_id = pmpro_addMembershipLevel( [
        'name'              => 'Ministry of Healing — Premium ($25/month)',
        'initial_payment'   => 0.00,
        'billing_amount'    => 25.00,
        'cycle_number'      => 1,
        'cycle_period'      => 'Month',
        'billing_limit'     => 0,
        'trial_amount'      => 0.00,
        'trial_limit'       => 0,
        'allow_signups'     => 1,
        'expiration_number' => 0,
        'expiration_period' => 'Year',
    ] );

    if ( $level_id ) {
        update_option( EX293_PREMIUM_PRODUCT_OPT, $level_id );
    }
}

/**
 * Creates the Unlimited Q&A Add-On level in PMPro (once).
 * 30-day expiry; $0 — admin-assigns manually or via separate Stripe flow.
 */
function excreet_293_ensure_unlimited_product(): void {
    if ( get_option( EX293_UNLIMITED_PRODUCT_OPT ) ) {
        return;
    }
    if ( ! function_exists( 'pmpro_addMembershipLevel' ) ) {
        return;
    }

    $level_id = pmpro_addMembershipLevel( [
        'name'              => 'Ministry of Healing — Unlimited Q&A Add-On (30 Days)',
        'initial_payment'   => 0.00,
        'billing_amount'    => 0.00,
        'cycle_number'      => 0,
        'cycle_period'      => 'Day',
        'billing_limit'     => 0,
        'trial_amount'      => 0.00,
        'trial_limit'       => 0,
        'allow_signups'     => 1,
        'expiration_number' => 30,
        'expiration_period' => 'Day',
    ] );

    if ( $level_id ) {
        update_option( EX293_UNLIMITED_PRODUCT_OPT, $level_id );
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   GATE — members only on page 231
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_293_gate(): void {
    if ( ! is_singular( 'page' ) || get_the_ID() !== EX293_MOH_PAGE_ID ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( get_permalink( EX293_MOH_PAGE_ID ) ) );
        exit;
    }
    if ( function_exists( 'excreet_291_is_member' ) && ! excreet_291_is_member() ) {
        wp_safe_redirect( home_url( '/membership-payment-page/' ) );
        exit;
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   TIER DETECTION
   ════════════════════════════════════════════════════════════════════════════ */

/** True if this member is on the Premium ($25/month) PMPro level. */
function excreet_293_is_premium( int $user_id ): bool {
    $premium_id = (int) get_option( EX293_PREMIUM_PRODUCT_OPT, 0 );
    if ( ! $premium_id || ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
        return false;
    }
    return pmpro_hasMembershipLevel( $premium_id, $user_id );
}

/** True if this member holds the Unlimited Q&A Add-On PMPro level. */
function excreet_293_has_unlimited( int $user_id ): bool {
    $unlimited_id = (int) get_option( EX293_UNLIMITED_PRODUCT_OPT, 0 );
    if ( ! $unlimited_id || ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
        return false;
    }
    return pmpro_hasMembershipLevel( $unlimited_id, $user_id );
}

/** Returns the response cap for this user based on their active tier. */
function excreet_293_get_limit( int $user_id ): int {
    return excreet_293_is_premium( $user_id ) ? EX293_PREMIUM_LIMIT : EX293_BASE_LIMIT;
}

/* ════════════════════════════════════════════════════════════════════════════
   USAGE TRACKING
   ════════════════════════════════════════════════════════════════════════════ */

/**
 * Returns current usage for user, auto-resetting after each 30-day window.
 * Shape: [ 'count' => int, 'period_start' => int ]
 */
function excreet_293_get_usage( int $user_id ): array {
    $raw  = get_user_meta( $user_id, EX293_USAGE_META, true );
    $data = is_array( $raw ) ? $raw : [ 'count' => 0, 'period_start' => time() ];

    if ( time() - (int) ( $data['period_start'] ?? 0 ) > 30 * DAY_IN_SECONDS ) {
        $data = [ 'count' => 0, 'period_start' => time() ];
        update_user_meta( $user_id, EX293_USAGE_META, $data );
    }

    return $data;
}

/**
 * Returns responses remaining this period.
 * Result honours the member's current tier (10 or 20).
 */
function excreet_293_remaining( int $user_id ): int {
    $limit = excreet_293_get_limit( $user_id );
    $usage = excreet_293_get_usage( $user_id );
    return max( 0, $limit - (int) $usage['count'] );
}

function excreet_293_increment_usage( int $user_id ): void {
    $usage          = excreet_293_get_usage( $user_id );
    $usage['count'] = (int) $usage['count'] + 1;
    update_user_meta( $user_id, EX293_USAGE_META, $usage );
}

/* ════════════════════════════════════════════════════════════════════════════
   HERMES API CALL
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_293_call_hermes( string $message, array $history, string $member_id, array $attachments = [], string $tier = 'starter', string $baseline_context = '' ) {
    if ( ! defined( 'EXCREET_HERMES_API_KEY' ) ) {
        return new \WP_Error( 'no_key', 'Hermes API key not configured in wp-config.php' );
    }

    $payload = [
        'member_id'            => $member_id,
        'message'              => $message,
        'conversation_history' => $history,
        'tier'                 => $tier,   // passed to server-side session ledger
    ];
    if ( ! empty( $attachments ) ) {
        $payload['attachments'] = $attachments;
    }
    // Inject member's onboarding Clinical Pattern Report as baseline context
    // so the Ministry of Healing is never starting cold.
    if ( $baseline_context !== '' ) {
        $payload['baseline_context'] = $baseline_context;
    }

    $body = wp_json_encode( $payload );

    $response = wp_remote_post( EXCREET_HERMES_MINISTRY_URL, [
        'timeout' => 60,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY,
        ],
        'body' => $body,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $data   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status !== 200 || ! isset( $data['response'] ) ) {
        return new \WP_Error( 'api_error', 'Unexpected Hermes response: HTTP ' . $status );
    }

    return (string) $data['response'];
}

/* ════════════════════════════════════════════════════════════════════════════
   AJAX CHAT HANDLER
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_293_ajax_chat(): void {
    check_ajax_referer( 'excreet_moh_chat', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'code' => 'not_logged_in' ], 401 );
    }
    if ( function_exists( 'excreet_291_is_member' ) && ! excreet_291_is_member() ) {
        wp_send_json_error( [ 'code' => 'not_member' ], 403 );
    }

    $user_id      = get_current_user_id();
    $is_unlimited = excreet_293_has_unlimited( $user_id );
    $message      = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );

    $history = json_decode( wp_unslash( $_POST['history'] ?? '[]' ), true );
    if ( ! is_array( $history ) ) { $history = []; }

    if ( '' === $message ) {
        wp_send_json_error( [ 'code' => 'empty_message' ], 400 );
    }

    // Enforce session limit — unlimited add-on holders bypass this entirely
    if ( ! $is_unlimited ) {
        $remaining = excreet_293_remaining( $user_id );
        if ( $remaining === 0 ) {
            $is_premium  = excreet_293_is_premium( $user_id );
            $premium_level_id   = (int) get_option( EX293_PREMIUM_PRODUCT_OPT, 0 );
            $upgrade_url = $is_premium
                ? home_url( '/membership-payment-page/' )
                : ( $premium_level_id && function_exists( 'pmpro_url' )
                    ? pmpro_url( 'checkout', '?level=' . $premium_level_id )
                    : home_url( '/membership-payment-page/' ) );
            wp_send_json_error( [
                'code'        => 'limit_reached',
                'is_premium'  => $is_premium,
                'upgrade_url' => $upgrade_url,
            ], 429 );
        }
    }

    // Sanitise history — last 40 turns, valid roles only
    $clean = [];
    foreach ( array_slice( $history, -40 ) as $h ) {
        if ( isset( $h['role'], $h['content'] )
            && in_array( $h['role'], [ 'user', 'assistant' ], true ) ) {
            $clean[] = [ 'role' => $h['role'], 'content' => sanitize_text_field( $h['content'] ) ];
        }
    }

    /* ── Attachments (base64 JSON sent by the browser) ── */
    $attachments      = [];
    $allowed_mime     = [ 'application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
    $max_file_bytes   = 10 * 1024 * 1024; // 10 MB raw ≈ 13.3 MB base64
    $raw_attach       = json_decode( wp_unslash( $_POST['attachments_json'] ?? '[]' ), true );

    if ( is_array( $raw_attach ) ) {
        foreach ( array_slice( $raw_attach, 0, 3 ) as $att ) {
            if ( ! isset( $att['name'], $att['mime_type'], $att['data'] ) ) { continue; }
            if ( ! in_array( $att['mime_type'], $allowed_mime, true ) )      { continue; }
            $raw_bytes = base64_decode( $att['data'], true );
            if ( $raw_bytes === false || strlen( $raw_bytes ) > $max_file_bytes ) { continue; }
            $attachments[] = [
                'name'      => sanitize_file_name( (string) $att['name'] ),
                'mime_type' => (string) $att['mime_type'],
                'data'      => (string) $att['data'],
            ];
        }
    }

    $user      = wp_get_current_user();
    $member_id = $user->user_login ?: (string) $user_id;

    // Determine tier to send to server-side session ledger
    $tier = 'starter';
    if ( excreet_293_has_unlimited( $user_id ) ) {
        $tier = 'unlimited';
    } elseif ( excreet_293_is_premium( $user_id ) ) {
        $tier = 'premium';
    }

    // Load the member's onboarding Clinical Pattern Report baseline.
    // Stored as user_meta when the intake job completes (pharmaceutical_intake).
    $raw_baseline    = get_user_meta( $user_id, 'excreet_hermes_baseline', true );
    $baseline_context = is_string( $raw_baseline ) && $raw_baseline !== '' ? $raw_baseline : '';

    $api_response = excreet_293_call_hermes( $message, $clean, $member_id, $attachments, $tier, $baseline_context );

    if ( is_wp_error( $api_response ) ) {
        wp_send_json_error( [
            'code'    => 'api_error',
            'message' => 'The healing guide is temporarily unavailable. Please try again in a moment.',
        ], 502 );
    }

    // Increment and compute remaining — skipped for unlimited holders
    if ( $is_unlimited ) {
        $new_remaining = -1; // signals "unlimited" to JS
        $limit         = 0;
    } else {
        excreet_293_increment_usage( $user_id );
        $new_remaining = excreet_293_remaining( $user_id );
        $limit         = excreet_293_get_limit( $user_id );
    }

    wp_send_json_success( [
        'response'     => $api_response,
        'remaining'    => $new_remaining,
        'limit'        => $limit,
        'is_premium'   => excreet_293_is_premium( $user_id ),
        'is_unlimited' => $is_unlimited,
    ] );
}

/* ════════════════════════════════════════════════════════════════════════════
   AJAX — LOAD CONVERSATION HISTORY
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_293_ajax_mark_rebaseline(): void {
    check_ajax_referer( 'excreet_moh_chat', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'code' => 'not_logged_in' ], 401 );
        return;
    }

    $user      = wp_get_current_user();
    $member_id = $user->user_login ?: (string) get_current_user_id();

    $hermes_url = defined( 'EXCREET_HERMES_BASE_URL' )
        ? rtrim( EXCREET_HERMES_BASE_URL, '/' ) . '/api/hermes/ministry/history/mark'
        : 'https://core-status-check.replit.app/api/hermes/ministry/history/mark';

    $response = wp_remote_post( $hermes_url, [
        'timeout' => 15,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY,
        ],
        'body' => wp_json_encode( [ 'member_id' => $member_id ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_success( [] ); // fail soft — non-critical
        return;
    }

    wp_send_json_success( [] );
}

function excreet_293_ajax_load_history(): void {
    check_ajax_referer( 'excreet_moh_chat', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_success( [ 'messages' => [] ] ); // fail soft
        return;
    }

    $user      = wp_get_current_user();
    $member_id = $user->user_login ?: (string) get_current_user_id();

    $response = wp_remote_get(
        EXCREET_HERMES_MINISTRY_HISTORY_URL . '/' . rawurlencode( $member_id ),
        [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY,
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_success( [ 'messages' => [] ] ); // fail soft
        return;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $data   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status !== 200 || ! isset( $data['messages'] ) || ! is_array( $data['messages'] ) ) {
        wp_send_json_success( [ 'messages' => [] ] ); // fail soft
        return;
    }

    // Sanitise — only pass role + content through to the browser
    $safe = [];
    foreach ( $data['messages'] as $m ) {
        if ( isset( $m['role'], $m['content'] )
            && in_array( $m['role'], [ 'user', 'assistant' ], true ) ) {
            $safe[] = [
                'role'    => $m['role'],
                'content' => (string) $m['content'],
            ];
        }
    }

    wp_send_json_success( [ 'messages' => $safe ] );
}

/* ════════════════════════════════════════════════════════════════════════════
   SHORTCODE  [excreet_ministry_of_healing]
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_293_shortcode(): string {
    if ( ! is_user_logged_in() ) {
        return '<p style="font-family:Georgia,serif;padding:24px;">Please <a href="'
            . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to access the Ministry of Healing.</p>';
    }

    $user_id      = get_current_user_id();
    $user         = wp_get_current_user();
    $display_name = $user->display_name ?: $user->user_login;

    $is_unlimited = excreet_293_has_unlimited( $user_id );
    $is_premium   = excreet_293_is_premium( $user_id );
    $limit        = $is_unlimited ? 0 : excreet_293_get_limit( $user_id );
    $remaining    = $is_unlimited ? 0 : excreet_293_remaining( $user_id );
    $used         = $is_unlimited ? 0 : ( $limit - $remaining );

    // Upgrade URL: Premium members get the add-on; Starter members get an upgrade to Premium
    $premium_level_id   = (int) get_option( EX293_PREMIUM_PRODUCT_OPT, 0 );
    $unlimited_level_id = (int) get_option( EX293_UNLIMITED_PRODUCT_OPT, 0 );
    $upgrade_url = $is_premium
        ? ( $unlimited_level_id && function_exists( 'pmpro_url' )
            ? pmpro_url( 'checkout', '?level=' . $unlimited_level_id )
            : home_url( '/membership-payment-page/' ) )
        : ( $premium_level_id && function_exists( 'pmpro_url' )
            ? pmpro_url( 'checkout', '?level=' . $premium_level_id )
            : home_url( '/membership-payment-page/' ) );

    $nonce    = wp_create_nonce( 'excreet_moh_chat' );
    $ajax_url = admin_url( 'admin-ajax.php' );

    $purple      = EX293_PURPLE;
    $purple_dark = EX293_PURPLE_DARK;
    $gold        = EX293_GOLD;

    /* ── Protocol button data (uses patch-294 functions if loaded) ── */
    $proto_credits      = (int) get_user_meta( $user_id, '_excreet_protocol_credits', true );
    $proto_checkout_url = function_exists( 'excreet_294_checkout_url' )
        ? excreet_294_checkout_url()
        : home_url( '/membership-payment-page/' );
    $proto_nonce        = wp_create_nonce( 'excreet_gen_protocol' );
    $proto_is_admin     = current_user_can( 'manage_options' );
    $proto_can_generate = $proto_credits > 0 || $proto_is_admin;

    /* ── Monthly background rotation (1 = Jan … 12 = Dec) ── */
    $bg_month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url    = 'https://excreet.com/wp-content/uploads/healer-bg-' . $bg_month . '.jpg';

    /* ── Usage bar ── */
    // Silent mode: counter is hidden until EX293_WARN_THRESHOLD responses remain.
    // Unlimited holders: no usage bar at all.
    $is_silent   = ( ! $is_unlimited && $remaining > EX293_WARN_THRESHOLD );
    $bar_color   = $remaining <= EX293_WARN_THRESHOLD ? '#c0392b' : $purple;
    $pct         = $limit > 0 ? round( ( $used / $limit ) * 100 ) : 0;
    $bar_display = ( $is_unlimited || $is_silent ) ? 'display:none;' : '';

    if ( $is_unlimited ) {
        $usage_html = ''; // no bar for unlimited members
    } elseif ( $remaining === 0 ) {
        if ( $is_premium ) {
            $usage_html = '<span style="color:#c0392b;font-weight:700;">Session limit reached.</span>'
                . ' <span style="color:#666;font-size:12px;">Your ' . EX293_PREMIUM_LIMIT . ' sessions reset at your next billing date.'
                . ' <a href="' . esc_url( $upgrade_url ) . '" style="color:' . $purple . ';font-weight:600;margin-left:4px;">Add unlimited sessions →</a></span>';
        } else {
            $usage_html = '<span style="color:#c0392b;font-weight:700;">Session limit reached.</span>'
                . ' <a href="' . esc_url( $upgrade_url ) . '" style="color:' . $purple . ';font-weight:700;margin-left:8px;">Upgrade to Premium — 20 sessions →</a>';
        }
    } else {
        $tier_label = $is_premium ? 'Premium plan' : 'Starter plan';
        $usage_html = '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">'
            . '<span style="font-size:13px;color:#444;white-space:nowrap;">'
            . '<strong style="color:' . $bar_color . ';">⚠ ' . $remaining . '</strong> session' . ( $remaining !== 1 ? 's' : '' ) . ' remaining this period'
            . ' <span style="color:#999;font-weight:400;">· ' . $tier_label . '</span></span>'
            . '<div style="flex:1;min-width:100px;height:6px;background:#e0d0ee;border-radius:3px;">'
            . '<div style="width:' . $pct . '%;height:6px;background:' . $bar_color . ';border-radius:3px;transition:width .4s;"></div></div>'
            . ( ! $is_premium
                ? '<a href="' . esc_url( $upgrade_url ) . '" style="font-size:12px;color:' . $purple . ';font-weight:600;white-space:nowrap;text-decoration:none;border:1px solid ' . $purple . ';padding:3px 10px;border-radius:12px;">Upgrade to Premium · 20 sessions</a>'
                : '<a href="' . esc_url( $upgrade_url ) . '" style="font-size:12px;color:' . $purple . ';font-weight:600;white-space:nowrap;text-decoration:none;border:1px solid ' . $purple . ';padding:3px 10px;border-radius:12px;">Add unlimited sessions</a>' )
            . '</div>';
    }

    $limit_reached = ( ! $is_unlimited && $remaining === 0 );

    ob_start();
    ?>
    <style id="excreet-293-moh-css">

    /* ── Page-level atmosphere — healer's office with books & light ── */

    /* Hide site header on this page — redundant for logged-in members */
    body.page-id-231 .site-header,
    body.page-id-231 #masthead,
    body.page-id-231 header.site-header,
    body.page-id-231 #site-header { display: none !important; }

    /* Hide redundant page title */
    body.page-id-231 h1.entry-title,
    body.page-id-231 .entry-header,
    body.page-id-231 .page-header { display: none !important; }

    /* Full-page background — monthly healing image, auto-rotates each month */
    body.page-id-231 {
        background:
            url('<?php echo esc_url( $bg_url ); ?>') center/cover no-repeat fixed !important;
    }

    body.page-id-231 #page,
    body.page-id-231 .site-content,
    body.page-id-231 #content,
    body.page-id-231 #main,
    body.page-id-231 .site-main,
    body.page-id-231 .entry-content {
        background: transparent !important;
    }

    body.page-id-231 .entry-content {
        padding-top: 32px !important;
    }

    /* Cards float over the photo — enhanced lift shadow */
    body.page-id-231 #excreet-moh,
    body.page-id-231 #excreet-protocol-card,
    body.page-id-231 #excreet-protocol-doc {
        box-shadow: 0 12px 60px rgba(0,0,0,.55), 0 2px 12px rgba(0,0,0,.3) !important;
    }

    /* Scroll hint bounce animation */
    @keyframes excreet-bounce {
        0%, 100% { transform: translateY(0); opacity: .7; }
        50%       { transform: translateY(10px); opacity: 1; }
    }
    .excreet-scroll-arrow { animation: excreet-bounce 1.8s ease-in-out infinite; display: inline-block; }

    /* ── File attachment chips ── */
    #excreet-moh-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px 0 0;
        min-height: 0;
    }
    .moh-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(107,47,160,.1);
        border: 1px solid rgba(107,47,160,.3);
        border-radius: 20px;
        padding: 4px 12px 4px 10px;
        font-size: 12px;
        color: #3D1060;
        font-family: sans-serif;
        max-width: 220px;
    }
    .moh-chip-icon { font-size: 13px; flex-shrink: 0; }
    .moh-chip-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .moh-chip-remove {
        background: none;
        border: none;
        cursor: pointer;
        color: #999;
        font-size: 14px;
        line-height: 1;
        padding: 0;
        flex-shrink: 0;
    }
    .moh-chip-remove:hover { color: #c0392b; }
    #excreet-moh-attach-btn {
        display: inline-flex;
        align-items: center;
        background: none;
        border: 1px solid rgba(107,47,160,.35);
        border-radius: 18px;
        padding: 5px 14px;
        font-size: 12px;
        color: #6B2FA0;
        cursor: pointer;
        font-family: sans-serif;
        white-space: nowrap;
        transition: background .15s;
        user-select: none;
        -webkit-user-select: none;
    }
    #excreet-moh-attach-btn:hover { background: rgba(107,47,160,.08); }
    #excreet-moh-attach-btn.moh-attach-disabled {
        opacity: .4;
        cursor: default;
        pointer-events: none;
    }

    #excreet-moh {
        font-family: Georgia, 'Times New Roman', serif;
        max-width: 820px;
        margin: 0 auto 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 40px rgba(0,0,0,.45);
    }
    #excreet-moh-header {
        background: linear-gradient(135deg, <?php echo $purple_dark; ?> 0%, <?php echo $purple; ?> 100%);
        padding: 22px 28px;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    #excreet-moh-usage {
        background: #F7F4FC;
        padding: 11px 20px;
        border-bottom: 1px solid #e0d0ee;
        font-family: Georgia, serif;
    }
    #excreet-moh-messages {
        min-height: 320px;
        max-height: 540px;
        overflow-y: auto;
        padding: 22px 20px;
        background: #fff;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .moh-history-sep {
        text-align: center;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: rgba(0,0,0,.3);
        padding: 8px 0 4px;
        position: relative;
    }
    .moh-history-sep::before, .moh-history-sep::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 30%;
        height: 1px;
        background: rgba(0,0,0,.1);
    }
    .moh-history-sep::before { left: 0; }
    .moh-history-sep::after  { right: 0; }
    .moh-history-sep-new { margin-top: 8px; color: <?php echo $purple; ?>; opacity: .6; }
    .moh-bubble { max-width: 82%; line-height: 1.68; font-size: 15px; }
    .moh-label  { font-size: 13px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 5px; opacity: .9; }
    .moh-ai {
        align-self: flex-start;
        background: #F7F4FC;
        border-left: 3px solid <?php echo $purple; ?>;
        padding: 13px 16px;
        border-radius: 4px 12px 12px 4px;
        color: #1a1a1a;
    }
    .moh-user {
        align-self: flex-end;
        background: <?php echo $purple; ?>;
        padding: 13px 16px;
        border-radius: 12px 4px 4px 12px;
        color: #fff;
    }
    #excreet-moh-typing {
        display: none;
        align-self: flex-start;
        background: #F7F4FC;
        border-left: 3px solid <?php echo $purple; ?>;
        padding: 11px 16px;
        border-radius: 4px 12px 12px 4px;
        color: <?php echo $purple; ?>;
        font-style: italic;
        font-size: 14px;
    }
    #excreet-moh-starter-tip {
        background: #faf5ff;
        border: 1px dashed <?php echo $purple; ?>;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 13px;
        color: #444;
        margin-bottom: 10px;
        display: none;
    }
    #excreet-moh-starter-tip strong { color: <?php echo $purple_dark; ?>; }
    #excreet-moh-input-area {
        background: #fafafa;
        border-top: 1px solid #e0d0ee;
        padding: 15px 20px;
    }
    #excreet-moh-textarea {
        width: 100%;
        min-height: 82px;
        max-height: 220px;
        padding: 11px 14px;
        border: 1px solid #c9b0e0;
        border-radius: 8px;
        font-family: Georgia, serif;
        font-size: 15px;
        color: #222;
        resize: vertical;
        box-sizing: border-box;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }
    #excreet-moh-textarea:focus {
        border-color: <?php echo $purple; ?>;
        box-shadow: 0 0 0 2px rgba(107,47,160,.12);
    }
    #excreet-moh-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }
    .moh-tool-btn {
        font-size: 12px;
        color: <?php echo $purple; ?>;
        background: none;
        border: 1px solid <?php echo $purple; ?>;
        border-radius: 12px;
        padding: 3px 11px;
        cursor: pointer;
        font-family: Georgia, serif;
        transition: background .15s, color .15s;
    }
    .moh-tool-btn:hover { background: <?php echo $purple; ?>; color: #fff; }
    #excreet-moh-send-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
        gap: 12px;
    }
    #excreet-moh-send {
        background: linear-gradient(135deg, <?php echo $purple_dark; ?>, <?php echo $purple; ?>);
        color: #fff;
        border: none;
        padding: 10px 28px;
        border-radius: 24px;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        cursor: pointer;
        font-family: Georgia, serif;
        transition: opacity .2s;
        white-space: nowrap;
    }
    #excreet-moh-send:disabled { opacity: .45; cursor: not-allowed; }
    #excreet-moh-error {
        display: none;
        background: #fdf0f0;
        color: #c0392b;
        padding: 9px 14px;
        border-radius: 6px;
        font-size: 13px;
        margin-top: 8px;
    }
    #excreet-moh-privacy {
        text-align: center;
        font-size: 11px;
        color: #999;
        padding: 9px 20px 13px;
        background: #fafafa;
        border-top: 1px solid #f0e8f8;
    }
    </style>

    <div id="excreet-moh">

        <!-- Header -->
        <div id="excreet-moh-header">
            <img src="https://excreet.com/wp-content/uploads/excreet-hero-logo.png" alt="Excreet"
                 style="width:62px;height:62px;border-radius:50%;flex-shrink:0;object-fit:cover;box-shadow:0 2px 14px rgba(0,0,0,.4);" />
            <div>
                <div style="color:<?php echo $gold; ?>;font-size:22px;font-weight:700;letter-spacing:.03em;margin-bottom:2px;font-family:Georgia,serif;text-shadow:0 1px 8px rgba(0,0,0,.35);">Excreet™</div>
                <div style="color:#fff;font-size:20px;font-weight:700;line-height:1.2;">Ministry of Healing</div>
                <div style="color:rgba(255,255,255,.92);font-size:15px;margin-top:3px;">One-on-one with your Excreet Healer · Completely private</div>
            </div>
        </div>

        <!-- Usage bar -->
        <div id="excreet-moh-usage" style="<?php echo esc_attr( $bar_display ); ?>"><?php echo $usage_html; ?></div>

        <!-- Messages -->
        <div id="excreet-moh-messages" role="log" aria-live="polite">
            <div class="moh-bubble moh-ai">
                <div class="moh-label">Ministry of Healing</div>
                <div>Welcome, <strong><?php echo esc_html( $display_name ); ?></strong>. This is your private healing space — nothing shared here is stored on our servers.<br><br>
                You have <strong><?php echo $remaining; ?> of <?php echo $limit; ?> responses</strong> available this month. To get the most from each one, share your full picture upfront — the more context you give, the more precise and useful the guidance.<br><br>
                Use the <strong>Starter Prompt</strong> button below if you'd like a template to guide your first message. Or just begin — <em>what's on your mind?</em></div>
            </div>
            <div id="excreet-moh-typing">Your healing guide is responding…</div>
        </div>

        <!-- Input area -->
        <?php if ( $limit_reached ) : ?>
        <div id="excreet-moh-input-area" style="text-align:center;padding:24px 20px;">
            <p style="color:#666;margin-bottom:14px;font-size:15px;">
                <?php if ( $is_premium ) : ?>
                    You have used all <?php echo $limit; ?> responses this month. They reset at the start of your next billing period.
                <?php else : ?>
                    You have used all <?php echo $limit; ?> responses on the $15/month plan.
                <?php endif; ?>
            </p>
            <?php if ( ! $is_premium ) : ?>
            <a href="<?php echo esc_url( $upgrade_url ); ?>" style="display:inline-block;background:linear-gradient(135deg,<?php echo $purple_dark; ?>,<?php echo $purple; ?>);color:#fff;padding:12px 30px;border-radius:24px;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:.06em;text-transform:uppercase;">Switch to $25/month · 20 responses →</a>
            <?php endif; ?>
        </div>
        <?php else : ?>
        <div id="excreet-moh-input-area">
            <!-- Starter prompt tip (hidden by default) -->
            <div id="excreet-moh-starter-tip">
                <strong>Starter prompt template</strong> — fill in what applies, skip what doesn't:<br>
                <em>Current concern:</em> [main issue or question]<br>
                <em>Symptoms &amp; timeline:</em> [when they started, how they've changed]<br>
                <em>Current medications/supplements:</em> [name, dose, how long]<br>
                <em>Diet &amp; lifestyle:</em> [diet type, sleep, stress level, exercise]<br>
                <em>What I've already tried:</em> [treatments, remedies, doctor visits]<br>
                <em>What I'm hoping to learn:</em> [specific guidance or next steps you want]
            </div>

            <!-- Toolbar -->
            <div id="excreet-moh-toolbar">
                <button class="moh-tool-btn" id="excreet-moh-starter-btn" type="button">📋 Starter Prompt</button>
                <label id="excreet-moh-attach-btn" for="excreet-moh-file-input" tabindex="0" role="button" title="Attach PDF or image (max 3 files, 10 MB each)">📎 Attach File</label>
                <span style="font-size:14px;color:#ccc;">PDF · JPG · PNG · WEBP &nbsp;·&nbsp; up to 3 files, 10 MB each</span>
            </div>

            <!-- File input — visually hidden but accessible; the label above triggers it directly -->
            <input type="file" id="excreet-moh-file-input"
                accept=".pdf,.jpg,.jpeg,.png,.webp"
                multiple
                style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;"
                aria-label="Attach files">

            <textarea id="excreet-moh-textarea"
                placeholder="Describe your health concern, symptoms, lab results, or question — the more detail, the better the guidance…"
                aria-label="Your message to the Ministry of Healing"></textarea>

            <!-- Attached file chips -->
            <div id="excreet-moh-chips"></div>

            <div id="excreet-moh-error"></div>

            <div id="excreet-moh-send-row">
                <span style="font-size:14px;color:#ccc;">Ctrl+Enter to send &nbsp;·&nbsp; Enter for new line</span>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button id="excreet-moh-send" type="button">Send Message</button>
                    <?php if ( $proto_can_generate ) : ?>
                    <button id="excreet-moh-proto-btn" type="button"
                        style="background:linear-gradient(135deg,#b8922a,<?php echo $gold; ?>);color:#fff;border:none;padding:10px 22px;border-radius:24px;font-size:13px;font-weight:700;letter-spacing:.04em;cursor:pointer;font-family:Georgia,serif;white-space:nowrap;box-shadow:0 2px 12px rgba(201,168,76,.45);transition:opacity .2s;">
                        Instant Healing Protocol · $29
                    </button>
                    <?php else : ?>
                    <a href="<?php echo esc_url( $proto_checkout_url ); ?>"
                        style="display:inline-block;background:linear-gradient(135deg,#b8922a,<?php echo $gold; ?>);color:#fff;padding:10px 22px;border-radius:24px;font-size:13px;font-weight:700;letter-spacing:.04em;text-decoration:none;font-family:Georgia,serif;white-space:nowrap;box-shadow:0 2px 12px rgba(201,168,76,.45);">
                        Instant Healing Protocol · $29
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Moat confirmation panel (hidden until gold button clicked) -->
            <div id="excreet-moh-moat" style="display:none;margin-top:12px;padding:16px 20px;background:rgba(61,16,96,.07);border:1px solid rgba(201,168,76,.35);border-radius:14px;font-family:Georgia,serif;"></div>
        </div>
        <?php endif; ?>

        <!-- Privacy notice -->
        <div id="excreet-moh-privacy">
            🔒 Your conversations are not stored on our servers. Each session is a completely private, fresh start.
        </div>

        <!-- Share with Provider — revealed after first AI response -->
        <div id="excreet-moh-provider-cta" style="display:none;margin-top:20px;padding:18px 22px;background:rgba(12,2,26,.72);border:1px solid rgba(201,168,76,.28);border-radius:16px;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);">
            <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
                <div style="flex:1;min-width:200px;">
                    <div style="font-family:Georgia,serif;font-size:.82rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(201,168,76,.75);margin-bottom:5px;">Ready to loop in your doctor?</div>
                    <div style="font-family:Georgia,serif;font-size:.88rem;color:rgba(240,232,255,.8);line-height:1.55;">Once your conversation feels complete, generate a one-page clinical summary your provider can read in under a minute.</div>
                </div>
                <a href="<?php echo esc_url( home_url( '/provider-report/' ) ); ?>"
                   style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,rgba(30,10,60,.9),rgba(60,20,100,.9));border:1px solid rgba(201,168,76,.5);color:#C9A84C;border-radius:12px;padding:10px 18px;font-family:Georgia,serif;font-size:.82rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;text-decoration:none;white-space:nowrap;transition:border-color .2s,background .2s;flex-shrink:0;">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="flex-shrink:0;"><rect x="2" y="1" width="10" height="13" rx="1.5" stroke="currentColor" stroke-width="1.4" fill="none"/><path d="M5 5h6M5 8h6M5 11h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                    Share with My Provider
                </a>
            </div>
        </div>

    </div><!-- #excreet-moh -->

    <!-- Scroll-down hint -->
    <div style="text-align:center;padding:20px 0 10px;">
        <div style="font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:rgba(201,168,76,.65);margin-bottom:8px;font-family:Georgia,serif;">Your Healing Protocol awaits below</div>
        <span class="excreet-scroll-arrow" style="font-size:30px;color:<?php echo $gold; ?>;line-height:1;">↓</span>
    </div>

    <script>
    (function () {
        var ajaxUrl    = <?php echo wp_json_encode( $ajax_url ); ?>;
        var nonce      = <?php echo wp_json_encode( $nonce ); ?>;
        var upgradeUrl = <?php echo wp_json_encode( $upgrade_url ); ?>;
        var limit       = <?php echo (int) $limit; ?>;
        var isPremium   = <?php echo $is_premium   ? 'true' : 'false'; ?>;
        var isUnlimited = <?php echo $is_unlimited ? 'true' : 'false'; ?>;
        var warnThresh  = <?php echo (int) EX293_WARN_THRESHOLD; ?>;
        var purple      = <?php echo wp_json_encode( $purple ); ?>;
        var purpleDark  = <?php echo wp_json_encode( $purple_dark ); ?>;
        var gold        = <?php echo wp_json_encode( $gold ); ?>;

        /* ── Protocol button vars ── */
        var protoNonce       = <?php echo wp_json_encode( $proto_nonce ); ?>;
        var protoCheckoutUrl = <?php echo wp_json_encode( $proto_checkout_url ); ?>;
        var protoCanGenerate = <?php echo $proto_can_generate ? 'true' : 'false'; ?>;

        /* ── Live remaining count (kept in sync with updateUsageBar) ── */
        var currentRemaining = <?php echo (int) $remaining; ?>;

        var messagesEl  = document.getElementById('excreet-moh-messages');
        var typingEl    = document.getElementById('excreet-moh-typing');
        var textarea    = document.getElementById('excreet-moh-textarea');
        var sendBtn     = document.getElementById('excreet-moh-send');
        var errorEl     = document.getElementById('excreet-moh-error');
        var usageEl     = document.getElementById('excreet-moh-usage');
        var starterBtn  = document.getElementById('excreet-moh-starter-btn');
        var starterTip  = document.getElementById('excreet-moh-starter-tip');
        var attachBtn   = document.getElementById('excreet-moh-attach-btn');
        var fileInput   = document.getElementById('excreet-moh-file-input');
        var chipsEl     = document.getElementById('excreet-moh-chips');

        if (!textarea || !sendBtn) { return; } // Limit reached — no input rendered

        var history       = [];
        var attachedFiles = []; // [{ name, mime_type, data }]

        /* ── Load persisted conversation history on page init ── */
        function exLoadMohHistory() {
            var fd = new FormData();
            fd.append('action', 'excreet_moh_load_history');
            fd.append('nonce',  nonce);

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.data || !data.data.messages) { return; }
                var msgs = data.data.messages;
                if (!msgs.length) { return; }

                /* Separator: "Prior conversations" */
                var sep = document.createElement('div');
                sep.className   = 'moh-history-sep';
                sep.textContent = 'Prior conversations';
                messagesEl.insertBefore(sep, typingEl);

                /* Render each stored message */
                msgs.forEach(function(m) {
                    addBubble(m.role === 'user' ? 'user' : 'ai', m.content);
                });

                /* Populate history array so AI has full context on the next send */
                history = msgs.map(function(m) {
                    return { role: m.role, content: m.content };
                });

                /* Separator: "New session" */
                var sep2 = document.createElement('div');
                sep2.className   = 'moh-history-sep moh-history-sep-new';
                sep2.textContent = 'New session';
                messagesEl.insertBefore(sep2, typingEl);

                scrollBottom();
            })
            .catch(function() { /* silent — history is non-critical */ });
        }

        var MAX_FILES     = 3;
        var MAX_BYTES     = 10 * 1024 * 1024;
        var ALLOWED_TYPES = ['application/pdf','image/jpeg','image/png','image/webp','image/gif'];

        /* ── File chips ── */
        function renderChips() {
            if (!chipsEl) { return; }
            chipsEl.innerHTML = '';
            attachedFiles.forEach(function(f, idx) {
                var icon = f.mime_type === 'application/pdf' ? '📄' : '🖼️';
                var chip = document.createElement('div');
                chip.className = 'moh-chip';
                chip.innerHTML =
                    '<span class="moh-chip-icon">' + icon + '</span>'
                  + '<span class="moh-chip-name" title="' + f.name + '">' + f.name + '</span>'
                  + '<button class="moh-chip-remove" data-idx="' + idx + '" type="button" title="Remove">×</button>';
                chipsEl.appendChild(chip);
            });
            chipsEl.querySelectorAll('.moh-chip-remove').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    attachedFiles.splice(parseInt(btn.getAttribute('data-idx'), 10), 1);
                    renderChips();
                    updateAttachBtn();
                });
            });
        }

        function updateAttachBtn() {
            if (attachBtn) {
                var full = attachedFiles.length >= MAX_FILES;
                attachBtn.classList.toggle('moh-attach-disabled', full);
                attachBtn.textContent = attachedFiles.length > 0
                    ? '📎 Add More (' + attachedFiles.length + '/' + MAX_FILES + ')'
                    : '📎 Attach File';
            }
        }

        /* ── File input change ── */
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                var files = Array.prototype.slice.call(fileInput.files);
                var remaining = MAX_FILES - attachedFiles.length;
                files = files.slice(0, remaining);

                var pending = files.length;
                if (pending === 0) { fileInput.value = ''; return; }

                files.forEach(function(file) {
                    if (!ALLOWED_TYPES.includes(file.type)) {
                        hideError();
                        showError('Only PDF, JPG, PNG, and WEBP files are supported.');
                        pending--;
                        return;
                    }
                    if (file.size > MAX_BYTES) {
                        showError(file.name + ' is too large — maximum 10 MB per file.');
                        pending--;
                        return;
                    }
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var dataUrl = e.target.result;
                        var base64  = dataUrl.split(',')[1];
                        attachedFiles.push({ name: file.name, mime_type: file.type, data: base64 });
                        pending--;
                        if (pending <= 0) { renderChips(); updateAttachBtn(); }
                    };
                    reader.readAsDataURL(file);
                });
                fileInput.value = '';
            });
        }

        /* ── Starter prompt toggle ── */
        if (starterBtn && starterTip) {
            starterBtn.addEventListener('click', function () {
                var shown = starterTip.style.display === 'block';
                starterTip.style.display = shown ? 'none' : 'block';
                starterBtn.textContent   = shown ? '📋 Starter Prompt' : '✕ Hide template';
            });
        }

        /* ── Helpers ── */
        function scrollBottom() { messagesEl.scrollTop = messagesEl.scrollHeight; }

        function addBubble(role, content, remainingOverride) {
            var wrap  = document.createElement('div');
            wrap.className = 'moh-bubble ' + (role === 'ai' ? 'moh-ai' : 'moh-user');
            var label = document.createElement('div');
            label.className   = 'moh-label';
            label.textContent = role === 'ai' ? 'Ministry of Healing' : 'You';
            var body = document.createElement('div');
            body.innerHTML = content
                .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/\n\n/g,'<br><br>').replace(/\n/g,'<br>');
            wrap.appendChild(label);
            wrap.appendChild(body);

            /* ── Per-message usage counter (AI bubbles only) ──
             * Silent until warnThresh remain; then loud warning until terminal.
             * Unlimited holders (isUnlimited) see nothing at all.
             */
            if (role === 'ai' && !isUnlimited && limit > 0) {
                var rem = (remainingOverride !== undefined) ? remainingOverride : currentRemaining;
                if (rem <= warnThresh) {
                    var counter = document.createElement('div');
                    counter.style.cssText = 'margin-top:10px;padding:8px 16px;border-radius:10px;background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.28);font-size:15px;font-weight:700;color:#c0392b;font-family:Georgia,serif;letter-spacing:.02em;text-align:right;';
                    counter.textContent   = rem <= 0
                        ? '⚠ Session limit reached for this period'
                        : '⚠ ' + rem + ' session' + (rem !== 1 ? 's' : '') + ' remaining this period';
                    wrap.appendChild(counter);
                }
                // rem > warnThresh → silent; no counter appended
            }

            messagesEl.insertBefore(wrap, typingEl);
            scrollBottom();
        }

        function showTyping() { typingEl.style.display = 'block'; scrollBottom(); }
        function hideTyping() { typingEl.style.display = 'none'; }
        function showError(msg) { errorEl.textContent = msg; errorEl.style.display = 'block'; }
        function hideError()    { errorEl.style.display = 'none'; }

        function updateUsageBar(newRemaining) {
            currentRemaining = newRemaining;

            // Unlimited holders — bar stays hidden
            if (isUnlimited) {
                usageEl.style.display = 'none';
                return;
            }

            if (newRemaining <= 0) {
                // Terminal: hard block
                usageEl.style.display = 'block';
                if (isPremium) {
                    usageEl.innerHTML = '<span style="color:#c0392b;font-weight:700;">Session limit reached.</span>'
                        + ' <span style="color:#666;font-size:12px;">Your ' + limit + ' sessions reset at your next billing date.'
                        + ' <a href="' + upgradeUrl + '" style="color:' + purple + ';font-weight:600;margin-left:4px;">Add unlimited sessions →</a></span>';
                } else {
                    usageEl.innerHTML = '<span style="color:#c0392b;font-weight:700;">Session limit reached.</span>'
                        + ' <a href="' + upgradeUrl + '" style="color:' + purple + ';font-weight:700;margin-left:8px;">Upgrade to Premium — 20 sessions →</a>';
                }
                textarea.disabled = true;
                sendBtn.disabled  = true;
            } else if (newRemaining <= warnThresh) {
                // Loud zone: show warning bar
                usageEl.style.display = 'block';
                var used      = limit - newRemaining;
                var pct       = Math.round((used / limit) * 100);
                var tierLabel = isPremium ? 'Premium plan' : 'Starter plan';
                var upgradeLabel = isPremium ? 'Add unlimited sessions' : 'Upgrade to Premium · 20 sessions';
                usageEl.innerHTML = '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">'
                    + '<span style="font-size:13px;color:#444;white-space:nowrap;">'
                    + '<strong style="color:#c0392b;">⚠ ' + newRemaining + '</strong> session' + (newRemaining !== 1 ? 's' : '') + ' remaining this period'
                    + ' <span style="color:#999;font-weight:400;">· ' + tierLabel + '</span></span>'
                    + '<div style="flex:1;min-width:100px;height:6px;background:#e0d0ee;border-radius:3px;">'
                    + '<div style="width:' + pct + '%;height:6px;background:#c0392b;border-radius:3px;transition:width .4s;"></div></div>'
                    + '<a href="' + upgradeUrl + '" style="font-size:12px;color:' + purple + ';font-weight:600;white-space:nowrap;text-decoration:none;border:1px solid ' + purple + ';padding:3px 10px;border-radius:12px;">' + upgradeLabel + '</a>'
                    + '</div>';
            } else {
                // Silent zone: hide bar
                usageEl.style.display = 'none';
            }
        }

        /* ── Send ── */
        function sendMessage() {
            var message = textarea.value.trim();
            if (!message || sendBtn.disabled) { return; }

            hideError();
            var sentFiles    = attachedFiles.slice(); // snapshot before clearing
            attachedFiles    = [];
            renderChips();
            updateAttachBtn();
            textarea.value    = '';
            textarea.disabled = true;
            sendBtn.disabled  = true;
            if (attachBtn) { attachBtn.disabled = true; }

            /* Build user bubble label including file names if any */
            var userLabel = message;
            if (sentFiles.length > 0) {
                userLabel += '\n\n📎 ' + sentFiles.map(function(f){ return f.name; }).join('  ·  ');
            }
            addBubble('user', userLabel);
            showTyping();

            var fd = new FormData();
            fd.append('action',           'excreet_moh_chat');
            fd.append('nonce',            nonce);
            fd.append('message',          message);
            fd.append('history',          JSON.stringify(history));
            fd.append('attachments_json', JSON.stringify(sentFiles));

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                hideTyping();
                if (!data.success) {
                    var code = data.data && data.data.code;
                    if (code === 'limit_reached') {
                        var limitMsg = isPremium
                            ? "You've used all " + limit + " sessions on the Premium plan. They reset at your next billing date. You can add unlimited sessions from your account."
                            : "You've used all " + limit + " sessions on the Starter plan. Upgrade to Premium for 20 sessions per period, or add unlimited sessions.";
                        addBubble('ai', limitMsg);
                        updateUsageBar(0);
                    } else {
                        showError((data.data && data.data.message) || 'Something went wrong. Please try again.');
                        textarea.disabled = false;
                        sendBtn.disabled  = false;
                        if (attachBtn) { attachBtn.disabled = false; updateAttachBtn(); }
                    }
                    return;
                }
                var response     = data.data.response;
                var newRemaining = data.data.remaining;

                history.push({ role: 'user',      content: message  });
                history.push({ role: 'assistant',  content: response });
                if (history.length > 40) { history = history.slice(history.length - 40); }

                addBubble('ai', response, newRemaining);
                updateUsageBar(newRemaining);

                // Reveal "Share with My Provider" after first AI response
                var providerCta = document.getElementById('excreet-moh-provider-cta');
                if (providerCta && providerCta.style.display === 'none') {
                    providerCta.style.display = 'block';
                    providerCta.style.opacity = '0';
                    providerCta.style.transition = 'opacity .6s ease';
                    setTimeout(function() { providerCta.style.opacity = '1'; }, 50);
                }

                // Re-enable input: always for unlimited; for limited only when sessions remain
                if (isUnlimited || newRemaining > 0) {
                    textarea.disabled = false;
                    sendBtn.disabled  = false;
                    if (attachBtn) { attachBtn.disabled = false; updateAttachBtn(); }
                    textarea.focus();
                }
            })
            .catch(function() {
                hideTyping();
                showError('Connection error. Please check your connection and try again.');
                textarea.disabled = false;
                sendBtn.disabled  = false;
                if (attachBtn) { attachBtn.disabled = false; updateAttachBtn(); }
            });
        }

        sendBtn.addEventListener('click', sendMessage);
        textarea.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') { e.preventDefault(); sendMessage(); }
        });

        scrollBottom();
        exLoadMohHistory();

        /* ── Gold "Instant Healing Protocol" button — two-moat confirmation ── */
        var protoBtn  = document.getElementById('excreet-moh-proto-btn');
        var moatPanel = document.getElementById('excreet-moh-moat');

        function resetProtoBtn() {
            protoBtn.disabled    = false;
            protoBtn.textContent = 'Instant Healing Protocol · $29';
            if (sendBtn)  { sendBtn.disabled  = false; }
            if (textarea) { textarea.disabled = false; }
        }

        function runProtocol(concern) {
            hideError();
            moatPanel.style.display = 'none';
            protoBtn.disabled    = true;
            protoBtn.textContent = 'Building your protocol…';
            if (sendBtn)   { sendBtn.disabled  = true; }
            if (textarea)  { textarea.disabled = true; }

            var fd = new FormData();
            fd.append('action',          'excreet_gen_protocol');
            fd.append('nonce',           protoNonce);
            fd.append('current_concern', concern);

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                resetProtoBtn();
                if (!data.success) {
                    var code = data.data && data.data.code;
                    if (code === 'no_credits') {
                        window.location.href = protoCheckoutUrl;
                    } else {
                        showError((data.data && data.data.message) || 'Protocol generation failed. Please try again.');
                    }
                    return;
                }
                var protoOutput = document.getElementById('excreet-protocol-output');
                if (protoOutput) {
                    protoOutput.innerHTML = data.data.html;
                    protoOutput.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                var histWrap = document.getElementById('excreet-history-wrap');
                if (histWrap && data.data.history_html) {
                    var existingList = histWrap.querySelector('.excreet-history-list');
                    if (existingList) {
                        var tmp = document.createElement('div');
                        tmp.innerHTML = data.data.history_html;
                        existingList.insertBefore(tmp.firstElementChild, existingList.firstChild);
                        var badge = histWrap.querySelector('.excreet-hist-count');
                        if (badge) {
                            var n = existingList.querySelectorAll('.excreet-history-item').length;
                            badge.textContent = n + ' protocol' + (n !== 1 ? 's' : '') + ' on file';
                        }
                    } else {
                        histWrap.innerHTML =
                            '<div style="margin-top:20px;padding:20px 0 0;border-top:2px solid #ede4f5;">'
                          + '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">'
                          + '<div style="font-size:20px;font-weight:700;color:' + purpleDark + ';font-family:Georgia,serif;">Your Past Protocols</div>'
                          + '<div class="excreet-hist-count" style="font-size:12px;color:#aaa;font-style:italic;">1 protocol on file</div>'
                          + '</div>'
                          + '<p style="font-size:13px;color:#888;margin:0 0 16px;line-height:1.5;">Each protocol is yours to keep. Click any entry to read the full document.</p>'
                          + '<div class="excreet-history-list">' + data.data.history_html + '</div>'
                          + '</div>';
                    }
                }
            })
            .catch(function () {
                resetProtoBtn();
                showError('Connection error. Please check your connection and try again.');
            });
        }

        function showMoat2(concern) {
            /* Moat 2 — brief-concern guard */
            moatPanel.style.display = 'block';
            moatPanel.innerHTML =
                '<div style="font-size:15px;font-weight:700;color:' + purpleDark + ';margin-bottom:8px;font-family:Georgia,serif;">Your description is quite brief.</div>'
              + '<p style="font-size:13px;color:#555;margin:0 0 14px;line-height:1.6;">'
              + 'A more detailed concern — symptoms, duration, anything you've already tried — helps the protocol speak directly to your situation. '
              + 'You can also add lab results and relevant files once the full intake form is available. '
              + 'Would you like to add more detail first, or proceed with what you've shared?'
              + '</p>'
              + '<div style="display:flex;gap:10px;flex-wrap:wrap;">'
              + '<button id="ex-moat2-add" style="background:#f5f0fb;color:' + purpleDark + ';border:1px solid ' + purpleDark + ';padding:8px 20px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;font-family:Georgia,serif;">Add more detail</button>'
              + '<button id="ex-moat2-go"  style="background:linear-gradient(135deg,#b8922a,' + gold + ');color:#fff;border:none;padding:8px 20px;border-radius:20px;font-size:13px;font-weight:700;cursor:pointer;font-family:Georgia,serif;">Proceed anyway</button>'
              + '</div>';
            document.getElementById('ex-moat2-add').addEventListener('click', function () {
                moatPanel.style.display = 'none';
                if (textarea) { textarea.focus(); }
            });
            document.getElementById('ex-moat2-go').addEventListener('click', function () {
                runProtocol(concern);
            });
        }

        if (protoBtn && protoCanGenerate) {
            protoBtn.addEventListener('click', function () {
                var concern = textarea ? textarea.value.trim() : '';
                if (!concern) {
                    showError('Please share your current health concern first — that's what the protocol is built from.');
                    return;
                }

                /* Moat 1 — accidental-click guard */
                hideError();
                moatPanel.style.display = 'block';
                moatPanel.innerHTML =
                    '<div style="font-size:15px;font-weight:700;color:' + purpleDark + ';margin-bottom:8px;font-family:Georgia,serif;">Generate your Instant Healing Protocol?</div>'
                  + '<p style="font-size:13px;color:#555;margin:0 0 14px;line-height:1.6;">'
                  + 'This will use one protocol credit and build a personalised healing document from your message below. '
                  + 'The protocol is yours to keep and will appear in your history.'
                  + '</p>'
                  + '<div style="display:flex;gap:10px;flex-wrap:wrap;">'
                  + '<button id="ex-moat1-cancel" style="background:#f5f0fb;color:' + purpleDark + ';border:1px solid ' + purpleDark + ';padding:8px 20px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;font-family:Georgia,serif;">Cancel</button>'
                  + '<button id="ex-moat1-confirm" style="background:linear-gradient(135deg,#b8922a,' + gold + ');color:#fff;border:none;padding:8px 20px;border-radius:20px;font-size:13px;font-weight:700;cursor:pointer;font-family:Georgia,serif;">Yes, generate my protocol</button>'
                  + '</div>';

                document.getElementById('ex-moat1-cancel').addEventListener('click', function () {
                    moatPanel.style.display = 'none';
                });
                document.getElementById('ex-moat1-confirm').addEventListener('click', function () {
                    /* Moat 2 fires only if concern is very brief (< 60 chars) */
                    if (concern.length < 60) {
                        showMoat2(concern);
                    } else {
                        runProtocol(concern);
                    }
                });
            });
        }
    })();
    </script>
    <?php
    return (string) ob_get_clean();
}
