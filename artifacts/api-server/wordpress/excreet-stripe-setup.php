<?php
/**
 * Plugin Name: Excreet Stripe Setup
 * Version: 1.0.0
 * Description: One-time admin tool — re-enter Stripe secret key, fetch webhook signing secret, remove dead MemberPress webhook.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_management_page(
        'Excreet Stripe Setup',
        'Excreet Stripe Setup',
        'manage_options',
        'excreet-stripe-setup',
        'excreet_stripe_setup_render'
    );
} );

function excreet_stripe_setup_api( string $sk, string $method, string $path ): array {
    $resp = wp_remote_request(
        'https://api.stripe.com/v1/' . ltrim( $path, '/' ),
        [
            'method'  => strtoupper( $method ),
            'timeout' => 20,
            'headers' => [ 'Authorization' => 'Basic ' . base64_encode( $sk . ':' ) ],
        ]
    );
    if ( is_wp_error( $resp ) ) {
        return [ '_err' => $resp->get_error_message() ];
    }
    return json_decode( wp_remote_retrieve_body( $resp ), true ) ?? [];
}

function excreet_stripe_setup_render(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );

    $pmpro_wh_id = 'we_1TXWJlC6Syuriyojbes2MV';
    $msgs        = [];
    $done        = false;

    if (
        isset( $_POST['_ex_ss_nonce'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ex_ss_nonce'] ) ), 'ex_stripe_setup' )
    ) {
        $sk = trim( sanitize_text_field( wp_unslash( $_POST['stripe_sk'] ?? '' ) ) );

        if ( strpos( $sk, 'sk_live_' ) !== 0 ) {
            $msgs[] = [ 'error', 'Key must start with sk_live_' ];
        } else {
            // 1 — Validate key
            $acct = excreet_stripe_setup_api( $sk, 'GET', 'account' );
            if ( ! empty( $acct['_err'] ) || ! empty( $acct['error'] ) ) {
                $msgs[] = [ 'error', 'Stripe rejected the key: ' . ( $acct['error']['message'] ?? $acct['_err'] ?? 'unknown' ) ];
            } else {
                // 2 — Save secret key to PMPro
                update_option( 'pmpro_stripe_secretkey', $sk );
                $msgs[] = [ 'ok', 'Secret key saved to PMPro (' . strlen( $sk ) . ' chars).' ];

                // 3 — Fetch & save webhook signing secret
                $wh = excreet_stripe_setup_api( $sk, 'GET', 'webhook_endpoints/' . $pmpro_wh_id );
                if ( ! empty( $wh['secret'] ) ) {
                    update_option( 'pmpro_stripe_webhook', $wh['secret'] );
                    $msgs[] = [ 'ok', 'Webhook signing secret fetched and saved.' ];
                } else {
                    $msgs[] = [ 'warn', 'Could not fetch webhook secret: ' . wp_json_encode( $wh ) ];
                }

                // 4 — Delete dead MemberPress webhook(s)
                $list    = excreet_stripe_setup_api( $sk, 'GET', 'webhook_endpoints?limit=20' );
                $deleted = false;
                foreach ( $list['data'] ?? [] as $ep ) {
                    if ( strpos( $ep['url'] ?? '', 'mepr' ) !== false ) {
                        $del = excreet_stripe_setup_api( $sk, 'DELETE', 'webhook_endpoints/' . $ep['id'] );
                        if ( ! empty( $del['deleted'] ) ) {
                            $msgs[] = [ 'ok', 'Deleted dead MemberPress webhook (' . $ep['id'] . ').' ];
                            $deleted = true;
                        } else {
                            $msgs[] = [ 'warn', 'Could not delete ' . $ep['id'] . ': ' . wp_json_encode( $del ) ];
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

    $cur_len = strlen( get_option( 'pmpro_stripe_secretkey', '' ) );
    $cur_wh  = get_option( 'pmpro_stripe_webhook', '' );
    ?>
    <div class="wrap">
        <h1>Excreet — Stripe Setup</h1>

        <?php if ( $done ) : ?>
        <div class="notice notice-success"><p><strong>All done.</strong> Stripe is fully configured. You can remove this plugin file from mu-plugins when convenient.</p></div>
        <?php endif; ?>

        <?php foreach ( $msgs as [ $t, $m ] ) :
            $cls = $t === 'ok' ? 'notice-success' : ( $t === 'warn' ? 'notice-warning' : 'notice-error' ); ?>
        <div class="notice <?php echo esc_attr( $cls ); ?>"><p><?php echo esc_html( $m ); ?></p></div>
        <?php endforeach; ?>

        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:3px;padding:24px;max-width:620px;margin-top:20px;">
            <h2 style="margin-top:0;font-size:16px;">Current state</h2>
            <table class="widefat striped" style="margin-bottom:24px;">
                <tr>
                    <th>Secret key stored in PMPro</th>
                    <td><?php echo $cur_len > 0
                        ? '<span style="color:green">✅ ' . esc_html( $cur_len ) . ' chars</span>'
                        : '<span style="color:red">❌ Missing</span>'; ?></td>
                </tr>
                <tr>
                    <th>Webhook signing secret</th>
                    <td><?php echo ! empty( $cur_wh )
                        ? '<span style="color:green">✅ Set</span>'
                        : '<span style="color:orange">⚠ Empty</span>'; ?></td>
                </tr>
            </table>

            <h2 style="font-size:16px;margin-top:0;">Enter your Stripe live secret key</h2>
            <p style="color:#555;margin-top:0;">
                Open <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe → Developers → API keys</a> in another tab.<br>
                Click <strong>Reveal</strong> next to <code>sk_live_&hellip;LWxJ</code>. Copy the full key and paste it below.
            </p>

            <form method="post">
                <?php wp_nonce_field( 'ex_stripe_setup', '_ex_ss_nonce' ); ?>
                <label for="stripe_sk" style="display:block;font-weight:600;margin-bottom:6px;">Stripe Live Secret Key</label>
                <input
                    type="password"
                    id="stripe_sk"
                    name="stripe_sk"
                    style="width:100%;font-family:monospace;padding:7px 9px;border:1px solid #8c8f94;border-radius:3px;box-sizing:border-box;"
                    placeholder="sk_live_..."
                    autocomplete="new-password"
                    required
                />
                <p style="color:#777;font-size:12px;margin:6px 0 18px;">Saved directly to your WordPress database — not sent anywhere else.</p>
                <input type="submit" class="button button-primary button-large" value="Save Key &amp; Fix Everything →" />
            </form>

            <p style="color:#aaa;font-size:11px;margin-top:20px;line-height:1.5;">
                On submit this tool will: validate the key with Stripe → save it to PMPro → fetch &amp; store the webhook signing secret → delete any dead MemberPress webhooks. One click, nothing else required.
            </p>
        </div>
    </div>
    <?php
}
