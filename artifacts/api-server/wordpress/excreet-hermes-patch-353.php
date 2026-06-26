<?php
/**
 * Plugin Name: Excreet Hermes — Patch 353 (Member Guide Page)
 * Description: Renders the full Member User Guide as a gated HTML page
 *              at /member-guide/. Only logged-in members with an active
 *              PMPro membership can view the content. Auto-creates the WP
 *              page on first load. Injects a "Member Guide" link into the
 *              Member Dashboard page. No downloadable PDF is served.
 * Version: 3.5.3
 * Author: Excreet Engineering
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Constants ───────────────────────────────────────────────────────────── */
define( 'EX353_PAGE_SLUG',    'member-guide' );
define( 'EX353_PAGE_OPTION',  '_excreet_353_guide_page_id' );

/* ── Bootstrap ───────────────────────────────────────────────────────────── */
add_action( 'init',      'ex353_register_shortcode' );
add_action( 'init',      'ex353_ensure_page',        20 );
add_action( 'wp_head',   'ex353_styles' );
add_action( 'wp_footer', 'ex353_inject_dashboard_link', 88 );

/* ── Shortcode registration ──────────────────────────────────────────────── */
function ex353_register_shortcode(): void {
    add_shortcode( 'excreet_member_guide', 'ex353_render_guide' );
}

/* ── Auto-create the /member-guide/ page ─────────────────────────────────── */
function ex353_ensure_page(): void {
    $existing_id = (int) get_option( EX353_PAGE_OPTION );
    if ( $existing_id && get_post_status( $existing_id ) === 'publish' ) return;

    $slug_check = get_page_by_path( EX353_PAGE_SLUG, OBJECT, 'page' );
    if ( $slug_check ) {
        update_option( EX353_PAGE_OPTION, $slug_check->ID );
        return;
    }

    $page_id = wp_insert_post( [
        'post_title'   => 'Member Guide',
        'post_name'    => EX353_PAGE_SLUG,
        'post_content' => '[excreet_member_guide]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => 1,
        'meta_input'   => [
            '_wp_page_template' => 'default',
        ],
    ] );

    if ( ! is_wp_error( $page_id ) ) {
        update_option( EX353_PAGE_OPTION, $page_id );
    }
}

/* ── Membership gate helper ──────────────────────────────────────────────── */
function ex353_is_member(): bool {
    if ( ! is_user_logged_in() ) return false;
    if ( function_exists( 'pmpro_hasMembershipLevel' ) ) {
        return (bool) pmpro_hasMembershipLevel( null, get_current_user_id() );
    }
    return true; // fallback: trust WP login if PMPro absent
}

/* ── Styles ──────────────────────────────────────────────────────────────── */
function ex353_styles(): void {
    if ( ! is_page( EX353_PAGE_SLUG ) && ! is_page( (int) get_option( EX353_PAGE_OPTION ) ) ) return;
    ?>
<style id="ex353-styles">
/* ── Guide wrapper ── */
.ex353-guide {
    max-width: 820px;
    margin: 0 auto;
    padding: 0 16px 60px;
    font-family: 'DM Sans', sans-serif;
    color: #2a0a4a;
}
.ex353-guide *, .ex353-guide *::before, .ex353-guide *::after {
    box-sizing: border-box;
}

/* ── Cover banner ── */
.ex353-cover {
    background: linear-gradient(135deg, #1a0a2e 0%, #3D1060 100%);
    border-radius: 12px;
    padding: 40px 36px 32px;
    margin-bottom: 40px;
    text-align: center;
}
.ex353-cover-wordmark {
    font-size: 36px;
    font-weight: 800;
    letter-spacing: 8px;
    color: #C9A84C;
    margin-bottom: 6px;
}
.ex353-cover-tagline {
    font-size: 12px;
    letter-spacing: 3px;
    color: #bbbbbb;
    text-transform: uppercase;
    margin-bottom: 24px;
}
.ex353-cover-rule {
    width: 60px;
    height: 2px;
    background: #C9A84C;
    margin: 0 auto 20px;
}
.ex353-cover-title {
    font-size: 22px;
    font-weight: 600;
    color: #ffffff;
    margin-bottom: 6px;
}
.ex353-cover-sub {
    font-size: 13px;
    color: #cccccc;
    font-style: italic;
}

/* ── Section headings ── */
.ex353-h2 {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #6B21A8;
    margin: 36px 0 6px;
    padding-bottom: 4px;
    border-bottom: 2px solid #C9A84C;
}
.ex353-h3 {
    font-size: 14px;
    font-weight: 700;
    color: #2a0a4a;
    margin: 18px 0 6px;
}

/* ── Body text ── */
.ex353-body {
    font-size: 14px;
    line-height: 1.65;
    color: #444444;
    margin: 0 0 10px;
}

/* ── Bullets ── */
.ex353-list {
    padding-left: 20px;
    margin: 0 0 10px;
}
.ex353-list li {
    font-size: 13.5px;
    line-height: 1.6;
    color: #444444;
    margin-bottom: 4px;
}

/* ── Numbered steps ── */
.ex353-steps { margin: 16px 0; }
.ex353-step {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 14px;
}
.ex353-step-num {
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    background: #6B21A8;
    color: #ffffff;
    font-weight: 700;
    font-size: 14px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 1px;
}
.ex353-step-body {}
.ex353-step-title {
    font-size: 14px;
    font-weight: 700;
    color: #2a0a4a;
    margin-bottom: 3px;
}
.ex353-step-desc {
    font-size: 13px;
    color: #555555;
    line-height: 1.55;
}

/* ── Tip box ── */
.ex353-tip {
    background: #f5f0ff;
    border-left: 3px solid #6B21A8;
    border-radius: 0 8px 8px 0;
    padding: 10px 14px;
    font-size: 13px;
    color: #3D1060;
    margin: 12px 0 16px;
    font-weight: 600;
}

/* ── Warning box ── */
.ex353-warn {
    background: #fffbeb;
    border-left: 3px solid #B8860B;
    border-radius: 0 8px 8px 0;
    padding: 10px 14px;
    font-size: 13px;
    color: #78450a;
    margin: 12px 0 16px;
    font-style: italic;
}

/* ── Tiers ── */
.ex353-tiers { margin: 12px 0 20px; }
.ex353-tier {
    border: 1px solid #e4d9f5;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 10px;
    background: #faf7ff;
}
.ex353-tier-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #6B21A8;
    margin-bottom: 4px;
}
.ex353-tier-desc {
    font-size: 13px;
    color: #444444;
    line-height: 1.55;
}

/* ── Earnings table ── */
.ex353-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin: 12px 0 18px;
}
.ex353-table thead tr {
    background: #6B21A8;
    color: #ffffff;
}
.ex353-table thead th {
    padding: 8px 12px;
    text-align: left;
    font-weight: 700;
    font-size: 12px;
}
.ex353-table tbody tr:nth-child(odd)  { background: #faf7ff; }
.ex353-table tbody tr:nth-child(even) { background: #ffffff; }
.ex353-table tbody td {
    padding: 8px 12px;
    border-bottom: 1px solid #e8e0f5;
    color: #444444;
}
.ex353-table tbody td.earn {
    color: #166534;
    font-weight: 700;
}

/* ── KV rows ── */
.ex353-kv {
    display: flex;
    gap: 8px;
    font-size: 13px;
    margin-bottom: 5px;
}
.ex353-kv-key {
    font-weight: 700;
    color: #2a0a4a;
    min-width: 160px;
    flex-shrink: 0;
}
.ex353-kv-val { color: #444444; }

/* ── Divider ── */
.ex353-rule {
    border: none;
    border-top: 1px solid #e8e0f5;
    margin: 28px 0;
}

/* ── Gate message ── */
.ex353-gate {
    text-align: center;
    padding: 60px 24px;
    background: linear-gradient(135deg, #1a0a2e 0%, #3D1060 100%);
    border-radius: 12px;
    color: #ffffff;
}
.ex353-gate h2 { font-size: 22px; color: #C9A84C; margin-bottom: 10px; }
.ex353-gate p  { font-size: 14px; color: #cccccc; margin-bottom: 24px; }
.ex353-gate a.btn {
    display: inline-block;
    background: #C9A84C;
    color: #1a0a2e;
    font-weight: 700;
    font-size: 14px;
    padding: 12px 28px;
    border-radius: 6px;
    text-decoration: none;
    margin: 0 6px 10px;
}
.ex353-gate a.btn-outline {
    background: transparent;
    border: 2px solid #C9A84C;
    color: #C9A84C;
}

/* ── Quick reference box ── */
.ex353-qr {
    background: #1a0a2e;
    border-radius: 10px;
    padding: 20px 24px;
    margin-top: 28px;
}
.ex353-qr .ex353-h2 { color: #C9A84C; border-color: #C9A84C; }
.ex353-qr .ex353-kv-key { color: #C9A84C; }
.ex353-qr .ex353-kv-val { color: #cccccc; }
.ex353-qr .ex353-kv-val a { color: #ffffff; }

/* ── Footer ── */
.ex353-footer {
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: #888888;
    line-height: 1.6;
}
.ex353-footer strong { color: #C9A84C; }
</style>
<?php
}

/* ── Shortcode renderer ───────────────────────────────────────────────────── */
function ex353_render_guide(): string {
    if ( ! ex353_is_member() ) {
        $login_url = wp_login_url( get_permalink() );
        $join_url  = function_exists( 'pmpro_url' )
            ? pmpro_url( 'checkout', '?level=1' )
            : '/membership-options/';
        ob_start(); ?>
<div class="ex353-gate">
    <h2>Members Only</h2>
    <p>The Excreet Member Guide is available exclusively to active Starter and Premium members.</p>
    <a class="btn" href="<?php echo esc_url( $join_url ); ?>">Become a Member</a>
    <a class="btn btn-outline" href="<?php echo esc_url( $login_url ); ?>">Log In</a>
</div>
        <?php return ob_get_clean();
    }

    ob_start(); ?>
<div class="ex353-guide">

    <!-- Cover -->
    <div class="ex353-cover">
        <div class="ex353-cover-wordmark">EXCREET</div>
        <div class="ex353-cover-tagline">A Pre-Clinical Warning System</div>
        <div class="ex353-cover-rule"></div>
        <div class="ex353-cover-title">Member Guide</div>
        <div class="ex353-cover-sub">Everything you need to get the most from your membership.</div>
    </div>

    <!-- Welcome -->
    <p class="ex353-body">
        Excreet is a pre-clinical health intelligence platform. Every morning, your body sends a full
        report through your urine, saliva, and bowel — color, pH, consistency. These are signals that
        can show up weeks before a doctor would ever catch them.
    </p>
    <p class="ex353-body">
        Excreet reads those signals, scores them, and tells you in plain language what your body is
        navigating — inflammation, gut dysfunction, cellular stress, hydration — and what to do about it.
        Under five minutes a day.
    </p>

    <hr class="ex353-rule">

    <!-- What you need -->
    <div class="ex353-h2">What You Need to Get Started</div>

    <div class="ex353-h3">1. pH Test Strips</div>
    <p class="ex353-body">
        You need wide-range pH test strips that measure both urine and saliva (pH 4.5–9.0 range).
        Recommended options on Amazon:
    </p>
    <ul class="ex353-list">
        <li><strong>Health Logics Urine and Saliva pH Test Strips</strong> — accurate, easy to read</li>
        <li><strong>pHion Balance Diagnostic pH Test Strips</strong> — color chart included</li>
        <li>Any wide-range 5-in-1 or 10-in-1 urinalysis strip that includes pH</li>
    </ul>
    <div class="ex353-tip">&#9733; Store strips in a cool, dry place. Humidity ruins them. Keep the cap tightly closed.</div>

    <div class="ex353-h3">2. Your Phone Camera</div>
    <p class="ex353-body">
        No special equipment needed. Take photos in natural light when possible — bathroom light is fine,
        but avoid dim yellow lighting which skews color readings.
    </p>

    <div class="ex353-h3">3. Your Excreet Account</div>
    <p class="ex353-body">
        Log in at excreet.com before you start. Your check-in, score, and history are all stored to
        your account. If you are not logged in, your results will not be saved.
    </p>

    <hr class="ex353-rule">

    <!-- Morning routine -->
    <div class="ex353-h2">Your Morning Routine — Step by Step</div>
    <p class="ex353-body">
        Do this first thing in the morning, before eating or drinking anything. Your first-morning
        samples give the most accurate reading.
    </p>

    <div class="ex353-steps">
        <div class="ex353-step">
            <div class="ex353-step-num">1</div>
            <div class="ex353-step-body">
                <div class="ex353-step-title">Dip the pH strip — urine</div>
                <div class="ex353-step-desc">Hold the strip in your urine stream for 3–5 seconds, or dip it in a small cup. Shake off excess. Wait 15 seconds, then compare the color to the chart on the packaging. Note the pH number.</div>
            </div>
        </div>
        <div class="ex353-step">
            <div class="ex353-step-num">2</div>
            <div class="ex353-step-body">
                <div class="ex353-step-title">Dip the pH strip — saliva (optional but recommended)</div>
                <div class="ex353-step-desc">Spit onto a spoon or small dish. Dip a fresh strip in your saliva for 3 seconds. Compare to the chart. Saliva pH gives Excreet a second reference point for your body's acid-alkaline balance.</div>
            </div>
        </div>
        <div class="ex353-step">
            <div class="ex353-step-num">3</div>
            <div class="ex353-step-body">
                <div class="ex353-step-title">Note your urine color</div>
                <div class="ex353-step-desc">Look at the color of your urine. You do not need to photograph the toilet — just note the color: pale yellow, bright yellow, dark amber, orange, or clear. You will select this in the check-in form.</div>
            </div>
        </div>
        <div class="ex353-step">
            <div class="ex353-step-num">4</div>
            <div class="ex353-step-body">
                <div class="ex353-step-title">Note your bowel (if applicable)</div>
                <div class="ex353-step-desc">If you had a bowel movement this morning, note the consistency: formed, loose, hard pellets, watery, or absent. This is one of the most informative signals Excreet reads.</div>
            </div>
        </div>
        <div class="ex353-step">
            <div class="ex353-step-num">5</div>
            <div class="ex353-step-body">
                <div class="ex353-step-title">Photograph your pH strip</div>
                <div class="ex353-step-desc">Take a clear, well-lit photo of your pH strip next to the color chart on its packaging. This is the photo you upload. Blurry or dark photos reduce accuracy.</div>
            </div>
        </div>
        <div class="ex353-step">
            <div class="ex353-step-num">6</div>
            <div class="ex353-step-body">
                <div class="ex353-step-title">Open Excreet and submit</div>
                <div class="ex353-step-desc">Go to excreet.com and navigate to the <a href="/healing-command-center/">Healing Command Center</a>. Fill in the short questionnaire, select your colors, upload your strip photo, and submit. The AI takes it from there.</div>
            </div>
        </div>
        <div class="ex353-step">
            <div class="ex353-step-num">7</div>
            <div class="ex353-step-body">
                <div class="ex353-step-title">Read your result</div>
                <div class="ex353-step-desc">Within 1–2 minutes, your Vitality Score and body reading appear. Review your score, read your pattern summary, and check the recommended actions.</div>
            </div>
        </div>
    </div>

    <hr class="ex353-rule">

    <!-- Vitality Score -->
    <div class="ex353-h2">Understanding Your Vitality Score</div>
    <p class="ex353-body">
        Your Vitality Score is a number from 0 to 100. It reflects how well your body's systems appear
        to be operating based on the signals you submitted. 100 means full alignment. 0 means acute
        distress. Most healthy adults score between 55 and 80 on a typical morning.
    </p>

    <div class="ex353-tiers">
        <div class="ex353-tier">
            <div class="ex353-tier-label">Nudge &nbsp;(Score ~60–80)</div>
            <div class="ex353-tier-desc">A simple, self-resolvable signal. You may be mildly dehydrated, had a rough night of sleep, or skipped a meal. The recommended actions are straightforward: drink water, rest, adjust a habit.</div>
        </div>
        <div class="ex353-tier">
            <div class="ex353-tier-label">Check-In &nbsp;(Score ~45–65)</div>
            <div class="ex353-tier-desc">A persistent mild signal or early pattern. Not urgent, but your body is asking for more attention. A Ministry of Healing session is recommended to explore what's building.</div>
        </div>
        <div class="ex353-tier">
            <div class="ex353-tier-label">Protocol &nbsp;(Score ~25–50)</div>
            <div class="ex353-tier-desc">A systemic pattern or moderate imbalance. A full Ministry of Healing protocol is recommended. You may also see doctor-visit guidance with specific questions and lab tests to request.</div>
        </div>
        <div class="ex353-tier">
            <div class="ex353-tier-label">Alarm &nbsp;(Score ~0–35)</div>
            <div class="ex353-tier-desc">Your signal pattern warrants both medical navigation and healing support. A "Prepare Doctor Visit Summary" button will appear — a formatted, printable report to bring to your provider.</div>
        </div>
    </div>

    <div class="ex353-warn">Important: Excreet is not a medical diagnosis tool. Your score is an educational signal, not a clinical finding. Always consult a qualified health professional for medical decisions.</div>

    <hr class="ex353-rule">

    <!-- Doctor Visit Summary -->
    <div class="ex353-h2">Doctor Visit Summary</div>
    <p class="ex353-body">
        If your result is in the Protocol or Alarm tier, a purple <strong>Prepare Doctor Visit Summary</strong>
        button appears on your results page. Click it to generate a formatted, printable report that includes:
    </p>
    <ul class="ex353-list">
        <li>Your Vitality Score and pattern reading in plain language</li>
        <li>Questions to bring to your provider — by name</li>
        <li>Specific lab tests to request (e.g. "Free T3/T4 thyroid panel")</li>
        <li>Red flags — specific signs that mean seek urgent care now</li>
    </ul>
    <p class="ex353-body">Use the Print / Save as PDF button to keep a copy or email it to your provider before your appointment.</p>

    <hr class="ex353-rule">

    <!-- Ministry of Healing -->
    <div class="ex353-h2">Ministry of Healing</div>
    <p class="ex353-body">
        The Ministry of Healing is your private AI health intelligence companion inside Excreet. It
        knows your body check history, your clinical pattern, and your health context. You can have a
        real conversation with it — ask questions, dig deeper into your results, or get guided protocols.
    </p>
    <div class="ex353-kv"><span class="ex353-kv-key">Starter membership:</span><span class="ex353-kv-val">10 sessions per month</span></div>
    <div class="ex353-kv"><span class="ex353-kv-key">Premium membership:</span><span class="ex353-kv-val">20 sessions per month</span></div>
    <br>
    <ul class="ex353-list">
        <li>Sessions reset on the 1st of each month.</li>
        <li>Each session is a full conversation — not just one message.</li>
        <li>To access: go to the <a href="/healing-command-center/">Healing Command Center</a> and open the Ministry of Healing section.</li>
    </ul>

    <hr class="ex353-rule">

    <!-- Affiliate Program -->
    <div class="ex353-h2">Your Affiliate Program</div>
    <p class="ex353-body">
        Every Excreet member — Starter and Premium alike — is automatically enrolled as an affiliate.
        You do not need to apply. You have a unique referral link in your account.
    </p>

    <div class="ex353-h3">What you earn</div>
    <table class="ex353-table">
        <thead>
            <tr>
                <th>Your Tier</th>
                <th>They Join</th>
                <th>You Earn</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Starter ($15/mo)</td><td>Starter ($15/mo)</td><td class="earn">$5/mo</td></tr>
            <tr><td>Starter ($15/mo)</td><td>Premium ($25/mo)</td><td class="earn">$5/mo</td></tr>
            <tr><td>Premium ($25/mo)</td><td>Starter ($15/mo)</td><td class="earn">$5/mo</td></tr>
            <tr><td>Premium ($25/mo)</td><td>Premium ($25/mo)</td><td class="earn">$10/mo</td></tr>
        </tbody>
    </table>

    <div class="ex353-h3">Payout rules</div>
    <ul class="ex353-list">
        <li>Minimum $50 accumulated before payout.</li>
        <li>Payouts issued every 2 weeks.</li>
        <li>Your membership must remain active for earnings to count.</li>
        <li>Store purchases and ancillary services do not generate commissions.</li>
        <li>If your referred member cancels, earnings for that member stop immediately.</li>
    </ul>
    <div class="ex353-tip">&#9733; Your referral link is in your <a href="/affiliate-area/">Affiliate Area</a> — share it via social media, email, or text.</div>

    <hr class="ex353-rule">

    <!-- SMS + Shipping -->
    <div class="ex353-h2">SMS Notifications &amp; Shipping</div>
    <ul class="ex353-list">
        <li><strong>SMS morning reminders</strong> are available for US phone numbers only. Add your US mobile number in your account settings.</li>
        <li><strong>Excreet Signature Formula</strong> (Excreet Store) ships within the United States only. International availability will be announced when ready.</li>
    </ul>

    <hr class="ex353-rule">

    <!-- Cancellation -->
    <div class="ex353-h2">How to Cancel Your Membership</div>
    <p class="ex353-body">You may cancel at any time. Your access continues through the end of the billing period you have already paid for.</p>
    <div class="ex353-steps">
        <div class="ex353-step">
            <div class="ex353-step-num">1</div>
            <div class="ex353-step-body"><div class="ex353-step-desc">Log in and go to <a href="/membership-account/">/membership-account/</a></div></div>
        </div>
        <div class="ex353-step">
            <div class="ex353-step-num">2</div>
            <div class="ex353-step-body"><div class="ex353-step-desc">Under Membership, click <strong>Cancel</strong> next to your active plan.</div></div>
        </div>
        <div class="ex353-step">
            <div class="ex353-step-num">3</div>
            <div class="ex353-step-body"><div class="ex353-step-desc">Confirm. You will receive an email confirmation.</div></div>
        </div>
    </div>
    <p class="ex353-body">Affiliate earnings above $50 at time of cancellation will be paid on the next scheduled payout date. Need help? Email <a href="mailto:support@excreet.com">support@excreet.com</a> — response within 1 business day.</p>

    <!-- Quick Reference -->
    <div class="ex353-qr">
        <div class="ex353-h2">Quick Reference</div>
        <div class="ex353-kv"><span class="ex353-kv-key">Daily check-in:</span><span class="ex353-kv-val"><a href="/healing-command-center/">Healing Command Center</a></span></div>
        <div class="ex353-kv"><span class="ex353-kv-key">Ministry of Healing:</span><span class="ex353-kv-val"><a href="/healing-command-center/">HCC → Ministry section</a></span></div>
        <div class="ex353-kv"><span class="ex353-kv-key">Your referral link:</span><span class="ex353-kv-val"><a href="/affiliate-area/">/affiliate-area/</a></span></div>
        <div class="ex353-kv"><span class="ex353-kv-key">Account &amp; billing:</span><span class="ex353-kv-val"><a href="/membership-account/">/membership-account/</a></span></div>
        <div class="ex353-kv"><span class="ex353-kv-key">Store:</span><span class="ex353-kv-val"><a href="/shop/">/shop/</a></span></div>
        <div class="ex353-kv"><span class="ex353-kv-key">Support:</span><span class="ex353-kv-val"><a href="mailto:support@excreet.com">support@excreet.com</a></span></div>
    </div>

    <!-- Footer -->
    <div class="ex353-footer">
        <strong>EXCREET</strong> &nbsp;&middot;&nbsp; Your bathroom is your laboratory.<br>
        This guide is for educational purposes. Excreet is not a medical device and does not provide medical advice.
    </div>

</div>
<?php
    return ob_get_clean();
}

/* ── Inject "Member Guide" link into Member Dashboard ────────────────────── */
function ex353_inject_dashboard_link(): void {
    // Target the member dashboard / welcome-member page
    if ( ! is_page( [ 'member-dashboard', 'welcome-member' ] ) ) return;
    if ( ! ex353_is_member() ) return;

    $guide_url = home_url( '/' . EX353_PAGE_SLUG . '/' );
    ?>
<script>
(function() {
    'use strict';
    var link = document.querySelector('.ex353-dashboard-guide-link');
    if ( link ) return; // already injected

    var el = document.createElement('div');
    el.className = 'ex353-dashboard-guide-link';
    el.style.cssText = 'text-align:center;margin:24px auto 0;max-width:820px;padding:0 16px;';
    el.innerHTML = '<a href="<?php echo esc_url( $guide_url ); ?>" style="display:inline-block;background:linear-gradient(135deg,#6B21A8,#3D1060);color:#C9A84C;font-weight:700;font-size:14px;padding:11px 28px;border-radius:8px;text-decoration:none;letter-spacing:0.5px;">&#128218;&nbsp; View Your Member Guide</a>';

    var content = document.querySelector('.entry-content, .elementor-widget-container, main, #content, .site-main');
    if ( content ) {
        content.appendChild( el );
    } else {
        document.body.appendChild( el );
    }
})();
</script>
<?php
}
