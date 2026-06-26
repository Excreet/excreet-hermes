<?php
/**
 * Plugin Name: Excreet Patch 348 — Owner Admin Panel
 * Description: Branded command-centre dashboard at /admin/ — member counts,
 *              Hermes health, Ministry activity, WooCommerce orders, quick links.
 *              Admin-only; non-admins are bounced to the login page.
 * Version:     3.4.8
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'template_redirect', 'excreet_348_maybe_render_panel', 1 );

function excreet_348_maybe_render_panel(): void {
    $uri = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
    if ( $uri !== 'admin' ) return;

    // Must be logged in and have manage_options
    if ( ! is_user_logged_in() ) {
        wp_redirect( wp_login_url( home_url( '/admin/' ) ) );
        exit;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_redirect( home_url( '/' ) );
        exit;
    }

    // ── Gather data ──────────────────────────────────────────────────────────
    global $wpdb;

    // Member counts via PMPro
    $active_members = 0;
    $level1_count   = 0;
    $level2_count   = 0;
    if ( function_exists( 'pmpro_hasMembershipLevel' ) ) {
        $active_members = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pmpro_memberships_users WHERE status = 'active'"
        );
        $level1_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pmpro_memberships_users WHERE membership_id = 1 AND status = 'active'"
        );
        $level2_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pmpro_memberships_users WHERE membership_id = 2 AND status = 'active'"
        );
    }

    // Recent signups (last 5)
    $recent_users = get_users( [
        'number'  => 5,
        'orderby' => 'registered',
        'order'   => 'DESC',
        'fields'  => [ 'ID', 'user_login', 'user_email', 'user_registered' ],
    ] );

    // Ministry sessions
    $ministry_sessions = 0;
    $last_ministry_ts  = null;
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ministry_chat_history'" );
    if ( $table_exists ) {
        $ministry_sessions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ministry_chat_history"
        );
        $last_ministry_ts = $wpdb->get_var(
            "SELECT updated_at FROM {$wpdb->prefix}ministry_chat_history ORDER BY updated_at DESC LIMIT 1"
        );
    }

    // WooCommerce orders (last 5)
    $wc_orders       = [];
    $wc_total_orders = 0;
    if ( function_exists( 'wc_get_orders' ) ) {
        $wc_total_orders = (int) $wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->posts}
             WHERE post_type = 'shop_order'
             AND post_status NOT IN ('trash','auto-draft')"
        );
        $wc_orders = wc_get_orders( [ 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC' ] );
    }

    // Hermes health — synchronous check (4-second timeout)
    $hermes_ok  = false;
    $hermes_msg = 'Unreachable';
    $hermes_url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/hermes/health';
    $hermes_resp = wp_remote_get( $hermes_url, [
        'timeout' => 4,
        'headers' => [
            'Authorization' => 'Bearer ' . ( defined( 'EXCREET_HERMES_API_KEY' ) ? EXCREET_HERMES_API_KEY : '' ),
        ],
    ] );
    if ( ! is_wp_error( $hermes_resp ) && wp_remote_retrieve_response_code( $hermes_resp ) === 200 ) {
        $hermes_ok  = true;
        $hermes_msg = 'Online';
    } elseif ( is_wp_error( $hermes_resp ) ) {
        $hermes_msg = esc_html( $hermes_resp->get_error_message() );
    }

    // AI background last rotation
    $last_rotation = get_option( 'excreet_last_bg_rotation', null );

    // Patch count
    $all_patches = glob( WP_CONTENT_DIR . '/mu-plugins/excreet-hermes-patch-*.php' ) ?: [];
    $patch_count = count( $all_patches );

    // Misc
    $wp_version  = get_bloginfo( 'version' );
    $php_version = PHP_VERSION;
    $now         = current_time( 'D, M j Y  g:i A' );

    // ── Render ───────────────────────────────────────────────────────────────
    status_header( 200 );
    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Excreet — Owner Panel</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
     background:#0b0b18;color:#e2e2f0;min-height:100vh}

/* Header */
.ep-hdr{background:linear-gradient(135deg,#1a0a2e 0%,#2a0a4a 100%);
        border-bottom:2px solid rgba(201,168,76,.4);
        padding:16px 32px;display:flex;align-items:center;
        justify-content:space-between;gap:16px;flex-wrap:wrap}
.ep-logo{font-size:20px;font-weight:800;letter-spacing:.2em;color:#C9A84C}
.ep-logo-sub{font-size:9.5px;letter-spacing:.3em;text-transform:uppercase;
             color:rgba(255,255,255,.4);margin-top:2px}
.ep-hdr-r{font-size:11px;color:rgba(255,255,255,.4);text-align:right}
.ep-hdr-r strong{display:block;color:rgba(255,255,255,.8);font-size:13px;margin-bottom:2px}

/* Layout */
.ep-wrap{max-width:1180px;margin:0 auto;padding:24px 20px 64px}
.ep-section{font-size:9.5px;letter-spacing:.22em;text-transform:uppercase;
            color:rgba(201,168,76,.55);margin:28px 0 12px}
.ep-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(330px,1fr))}
.ep-grid-2{grid-template-columns:1fr 1fr}
.ep-full{grid-column:1/-1}

/* Cards */
.ep-card{background:#111126;border:1px solid rgba(255,255,255,.07);
         border-radius:10px;overflow:hidden}
.ep-ch{display:flex;align-items:center;gap:10px;
       padding:11px 16px 9px;border-bottom:1px solid rgba(255,255,255,.06)}
.ep-ch-icon{width:26px;height:26px;border-radius:6px;display:flex;align-items:center;
            justify-content:center;font-size:13px;flex-shrink:0}
.ep-ch-title{font-size:10.5px;font-weight:700;letter-spacing:.12em;
             text-transform:uppercase;color:#C9A84C}
.ep-cb{padding:14px 16px}

/* Big number */
.ep-big{display:flex;align-items:baseline;gap:8px;margin-bottom:6px}
.ep-big-n{font-size:38px;font-weight:700;line-height:1;color:#fff}
.ep-big-l{font-size:11px;color:rgba(255,255,255,.38);text-transform:uppercase;letter-spacing:.08em}

/* Pills */
.ep-pills{display:flex;flex-wrap:wrap;gap:7px;margin-top:6px}
.ep-pill{border-radius:20px;padding:3px 11px;font-size:11px;font-weight:600}
.ep-pill-gold{background:rgba(201,168,76,.14);border:1px solid rgba(201,168,76,.35);color:#F5D97A}
.ep-pill-purple{background:rgba(107,33,168,.22);border:1px solid rgba(107,33,168,.45);color:#c4a0ff}

/* Badge */
.ep-badge{display:inline-flex;align-items:center;gap:5px;
          padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;margin-bottom:12px}
.ep-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}
.ep-ok{background:rgba(22,101,52,.28);border:1px solid rgba(34,197,94,.4);color:#4ade80}
.ep-err{background:rgba(127,29,29,.28);border:1px solid rgba(248,113,113,.4);color:#f87171}
.ep-warn{background:rgba(146,64,14,.28);border:1px solid rgba(251,191,36,.4);color:#fbbf24}

/* KV rows */
.ep-kv{display:flex;justify-content:space-between;align-items:center;
       padding:5px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:12px}
.ep-kv:last-child{border-bottom:none}
.ep-kv-k{color:rgba(255,255,255,.38)}
.ep-kv-v{color:rgba(255,255,255,.82);text-align:right}

/* Table */
.ep-tbl{width:100%;border-collapse:collapse;font-size:12px}
.ep-tbl th{text-align:left;padding:4px 8px;font-size:9.5px;letter-spacing:.1em;
           text-transform:uppercase;color:rgba(255,255,255,.3);
           border-bottom:1px solid rgba(255,255,255,.07)}
.ep-tbl td{padding:7px 8px;border-bottom:1px solid rgba(255,255,255,.04);
           color:rgba(255,255,255,.72)}
.ep-tbl tr:last-child td{border-bottom:none}
.ep-tbl tr:hover td{background:rgba(255,255,255,.025)}

/* Quick links */
.ep-links{display:flex;flex-wrap:wrap;gap:9px}
.ep-link{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;
         background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.09);
         border-radius:8px;color:rgba(255,255,255,.78);font-size:12px;text-decoration:none;
         transition:background .15s,border-color .15s,color .15s}
.ep-link:hover{background:rgba(201,168,76,.12);border-color:rgba(201,168,76,.4);color:#F5D97A}

.ep-muted{font-size:12px;color:rgba(255,255,255,.28);font-style:italic}
</style>
</head>
<body>

<div class="ep-hdr">
  <div>
    <div class="ep-logo">EXCREET</div>
    <div class="ep-logo-sub">Owner Panel &nbsp;&mdash;&nbsp; Command Centre</div>
  </div>
  <div class="ep-hdr-r">
    <strong><?= esc_html( wp_get_current_user()->display_name ) ?></strong>
    <?= esc_html( $now ) ?>
  </div>
</div>

<div class="ep-wrap">

  <!-- ── LIVE METRICS ── -->
  <div class="ep-section">Live Metrics</div>
  <div class="ep-grid">

    <!-- Members -->
    <div class="ep-card">
      <div class="ep-ch">
        <div class="ep-ch-icon" style="background:rgba(107,33,168,.3)">👥</div>
        <div class="ep-ch-title">Active Members</div>
      </div>
      <div class="ep-cb">
        <div class="ep-big">
          <div class="ep-big-n"><?= $active_members ?></div>
          <div class="ep-big-l">total active</div>
        </div>
        <div class="ep-pills">
          <span class="ep-pill ep-pill-gold">Starter &nbsp;<?= $level1_count ?></span>
          <span class="ep-pill ep-pill-purple">Premium &nbsp;<?= $level2_count ?></span>
          <?php $other = $active_members - $level1_count - $level2_count;
          if ( $other > 0 ): ?>
            <span class="ep-pill" style="background:rgba(55,65,81,.4);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.6)">Other &nbsp;<?= $other ?></span>
          <?php endif ?>
        </div>
        <?php if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ): ?>
          <p class="ep-muted" style="margin-top:8px">PMPro not active</p>
        <?php endif ?>
      </div>
    </div>

    <!-- Hermes -->
    <div class="ep-card">
      <div class="ep-ch">
        <div class="ep-ch-icon" style="background:rgba(22,101,52,.3)">⚡</div>
        <div class="ep-ch-title">Hermes API</div>
      </div>
      <div class="ep-cb">
        <span class="ep-badge <?= $hermes_ok ? 'ep-ok' : 'ep-err' ?>"><?= esc_html( $hermes_msg ) ?></span>
        <div class="ep-kv">
          <span class="ep-kv-k">Health endpoint</span>
          <span class="ep-kv-v" style="font-family:monospace;font-size:10px">/api/hermes/health</span>
        </div>
        <div class="ep-kv">
          <span class="ep-kv-k">API key</span>
          <span class="ep-kv-v"><?= defined( 'EXCREET_HERMES_API_KEY' ) ? '&#10003; configured' : '&#10007; missing' ?></span>
        </div>
        <div class="ep-kv">
          <span class="ep-kv-k">Checked</span>
          <span class="ep-kv-v">just now</span>
        </div>
      </div>
    </div>

    <!-- Ministry -->
    <div class="ep-card">
      <div class="ep-ch">
        <div class="ep-ch-icon" style="background:rgba(201,168,76,.2)">🌿</div>
        <div class="ep-ch-title">Ministry of Healing</div>
      </div>
      <div class="ep-cb">
        <div class="ep-big">
          <div class="ep-big-n"><?= $ministry_sessions ?></div>
          <div class="ep-big-l">member sessions</div>
        </div>
        <div class="ep-kv" style="margin-top:8px">
          <span class="ep-kv-k">Last activity</span>
          <span class="ep-kv-v">
            <?= $last_ministry_ts
                ? esc_html( human_time_diff( strtotime( $last_ministry_ts ), current_time( 'timestamp' ) ) . ' ago' )
                : '&mdash;' ?>
          </span>
        </div>
        <div class="ep-kv">
          <span class="ep-kv-k">Chat history table</span>
          <span class="ep-kv-v"><?= $table_exists ? '&#10003; present' : '&#10007; missing' ?></span>
        </div>
      </div>
    </div>

  </div><!-- /metrics grid -->

  <!-- ── RECENT ACTIVITY ── -->
  <div class="ep-section">Recent Activity</div>
  <div class="ep-grid ep-grid-2">

    <!-- Recent signups -->
    <div class="ep-card">
      <div class="ep-ch">
        <div class="ep-ch-icon" style="background:rgba(29,78,216,.3)">🆕</div>
        <div class="ep-ch-title">Recent Signups</div>
      </div>
      <div class="ep-cb">
        <?php if ( $recent_users ): ?>
        <table class="ep-tbl">
          <thead><tr><th>User</th><th>Email</th><th>Level</th><th>Joined</th></tr></thead>
          <tbody>
          <?php foreach ( $recent_users as $u ):
            $lvl = function_exists( 'pmpro_getMembershipLevelForUser' )
                   ? pmpro_getMembershipLevelForUser( $u->ID ) : null;
          ?>
            <tr>
              <td><?= esc_html( $u->user_login ) ?></td>
              <td style="color:rgba(255,255,255,.42);font-size:11px"><?= esc_html( $u->user_email ) ?></td>
              <td>
                <?php if ( $lvl ): ?>
                  <span style="font-size:10px;color:#C9A84C"><?= esc_html( $lvl->name ) ?></span>
                <?php else: ?>
                  <span style="font-size:10px;color:rgba(255,255,255,.28)">—</span>
                <?php endif ?>
              </td>
              <td style="font-size:11px;white-space:nowrap;color:rgba(255,255,255,.5)">
                <?= esc_html( date( 'M j', strtotime( $u->user_registered ) ) ) ?>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        <?php else: ?>
          <p class="ep-muted">No users yet</p>
        <?php endif ?>
      </div>
    </div>

    <!-- WooCommerce orders -->
    <div class="ep-card">
      <div class="ep-ch">
        <div class="ep-ch-icon" style="background:rgba(124,45,18,.3)">🛒</div>
        <div class="ep-ch-title">WooCommerce Orders</div>
      </div>
      <div class="ep-cb">
        <?php if ( function_exists( 'wc_get_orders' ) ): ?>
          <div class="ep-big" style="margin-bottom:12px">
            <div class="ep-big-n"><?= $wc_total_orders ?></div>
            <div class="ep-big-l">total orders</div>
          </div>
          <?php if ( $wc_orders ): ?>
          <table class="ep-tbl">
            <thead><tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ( $wc_orders as $o ): ?>
              <tr>
                <td style="color:rgba(255,255,255,.4)">#<?= $o->get_id() ?></td>
                <td><?= esc_html( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) ?: $o->get_billing_email() ) ?></td>
                <td><?= wp_kses_post( $o->get_formatted_order_total() ) ?></td>
                <td><span style="font-size:10px;text-transform:capitalize;color:#C9A84C"><?= esc_html( $o->get_status() ) ?></span></td>
              </tr>
            <?php endforeach ?>
            </tbody>
          </table>
          <?php else: ?>
            <p class="ep-muted">No orders yet</p>
          <?php endif ?>
        <?php else: ?>
          <p class="ep-muted">WooCommerce not active</p>
        <?php endif ?>
      </div>
    </div>

  </div><!-- /activity grid -->

  <!-- ── SYSTEM ── -->
  <div class="ep-section">System</div>
  <div class="ep-grid ep-grid-2">

    <!-- System info -->
    <div class="ep-card">
      <div class="ep-ch">
        <div class="ep-ch-icon" style="background:rgba(55,48,163,.3)">🖥</div>
        <div class="ep-ch-title">System Status</div>
      </div>
      <div class="ep-cb">
        <div class="ep-kv">
          <span class="ep-kv-k">WordPress</span>
          <span class="ep-kv-v"><?= esc_html( $wp_version ) ?></span>
        </div>
        <div class="ep-kv">
          <span class="ep-kv-k">PHP</span>
          <span class="ep-kv-v"><?= esc_html( $php_version ) ?></span>
        </div>
        <div class="ep-kv">
          <span class="ep-kv-k">PMPro</span>
          <span class="ep-kv-v"><?= function_exists( 'pmpro_hasMembershipLevel' ) ? ( defined('PMPRO_VERSION') ? 'v'.PMPRO_VERSION : '&#10003; active' ) : '&#10007; inactive' ?></span>
        </div>
        <div class="ep-kv">
          <span class="ep-kv-k">WooCommerce</span>
          <span class="ep-kv-v"><?= function_exists( 'wc_get_orders' ) ? ( defined('WC_VERSION') ? 'v'.WC_VERSION : '&#10003; active' ) : '&#10007; inactive' ?></span>
        </div>
        <div class="ep-kv">
          <span class="ep-kv-k">mu-plugin patches</span>
          <span class="ep-kv-v"><?= $patch_count ?> loaded</span>
        </div>
        <div class="ep-kv">
          <span class="ep-kv-k">AI bg last rotation</span>
          <span class="ep-kv-v"><?= $last_rotation ? esc_html( date( 'M j, Y', strtotime( $last_rotation ) ) ) : 'not recorded' ?></span>
        </div>
      </div>
    </div>

    <!-- Quick links -->
    <div class="ep-card">
      <div class="ep-ch">
        <div class="ep-ch-icon" style="background:rgba(201,168,76,.2)">🔗</div>
        <div class="ep-ch-title">Quick Links</div>
      </div>
      <div class="ep-cb">
        <div class="ep-links">
          <a class="ep-link" href="<?= admin_url() ?>">⚙️ WP Admin</a>
          <a class="ep-link" href="<?= admin_url( 'admin.php?page=pmpro-memberslist' ) ?>">👥 PMPro Members</a>
          <a class="ep-link" href="<?= admin_url( 'admin.php?page=excreet-activation' ) ?>">🚀 Activation Helper</a>
          <a class="ep-link" href="<?= admin_url( 'edit.php?post_type=shop_order' ) ?>">🛒 Orders</a>
          <a class="ep-link" href="<?= admin_url( 'admin.php?page=pmpro-membershiplevels' ) ?>">🏷 Membership Levels</a>
          <a class="ep-link" href="<?= admin_url( 'admin.php?page=pmpro-paymentsettings' ) ?>">💳 Payments</a>
          <a class="ep-link" href="<?= home_url( '/member-dashboard/' ) ?>">📊 Member Dashboard</a>
          <a class="ep-link" href="<?= home_url( '/shop/' ) ?>">🛍 Shop</a>
          <a class="ep-link" href="<?= home_url( '/affiliate-area/' ) ?>">🤝 Affiliate Area</a>
          <a class="ep-link" href="<?= home_url( '/ask-the-healer/' ) ?>">🌿 Ministry</a>
          <a class="ep-link" href="<?= home_url( '/healing-command-center/' ) ?>">🧬 HCC</a>
          <a class="ep-link" href="<?= home_url( '/explore/' ) ?>">🌐 Explore</a>
        </div>
      </div>
    </div>

  </div><!-- /system grid -->

</div><!-- /ep-wrap -->
</body>
</html>
<?php
    exit;
}
