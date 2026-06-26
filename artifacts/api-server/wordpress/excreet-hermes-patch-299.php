<?php
/**
 * Plugin Name: Excreet Hermes Patch 2.9.9
 * Description: Affiliate & referral system — referral code at PMPro checkout,
 *              90-day grace validation, affiliate dashboard panel (balance,
 *              referral list, payout history, W-9 alert), monthly credit cron,
 *              bi-weekly payout trigger cron.
 *
 * Version:    2.9.9
 * Depends on: excreet-hermes-client.php  (EXCREET_HERMES_URL, EXCREET_HERMES_API_KEY)
 *             excreet-hermes-patch-291.php (excreet_291_is_member)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── Constants ────────────────────────────────────────────────────────────── */

define( 'EX299_GRACE_DAYS',           90 );
define( 'EX299_PAYOUT_THRESHOLD',     50 );   // dollars
define( 'EX299_REFERRAL_FIELD',       'excreet_referral_code' );
define( 'EX299_HERMES_BASE_OPT',      '_excreet_hermes_base_url' );

/* ── Helper: Hermes base URL ──────────────────────────────────────────────── */

function excreet_299_hermes_base(): string {
    if ( defined( 'EXCREET_HERMES_URL' ) ) {
        return rtrim( (string) EXCREET_HERMES_URL, '/' );
    }
    return get_option( EX299_HERMES_BASE_OPT, 'https://core-status-check.replit.app/api/hermes/intake' );
}

function excreet_299_hermes_url( string $path ): string {
    $base = excreet_299_hermes_base();
    $base = preg_replace( '#/api/hermes/intake$#', '', $base );
    return rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );
}

function excreet_299_hermes_key(): string {
    return defined( 'EXCREET_HERMES_API_KEY' ) ? (string) EXCREET_HERMES_API_KEY : '';
}

/* ────────────────────────────────────────────────────────────────────────── */
/* SECTION 1 — Referral code at PMPro checkout                               */
/* ────────────────────────────────────────────────────────────────────────── */

/**
 * Add referral code field after the user fields on PMPro checkout.
 */
add_action( 'pmpro_checkout_after_user_fields', 'excreet_299_checkout_referral_field' );
function excreet_299_checkout_referral_field(): void {
    $saved = isset( $_POST[ EX299_REFERRAL_FIELD ] )
        ? sanitize_text_field( wp_unslash( $_POST[ EX299_REFERRAL_FIELD ] ) )
        : '';
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
            placeholder="Enter your referring member's ID"
            style="width:100%;max-width:320px;padding:8px 10px;border:1px solid #555;border-radius:4px;background:#1a1a1a;color:#e8e0d5;"
            autocomplete="off"
        />
        <p style="margin:4px 0 0;font-size:.8em;color:#9a9a9a;">
            If someone referred you, enter their member number here.
        </p>
    </div>
    <?php
}

/**
 * Validate the referral code during PMPro registration checks.
 *
 * Rules:
 *  - If empty, skip (field is optional).
 *  - Referrer must be a real WP user.
 *  - Referrer must be an active PMPro member, OR have lapsed within 90 days.
 *  - Referrer cannot refer themselves.
 */
add_filter( 'pmpro_registration_checks', 'excreet_299_validate_referral_code' );
function excreet_299_validate_referral_code( bool $okay ): bool {
    if ( ! $okay ) {
        return false;
    }

    $code = isset( $_POST[ EX299_REFERRAL_FIELD ] )
        ? sanitize_text_field( wp_unslash( $_POST[ EX299_REFERRAL_FIELD ] ) )
        : '';

    if ( '' === $code ) {
        return true; // optional field
    }

    $referrer_id = (int) $code;
    if ( $referrer_id <= 0 ) {
        pmpro_setMessage( 'Referral code is not valid. Please check the number and try again.', 'pmpro_error' );
        return false;
    }

    // Must be a real user
    $referrer = get_user_by( 'id', $referrer_id );
    if ( ! $referrer ) {
        pmpro_setMessage( 'Referral code is not valid. Please check the number and try again.', 'pmpro_error' );
        return false;
    }

    // Cannot refer yourself
    if ( is_user_logged_in() && get_current_user_id() === $referrer_id ) {
        pmpro_setMessage( 'You cannot use your own referral code.', 'pmpro_error' );
        return false;
    }

    // Must be active member OR lapsed within 90 days OR be an admin
    if ( user_can( $referrer_id, 'manage_options' ) ) {
        return true; // admins always valid
    }

    if ( excreet_299_referrer_is_eligible( $referrer_id ) ) {
        return true;
    }

    pmpro_setMessage( 'That referral code is no longer active. The member who referred you may need to renew their membership.', 'pmpro_error' );
    return false;
}

/**
 * Returns true if the referrer is currently an active PMPro member,
 * OR had an active membership that lapsed within the last 90 days.
 */
function excreet_299_referrer_is_eligible( int $user_id ): bool {
    // Active right now
    if ( function_exists( 'pmpro_hasMembershipLevel' ) && pmpro_hasMembershipLevel( null, $user_id ) ) {
        return true;
    }

    // Check last membership end date (grace window)
    global $wpdb;
    $last_end = $wpdb->get_var( $wpdb->prepare(
        "SELECT enddate FROM {$wpdb->prefix}pmpro_memberships_users
         WHERE user_id = %d AND status = 'inactive'
         ORDER BY enddate DESC LIMIT 1",
        $user_id
    ) );

    if ( ! $last_end ) {
        return false;
    }

    $lapsed_ts  = strtotime( $last_end );
    $grace_secs = EX299_GRACE_DAYS * DAY_IN_SECONDS;

    return ( time() - $lapsed_ts ) <= $grace_secs;
}

/**
 * After a successful PMPro checkout, register the referral with Hermes.
 * Also fires the W-9 alert to the referrer if this is their first cleared referral.
 */
add_action( 'pmpro_after_checkout', 'excreet_299_after_checkout', 20, 2 );
function excreet_299_after_checkout( int $user_id, $morder ): void {
    $code = isset( $_POST[ EX299_REFERRAL_FIELD ] )
        ? sanitize_text_field( wp_unslash( $_POST[ EX299_REFERRAL_FIELD ] ) )
        : '';

    if ( '' === $code ) {
        return;
    }

    $referrer_id  = (int) $code;
    $level_id     = isset( $morder->membership_id ) ? (int) $morder->membership_id : 1;
    $referred_level = ( $level_id === 2 ) ? 2 : 1; // 2=Premium → $10, else → $5

    // Store referral meta on the new member for traceability
    update_user_meta( $user_id, '_excreet_referrer_id', $referrer_id );

    // Register with Hermes (non-blocking — failure is logged, not fatal)
    $url = excreet_299_hermes_url( 'api/hermes/affiliate/register' );
    $response = wp_remote_post( $url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . excreet_299_hermes_key(),
        ],
        'body'    => wp_json_encode( [
            'referrer_member_id' => (string) $referrer_id,
            'referred_member_id' => (string) $user_id,
            'referred_level'     => $referred_level,
        ] ),
        'timeout' => 10,
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( '[EX299] Hermes affiliate register error: ' . $response->get_error_message() );
    }

    // Store referral code for display in session
    set_transient( 'ex299_referral_registered_' . $user_id, $referrer_id, HOUR_IN_SECONDS );
}

/* ────────────────────────────────────────────────────────────────────────── */
/* SECTION 2 — Affiliate dashboard shortcode                                 */
/* ────────────────────────────────────────────────────────────────────────── */

/* ── Affiliate area page brand treatment ─────────────────────────────────── */
add_action( 'wp_head', 'excreet_299_page_styles', 99 );
function excreet_299_page_styles(): void {
    if ( ! is_page( 'affiliate-area' ) ) { return; }
    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url = esc_url( 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg' );
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style id="ex299-page-brand">
body.page-template-default.page:is([class*="page-id"]) {
    font-family:"Poppins",sans-serif !important;
}
html, body {
    background:
        linear-gradient(160deg,rgba(13,1,32,.52) 0%,rgba(26,5,53,.20) 28%,rgba(26,5,53,.15) 62%,rgba(13,1,32,.50) 100%),
        url("' . $bg_url . '") center/cover no-repeat fixed #0c0115 !important;
    font-family:"Poppins",sans-serif !important;
}
.site-header,.site-footer,.elementor-location-header,.elementor-location-footer { background:rgba(8,1,16,.90) !important; }
.entry-content,.post-content,.page-content { background:rgba(12,2,26,.74) !important; border:1px solid rgba(201,168,76,.15) !important; border-radius:16px !important; padding:2rem 2.4rem !important; backdrop-filter:blur(10px) !important; }
h1.entry-title { color:#C9A84C !important; font-family:"Poppins",sans-serif !important; }
.ex299-dashboard { font-family:"Poppins",sans-serif !important; }
.ex299-dashboard h3 { font-family:"Poppins",sans-serif !important; font-size:.82rem !important; font-weight:700 !important; letter-spacing:.1em !important; text-transform:uppercase !important; color:rgba(201,168,76,.85) !important; margin:0 0 14px !important; }
.ex299-card { background:rgba(12,2,26,.76) !important; border:1px solid rgba(201,168,76,.22) !important; border-radius:14px !important; padding:20px !important; backdrop-filter:blur(8px) !important; }
.ex299-summary { gap:14px !important; margin-bottom:28px !important; }
</style>' . "\n";
}

add_shortcode( 'excreet_affiliate_dashboard', 'excreet_299_affiliate_dashboard_shortcode' );
function excreet_299_affiliate_dashboard_shortcode(): string {
    if ( ! is_user_logged_in() ) {
        return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your affiliate dashboard.</p>';
    }

    $user_id  = get_current_user_id();
    $member_id = (string) $user_id;

    // Fetch data from Hermes
    $url      = excreet_299_hermes_url( 'api/hermes/affiliate/dashboard/' . $member_id );
    $response = wp_remote_get( $url, [
        'headers' => [
            'Authorization' => 'Bearer ' . excreet_299_hermes_key(),
        ],
        'timeout' => 8,
    ] );

    if ( is_wp_error( $response ) ) {
        return '<p class="ex299-error">Unable to load affiliate data. Please try again shortly.</p>';
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) ) {
        return '<p class="ex299-error">Unable to load affiliate data. Please try again shortly.</p>';
    }

    $balance       = isset( $body['payout_balance_cents'] ) ? (int) $body['payout_balance_cents'] : 0;
    $total_earned  = isset( $body['total_earned_cents'] )   ? (int) $body['total_earned_cents']   : 0;
    $w9_status     = $body['w9_status'] ?? 'not_required';
    $referrals     = is_array( $body['referrals'] ?? null ) ? $body['referrals'] : [];
    $payouts       = is_array( $body['payouts']   ?? null ) ? $body['payouts']   : [];

    $referral_code = $user_id; // code IS the member's WP user ID
    $balance_fmt   = '$' . number_format( $balance / 100, 2 );
    $total_fmt     = '$' . number_format( $total_earned / 100, 2 );
    $threshold_fmt = '$' . EX299_PAYOUT_THRESHOLD . '.00';

    ob_start();
    ?>
    <div class="ex299-dashboard" style="font-family:inherit;max-width:720px;">

        <?php /* ── W-9 Alert ── */ ?>
        <?php if ( 'pending' === $w9_status ) : ?>
        <div class="ex299-w9-alert" style="background:rgba(58,42,0,.65);border:1px solid rgba(184,134,11,.7);border-radius:14px;padding:16px 20px;margin-bottom:24px;backdrop-filter:blur(8px);">
            <strong style="color:#f0c040;">Action Required — W-9 Tax Form</strong>
            <p style="margin:8px 0 0;color:#e8d9a0;font-size:.9em;">
                Congratulations — you have a cleared referral! Before your first payout can be released,
                the IRS requires us to collect a W-9 form from you.
            </p>
            <ol style="margin:10px 0 0 16px;color:#e8d9a0;font-size:.875em;line-height:1.7;">
                <li>Download the <a href="https://www.irs.gov/pub/irs-pdf/fw9.pdf" target="_blank" style="color:#f0c040;">IRS W-9 form (PDF)</a>.</li>
                <li>Complete all fields (name, address, SSN or EIN, signature).</li>
                <li>Email your completed W-9 to <a href="mailto:compliance@excreet.com" style="color:#f0c040;">compliance@excreet.com</a>.</li>
            </ol>
            <p style="margin:8px 0 0;color:#9a8a60;font-size:.8em;">
                Your payout balance will accumulate and be released once your W-9 is confirmed.
                A 1099-NEC will be issued if your total earnings exceed $600 in a calendar year.
            </p>
        </div>
        <?php endif; ?>

        <?php /* ── Summary cards ── */ ?>
        <div class="ex299-summary" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:28px;">
            <div class="ex299-card" style="flex:1;min-width:160px;">
                <div style="font-size:.72em;font-family:'Poppins',sans-serif;color:rgba(201,168,76,.7);text-transform:uppercase;letter-spacing:.1em;font-weight:600;">Your Referral Code</div>
                <div style="font-size:2em;font-weight:700;color:#C9A84C;margin-top:6px;letter-spacing:.04em;"><?php echo esc_html( $referral_code ); ?></div>
                <div style="font-size:.75em;color:rgba(240,232,255,.45);margin-top:4px;font-family:'Poppins',sans-serif;">Share this with friends</div>
            </div>
            <div class="ex299-card" style="flex:1;min-width:160px;">
                <div style="font-size:.72em;font-family:'Poppins',sans-serif;color:rgba(201,168,76,.7);text-transform:uppercase;letter-spacing:.1em;font-weight:600;">Pending Balance</div>
                <div style="font-size:2em;font-weight:700;color:#f0e8ff;margin-top:6px;"><?php echo esc_html( $balance_fmt ); ?></div>
                <div style="font-size:.75em;color:rgba(240,232,255,.45);margin-top:4px;font-family:'Poppins',sans-serif;">Pays out at <?php echo esc_html( $threshold_fmt ); ?> (bi-weekly)</div>
            </div>
            <div class="ex299-card" style="flex:1;min-width:160px;">
                <div style="font-size:.72em;font-family:'Poppins',sans-serif;color:rgba(201,168,76,.7);text-transform:uppercase;letter-spacing:.1em;font-weight:600;">Total Earned</div>
                <div style="font-size:2em;font-weight:700;color:#f0e8ff;margin-top:6px;"><?php echo esc_html( $total_fmt ); ?></div>
                <div style="font-size:.75em;color:rgba(240,232,255,.45);margin-top:4px;font-family:'Poppins',sans-serif;">All-time</div>
            </div>
        </div>

        <?php /* ── Reward rates ── */ ?>
        <div style="background:rgba(107,33,168,.18);border:1px solid rgba(201,168,76,.22);border-radius:14px;padding:14px 20px;margin-bottom:28px;font-size:.88em;color:rgba(240,232,255,.72);font-family:'Poppins',sans-serif;backdrop-filter:blur(8px);">
            <strong style="color:#C9A84C;">Reward Rates:</strong>
            &nbsp;Starter referral = <strong style="color:#f0e8ff;">$5 / month</strong>
            &nbsp;·&nbsp;
            Premium referral = <strong style="color:#f0e8ff;">$10 / month</strong>
            &nbsp;·&nbsp;
            Credit clears 30 days after referred member's first payment.
        </div>

        <?php /* ── Referral list ── */ ?>
        <h3 style="color:#c9a84c;font-size:1em;text-transform:uppercase;letter-spacing:.06em;margin:0 0 12px;">
            Your Referrals (<?php echo count( $referrals ); ?>)
        </h3>
        <?php if ( empty( $referrals ) ) : ?>
            <p style="color:#666;font-size:.9em;">No referrals yet. Share your referral code to start earning.</p>
        <?php else : ?>
        <table style="width:100%;border-collapse:collapse;font-size:.875em;margin-bottom:24px;background:rgba(12,2,26,.65);border:1px solid rgba(201,168,76,.15);border-radius:12px;overflow:hidden;">
            <thead>
                <tr style="background:rgba(107,33,168,.35);text-align:left;border-bottom:1px solid rgba(201,168,76,.25);">
                    <th style="padding:10px 12px;color:#F5D97A;font-family:'Poppins',sans-serif;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;">Member #</th>
                    <th style="padding:10px 12px;color:#F5D97A;font-family:'Poppins',sans-serif;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;">Plan</th>
                    <th style="padding:10px 12px;color:#F5D97A;font-family:'Poppins',sans-serif;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;">Status</th>
                    <th style="padding:10px 12px;color:#F5D97A;font-family:'Poppins',sans-serif;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;">Joined</th>
                    <th style="padding:10px 12px;color:#F5D97A;font-family:'Poppins',sans-serif;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;">Credit Active</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $referrals as $ref ) :
                $level_label  = ( (int) ( $ref['referred_level'] ?? 1 ) === 2 ) ? 'Premium (+$10/mo)' : 'Starter (+$5/mo)';
                $status       = $ref['status'] ?? 'pending';
                $status_color = match( $status ) {
                    'cleared' => '#4ade80',
                    'revoked' => '#f87171',
                    default   => '#fbbf24',
                };
                $joined_date  = isset( $ref['joined_at'] )
                    ? date( 'M j, Y', strtotime( $ref['joined_at'] ) )
                    : '—';
                $cleared_date = isset( $ref['credit_cleared_at'] ) && $ref['credit_cleared_at']
                    ? date( 'M j, Y', strtotime( $ref['credit_cleared_at'] ) )
                    : ( 'pending' === $status ? 'After 30 days' : '—' );
            ?>
                <tr style="border-bottom:1px solid rgba(201,168,76,.08);color:rgba(240,232,255,.88);font-family:'Poppins',sans-serif;">
                    <td style="padding:8px 10px;"><?php echo esc_html( $ref['referred_member_id'] ?? '—' ); ?></td>
                    <td style="padding:8px 10px;"><?php echo esc_html( $level_label ); ?></td>
                    <td style="padding:8px 10px;">
                        <span style="color:<?php echo esc_attr( $status_color ); ?>;font-weight:600;">
                            <?php echo esc_html( ucfirst( $status ) ); ?>
                        </span>
                    </td>
                    <td style="padding:8px 10px;"><?php echo esc_html( $joined_date ); ?></td>
                    <td style="padding:8px 10px;"><?php echo esc_html( $cleared_date ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php /* ── Payout history ── */ ?>
        <h3 style="color:#c9a84c;font-size:1em;text-transform:uppercase;letter-spacing:.06em;margin:0 0 12px;">
            Payout History (<?php echo count( $payouts ); ?>)
        </h3>
        <?php if ( empty( $payouts ) ) : ?>
            <p style="color:#666;font-size:.9em;">No payouts yet. Payouts trigger bi-weekly when your balance reaches $50.</p>
        <?php else : ?>
        <table style="width:100%;border-collapse:collapse;font-size:.875em;margin-bottom:16px;background:rgba(12,2,26,.65);border:1px solid rgba(201,168,76,.15);border-radius:12px;overflow:hidden;">
            <thead>
                <tr style="background:rgba(107,33,168,.35);text-align:left;border-bottom:1px solid rgba(201,168,76,.25);">
                    <th style="padding:10px 12px;color:#F5D97A;font-family:'Poppins',sans-serif;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;">Amount</th>
                    <th style="padding:10px 12px;color:#F5D97A;font-family:'Poppins',sans-serif;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;">Status</th>
                    <th style="padding:10px 12px;color:#F5D97A;font-family:'Poppins',sans-serif;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;">Period</th>
                    <th style="padding:10px 12px;color:#F5D97A;font-family:'Poppins',sans-serif;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;">Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $payouts as $p ) :
                $amt     = '$' . number_format( ( (int) ( $p['amount_cents'] ?? 0 ) ) / 100, 2 );
                $pstatus = $p['status'] ?? 'pending';
                $pcolor  = match( $pstatus ) {
                    'paid'       => '#4ade80',
                    'failed'     => '#f87171',
                    'processing' => '#60a5fa',
                    default      => '#fbbf24',
                };
                $period = '';
                if ( ! empty( $p['period_start'] ) && ! empty( $p['period_end'] ) ) {
                    $period = date( 'M j', strtotime( $p['period_start'] ) ) . ' – ' . date( 'M j, Y', strtotime( $p['period_end'] ) );
                }
                $pdate = ! empty( $p['created_at'] ) ? date( 'M j, Y', strtotime( $p['created_at'] ) ) : '—';
            ?>
                <tr style="border-bottom:1px solid rgba(201,168,76,.08);color:rgba(240,232,255,.88);font-family:'Poppins',sans-serif;">
                    <td style="padding:8px 10px;font-weight:600;"><?php echo esc_html( $amt ); ?></td>
                    <td style="padding:8px 10px;">
                        <span style="color:<?php echo esc_attr( $pcolor ); ?>;font-weight:600;">
                            <?php echo esc_html( ucfirst( $pstatus ) ); ?>
                        </span>
                    </td>
                    <td style="padding:8px 10px;"><?php echo esc_html( $period ); ?></td>
                    <td style="padding:8px 10px;"><?php echo esc_html( $pdate ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}

/* ────────────────────────────────────────────────────────────────────────── */
/* SECTION 3 — WP-Cron: monthly credit run                                   */
/* ────────────────────────────────────────────────────────────────────────── */

add_filter( 'cron_schedules', 'excreet_299_cron_schedules' );
function excreet_299_cron_schedules( array $schedules ): array {
    $schedules['ex299_biweekly'] = [
        'interval' => 14 * DAY_IN_SECONDS,
        'display'  => 'Every 2 Weeks',
    ];
    return $schedules;
}

register_activation_hook( __FILE__, 'excreet_299_schedule_crons' );
function excreet_299_schedule_crons(): void {
    if ( ! wp_next_scheduled( 'ex299_monthly_credit' ) ) {
        wp_schedule_event( strtotime( 'first day of next month midnight' ), 'monthly', 'ex299_monthly_credit' );
    }
    if ( ! wp_next_scheduled( 'ex299_biweekly_payout' ) ) {
        wp_schedule_event( time() + DAY_IN_SECONDS, 'ex299_biweekly', 'ex299_biweekly_payout' );
    }
}

register_deactivation_hook( __FILE__, 'excreet_299_clear_crons' );
function excreet_299_clear_crons(): void {
    wp_clear_scheduled_hook( 'ex299_monthly_credit' );
    wp_clear_scheduled_hook( 'ex299_biweekly_payout' );
}

// Since mu-plugins can't use register_activation_hook, schedule on init if not yet scheduled
add_action( 'init', 'excreet_299_ensure_crons' );
function excreet_299_ensure_crons(): void {
    if ( ! wp_next_scheduled( 'ex299_monthly_credit' ) ) {
        wp_schedule_event( strtotime( 'first day of next month midnight' ), 'monthly', 'ex299_monthly_credit' );
    }
    if ( ! wp_next_scheduled( 'ex299_biweekly_payout' ) ) {
        wp_schedule_event( time() + DAY_IN_SECONDS, 'ex299_biweekly', 'ex299_biweekly_payout' );
    }
}

add_action( 'ex299_monthly_credit', 'excreet_299_run_monthly_credit' );
function excreet_299_run_monthly_credit(): void {
    // Collect all currently active PMPro member IDs
    global $wpdb;
    $rows = $wpdb->get_col(
        "SELECT DISTINCT user_id FROM {$wpdb->prefix}pmpro_memberships_users
         WHERE status = 'active'"
    );

    if ( empty( $rows ) ) {
        return;
    }

    $active_ids = array_map( 'strval', $rows );

    $url = excreet_299_hermes_url( 'api/hermes/affiliate/credit/batch' );
    $response = wp_remote_post( $url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . excreet_299_hermes_key(),
        ],
        'body'    => wp_json_encode( [ 'active_member_ids' => $active_ids ] ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( '[EX299] Monthly credit cron error: ' . $response->get_error_message() );
    }
}

/* ────────────────────────────────────────────────────────────────────────── */
/* SECTION 4 — WP-Cron: bi-weekly payout trigger                             */
/* ────────────────────────────────────────────────────────────────────────── */

add_action( 'ex299_biweekly_payout', 'excreet_299_run_biweekly_payout' );
function excreet_299_run_biweekly_payout(): void {
    $period_end   = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
    $period_start = ( clone $period_end )->modify( '-14 days' );

    $url = excreet_299_hermes_url( 'api/hermes/affiliate/payout/trigger' );
    $response = wp_remote_post( $url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . excreet_299_hermes_key(),
        ],
        'body'    => wp_json_encode( [
            'period_start' => $period_start->format( 'c' ),
            'period_end'   => $period_end->format( 'c' ),
        ] ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( '[EX299] Bi-weekly payout cron error: ' . $response->get_error_message() );
    }
}

/* ────────────────────────────────────────────────────────────────────────── */
/* SECTION 5 — Admin: W-9 confirmation tool                                  */
/* ────────────────────────────────────────────────────────────────────────── */

/**
 * Adds a "W-9 Received" button to the WP user edit screen so an admin
 * can mark a member's W-9 as completed once the physical/emailed form arrives.
 */
add_action( 'show_user_profile',    'excreet_299_user_w9_field' );
add_action( 'edit_user_profile',    'excreet_299_user_w9_field' );
function excreet_299_user_w9_field( WP_User $user ): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <h2>Excreet Affiliate</h2>
    <table class="form-table">
        <tr>
            <th>W-9 Status</th>
            <td>
                <form method="post" action="">
                    <?php wp_nonce_field( 'ex299_w9_complete_' . $user->ID, 'ex299_w9_nonce' ); ?>
                    <input type="hidden" name="ex299_user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
                    <button type="submit" name="ex299_mark_w9" class="button button-secondary">
                        Mark W-9 as Received &amp; Completed
                    </button>
                    <span class="description" style="margin-left:8px;">
                        Only click after you have physically received and verified the member's W-9.
                    </span>
                </form>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'admin_init', 'excreet_299_handle_w9_complete' );
function excreet_299_handle_w9_complete(): void {
    if ( ! isset( $_POST['ex299_mark_w9'] ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $user_id = (int) ( $_POST['ex299_user_id'] ?? 0 );
    if ( ! wp_verify_nonce( $_POST['ex299_w9_nonce'] ?? '', 'ex299_w9_complete_' . $user_id ) ) {
        wp_die( 'Security check failed.' );
    }

    $url = excreet_299_hermes_url( 'api/hermes/affiliate/w9/complete' );
    wp_remote_post( $url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . excreet_299_hermes_key(),
        ],
        'body'    => wp_json_encode( [ 'member_id' => (string) $user_id ] ),
        'timeout' => 8,
    ] );
}

/* ────────────────────────────────────────────────────────────────────────── */
/* SECTION 6 — [excreet_member_count] shortcode (bonus)                      */
/* ────────────────────────────────────────────────────────────────────────── */

add_shortcode( 'excreet_member_count', 'excreet_299_member_count_shortcode' );
function excreet_299_member_count_shortcode(): string {
    $count = get_transient( 'ex299_member_count' );

    if ( false === $count ) {
        global $wpdb;
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}pmpro_memberships_users WHERE status = 'active'"
        );
        set_transient( 'ex299_member_count', $count, 6 * HOUR_IN_SECONDS );
    }

    return '<span class="ex299-member-count">' . number_format( (int) $count ) . ' members and growing</span>';
}
