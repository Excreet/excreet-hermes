<?php
/**
 * Plugin Name: Excreet Hermes — Patch 349 (Affiliate Referral Codes)
 * Description: Assigns every member a unique referral code on checkout.
 *              Captures ?ref=CODE on any page visit, registers the referral
 *              after checkout, and surfaces the member's code, live referral
 *              list, earnings balance, and payout history on the dashboard.
 * Version: 3.4.10
 * Author: Excreet Engineering
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// Config
// ─────────────────────────────────────────────────────────────────────────────

define( 'EX349_HERMES_BASE', 'https://excreet.com/api/hermes' );
define( 'EX349_HERMES_KEY',  defined( 'EXCREET_HERMES_API_KEY' )
    ? EXCREET_HERMES_API_KEY
    : ( getenv( 'HERMES_API_KEY' ) ?: '' ) );
define( 'EX349_COOKIE_NAME', 'excreet_ref' );
define( 'EX349_COOKIE_DAYS', 30 );

// ─────────────────────────────────────────────────────────────────────────────
// Helper: call Hermes
// ─────────────────────────────────────────────────────────────────────────────

function ex349_hermes( string $method, string $path, array $body = [] ): ?array {
    $args = [
        'method'  => strtoupper( $method ),
        'timeout' => 10,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . EX349_HERMES_KEY,
        ],
    ];
    if ( $body ) {
        $args['body'] = wp_json_encode( $body );
    }
    $resp = wp_remote_request( EX349_HERMES_BASE . $path, $args );
    if ( is_wp_error( $resp ) ) {
        error_log( '[EX349] Hermes error on ' . $path . ': ' . $resp->get_error_message() );
        return null;
    }
    return json_decode( wp_remote_retrieve_body( $resp ), true ) ?: null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Step 1 — Capture ?ref=CODE on any page visit; persist in a 30-day cookie
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function () {
    $ref = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
    if ( ! $ref ) return;
    // Validate format: EXC + exactly 6 uppercase alphanumeric (no O/0/I/1/L)
    if ( ! preg_match( '/^EXC[A-Z0-9]{6}$/i', $ref ) ) return;

    $expiry = time() + EX349_COOKIE_DAYS * DAY_IN_SECONDS;
    setcookie( EX349_COOKIE_NAME, strtoupper( $ref ), [
        'expires'  => $expiry,
        'path'     => '/',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ] );
}, 1 );

// ─────────────────────────────────────────────────────────────────────────────
// Step 2 — On successful checkout: provision this member's affiliate code,
//           then register the referral if a ref cookie is present
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'pmpro_after_checkout', function ( $user_id, $morder ) {
    if ( ! $user_id ) return;
    $member_id = (string) $user_id;

    // 2a. Always provision this member's own affiliate account + code (idempotent)
    ex349_hermes( 'POST', '/affiliate/provision', [ 'member_id' => $member_id ] );

    // 2b. Check for a referral cookie
    $ref_code = isset( $_COOKIE[ EX349_COOKIE_NAME ] )
        ? sanitize_text_field( $_COOKIE[ EX349_COOKIE_NAME ] )
        : '';

    if ( ! $ref_code ) return;

    // Resolve the code → referrer member ID
    $resolved = ex349_hermes( 'POST', '/affiliate/resolve-code', [ 'referral_code' => $ref_code ] );
    if ( ! $resolved || empty( $resolved['member_id'] ) ) {
        error_log( '[EX349] Could not resolve referral code: ' . $ref_code );
        return;
    }

    $referrer_id = (string) $resolved['member_id'];
    if ( $referrer_id === $member_id ) return; // no self-referrals

    // Determine PMPro level (1 = Starter, 2 = Premium)
    $level = 1;
    if ( isset( $morder->membership_id ) && (int) $morder->membership_id === 2 ) {
        $level = 2;
    }

    // Register the referral
    ex349_hermes( 'POST', '/affiliate/register', [
        'referrer_member_id' => $referrer_id,
        'referred_member_id' => $member_id,
        'referred_level'     => $level,
    ] );

    // Clear the cookie — referral captured, don't re-fire
    setcookie( EX349_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ] );

}, 10, 2 );

// ─────────────────────────────────────────────────────────────────────────────
// Step 3 — Shortcode [excreet_affiliate_code]
//           Renders: referral code card + live referrals + earnings + payouts.
// ─────────────────────────────────────────────────────────────────────────────

add_shortcode( 'excreet_affiliate_code', 'ex349_render_affiliate_code' );

function ex349_render_affiliate_code(): string {
    if ( ! is_user_logged_in() ) return '';

    $member_id = (string) get_current_user_id();
    $cache_key = 'ex349_dash_' . $member_id;
    $dash      = get_transient( $cache_key );

    if ( ! $dash ) {
        $dash = ex349_hermes( 'GET', '/affiliate/dashboard/' . $member_id );
        if ( ! $dash || empty( $dash['referral_code'] ) ) return '';
        set_transient( $cache_key, $dash, 2 * MINUTE_IN_SECONDS );
    }

    $code        = esc_html( $dash['referral_code'] );
    $share_url   = esc_attr( $dash['share_url'] ?? '' );
    $balance     = number_format( ( (int) ( $dash['payout_balance_cents'] ?? 0 ) ) / 100, 2 );
    $total       = number_format( ( (int) ( $dash['total_earned_cents']   ?? 0 ) ) / 100, 2 );
    $referrals   = is_array( $dash['referrals'] ) ? $dash['referrals'] : [];
    $payouts     = is_array( $dash['payouts']   ) ? $dash['payouts']   : [];

    $active_count  = count( array_filter( $referrals, fn($r) => ( $r['status'] ?? '' ) === 'cleared' ) );
    $pending_count = count( array_filter( $referrals, fn($r) => ( $r['status'] ?? '' ) === 'pending' ) );

    ob_start(); ?>
<div class="ex349-hub">

  <?php /* ── CSS (emitted once) ── */ ?>
  <style>
  .ex349-hub{font-family:'DM Sans',system-ui,sans-serif;max-width:600px;display:flex;flex-direction:column;gap:16px;margin:22px 0}

  /* Code card */
  .ex349-card{background:linear-gradient(135deg,#1a0a2e 0%,#2a1250 100%);border:1px solid rgba(245,197,24,.35);border-radius:12px;padding:24px 28px 20px}
  .ex349-eyebrow{font-size:10px;letter-spacing:.2em;color:#F5C518;font-weight:700;text-transform:uppercase;margin-bottom:8px;font-family:'Cormorant Garamond',Georgia,serif}
  .ex349-code{font-size:34px;font-weight:800;color:#F5C518;letter-spacing:.22em;line-height:1;margin-bottom:14px}
  .ex349-row{display:flex;gap:10px;margin-bottom:12px;align-items:center}
  .ex349-input{flex:1;background:rgba(255,255,255,.07);border:1px solid rgba(245,197,24,.28);border-radius:6px;color:#E8D8F5;padding:8px 12px;font-size:13px;font-family:inherit;outline:none;min-width:0}
  .ex349-copy{background:#F5C518;color:#1a0a2e;border:none;border-radius:6px;padding:9px 18px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;transition:background .15s}
  .ex349-copy:hover{background:#ffe04a}
  .ex349-copy.copied{background:#5cb85c;color:#fff}
  .ex349-note{color:rgba(232,216,245,.65);font-size:12px;line-height:1.55;margin:0}
  .ex349-note strong{color:#F5C518;font-weight:600}

  /* Earnings strip */
  .ex349-earnings{background:rgba(26,10,46,.75);border:1px solid rgba(245,197,24,.2);border-radius:10px;padding:16px 24px;display:flex;gap:0;align-items:stretch}
  .ex349-earn-col{flex:1;text-align:center}
  .ex349-earn-col+.ex349-earn-col{border-left:1px solid rgba(245,197,24,.18)}
  .ex349-earn-val{font-size:26px;font-weight:800;color:#F5C518;line-height:1;margin-bottom:4px}
  .ex349-earn-lbl{font-size:10px;letter-spacing:.14em;color:rgba(232,216,245,.55);text-transform:uppercase;font-weight:600}
  .ex349-earn-sub{font-size:11px;color:rgba(232,216,245,.45);margin-top:3px}

  /* Referrals panel */
  .ex349-panel{background:rgba(26,10,46,.75);border:1px solid rgba(123,47,160,.35);border-radius:10px;padding:18px 24px}
  .ex349-panel-title{font-size:10px;letter-spacing:.18em;color:#F5C518;font-weight:700;text-transform:uppercase;margin-bottom:14px;font-family:'Cormorant Garamond',Georgia,serif}
  .ex349-empty{color:rgba(232,216,245,.45);font-size:13px;padding:8px 0}
  .ex349-ref-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.06)}
  .ex349-ref-row:last-child{border-bottom:none;padding-bottom:0}
  .ex349-ref-icon{width:34px;height:34px;border-radius:50%;background:rgba(123,47,160,.4);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;font-weight:700;color:#E8D8F5}
  .ex349-ref-info{flex:1;min-width:0}
  .ex349-ref-date{font-size:12px;color:#E8D8F5;font-weight:600}
  .ex349-ref-sub{font-size:11px;color:rgba(232,216,245,.5);margin-top:2px}
  .ex349-badge{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:3px 9px;border-radius:20px;white-space:nowrap}
  .ex349-badge-pending{background:rgba(245,197,24,.15);color:#F5C518;border:1px solid rgba(245,197,24,.3)}
  .ex349-badge-cleared{background:rgba(92,184,92,.15);color:#5cb85c;border:1px solid rgba(92,184,92,.3)}
  .ex349-badge-revoked{background:rgba(229,115,115,.12);color:#e57373;border:1px solid rgba(229,115,115,.25)}

  /* Payouts panel */
  .ex349-payout-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:13px}
  .ex349-payout-row:last-child{border-bottom:none;padding-bottom:0}
  .ex349-payout-amt{font-weight:700;color:#F5C518}
  .ex349-payout-period{color:rgba(232,216,245,.5);font-size:11px;margin-top:2px}
  .ex349-badge-paid{background:rgba(92,184,92,.15);color:#5cb85c;border:1px solid rgba(92,184,92,.3)}
  .ex349-badge-processing{background:rgba(245,197,24,.15);color:#F5C518;border:1px solid rgba(245,197,24,.3)}
  .ex349-badge-failed{background:rgba(229,115,115,.12);color:#e57373;border:1px solid rgba(229,115,115,.25)}
  </style>

  <?php /* ── 1. Referral code card ── */ ?>
  <div class="ex349-card">
    <div class="ex349-eyebrow">Your Referral Code</div>
    <div class="ex349-code"><?php echo $code; ?></div>
    <div class="ex349-row">
      <input id="ex349-link" class="ex349-input" type="text"
             value="<?php echo $share_url; ?>" readonly />
      <button class="ex349-copy" onclick="ex349Copy(this)">Copy Link</button>
    </div>
    <p class="ex349-note">
      Share your link. Every person who joins through it earns you
      <strong>$5&ndash;$10&nbsp;per&nbsp;month</strong> for as long as you&rsquo;re
      both active members &mdash; no application, starts automatically.
    </p>
  </div>

  <?php /* ── 2. Earnings strip ── */ ?>
  <div class="ex349-earnings">
    <div class="ex349-earn-col">
      <div class="ex349-earn-val">$<?php echo $balance; ?></div>
      <div class="ex349-earn-lbl">Available Balance</div>
      <div class="ex349-earn-sub">Paid out at $50</div>
    </div>
    <div class="ex349-earn-col">
      <div class="ex349-earn-val"><?php echo $active_count; ?></div>
      <div class="ex349-earn-lbl">Active Referrals</div>
      <div class="ex349-earn-sub">Earning monthly</div>
    </div>
    <div class="ex349-earn-col">
      <div class="ex349-earn-val">$<?php echo $total; ?></div>
      <div class="ex349-earn-lbl">Total Earned</div>
      <div class="ex349-earn-sub">All time</div>
    </div>
  </div>

  <?php /* ── 3. Referrals list ── */ ?>
  <div class="ex349-panel">
    <div class="ex349-panel-title">
      Your Referrals
      <?php if ( $pending_count > 0 ) : ?>
        <span class="ex349-badge ex349-badge-pending" style="margin-left:8px;font-size:9px">
          <?php echo $pending_count; ?> pending
        </span>
      <?php endif; ?>
    </div>
    <?php if ( empty( $referrals ) ) : ?>
      <p class="ex349-empty">No referrals yet. Share your link to start earning.</p>
    <?php else : ?>
      <?php foreach ( $referrals as $i => $ref ) :
        $status    = $ref['status'] ?? 'pending';
        $level_lbl = ( (int) ( $ref['referred_level'] ?? 1 ) ) === 2 ? 'Premium · $10/mo' : 'Starter · $5/mo';
        $joined    = ! empty( $ref['joined_at'] )
            ? date( 'M j, Y', strtotime( $ref['joined_at'] ) )
            : '—';
        $cleared   = ! empty( $ref['credit_cleared_at'] )
            ? 'Cleared ' . date( 'M j', strtotime( $ref['credit_cleared_at'] ) )
            : ( $status === 'pending' ? '30-day hold in progress' : '' );
        $badge_cls = 'ex349-badge-' . esc_attr( $status );
        $badge_lbl = ucfirst( $status );
        if ( $status === 'cleared' ) $badge_lbl = 'Earning';
        $avatar_letter = strtoupper( substr( 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', $i % 26, 1 ) );
      ?>
      <div class="ex349-ref-row">
        <div class="ex349-ref-icon"><?php echo $avatar_letter; ?></div>
        <div class="ex349-ref-info">
          <div class="ex349-ref-date">Joined <?php echo esc_html( $joined ); ?></div>
          <div class="ex349-ref-sub"><?php echo esc_html( $level_lbl ); ?>
            <?php if ( $cleared ) : ?>&nbsp;&middot;&nbsp;<?php echo esc_html( $cleared ); ?><?php endif; ?>
          </div>
        </div>
        <span class="ex349-badge <?php echo $badge_cls; ?>"><?php echo $badge_lbl; ?></span>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php /* ── 4. Payout history (only if any) ── */ ?>
  <?php if ( ! empty( $payouts ) ) : ?>
  <div class="ex349-panel">
    <div class="ex349-panel-title">Payout History</div>
    <?php foreach ( $payouts as $p ) :
      $amt    = number_format( ( (int) ( $p['amount_cents'] ?? 0 ) ) / 100, 2 );
      $status = $p['status'] ?? 'pending';
      $period = '';
      if ( ! empty( $p['period_start'] ) && ! empty( $p['period_end'] ) ) {
          $period = date( 'M j', strtotime( $p['period_start'] ) )
                  . ' – ' . date( 'M j, Y', strtotime( $p['period_end'] ) );
      }
    ?>
    <div class="ex349-payout-row">
      <div>
        <div class="ex349-payout-amt">$<?php echo $amt; ?></div>
        <?php if ( $period ) : ?>
          <div class="ex349-payout-period"><?php echo esc_html( $period ); ?></div>
        <?php endif; ?>
      </div>
      <span class="ex349-badge ex349-badge-<?php echo esc_attr( $status ); ?>"><?php echo ucfirst( $status ); ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<script>
function ex349Copy(btn){
  var inp=document.getElementById('ex349-link');
  if(!inp)return;
  if(navigator.clipboard&&window.isSecureContext){
    navigator.clipboard.writeText(inp.value).then(function(){ex349Flash(btn);});
  }else{inp.select();document.execCommand('copy');ex349Flash(btn);}
}
function ex349Flash(btn){
  btn.textContent='Copied!';btn.classList.add('copied');
  setTimeout(function(){btn.textContent='Copy Link';btn.classList.remove('copied');},2200);
}
</script>
<?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// Step 4 — Auto-inject the code card into the member dashboard (page 366)
//           Inserted after the first <h2> or <h3> in the page content.
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'the_content', function ( $content ) {
    if ( ! is_page( 366 ) ) return $content; // /welcome-member/ dashboard
    if ( ! is_user_logged_in() ) return $content;

    $card = ex349_render_affiliate_code();
    if ( ! $card ) return $content;

    // Inject after the first heading
    $modified = preg_replace( '/(<\/h[23]>)/i', '$1' . $card, $content, 1 );

    // Fallback: prepend if no heading found
    return ( $modified && strpos( $modified, 'ex349-wrap' ) !== false )
        ? $modified
        : $card . $content;
}, 20 );

// ─────────────────────────────────────────────────────────────────────────────
// Step 5 — Backfill on dashboard load for members who joined before patch-349
//           Runs once per member (guarded by user meta flag).
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp', function () {
    if ( ! is_user_logged_in() || ! is_page( 366 ) ) return;

    $user_id  = get_current_user_id();
    $meta_key = '_ex349_provisioned';
    if ( get_user_meta( $user_id, $meta_key, true ) ) return;

    $result = ex349_hermes( 'POST', '/affiliate/provision', [ 'member_id' => (string) $user_id ] );
    if ( $result && ! empty( $result['ok'] ) ) {
        update_user_meta( $user_id, $meta_key, 1 );
    }
}, 10 );

// ─────────────────────────────────────────────────────────────────────────────
// Step 6 — Admin AJAX: bulk backfill codes for all existing DB accounts
//           Trigger from WP Admin console:
//           jQuery.post(ajaxurl,{action:'excreet_349_backfill'},console.log)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_excreet_349_backfill', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );
    $result = ex349_hermes( 'POST', '/affiliate/backfill-codes' );
    $result ? wp_send_json_success( $result ) : wp_send_json_error( 'Hermes error' );
} );
