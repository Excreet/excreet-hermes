<?php
/**
 * Plugin Name: Excreet Hermes — Patch 354 (Membership Pricing Page)
 * Description: Auto-creates /membership-options/ as a fully styled,
 *              mobile-first pricing page with large tappable "Join" buttons
 *              that route directly into PMPro checkout for Level 1 (Starter)
 *              and Level 2 (Premium). Fixes the broken "Become a Member"
 *              flow reported by prospective members.
 * Version: 3.5.4
 * Author: Excreet Engineering
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EX354_PAGE_SLUG',   'membership-options' );
define( 'EX354_PAGE_OPTION', '_excreet_354_pricing_page_id' );

/* ── Bootstrap ───────────────────────────────────────────────────────────── */
add_action( 'init',      'ex354_register_shortcode' );
add_action( 'init',      'ex354_ensure_page',        20 );
add_action( 'wp_head',   'ex354_styles' );

/* ── Shortcode ───────────────────────────────────────────────────────────── */
function ex354_register_shortcode(): void {
    add_shortcode( 'excreet_pricing', 'ex354_render' );
}

/* ── Auto-create the page ────────────────────────────────────────────────── */
function ex354_ensure_page(): void {
    $existing_id = (int) get_option( EX354_PAGE_OPTION );
    if ( $existing_id && get_post_status( $existing_id ) === 'publish' ) return;

    $slug_check = get_page_by_path( EX354_PAGE_SLUG, OBJECT, 'page' );
    if ( $slug_check ) {
        update_option( EX354_PAGE_OPTION, $slug_check->ID );
        // Ensure shortcode is in the content
        if ( strpos( $slug_check->post_content, 'excreet_pricing' ) === false ) {
            wp_update_post( [
                'ID'           => $slug_check->ID,
                'post_content' => '[excreet_pricing]',
            ] );
        }
        return;
    }

    $page_id = wp_insert_post( [
        'post_title'   => 'Membership Options',
        'post_name'    => EX354_PAGE_SLUG,
        'post_content' => '[excreet_pricing]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => 1,
    ] );

    if ( ! is_wp_error( $page_id ) ) {
        update_option( EX354_PAGE_OPTION, $page_id );
        // Wire PMPro pages option so PMPro knows where pricing lives
        if ( function_exists( 'pmpro_setOption' ) ) {
            pmpro_setOption( 'levels_page_id', $page_id );
        } else {
            update_option( 'pmpro_levels_page_id', $page_id );
        }
    }
}

/* ── Checkout URL helper ─────────────────────────────────────────────────── */
function ex354_checkout_url( int $level ): string {
    if ( function_exists( 'pmpro_url' ) ) {
        return pmpro_url( 'checkout', '?level=' . $level );
    }
    return home_url( '/membership-account/?pmpro_checkout=1&level=' . $level );
}

/* ── Styles ──────────────────────────────────────────────────────────────── */
function ex354_styles(): void {
    $page_id = (int) get_option( EX354_PAGE_OPTION );
    if ( ! is_page( EX354_PAGE_SLUG ) && ! is_page( $page_id ) ) return;
    ?>
<style id="ex354-styles">
/* ── Reset & base ── */
.ex354-wrap *, .ex354-wrap *::before, .ex354-wrap *::after {
    box-sizing: border-box;
}
.ex354-wrap {
    font-family: 'Poppins', 'Segoe UI', system-ui, -apple-system, sans-serif;
    max-width: 880px;
    margin: 0 auto;
    padding: 0 16px 64px;
    color: #e8e0f0;
}

/* ── Hero ── */
.ex354-hero {
    text-align: center;
    padding: 40px 8px 28px;
}
.ex354-hero-eyebrow {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #C9A84C;
    margin-bottom: 12px;
}
.ex354-hero h1 {
    font-size: clamp(26px, 5vw, 40px);
    font-weight: 800;
    color: #C9A84C;
    line-height: 1.2;
    margin: 0 0 14px;
    text-shadow: 0 2px 12px rgba(0,0,0,0.4);
}
.ex354-hero-sub {
    font-size: 15px;
    color: rgba(232,224,240,0.8);
    max-width: 500px;
    margin: 0 auto;
    line-height: 1.7;
}

/* ── Billing note pill ── */
.ex354-billing-note {
    text-align: center;
    font-size: 12px;
    color: rgba(232,224,240,0.7);
    margin: 0 0 28px;
    padding: 8px 20px;
    background: rgba(107,33,168,0.3);
    border: 1px solid rgba(201,168,76,0.3);
    border-radius: 20px;
    display: inline-block;
    position: relative;
    left: 50%;
    transform: translateX(-50%);
    backdrop-filter: blur(4px);
}

/* ── Card grid ── */
.ex354-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 36px;
}
@media (max-width: 560px) {
    .ex354-cards {
        grid-template-columns: 1fr;
        gap: 24px;
    }
}

/* ── Individual card ── */
.ex354-card {
    background: rgba(20, 5, 38, 0.82);
    border: 1px solid rgba(107,33,168,0.5);
    border-radius: 18px;
    padding: 32px 26px 26px;
    position: relative;
    display: flex;
    flex-direction: column;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: box-shadow 0.25s, border-color 0.25s, transform 0.2s;
}
.ex354-card:hover {
    box-shadow: 0 12px 40px rgba(107,33,168,0.35);
    border-color: rgba(139,92,246,0.7);
    transform: translateY(-2px);
}
.ex354-card.featured {
    border-color: rgba(201,168,76,0.6);
    background: rgba(30, 8, 50, 0.88);
    box-shadow: 0 12px 40px rgba(201,168,76,0.2);
}
.ex354-card.featured:hover {
    box-shadow: 0 16px 48px rgba(201,168,76,0.3);
    border-color: rgba(201,168,76,0.85);
}

/* Badge */
.ex354-badge {
    position: absolute;
    top: -13px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(90deg, #C9A84C, #B8860B);
    color: #1a0a2e;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    padding: 5px 16px;
    border-radius: 20px;
    white-space: nowrap;
    box-shadow: 0 2px 12px rgba(184,134,11,0.45);
}

.ex354-tier-name {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: #C9A84C;
    margin-bottom: 8px;
}
.ex354-price-row {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    margin-bottom: 4px;
}
.ex354-price-amt {
    font-size: 46px;
    font-weight: 900;
    color: #ffffff;
    line-height: 1;
}
.ex354-price-meta {
    font-size: 14px;
    color: rgba(232,224,240,0.55);
    padding-bottom: 6px;
}
.ex354-price-desc {
    font-size: 12.5px;
    color: rgba(232,224,240,0.6);
    margin-bottom: 20px;
    line-height: 1.5;
}

/* Feature list */
.ex354-features {
    list-style: none;
    padding: 0;
    margin: 0 0 26px;
    flex: 1;
}
.ex354-features li {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    font-size: 13.5px;
    color: #e8e0f0;
    padding: 8px 0;
    border-bottom: 1px solid rgba(107,33,168,0.25);
    line-height: 1.45;
}
.ex354-features li:last-child { border-bottom: none; }
.ex354-feat-icon {
    font-size: 14px;
    flex-shrink: 0;
    margin-top: 1px;
}
.ex354-feat-muted {
    color: rgba(232,224,240,0.45);
    font-size: 12px;
}

/* ── CTA Button ── */
.ex354-btn {
    display: block;
    width: 100%;
    text-align: center;
    text-decoration: none;
    font-size: 15px;
    font-weight: 700;
    padding: 16px 20px;
    border-radius: 12px;
    letter-spacing: 0.4px;
    transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
    cursor: pointer;
    border: none;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
}
.ex354-btn:active { transform: scale(0.97); }
.ex354-btn-starter {
    background: linear-gradient(135deg, #7C3AED, #5B1FA8);
    color: #ffffff;
    box-shadow: 0 4px 20px rgba(107,33,168,0.5);
}
.ex354-btn-starter:hover {
    background: linear-gradient(135deg, #8B5CF6, #7C3AED);
    box-shadow: 0 6px 24px rgba(107,33,168,0.6);
    color: #fff;
    text-decoration: none;
}
.ex354-btn-premium {
    background: linear-gradient(135deg, #C9A84C, #9A7A1F);
    color: #1a0a2e;
    box-shadow: 0 4px 20px rgba(184,134,11,0.45);
}
.ex354-btn-premium:hover {
    background: linear-gradient(135deg, #D9B85C, #C9A84C);
    box-shadow: 0 6px 24px rgba(184,134,11,0.55);
    color: #1a0a2e;
    text-decoration: none;
}

/* ── Trust strip ── */
.ex354-trust {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
    margin: 0 0 32px;
    padding: 16px 20px;
    background: rgba(107,33,168,0.2);
    border: 1px solid rgba(107,33,168,0.3);
    border-radius: 12px;
    backdrop-filter: blur(8px);
}
.ex354-trust-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    color: rgba(232,224,240,0.8);
    font-weight: 600;
}
.ex354-trust-item span.icon { font-size: 15px; }

/* ── Comparison table ── */
.ex354-compare-title {
    font-size: 11px;
    font-weight: 700;
    color: #C9A84C;
    letter-spacing: 2px;
    text-transform: uppercase;
    text-align: center;
    margin: 0 0 16px;
}
.ex354-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 12px; }
.ex354-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
    min-width: 320px;
}
.ex354-table thead th {
    background: rgba(107,33,168,0.7);
    color: #fff;
    padding: 13px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.ex354-table thead th:first-child { background: rgba(50,10,80,0.85); }
.ex354-table tbody tr { background: rgba(20,5,38,0.65); }
.ex354-table tbody tr:nth-child(even) { background: rgba(40,10,65,0.65); }
.ex354-table tbody td {
    padding: 11px 16px;
    border-bottom: 1px solid rgba(107,33,168,0.2);
    color: #e8e0f0;
    vertical-align: middle;
}
.ex354-table tbody td:first-child {
    font-weight: 600;
    color: rgba(232,224,240,0.9);
}
.chk  { color: #4ade80; font-weight: 700; }
.gold { color: #C9A84C; font-weight: 700; }
.mute { color: rgba(232,224,240,0.4); font-size: 12px; }

/* ── FAQ section ── */
.ex354-faq { margin-top: 36px; }
.ex354-faq-title {
    font-size: 11px;
    font-weight: 700;
    color: #C9A84C;
    letter-spacing: 2px;
    text-transform: uppercase;
    text-align: center;
    margin: 0 0 18px;
}
.ex354-faq-item {
    border-bottom: 1px solid rgba(107,33,168,0.25);
    padding: 16px 0;
}
.ex354-faq-q {
    font-size: 14px;
    font-weight: 700;
    color: #e8e0f0;
    margin-bottom: 6px;
}
.ex354-faq-a {
    font-size: 13px;
    color: rgba(232,224,240,0.65);
    line-height: 1.7;
}
.ex354-faq-a a { color: #C9A84C; }

/* ── Footer note ── */
.ex354-footer-note {
    text-align: center;
    font-size: 12px;
    color: rgba(232,224,240,0.4);
    margin-top: 32px;
    line-height: 1.7;
}
.ex354-footer-note a { color: rgba(201,168,76,0.7); }

/* ── Bottom CTA row ── */
.ex354-bottom-ctas {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 36px;
}
@media (max-width: 480px) {
    .ex354-bottom-ctas { grid-template-columns: 1fr; }
}

/* ── Mobile touch targets ── */
@media (max-width: 480px) {
    .ex354-btn {
        font-size: 17px;
        padding: 18px 20px;
        border-radius: 14px;
    }
    .ex354-card { padding: 30px 18px 24px; }
    .ex354-price-amt { font-size: 52px; }
    .ex354-hero h1 { font-size: 28px; }
    .ex354-trust { gap: 14px; }
}
</style>
    <?php
}

/* ── Shortcode renderer ──────────────────────────────────────────────────── */
function ex354_render(): string {
    $starter_url = ex354_checkout_url( 1 );
    $premium_url = ex354_checkout_url( 2 );

    ob_start(); ?>
<div class="ex354-wrap">

    <!-- Hero -->
    <div class="ex354-hero">
        <div class="ex354-hero-eyebrow">Excreet Membership</div>
        <h1>Your body checks in every morning.<br>Are you listening?</h1>
        <p class="ex354-hero-sub">Choose a plan and start reading your body's daily signals in under 5 minutes.</p>
    </div>

    <div class="ex354-billing-note">&#128197;&nbsp; Billed monthly &nbsp;&middot;&nbsp; Cancel anytime &nbsp;&middot;&nbsp; No contracts</div>

    <!-- Trust strip -->
    <div class="ex354-trust">
        <div class="ex354-trust-item"><span class="icon">&#128274;</span> Secure checkout via Stripe</div>
        <div class="ex354-trust-item"><span class="icon">&#10003;</span> Cancel anytime, no fees</div>
        <div class="ex354-trust-item"><span class="icon">&#129309;</span> Affiliate earnings from day one</div>
        <div class="ex354-trust-item"><span class="icon">&#128202;</span> AI-powered daily scoring</div>
    </div>

    <!-- Tier cards -->
    <div class="ex354-cards">

        <!-- Starter -->
        <div class="ex354-card">
            <div class="ex354-tier-name">Starter</div>
            <div class="ex354-price-row">
                <div class="ex354-price-amt">$15</div>
                <div class="ex354-price-meta">/ mo</div>
            </div>
            <div class="ex354-price-desc">Best way to start reading your body.</div>
            <ul class="ex354-features">
                <li><span class="ex354-feat-icon">&#9989;</span> Daily Body Check &mdash; unlimited</li>
                <li><span class="ex354-feat-icon">&#129492;</span> 10 Ministry of Healing sessions / mo</li>
                <li><span class="ex354-feat-icon">&#128200;</span> Vitality Score + pattern reading</li>
                <li><span class="ex354-feat-icon">&#128105;&#8205;&#9877;</span> Doctor Visit Summary (Protocol &amp; Alarm)</li>
                <li><span class="ex354-feat-icon">&#128139;</span> Affiliate program &mdash; earn $5&ndash;$10/mo per referral</li>
                <li><span class="ex354-feat-icon">&#128722;</span> Excreet Store access</li>
                <li><span class="ex354-feat-icon">&#128241;</span> <span class="ex354-feat-muted">SMS reminders &mdash; US numbers only</span></li>
            </ul>
            <a href="<?php echo esc_url( $starter_url ); ?>" class="ex354-btn ex354-btn-starter">
                Join Starter &mdash; $15/mo
            </a>
        </div>

        <!-- Premium -->
        <div class="ex354-card featured">
            <div class="ex354-badge">Most Popular</div>
            <div class="ex354-tier-name">Premium</div>
            <div class="ex354-price-row">
                <div class="ex354-price-amt">$25</div>
                <div class="ex354-price-meta">/ mo</div>
            </div>
            <div class="ex354-price-desc">Double the healing sessions. Double the insight.</div>
            <ul class="ex354-features">
                <li><span class="ex354-feat-icon">&#9989;</span> Daily Body Check &mdash; unlimited</li>
                <li><span class="ex354-feat-icon">&#129492;</span> 20 Ministry of Healing sessions / mo</li>
                <li><span class="ex354-feat-icon">&#128200;</span> Vitality Score + pattern reading</li>
                <li><span class="ex354-feat-icon">&#128105;&#8205;&#9877;</span> Doctor Visit Summary (Protocol &amp; Alarm)</li>
                <li><span class="ex354-feat-icon">&#128139;</span> Affiliate program &mdash; earn up to $10/mo per Premium referral</li>
                <li><span class="ex354-feat-icon">&#128722;</span> Excreet Store access</li>
                <li><span class="ex354-feat-icon">&#128241;</span> <span class="ex354-feat-muted">SMS reminders &mdash; US numbers only</span></li>
            </ul>
            <a href="<?php echo esc_url( $premium_url ); ?>" class="ex354-btn ex354-btn-premium">
                Join Premium &mdash; $25/mo
            </a>
        </div>

    </div><!-- /cards -->

    <!-- Comparison table -->
    <div class="ex354-compare-title">Full Comparison</div>
    <div class="ex354-table-scroll">
        <table class="ex354-table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Starter &mdash; $15</th>
                    <th>Premium &mdash; $25</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Daily Body Check</td>
                    <td><span class="chk">&#10003;</span> Unlimited</td>
                    <td><span class="chk">&#10003;</span> Unlimited</td>
                </tr>
                <tr>
                    <td>Ministry of Healing</td>
                    <td>10 sessions / mo</td>
                    <td>20 sessions / mo</td>
                </tr>
                <tr>
                    <td>Vitality Score &amp; reading</td>
                    <td><span class="chk">&#10003;</span></td>
                    <td><span class="chk">&#10003;</span></td>
                </tr>
                <tr>
                    <td>Doctor Visit Summary</td>
                    <td><span class="chk">&#10003;</span></td>
                    <td><span class="chk">&#10003;</span></td>
                </tr>
                <tr>
                    <td>Affiliate program</td>
                    <td><span class="chk">&#10003;</span> Automatic</td>
                    <td><span class="chk">&#10003;</span> Automatic</td>
                </tr>
                <tr>
                    <td>Earn on Starter referrals</td>
                    <td><span class="gold">$5/mo</span></td>
                    <td><span class="gold">$5/mo</span></td>
                </tr>
                <tr>
                    <td>Earn on Premium referrals</td>
                    <td><span class="gold">$5/mo</span></td>
                    <td><span class="gold">$10/mo</span></td>
                </tr>
                <tr>
                    <td>Excreet Store</td>
                    <td><span class="chk">&#10003;</span></td>
                    <td><span class="chk">&#10003;</span></td>
                </tr>
                <tr>
                    <td>SMS morning reminders</td>
                    <td colspan="2" style="text-align:center;"><span class="mute">US phone numbers only</span></td>
                </tr>
                <tr>
                    <td>Excreet bottle shipping</td>
                    <td colspan="2" style="text-align:center;"><span class="mute">US only at this time</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- FAQ -->
    <div class="ex354-faq">
        <div class="ex354-faq-title">Common Questions</div>

        <div class="ex354-faq-item">
            <div class="ex354-faq-q">Can I cancel anytime?</div>
            <div class="ex354-faq-a">Yes. Cancel from your <a href="/membership-account/">membership account page</a> at any time. You keep access through the end of the period you already paid for. No cancellation fees.</div>
        </div>
        <div class="ex354-faq-item">
            <div class="ex354-faq-q">What do I need to do the daily body check?</div>
            <div class="ex354-faq-a">Just pH test strips (available on Amazon for ~$8) and your phone camera. No special equipment. The whole check-in takes under 5 minutes.</div>
        </div>
        <div class="ex354-faq-item">
            <div class="ex354-faq-q">What is the Ministry of Healing?</div>
            <div class="ex354-faq-a">Your private AI health intelligence companion. It knows your check-in history and can guide you through what your body signals mean, what to do about them, and what to bring to a doctor visit.</div>
        </div>
        <div class="ex354-faq-item">
            <div class="ex354-faq-q">How does the affiliate program work?</div>
            <div class="ex354-faq-a">Every member gets a referral link automatically on day one. When someone joins through your link, you earn a monthly commission as long as both of you stay active. Minimum $50 before payout, issued every 2 weeks.</div>
        </div>
        <div class="ex354-faq-item">
            <div class="ex354-faq-q">Is this a medical product?</div>
            <div class="ex354-faq-a">No. Excreet is an educational wellness tool. It reads body signal patterns and helps you ask better questions. It does not diagnose, treat, or replace medical care.</div>
        </div>
    </div>

    <!-- CTA repeat (bottom) -->
    <div class="ex354-bottom-ctas">
        <a href="<?php echo esc_url( $starter_url ); ?>" class="ex354-btn ex354-btn-starter">Join Starter &mdash; $15/mo</a>
        <a href="<?php echo esc_url( $premium_url ); ?>" class="ex354-btn ex354-btn-premium">Join Premium &mdash; $25/mo</a>
    </div>

    <div class="ex354-footer-note">
        Secure checkout powered by Stripe &nbsp;&middot;&nbsp;
        <a href="/terms/">Terms</a> &nbsp;&middot;&nbsp;
        <a href="/privacy-policy/">Privacy</a> &nbsp;&middot;&nbsp;
        Questions? <a href="mailto:support@excreet.com">support@excreet.com</a>
    </div>

</div>
    <?php
    return ob_get_clean();
}
