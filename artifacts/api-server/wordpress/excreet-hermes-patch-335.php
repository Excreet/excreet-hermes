<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.3.5
 * Description: Referral code hardening + admin assignment tool.
 *
 *   A — House referral code
 *       Stores a "house" referrer ID in WP options (default: admin user ID 1).
 *       If a new member completes checkout without entering a referral code,
 *       the checkout silently auto-assigns the house code so every signup is
 *       always tracked. The field label and placeholder make this clear.
 *
 *   B — Admin: Set House Referrer
 *       Simple settings row on the Excreet Activation page (patch-302) — or
 *       a standalone admin notice — letting the owner set which member ID
 *       receives unattributed signups.
 *
 *   C — Admin: Retroactive Referral Assignment
 *       WP Admin → Excreet → Assign Referral
 *       Look up any member by email or ID, choose the referrer, submit.
 *       Calls POST /api/hermes/affiliate/assign-referral. Skips the 30-day
 *       hold — admin attestation is the gate.
 *
 * Version: 3.3.5
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX335_HOUSE_OPT',    '_excreet_335_house_referrer_id' );
define( 'EX335_HERMES_KEY',   defined( 'EXCREET_HERMES_API_KEY' ) ? EXCREET_HERMES_API_KEY : '' );
define( 'EX335_HERMES_URL',   defined( 'EXCREET_HERMES_URL' )     ? EXCREET_HERMES_URL     : 'https://5f675b52-473c-4967-bc08-dce590fe9502-00-31pp3ajiubp63.kirk.replit.dev' );

/* ── A — Override patch-299's optional referral field with auto-fill ─────── */

// Remove patch-299's registration check; we add our own that handles blanks
add_action( 'plugins_loaded', function () {
    remove_filter( 'pmpro_registration_checks', 'excreet_299_validate_referral_code' );
}, 20 );

// Our replacement: validate OR auto-fill with house code
add_filter( 'pmpro_registration_checks', 'excreet_335_validate_referral_code', 10 );
function excreet_335_validate_referral_code( bool $okay ): bool {
    if ( ! $okay ) { return false; }

    $code = isset( $_POST['excreet_referral_code'] )
        ? sanitize_text_field( wp_unslash( $_POST['excreet_referral_code'] ) )
        : '';

    // Empty → silently swap in house referrer; never block checkout
    if ( '' === $code ) {
        $house_id = (int) get_option( EX335_HOUSE_OPT, 1 );
        $_POST['excreet_referral_code'] = (string) $house_id;
        return true;
    }

    $referrer_id = (int) $code;
    if ( $referrer_id <= 0 ) {
        pmpro_setMessage( 'Referral code is not valid. Please check and try again.', 'pmpro_error' );
        return false;
    }
    $referrer = get_user_by( 'id', $referrer_id );
    if ( ! $referrer ) {
        pmpro_setMessage( 'Referral code is not valid. Please check and try again.', 'pmpro_error' );
        return false;
    }
    if ( is_user_logged_in() && get_current_user_id() === $referrer_id ) {
        pmpro_setMessage( 'You cannot use your own referral code.', 'pmpro_error' );
        return false;
    }
    if ( user_can( $referrer_id, 'manage_options' ) ) {
        return true; // admin codes always valid (includes house code)
    }
    if ( function_exists( 'excreet_299_referrer_is_eligible' ) && excreet_299_referrer_is_eligible( $referrer_id ) ) {
        return true;
    }
    pmpro_setMessage( 'That referral code is no longer active. The member who referred you may need to renew their membership.', 'pmpro_error' );
    return false;
}

// Replace patch-299's referral field with one that has the house code baked in server-side
add_action( 'init', 'excreet_335_swap_referral_field', 20 );
function excreet_335_swap_referral_field(): void {
    remove_action( 'pmpro_checkout_after_user_fields', 'excreet_299_checkout_referral_field' );
    add_action( 'pmpro_checkout_after_user_fields', 'excreet_335_checkout_referral_field' );
}

function excreet_335_checkout_referral_field(): void {
    $saved      = sanitize_text_field( wp_unslash( $_POST[ EX299_REFERRAL_FIELD ] ?? '' ) );
    $house_code = (string) (int) get_option( EX335_HOUSE_OPT, 1 );
    ?>
    <div class="ex299-referral-field" style="margin:16px 0;">
        <label for="ex299_referral_code" style="display:block;font-weight:600;margin-bottom:6px;">
            Referral Code
        </label>
        <input
            type="text"
            id="ex299_referral_code"
            name="<?php echo esc_attr( EX299_REFERRAL_FIELD ); ?>"
            value="<?php echo esc_attr( $saved ); ?>"
            placeholder="e.g. <?php echo esc_attr( $house_code ); ?>"
            style="width:100%;max-width:320px;padding:8px 10px;border:1px solid #555;border-radius:4px;background:#1a1a1a;color:#e8e0d5;"
            autocomplete="off"
        />
        <p class="ex299-hint" style="margin:4px 0 0;font-size:.85em;color:#9a9a9a;">
            If someone referred you, enter their member number. <strong>Leave blank if no one referred you.</strong>
        </p>
    </div>
    <?php
}

/* ── A2 — Hide search button on PMPro checkout page ─────────────────────── */
add_action( 'wp_head', 'excreet_335_hide_checkout_search' );
function excreet_335_hide_checkout_search(): void {
    if ( ! function_exists( 'pmpro_is_checkout' ) || ! pmpro_is_checkout() ) { return; }
    ?>
    <style>
    body.pmpro_checkout .search-form,
    body.pmpro_checkout .search-field,
    body.pmpro_checkout .search-submit,
    body.pmpro_checkout form[role="search"],
    body.pmpro_checkout .widget_search,
    body.pmpro_checkout .wp-block-search,
    body.pmpro_checkout [class*="elementor-search"],
    body.pmpro_checkout .elementor-widget-search-form,
    body.pmpro_checkout button[type="submit"].search-submit,
    body.pmpro_checkout input[type="search"] { display: none !important; }
    </style>
    <?php
}

/* ── B — Save house referrer via admin POST ──────────────────────────────── */
add_action( 'admin_post_ex335_save_house', 'excreet_335_save_house' );
function excreet_335_save_house(): void {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden' ); }
    check_admin_referer( 'ex335_house_nonce' );
    $id = (int) ( $_POST['house_referrer_id'] ?? 1 );
    update_option( EX335_HOUSE_OPT, $id );
    wp_safe_redirect( admin_url( 'admin.php?page=excreet-assign-referral&house_saved=1' ) );
    exit;
}

/* ── C — Admin menu: Assign Referral ─────────────────────────────────────── */
add_action( 'admin_menu', 'excreet_335_admin_menu' );
function excreet_335_admin_menu(): void {
    add_submenu_page(
        'excreet-activation',
        'Assign Referral',
        'Assign Referral',
        'manage_options',
        'excreet-assign-referral',
        'excreet_335_admin_page'
    );
}

add_action( 'wp_ajax_ex335_assign', 'excreet_335_ajax_assign' );
function excreet_335_ajax_assign(): void {
    check_ajax_referer( 'ex335_assign_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Forbidden' );
    }

    $new_member_id  = (int) ( $_POST['new_member_id']  ?? 0 );
    $referrer_id    = (int) ( $_POST['referrer_id']    ?? 0 );
    $referred_level = (int) ( $_POST['referred_level'] ?? 1 );

    if ( $new_member_id <= 0 || $referrer_id <= 0 ) {
        wp_send_json_error( 'Both member IDs are required.' );
    }
    if ( ! get_user_by( 'id', $new_member_id ) ) {
        wp_send_json_error( 'New member ID ' . $new_member_id . ' not found.' );
    }
    if ( ! get_user_by( 'id', $referrer_id ) ) {
        wp_send_json_error( 'Referrer ID ' . $referrer_id . ' not found.' );
    }

    // Save meta on new member for traceability
    update_user_meta( $new_member_id, '_excreet_referrer_id', $referrer_id );
    update_user_meta( $new_member_id, '_excreet_referral_admin_assigned', current_user_id() );

    // Call Hermes
    $url  = rtrim( EX335_HERMES_URL, '/' ) . '/api/hermes/affiliate/assign-referral';
    $resp = wp_remote_post( $url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . EX335_HERMES_KEY,
        ],
        'body'    => wp_json_encode( [
            'new_member_id'  => (string) $new_member_id,
            'referrer_id'    => (string) $referrer_id,
            'referred_level' => $referred_level,
        ] ),
        'timeout' => 15,
    ] );

    if ( is_wp_error( $resp ) ) {
        wp_send_json_error( 'Hermes error: ' . $resp->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $resp );
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );

    if ( $code !== 200 ) {
        wp_send_json_error( 'Hermes returned ' . $code . ': ' . ( $body['error'] ?? 'unknown' ) );
    }

    $new_user      = get_user_by( 'id', $new_member_id );
    $referrer_user = get_user_by( 'id', $referrer_id );

    wp_send_json_success( [
        'action'       => $body['action'] ?? 'done',
        'new_member'   => $new_user->display_name . ' (#' . $new_member_id . ')',
        'referrer'     => $referrer_user->display_name . ' (#' . $referrer_id . ')',
        'level'        => $referred_level,
    ] );
}

function excreet_335_admin_page(): void {
    $house_id   = (int) get_option( EX335_HOUSE_OPT, 1 );
    $house_user = get_user_by( 'id', $house_id );
    $house_name = $house_user ? $house_user->display_name . ' (#' . $house_id . ')' : 'Not set';
    $saved      = isset( $_GET['house_saved'] );
    ?>
<div class="wrap" style="max-width:720px;">
    <h1 style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:1.4rem;">🤝</span> Excreet — Assign Referral
    </h1>

    <?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible"><p>House referrer updated.</p></div>
    <?php endif; ?>

    <!-- House referrer setting -->
    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:20px 24px;margin:20px 0;">
        <h2 style="margin-top:0;font-size:1.1rem;">Default (House) Referrer</h2>
        <p style="color:#555;margin-top:0;">When a new member signs up without entering a referral code, this member automatically receives the credit. Set it to your own member ID to capture unattributed signups.</p>
        <p><strong>Current house referrer:</strong> <?php echo esc_html( $house_name ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'ex335_house_nonce' ); ?>
            <input type="hidden" name="action" value="ex335_save_house">
            <table class="form-table" style="margin:0;">
                <tr>
                    <th style="width:160px;padding:8px 0;">House Referrer ID</th>
                    <td>
                        <input type="number" name="house_referrer_id" value="<?php echo esc_attr( $house_id ); ?>" min="1" style="width:120px;" class="regular-text">
                        <p class="description">Enter the WP user ID of the member who should receive unattributed signups. Your own ID is shown in your profile URL.</p>
                    </td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary">Save House Referrer</button></p>
        </form>
    </div>

    <!-- Retroactive assignment -->
    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:20px 24px;margin:20px 0;">
        <h2 style="margin-top:0;font-size:1.1rem;">Retroactive Referral Assignment</h2>
        <p style="color:#555;margin-top:0;">Use this when a new member forgot to enter their referrer's code at checkout. Look up both users and submit — the credit is applied immediately with no 30-day hold.</p>

        <table class="form-table" style="margin:0;" id="ex335-form">
            <tr>
                <th style="width:200px;padding:10px 0;">New Member (who signed up)</th>
                <td>
                    <input type="text" id="ex335-new-member" placeholder="Email or WP User ID" style="width:280px;" class="regular-text">
                    <button type="button" class="button" onclick="excreet335Lookup('new')">Look Up</button>
                    <span id="ex335-new-result" style="margin-left:8px;color:#0073aa;font-weight:600;"></span>
                    <input type="hidden" id="ex335-new-id">
                </td>
            </tr>
            <tr>
                <th style="padding:10px 0;">Referrer (who invited them)</th>
                <td>
                    <input type="text" id="ex335-referrer" placeholder="Email, WP User ID, or referral code" style="width:280px;" class="regular-text">
                    <button type="button" class="button" onclick="excreet335Lookup('referrer')">Look Up</button>
                    <span id="ex335-referrer-result" style="margin-left:8px;color:#0073aa;font-weight:600;"></span>
                    <input type="hidden" id="ex335-referrer-id">
                </td>
            </tr>
            <tr>
                <th style="padding:10px 0;">New Member's Plan</th>
                <td>
                    <select id="ex335-level" style="width:200px;">
                        <option value="1">Starter ($15/mo) — referrer earns $5/mo</option>
                        <option value="2">Premium ($25/mo) — referrer earns $10/mo</option>
                    </select>
                </td>
            </tr>
        </table>

        <p style="margin-top:16px;">
            <button type="button" class="button button-primary" onclick="excreet335Assign()" id="ex335-submit">
                Assign Referral Credit
            </button>
        </p>

        <div id="ex335-msg" style="margin-top:12px;padding:12px 16px;border-radius:4px;display:none;font-weight:500;"></div>
    </div>

    <!-- Lookup helper note -->
    <p style="color:#666;font-size:.9em;">
        Tip: every member's referral code is their WP User ID. You can find it at
        <strong>Users → All Users → click a member → look at the URL (?user_id=N)</strong>.
    </p>
</div>

<script>
var ex335Nonce = '<?php echo esc_js( wp_create_nonce( 'ex335_assign_nonce' ) ); ?>';

function excreet335Lookup(which) {
    var inp    = document.getElementById('ex335-' + (which === 'new' ? 'new-member' : 'referrer'));
    var result = document.getElementById('ex335-' + (which === 'new' ? 'new' : 'referrer') + '-result');
    var hidden = document.getElementById('ex335-' + (which === 'new' ? 'new' : 'referrer') + '-id');
    var query  = inp.value.trim();
    if (!query) { result.textContent = 'Enter an email or ID first.'; return; }

    result.textContent = 'Looking up…';

    var fd = new FormData();
    fd.append('action', 'ex335_lookup_user');
    fd.append('nonce',  ex335Nonce);
    fd.append('query',  query);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function (data) {
            if (data.success) {
                result.textContent = '✓ ' + data.data.display + ' (ID ' + data.data.id + ')';
                hidden.value = data.data.id;
            } else {
                result.textContent = '✗ ' + data.data;
                hidden.value = '';
            }
        });
}

function excreet335Assign() {
    var newId      = document.getElementById('ex335-new-id').value;
    var refId      = document.getElementById('ex335-referrer-id').value;
    var level      = document.getElementById('ex335-level').value;
    var msg        = document.getElementById('ex335-msg');
    var btn        = document.getElementById('ex335-submit');

    if (!newId || !refId) {
        msg.style.display = 'block';
        msg.style.background = '#fef2f2';
        msg.style.border = '1px solid #fca5a5';
        msg.textContent = 'Look up both members first.';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Assigning…';

    var fd = new FormData();
    fd.append('action',         'ex335_assign');
    fd.append('nonce',          ex335Nonce);
    fd.append('new_member_id',  newId);
    fd.append('referrer_id',    refId);
    fd.append('referred_level', level);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function (data) {
            msg.style.display = 'block';
            if (data.success) {
                msg.style.background = '#f0fdf4';
                msg.style.border = '1px solid #86efac';
                msg.innerHTML = '✅ Done (' + data.data.action + '). <strong>' + data.data.new_member + '</strong> is now credited to <strong>' + data.data.referrer + '</strong> at Level ' + data.data.level + '.';
            } else {
                msg.style.background = '#fef2f2';
                msg.style.border = '1px solid #fca5a5';
                msg.textContent = '✗ ' + data.data;
            }
            btn.disabled = false;
            btn.textContent = 'Assign Referral Credit';
        });
}
</script>
    <?php
}

// AJAX: look up user by email or ID
add_action( 'wp_ajax_ex335_lookup_user', 'excreet_335_ajax_lookup_user' );
function excreet_335_ajax_lookup_user(): void {
    check_ajax_referer( 'ex335_assign_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Forbidden' ); }

    $query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
    if ( '' === $query ) { wp_send_json_error( 'Empty query.' ); }

    $user = is_numeric( $query )
        ? get_user_by( 'id', (int) $query )
        : get_user_by( 'email', $query );

    if ( ! $user ) {
        wp_send_json_error( 'No user found for "' . $query . '".' );
    }

    wp_send_json_success( [
        'id'      => $user->ID,
        'display' => $user->display_name . ' <' . $user->user_email . '>',
    ] );
}
