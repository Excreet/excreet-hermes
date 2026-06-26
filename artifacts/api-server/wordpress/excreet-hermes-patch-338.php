<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.3.8
 * Description: Email Notification System — four branded transactional emails.
 *
 *   A — Welcome email          fires on pmpro_after_checkout
 *   B — Body Score ready       fires via wp_ajax_excreet_email_score (JS calls
 *                              after job-status returns a result)
 *   C — Referral credit earned fires when a referral clears the 30-day hold
 *                              (hooks into ex299_monthly_credit cron result)
 *   D — Payout notification    fires when a payout record is created for a member
 *
 *   All emails use wp_mail() with full HTML templates in the Excreet
 *   botanical dark-card palette. Content-Type header set to text/html.
 *   From address: no-reply@excreet.com (overridden via wp_mail_from filter).
 *
 * Version: 3.3.8
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── Shared: from address ────────────────────────────────────────────────── */
add_filter( 'wp_mail_from',      fn() => 'no-reply@excreet.com' );
add_filter( 'wp_mail_from_name', fn() => 'Excreet' );

/* ── Shared: HTML email wrapper ──────────────────────────────────────────── */
function excreet_338_wrap( string $body_html ): string {
    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Excreet</title>
</head>
<body style="margin:0;padding:0;background:#f3eeff;font-family:Georgia,\'Times New Roman\',serif;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr><td align="center" style="padding:32px 16px;">

  <table width="600" cellpadding="0" cellspacing="0" role="presentation"
         style="max-width:600px;width:100%;background:#0c0115;border-radius:16px;overflow:hidden;box-shadow:0 8px 48px rgba(0,0,0,.45);">

    <!-- Header -->
    <tr>
      <td style="background:linear-gradient(135deg,#1a0535 0%,#3D1060 50%,#1a0535 100%);padding:32px 40px;text-align:center;border-bottom:1px solid rgba(201,168,76,.25);">
        <div style="font-family:Georgia,serif;font-size:22px;font-weight:700;color:#C9A84C;letter-spacing:8px;text-transform:uppercase;">EXCREET</div>
        <div style="font-size:10px;letter-spacing:5px;color:rgba(201,168,76,.55);text-transform:uppercase;margin-top:4px;">CLEANS &nbsp; COMPLETE</div>
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:36px 40px;color:#f0e8ff;">
        ' . $body_html . '
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="border-top:1px solid rgba(201,168,76,.15);padding:20px 40px;text-align:center;">
        <p style="font-size:11px;color:rgba(255,255,255,.25);margin:0 0 6px;font-family:Arial,sans-serif;">
          You received this because you\'re an Excreet member.
        </p>
        <p style="font-size:11px;margin:0;font-family:Arial,sans-serif;">
          <a href="https://excreet.com/membership-account/" style="color:#C9A84C;text-decoration:none;">Manage account</a>
          &nbsp;·&nbsp;
          <a href="https://excreet.com/member-dashboard/" style="color:#C9A84C;text-decoration:none;">Dashboard</a>
        </p>
      </td>
    </tr>

  </table>
</td></tr>
</table>
</body>
</html>';
}

function excreet_338_send( string $to, string $subject, string $body_html ): void {
    wp_mail(
        $to,
        $subject,
        excreet_338_wrap( $body_html ),
        [ 'Content-Type: text/html; charset=UTF-8' ]
    );
}

/* ════════════════════════════════════════════════════════════════════════════
   A — WELCOME EMAIL  (after checkout)
   ════════════════════════════════════════════════════════════════════════════ */
add_action( 'pmpro_after_checkout', 'excreet_338_welcome_email', 30, 2 );
function excreet_338_welcome_email( int $user_id, $morder ): void {
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) { return; }

    $first       = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
    $email       = $user->user_email;
    $level_id    = isset( $morder->membership_id ) ? (int) $morder->membership_id : 1;
    $tier        = $level_id === 2 ? 'Premium' : 'Starter';
    $price       = $level_id === 2 ? '$25/mo'  : '$15/mo';
    $earn        = $level_id === 2 ? '$10'      : '$5';
    $sessions    = $level_id === 2 ? 20         : 10;
    $accent      = $level_id === 2 ? '#a78bfa'  : '#C9A84C';
    $code        = (string) $user_id;

    $body = '
<h1 style="font-size:28px;font-weight:700;color:#ffffff;margin:0 0 6px;line-height:1.2;">You\'re in, ' . esc_html( $first ) . '.</h1>
<p style="font-size:13px;color:rgba(255,255,255,.45);margin:0 0 28px;font-family:Arial,sans-serif;line-height:1.6;">
  Your body has been speaking. Now you have the intelligence to hear it.
</p>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.25);border-radius:12px;margin-bottom:28px;">
<tr><td style="padding:20px 24px;text-align:center;">
  <div style="font-size:10px;font-family:Arial,sans-serif;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:rgba(201,168,76,.7);margin-bottom:8px;">Membership Confirmed</div>
  <div style="font-size:20px;font-weight:700;color:' . esc_attr( $accent ) . ';letter-spacing:1px;">' . esc_html( $tier ) . ' &nbsp;·&nbsp; ' . esc_html( $price ) . '</div>
</td></tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:rgba(12,2,26,.80);border:1px solid rgba(201,168,76,.3);border-radius:12px;margin-bottom:28px;">
<tr><td style="padding:24px;text-align:center;">
  <div style="font-size:10px;font-family:Arial,sans-serif;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:rgba(201,168,76,.7);margin-bottom:8px;">Your Affiliate Referral Code</div>
  <div style="font-size:48px;font-weight:700;color:#C9A84C;letter-spacing:6px;line-height:1;">' . esc_html( $code ) . '</div>
  <p style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.45);margin:10px 0 0;line-height:1.6;">
    Every member you refer earns you <strong style="color:#C9A84C;">' . esc_html( $earn ) . ' per month</strong> while both are active.<br>
    Share your code wherever you talk about health.
  </p>
</td></tr>
</table>

<p style="font-size:11px;font-family:Arial,sans-serif;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:rgba(201,168,76,.6);margin:0 0 14px;">Your First Three Moves</p>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:10px;">
<tr>
  <td style="background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);border-radius:10px;padding:16px 20px;">
    <div style="font-size:10px;font-family:Arial,sans-serif;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#C9A84C;margin-bottom:4px;">Step 1 — Body Check</div>
    <div style="font-size:15px;color:#ffffff;font-weight:700;margin-bottom:4px;">Take your first Body Snapshot</div>
    <div style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.45);line-height:1.5;">3 minutes. Establishes your baseline Body Score.</div>
    <div style="margin-top:10px;"><a href="https://excreet.com/healing-command-center/" style="display:inline-block;background:#C9A84C;color:#0c0115;font-family:Arial,sans-serif;font-size:12px;font-weight:700;padding:8px 18px;border-radius:6px;text-decoration:none;letter-spacing:.5px;">Start Body Check →</a></div>
  </td>
</tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:10px;">
<tr>
  <td style="background:rgba(107,47,160,.12);border:1px solid rgba(107,47,160,.3);border-radius:10px;padding:16px 20px;">
    <div style="font-size:10px;font-family:Arial,sans-serif;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#a78bfa;margin-bottom:4px;">Step 2 — Ministry of Healing</div>
    <div style="font-size:15px;color:#ffffff;font-weight:700;margin-bottom:4px;">Meet your AI health companion</div>
    <div style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.45);line-height:1.5;">Ask anything. It knows your profile. ' . (int) $sessions . ' sessions / month.</div>
    <div style="margin-top:10px;"><a href="https://excreet.com/ministry-of-healing/" style="display:inline-block;background:#7b3fc4;color:#fff;font-family:Arial,sans-serif;font-size:12px;font-weight:700;padding:8px 18px;border-radius:6px;text-decoration:none;letter-spacing:.5px;">Open Ministry →</a></div>
  </td>
</tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:28px;">
<tr>
  <td style="background:rgba(61,16,96,.20);border:1px solid rgba(61,16,96,.4);border-radius:10px;padding:16px 20px;">
    <div style="font-size:10px;font-family:Arial,sans-serif;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:rgba(201,168,76,.6);margin-bottom:4px;">Step 3 — Dashboard</div>
    <div style="font-size:15px;color:#ffffff;font-weight:700;margin-bottom:4px;">Your Healing Command Center</div>
    <div style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.45);line-height:1.5;">Body Score trends, session history, affiliate earnings.</div>
    <div style="margin-top:10px;"><a href="https://excreet.com/member-dashboard/" style="display:inline-block;background:rgba(61,16,96,.6);border:1px solid rgba(201,168,76,.4);color:#C9A84C;font-family:Arial,sans-serif;font-size:12px;font-weight:700;padding:8px 18px;border-radius:6px;text-decoration:none;letter-spacing:.5px;">View Dashboard →</a></div>
  </td>
</tr>
</table>

<p style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.35);margin:0;line-height:1.6;text-align:center;">
  Questions? Reply to this email or visit <a href="https://excreet.com/ministry-of-healing/" style="color:#C9A84C;text-decoration:none;">Ministry of Healing</a> any time.
</p>';

    excreet_338_send(
        $email,
        'Welcome to Excreet, ' . $first . ' — You\'re in.',
        $body
    );
}

/* ════════════════════════════════════════════════════════════════════════════
   B — BODY SCORE READY  (JS calls wp-ajax after job-status returns result)
   ════════════════════════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_excreet_email_score',        'excreet_338_ajax_score_email' );
add_action( 'wp_ajax_nopriv_excreet_email_score', 'excreet_338_ajax_score_email' );

function excreet_338_ajax_score_email(): void {
    // Lightweight nonce-free endpoint; score data is non-sensitive.
    // Rate-limited by the fact that it only fires once per job result.
    $user_id = (int) ( $_POST['user_id'] ?? 0 );
    $score   = sanitize_text_field( wp_unslash( $_POST['score']   ?? '' ) );
    $delta   = sanitize_text_field( wp_unslash( $_POST['delta']   ?? '' ) );
    $label   = sanitize_text_field( wp_unslash( $_POST['label']   ?? 'Your Body Score' ) );

    if ( $user_id <= 0 || $score === '' ) {
        wp_send_json_error( 'missing_params' );
    }

    // Deduplicate: only email once per job (use 10-min transient)
    $key = 'ex338_score_sent_' . $user_id . '_' . md5( $score );
    if ( get_transient( $key ) ) {
        wp_send_json_success( 'already_sent' );
    }
    set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );

    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) { wp_send_json_error( 'user_not_found' ); }

    $first = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
    $delta_display = '';
    if ( $delta !== '' && $delta !== '0' ) {
        $sign  = (float) $delta > 0 ? '▲ +' : '▼ ';
        $color = (float) $delta > 0 ? '#4ade80' : '#f87171';
        $delta_display = '<span style="font-size:14px;color:' . esc_attr( $color ) . ';font-family:Arial,sans-serif;"> ' . $sign . esc_html( $delta ) . '</span>';
    }

    $body = '
<h1 style="font-size:26px;font-weight:700;color:#ffffff;margin:0 0 6px;">Your Body Score is ready, ' . esc_html( $first ) . '.</h1>
<p style="font-size:13px;color:rgba(255,255,255,.45);margin:0 0 28px;font-family:Arial,sans-serif;line-height:1.6;">
  Excreet has analysed your latest Body Check submission.
</p>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.3);border-radius:14px;margin-bottom:28px;">
<tr><td style="padding:28px;text-align:center;">
  <div style="font-size:10px;font-family:Arial,sans-serif;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:rgba(201,168,76,.7);margin-bottom:10px;">' . esc_html( $label ) . '</div>
  <div style="font-size:64px;font-weight:700;color:#C9A84C;line-height:1;letter-spacing:2px;">' . esc_html( $score ) . $delta_display . '</div>
  <div style="margin-top:20px;"><a href="https://excreet.com/member-dashboard/" style="display:inline-block;background:#C9A84C;color:#0c0115;font-family:Arial,sans-serif;font-size:13px;font-weight:700;padding:10px 24px;border-radius:8px;text-decoration:none;letter-spacing:.5px;">View Full Report →</a></div>
</td></tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:rgba(107,47,160,.12);border:1px solid rgba(107,47,160,.25);border-radius:10px;margin-bottom:28px;">
<tr><td style="padding:16px 20px;">
  <p style="margin:0;font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.5);line-height:1.7;">
    Your score reflects patterns in your body\'s current state — not a diagnosis. For questions about what it means, ask your <a href="https://excreet.com/ministry-of-healing/" style="color:#a78bfa;text-decoration:none;">Ministry of Healing companion</a> in plain language.
  </p>
</td></tr>
</table>

<p style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.35);margin:0;line-height:1.6;text-align:center;">
  Keep checking in daily — your trend data builds over time.
</p>';

    excreet_338_send(
        $user->user_email,
        'Your Excreet Body Score is ready',
        $body
    );

    wp_send_json_success( 'sent' );
}

/* ════════════════════════════════════════════════════════════════════════════
   C — REFERRAL CREDIT EARNED  (hooks into ex299 monthly cron via filter)
   ════════════════════════════════════════════════════════════════════════════ */
add_action( 'excreet_referral_cleared', 'excreet_338_referral_cleared_email', 10, 3 );
function excreet_338_referral_cleared_email( int $referrer_id, int $referred_id, int $level ): void {
    // House account never gets credit emails
    if ( (int) get_option( '_excreet_336_done', 0 ) === $referrer_id ) { return; }

    $referrer = get_user_by( 'id', $referrer_id );
    $referred = get_user_by( 'id', $referred_id );
    if ( ! $referrer || ! $referred ) { return; }

    $first      = ! empty( $referrer->first_name ) ? $referrer->first_name : $referrer->display_name;
    $earn       = $level === 2 ? '$10' : '$5';
    $tier       = $level === 2 ? 'Premium' : 'Starter';
    $ref_name   = $referred->display_name;

    $body = '
<h1 style="font-size:26px;font-weight:700;color:#ffffff;margin:0 0 6px;">You earned ' . esc_html( $earn ) . ', ' . esc_html( $first ) . '.</h1>
<p style="font-size:13px;color:rgba(255,255,255,.45);margin:0 0 28px;font-family:Arial,sans-serif;line-height:1.6;">
  A referral you made has cleared its 30-day confirmation window.
</p>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.3);border-radius:12px;margin-bottom:28px;">
<tr><td style="padding:24px;">
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
      <td style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.45);padding-bottom:10px;">New member</td>
      <td style="font-size:13px;font-family:Arial,sans-serif;color:#ffffff;font-weight:600;text-align:right;padding-bottom:10px;">' . esc_html( $ref_name ) . '</td>
    </tr>
    <tr>
      <td style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.45);padding-bottom:10px;">Their plan</td>
      <td style="font-size:13px;font-family:Arial,sans-serif;color:#ffffff;font-weight:600;text-align:right;padding-bottom:10px;">' . esc_html( $tier ) . '</td>
    </tr>
    <tr>
      <td style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.45);border-top:1px solid rgba(201,168,76,.15);padding-top:12px;">Monthly credit earned</td>
      <td style="font-size:20px;font-family:Arial,sans-serif;color:#C9A84C;font-weight:700;text-align:right;border-top:1px solid rgba(201,168,76,.15);padding-top:12px;">' . esc_html( $earn ) . ' / mo</td>
    </tr>
  </table>
</td></tr>
</table>

<p style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.45);margin:0 0 20px;line-height:1.7;">
  This credit will be added to your affiliate balance on the 1st of each month while both memberships remain active. Once your balance reaches <strong style="color:#C9A84C;">$50</strong>, it becomes eligible for a bi-weekly payout.
</p>

<div style="text-align:center;"><a href="https://excreet.com/affiliate-area/" style="display:inline-block;background:#C9A84C;color:#0c0115;font-family:Arial,sans-serif;font-size:13px;font-weight:700;padding:10px 24px;border-radius:8px;text-decoration:none;letter-spacing:.5px;">View Affiliate Dashboard →</a></div>';

    excreet_338_send(
        $referrer->user_email,
        'You earned ' . $earn . '/mo — your referral just cleared',
        $body
    );
}

/* ════════════════════════════════════════════════════════════════════════════
   D — PAYOUT NOTIFICATION  (hook: excreet_payout_created)
   ════════════════════════════════════════════════════════════════════════════ */
add_action( 'excreet_payout_created', 'excreet_338_payout_email', 10, 2 );
function excreet_338_payout_email( int $member_id, int $amount_cents ): void {
    if ( (int) get_option( '_excreet_336_done', 0 ) === $member_id ) { return; }

    $user = get_user_by( 'id', $member_id );
    if ( ! $user ) { return; }

    $first  = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
    $amount = '$' . number_format( $amount_cents / 100, 2 );

    $body = '
<h1 style="font-size:26px;font-weight:700;color:#ffffff;margin:0 0 6px;">Your payout of ' . esc_html( $amount ) . ' is on its way, ' . esc_html( $first ) . '.</h1>
<p style="font-size:13px;color:rgba(255,255,255,.45);margin:0 0 28px;font-family:Arial,sans-serif;line-height:1.6;">
  Your Excreet affiliate balance has reached the payout threshold.
</p>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.3);border-radius:14px;margin-bottom:28px;">
<tr><td style="padding:28px;text-align:center;">
  <div style="font-size:10px;font-family:Arial,sans-serif;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:rgba(201,168,76,.7);margin-bottom:10px;">Payout Amount</div>
  <div style="font-size:52px;font-weight:700;color:#C9A84C;line-height:1;">' . esc_html( $amount ) . '</div>
  <p style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.4);margin:14px 0 0;line-height:1.6;">
    Processed bi-weekly. Allow 3–5 business days for funds to appear.<br>
    Payouts require a completed W-9 on file.
  </p>
</td></tr>
</table>

<p style="font-size:12px;font-family:Arial,sans-serif;color:rgba(255,255,255,.45);margin:0 0 20px;line-height:1.7;">
  Keep sharing your referral code to grow your monthly earnings. The more active members you bring in, the larger your bi-weekly payout becomes.
</p>

<div style="text-align:center;"><a href="https://excreet.com/affiliate-area/" style="display:inline-block;background:#C9A84C;color:#0c0115;font-family:Arial,sans-serif;font-size:13px;font-weight:700;padding:10px 24px;border-radius:8px;text-decoration:none;letter-spacing:.5px;">View Affiliate Dashboard →</a></div>';

    excreet_338_send(
        $user->user_email,
        'Your Excreet payout of ' . $amount . ' is being processed',
        $body
    );
}

/* ════════════════════════════════════════════════════════════════════════════
   Body Score email trigger — injected into job-status JS via wp_footer
   Fires after the HCC page receives a completed result from Hermes.
   ════════════════════════════════════════════════════════════════════════════ */
add_action( 'wp_footer', 'excreet_338_score_email_trigger', 99 );
function excreet_338_score_email_trigger(): void {
    if ( ! is_user_logged_in() ) { return; }
    // Only on the HCC / Body Check page (page 230 or healing-command-center slug)
    if ( ! is_page( [ 'healing-command-center', 230 ] ) ) { return; }

    $uid = get_current_user_id();
    ?>
<script>
(function () {
    // Hook into the existing storeResultV2 / job-status flow.
    // After a score result is received, fire a one-shot AJAX to send the email.
    var _origFetch = window.fetch;
    window.fetch = function (url, opts) {
        var p = _origFetch.apply(this, arguments);
        if (typeof url === 'string' && url.indexOf('job-status') !== -1) {
            p.then(function (res) {
                return res.clone().json().catch(function () { return null; });
            }).then(function (data) {
                if (!data || !data.result || !data.result.bodyScore) { return; }
                var score = data.result.bodyScore;
                var delta = data.result.scoreDelta || '';
                var label = data.result.scoreLabel || 'Your Body Score';
                var sent  = sessionStorage.getItem('ex338_score_emailed_<?php echo (int) $uid; ?>');
                if (sent) { return; }
                sessionStorage.setItem('ex338_score_emailed_<?php echo (int) $uid; ?>', '1');

                var fd = new FormData();
                fd.append('action',  'excreet_email_score');
                fd.append('user_id', '<?php echo (int) $uid; ?>');
                fd.append('score',   score);
                fd.append('delta',   delta);
                fd.append('label',   label);
                fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST', body: fd
                });
            });
        }
        return p;
    };
})();
</script>
    <?php
}
