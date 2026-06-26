<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.3.2
 * Description: Member Onboarding Flow — guided first-experience after signup.
 *
 *   A — Post-checkout redirect (pmpro_confirmation_url)
 *       First-time members go to /getting-started/ instead of the default
 *       PMPro confirmation page. Set via a 7-day transient; cleared on view.
 *       Upgrades and renewals are NOT redirected.
 *
 *   B — /getting-started/ page auto-creation on first init.
 *
 *   C — [excreet_onboarding] shortcode
 *       Full-screen branded welcome:
 *         · Personalised greeting + tier confirmation pill
 *         · Referral code with one-click copy + email share link
 *         · Three next-step action cards (Body Check · Ministry · Dashboard)
 *         · Session cap reminder with affiliate area link
 *       Botanical Healing palette; monthly healer-bg background.
 *
 * Version: 3.3.2
 * Depends on: excreet-hermes-client.php  (EXCREET_HERMES_API_KEY)
 *             excreet-hermes-patch-291.php (excreet_291_is_member)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX332_PAGE_SLUG',  'getting-started' );
define( 'EX332_PAGE_TITLE', 'Getting Started' );

/* ── B — Auto-create /getting-started/ WP page ─────────────────────────────── */
add_action( 'init', 'excreet_332_ensure_page', 20 );
function excreet_332_ensure_page(): void {
    if ( get_option( '_excreet_332_page_id' ) ) {
        return;
    }
    $existing = get_page_by_path( EX332_PAGE_SLUG );
    if ( $existing ) {
        update_option( '_excreet_332_page_id', $existing->ID );
        return;
    }
    $id = wp_insert_post( [
        'post_title'   => EX332_PAGE_TITLE,
        'post_name'    => EX332_PAGE_SLUG,
        'post_content' => '[excreet_onboarding]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'meta_input'   => [ '_wp_page_template' => 'elementor_header_footer' ],
    ] );
    if ( $id && ! is_wp_error( $id ) ) {
        update_option( '_excreet_332_page_id', $id );
    }
}

/* ── A — Redirect new members to onboarding ────────────────────────────────── */
add_filter( 'pmpro_confirmation_url', 'excreet_332_confirmation_url', 10, 2 );
function excreet_332_confirmation_url( string $url, $order ): string {
    if ( ! is_user_logged_in() ) {
        return $url;
    }
    $uid = get_current_user_id();

    // Only redirect on very first order
    $orders = function_exists( 'pmpro_getMemberOrders' )
        ? pmpro_getMemberOrders( $uid, 'success' )
        : [];
    if ( ! is_array( $orders ) || count( $orders ) > 1 ) {
        return $url;
    }

    $page = get_page_by_path( EX332_PAGE_SLUG );
    if ( ! $page ) {
        return $url;
    }

    set_transient( 'ex332_new_member_' . $uid, 1, 7 * DAY_IN_SECONDS );
    return get_permalink( $page->ID );
}

/* ── Background on /getting-started/ ───────────────────────────────────────── */
add_action( 'wp_head', 'excreet_332_page_head', 99 );
function excreet_332_page_head(): void {
    if ( ! is_page( EX332_PAGE_SLUG ) ) {
        return;
    }
    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url = esc_url( 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg' );
    ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet">
<style id="ex332-page">
html, body {
    background:
        linear-gradient(160deg,rgba(13,1,32,.58) 0%,rgba(26,5,53,.22) 35%,rgba(26,5,53,.18) 65%,rgba(13,1,32,.55) 100%),
        url("<?php echo $bg_url; ?>") center/cover no-repeat fixed #0c0115 !important;
    font-family:"Poppins",sans-serif !important;
}
body #page, body .site-content, body #content, body #main,
body .site-main, body .elementor-section, body .elementor-container,
body .e-con, body .e-con-inner, body article.page {
    background: transparent !important;
}
.site-header, .site-footer,
.elementor-location-header, .elementor-location-footer {
    background: rgba(8,1,16,.90) !important;
}
.entry-content, .post-content, .page-content {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
}
h1.entry-title { display:none; }
</style>
    <?php
}

/* ── C — [excreet_onboarding] shortcode ────────────────────────────────────── */
add_shortcode( 'excreet_onboarding', 'excreet_332_shortcode' );
function excreet_332_shortcode(): string {
    if ( ! is_user_logged_in() ) {
        return '<p style="text-align:center;color:#888;padding:3rem 1rem;">
            Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your welcome.
        </p>';
    }

    $uid   = get_current_user_id();
    $user  = wp_get_current_user();
    $first = ! empty( $user->first_name )
        ? esc_html( $user->first_name )
        : esc_html( $user->display_name );

    // Tier detection
    $is_premium  = function_exists( 'pmpro_hasMembershipLevel' ) && pmpro_hasMembershipLevel( 2, $uid );
    $tier_label  = $is_premium ? 'Premium' : 'Starter';
    $tier_price  = $is_premium ? '$25/mo'  : '$15/mo';
    $tier_accent = $is_premium ? '#a78bfa' : '#C9A84C';
    $tier_bg     = $is_premium ? 'rgba(107,47,160,.35)' : 'rgba(55,35,10,.40)';
    $session_cap = $is_premium ? 20 : 10;
    $earn_rate   = $is_premium ? '$10' : '$5';

    $code = (string) $uid;

    // Clear new-member flag now that they've seen this page
    delete_transient( 'ex332_new_member_' . $uid );

    ob_start();
    ?>
<div class="ex332-wrap" style="max-width:740px;margin:0 auto;padding:44px 22px 80px;font-family:'Poppins',sans-serif;">

    <!-- Gold accent rule -->
    <div style="width:56px;height:2px;background:#C9A84C;border-radius:2px;margin:0 auto 34px;"></div>

    <!-- Heading -->
    <div style="text-align:center;margin-bottom:38px;">
        <p style="font-size:.72rem;font-weight:700;letter-spacing:.38em;text-transform:uppercase;color:rgba(201,168,76,.75);margin:0 0 14px;">Welcome to Excreet</p>
        <h1 style="font-family:'Playfair Display',Georgia,serif;font-size:clamp(1.8rem,5vw,2.6rem);color:#fff;font-weight:700;line-height:1.15;margin:0 0 16px;">You're in, <?php echo $first; ?>.</h1>
        <p style="font-size:.95rem;color:rgba(255,255,255,.5);font-weight:300;line-height:1.75;max-width:440px;margin:0 auto;">Your body has been speaking. Now you have the intelligence to hear it.</p>
    </div>

    <!-- Tier pill -->
    <div style="text-align:center;margin-bottom:36px;">
        <span style="display:inline-block;background:<?php echo esc_attr( $tier_bg ); ?>;border:1px solid <?php echo esc_attr( $tier_accent ); ?>;border-radius:100px;padding:8px 24px;font-size:.8rem;font-weight:600;color:<?php echo esc_attr( $tier_accent ); ?>;letter-spacing:.09em;">
            <?php echo esc_html( $tier_label ); ?> Member &nbsp;·&nbsp; <?php echo esc_html( $tier_price ); ?>
        </span>
    </div>

    <!-- Referral code card -->
    <div style="background:rgba(12,2,26,.82);border:1px solid rgba(201,168,76,.32);border-radius:18px;padding:28px 28px 22px;margin-bottom:30px;backdrop-filter:blur(12px);">
        <p style="font-size:.7rem;font-weight:700;letter-spacing:.3em;text-transform:uppercase;color:rgba(201,168,76,.7);text-align:center;margin:0 0 10px;">Your Affiliate Referral Code</p>
        <div id="ex332-code" style="font-family:'Playfair Display',Georgia,serif;font-size:clamp(2.2rem,8vw,3.2rem);font-weight:700;color:#C9A84C;letter-spacing:.14em;line-height:1;text-align:center;margin-bottom:12px;"><?php echo esc_html( $code ); ?></div>
        <p style="font-size:.82rem;color:rgba(255,255,255,.42);text-align:center;margin:0 0 18px;line-height:1.65;">
            Every <?php echo esc_html( $tier_label ); ?> member you refer earns you
            <strong style="color:#C9A84C;"><?php echo esc_html( $earn_rate ); ?> per month</strong>
            while both memberships are active. Credit clears after 30 days.
        </p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <button id="ex332-copy-btn"
                onclick="excreet332Copy()"
                style="background:rgba(201,168,76,.14);border:1px solid rgba(201,168,76,.45);border-radius:8px;padding:9px 22px;color:#C9A84C;font-family:'Poppins',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;letter-spacing:.04em;transition:background .2s;">
                Copy Code
            </button>
            <a href="mailto:?subject=I joined Excreet — and you should too&body=Hey — I just joined Excreet, a pre-clinical health intelligence platform that monitors your body before symptoms appear. Use my referral code <?php echo esc_attr( $code ); ?> at https://excreet.com/membership-levels/ — I earn <?php echo esc_attr( $earn_rate ); ?>/month for every member I refer who stays active. Worth checking out."
               style="background:rgba(107,47,160,.28);border:1px solid rgba(107,47,160,.48);border-radius:8px;padding:9px 22px;color:#d4bbff;font-family:'Poppins',sans-serif;font-size:.82rem;font-weight:600;text-decoration:none;letter-spacing:.04em;">
                Share via Email →
            </a>
        </div>
    </div>

    <!-- Next steps label -->
    <p style="font-size:.7rem;font-weight:700;letter-spacing:.3em;text-transform:uppercase;color:rgba(201,168,76,.58);text-align:center;margin:0 0 16px;">Your First Three Moves</p>

    <!-- Action cards -->
    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:38px;">

        <a href="/healing-command-center/" style="text-decoration:none;">
            <div class="ex332-card" style="background:rgba(12,2,26,.80);border:1px solid rgba(201,168,76,.2);border-radius:16px;padding:20px 22px;backdrop-filter:blur(8px);display:flex;align-items:center;gap:18px;">
                <div style="flex-shrink:0;width:46px;height:46px;background:rgba(201,168,76,.12);border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;">📊</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#C9A84C;margin-bottom:3px;">Step 1 — Body Check</div>
                    <div style="font-size:.95rem;font-weight:600;color:#fff;margin-bottom:3px;">Take your first Body Snapshot</div>
                    <div style="font-size:.8rem;color:rgba(255,255,255,.4);line-height:1.55;">Establishes your baseline Body Score. Takes 3 minutes.</div>
                </div>
                <div style="flex-shrink:0;color:rgba(201,168,76,.5);font-size:1.3rem;">›</div>
            </div>
        </a>

        <a href="/ministry-of-healing/" style="text-decoration:none;">
            <div class="ex332-card" style="background:rgba(12,2,26,.80);border:1px solid rgba(107,47,160,.32);border-radius:16px;padding:20px 22px;backdrop-filter:blur(8px);display:flex;align-items:center;gap:18px;">
                <div style="flex-shrink:0;width:46px;height:46px;background:rgba(107,47,160,.18);border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;">🌿</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#a78bfa;margin-bottom:3px;">Step 2 — Ministry of Healing</div>
                    <div style="font-size:.95rem;font-weight:600;color:#fff;margin-bottom:3px;">Meet your AI health companion</div>
                    <div style="font-size:.8rem;color:rgba(255,255,255,.4);line-height:1.55;">Ask anything. It already knows your profile and speaks plainly — no clinical jargon.</div>
                </div>
                <div style="flex-shrink:0;color:rgba(167,139,250,.5);font-size:1.3rem;">›</div>
            </div>
        </a>

        <a href="/member-dashboard/" style="text-decoration:none;">
            <div class="ex332-card" style="background:rgba(12,2,26,.80);border:1px solid rgba(61,16,96,.45);border-radius:16px;padding:20px 22px;backdrop-filter:blur(8px);display:flex;align-items:center;gap:18px;">
                <div style="flex-shrink:0;width:46px;height:46px;background:rgba(61,16,96,.28);border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;">⚡</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:rgba(201,168,76,.58);margin-bottom:3px;">Step 3 — Dashboard</div>
                    <div style="font-size:.95rem;font-weight:600;color:#fff;margin-bottom:3px;">Your Healing Command Center</div>
                    <div style="font-size:.8rem;color:rgba(255,255,255,.4);line-height:1.55;">Body Score trends, Ministry history, affiliate earnings — all in one place.</div>
                </div>
                <div style="flex-shrink:0;color:rgba(201,168,76,.45);font-size:1.3rem;">›</div>
            </div>
        </a>

    </div>

    <!-- Session cap notice -->
    <div style="background:rgba(107,47,160,.10);border:1px solid rgba(107,47,160,.22);border-radius:12px;padding:14px 20px;text-align:center;margin-bottom:32px;">
        <span style="font-size:.82rem;color:rgba(240,232,255,.55);line-height:1.7;">
            Your <strong style="color:rgba(240,232,255,.8);"><?php echo esc_html( $tier_label ); ?></strong> plan includes
            <strong style="color:#a78bfa;"><?php echo (int) $session_cap; ?> Ministry sessions</strong> per month — resets on the 1st.
            &nbsp;<a href="/affiliate-area/" style="color:#C9A84C;text-decoration:none;font-weight:500;">View your affiliate dashboard →</a>
        </span>
    </div>

    <!-- Wordmark footer -->
    <div style="text-align:center;border-top:1px solid rgba(201,168,76,.12);padding-top:26px;">
        <div style="font-family:'Playfair Display',Georgia,serif;font-size:1.15rem;font-weight:700;color:rgba(255,255,255,.22);letter-spacing:.26em;">EXCREET</div>
        <div style="font-size:.62rem;letter-spacing:.44em;text-transform:uppercase;color:rgba(255,255,255,.12);margin-top:4px;">CLEANS &nbsp; COMPLETE</div>
    </div>

</div>

<style>
.ex332-card { transition: border-color .2s, background .2s; }
.ex332-card:hover { background: rgba(20,4,40,.90) !important; }
@media (max-width: 480px) {
    .ex332-card { flex-direction: column; text-align: center; }
    .ex332-card > div:last-child { display: none; }
}
</style>
<script>
function excreet332Copy() {
    var code = '<?php echo esc_js( $code ); ?>';
    var btn  = document.getElementById('ex332-copy-btn');
    (navigator.clipboard ? navigator.clipboard.writeText(code) : Promise.reject())
        .then(function () {
            btn.textContent = '✓ Copied!';
            btn.style.background = 'rgba(201,168,76,.28)';
        })
        .catch(function () {
            var el = document.createElement('textarea');
            el.value = code;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            btn.textContent = '✓ Copied!';
            btn.style.background = 'rgba(201,168,76,.28)';
        })
        .finally(function () {
            setTimeout(function () {
                btn.textContent = 'Copy Code';
                btn.style.background = 'rgba(201,168,76,.14)';
            }, 2200);
        });
}
</script>
    <?php
    return ob_get_clean();
}
