<?php
/**
 * Excreet Stripe Fix — Standalone (no WP admin required)
 * Access at: https://excreet.com/?excreet_stripe_fix=1
 *
 * Deploy to WP root as: excreet-stripe-fix.php
 * Then visit: https://excreet.com/excreet-stripe-fix.php
 */

// Simple access token — change after use
define( 'ACCESS_TOKEN', 'excreet2026fix' );

$token_ok = ( $_GET['token'] ?? '' ) === ACCESS_TOKEN || ( $_POST['token'] ?? '' ) === ACCESS_TOKEN;

if ( ! $token_ok ) {
    http_response_code( 403 );
    die( 'Not found.' );
}

// Load WordPress (DB access only — no plugins)
define( 'SHORTINIT', true );
$wp_root = __DIR__;
require_once $wp_root . '/wp-load.php';

$pmpro_wh_id = 'we_1TXWJlC6Syuriyojbes2MV';
$msgs        = [];
$done        = false;

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['stripe_sk'] ) ) {
    $sk = trim( $_POST['stripe_sk'] ?? '' );

    if ( strpos( $sk, 'sk_live_' ) !== 0 ) {
        $msgs[] = [ 'error', 'Key must start with sk_live_' ];
    } else {
        // 1 — Validate key against Stripe
        $ch = curl_init( 'https://api.stripe.com/v1/account' );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERPWD        => $sk . ':',
        ] );
        $body = curl_exec( $ch );
        $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );
        $acct = json_decode( $body, true );

        if ( $code !== 200 ) {
            $err    = $acct['error']['message'] ?? 'HTTP ' . $code;
            $msgs[] = [ 'error', 'Stripe rejected the key: ' . $err ];
        } else {
            // 2 — Save secret key to wp_options directly
            global $wpdb;
            $wpdb->update( $wpdb->options, [ 'option_value' => $sk ], [ 'option_name' => 'pmpro_stripe_secretkey' ] );
            $msgs[] = [ 'ok', 'Secret key saved (' . strlen( $sk ) . ' chars, Stripe account: ' . ( $acct['id'] ?? '?' ) . ').' ];

            // 3 — Fetch webhook signing secret
            $ch2 = curl_init( 'https://api.stripe.com/v1/webhook_endpoints/' . $pmpro_wh_id );
            curl_setopt_array( $ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_USERPWD        => $sk . ':',
            ] );
            $wh_body = curl_exec( $ch2 );
            $wh_code = curl_getinfo( $ch2, CURLINFO_HTTP_CODE );
            curl_close( $ch2 );
            $wh = json_decode( $wh_body, true );

            if ( ! empty( $wh['secret'] ) ) {
                $wpdb->update( $wpdb->options, [ 'option_value' => $wh['secret'] ], [ 'option_name' => 'pmpro_stripe_webhook' ] );
                $msgs[] = [ 'ok', 'Webhook signing secret fetched and saved.' ];
            } else {
                $msgs[] = [ 'warn', 'Could not fetch webhook secret (code ' . $wh_code . '): ' . htmlspecialchars( $wh_body ) ];
            }

            // 4 — Delete dead MemberPress webhook(s)
            $ch3 = curl_init( 'https://api.stripe.com/v1/webhook_endpoints?limit=20' );
            curl_setopt_array( $ch3, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_USERPWD        => $sk . ':',
            ] );
            $list     = json_decode( curl_exec( $ch3 ), true );
            curl_close( $ch3 );
            $deleted = false;
            foreach ( $list['data'] ?? [] as $ep ) {
                if ( strpos( $ep['url'] ?? '', 'mepr' ) !== false ) {
                    $ch4 = curl_init( 'https://api.stripe.com/v1/webhook_endpoints/' . $ep['id'] );
                    curl_setopt_array( $ch4, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST  => 'DELETE',
                        CURLOPT_TIMEOUT        => 20,
                        CURLOPT_USERPWD        => $sk . ':',
                    ] );
                    $del = json_decode( curl_exec( $ch4 ), true );
                    curl_close( $ch4 );
                    if ( ! empty( $del['deleted'] ) ) {
                        $msgs[] = [ 'ok', 'Deleted dead MemberPress webhook (' . $ep['id'] . ').' ];
                        $deleted = true;
                    }
                }
            }
            if ( ! $deleted ) {
                $msgs[] = [ 'ok', 'No MemberPress webhooks found (already removed).' ];
            }

            $done = true;
        }
    }
}

// Read current state
global $wpdb;
$cur_sk_len = strlen( $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='pmpro_stripe_secretkey'" ) ?? '' );
$cur_wh     = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='pmpro_stripe_webhook'" ) ?? '';

?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Excreet Stripe Fix</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f0f1;margin:0;padding:40px 20px;}
  .card{background:#fff;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.15);padding:32px;max-width:580px;margin:0 auto;}
  h1{font-size:22px;margin:0 0 24px;}
  .ok{background:#d1e7dd;border:1px solid #a3cfbb;color:#0a3622;padding:10px 14px;border-radius:4px;margin:8px 0;}
  .error{background:#f8d7da;border:1px solid #f1aeb5;color:#58151c;padding:10px 14px;border-radius:4px;margin:8px 0;}
  .warn{background:#fff3cd;border:1px solid #ffe69c;color:#664d03;padding:10px 14px;border-radius:4px;margin:8px 0;}
  .done{background:#d1e7dd;border:2px solid #0a3622;color:#0a3622;padding:16px;border-radius:4px;margin:16px 0;font-weight:600;font-size:15px;}
  table{width:100%;border-collapse:collapse;margin-bottom:24px;}
  td,th{padding:8px 12px;text-align:left;border-bottom:1px solid #eee;}
  th{font-weight:600;width:55%;}
  label{display:block;font-weight:600;margin-bottom:6px;}
  input[type=password]{width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;font-family:monospace;box-sizing:border-box;font-size:13px;}
  button{background:#2271b1;color:#fff;border:none;padding:10px 20px;border-radius:4px;font-size:15px;cursor:pointer;margin-top:12px;}
  button:hover{background:#135e96;}
  small{color:#888;font-size:12px;}
</style>
</head>
<body>
<div class="card">
  <h1>Excreet — Stripe Fix</h1>

  <?php if ( $done ) : ?>
    <div class="done">✅ All done. Stripe is fully configured. Delete this file from your server when ready.</div>
  <?php endif; ?>

  <?php foreach ( $msgs as [ $t, $m ] ) : ?>
    <div class="<?= $t ?>"><?= htmlspecialchars( $m ) ?></div>
  <?php endforeach; ?>

  <table>
    <tr><th>Secret key in PMPro</th><td><?= $cur_sk_len > 0 ? "✅ {$cur_sk_len} chars" : '<span style="color:red">❌ Missing</span>' ?></td></tr>
    <tr><th>Webhook signing secret</th><td><?= ! empty( $cur_wh ) ? '✅ Set' : '<span style="color:orange">⚠ Empty</span>' ?></td></tr>
  </table>

  <?php if ( ! $done ) : ?>
  <p style="margin-top:0;color:#444;">
    Open <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe → API keys</a> in another tab.<br>
    Click <strong>Reveal</strong> on <code>sk_live_…LWxJ</code> (or create a new key with <em>"Powering an integration you built"</em>).<br>
    Copy the full key and paste it below.
  </p>
  <form method="post">
    <input type="hidden" name="token" value="<?= htmlspecialchars( ACCESS_TOKEN ) ?>">
    <label for="sk">Stripe Live Secret Key</label>
    <input type="password" id="sk" name="stripe_sk" placeholder="sk_live_..." autocomplete="new-password" required>
    <br><small>Sent only to your own server — never to this chat or anywhere else.</small>
    <br><button type="submit">Save Key &amp; Fix Everything</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
