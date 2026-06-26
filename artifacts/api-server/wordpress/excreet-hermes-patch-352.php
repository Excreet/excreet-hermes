<?php
/**
 * Plugin Name: Excreet Hermes — Patch 352 (Membership Clarity)
 * Description: Injects plain-language membership comparison tables and
 *              cancellation instructions on three key pages:
 *                - /membership-options/ (or /levels/) — tier comparison
 *                - /affiliate-area/                  — affiliate earnings
 *                - /membership-account/              — cancellation guide
 *              Addresses client audit: membership terms must be readable
 *              without opening the Terms & Waiver agreement.
 * Version: 3.5.2
 * Author: Excreet Engineering
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EX352_PURPLE',      '#6B2FA0' );
define( 'EX352_PURPLE_DARK', '#3D1060' );
define( 'EX352_GOLD',        '#F5C518' );
define( 'EX352_BG',          '#1a0a2e' );

add_action( 'wp_head',   'ex352_styles'                );
add_action( 'wp_footer', 'ex352_inject_all',        88 );

/* ── Shared styles ───────────────────────────────────────────────────────── */

function ex352_styles(): void {
    if ( ! ex352_is_target_page() ) return;
    ?>
<style id="ex352-styles">
/* ── Clarity card wrapper ── */
.ex352-clarity-wrap {
    margin: 36px auto;
    max-width: 900px;
    font-family: 'DM Sans', sans-serif;
}
.ex352-clarity-wrap * { box-sizing: border-box; }

/* ── Section heading ── */
.ex352-heading {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: clamp(22px, 3vw, 30px);
    font-weight: 700;
    color: <?php echo EX352_PURPLE; ?>;
    letter-spacing: .06em;
    text-transform: uppercase;
    text-align: center;
    margin: 0 0 8px;
}
.ex352-subheading {
    text-align: center;
    font-size: 14px;
    color: #666;
    margin: 0 0 28px;
}

/* ── Comparison table ── */
.ex352-table-wrap { overflow-x: auto; }
.ex352-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.ex352-table th,
.ex352-table td {
    padding: 13px 16px;
    border: 1px solid #e4d9f5;
    text-align: left;
    vertical-align: top;
    line-height: 1.55;
}
.ex352-table thead th {
    background: <?php echo EX352_PURPLE; ?>;
    color: #fff;
    font-weight: 700;
    font-size: 13px;
    letter-spacing: .05em;
    text-transform: uppercase;
}
.ex352-table thead th:first-child { background: <?php echo EX352_PURPLE_DARK; ?>; }
.ex352-table tbody tr:nth-child(even) td { background: #faf7ff; }
.ex352-table tbody tr:hover td { background: #f3ecfc; }
.ex352-table td:first-child {
    font-weight: 600;
    color: <?php echo EX352_PURPLE; ?>;
    white-space: nowrap;
}
.ex352-check  { color: #2d7a3c; font-weight: 700; }
.ex352-cross  { color: #b91c1c; }
.ex352-gold   { color: #b58900; font-weight: 700; }
.ex352-note   { font-size: 12px; color: #888; margin-top: 4px; }

/* ── Info cards ── */
.ex352-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px;
    margin-top: 24px;
}
.ex352-card {
    background: #fff;
    border: 1px solid #e4d9f5;
    border-radius: 10px;
    padding: 22px 24px;
    position: relative;
}
.ex352-card-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 19px;
    font-weight: 700;
    color: <?php echo EX352_PURPLE; ?>;
    margin: 0 0 10px;
}
.ex352-card-price {
    font-size: 28px;
    font-weight: 700;
    color: <?php echo EX352_PURPLE; ?>;
    line-height: 1;
    margin-bottom: 4px;
}
.ex352-card-price span { font-size: 14px; font-weight: 400; color: #888; }
.ex352-card ul {
    margin: 12px 0 0;
    padding-left: 0;
    list-style: none;
}
.ex352-card li {
    padding: 6px 0 6px 20px;
    font-size: 13.5px;
    color: #2d1a4a;
    border-bottom: 1px solid #f0ebf8;
    position: relative;
    line-height: 1.5;
}
.ex352-card li:last-child { border-bottom: none; }
.ex352-card li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #2d7a3c;
    font-weight: 700;
}
.ex352-card li.ex352-li-x::before { content: '×'; color: #b91c1c; }

/* ── Payout rules box ── */
.ex352-rules-box {
    background: #faf7ff;
    border: 1px solid #ddd5f5;
    border-radius: 8px;
    padding: 20px 24px;
    margin-top: 18px;
    font-size: 13.5px;
    color: #2d1a4a;
    line-height: 1.7;
}
.ex352-rules-box strong { color: <?php echo EX352_PURPLE; ?>; }
.ex352-rules-box ul { margin: 8px 0 0; padding-left: 20px; }
.ex352-rules-box li { margin-bottom: 6px; }

/* ── Cancel box ── */
.ex352-cancel-box {
    background: #fff8e1;
    border-left: 4px solid <?php echo EX352_GOLD; ?>;
    border-radius: 6px;
    padding: 20px 24px;
    margin-top: 20px;
    font-size: 14px;
    line-height: 1.7;
    color: #3d2a00;
}
.ex352-cancel-box h4 {
    margin: 0 0 10px;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 18px;
    color: #7a5200;
}
.ex352-cancel-box ol { margin: 0; padding-left: 20px; }
.ex352-cancel-box li { margin-bottom: 7px; }
.ex352-cancel-box a { color: <?php echo EX352_PURPLE; ?>; }

/* ── Geo note ── */
.ex352-geo-note {
    background: #e8f5e9;
    border-left: 4px solid #2d7a3c;
    border-radius: 6px;
    padding: 14px 18px;
    font-size: 13px;
    color: #1b3a20;
    margin-top: 18px;
    line-height: 1.6;
}
</style>
    <?php
}

/* ── Page detection ───────────────────────────────────────────────────────── */

function ex352_is_target_page(): bool {
    return ex352_is_pricing_page()
        || ex352_is_affiliate_page()
        || ex352_is_account_page();
}

function ex352_is_pricing_page(): bool {
    return is_page( [ 'membership-options', 'levels', 'pricing', 'join', 'membership' ] )
        || ( function_exists( 'pmpro_is_checkout' ) && false );
}

function ex352_is_affiliate_page(): bool {
    return is_page( [ 'affiliate-area', 'affiliate', 'referrals' ] );
}

function ex352_is_account_page(): bool {
    return is_page( [ 'membership-account', 'my-account', 'account' ] )
        || ( function_exists( 'pmpro_is_level' ) && false );
}

/* ── Main injection dispatcher ────────────────────────────────────────────── */

function ex352_inject_all(): void {
    if ( ex352_is_pricing_page() )   ex352_pricing_table();
    if ( ex352_is_affiliate_page() ) ex352_affiliate_section();
    if ( ex352_is_account_page() )   ex352_account_cancel();
}

/* ════════════════════════════════════════════════════════════════════════════
   PRICING PAGE — tier comparison table
   ════════════════════════════════════════════════════════════════════════════ */

function ex352_pricing_table(): void {
    ?>
<div class="ex352-clarity-wrap" id="ex352-pricing">
    <h2 class="ex352-heading">What You Get</h2>
    <p class="ex352-subheading">Plain-language breakdown of both membership tiers.</p>

    <div class="ex352-cards">
        <div class="ex352-card">
            <div class="ex352-card-title">Starter</div>
            <div class="ex352-card-price">$15 <span>/ month</span></div>
            <ul>
                <li>Daily Body Check (unlimited)</li>
                <li>10 Ministry of Healing sessions / month</li>
                <li>Access to the Excreet Store</li>
                <li>Automatic affiliate enrollment</li>
                <li class="ex352-li-x">SMS morning notifications (US numbers only)</li>
                <li class="ex352-li-x">Excreet bottle — US shipping only</li>
            </ul>
        </div>
        <div class="ex352-card">
            <div class="ex352-card-title">Premium</div>
            <div class="ex352-card-price">$25 <span>/ month</span></div>
            <ul>
                <li>Daily Body Check (unlimited)</li>
                <li>20 Ministry of Healing sessions / month</li>
                <li>Access to the Excreet Store</li>
                <li>Automatic affiliate enrollment</li>
                <li class="ex352-li-x">SMS morning notifications (US numbers only)</li>
                <li class="ex352-li-x">Excreet bottle — US shipping only</li>
            </ul>
        </div>
    </div>

    <div class="ex352-table-wrap" style="margin-top:28px;">
        <table class="ex352-table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Starter — $15/mo</th>
                    <th>Premium — $25/mo</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Daily Body Check</td>
                    <td><span class="ex352-check">✓</span> Unlimited</td>
                    <td><span class="ex352-check">✓</span> Unlimited</td>
                </tr>
                <tr>
                    <td>Ministry of Healing sessions</td>
                    <td>10 per month</td>
                    <td>20 per month</td>
                </tr>
                <tr>
                    <td>Excreet Store access</td>
                    <td><span class="ex352-check">✓</span> Included</td>
                    <td><span class="ex352-check">✓</span> Included</td>
                </tr>
                <tr>
                    <td>Affiliate program</td>
                    <td><span class="ex352-check">✓</span> Automatic</td>
                    <td><span class="ex352-check">✓</span> Automatic</td>
                </tr>
                <tr>
                    <td>Referral earnings (Starter referral)</td>
                    <td><span class="ex352-gold">$5/mo</span> per active referral</td>
                    <td><span class="ex352-gold">$5/mo</span> per active referral</td>
                </tr>
                <tr>
                    <td>Referral earnings (Premium referral)</td>
                    <td><span class="ex352-gold">$5/mo</span> per active referral</td>
                    <td><span class="ex352-gold">$10/mo</span> per active referral</td>
                </tr>
                <tr>
                    <td>SMS morning notifications</td>
                    <td colspan="2" style="text-align:center;">US registered phone numbers only</td>
                </tr>
                <tr>
                    <td>Excreet bottle product</td>
                    <td colspan="2" style="text-align:center;">US shipping only (at this time)</td>
                </tr>
                <tr>
                    <td>Store / ancillary service proceeds</td>
                    <td colspan="2" style="text-align:center;"><span class="ex352-cross">×</span> Not included in affiliate earnings</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="ex352-geo-note">
        <strong>Geographic note:</strong> SMS morning notifications are only delivered to US-registered phone numbers.
        The Excreet signature bottle ships within the United States only at this time. International shipping will be announced when available.
    </div>

    <?php ex352_cancel_note(); ?>
</div>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   AFFILIATE AREA — earnings clarity
   ════════════════════════════════════════════════════════════════════════════ */

function ex352_affiliate_section(): void {
    ?>
<div class="ex352-clarity-wrap" id="ex352-affiliate">
    <h2 class="ex352-heading">Your Affiliate Earnings</h2>
    <p class="ex352-subheading">Both Starter and Premium members are full affiliates. Here is exactly what you earn.</p>

    <div class="ex352-table-wrap">
        <table class="ex352-table">
            <thead>
                <tr>
                    <th>Your Membership</th>
                    <th>You Refer a Starter ($15/mo)</th>
                    <th>You Refer a Premium ($25/mo)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Starter — $15/mo</td>
                    <td><span class="ex352-gold">$5 / month</span><div class="ex352-note">While both memberships stay active</div></td>
                    <td><span class="ex352-gold">$5 / month</span><div class="ex352-note">While both memberships stay active</div></td>
                </tr>
                <tr>
                    <td>Premium — $25/mo</td>
                    <td><span class="ex352-gold">$5 / month</span><div class="ex352-note">While both memberships stay active</div></td>
                    <td><span class="ex352-gold">$10 / month</span><div class="ex352-note">While both memberships stay active</div></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="ex352-rules-box">
        <strong>Payout rules — read before you refer:</strong>
        <ul>
            <li>Your own membership must be <strong>active and current</strong> for earnings to count.</li>
            <li>Earnings accumulate until you reach a <strong>minimum $50 balance</strong>, then issue on the next payout date.</li>
            <li>Payouts are issued <strong>every 2 weeks</strong>.</li>
            <li><strong>Store purchases and ancillary services</strong> do not generate affiliate commissions — earnings come from membership referrals only.</li>
            <li>If your referred member cancels, earnings for that member stop immediately.</li>
            <li>If your own membership lapses, earnings pause until you reactivate.</li>
        </ul>
    </div>
</div>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   MEMBERSHIP ACCOUNT PAGE — cancellation guide
   ════════════════════════════════════════════════════════════════════════════ */

function ex352_account_cancel(): void {
    ?>
<div class="ex352-clarity-wrap" id="ex352-account">
    <?php ex352_cancel_note(); ?>
</div>
    <?php
}

/* ── Reusable cancel block ───────────────────────────────────────────────── */

function ex352_cancel_note(): void {
    ?>
<div class="ex352-cancel-box">
    <h4>How to Cancel Your Membership</h4>
    <p>You may cancel at any time. Your access continues through the end of your current billing period.</p>
    <ol>
        <li>Log in to your account at <a href="/membership-account/">/membership-account/</a>.</li>
        <li>Under <strong>Membership</strong>, click <strong>Cancel</strong> next to your active plan.</li>
        <li>Confirm the cancellation. You will receive an email confirmation.</li>
        <li>Your membership remains active until the end of the period you have already paid for.</li>
        <li>Affiliate earnings already accumulated above $50 will be paid out on the next scheduled payout date.</li>
    </ol>
    <p style="margin-top:12px;font-size:13px;">
        Need help? Email <strong>support@excreet.com</strong> and a team member will assist within 1 business day.
    </p>
</div>
    <?php
}
