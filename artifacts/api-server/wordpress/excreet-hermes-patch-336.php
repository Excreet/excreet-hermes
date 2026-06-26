<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.3.6
 * Description: Creates the "Excreet House" internal affiliate account and sets
 *              it as the house referrer for all unattributed signups.
 *
 *   A — Creates a WP subscriber user "Excreet House" (house@excreet.com)
 *       if it doesn't already exist. Password is random and never used.
 *
 *   B — Sets _excreet_335_house_referrer_id to that user's ID so patch-335
 *       auto-assigns blank referral codes to the house account.
 *
 *   C — Flags the house account in the affiliate system:
 *       _excreet_house_account = 1 user meta — prevents W-9 prompts,
 *       payout processing, and dashboard display for this user.
 *
 *   D — Filters the affiliate credit/batch cron and payout trigger to skip
 *       the house account so its balance never generates a real payout.
 *
 * Version: 3.3.6
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX336_HOUSE_EMAIL', 'house@excreet.com' );
define( 'EX336_HOUSE_NAME',  'Excreet House' );

/* ── A + B + C — Create house user on first init ─────────────────────────── */
add_action( 'init', 'excreet_336_ensure_house_account', 5 );
function excreet_336_ensure_house_account(): void {
    if ( get_option( '_excreet_336_done' ) ) { return; }

    $existing = get_user_by( 'email', EX336_HOUSE_EMAIL );

    if ( $existing ) {
        $house_id = $existing->ID;
    } else {
        $house_id = wp_insert_user( [
            'user_login'   => 'excreet_house',
            'user_email'   => EX336_HOUSE_EMAIL,
            'display_name' => EX336_HOUSE_NAME,
            'first_name'   => 'Excreet',
            'last_name'    => 'House',
            'role'         => 'subscriber',
            'user_pass'    => wp_generate_password( 64, true, true ),
        ] );

        if ( is_wp_error( $house_id ) ) {
            error_log( '[EX336] Failed to create house account: ' . $house_id->get_error_message() );
            return;
        }
    }

    // B — Set as house referrer for patch-335
    update_option( '_excreet_335_house_referrer_id', $house_id );

    // C — Flag as internal house account (never shown in affiliate dashboard)
    update_user_meta( $house_id, '_excreet_house_account', 1 );
    update_user_meta( $house_id, '_excreet_referrer_id', 0 );

    update_option( '_excreet_336_done', $house_id );
    error_log( '[EX336] House account ready. User ID: ' . $house_id );
}

/* ── D — Skip house account in payout and W-9 processing ────────────────── */

/**
 * Filter the monthly credit batch to exclude the house account.
 * Hooks into excreet_299_run_monthly_credit before it fires.
 */
add_filter( 'excreet_affiliate_batch_member_ids', 'excreet_336_remove_house_from_batch' );
function excreet_336_remove_house_from_batch( array $ids ): array {
    $house_id = (string) get_option( '_excreet_336_done', 0 );
    if ( $house_id && in_array( $house_id, $ids, true ) ) {
        $ids = array_values( array_diff( $ids, [ $house_id ] ) );
    }
    return $ids;
}

/**
 * Prevent W-9 prompt emails from firing for the house account.
 */
add_action( 'excreet_affiliate_w9_alert', 'excreet_336_suppress_house_w9', 1 );
function excreet_336_suppress_house_w9( int $referrer_id ): void {
    $house_id = (int) get_option( '_excreet_336_done', 0 );
    if ( $house_id && $referrer_id === $house_id ) {
        // Remove any downstream hooks that send the W-9 email
        remove_all_actions( 'excreet_affiliate_w9_alert' );
    }
}

/**
 * Admin notice confirming house account status (shown once, dismissible).
 */
add_action( 'admin_notices', 'excreet_336_admin_notice' );
function excreet_336_admin_notice(): void {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( get_option( '_excreet_336_notice_dismissed' ) ) { return; }

    $house_id   = (int) get_option( '_excreet_336_done', 0 );
    if ( ! $house_id ) { return; }

    $house_user = get_user_by( 'id', $house_id );
    if ( ! $house_user ) { return; }
    ?>
<div class="notice notice-success is-dismissible" id="ex336-notice">
    <p>
        <strong>Excreet House Account Ready</strong> —
        Unattributed signups are now credited to
        <strong><?php echo esc_html( EX336_HOUSE_NAME ); ?></strong>
        (ID <?php echo (int) $house_id; ?>, <?php echo esc_html( EX336_HOUSE_EMAIL ); ?>).
        This is an internal business account — no W-9, no payouts, no personal tax exposure.
        Credits accumulate as retained company revenue.
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=excreet-assign-referral' ) ); ?>">
            Manage referrals →
        </a>
    </p>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('ex336-notice');
    if (!el) return;
    el.querySelector('.notice-dismiss') && el.querySelector('.notice-dismiss').addEventListener('click', function () {
        fetch(ajaxurl + '?action=ex336_dismiss_notice&nonce=<?php echo esc_js( wp_create_nonce( "ex336_dismiss" ) ); ?>');
    });
});
</script>
    <?php
}

add_action( 'wp_ajax_ex336_dismiss_notice', function () {
    check_ajax_referer( 'ex336_dismiss', 'nonce' );
    update_option( '_excreet_336_notice_dismissed', 1 );
    wp_send_json_success();
} );
