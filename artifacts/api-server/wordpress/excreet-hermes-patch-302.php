<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.0.2
 * Description: PMPro Activation Helper — admin-only status dashboard.
 *
 *   Checks and completes everything needed to activate Paid Memberships Pro
 *   on Excreet.com. Shows live status for all 4 membership levels, PMPro page
 *   wiring, and Stripe gateway. Automates what it can; guides the rest.
 *
 *   Admin menu: Excreet → PMPro Activation
 *   URL: /wp-admin/admin.php?page=excreet-activation
 *
 * Version: 3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ── Constants ────────────────────────────────────────────────────────────── */

define( 'EX302_STARTER_OPT', '_excreet_302_starter_level' );

// PMPro page IDs per SITE_AUDIT.md
define( 'EX302_PAGES', serialize( [
    'loginpage'        => 875,
    'accountpage'      => 868,
    'billingpage'      => 869,
    'cancelpage'       => 870,
    'checkoutpage'     => 871,
    'confirmationpage' => 872,
    'invoicepage'      => 873,
    'levelspage'       => 874,
] ) );

/* ── Hooks ────────────────────────────────────────────────────────────────── */

add_action( 'admin_menu',              'excreet_302_admin_menu'      );
add_action( 'wp_ajax_ex302_activate',  'excreet_302_ajax_activate'   );

/* ════════════════════════════════════════════════════════════════════════════
   ADMIN MENU
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_302_admin_menu(): void {
    add_menu_page(
        'Excreet Activation',
        'Excreet Activation',
        'manage_options',
        'excreet-activation',
        'excreet_302_admin_page',
        'dashicons-heart',
        3
    );
}

/* ════════════════════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════════════════════ */

/** True if PMPro is installed and active. */
function excreet_302_pmpro_active(): bool {
    return defined( 'PMPRO_VERSION' )
        || function_exists( 'pmpro_hasMembershipLevel' )
        || class_exists( 'MemberOrder' );
}

/** True if a PMPro level exists and has a name. */
function excreet_302_level_exists( int $level_id ): bool {
    if ( $level_id <= 0 ) return false;
    $level = pmpro_getLevel( $level_id );
    return ! empty( $level->name );
}

/** True if Stripe secret key is stored in PMPro options. */
function excreet_302_stripe_configured(): bool {
    if ( ! function_exists( 'pmpro_getOption' ) ) return false;
    $gw  = pmpro_getOption( 'gateway' );
    $key = pmpro_getOption( 'stripe_secretkey' );
    return ( $gw === 'stripe' ) && ! empty( $key );
}

/** True if PMPro option matches expected page ID. */
function excreet_302_page_wired( string $option, int $expected_id ): bool {
    if ( ! function_exists( 'pmpro_getOption' ) ) return false;
    return (int) pmpro_getOption( $option ) === $expected_id;
}

/** True if PMPro level has allow_signups=0 in DB. */
function excreet_302_signups_disabled( int $level_id ): bool {
    global $wpdb;
    if ( $level_id <= 0 ) return false;
    $val = $wpdb->get_var( $wpdb->prepare(
        "SELECT allow_signups FROM {$wpdb->prefix}pmpro_membership_levels WHERE id = %d",
        $level_id
    ) );
    return $val !== null && (int) $val === 0;
}

/** Renders a ✅ / ❌ badge. */
function excreet_302_badge( bool $ok, string $ok_label = '', string $fail_label = '' ): string {
    $ok_text   = $ok_label   ?: 'OK';
    $fail_text = $fail_label ?: 'Missing';
    $color     = $ok ? '#2e7d32' : '#c62828';
    $bg        = $ok ? '#e8f5e9' : '#ffebee';
    $icon      = $ok ? '✓' : '✗';
    return "<span style='background:{$bg};color:{$color};border-radius:5px;padding:2px 9px;font-size:0.8rem;font-weight:700;'>{$icon} {$ok_text}</span>"
         . ( ! $ok ? "<span style='color:{$color};font-size:0.78rem;margin-left:6px;'>{$fail_text}</span>" : '' );
}

/* ════════════════════════════════════════════════════════════════════════════
   AJAX — Run activation
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_302_ajax_activate(): void {
    check_ajax_referer( 'ex302_activate', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    if ( ! excreet_302_pmpro_active() ) {
        wp_send_json_error( [ 'message' => 'PMPro is not active. Install and activate the plugin first.' ] );
    }

    global $wpdb;
    $log = [];

    // ── 1. Level 1 — Starter ($15/month recurring) ─────────────────────────
    $starter_id = (int) get_option( EX302_STARTER_OPT, 0 );
    if ( ! $starter_id || ! excreet_302_level_exists( $starter_id ) ) {
        $starter_id = pmpro_addMembershipLevel( [
            'name'              => 'Excreet Starter ($15/month)',
            'description'       => 'Full access to Body Check and Ministry of Healing (10 AI responses per 30-day period).',
            'confirmation'      => 'Welcome to Excreet! Your membership is now active.',
            'initial_payment'   => 0.00,
            'billing_amount'    => 15.00,
            'cycle_number'      => 1,
            'cycle_period'      => 'Month',
            'billing_limit'     => 0,
            'trial_amount'      => 0.00,
            'trial_limit'       => 0,
            'allow_signups'     => 1,
            'expiration_number' => 0,
            'expiration_period' => 'Year',
        ] );
        if ( $starter_id ) {
            update_option( EX302_STARTER_OPT, $starter_id );
            $log[] = "✓ Level 1 (Starter) created — PMPro ID #{$starter_id}";
        } else {
            $log[] = '✗ Level 1 (Starter) creation failed — check PMPro error log';
        }
    } else {
        $log[] = "→ Level 1 (Starter) already exists (ID #{$starter_id}) — skipped";
    }

    // ── 2. Level 2 — Premium ($25/month) ───────────────────────────────────
    // patch-293's ensure function handles this; clear option if level was deleted.
    $premium_id = (int) get_option( '_excreet_293_premium_product', 0 );
    if ( $premium_id && ! excreet_302_level_exists( $premium_id ) ) {
        delete_option( '_excreet_293_premium_product' );
        $premium_id = 0;
        $log[] = '→ Level 2 option pointed to deleted level — cleared, will re-create on next page load';
    }
    if ( ! $premium_id ) {
        if ( function_exists( 'excreet_293_ensure_premium_product' ) ) {
            excreet_293_ensure_premium_product();
            $premium_id = (int) get_option( '_excreet_293_premium_product', 0 );
            $log[] = $premium_id
                ? "✓ Level 2 (Premium) created — PMPro ID #{$premium_id}"
                : '✗ Level 2 (Premium) creation failed';
        } else {
            $log[] = '→ Level 2: patch-293 not loaded yet — will auto-create on next init';
        }
    } else {
        $log[] = "→ Level 2 (Premium) already exists (ID #{$premium_id}) — skipped";
    }

    // ── 3. Level 3 — Unlimited Q&A Add-On ──────────────────────────────────
    $unlimited_id = (int) get_option( '_excreet_293_unlimited_product', 0 );
    if ( $unlimited_id && ! excreet_302_level_exists( $unlimited_id ) ) {
        delete_option( '_excreet_293_unlimited_product' );
        $unlimited_id = 0;
        $log[] = '→ Level 3 option pointed to deleted level — cleared, will re-create';
    }
    if ( ! $unlimited_id ) {
        if ( function_exists( 'excreet_293_ensure_unlimited_product' ) ) {
            excreet_293_ensure_unlimited_product();
            $unlimited_id = (int) get_option( '_excreet_293_unlimited_product', 0 );
            $log[] = $unlimited_id
                ? "✓ Level 3 (Unlimited) created — PMPro ID #{$unlimited_id}"
                : '✗ Level 3 (Unlimited) creation failed';
        } else {
            $log[] = '→ Level 3: patch-293 not loaded yet — will auto-create on next init';
        }
    } else {
        $log[] = "→ Level 3 (Unlimited) already exists (ID #{$unlimited_id}) — skipped";
    }

    // ── Level 3: disable public signups ────────────────────────────────────
    if ( $unlimited_id && ! excreet_302_signups_disabled( $unlimited_id ) ) {
        $rows = $wpdb->update(
            $wpdb->prefix . 'pmpro_membership_levels',
            [ 'allow_signups' => 0 ],
            [ 'id' => $unlimited_id ]
        );
        $log[] = $rows !== false
            ? "✓ Level 3 signups disabled (admin-assign only)"
            : '✗ Failed to disable Level 3 signups';
    } else if ( $unlimited_id ) {
        $log[] = '→ Level 3 signups already disabled — skipped';
    }

    // ── 4. Level 4 — Protocol Session ($29 one-time) ───────────────────────
    $protocol_id = (int) get_option( '_excreet_294_protocol_product', 0 );
    if ( $protocol_id && ! excreet_302_level_exists( $protocol_id ) ) {
        delete_option( '_excreet_294_protocol_product' );
        $protocol_id = 0;
        $log[] = '→ Level 4 option pointed to deleted level — cleared, will re-create';
    }
    if ( ! $protocol_id ) {
        if ( function_exists( 'excreet_294_ensure_product' ) ) {
            excreet_294_ensure_product();
            $protocol_id = (int) get_option( '_excreet_294_protocol_product', 0 );
            $log[] = $protocol_id
                ? "✓ Level 4 (Protocol Session) created — PMPro ID #{$protocol_id}"
                : '✗ Level 4 (Protocol Session) creation failed';
        } else {
            $log[] = '→ Level 4: patch-294 not loaded yet — will auto-create on next init';
        }
    } else {
        $log[] = "→ Level 4 (Protocol Session) already exists (ID #{$protocol_id}) — skipped";
    }

    // ── 5. Wire PMPro page options ──────────────────────────────────────────
    $pages = unserialize( EX302_PAGES );
    foreach ( $pages as $option => $page_id ) {
        if ( function_exists( 'pmpro_setOption' ) ) {
            $current = (int) pmpro_getOption( $option );
            if ( $current !== $page_id ) {
                pmpro_setOption( $option, $page_id );
                $log[] = "✓ PMPro page option '{$option}' → page #{$page_id}";
            } else {
                $log[] = "→ PMPro page option '{$option}' already correct — skipped";
            }
        }
    }

    wp_send_json_success( [ 'log' => $log ] );
}

/* ════════════════════════════════════════════════════════════════════════════
   ADMIN PAGE
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_302_admin_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $pmpro = excreet_302_pmpro_active();

    // Level IDs
    $starter_id   = (int) get_option( EX302_STARTER_OPT, 0 );
    $premium_id   = (int) get_option( '_excreet_293_premium_product', 0 );
    $unlimited_id = (int) get_option( '_excreet_293_unlimited_product', 0 );
    $protocol_id  = (int) get_option( '_excreet_294_protocol_product', 0 );

    // Level existence
    $l1_ok  = $pmpro && $starter_id   && excreet_302_level_exists( $starter_id );
    $l2_ok  = $pmpro && $premium_id   && excreet_302_level_exists( $premium_id );
    $l3_ok  = $pmpro && $unlimited_id && excreet_302_level_exists( $unlimited_id );
    $l4_ok  = $pmpro && $protocol_id  && excreet_302_level_exists( $protocol_id );
    $l3_dis = $pmpro && $unlimited_id && excreet_302_signups_disabled( $unlimited_id );

    // Pages
    $pages    = unserialize( EX302_PAGES );
    $all_wired = true;
    $page_rows = [];
    foreach ( $pages as $option => $page_id ) {
        $wired = excreet_302_page_wired( $option, $page_id );
        if ( ! $wired ) $all_wired = false;
        $page_rows[ $option ] = [ 'id' => $page_id, 'ok' => $wired ];
    }

    // Stripe
    $stripe_ok = excreet_302_stripe_configured();

    // Overall readiness
    $all_levels_ok = $l1_ok && $l2_ok && $l3_ok && $l4_ok && $l3_dis;
    $fully_live    = $pmpro && $stripe_ok && $all_levels_ok && $all_wired;

    $nonce = wp_create_nonce( 'ex302_activate' );
    $ajax  = admin_url( 'admin-ajax.php' );

    ?>
    <style>
    .ex302 { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; max-width: 860px; margin: 2rem auto; }
    .ex302 h1 { font-size: 1.4rem; color: #1a0a2e; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.75rem; }
    .ex302-subtitle { color: #6b7a8d; font-size: 0.88rem; margin-bottom: 2rem; }
    .ex302-live-banner {
        background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 10px;
        padding: 0.9rem 1.4rem; margin-bottom: 1.8rem; color: #1b5e20;
        font-weight: 600; font-size: 0.95rem;
    }
    .ex302-section {
        background: #fff; border: 1px solid #e0d7f0; border-radius: 12px;
        padding: 1.4rem 1.6rem; margin-bottom: 1.4rem;
        box-shadow: 0 2px 8px rgba(30,10,60,0.06);
    }
    .ex302-section h2 { font-size: 1rem; color: #3d1060; margin: 0 0 1rem; border-bottom: 1px solid #ede4f5; padding-bottom: 0.5rem; }
    .ex302-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.45rem 0; border-bottom: 1px solid #f5eeff; font-size: 0.88rem; }
    .ex302-row:last-child { border-bottom: none; }
    .ex302-row-label { flex: 1; color: #334e68; }
    .ex302-id { font-size: 0.75rem; color: #9a9a9a; font-family: monospace; }
    .ex302-manual { background: #fff8e1; border: 1px solid #ffe082; border-radius: 10px; padding: 1.2rem 1.6rem; margin-bottom: 1.4rem; }
    .ex302-manual h2 { font-size: 1rem; color: #5d4037; margin: 0 0 0.75rem; }
    .ex302-manual ol { margin: 0; padding-left: 1.3rem; font-size: 0.88rem; color: #4e342e; line-height: 2; }
    .ex302-manual code { background: #ffe0b2; border-radius: 4px; padding: 1px 5px; font-size: 0.82rem; }
    .ex302-btn {
        background: linear-gradient(135deg, #3d1060, #6b2fa0);
        color: #fff; border: none; border-radius: 8px;
        padding: 0.65rem 1.8rem; font-size: 0.9rem; font-weight: 700;
        cursor: pointer; letter-spacing: 0.04em;
        box-shadow: 0 3px 10px rgba(107,47,160,0.3);
    }
    .ex302-btn:disabled { opacity: 0.55; cursor: default; }
    .ex302-log {
        display: none; background: #1a0535; color: #b0d4ff; border-radius: 8px;
        padding: 1rem 1.2rem; margin-top: 1rem; font-family: monospace;
        font-size: 0.82rem; line-height: 1.8; max-height: 300px; overflow-y: auto;
    }
    .ex302-log.visible { display: block; }
    </style>

    <div class="ex302">
        <h1>
            <?php if ( $fully_live ) : ?>
            <span style="color:#2e7d32;">✓</span> Excreet — PMPro Activation
            <?php else : ?>
            <span style="color:#c62828;">○</span> Excreet — PMPro Activation
            <?php endif; ?>
        </h1>
        <div class="ex302-subtitle">Configure Paid Memberships Pro for Excreet.com — member levels, page wiring, and Stripe gateway.</div>

        <?php if ( $fully_live ) : ?>
        <div class="ex302-live-banner">✓ Everything looks good — PMPro is fully activated and Stripe is connected. The site is ready to accept paying members.</div>
        <?php endif; ?>

        <!-- Manual steps (Stripe — always shown until Stripe is configured) -->
        <?php if ( ! $stripe_ok || ! $pmpro ) : ?>
        <div class="ex302-manual">
            <h2>⚠ Manual Steps Required</h2>
            <ol>
                <?php if ( ! $pmpro ) : ?>
                <li>Go to <strong>WP Admin → Plugins → Add New</strong>, search for <strong>Paid Memberships Pro</strong>, install and activate it.</li>
                <li>Come back to this page and click <strong>Run Activation</strong>.</li>
                <?php endif; ?>
                <?php if ( $pmpro && ! $stripe_ok ) : ?>
                <li>Go to <strong>PMPro → Payment Settings</strong></li>
                <li>Gateway: select <code>Stripe</code></li>
                <li>Enter your Stripe <strong>Publishable Key</strong> and <strong>Secret Key</strong> (use test keys first, then switch to live)</li>
                <li>Stripe Webhook: copy the webhook URL shown, add it in your <a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">Stripe dashboard</a> with events <code>checkout.session.completed</code> and <code>invoice.payment_succeeded</code></li>
                <li>Save settings — then come back and run the level check below</li>
                <?php endif; ?>
            </ol>
        </div>
        <?php endif; ?>

        <!-- PMPro status -->
        <div class="ex302-section">
            <h2>PMPro Installation</h2>
            <div class="ex302-row">
                <div class="ex302-row-label">PMPro plugin active</div>
                <?php echo excreet_302_badge( $pmpro, 'Active', 'Install PMPro from WP Admin → Plugins' ); ?>
            </div>
            <div class="ex302-row">
                <div class="ex302-row-label">Stripe gateway configured</div>
                <?php echo excreet_302_badge( $stripe_ok, 'Connected', 'Go to PMPro → Payment Settings → select Stripe' ); ?>
            </div>
        </div>

        <!-- Membership levels -->
        <div class="ex302-section">
            <h2>Membership Levels</h2>

            <div class="ex302-row">
                <div class="ex302-row-label">
                    Level 1 — Starter ($15/month recurring)
                    <?php if ( $l1_ok ) echo '<br><span class="ex302-id">PMPro ID #' . $starter_id . ' · option: ' . EX302_STARTER_OPT . '</span>'; ?>
                </div>
                <?php echo excreet_302_badge( $l1_ok, 'Exists', 'Run Activation to create' ); ?>
            </div>

            <div class="ex302-row">
                <div class="ex302-row-label">
                    Level 2 — Premium ($25/month recurring)
                    <?php if ( $l2_ok ) echo '<br><span class="ex302-id">PMPro ID #' . $premium_id . ' · option: _excreet_293_premium_product</span>'; ?>
                </div>
                <?php echo excreet_302_badge( $l2_ok, 'Exists', 'Run Activation to create (via patch-293)' ); ?>
            </div>

            <div class="ex302-row">
                <div class="ex302-row-label">
                    Level 3 — Unlimited Q&A Add-On ($0, 30-day, admin-assign only)
                    <?php if ( $l3_ok ) echo '<br><span class="ex302-id">PMPro ID #' . $unlimited_id . ' · option: _excreet_293_unlimited_product</span>'; ?>
                </div>
                <?php echo excreet_302_badge( $l3_ok, 'Exists', 'Run Activation to create (via patch-293)' ); ?>
            </div>

            <div class="ex302-row">
                <div class="ex302-row-label">
                    Level 3 — public signups disabled (admin-assign only)
                    <?php if ( ! $l3_ok ) echo '<br><span class="ex302-id" style="color:#c62828;">Create Level 3 first</span>'; ?>
                </div>
                <?php echo excreet_302_badge( $l3_dis, 'Disabled', $l3_ok ? 'Run Activation to disable' : 'N/A — level missing' ); ?>
            </div>

            <div class="ex302-row">
                <div class="ex302-row-label">
                    Level 4 — Healing Protocol ($29 one-time)
                    <?php if ( $l4_ok ) echo '<br><span class="ex302-id">PMPro ID #' . $protocol_id . ' · option: _excreet_294_protocol_product</span>'; ?>
                </div>
                <?php echo excreet_302_badge( $l4_ok, 'Exists', 'Run Activation to create (via patch-294)' ); ?>
            </div>
        </div>

        <!-- PMPro page wiring -->
        <div class="ex302-section">
            <h2>PMPro Page Options</h2>
            <?php foreach ( $page_rows as $option => $row ) : ?>
            <div class="ex302-row">
                <div class="ex302-row-label">
                    <code><?php echo esc_html( $option ); ?></code>
                    → page #<?php echo $row['id']; ?>
                </div>
                <?php echo excreet_302_badge( $row['ok'], 'Wired', 'Run Activation to wire' ); ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Post-activation checklist -->
        <div class="ex302-section">
            <h2>Post-Activation Checklist (manual)</h2>
            <div class="ex302-row">
                <div class="ex302-row-label">Test a $0 trial checkout as a test Stripe account</div>
                <span style="color:#9a9a9a;font-size:0.8rem;">Manual</span>
            </div>
            <div class="ex302-row">
                <div class="ex302-row-label">Test Starter ($15/mo) checkout with Stripe test card 4242 4242 4242 4242</div>
                <span style="color:#9a9a9a;font-size:0.8rem;">Manual</span>
            </div>
            <div class="ex302-row">
                <div class="ex302-row-label">Verify member lands on /welcome-member/ after intake, then can access /healing-command-center/ and /ask-the-healer/</div>
                <span style="color:#9a9a9a;font-size:0.8rem;">Manual</span>
            </div>
            <div class="ex302-row">
                <div class="ex302-row-label">Switch PMPro gateway from test keys → live keys when ready to accept real payments</div>
                <span style="color:#9a9a9a;font-size:0.8rem;">Manual</span>
            </div>
            <div class="ex302-row">
                <div class="ex302-row-label">Assign a test member to Level 3 (Unlimited) via PMPro admin; verify Ministry shows unlimited badge</div>
                <span style="color:#9a9a9a;font-size:0.8rem;">Manual</span>
            </div>
        </div>

        <!-- Run Activation -->
        <?php if ( $pmpro ) : ?>
        <div class="ex302-section">
            <h2>Run Activation</h2>
            <p style="font-size:0.88rem;color:#334e68;margin:0 0 1rem;">
                This creates any missing membership levels, disables public signups on Level 3,
                and wires all PMPro page options. Safe to run multiple times — already-correct items are skipped.
            </p>
            <button class="ex302-btn" id="ex302-run">Run Activation</button>
            <div class="ex302-log" id="ex302-log"></div>
        </div>
        <?php else : ?>
        <div class="ex302-section" style="background:#fff8f8;">
            <p style="font-size:0.88rem;color:#c62828;margin:0;">
                Install and activate the PMPro plugin first, then return here to run activation.
            </p>
        </div>
        <?php endif; ?>

    </div>

    <script>
    (function () {
        var btn = document.getElementById('ex302-run');
        var log = document.getElementById('ex302-log');
        if ( ! btn ) return;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.textContent = 'Running…';
            log.textContent = '';
            log.classList.add('visible');

            var fd = new FormData();
            fd.append('action', 'ex302_activate');
            fd.append('nonce',  <?php echo wp_json_encode( $nonce ); ?>);

            fetch(<?php echo wp_json_encode( $ajax ); ?>, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if ( data.success ) {
                    log.innerHTML = data.data.log.join('<br>');
                    btn.textContent = '✓ Done — reload to see updated status';
                    btn.addEventListener('click', function () {
                        window.location.reload();
                    });
                    btn.disabled = false;
                } else {
                    log.innerHTML = '✗ ' + ( data.data.message || 'Unknown error' );
                    btn.textContent = 'Run Activation';
                    btn.disabled = false;
                }
            })
            .catch(function () {
                log.innerHTML = '✗ Connection error';
                btn.textContent = 'Run Activation';
                btn.disabled = false;
            });
        });
    })();
    </script>
    <?php
}
