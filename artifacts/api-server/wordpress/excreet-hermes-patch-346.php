<?php
/**
 * Plugin Name: Excreet Patch 346 — Signature Formula Direct Checkout
 * Description: Replaces the WooCommerce product page for the Excreet Signature
 *              Formula (ID 890) with a branded full-page layout and a direct
 *              Stripe "Buy Now" button via the Hermes /api/hermes/formula/checkout
 *              endpoint. No WooCommerce cart or payment plugin required.
 *
 *   A — Product page override (template_redirect on is_product + ID 890)
 *   B — REST endpoint: POST /wp-json/excreet/v1/formula-order
 *       Receives webhook callback from Hermes after successful purchase,
 *       sends admin email notification to daytoheal@yahoo.com.
 *
 * Version: 3.4.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EX346_FORMULA_ID',      890 );
define( 'EX346_HERMES_BASE',     'https://core-status-check.replit.app/api/hermes' );
define( 'EX346_SUCCESS_PAGE',    '/product/excreet-signature-formula/' );
define( 'EX346_ADMIN_EMAIL',     'daytoheal@yahoo.com' );

/* ═══════════════════════════════════════════════════════════════════
   A — FORMULA PRODUCT PAGE OVERRIDE
   ═══════════════════════════════════════════════════════════════════ */

add_action( 'template_redirect', 'excreet_346_formula_page', 1 );

function excreet_346_formula_page(): void {
    if ( ! function_exists( 'is_product' ) ) return;
    if ( ! is_product() ) return;
    $queried = get_queried_object();
    if ( ! $queried || (int) $queried->ID !== EX346_FORMULA_ID ) return;

    /* Gather member context */
    $user        = wp_get_current_user();
    $is_member   = is_user_logged_in() && function_exists( 'pmpro_hasMembershipLevel' )
                   ? pmpro_hasMembershipLevel( null, $user->ID )
                   : is_user_logged_in();
    $member_email = $user->user_email ?? '';
    $member_id    = (string) $user->ID;

    $month    = date( 'm' );
    $bg_url   = 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg';
    $img_url  = 'https://excreet.com/wp-content/uploads/2026/05/excreet-formula-bottle-237x300.png';
    $logo_url = 'https://excreet.com/wp-content/uploads/excreet-logo-v3.png';

    /* Success / cancel message from query string */
    $notice = '';
    if ( isset( $_GET['formula_purchased'] ) ) {
        $notice = 'success';
    } elseif ( isset( $_GET['formula_cancelled'] ) ) {
        $notice = 'cancelled';
    }

    status_header( 200 );
    header( 'Content-Type: text/html; charset=utf-8' );
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Excreet Signature Formula — Cellular Health Supplement</title>
<?php wp_head(); ?>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    background: url('<?php echo esc_url( $bg_url ); ?>') center center / cover no-repeat fixed;
    min-height: 100vh;
    color: #1a1a2e;
}

/* Nav */
.ex346-nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(86, 7, 94, 0.96);
    backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 32px; gap: 16px;
}
.ex346-nav-brand { font-size: 20px; font-weight: 700; color: #F5D97A; text-decoration: none; }
.ex346-nav-links { display: flex; gap: 20px; }
.ex346-nav-links a { color: #fff; text-decoration: none; font-size: 13px; font-weight: 500; opacity: .88; }
.ex346-nav-links a:hover { opacity: 1; }

/* Page wrapper */
.ex346-page {
    max-width: 1100px;
    margin: 0 auto;
    padding: 48px 24px 80px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
    align-items: start;
}

/* Left — image panel */
.ex346-image-panel {
    background: #fff;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 8px 48px rgba(0,0,0,0.18);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    position: sticky;
    top: 80px;
}
.ex346-image-panel img {
    max-height: 340px;
    max-width: 100%;
    object-fit: contain;
}
.ex346-badge-own {
    background: #C8930A;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: 5px 16px;
    border-radius: 30px;
}
.ex346-spec-row {
    display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;
}
.ex346-spec-pill {
    background: #f0ebf9;
    color: #56075E;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
    white-space: nowrap;
}

/* Right — info + CTA panel */
.ex346-info-panel {
    background: #fff;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 8px 48px rgba(0,0,0,0.18);
}
.ex346-tag {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #56075E;
    margin-bottom: 10px;
}
.ex346-title {
    font-size: clamp(22px, 2.4vw, 32px);
    font-weight: 700;
    color: #1a1a2e;
    line-height: 1.25;
    margin-bottom: 8px;
}
.ex346-subtitle {
    font-size: 14px;
    color: #666;
    margin-bottom: 24px;
    line-height: 1.6;
}
.ex346-price {
    font-size: 42px;
    font-weight: 700;
    color: #C8930A;
    margin-bottom: 6px;
    letter-spacing: -.02em;
}
.ex346-price-note {
    font-size: 12px;
    color: #999;
    margin-bottom: 28px;
}

/* Benefits list */
.ex346-benefits {
    list-style: none;
    margin-bottom: 28px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.ex346-benefits li {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    font-size: 13.5px;
    color: #333;
    line-height: 1.5;
}
.ex346-benefits li::before {
    content: '✓';
    color: #C8930A;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
    margin-top: 1px;
}

/* CTA */
.ex346-btn-buy {
    display: block;
    width: 100%;
    padding: 16px 24px;
    background: #C8930A;
    color: #fff;
    border: none;
    border-radius: 40px;
    font-family: inherit;
    font-size: 17px;
    font-weight: 700;
    cursor: pointer;
    letter-spacing: .03em;
    transition: background .18s, transform .12s;
    text-align: center;
    margin-bottom: 12px;
}
.ex346-btn-buy:hover:not(:disabled) { background: #a97509; transform: translateY(-2px); }
.ex346-btn-buy:disabled { background: #ccc; cursor: not-allowed; }

.ex346-btn-loading { display: none; }
.ex346-loading .ex346-btn-buy-text { display: none; }
.ex346-loading .ex346-btn-loading { display: inline; }

.ex346-members-note {
    text-align: center;
    font-size: 12px;
    color: #888;
    margin-bottom: 24px;
}
.ex346-members-note a { color: #56075E; }

/* Divider */
.ex346-divider { border: none; border-top: 1px solid #f0eaf7; margin: 24px 0; }

/* Ingredients section */
.ex346-section-title {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #56075E;
    margin-bottom: 10px;
}
.ex346-ingredients {
    font-size: 13px;
    color: #555;
    line-height: 1.7;
}

/* Notice banners */
.ex346-notice {
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 500;
}
.ex346-notice-success {
    background: #e8f5e9;
    color: #1b5e20;
    border-left: 4px solid #4caf50;
}
.ex346-notice-cancelled {
    background: #fff8e1;
    color: #6d4c00;
    border-left: 4px solid #ffc107;
}
.ex346-error-msg {
    color: #c0392b;
    font-size: 13px;
    margin-top: 8px;
    display: none;
}

/* Gate banner for non-members */
.ex346-gate-banner {
    background: linear-gradient(135deg, #f5eaff, #ede0ff);
    border: 1.5px solid #c9a6f5;
    border-radius: 14px;
    padding: 20px 24px;
    text-align: center;
    margin-bottom: 20px;
}
.ex346-gate-banner p { font-size: 14px; color: #3d1060; margin-bottom: 12px; }
.ex346-gate-btn {
    display: inline-block;
    padding: 10px 24px;
    background: #56075E;
    color: #fff;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
}

/* Responsive */
@media (max-width: 768px) {
    .ex346-page { grid-template-columns: 1fr; gap: 24px; padding: 24px 16px 60px; }
    .ex346-image-panel { position: static; padding: 28px 20px; }
    .ex346-info-panel { padding: 28px 20px; }
    .ex346-nav { padding: 10px 16px; }
    .ex346-price { font-size: 34px; }
}
</style>
</head>
<body>

<nav class="ex346-nav">
    <a class="ex346-nav-brand" href="/">Excreet</a>
    <div class="ex346-nav-links">
        <a href="/shop/">Excreet Store</a>
        <a href="/welcome-member/">Dashboard</a>
        <a href="/membership-account/">My Account</a>
    </div>
</nav>

<div class="ex346-page">

    <!-- LEFT: Image -->
    <div class="ex346-image-panel">
        <span class="ex346-badge-own">Excreet Signature Formula</span>
        <img src="<?php echo esc_url( $img_url ); ?>"
             alt="Excreet Signature Formula bottle">
        <div class="ex346-spec-row">
            <span class="ex346-spec-pill">30-day supply</span>
            <span class="ex346-spec-pill">32 ounces</span>
            <span class="ex346-spec-pill">SKU: EX-FORMULA-001</span>
        </div>
    </div>

    <!-- RIGHT: Info + CTA -->
    <div class="ex346-info-panel">

        <p class="ex346-tag">Proprietary Formula · Excreet Exclusive</p>
        <h1 class="ex346-title">Excreet Signature Formula</h1>
        <p class="ex346-subtitle">
            Precision cellular health supplement — bioavailable minerals, digestive enzymes, and
            botanicals formulated to support gut motility, reduce systemic inflammation, and restore
            your body's natural signalling rhythms.
        </p>

        <?php if ( $notice === 'success' ) : ?>
        <div class="ex346-notice ex346-notice-success">
            ✓ &nbsp;Order confirmed! Check your email for a receipt from Stripe.
            We'll be in touch about shipping shortly.
        </div>
        <?php elseif ( $notice === 'cancelled' ) : ?>
        <div class="ex346-notice ex346-notice-cancelled">
            Your checkout was cancelled. No charge was made.
        </div>
        <?php endif; ?>

        <div class="ex346-price">$65.00</div>
        <p class="ex346-price-note">Free shipping · US, Canada, UK, Australia</p>

        <ul class="ex346-benefits">
            <li>Bioavailable minerals for rapid cellular uptake</li>
            <li>Broad-spectrum digestive enzymes for complete nutrient absorption</li>
            <li>Anti-inflammatory botanicals — turmeric, ginger, boswellia</li>
            <li>Third-party tested for purity and potency</li>
            <li>No fillers, artificial colours, or proprietary blends hidden behind labels</li>
            <li>Vegan capsules · Non-GMO · Gluten-free</li>
        </ul>

        <?php if ( $is_member ) : ?>
        <button class="ex346-btn-buy" id="ex346-buy-btn"
                data-user-id="<?php echo esc_attr( $member_id ); ?>"
                data-email="<?php echo esc_attr( $member_email ); ?>">
            <span class="ex346-btn-buy-text">Buy Now — $65.00</span>
            <span class="ex346-btn-loading">Redirecting to payment…</span>
        </button>
        <p class="ex346-error-msg" id="ex346-error"></p>
        <p class="ex346-members-note">
            Secure checkout via Stripe &nbsp;·&nbsp;
            Receipt sent to your email &nbsp;·&nbsp;
            <a href="https://stripe.com/legal/consumer" target="_blank" rel="noopener">Stripe terms</a>
        </p>
        <?php else : ?>
        <div class="ex346-gate-banner">
            <p>This product is available to Excreet members only.</p>
            <a class="ex346-gate-btn"
               href="<?php echo esc_url( function_exists('pmpro_url') ? pmpro_url('checkout','?level=1') : '/membership-checkout/?level=1' ); ?>">
               Become a Member to Purchase
            </a>
        </div>
        <?php endif; ?>

        <hr class="ex346-divider">

        <p class="ex346-section-title">Key Ingredients</p>
        <p class="ex346-ingredients">
            Magnesium glycinate, zinc bisglycinate, selenium (selenomethionine), fulvic acid complex,
            amylase, protease, lipase, cellulase, lactase, bromelain, papain,
            turmeric root extract (95% curcuminoids), ginger root extract, boswellia serrata extract,
            ashwagandha root extract (KSM-66), black pepper extract (BioPerine® — enhances absorption).
        </p>

        <hr class="ex346-divider">

        <p class="ex346-section-title">Directions</p>
        <p class="ex346-ingredients">
            Take 2 capsules daily with food. For best results, take with your largest meal of the day.
            Do not exceed recommended dose. Consult your healthcare provider before use if pregnant,
            nursing, or taking prescription medications.
        </p>

    </div>
</div>

<script>
(function () {
    var btn    = document.getElementById('ex346-buy-btn');
    var errEl  = document.getElementById('ex346-error');
    if (!btn) return;

    btn.addEventListener('click', async function () {
        btn.disabled = true;
        btn.classList.add('ex346-loading');
        if (errEl) errEl.style.display = 'none';

        try {
            var resp = await fetch('<?php echo esc_js( EX346_HERMES_BASE ); ?>/formula/checkout', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    wp_user_id:   btn.dataset.userId   || '',
                    member_email: btn.dataset.email    || '',
                    return_url:   window.location.origin + '<?php echo esc_js( EX346_SUCCESS_PAGE ); ?>',
                }),
            });

            var data = await resp.json();

            if (data.checkout_url) {
                window.location.href = data.checkout_url;
            } else {
                throw new Error(data.message || 'Checkout unavailable. Please try again.');
            }
        } catch (err) {
            if (errEl) {
                errEl.textContent = err.message || 'Something went wrong. Please try again.';
                errEl.style.display = 'block';
            }
            btn.disabled = false;
            btn.classList.remove('ex346-loading');
        }
    });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
<?php
    exit;
}

/* ═══════════════════════════════════════════════════════════════════
   B — REST ENDPOINT: receive Hermes formula-order notification
   POST /wp-json/excreet/v1/formula-order
   ═══════════════════════════════════════════════════════════════════ */

add_action( 'rest_api_init', 'excreet_346_register_rest' );

function excreet_346_register_rest(): void {
    register_rest_route( 'excreet/v1', '/formula-order', [
        'methods'             => 'POST',
        'callback'            => 'excreet_346_formula_order_handler',
        'permission_callback' => '__return_true',
    ] );
}

function excreet_346_formula_order_handler( WP_REST_Request $request ): WP_REST_Response {
    $body     = $request->get_body();
    $data     = json_decode( $body, true );
    $hmac_in  = $request->get_header( 'x-excreet-hmac' );

    /* Verify HMAC from Hermes */
    $hermes_key = defined( '_HERMES_API_KEY' ) ? constant( '_HERMES_API_KEY' ) : getenv( 'HERMES_API_KEY' );
    /* Fall back to DB-stored key (set during Hermes config) */
    if ( ! $hermes_key ) {
        $hermes_key = get_option( '_excreet_hermes_api_key', '' );
    }

    if ( $hermes_key ) {
        $expected = hash_hmac( 'sha256', $body, $hermes_key );
        if ( ! hash_equals( $expected, (string) $hmac_in ) ) {
            return new WP_REST_Response( [ 'error' => 'invalid_hmac' ], 403 );
        }
    }

    $session_id     = sanitize_text_field( $data['stripe_session_id'] ?? '' );
    $customer_email = sanitize_email( $data['customer_email'] ?? '' );
    $amount         = sanitize_text_field( $data['amount'] ?? '$65.00' );
    $admin_email    = sanitize_email( $data['admin_email'] ?? EX346_ADMIN_EMAIL );

    $subject = "New Excreet Formula Order — {$amount}";
    $message = "A new Excreet Signature Formula order was completed.\n\n"
             . "Customer email : {$customer_email}\n"
             . "Amount charged : {$amount}\n"
             . "Stripe session : {$session_id}\n\n"
             . "View in Stripe Dashboard:\n"
             . "https://dashboard.stripe.com/payments\n\n"
             . "— Excreet Hermes";

    wp_mail( $admin_email, $subject, $message );

    return new WP_REST_Response( [ 'received' => true ], 200 );
}
