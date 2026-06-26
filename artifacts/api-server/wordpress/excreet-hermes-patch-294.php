<?php
/**
 * Plugin Name: Excreet Hermes Patch 2.9.5
 * Description: Excreet Healing Protocol — $29 one-time purchase.
 *              Combines member intake history + current concern into a
 *              comprehensive, personalized Excreet-style healing protocol.
 *
 *              - Creates $29 MemberPress one-time product on first load
 *              - Stores intake snapshot in user meta when intake webhook fires
 *              - Adds full structured intake form to /ask-the-healer/
 *              - File attachments (lab results, images) wired into protocol engine
 *              - Stripe Checkout integration: create-checkout + credit-grant REST endpoint
 *              - AJAX handler calls POST /api/hermes/ministry/protocol
 *              - Protocol rendered in formatted branded sections
 *
 * Version:    2.9.5
 * Depends on: excreet-hermes-patch-293.php, excreet-hermes-client.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── Constants ────────────────────────────────────────────────────────────── */

define( 'EX294_MOH_PAGE_ID',          231    );
define( 'EX294_PROTOCOL_PRICE',       29.00  );
define( 'EX294_PRODUCT_OPT',          '_excreet_294_protocol_product'  );
define( 'EX294_PAGE_SETUP_OPT',       '_excreet_294_page_setup'        );
define( 'EX294_INTAKE_META',          '_excreet_member_intake'         );
define( 'EX294_CREDIT_META',          '_excreet_protocol_credits'      );
define( 'EX294_HISTORY_META',         '_excreet_protocol_history'      );
define( 'EX294_PURPLE',               '#6B2FA0' );
define( 'EX294_PURPLE_DARK',          '#3D1060' );
define( 'EX294_GOLD',                 '#C9A84C' );

if ( ! defined( 'EXCREET_HERMES_PROTOCOL_URL' ) ) {
    define( 'EXCREET_HERMES_PROTOCOL_URL', 'https://core-status-check.replit.app/api/hermes/ministry/protocol' );
}

/* ── Hooks ────────────────────────────────────────────────────────────────── */

add_action( 'init',                              'excreet_294_setup',           1  );
add_action( 'pmpro_after_checkout',              'excreet_294_on_purchase',     10, 2 );
add_action( 'wp_ajax_excreet_gen_protocol',      'excreet_294_ajax_protocol'       );
add_action( 'admin_menu',                        'excreet_294_admin_menu'          );
add_action( 'wp_ajax_excreet_294_grant_credit',  'excreet_294_ajax_grant_credit'   );
add_shortcode( 'excreet_healing_protocol',       'excreet_294_shortcode'           );
add_action( 'rest_api_init',                          'excreet_294_register_rest_routes'    );
add_action( 'wp_ajax_excreet_294_stripe_checkout',    'excreet_294_ajax_stripe_checkout'    );

/* ════════════════════════════════════════════════════════════════════════════
   SETUP
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_294_setup(): void {
    excreet_294_ensure_product();

    if ( ! get_option( EX294_PAGE_SETUP_OPT ) ) {
        wp_update_post( [
            'ID'           => EX294_MOH_PAGE_ID,
            'post_content' => '[excreet_ministry_of_healing][excreet_healing_protocol]',
            'post_status'  => 'publish',
        ] );
        update_option( EX294_PAGE_SETUP_OPT, '1' );
    }
}

function excreet_294_ensure_product(): void {
    if ( get_option( EX294_PRODUCT_OPT ) ) {
        return;
    }
    if ( ! function_exists( 'pmpro_addMembershipLevel' ) ) {
        return;
    }

    // One-time payment level: billing_limit=1, billing_amount=0 after initial
    $level_id = pmpro_addMembershipLevel( [
        'name'              => 'Excreet Healing Protocol — One Session ($29)',
        'initial_payment'   => (float) EX294_PROTOCOL_PRICE,
        'billing_amount'    => 0.00,
        'billing_limit'     => 1,
        'cycle_number'      => 0,
        'cycle_period'      => 'Day',
        'trial_amount'      => 0.00,
        'trial_limit'       => 0,
        'allow_signups'     => 1,
        'expiration_number' => 0,
        'expiration_period' => 'Year',
    ] );

    if ( $level_id ) {
        update_option( EX294_PRODUCT_OPT, $level_id );
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   INTAKE SNAPSHOT STORAGE
   Called by excreet-hermes-client.php (v2.8.1+) during intake webhook.
   Finds the member by email in the raw form body and persists their intake
   fields in user meta so the protocol engine can retrieve them later.
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_store_intake_snapshot( array $body, array $payload ): void {
    $email = '';

    foreach ( [ 'email', 'email-1', 'email_1', 'email-2', 'email_2' ] as $key ) {
        if ( ! empty( $body[ $key ] ) && is_string( $body[ $key ] ) ) {
            $candidate = sanitize_email( trim( $body[ $key ] ) );
            if ( is_email( $candidate ) ) {
                $email = $candidate;
                break;
            }
        }
    }

    if ( '' === $email ) {
        foreach ( $body as $value ) {
            if ( is_string( $value ) ) {
                $candidate = sanitize_email( trim( $value ) );
                if ( is_email( $candidate ) ) {
                    $email = $candidate;
                    break;
                }
            }
        }
    }

    if ( '' === $email ) {
        return;
    }

    $user = get_user_by( 'email', $email );
    if ( ! ( $user instanceof WP_User ) ) {
        return;
    }

    $snapshot = [
        'age'         => sanitize_text_field( (string) ( $payload['age']         ?? '' ) ),
        'sex'         => sanitize_text_field( (string) ( $payload['sex']         ?? '' ) ),
        'symptoms'    => sanitize_text_field( (string) ( $payload['symptoms']    ?? '' ) ),
        'medications' => sanitize_text_field( (string) ( $payload['medications'] ?? '' ) ),
        'concerns'    => sanitize_text_field( (string) ( $payload['concerns']    ?? '' ) ),
        'surgeries'   => sanitize_text_field( (string) ( $payload['surgeries']   ?? '' ) ),
        'alias'       => sanitize_text_field( (string) ( $payload['alias']       ?? '' ) ),
        'stored_at'   => gmdate( 'c' ),
    ];

    update_user_meta( $user->ID, EX294_INTAKE_META, $snapshot );
}

/* ════════════════════════════════════════════════════════════════════════════
   INTAKE RETRIEVAL — WITH FORMINATOR BACKFILL
   Returns the stored intake snapshot for a user. If none is in user meta yet
   (members who submitted before this patch was deployed), queries the Forminator
   database directly, reconstructs the fields, and stores the result so future
   calls are fast (one DB query, ever).
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_294_get_intake_for_user( int $user_id ): array {

    // Fast path — user meta already populated
    $meta = get_user_meta( $user_id, EX294_INTAKE_META, true );
    if ( is_array( $meta ) && ! empty( $meta['stored_at'] ) ) {
        return $meta;
    }

    // Slow path — backfill from Forminator database
    if ( ! defined( 'EXCREET_FORM_ID' ) ) {
        return [];
    }

    global $wpdb;

    $entry_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT e.entry_id
           FROM {$wpdb->prefix}frmt_form_entry e
           INNER JOIN {$wpdb->prefix}frmt_form_entry_meta m ON e.entry_id = m.entry_id
          WHERE e.form_id = %d
            AND m.meta_key = 'entry_created_by'
            AND m.meta_value = %s
          ORDER BY e.entry_id DESC
          LIMIT 1",
        EXCREET_FORM_ID,
        (string) $user_id
    ) );

    if ( ! $entry_id ) {
        return [];
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT meta_key, meta_value
           FROM {$wpdb->prefix}frmt_form_entry_meta
          WHERE entry_id = %d",
        absint( $entry_id )
    ), ARRAY_A );

    if ( empty( $rows ) ) {
        return [];
    }

    $raw = [];
    foreach ( $rows as $row ) {
        $raw[ $row['meta_key'] ] = $row['meta_value'];
    }

    $pluck = function ( array $keys ) use ( $raw ): string {
        foreach ( $keys as $key ) {
            if ( ! empty( $raw[ $key ] ) && is_string( $raw[ $key ] ) ) {
                return sanitize_text_field( $raw[ $key ] );
            }
        }
        return '';
    };

    $snapshot = [
        'alias'       => $pluck( [ 'name_1',     'name-1',     'alias', 'private_alias', 'name' ] ),
        'age'         => $pluck( [ 'number_1',   'number-1',   'age' ] ),
        'sex'         => $pluck( [ 'radio_1',    'radio-1',    'select_1', 'select-1', 'sex', 'gender' ] ),
        'symptoms'    => $pluck( [ 'checkbox_2', 'checkbox-2', 'symptoms' ] ),
        'medications' => $pluck( [ 'textarea_1', 'textarea-1', 'medications' ] ),
        'concerns'    => $pluck( [ 'textarea_2', 'textarea-2', 'concerns' ] ),
        'surgeries'   => $pluck( [ 'textarea_3', 'textarea-3', 'surgeries' ] ),
        'stored_at'   => gmdate( 'c' ),
        'source'      => 'forminator_backfill',
    ];

    // Persist only if we got meaningful data
    if ( ! empty( $snapshot['symptoms'] ) || ! empty( $snapshot['age'] ) || ! empty( $snapshot['concerns'] ) ) {
        update_user_meta( $user_id, EX294_INTAKE_META, $snapshot );
    }

    return $snapshot;
}

/* ── Shared helper: PMPro checkout URL for the protocol level ── */

function excreet_294_checkout_url(): string {
    $level_id = (int) get_option( EX294_PRODUCT_OPT, 0 );
    if ( $level_id > 0 && function_exists( 'pmpro_url' ) ) {
        return pmpro_url( 'checkout', '?level=' . $level_id );
    }
    return home_url( '/membership-payment-page/' );
}

/* ════════════════════════════════════════════════════════════════════════════
   STRIPE CREDIT GRANT — REST ENDPOINT
   Called by Hermes after checkout.session.completed webhook.
   Validates HMAC(wp_user_id:stripe_session_id, HERMES_API_KEY), then adds
   one credit. Idempotent — duplicate session IDs are silently ignored.
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_294_register_rest_routes(): void {
    register_rest_route( 'excreet/v1', '/protocol-credit', [
        'methods'             => 'POST',
        'callback'            => 'excreet_294_rest_grant_credit',
        'permission_callback' => '__return_true',
    ] );
}

function excreet_294_rest_grant_credit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    $params      = $request->get_json_params();
    $wp_user_id  = sanitize_text_field( (string) ( $params['wp_user_id']        ?? '' ) );
    $session_id  = sanitize_text_field( (string) ( $params['stripe_session_id'] ?? '' ) );
    $given_hmac  = sanitize_text_field( (string) ( $params['hmac']              ?? '' ) );

    if ( ! $wp_user_id || ! $session_id || ! $given_hmac ) {
        return new WP_Error( 'missing_params', 'Missing required parameters.', [ 'status' => 400 ] );
    }

    if ( ! defined( 'EXCREET_HERMES_API_KEY' ) ) {
        return new WP_Error( 'config_error', 'Server configuration error.', [ 'status' => 500 ] );
    }

    $expected = hash_hmac( 'sha256', $wp_user_id . ':' . $session_id, EXCREET_HERMES_API_KEY );
    if ( ! hash_equals( $expected, $given_hmac ) ) {
        return new WP_Error( 'invalid_hmac', 'Invalid signature.', [ 'status' => 403 ] );
    }

    // Idempotency: skip if this Stripe session was already processed
    $processed_opt = '_excreet_stripe_sessions';
    $processed     = get_option( $processed_opt, [] );
    if ( ! is_array( $processed ) ) { $processed = []; }

    if ( in_array( $session_id, $processed, true ) ) {
        return rest_ensure_response( [ 'status' => 'already_processed' ] );
    }

    $user_id = (int) $wp_user_id;
    $current = (int) get_user_meta( $user_id, EX294_CREDIT_META, true );
    update_user_meta( $user_id, EX294_CREDIT_META, $current + 1 );

    // Keep last 1 000 processed sessions to bound option size
    $processed[] = $session_id;
    $processed   = array_slice( $processed, -1000 );
    update_option( $processed_opt, $processed, false );

    return rest_ensure_response( [ 'status' => 'ok', 'credits' => $current + 1 ] );
}

/* ════════════════════════════════════════════════════════════════════════════
   STRIPE CHECKOUT — AJAX (initiates Stripe Checkout via Hermes)
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_294_ajax_stripe_checkout(): void {
    check_ajax_referer( 'excreet_gen_protocol', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'code' => 'not_logged_in' ], 401 );
    }

    $user      = wp_get_current_user();
    $user_id   = get_current_user_id();
    $member_id = $user->user_login ?: (string) $user_id;

    if ( ! defined( 'EXCREET_HERMES_API_KEY' ) ) {
        wp_send_json_error( [ 'code' => 'no_key' ], 500 );
    }

    $return_url = home_url( '/ask-the-healer/' );
    $body       = wp_json_encode( [
        'wp_user_id' => (string) $user_id,
        'member_id'  => $member_id,
        'return_url' => $return_url,
    ] );

    $response = wp_remote_post(
        'https://core-status-check.replit.app/api/hermes/ministry/stripe/create-checkout',
        [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY,
            ],
            'body' => $body,
        ]
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'code' => 'api_error', 'message' => 'Checkout unavailable. Please try again.' ], 502 );
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $data   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( 503 === $status ) {
        wp_send_json_error( [ 'code' => 'stripe_not_configured', 'message' => 'Online payment is not yet enabled. Please contact support.' ], 503 );
    }

    if ( $status !== 200 || empty( $data['checkout_url'] ) ) {
        wp_send_json_error( [ 'code' => 'stripe_error', 'message' => 'Unable to initiate checkout. Please try again.' ], 502 );
    }

    wp_send_json_success( [ 'checkout_url' => $data['checkout_url'] ] );
}

/* ════════════════════════════════════════════════════════════════════════════
   PURCHASE HANDLER
   Adds one protocol credit when the $29 product is purchased.
   ════════════════════════════════════════════════════════════════════════════ */

/**
 * Fires on pmpro_after_checkout (PMPro hook).
 * Grants one protocol credit when a member checks out for the protocol level.
 *
 * @param int    $user_id  WP user ID
 * @param object $morder   PMPro MemberOrder — $morder->membership_id = level ID
 */
function excreet_294_on_purchase( int $user_id, $morder ): void {
    $level_id = (int) get_option( EX294_PRODUCT_OPT, 0 );
    if ( ! $level_id || ! is_object( $morder ) ) {
        return;
    }
    if ( (int) $morder->membership_id !== $level_id ) {
        return;
    }

    $current = (int) get_user_meta( $user_id, EX294_CREDIT_META, true );
    update_user_meta( $user_id, EX294_CREDIT_META, $current + 1 );
}

/* ════════════════════════════════════════════════════════════════════════════
   AJAX — PROTOCOL GENERATION
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_294_ajax_protocol(): void {
    check_ajax_referer( 'excreet_gen_protocol', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'code' => 'not_logged_in' ], 401 );
    }
    if ( function_exists( 'excreet_291_is_member' ) && ! excreet_291_is_member() ) {
        wp_send_json_error( [ 'code' => 'not_member' ], 403 );
    }

    $user_id  = get_current_user_id();
    $is_admin = current_user_can( 'manage_options' );
    $credits  = (int) get_user_meta( $user_id, EX294_CREDIT_META, true );

    if ( ! $is_admin && $credits < 1 ) {
        wp_send_json_error( [
            'code'         => 'no_credits',
            'checkout_url' => excreet_294_checkout_url(),
        ], 402 );
    }

    // ── Collect all structured intake fields ────────────────────────────────
    $current_concern   = sanitize_textarea_field( wp_unslash( $_POST['current_concern']   ?? '' ) );
    $symptoms_timeline = sanitize_textarea_field( wp_unslash( $_POST['symptoms_timeline'] ?? '' ) );
    $better_worse      = sanitize_textarea_field( wp_unslash( $_POST['better_worse']      ?? '' ) );
    $already_tried     = sanitize_textarea_field( wp_unslash( $_POST['already_tried']     ?? '' ) );
    $hoping_to_learn   = sanitize_textarea_field( wp_unslash( $_POST['hoping_to_learn']   ?? '' ) );

    // Build an enriched, structured concern from all fields
    $parts = [];
    if ( $current_concern )   { $parts[] = "CURRENT HEALTH CONCERN\n" . $current_concern; }
    if ( $symptoms_timeline ) { $parts[] = "SYMPTOMS & TIMELINE\n" . $symptoms_timeline; }
    if ( $better_worse )      { $parts[] = "WHAT MAKES IT BETTER OR WORSE\n" . $better_worse; }
    if ( $already_tried )     { $parts[] = "WHAT I'VE ALREADY TRIED\n" . $already_tried; }
    if ( $hoping_to_learn )   { $parts[] = "WHAT I'M HOPING TO LEARN\n" . $hoping_to_learn; }

    $full_concern = implode( "\n\n", $parts );
    if ( '' === $full_concern ) {
        wp_send_json_error( [ 'code' => 'empty_concern' ], 400 );
    }

    // ── Attachments (base64 JSON array) ─────────────────────────────────────
    $attachments     = [];
    $attachments_raw = wp_unslash( $_POST['attachments_json'] ?? '' );
    if ( ! empty( $attachments_raw ) ) {
        $decoded = json_decode( $attachments_raw, true );
        if ( is_array( $decoded ) ) {
            $attachments = array_slice( $decoded, 0, 3 );
        }
    }

    $intake    = excreet_294_get_intake_for_user( $user_id );
    $user      = wp_get_current_user();
    $member_id = $user->user_login ?: (string) $user_id;

    if ( ! defined( 'EXCREET_HERMES_API_KEY' ) ) {
        wp_send_json_error( [ 'code' => 'no_key' ], 500 );
    }

    $request_data = [
        'member_id'       => $member_id,
        'current_concern' => $full_concern,
        'intake_data'     => $intake,
    ];
    if ( ! empty( $attachments ) ) {
        $request_data['attachments'] = $attachments;
    }

    $body = wp_json_encode( $request_data );

    $response = wp_remote_post( EXCREET_HERMES_PROTOCOL_URL, [
        'timeout' => 90,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY,
        ],
        'body' => $body,
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [
            'code'    => 'api_error',
            'message' => 'Protocol generation is temporarily unavailable. Your credit has not been used. Please try again.',
        ], 502 );
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $data   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status !== 200 || ! isset( $data['protocol'] ) ) {
        wp_send_json_error( [
            'code'    => 'api_error',
            'message' => 'Protocol generation failed. Your credit has not been used. Please try again.',
        ], 502 );
    }

    // Deduct credit only on success, and only for non-admins
    $generated_at = $data['generated_at'] ?? gmdate( 'c' );
    if ( $is_admin ) {
        $credits_left = $credits; // admin: no deduction
    } else {
        $credits_left = max( 0, $credits - 1 );
        update_user_meta( $user_id, EX294_CREDIT_META, $credits_left );
    }

    // Prepend to protocol history (newest first, cap at 10)
    // Admin tests are tagged so they're identifiable in the dashboard
    $display_concern = $current_concern ?: $symptoms_timeline ?: wp_trim_words( $full_concern, 20, '…' );
    $concern_label   = $is_admin
        ? '[Admin Preview] ' . wp_trim_words( $display_concern, 18, '…' )
        : wp_trim_words( $display_concern, 20, '…' );

    $history_entry = [
        'id'           => wp_generate_uuid4(),
        'generated_at' => $generated_at,
        'concern'      => $concern_label,
        'protocol'     => $data['protocol'],
    ];
    $history = get_user_meta( $user_id, EX294_HISTORY_META, true );
    $history = is_array( $history ) ? $history : [];
    array_unshift( $history, $history_entry );
    $history = array_slice( $history, 0, 10 );
    update_user_meta( $user_id, EX294_HISTORY_META, $history );

    $html         = excreet_294_render_protocol( $data['protocol'], $generated_at );
    $history_html = excreet_294_render_history_item( $history_entry );

    // Email only for real member sessions — skip admin previews
    if ( ! $is_admin ) {
        excreet_294_send_protocol_email( $user_id, $data['protocol'], $generated_at, $current_concern );
    }

    wp_send_json_success( [
        'html'          => $html,
        'history_html'  => $history_html,
        'credits_left'  => $credits_left,
        'generated_at'  => $generated_at,
    ] );
}

/* ════════════════════════════════════════════════════════════════════════════
   PROTOCOL HTML RENDERER
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_294_render_protocol( array $p, string $generated_at ): string {
    $purple      = EX294_PURPLE;
    $purple_dark = EX294_PURPLE_DARK;
    $gold        = EX294_GOLD;
    $date        = gmdate( 'F j, Y', strtotime( $generated_at ) );

    $esc = 'esc_html';

    /* ── Helper: render a list ── */
    $list = function( array $items, string $marker = '•' ) use ( $purple, $esc ): string {
        if ( empty( $items ) ) { return ''; }
        $out = '<ul style="margin:0;padding:0;list-style:none;">';
        foreach ( $items as $item ) {
            $out .= '<li style="padding:7px 0 7px 22px;position:relative;border-bottom:1px solid #f0e8f8;font-size:15px;line-height:1.6;">'
                . '<span style="position:absolute;left:0;top:8px;color:' . $purple . ';font-weight:700;">' . esc_html( $marker ) . '</span>'
                . esc_html( $item )
                . '</li>';
        }
        $out .= '</ul>';
        return $out;
    };

    /* ── Helper: section block ── */
    $section = function( string $label, string $content, string $bg = '#fff' ) use ( $purple_dark ): string {
        return '<div style="background:' . $bg . ';padding:20px 24px;border-bottom:1px solid #ede4f5;">'
            . '<div style="font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:' . $purple_dark . ';margin-bottom:10px;">' . esc_html( $label ) . '</div>'
            . $content
            . '</div>';
    };

    ob_start();
    ?>
    <div id="excreet-protocol-doc" style="font-family:Georgia,'Times New Roman',serif;border-radius:14px;overflow:hidden;box-shadow:0 6px 32px rgba(61,16,96,.18);margin-top:32px;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,<?php echo $purple_dark; ?> 0%,<?php echo $purple; ?> 100%);padding:26px 28px;display:flex;align-items:flex-start;gap:18px;">
            <img src="https://excreet.com/wp-content/uploads/excreet-hero-logo.png" alt="Excreet"
                 style="width:60px;height:60px;border-radius:50%;flex-shrink:0;object-fit:cover;box-shadow:0 2px 14px rgba(0,0,0,.4);" />
            <div>
                <div style="color:<?php echo $gold; ?>;font-size:20px;font-weight:700;letter-spacing:.03em;margin-bottom:4px;font-family:Georgia,serif;text-shadow:0 1px 8px rgba(0,0,0,.35);">Excreet™</div>
                <div style="color:#fff;font-size:22px;font-weight:700;line-height:1.25;"><?php echo esc_html( $p['title'] ?? 'Healing Protocol' ); ?></div>
                <div style="color:rgba(255,255,255,.65);font-size:12px;margin-top:4px;">Generated <?php echo esc_html( $date ); ?> · Private &amp; Confidential</div>
            </div>
        </div>

        <!-- Vitality Read -->
        <div style="background:#F7F4FC;padding:20px 24px;border-bottom:1px solid #ede4f5;">
            <div style="font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:<?php echo $purple_dark; ?>;margin-bottom:10px;">Vitality Read</div>
            <p style="margin:0;font-size:16px;line-height:1.7;color:#2a1040;font-style:italic;"><?php echo esc_html( $p['vitality_read'] ?? '' ); ?></p>
        </div>

        <!-- Root Pattern -->
        <div style="background:#fff;padding:20px 24px;border-bottom:1px solid #ede4f5;border-left:4px solid <?php echo $purple; ?>;">
            <div style="font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:<?php echo $purple_dark; ?>;margin-bottom:10px;">Root Pattern</div>
            <p style="margin:0;font-size:15px;line-height:1.65;color:#333;"><?php echo esc_html( $p['root_pattern'] ?? '' ); ?></p>
        </div>

        <!-- Healing Approach -->
        <?php echo $section( 'Healing Approach', $list( (array) ( $p['healing_approach'] ?? [] ) ) ); ?>

        <!-- Dietary Protocol -->
        <?php echo $section( 'Dietary Protocol', $list( (array) ( $p['dietary_protocol'] ?? [] ), '→' ), '#fdfaff' ); ?>

        <!-- Supplement Stack -->
        <div style="background:#fff;padding:20px 24px;border-bottom:1px solid #ede4f5;">
            <div style="font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:<?php echo $purple_dark; ?>;margin-bottom:10px;">Supplement Stack</div>
            <?php
            $supplements = (array) ( $p['supplement_stack'] ?? [] );
            if ( ! empty( $supplements ) ) :
            ?>
            <ul style="margin:0;padding:0;list-style:none;">
                <?php foreach ( $supplements as $supp ) : ?>
                <li style="padding:10px 0;border-bottom:1px solid #f5eeff;display:flex;gap:12px;align-items:flex-start;">
                    <span style="background:<?php echo $purple; ?>;color:#fff;border-radius:50%;width:22px;height:22px;line-height:22px;text-align:center;font-size:11px;font-weight:700;flex-shrink:0;margin-top:1px;">✦</span>
                    <span style="font-size:15px;line-height:1.6;color:#222;"><?php echo esc_html( $supp ); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Lifestyle Shifts -->
        <?php echo $section( 'Lifestyle Shifts', $list( (array) ( $p['lifestyle_shifts'] ?? [] ), '◆' ), '#fdfaff' ); ?>

        <!-- Labs to Request -->
        <div style="background:#fff;padding:20px 24px;border-bottom:1px solid #ede4f5;">
            <div style="font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:<?php echo $purple_dark; ?>;margin-bottom:10px;">Labs to Request by Name</div>
            <?php
            $labs = (array) ( $p['labs_to_request'] ?? [] );
            if ( ! empty( $labs ) ) :
            ?>
            <ul style="margin:0;padding:0;list-style:none;">
                <?php foreach ( $labs as $lab ) : ?>
                <li style="padding:6px 0 6px 26px;position:relative;border-bottom:1px dashed #ede4f5;font-size:14px;color:#333;line-height:1.5;">
                    <span style="position:absolute;left:0;top:7px;color:<?php echo $purple; ?>;font-size:13px;">☐</span>
                    <?php echo esc_html( $lab ); ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Red Flags -->
        <div style="background:#fff9f9;padding:20px 24px;border-bottom:1px solid #ede4f5;border-left:4px solid #c0392b;">
            <div style="font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#c0392b;margin-bottom:10px;">⚠ Red Flags — Seek Care Promptly</div>
            <?php echo $list( (array) ( $p['red_flags'] ?? [] ), '!' ); ?>
        </div>

        <!-- Follow Up -->
        <?php echo $section( 'Follow-Up & Next Steps', '<p style="margin:0;font-size:15px;line-height:1.65;color:#333;">' . esc_html( $p['follow_up'] ?? '' ) . '</p>' ); ?>

        <!-- Disclaimer -->
        <div style="background:#F7F4FC;padding:16px 24px;font-size:12px;color:#777;line-height:1.65;font-style:italic;">
            <?php echo esc_html( $p['disclaimer'] ?? '' ); ?>
        </div>

        <!-- Print / Save -->
        <div style="background:#fafafa;padding:14px 24px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #ede4f5;">
            <button onclick="window.print();" style="background:none;border:1px solid <?php echo $purple; ?>;color:<?php echo $purple; ?>;padding:8px 20px;border-radius:20px;font-family:Georgia,serif;font-size:13px;cursor:pointer;">🖨 Print / Save PDF</button>
        </div>

    </div>
    <?php
    return (string) ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   PROTOCOL EMAIL DELIVERY
   ════════════════════════════════════════════════════════════════════════════ */

/**
 * Send the full protocol to the member's email address.
 * Failures are silent — the protocol is already stored; email is a bonus copy.
 */
function excreet_294_send_protocol_email( int $user_id, array $protocol, string $generated_at, string $concern ): void {

    $user = get_user_by( 'id', $user_id );
    if ( ! ( $user instanceof WP_User ) || ! is_email( $user->user_email ) ) {
        return;
    }

    $title   = $protocol['title'] ?? 'Your Healing Protocol';
    $subject = 'Your Healing Protocol: ' . $title;
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Excreet WHealth <info@excreet.com>',
    ];

    $body = excreet_294_render_protocol_email( $protocol, $generated_at, $concern );

    wp_mail( $user->user_email, $subject, $body, $headers );
}

/**
 * Render the protocol as an email-client-safe HTML document.
 * Uses table-based layout and inline styles — compatible with Gmail,
 * Outlook, Apple Mail, and all major clients.
 */
function excreet_294_render_protocol_email( array $p, string $generated_at, string $concern = '' ): string {

    $purple      = EX294_PURPLE;
    $purple_dark = EX294_PURPLE_DARK;
    $gold        = EX294_GOLD;
    $date        = gmdate( 'F j, Y', strtotime( $generated_at ) );
    $site_url    = esc_url( home_url( '/ask-the-healer/' ) );

    $title        = $p['title']        ?? 'Healing Protocol';
    $vitality     = $p['vitality_read']    ?? '';
    $root         = $p['root_pattern']     ?? '';
    $approach     = (array) ( $p['healing_approach'] ?? [] );
    $dietary      = (array) ( $p['dietary_protocol']  ?? [] );
    $supplements  = (array) ( $p['supplement_stack']  ?? [] );
    $lifestyle    = (array) ( $p['lifestyle_shifts']  ?? [] );
    $labs         = (array) ( $p['labs_to_request']   ?? [] );
    $red_flags    = (array) ( $p['red_flags']          ?? [] );
    $follow_up    = $p['follow_up']    ?? '';
    $disclaimer   = $p['disclaimer']   ?? '';

    /* ── Email-safe list builder ─────────────────────────────────────────── */
    $list = function ( array $items, string $marker = '•', string $color = '#444' ) use ( $purple ): string {
        if ( empty( $items ) ) { return ''; }
        $out = '<table width="100%" cellpadding="0" cellspacing="0" border="0">';
        foreach ( $items as $item ) {
            $out .= '<tr>'
                . '<td width="20" valign="top" style="font-size:15px;color:' . $purple . ';font-weight:700;padding:4px 8px 4px 0;line-height:1.6;">' . esc_html( $marker ) . '</td>'
                . '<td valign="top" style="font-size:15px;color:' . esc_attr( $color ) . ';line-height:1.65;padding:4px 0;border-bottom:1px solid #f2eafa;">' . esc_html( $item ) . '</td>'
                . '</tr>';
        }
        $out .= '</table>';
        return $out;
    };

    /* ── Section wrapper ─────────────────────────────────────────────────── */
    $section = function ( string $label, string $content, string $bg = '#ffffff' ) use ( $purple_dark ): string {
        return '<tr><td style="background:' . $bg . ';padding:20px 28px;border-bottom:1px solid #ede4f5;">'
            . '<div style="font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:' . $purple_dark . ';margin-bottom:10px;">' . esc_html( $label ) . '</div>'
            . $content
            . '</td></tr>';
    };

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo esc_html( $title ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f0ece8;font-family:Georgia,'Times New Roman',serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0ece8;min-width:320px;">
<tr><td align="center" style="padding:28px 16px 40px;">

<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;border-radius:14px;overflow:hidden;box-shadow:0 4px 32px rgba(61,16,96,.18);">

    <!-- ── HEADER ─────────────────────────────────────────────────────── -->
    <tr>
        <td style="background:<?php echo $purple_dark; ?>;padding:28px 32px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td width="68" valign="middle" style="padding-right:18px;">
                        <div style="background:<?php echo $gold; ?>;border-radius:50%;width:60px;height:60px;text-align:center;line-height:60px;font-size:30px;font-weight:900;color:<?php echo $purple_dark; ?>;font-family:Georgia,serif;">℮</div>
                    </td>
                    <td valign="middle">
                        <div style="color:<?php echo $gold; ?>;font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;margin-bottom:6px;">Excreet™ — Personalized Healing Protocol</div>
                        <div style="color:#ffffff;font-size:22px;font-weight:700;line-height:1.2;"><?php echo esc_html( $title ); ?></div>
                        <div style="color:rgba(255,255,255,.6);font-size:12px;margin-top:5px;">Generated <?php echo esc_html( $date ); ?> · Private &amp; Confidential</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- ── INTRO ──────────────────────────────────────────────────────── -->
    <tr>
        <td style="background:#f7f4fc;padding:16px 28px;border-bottom:1px solid #ede4f5;">
            <p style="margin:0;font-size:14px;color:#6b4f9a;line-height:1.6;">
                This is your personal Excreet Healing Protocol, built from your intake history and today's concern.
                It is saved in your account at <a href="<?php echo $site_url; ?>" style="color:<?php echo $purple; ?>;">excreet.com/ask-the-healer/</a>
                where you can read it, expand every section, and print it at any time.
            </p>
            <?php if ( $concern ) : ?>
            <p style="margin:10px 0 0;font-size:13px;color:#888;line-height:1.5;font-style:italic;">
                Today's concern: <?php echo esc_html( wp_trim_words( $concern, 30, '…' ) ); ?>
            </p>
            <?php endif; ?>
        </td>
    </tr>

    <!-- ── VITALITY READ ──────────────────────────────────────────────── -->
    <tr>
        <td style="background:#f7f4fc;padding:20px 28px;border-bottom:1px solid #ede4f5;border-left:4px solid <?php echo $purple; ?>;">
            <div style="font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:<?php echo $purple_dark; ?>;margin-bottom:10px;">Vitality Read</div>
            <p style="margin:0;font-size:16px;line-height:1.7;color:#2a1040;font-style:italic;"><?php echo esc_html( $vitality ); ?></p>
        </td>
    </tr>

    <!-- ── ROOT PATTERN ───────────────────────────────────────────────── -->
    <tr>
        <td style="background:#ffffff;padding:20px 28px;border-bottom:1px solid #ede4f5;border-left:4px solid <?php echo $gold; ?>;">
            <div style="font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:<?php echo $purple_dark; ?>;margin-bottom:10px;">Root Pattern</div>
            <p style="margin:0;font-size:15px;line-height:1.65;color:#333;"><?php echo esc_html( $root ); ?></p>
        </td>
    </tr>

    <!-- ── HEALING APPROACH ───────────────────────────────────────────── -->
    <?php echo $section( 'Healing Approach', $list( $approach ) ); ?>

    <!-- ── DIETARY PROTOCOL ───────────────────────────────────────────── -->
    <?php echo $section( 'Dietary Protocol', $list( $dietary, '→' ), '#fdfaff' ); ?>

    <!-- ── SUPPLEMENT STACK ───────────────────────────────────────────── -->
    <?php echo $section( 'Supplement Stack', $list( $supplements, '✦' ) ); ?>

    <!-- ── LIFESTYLE SHIFTS ───────────────────────────────────────────── -->
    <?php echo $section( 'Lifestyle Shifts', $list( $lifestyle, '◆' ), '#fdfaff' ); ?>

    <!-- ── LABS TO REQUEST ────────────────────────────────────────────── -->
    <?php echo $section( 'Labs to Request by Name', $list( $labs, '☐' ) ); ?>

    <!-- ── RED FLAGS ──────────────────────────────────────────────────── -->
    <tr>
        <td style="background:#fff9f9;padding:20px 28px;border-bottom:1px solid #ede4f5;border-left:4px solid #c0392b;">
            <div style="font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#c0392b;margin-bottom:10px;">⚠ Red Flags — Seek Care Promptly</div>
            <?php echo $list( $red_flags, '!', '#c0392b' ); ?>
        </td>
    </tr>

    <!-- ── FOLLOW-UP ──────────────────────────────────────────────────── -->
    <?php echo $section( 'Follow-Up & Next Steps', '<p style="margin:0;font-size:15px;line-height:1.65;color:#333;">' . esc_html( $follow_up ) . '</p>' ); ?>

    <!-- ── DISCLAIMER ─────────────────────────────────────────────────── -->
    <tr>
        <td style="background:#f7f4fc;padding:16px 28px;font-size:12px;color:#888;line-height:1.7;font-style:italic;border-bottom:1px solid #ede4f5;">
            <?php echo esc_html( $disclaimer ); ?>
        </td>
    </tr>

    <!-- ── FOOTER ─────────────────────────────────────────────────────── -->
    <tr>
        <td style="background:<?php echo $purple_dark; ?>;padding:20px 28px;border-radius:0 0 14px 14px;text-align:center;">
            <p style="margin:0 0 10px;color:rgba(255,255,255,.6);font-size:12px;line-height:1.6;">
                This protocol is saved in your Excreet member account.
            </p>
            <a href="<?php echo $site_url; ?>"
               style="display:inline-block;background:<?php echo $gold; ?>;color:<?php echo $purple_dark; ?>;font-weight:700;font-size:13px;padding:10px 28px;border-radius:20px;text-decoration:none;font-family:Georgia,serif;letter-spacing:.04em;">
                View in Your Account →
            </a>
            <p style="margin:16px 0 0;color:rgba(255,255,255,.4);font-size:11px;">
                Excreet WHealth · <a href="<?php echo esc_url( home_url() ); ?>" style="color:rgba(255,255,255,.5);">excreet.com</a>
            </p>
        </td>
    </tr>

</table>

</td></tr>
</table>

</body>
</html>
    <?php
    return (string) ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   PROTOCOL HISTORY RENDERERS
   ════════════════════════════════════════════════════════════════════════════ */

/**
 * Render one collapsed history accordion item.
 * The full protocol is embedded but hidden until the member clicks to expand.
 */
function excreet_294_render_history_item( array $entry ): string {
    $id           = sanitize_key( $entry['id'] ?? uniqid( 'hist_', true ) );
    $generated_at = $entry['generated_at'] ?? gmdate( 'c' );
    $concern      = esc_html( $entry['concern'] ?? '' );
    $protocol     = is_array( $entry['protocol'] ) ? $entry['protocol'] : [];
    $title        = esc_html( $protocol['title'] ?? 'Healing Protocol' );
    $date         = gmdate( 'F j, Y', strtotime( $generated_at ) );
    $purple       = EX294_PURPLE;
    $purple_dark  = EX294_PURPLE_DARK;

    $protocol_html = excreet_294_render_protocol( $protocol, $generated_at );

    ob_start();
    ?>
    <div class="excreet-history-item" id="excreet-hist-<?php echo esc_attr( $id ); ?>" style="border-bottom:1px solid #ede4f5;">

        <div class="excreet-hist-trigger"
             onclick="excreetHistToggle('<?php echo esc_js( $id ); ?>')"
             style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:16px 4px;cursor:pointer;user-select:none;">
            <div style="flex:1;min-width:0;">
                <div style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#aaa;margin-bottom:3px;"><?php echo esc_html( $date ); ?></div>
                <div style="font-size:16px;font-weight:700;color:<?php echo $purple_dark; ?>;line-height:1.25;margin-bottom:4px;"><?php echo $title; ?></div>
                <?php if ( $concern ) : ?>
                <div style="font-size:13px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Concern: <?php echo $concern; ?></div>
                <?php endif; ?>
            </div>
            <span id="excreet-caret-<?php echo esc_attr( $id ); ?>"
                  style="color:<?php echo $purple; ?>;font-size:18px;font-weight:700;flex-shrink:0;padding-top:4px;transition:transform .2s;">▼</span>
        </div>

        <div id="excreet-hist-body-<?php echo esc_attr( $id ); ?>"
             style="display:none;padding-bottom:20px;">
            <?php echo $protocol_html; ?>
        </div>

    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Render the full "Your Past Protocols" history section.
 * Called server-side when the shortcode loads and history already exists.
 */
function excreet_294_render_history_section( array $history ): string {
    $purple      = EX294_PURPLE;
    $purple_dark = EX294_PURPLE_DARK;
    $count       = count( $history );

    ob_start();
    ?>
    <div style="margin-top:20px;padding:20px 0 0;border-top:2px solid #ede4f5;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
            <div style="font-size:20px;font-weight:700;color:<?php echo $purple_dark; ?>;font-family:Georgia,serif;">Your Past Protocols</div>
            <div style="font-size:12px;color:#aaa;font-style:italic;"><?php echo $count; ?> protocol<?php echo $count !== 1 ? 's' : ''; ?> on file</div>
        </div>
        <p style="font-size:13px;color:#888;margin:0 0 16px;line-height:1.5;">Each protocol is yours to keep. Click any entry to read the full document.</p>
        <div class="excreet-history-list">
            <?php foreach ( $history as $entry ) : ?>
                <?php echo excreet_294_render_history_item( $entry ); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   ADMIN DASHBOARD
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_294_admin_menu(): void {
    add_menu_page(
        'Healing Protocols',
        'Protocols',
        'manage_options',
        'excreet-protocols',
        'excreet_294_admin_page',
        'dashicons-heart',
        30
    );
}

/**
 * Grant one protocol credit to a user. Admin-only AJAX action.
 */
function excreet_294_ajax_grant_credit(): void {
    check_ajax_referer( 'excreet_294_admin', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $user_id = (int) ( $_POST['user_id'] ?? 0 );
    if ( ! $user_id ) {
        wp_send_json_error( 'Missing user_id', 400 );
    }

    $credits = (int) get_user_meta( $user_id, EX294_CREDIT_META, true );
    $credits++;
    update_user_meta( $user_id, EX294_CREDIT_META, $credits );

    wp_send_json_success( [ 'credits' => $credits ] );
}

/**
 * Render the Protocols admin dashboard page.
 */
function excreet_294_admin_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $purple      = EX294_PURPLE;
    $purple_dark = EX294_PURPLE_DARK;
    $gold        = EX294_GOLD;
    $nonce       = wp_create_nonce( 'excreet_294_admin' );
    $ajax_url    = admin_url( 'admin-ajax.php' );

    // ── Gather data ───────────────────────────────────────────────────────────
    $members_with_history = get_users( [
        'meta_key'   => EX294_HISTORY_META,
        'meta_value' => '',
        'compare'    => '!=',
        'number'     => 200,
        'orderby'    => 'registered',
        'order'      => 'DESC',
    ] );

    $rows          = [];
    $total_protos  = 0;
    $total_credits = 0;

    foreach ( $members_with_history as $user ) {
        $history = get_user_meta( $user->ID, EX294_HISTORY_META, true );
        $history = is_array( $history ) ? $history : [];
        $credits = (int) get_user_meta( $user->ID, EX294_CREDIT_META, true );

        if ( empty( $history ) ) { continue; }

        usort( $history, fn( $a, $b ) => strcmp( $b['generated_at'] ?? '', $a['generated_at'] ?? '' ) );

        $last        = $history[0];
        $total_protos  += count( $history );
        $total_credits += $credits;

        $rows[] = [
            'user'         => $user,
            'history'      => $history,
            'count'        => count( $history ),
            'last_date'    => $last['generated_at'] ?? '',
            'last_concern' => $last['concern'] ?? '',
            'last_title'   => $last['protocol']['title'] ?? 'Protocol',
            'credits'      => $credits,
        ];
    }

    // Sort rows by most recent protocol
    usort( $rows, fn( $a, $b ) => strcmp( $b['last_date'], $a['last_date'] ) );

    $also_have_credits = get_users( [
        'meta_key'     => EX294_CREDIT_META,
        'meta_value'   => '0',
        'meta_compare' => '>',
        'number'       => 200,
    ] );
    $members_with_credits = count( $also_have_credits );

    ?>
    <div class="wrap" style="font-family:Georgia,'Times New Roman',serif;">

        <!-- ── Header ── -->
        <div style="background:<?php echo $purple_dark; ?>;border-radius:10px;padding:20px 28px;margin:12px 0 24px;display:flex;align-items:center;gap:20px;">
            <div style="background:<?php echo $gold; ?>;border-radius:50%;width:48px;height:48px;display:flex;align-items:center;justify-content:center;font-size:26px;color:<?php echo $purple_dark; ?>;font-weight:900;flex-shrink:0;">℮</div>
            <div>
                <div style="color:<?php echo $gold; ?>;font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;margin-bottom:3px;">Excreet WHealth</div>
                <div style="color:#fff;font-size:22px;font-weight:700;">Healing Protocol Dashboard</div>
            </div>
        </div>

        <!-- ── Stats ── -->
        <div style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap;">
            <?php
            $stats = [
                [ 'Members with protocols', count( $rows ) ],
                [ 'Total protocols generated', $total_protos ],
                [ 'Members with credits remaining', $members_with_credits ],
            ];
            foreach ( $stats as [ $label, $val ] ) :
            ?>
            <div style="background:#fff;border:1px solid #e0d4f0;border-radius:10px;padding:16px 24px;min-width:180px;flex:1;box-shadow:0 2px 8px rgba(61,16,96,.07);">
                <div style="font-size:32px;font-weight:700;color:<?php echo $purple; ?>;"><?php echo (int) $val; ?></div>
                <div style="font-size:12px;color:#888;margin-top:4px;"><?php echo esc_html( $label ); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Member table ── -->
        <?php if ( empty( $rows ) ) : ?>
            <div style="background:#f9f6ff;border:1px solid #ede4f5;border-radius:10px;padding:32px;text-align:center;color:#888;">
                No protocols generated yet. They will appear here after the first member session.
            </div>
        <?php else : ?>
        <table class="widefat striped" style="border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(61,16,96,.08);">
            <thead style="background:<?php echo $purple; ?>;">
                <tr>
                    <?php
                    foreach ( [ 'Member', 'Protocols', 'Last Generated', 'Last Protocol', 'Credits', 'Actions' ] as $h ) :
                    ?>
                    <th style="color:#fff;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:12px 14px;background:<?php echo $purple; ?>;">
                        <?php echo esc_html( $h ); ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody id="excreet-proto-admin-tbody">
            <?php foreach ( $rows as $row ) :
                $uid      = $row['user']->ID;
                $name     = esc_html( $row['user']->display_name ?: $row['user']->user_login );
                $email    = esc_html( $row['user']->user_email );
                $count    = $row['count'];
                $credits  = $row['credits'];
                $last_date = $row['last_date'] ? gmdate( 'M j, Y', strtotime( $row['last_date'] ) ) : '—';
            ?>
                <!-- Summary row -->
                <tr id="excreet-row-<?php echo $uid; ?>">
                    <td style="padding:12px 14px;">
                        <div style="font-weight:700;color:<?php echo $purple_dark; ?>;"><?php echo $name; ?></div>
                        <div style="font-size:12px;color:#888;"><?php echo $email; ?></div>
                    </td>
                    <td style="padding:12px 14px;font-weight:700;font-size:18px;color:<?php echo $purple; ?>;"><?php echo $count; ?></td>
                    <td style="padding:12px 14px;font-size:13px;color:#555;"><?php echo $last_date; ?></td>
                    <td style="padding:12px 14px;font-size:13px;color:#333;max-width:240px;">
                        <div style="font-weight:600;color:<?php echo $purple_dark; ?>;margin-bottom:2px;"><?php echo esc_html( $row['last_title'] ); ?></div>
                        <div style="font-size:12px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;"><?php echo esc_html( $row['last_concern'] ); ?></div>
                    </td>
                    <td style="padding:12px 14px;">
                        <span id="excreet-credits-<?php echo $uid; ?>"
                              style="display:inline-block;background:<?php echo $credits > 0 ? '#f0faf0' : '#fff8f8'; ?>;border:1px solid <?php echo $credits > 0 ? '#b2dfdb' : '#ffcdd2'; ?>;border-radius:20px;padding:3px 12px;font-size:13px;font-weight:700;color:<?php echo $credits > 0 ? '#2e7d32' : '#c62828'; ?>;">
                            <?php echo $credits; ?>
                        </span>
                        <button class="excreet-grant-btn button button-small"
                                data-uid="<?php echo $uid; ?>"
                                style="margin-left:8px;font-size:11px;"
                                title="Grant 1 credit">+ Credit</button>
                    </td>
                    <td style="padding:12px 14px;">
                        <button class="excreet-expand-btn button button-secondary button-small"
                                data-uid="<?php echo $uid; ?>"
                                onclick="excreetAdminToggle(<?php echo $uid; ?>)">
                            View Protocols ▼
                        </button>
                    </td>
                </tr>

                <!-- Expandable detail row -->
                <tr id="excreet-detail-<?php echo $uid; ?>" style="display:none;">
                    <td colspan="6" style="padding:0 0 8px 32px;background:#faf7ff;">
                        <table style="width:100%;border-collapse:collapse;margin:8px 0;">
                            <thead>
                                <tr>
                                    <th style="text-align:left;font-size:11px;color:#aaa;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:6px 12px;width:130px;">Date</th>
                                    <th style="text-align:left;font-size:11px;color:#aaa;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:6px 12px;">Protocol Title</th>
                                    <th style="text-align:left;font-size:11px;color:#aaa;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:6px 12px;">Concern</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $row['history'] as $entry ) :
                                $entry_date  = $entry['generated_at'] ? gmdate( 'M j, Y · g:i a', strtotime( $entry['generated_at'] ) ) : '—';
                                $entry_title = $entry['protocol']['title'] ?? 'Protocol';
                                $entry_concern = $entry['concern'] ?? '';
                            ?>
                                <tr style="border-top:1px solid #ede4f5;">
                                    <td style="padding:8px 12px;font-size:12px;color:#888;white-space:nowrap;"><?php echo esc_html( $entry_date ); ?></td>
                                    <td style="padding:8px 12px;font-size:13px;font-weight:600;color:<?php echo $purple_dark; ?>;"><?php echo esc_html( $entry_title ); ?></td>
                                    <td style="padding:8px 12px;font-size:12px;color:#555;"><?php echo esc_html( $entry_concern ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>

            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    </div>

    <script>
    function excreetAdminToggle(uid) {
        var row = document.getElementById('excreet-detail-' + uid);
        var btn = document.querySelector('[data-uid="' + uid + '"].excreet-expand-btn');
        if (!row) { return; }
        var open = row.style.display !== 'none';
        row.style.display = open ? 'none' : 'table-row';
        if (btn) { btn.textContent = open ? 'View Protocols ▼' : 'Collapse ▲'; }
    }

    document.querySelectorAll('.excreet-grant-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var uid   = btn.dataset.uid;
            var nonce = <?php echo wp_json_encode( $nonce ); ?>;
            btn.disabled = true;
            btn.textContent = '…';

            var fd = new FormData();
            fd.append('action',  'excreet_294_grant_credit');
            fd.append('nonce',   nonce);
            fd.append('user_id', uid);

            fetch(<?php echo wp_json_encode( $ajax_url ); ?>, {
                method: 'POST', body: fd, credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var badge = document.getElementById('excreet-credits-' + uid);
                    if (badge) {
                        var c = data.data.credits;
                        badge.textContent = c;
                        badge.style.background = c > 0 ? '#f0faf0' : '#fff8f8';
                        badge.style.borderColor = c > 0 ? '#b2dfdb' : '#ffcdd2';
                        badge.style.color       = c > 0 ? '#2e7d32' : '#c62828';
                    }
                }
                btn.disabled = false;
                btn.textContent = '+ Credit';
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = '+ Credit';
            });
        });
    });
    </script>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   SHORTCODE  [excreet_healing_protocol]
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_294_shortcode(): string {
    if ( ! is_user_logged_in() ) {
        return '';
    }
    if ( function_exists( 'excreet_291_is_member' ) && ! excreet_291_is_member() ) {
        return '';
    }

    $user_id      = get_current_user_id();
    $credits      = (int) get_user_meta( $user_id, EX294_CREDIT_META, true );
    $intake       = excreet_294_get_intake_for_user( $user_id );
    $has_intake   = ! empty( $intake['symptoms'] ) || ! empty( $intake['age'] ) || ! empty( $intake['concerns'] );
    $checkout_url = excreet_294_checkout_url();

    $nonce    = wp_create_nonce( 'excreet_gen_protocol' );
    $ajax_url = admin_url( 'admin-ajax.php' );
    $purple      = EX294_PURPLE;
    $purple_dark = EX294_PURPLE_DARK;
    $gold        = EX294_GOLD;

    // Detect post-Stripe-redirect states
    $just_purchased     = isset( $_GET['protocol_purchased'] ) && '1' === sanitize_key( $_GET['protocol_purchased'] );
    $purchase_cancelled = isset( $_GET['protocol_cancelled'] )  && '1' === sanitize_key( $_GET['protocol_cancelled']  );

    ob_start();
    ?>
    <style id="excreet-294-css">
    #excreet-protocol-wrap {
        font-family: Georgia, 'Times New Roman', serif;
        max-width: 820px;
        margin: 32px auto 60px;
    }
    #excreet-protocol-card {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 24px rgba(61,16,96,.12);
    }
    #excreet-protocol-card-header {
        background: linear-gradient(135deg, <?php echo $purple_dark; ?> 0%, <?php echo $purple; ?> 100%);
        padding: 22px 28px;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    #excreet-protocol-body {
        background: #fff;
        padding: 24px 28px;
    }
    .excreet-intake-field {
        width: 100%;
        min-height: 80px;
        padding: 12px 14px;
        border: 1px solid #c9b0e0;
        border-radius: 8px;
        font-family: Georgia, serif;
        font-size: 15px;
        color: #222;
        resize: vertical;
        box-sizing: border-box;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
        margin-bottom: 18px;
    }
    .excreet-intake-field:focus {
        border-color: <?php echo $purple; ?>;
        box-shadow: 0 0 0 2px rgba(107,47,160,.12);
    }
    #excreet-concern-textarea { min-height: 110px; }
    .excreet-intake-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: <?php echo $purple_dark; ?>;
        letter-spacing: .12em;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    .excreet-proto-btn {
        display: inline-block;
        background: linear-gradient(135deg, <?php echo $purple_dark; ?>, <?php echo $purple; ?>);
        color: #fff;
        border: none;
        padding: 12px 32px;
        border-radius: 24px;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        cursor: pointer;
        font-family: Georgia, serif;
        text-decoration: none;
        transition: opacity .2s;
    }
    .excreet-proto-btn:disabled { opacity: .45; cursor: not-allowed; }
    .excreet-proto-btn-secondary {
        display: inline-block;
        border: 2px solid <?php echo $purple; ?>;
        color: <?php echo $purple; ?>;
        background: none;
        padding: 11px 28px;
        border-radius: 24px;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        cursor: pointer;
        font-family: Georgia, serif;
        text-decoration: none;
    }
    #excreet-proto-status {
        margin-top: 14px;
        font-size: 13px;
        font-style: italic;
        color: <?php echo $purple; ?>;
        display: none;
    }
    #excreet-proto-error {
        display: none;
        background: #fdf0f0;
        color: #c0392b;
        padding: 10px 14px;
        border-radius: 6px;
        font-size: 13px;
        margin-top: 10px;
    }
    </style>

    <div id="excreet-protocol-wrap">
        <div id="excreet-protocol-card">

            <!-- Header -->
            <div id="excreet-protocol-card-header">
                <img src="https://excreet.com/wp-content/uploads/excreet-hero-logo.png" alt="Excreet"
                     style="width:62px;height:62px;border-radius:50%;flex-shrink:0;object-fit:cover;box-shadow:0 2px 14px rgba(0,0,0,.4);" />
                <div>
                    <div style="color:<?php echo $gold; ?>;font-size:22px;font-weight:700;letter-spacing:.03em;margin-bottom:2px;font-family:Georgia,serif;text-shadow:0 1px 8px rgba(0,0,0,.35);">Excreet™</div>
                    <div style="color:#fff;font-size:20px;font-weight:700;line-height:1.2;">Generate Your Healing Protocol</div>
                    <div style="color:rgba(255,255,255,.75);font-size:12px;margin-top:3px;">Built from your intake history + today's concern · One session · $29</div>
                </div>
            </div>

            <!-- Body -->
            <div id="excreet-protocol-body">


                <?php if ( $just_purchased ) : ?>
                <div style="background:#f0faf4;border:1px solid #b7dfca;border-radius:8px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:flex-start;gap:12px;">
                    <span style="font-size:20px;flex-shrink:0;line-height:1.2;color:#1a6b3a;">✓</span>
                    <div>
                        <strong style="color:#1a6b3a;font-size:15px;">Payment received — your protocol session is ready.</strong>
                        <p style="margin:4px 0 0;font-size:13px;color:#2a7a45;line-height:1.5;">Complete your intake below to generate your personalized Healing Protocol.</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $purchase_cancelled ) : ?>
                <div style="background:#fff8f0;border:1px solid #e6c47a;border-radius:8px;padding:14px 20px;margin-bottom:20px;font-size:14px;color:#7a5a00;">
                    Checkout was not completed. No charge was made.
                </div>
                <?php endif; ?>

                <?php if ( ! $has_intake ) : ?>
                <div style="background:#fffbf0;border:1px solid #e6c84a;border-radius:8px;padding:14px 20px;margin-bottom:20px;font-size:14px;color:#6b4f00;">
                    <strong>Intake data not yet on file.</strong> Complete the Member Intake Form first — your health history is what makes this protocol personal.
                    <br><a href="<?php echo esc_url( home_url( '/member-intake-form/' ) ); ?>" style="color:<?php echo $purple; ?>;font-weight:700;">Complete intake form →</a>
                </div>
                <?php endif; ?>

                <p style="font-size:15px;line-height:1.7;color:#444;margin-top:0;">
                    Complete the fields below for your personalized Healing Protocol. The more detail you provide, the more precise your protocol.
                    <?php if ( $has_intake ) : ?>Your intake history will be combined automatically.<?php endif; ?>
                </p>

                <!-- Field 1: Current concern (required) -->
                <label for="excreet-concern-textarea" class="excreet-intake-label">
                    What are you experiencing right now? <span style="color:#c0392b;">*</span>
                </label>
                <textarea id="excreet-concern-textarea" class="excreet-intake-field"
                    placeholder="Your primary health concern — symptoms, duration, intensity…"
                    <?php echo ( ! $has_intake ) ? 'disabled' : ''; ?>></textarea>

                <!-- Field 2: Symptoms & timeline -->
                <label for="excreet-symptoms-timeline" class="excreet-intake-label">Symptoms &amp; Timeline</label>
                <textarea id="excreet-symptoms-timeline" class="excreet-intake-field"
                    placeholder="When did this start? How have symptoms changed? Any patterns you've noticed?"
                    <?php echo ( ! $has_intake ) ? 'disabled' : ''; ?>></textarea>

                <!-- Field 3: Better / worse -->
                <label for="excreet-better-worse" class="excreet-intake-label">What Makes It Better or Worse?</label>
                <textarea id="excreet-better-worse" class="excreet-intake-field"
                    placeholder="Foods, activities, time of day, stress, sleep, position…"
                    <?php echo ( ! $has_intake ) ? 'disabled' : ''; ?>></textarea>

                <!-- Field 4: Already tried -->
                <label for="excreet-already-tried" class="excreet-intake-label">What Have You Already Tried?</label>
                <textarea id="excreet-already-tried" class="excreet-intake-field"
                    placeholder="Treatments, supplements, dietary changes, medications, remedies…"
                    <?php echo ( ! $has_intake ) ? 'disabled' : ''; ?>></textarea>

                <!-- Field 5: Hoping to learn -->
                <label for="excreet-hoping-learn" class="excreet-intake-label">What Are You Hoping to Learn?</label>
                <textarea id="excreet-hoping-learn" class="excreet-intake-field"
                    placeholder="Root cause? Next steps? Specific guidance on a treatment? What outcome matters most?"
                    <?php echo ( ! $has_intake ) ? 'disabled' : ''; ?>></textarea>

                <!-- File attachments -->
                <div style="margin-bottom:22px;">
                    <div class="excreet-intake-label" style="margin-bottom:6px;">Attach Files <span style="font-weight:400;letter-spacing:0;text-transform:none;color:#999;">(optional)</span></div>
                    <p style="font-size:13px;color:#777;margin:0 0 10px;line-height:1.5;">Lab results, blood work, imaging reports, or health documents. Up to 3 files · 10 MB each · PDF, JPG, PNG, WebP.</p>
                    <input type="file" id="excreet-proto-file-input"
                           accept=".pdf,.jpg,.jpeg,.png,.webp"
                           multiple style="display:none;"
                           <?php echo ( ! $has_intake ) ? 'disabled' : ''; ?>>
                    <label for="excreet-proto-file-input"
                           id="excreet-proto-attach-label"
                           style="display:inline-flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;font-weight:700;color:<?php echo $purple; ?>;border:1.5px solid <?php echo $purple; ?>;padding:7px 18px;border-radius:20px;transition:background .15s;user-select:none;<?php echo ( ! $has_intake ) ? 'opacity:.4;pointer-events:none;' : ''; ?>">
                        📎 Attach Lab Results / Files
                    </label>
                    <div id="excreet-proto-chips" style="display:flex;flex-wrap:wrap;gap:7px;margin-top:10px;"></div>
                    <div id="excreet-proto-file-error" style="display:none;font-size:12px;color:#c0392b;margin-top:6px;"></div>
                </div>

                <!-- Action row -->
                <?php if ( $credits > 0 ) : ?>
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                        <button id="excreet-gen-btn" class="excreet-proto-btn" type="button" <?php echo ( ! $has_intake ) ? 'disabled' : ''; ?>>
                            Generate My Protocol →
                        </button>
                        <span id="excreet-credits-display" style="font-size:13px;color:#888;"><?php echo $credits; ?> protocol session<?php echo $credits !== 1 ? 's' : ''; ?> available</span>
                    </div>
                <?php else : ?>
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                        <button id="excreet-buy-btn" class="excreet-proto-btn" type="button">
                            Purchase Protocol · $29 →
                        </button>
                        <span style="font-size:13px;color:#888;line-height:1.4;">One session · replaces a $450 consult · yours to keep</span>
                    </div>
                <?php endif; ?>

                <div id="excreet-proto-status">⟳ &nbsp;Your healing guide is building your protocol — this takes 30–60 seconds…</div>
                <div id="excreet-checkout-status" style="display:none;margin-top:14px;font-size:13px;font-style:italic;color:<?php echo $purple; ?>;">⟳ &nbsp;Opening secure checkout…</div>
                <div id="excreet-proto-error"></div>

            </div><!-- /#excreet-protocol-body -->
        </div><!-- /#excreet-protocol-card -->

        <!-- Protocol output renders here -->
        <div id="excreet-protocol-output"></div>

        <!-- Protocol history -->
        <?php
        $history = get_user_meta( $user_id, EX294_HISTORY_META, true );
        $history = is_array( $history ) ? $history : [];
        ?>
        <div id="excreet-history-wrap">
            <?php if ( ! empty( $history ) ) : ?>
                <?php echo excreet_294_render_history_section( $history ); ?>
            <?php endif; ?>
        </div>

    </div><!-- /#excreet-protocol-wrap -->

    <script>
    /* ── History accordion toggle ── */
    function excreetHistToggle(id) {
        var body  = document.getElementById('excreet-hist-body-'  + id);
        var caret = document.getElementById('excreet-caret-' + id);
        if (!body) { return; }
        var isOpen = body.style.display !== 'none';
        body.style.display = isOpen ? 'none' : 'block';
        if (caret) { caret.textContent = isOpen ? '▼' : '▲'; }
    }

    (function () {
        var ajaxUrl    = <?php echo wp_json_encode( $ajax_url ); ?>;
        var nonce      = <?php echo wp_json_encode( $nonce ); ?>;
        var purpleDark = <?php echo wp_json_encode( EX294_PURPLE_DARK ); ?>;

        var genBtn      = document.getElementById('excreet-gen-btn');
        var buyBtn      = document.getElementById('excreet-buy-btn');
        var allFields   = document.querySelectorAll('.excreet-intake-field');
        var mainField   = document.getElementById('excreet-concern-textarea');
        var status      = document.getElementById('excreet-proto-status');
        var chkStatus   = document.getElementById('excreet-checkout-status');
        var errorEl     = document.getElementById('excreet-proto-error');
        var output      = document.getElementById('excreet-protocol-output');
        var histWrap    = document.getElementById('excreet-history-wrap');

        /* ── File attachment handling ──────────────────────────────────────── */
        var fileInput   = document.getElementById('excreet-proto-file-input');
        var chipsEl     = document.getElementById('excreet-proto-chips');
        var fileErrEl   = document.getElementById('excreet-proto-file-error');
        var attachedFiles = []; // [{ name, mime_type, data }]

        var MAX_FILES    = 3;
        var MAX_BYTES    = 10 * 1024 * 1024;
        var ALLOWED_MIME = ['application/pdf','image/jpeg','image/png','image/webp','image/gif'];

        function renderChips() {
            if (!chipsEl) { return; }
            chipsEl.innerHTML = '';
            attachedFiles.forEach(function (f, i) {
                var chip = document.createElement('div');
                chip.style.cssText = 'display:inline-flex;align-items:center;gap:6px;background:#f5eeff;border:1px solid #c9b0e0;border-radius:20px;padding:4px 12px;font-size:12px;color:#3d1060;max-width:220px;';
                var lbl = document.createElement('span');
                lbl.textContent = f.name.length > 26 ? f.name.slice(0,24) + '…' : f.name;
                lbl.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
                var rm = document.createElement('button');
                rm.type = 'button'; rm.textContent = '×';
                rm.style.cssText = 'background:none;border:none;cursor:pointer;color:#6b2fa0;font-size:16px;line-height:1;padding:0;flex-shrink:0;';
                rm.onclick = function () { attachedFiles.splice(i, 1); renderChips(); };
                chip.appendChild(lbl); chip.appendChild(rm);
                chipsEl.appendChild(chip);
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (fileErrEl) { fileErrEl.style.display = 'none'; }
                var files = Array.from(this.files || []);
                if (attachedFiles.length + files.length > MAX_FILES) {
                    if (fileErrEl) { fileErrEl.textContent = 'Maximum ' + MAX_FILES + ' files.'; fileErrEl.style.display = 'block'; }
                    this.value = ''; return;
                }
                files.forEach(function (file) {
                    if (!ALLOWED_MIME.includes(file.type)) {
                        if (fileErrEl) { fileErrEl.textContent = '"' + file.name + '" is not a supported type.'; fileErrEl.style.display = 'block'; }
                        return;
                    }
                    if (file.size > MAX_BYTES) {
                        if (fileErrEl) { fileErrEl.textContent = '"' + file.name + '" exceeds 10 MB.'; fileErrEl.style.display = 'block'; }
                        return;
                    }
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        var res = e.target.result, comma = res.indexOf(',');
                        attachedFiles.push({ name: file.name, mime_type: file.type, data: comma >= 0 ? res.slice(comma + 1) : res });
                        renderChips();
                    };
                    reader.readAsDataURL(file);
                });
                this.value = '';
            });
        }

        /* ── Helpers ───────────────────────────────────────────────────────── */
        function getFieldVal(id) {
            var el = document.getElementById(id);
            return el ? el.value.trim() : '';
        }

        function disableForm(disabled) {
            allFields.forEach(function (t) { t.disabled = disabled; });
            if (fileInput) { fileInput.disabled = disabled; }
        }

        /* ── Protocol generation ───────────────────────────────────────────── */
        if (genBtn) {
            genBtn.addEventListener('click', function () {
                var concern = mainField ? mainField.value.trim() : '';
                if (!concern) {
                    errorEl.textContent = 'Please describe your current health concern before generating.';
                    errorEl.style.display = 'block';
                    if (mainField) { mainField.focus(); }
                    return;
                }

                errorEl.style.display = 'none';
                genBtn.disabled = true;
                disableForm(true);
                status.style.display = 'block';
                output.innerHTML = '';

                var fd = new FormData();
                fd.append('action',           'excreet_gen_protocol');
                fd.append('nonce',            nonce);
                fd.append('current_concern',  concern);
                fd.append('symptoms_timeline', getFieldVal('excreet-symptoms-timeline'));
                fd.append('better_worse',      getFieldVal('excreet-better-worse'));
                fd.append('already_tried',     getFieldVal('excreet-already-tried'));
                fd.append('hoping_to_learn',   getFieldVal('excreet-hoping-learn'));
                if (attachedFiles.length > 0) {
                    fd.append('attachments_json', JSON.stringify(attachedFiles));
                }

                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    status.style.display = 'none';
                    if (!data.success) {
                        var code = data.data && data.data.code;
                        errorEl.textContent = code === 'no_credits'
                            ? 'No protocol sessions remaining. Purchase one to continue.'
                            : ((data.data && data.data.message) || 'Something went wrong. Please try again.');
                        errorEl.style.display = 'block';
                        genBtn.disabled = false;
                        disableForm(false);
                        return;
                    }

                    output.innerHTML = data.data.html;
                    output.scrollIntoView({ behavior: 'smooth', block: 'start' });

                    if (data.data.credits_left === 0) {
                        var cd = document.getElementById('excreet-credits-display');
                        if (cd) { cd.textContent = '0 protocol sessions remaining'; }
                        genBtn.disabled = true;
                    }

                    if (data.data.history_html && histWrap) {
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
                    status.style.display = 'none';
                    errorEl.textContent = 'Connection error. Please check your connection and try again.';
                    errorEl.style.display = 'block';
                    genBtn.disabled = false;
                    disableForm(false);
                });
            });
        }

        /* ── Stripe checkout (purchase) ────────────────────────────────────── */
        if (buyBtn) {
            buyBtn.addEventListener('click', function () {
                errorEl.style.display = 'none';
                buyBtn.disabled = true;
                if (chkStatus) { chkStatus.style.display = 'block'; }

                var fd = new FormData();
                fd.append('action', 'excreet_294_stripe_checkout');
                fd.append('nonce',  nonce);

                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (chkStatus) { chkStatus.style.display = 'none'; }
                    if (!data.success) {
                        errorEl.textContent = (data.data && data.data.message) || 'Checkout unavailable. Please try again.';
                        errorEl.style.display = 'block';
                        buyBtn.disabled = false;
                        return;
                    }
                    window.location.href = data.data.checkout_url;
                })
                .catch(function () {
                    if (chkStatus) { chkStatus.style.display = 'none'; }
                    errorEl.textContent = 'Connection error. Please try again.';
                    errorEl.style.display = 'block';
                    buyBtn.disabled = false;
                });
            });
        }

    })();
    </script>
    <?php
    return (string) ob_get_clean();
}
