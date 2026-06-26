<?php
/**
 * Plugin Name: Excreet Hermes — Patch 357 (Public Store)
 * Description: Opens the WooCommerce product pages, cart, and checkout to the
 *              public — no membership required to browse or purchase.
 *
 *              Replaces the patch-303 member gate for WooCommerce pages:
 *                - /shop/, product pages, categories → fully public
 *                - /cart/, /checkout/               → login required only
 *                - /my-account/                     → login required only
 *
 *              HCC, Ministry, Doctor Visit Summary, and all other member-only
 *              pages remain gated by their own patches (291 / 296 / 351 etc).
 *
 *              Adds post-purchase membership upsell on the thank-you page.
 *              Adds affiliate nudge in cart sidebar and below Add to Cart.
 *
 * Version: 3.5.7
 * Depends on: excreet-hermes-patch-303.php (overrides Section A gate only)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ════════════════════════════════════════════════════════════════════════════
   A — SWAP PATCH-303 MEMBER GATE WITH LIGHTER STORE GATE
   Uses the 'wp' hook (fires after WP loads, before template_redirect) to
   unhook the old gate at priority 1 and register the replacement at same priority.
   ════════════════════════════════════════════════════════════════════════════ */

add_action( 'wp', 'excreet_357_swap_gate', 5 );

function excreet_357_swap_gate(): void {
    remove_action( 'template_redirect', 'excreet_303_member_gate', 1 );
    add_action( 'template_redirect', 'excreet_357_store_gate', 1 );
}

function excreet_357_store_gate(): void {
    if ( ! function_exists( 'is_woocommerce' ) ) { return; }

    if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) { return; }

    /* Product browse — fully public, no login required */
    if ( is_shop() || is_product() || is_product_category() || is_product_tag() ) { return; }

    /* Cart & checkout — login required, no membership check */
    if ( is_cart() || is_checkout() ) {
        if ( ! is_user_logged_in() ) {
            $login = function_exists( 'pmpro_url' )
                ? pmpro_url( 'login' )
                : home_url( '/member-login/' );
            wp_safe_redirect( add_query_arg( 'redirect_to', urlencode( get_permalink() ), $login ) );
            exit;
        }
        return;
    }

    /* My account — login required */
    if ( is_account_page() ) {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( function_exists( 'pmpro_url' ) ? pmpro_url( 'login' ) : home_url( '/member-login/' ) );
            exit;
        }
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   B — POST-PURCHASE MEMBERSHIP UPSELL (woocommerce_thankyou)
   Shown only to buyers who are not yet members.
   ════════════════════════════════════════════════════════════════════════════ */

add_action( 'woocommerce_thankyou', 'excreet_357_thankyou_upsell', 20 );

function excreet_357_thankyou_upsell(): void {
    if ( is_user_logged_in() && function_exists( 'pmpro_hasMembershipLevel' ) ) {
        if ( pmpro_hasMembershipLevel( null, get_current_user_id() ) ) { return; }
    }

    $starter_url = function_exists( 'pmpro_url' ) ? pmpro_url( 'checkout', '?level=1' ) : home_url( '/membership-levels/' );
    $premium_url = function_exists( 'pmpro_url' ) ? pmpro_url( 'checkout', '?level=2' ) : home_url( '/membership-levels/' );
    ?>
    <div style="margin:40px auto 0;max-width:620px;background:linear-gradient(135deg,#1a0a2e,#0c0115);border:1px solid rgba(245,197,24,.3);border-radius:16px;padding:32px 28px;font-family:'DM Sans',sans-serif;color:#fff;text-align:center;">
        <p style="font-size:.65rem;letter-spacing:.28em;text-transform:uppercase;color:#F5C518;margin:0 0 10px;opacity:.85;">Your order is on its way</p>
        <h3 style="font-family:'Cormorant Garamond',Georgia,serif;font-size:1.7rem;font-weight:700;color:#fff;margin:0 0 8px;line-height:1.2;">Turn your purchase into a paycheck.</h3>
        <p style="font-size:.88rem;color:rgba(255,255,255,.6);margin:0 0 24px;line-height:1.65;">Excreet members earn <strong style="color:#F5C518;">$5–$10 per referred member per month</strong>. Your first referral covers your membership fee.</p>
        <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-bottom:20px;">
            <div style="flex:1;min-width:175px;background:rgba(255,255,255,.05);border:1px solid rgba(245,197,24,.15);border-radius:12px;padding:18px 16px;">
                <p style="font-size:.6rem;letter-spacing:.22em;text-transform:uppercase;color:#F5C518;margin:0 0 6px;opacity:.8;">Starter</p>
                <p style="font-size:1.4rem;font-weight:700;color:#fff;margin:0 0 4px;">$15<span style="font-size:.75rem;font-weight:300;opacity:.5;">/mo</span></p>
                <p style="font-size:.75rem;color:rgba(255,255,255,.5);margin:0 0 14px;line-height:1.5;">Body Scan · Ministry of Healing<br>Earn <strong style="color:#F5C518;">$5/referral/mo</strong></p>
                <a href="<?php echo esc_url($starter_url); ?>" style="display:block;padding:9px 16px;border:1px solid #F5C518;border-radius:20px;color:#F5C518;font-size:.78rem;text-decoration:none;letter-spacing:.06em;">Join Starter</a>
            </div>
            <div style="flex:1;min-width:175px;background:rgba(245,197,24,.07);border:1px solid rgba(245,197,24,.4);border-radius:12px;padding:18px 16px;position:relative;">
                <span style="position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:#F5C518;color:#0a0318;font-size:.58rem;font-weight:700;letter-spacing:.1em;padding:3px 10px;border-radius:10px;white-space:nowrap;">MOST POPULAR</span>
                <p style="font-size:.6rem;letter-spacing:.22em;text-transform:uppercase;color:#F5C518;margin:0 0 6px;opacity:.8;">Premium</p>
                <p style="font-size:1.4rem;font-weight:700;color:#fff;margin:0 0 4px;">$25<span style="font-size:.75rem;font-weight:300;opacity:.5;">/mo</span></p>
                <p style="font-size:.75rem;color:rgba(255,255,255,.5);margin:0 0 14px;line-height:1.5;">Everything in Starter<br>Earn <strong style="color:#F5C518;">$10/referral/mo</strong></p>
                <a href="<?php echo esc_url($premium_url); ?>" style="display:block;padding:9px 16px;background:#F5C518;border-radius:20px;color:#0a0318;font-size:.78rem;font-weight:600;text-decoration:none;letter-spacing:.06em;">Join Premium</a>
            </div>
        </div>
        <p style="font-size:.68rem;color:rgba(255,255,255,.25);margin:0;line-height:1.6;">Active membership required · $50 minimum before payout · Issued every 2 weeks</p>
    </div>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   C — CART SIDEBAR AFFILIATE NUDGE
   Above the cart totals block for non-member visitors.
   ════════════════════════════════════════════════════════════════════════════ */

add_action( 'woocommerce_before_cart_totals', 'excreet_357_cart_nudge' );

function excreet_357_cart_nudge(): void {
    if ( is_user_logged_in() && function_exists( 'pmpro_hasMembershipLevel' ) ) {
        if ( pmpro_hasMembershipLevel( null, get_current_user_id() ) ) { return; }
    }
    $url = function_exists( 'pmpro_url' ) ? pmpro_url( 'checkout', '?level=1' ) : home_url( '/membership-levels/' );
    ?>
    <div style="background:rgba(245,197,24,.07);border:1px solid rgba(245,197,24,.25);border-radius:10px;padding:14px 16px;margin-bottom:16px;font-family:'DM Sans',sans-serif;">
        <p style="font-size:.8rem;color:rgba(255,255,255,.75);margin:0 0 8px;line-height:1.55;">
            💛 <strong style="color:#F5C518;">Members earn $5–$10 per referral per month.</strong><br>
            Your first referral covers your membership.
        </p>
        <a href="<?php echo esc_url($url); ?>" style="font-size:.75rem;color:#F5C518;text-decoration:underline;letter-spacing:.04em;">See membership plans →</a>
    </div>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   D — PRODUCT PAGE AFFILIATE TEASER (below Add to Cart)
   Visible only to non-members.
   ════════════════════════════════════════════════════════════════════════════ */

add_action( 'woocommerce_after_add_to_cart_button', 'excreet_357_product_teaser' );

function excreet_357_product_teaser(): void {
    if ( is_user_logged_in() && function_exists( 'pmpro_hasMembershipLevel' ) ) {
        if ( pmpro_hasMembershipLevel( null, get_current_user_id() ) ) { return; }
    }
    $url = function_exists( 'pmpro_url' ) ? pmpro_url( 'checkout', '?level=1' ) : home_url( '/membership-levels/' );
    ?>
    <p style="font-size:.75rem;color:rgba(255,255,255,.45);margin:10px 0 0;line-height:1.5;font-family:'DM Sans',sans-serif;">
        💛 <a href="<?php echo esc_url($url); ?>" style="color:#F5C518;text-decoration:none;">Members earn $5–$10/month</a> sharing Excreet with others.
    </p>
    <?php
}
