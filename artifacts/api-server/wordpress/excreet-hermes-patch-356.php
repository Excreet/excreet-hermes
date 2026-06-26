<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.5.6
 * Description: Morning reminder enrollment — SMS (US) + WhatsApp (international).
 *   A — [excreet_reminder_signup] shortcode: phone number field, channel selector,
 *       opt-in/opt-out toggle. Renders on /member-dashboard/ and /membership-account/.
 *   B — AJAX handlers: opt-in and opt-out call Hermes /api/hermes/sms/* endpoints.
 *   C — Auto-inject enrollment card on /member-dashboard/ for active members
 *       who have not yet enrolled.
 *
 * Version: 3.5.6
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX356_HERMES_URL', defined( 'EXCREET_HERMES_URL' ) ? EXCREET_HERMES_URL : 'https://5f675b52-473c-4967-bc08-dce590fe9502-00-31pp3ajiubp63.kirk.replit.dev' );
define( 'EX356_HERMES_KEY', defined( 'EXCREET_HERMES_API_KEY' ) ? EXCREET_HERMES_API_KEY : '' );

/* ── A — Shortcode ─────────────────────────────────────────────────────────── */

add_shortcode( 'excreet_reminder_signup', 'excreet_356_reminder_shortcode' );
function excreet_356_reminder_shortcode(): string {
    if ( ! is_user_logged_in() ) { return ''; }
    if ( ! function_exists( 'pmpro_hasMembershipLevel' ) || ! pmpro_hasMembershipLevel( null ) ) { return ''; }

    $user_id   = get_current_user_id();
    $member_id = (string) $user_id;
    $enrolled  = get_user_meta( $user_id, '_excreet_356_enrolled', true );
    $channel   = get_user_meta( $user_id, '_excreet_356_channel', true ) ?: 'sms';
    $phone     = get_user_meta( $user_id, '_excreet_356_phone', true ) ?: '';
    $nonce     = wp_create_nonce( 'ex356_sms' );

    ob_start(); ?>
    <div class="ex356-wrap" id="ex356-wrap">
        <div class="ex356-card">
            <div class="ex356-icon">&#128241;</div>
            <div class="ex356-heading">Morning Body Check Reminder</div>
            <div class="ex356-sub">Get a message every morning at 7 AM reminding you to run your body check. Never miss a day.</div>

            <?php if ( $enrolled ) : ?>
            <div class="ex356-enrolled" id="ex356-enrolled-state">
                <div class="ex356-badge">&#10003; Enrolled via <?php echo esc_html( strtoupper( $channel ) ); ?></div>
                <p class="ex356-phone-display">Number on file: <?php echo esc_html( $phone ); ?></p>
                <button class="ex356-btn ex356-btn-out" id="ex356-opt-out-btn">Stop Reminders</button>
            </div>
            <?php else : ?>
            <div class="ex356-form" id="ex356-form-state">
                <div class="ex356-field">
                    <label for="ex356-phone">Your mobile number</label>
                    <input type="tel" id="ex356-phone" placeholder="+1 555 000 0000" value="<?php echo esc_attr( $phone ); ?>" />
                </div>
                <div class="ex356-field">
                    <label>Delivery channel</label>
                    <div class="ex356-channels">
                        <label class="ex356-ch <?php echo $channel === 'sms' ? 'ex356-ch-active' : ''; ?>">
                            <input type="radio" name="ex356-channel" value="sms" <?php checked( $channel, 'sms' ); ?> />
                            <span>&#128172; SMS</span>
                            <small>US numbers only</small>
                        </label>
                        <label class="ex356-ch <?php echo $channel === 'whatsapp' ? 'ex356-ch-active' : ''; ?>">
                            <input type="radio" name="ex356-channel" value="whatsapp" <?php checked( $channel, 'whatsapp' ); ?> />
                            <span>&#128242; WhatsApp</span>
                            <small>Any country</small>
                        </label>
                    </div>
                </div>
                <button class="ex356-btn ex356-btn-in" id="ex356-opt-in-btn">Activate Reminders</button>
            </div>
            <?php endif; ?>

            <div class="ex356-msg" id="ex356-msg" style="display:none;"></div>
        </div>
    </div>

    <style>
    .ex356-wrap { font-family: inherit; max-width: 460px; margin: 24px auto; }
    .ex356-card {
        background: linear-gradient(135deg, #1a0a2e 0%, #2d1b4e 100%);
        border: 1px solid rgba(201,168,76,.3);
        border-radius: 16px;
        padding: 28px 24px;
        color: #e8e0d5;
        text-align: center;
    }
    .ex356-icon { font-size: 2.2rem; margin-bottom: 8px; }
    .ex356-heading { font-size: 1.15rem; font-weight: 700; color: #C9A84C; margin-bottom: 6px; }
    .ex356-sub { font-size: .85rem; color: #aaa; margin-bottom: 20px; line-height: 1.5; }
    .ex356-field { margin-bottom: 14px; text-align: left; }
    .ex356-field label { display: block; font-size: .8rem; color: #C9A84C; font-weight: 600; margin-bottom: 5px; text-transform: uppercase; letter-spacing: .05em; }
    .ex356-field input[type="tel"] {
        width: 100%; padding: 10px 12px; border: 1px solid #555; border-radius: 8px;
        background: #0e0520; color: #e8e0d5; font-size: 1rem; box-sizing: border-box;
    }
    .ex356-channels { display: flex; gap: 10px; }
    .ex356-ch {
        flex: 1; border: 1px solid #444; border-radius: 10px; padding: 12px 8px;
        cursor: pointer; text-align: center; transition: border-color .2s, background .2s;
    }
    .ex356-ch input[type="radio"] { display: none; }
    .ex356-ch span { display: block; font-size: .95rem; font-weight: 600; }
    .ex356-ch small { display: block; font-size: .72rem; color: #888; margin-top: 3px; }
    .ex356-ch-active, .ex356-ch:has(input:checked) {
        border-color: #C9A84C; background: rgba(201,168,76,.1);
    }
    .ex356-btn {
        width: 100%; padding: 12px; border: none; border-radius: 10px;
        font-size: 1rem; font-weight: 700; cursor: pointer; margin-top: 8px;
        transition: opacity .2s;
    }
    .ex356-btn:hover { opacity: .88; }
    .ex356-btn-in { background: #C9A84C; color: #1a0a2e; }
    .ex356-btn-out { background: transparent; border: 1px solid #555; color: #aaa; font-size: .85rem; }
    .ex356-badge {
        display: inline-block; background: rgba(201,168,76,.15); border: 1px solid #C9A84C;
        color: #C9A84C; border-radius: 20px; padding: 5px 16px; font-size: .85rem;
        font-weight: 700; margin-bottom: 10px;
    }
    .ex356-phone-display { font-size: .82rem; color: #888; margin-bottom: 14px; }
    .ex356-msg { margin-top: 12px; padding: 10px 14px; border-radius: 8px; font-size: .85rem; }
    .ex356-msg.ok { background: rgba(72,199,116,.15); color: #48c774; }
    .ex356-msg.err { background: rgba(255,80,80,.12); color: #ff6b6b; }
    </style>

    <script>
    (function() {
        var memberId  = <?php echo wp_json_encode( $member_id ); ?>;
        var ajaxUrl   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        var nonce     = <?php echo wp_json_encode( $nonce ); ?>;

        function showMsg(text, type) {
            var el = document.getElementById('ex356-msg');
            if (!el) return;
            el.textContent = text;
            el.className = 'ex356-msg ' + type;
            el.style.display = 'block';
        }

        function ajax(action, data, cb) {
            data.action = action;
            data.nonce  = nonce;
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data).toString(),
            })
            .then(function(r) { return r.json(); })
            .then(cb)
            .catch(function() { showMsg('Network error — please try again.', 'err'); });
        }

        var optInBtn = document.getElementById('ex356-opt-in-btn');
        if (optInBtn) {
            optInBtn.addEventListener('click', function() {
                var phone   = (document.getElementById('ex356-phone') || {}).value || '';
                var chanEl  = document.querySelector('input[name="ex356-channel"]:checked');
                var channel = chanEl ? chanEl.value : 'sms';
                if (!phone.trim()) { showMsg('Please enter your mobile number.', 'err'); return; }
                optInBtn.disabled = true;
                optInBtn.textContent = 'Activating…';
                ajax('excreet_356_opt_in', { member_id: memberId, phone: phone, channel: channel }, function(r) {
                    if (r.success) {
                        showMsg('✓ You are enrolled! First reminder arrives tomorrow at 7 AM.', 'ok');
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        optInBtn.disabled = false;
                        optInBtn.textContent = 'Activate Reminders';
                        showMsg(r.data || 'Something went wrong.', 'err');
                    }
                });
            });
        }

        var optOutBtn = document.getElementById('ex356-opt-out-btn');
        if (optOutBtn) {
            optOutBtn.addEventListener('click', function() {
                if (!confirm('Stop morning reminders?')) return;
                optOutBtn.disabled = true;
                ajax('excreet_356_opt_out', { member_id: memberId }, function(r) {
                    if (r.success) {
                        showMsg('Reminders stopped. Re-enroll any time.', 'ok');
                        setTimeout(function() { location.reload(); }, 1800);
                    } else {
                        optOutBtn.disabled = false;
                        showMsg(r.data || 'Something went wrong.', 'err');
                    }
                });
            });
        }

        document.querySelectorAll('.ex356-ch').forEach(function(el) {
            el.addEventListener('click', function() {
                document.querySelectorAll('.ex356-ch').forEach(function(e) { e.classList.remove('ex356-ch-active'); });
                el.classList.add('ex356-ch-active');
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ── B — AJAX handlers ─────────────────────────────────────────────────────── */

add_action( 'wp_ajax_excreet_356_opt_in', 'excreet_356_ajax_opt_in' );
function excreet_356_ajax_opt_in(): void {
    check_ajax_referer( 'ex356_sms', 'nonce' );
    $user_id   = get_current_user_id();
    $member_id = sanitize_text_field( wp_unslash( $_POST['member_id'] ?? '' ) );
    $phone     = sanitize_text_field( wp_unslash( $_POST['phone']     ?? '' ) );
    $channel   = sanitize_text_field( wp_unslash( $_POST['channel']   ?? 'sms' ) );

    if ( ! $user_id || ! $phone ) {
        wp_send_json_error( 'Missing required fields.' );
    }

    $resp = wp_remote_post( EX356_HERMES_URL . '/api/hermes/sms/opt-in', [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . EX356_HERMES_KEY,
        ],
        'body'    => wp_json_encode( [
            'memberId'    => $member_id,
            'phoneNumber' => $phone,
            'channel'     => $channel,
            'timezone'    => 'America/New_York',
        ] ),
        'timeout' => 10,
    ] );

    if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
        wp_send_json_error( 'Could not reach the reminder service. Please try again.' );
    }

    update_user_meta( $user_id, '_excreet_356_enrolled', '1' );
    update_user_meta( $user_id, '_excreet_356_channel',  $channel );
    update_user_meta( $user_id, '_excreet_356_phone',    $phone );

    wp_send_json_success( [ 'channel' => $channel ] );
}

add_action( 'wp_ajax_excreet_356_opt_out', 'excreet_356_ajax_opt_out' );
function excreet_356_ajax_opt_out(): void {
    check_ajax_referer( 'ex356_sms', 'nonce' );
    $user_id   = get_current_user_id();
    $member_id = sanitize_text_field( wp_unslash( $_POST['member_id'] ?? '' ) );

    wp_remote_post( EX356_HERMES_URL . '/api/hermes/sms/opt-out', [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . EX356_HERMES_KEY,
        ],
        'body'    => wp_json_encode( [ 'memberId' => $member_id ] ),
        'timeout' => 10,
    ] );

    delete_user_meta( $user_id, '_excreet_356_enrolled' );
    wp_send_json_success();
}

/* ── C — Auto-inject enrollment card on member dashboard ───────────────────── */

add_action( 'wp_footer', 'excreet_356_inject_dashboard_card' );
function excreet_356_inject_dashboard_card(): void {
    if ( ! is_user_logged_in() ) { return; }
    if ( ! is_page( 772 ) ) { return; }
    if ( ! function_exists( 'pmpro_hasMembershipLevel' ) || ! pmpro_hasMembershipLevel( null ) ) { return; }

    $user_id  = get_current_user_id();
    $enrolled = get_user_meta( $user_id, '_excreet_356_enrolled', true );
    if ( $enrolled ) { return; }
    ?>
    <div id="ex356-dashboard-nudge" style="position:fixed;bottom:80px;right:20px;z-index:9990;max-width:300px;box-shadow:0 8px 32px rgba(0,0,0,.5);border-radius:14px;overflow:hidden;">
        <?php echo excreet_356_reminder_shortcode(); ?>
        <button onclick="document.getElementById('ex356-dashboard-nudge').style.display='none'"
            style="position:absolute;top:8px;right:10px;background:none;border:none;color:#888;font-size:1.1rem;cursor:pointer;">&#10005;</button>
    </div>
    <?php
}
