<?php
/**
 * Plugin Name: Excreet Hermes Patch 2.9.2
 * Description: Payment & onboarding flow.
 *              - Replaces bare membership-payment-page with branded checkout
 *              - Injects Excreet CSS over MemberPress checkout form
 *              - Redirects post-payment straight to member-intake-form
 *              - Upgrades welcome-member page with branded onboarding CTA
 * Version:     2.9.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Constants ─────────────────────────────────────────────────────────────────

define( 'EX292_PRODUCT_ID',       171 );
define( 'EX292_INTAKE_PAGE_ID',   21  );  // member-intake-form
define( 'EX292_PAYMENT_PAGE_ID',  630 );
define( 'EX292_WELCOME_PAGE_ID',  366 );
define( 'EX292_PURPLE',      '#6B2FA0' );
define( 'EX292_PURPLE_DARK', '#3D1060' );
define( 'EX292_GOLD',        '#C9A84C' );

/* ════════════════════════════════════════════════════════════════════════════
   HOOKS
   ════════════════════════════════════════════════════════════════════════════ */

add_action( 'init',               'excreet_292_set_thankyou_redirect', 10 );
add_action( 'wp_enqueue_scripts', 'excreet_292_checkout_css',           99 );
add_action( 'wp_enqueue_scripts', 'excreet_292_upgrade_pages',          20 );
add_action( 'mepr-above-checkout-form', 'excreet_292_checkout_header', 10, 1 );
add_shortcode( 'excreet_checkout_page',  'excreet_292_checkout_shortcode' );
add_shortcode( 'excreet_welcome_member', 'excreet_292_welcome_shortcode'  );

/* ════════════════════════════════════════════════════════════════════════════
   BRANDED HEADER — injected via the_content filter (most reliable)
   ════════════════════════════════════════════════════════════════════════════ */

/**
 * Prepends the Excreet branded header to the MemberPress checkout
 * page content. Runs via the_content filter which always fires.
 */
function excreet_292_filter_checkout_content( string $content ): string {
    if ( ! excreet_292_is_mepr_checkout() ) {
        return $content;
    }
    ob_start();
    excreet_292_checkout_header( EX292_PRODUCT_ID );
    $header = (string) ob_get_clean();
    return $header . $content;
}

function excreet_292_checkout_header( $product_id ): void {
    ?>
    <div style="
        font-family: Georgia, 'Times New Roman', serif;
        background: linear-gradient(135deg, <?php echo EX292_PURPLE_DARK; ?> 0%, <?php echo EX292_PURPLE; ?> 100%);
        border-radius: 16px 16px 0 0;
        padding: 28px 32px;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        gap: 16px;
    ">
        <div style="
            background: <?php echo EX292_GOLD; ?>;
            border-radius: 50%;
            width: 54px; height: 54px;
            line-height: 54px;
            text-align: center;
            font-size: 28px; font-weight: 900;
            color: <?php echo EX292_PURPLE_DARK; ?>;
            font-family: serif;
            flex-shrink: 0;
        ">℮</div>
        <div>
            <div style="color: <?php echo EX292_GOLD; ?>; font-size: 10px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; margin-bottom: 4px;">Excreet™ — WHealth Intelligence</div>
            <div style="color: #fff; font-size: 20px; font-weight: 700; line-height: 1.2;">Become an Excreet Member</div>
            <div style="color: rgba(255,255,255,.75); font-size: 13px; margin-top: 3px;">$15 / month · Cancel anytime · Secured by Stripe</div>
        </div>
    </div>
    <div style="
        background: #F7F4FC;
        border-left: 3px solid <?php echo EX292_PURPLE; ?>;
        border-right: 3px solid <?php echo EX292_PURPLE; ?>;
        padding: 14px 32px;
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
        justify-content: center;
        font-family: Georgia, serif;
    ">
        <?php
        $perks = [ 'Clinical Pattern Report', 'Drug Interaction Analysis', 'Red Flag Triage', 'Lab Marker Triggers', 'Excreet Health Library' ];
        foreach ( $perks as $p ) : ?>
        <span style="font-size: 12px; color: <?php echo EX292_PURPLE_DARK; ?>; font-weight: 600;">
            <span style="color: <?php echo EX292_GOLD; ?>; font-weight: 700;">✓</span> <?php echo esc_html( $p ); ?>
        </span>
        <?php endforeach; ?>
    </div>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   JS HEADER INJECTION — reliable fallback via wp_footer
   Prepends branded header to the MemberPress form via vanilla JS.
   Only fires on /register/ checkout pages.
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_292_checkout_js_header(): void {
    if ( ! excreet_292_is_mepr_checkout() ) {
        return;
    }
    $purple      = EX292_PURPLE;
    $purple_dark = EX292_PURPLE_DARK;
    $gold        = EX292_GOLD;
    $perks_json  = json_encode( [
        'Clinical Pattern Report',
        'Drug Interaction Analysis',
        'Red Flag Triage',
        'Lab Marker Triggers',
        'Excreet Health Library',
    ] );
    ?>
    <script>
    (function() {
        var perks = <?php echo $perks_json; ?>;
        var perksHtml = perks.map(function(p){
            return '<span style="font-size:12px;color:<?php echo $purple_dark; ?>;font-weight:600;white-space:nowrap;">'
                 + '<span style="color:<?php echo $gold; ?>;font-weight:700;">&#10003;</span> '+p+'</span>';
        }).join('');

        var header = '<div id="excreet-checkout-hero" style="'
            + 'font-family:Georgia,serif;'
            + 'background:linear-gradient(135deg,<?php echo $purple_dark; ?> 0%,<?php echo $purple; ?> 100%);'
            + 'border-radius:16px 16px 0 0;padding:22px 28px;'
            + 'display:flex;align-items:center;gap:16px;margin-bottom:0;'
            + '">'
            + '<div style="background:<?php echo $gold; ?>;border-radius:50%;width:50px;height:50px;'
            + 'line-height:50px;text-align:center;font-size:26px;font-weight:900;'
            + 'color:<?php echo $purple_dark; ?>;font-family:serif;flex-shrink:0;">\u212E</div>'
            + '<div>'
            + '<div style="color:<?php echo $gold; ?>;font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;margin-bottom:4px;">Excreet\u2122 \u2014 WHealth Intelligence</div>'
            + '<div style="color:#fff;font-size:19px;font-weight:700;line-height:1.2;">Become an Excreet Member</div>'
            + '<div style="color:rgba(255,255,255,.75);font-size:12px;margin-top:3px;">$15\u00a0/\u00a0month \u00b7 Cancel anytime \u00b7 Secured by Stripe</div>'
            + '</div></div>'
            + '<div style="background:#F7F4FC;border-left:3px solid <?php echo $purple; ?>;border-right:3px solid <?php echo $purple; ?>;'
            + 'padding:12px 28px;display:flex;gap:20px;flex-wrap:wrap;justify-content:center;">'
            + perksHtml
            + '</div>';

        function inject() {
            if (document.getElementById('excreet-checkout-hero')) return;
            var form = document.querySelector('form.mepr-signup-form, form#mepr_signup_form');
            if (form && form.parentNode) {
                var wrapper = document.createElement('div');
                wrapper.innerHTML = header;
                form.parentNode.insertBefore(wrapper.firstChild, form);
                // hide the PHP-hook duplicate if it rendered inside the form
                var inside = form.querySelector('[id^="excreet-checkout-hero"]');
                if (inside) inside.style.display = 'none';
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', inject);
        } else {
            inject();
        }
    })();
    </script>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   POST-PAYMENT REDIRECT — wire product 171 thank-you → intake form
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_292_set_thankyou_redirect(): void {
    // Idempotent: only update if not already pointing at the intake form
    $current = (int) get_post_meta( EX292_PRODUCT_ID, '_mepr_product_thank_you_page_id', true );
    if ( $current === EX292_INTAKE_PAGE_ID ) {
        return;
    }
    update_post_meta( EX292_PRODUCT_ID, '_mepr_thank_you_page_enabled',   '1'    );
    update_post_meta( EX292_PRODUCT_ID, '_mepr_thank_you_page_type',      'page' );
    update_post_meta( EX292_PRODUCT_ID, '_mepr_product_thank_you_page_id', (string) EX292_INTAKE_PAGE_ID );
}

/* ════════════════════════════════════════════════════════════════════════════
   UPGRADE PAGE CONTENT (idempotent, runs once per page)
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_292_upgrade_pages(): void {
    if ( ! is_singular( 'page' ) ) {
        return;
    }
    $id = get_the_ID();

    // Payment page → inject branded checkout shortcode if still bare
    if ( $id === EX292_PAYMENT_PAGE_ID ) {
        $content = get_post_field( 'post_content', $id );
        if ( strpos( $content, 'excreet_checkout_page' ) === false ) {
            wp_update_post( [
                'ID'           => $id,
                'post_content' => '[excreet_checkout_page]',
            ] );
        }
    }

    // Welcome page → inject branded shortcode if still bare
    if ( $id === EX292_WELCOME_PAGE_ID ) {
        $content = get_post_field( 'post_content', $id );
        if ( strpos( $content, 'excreet_welcome_member' ) === false ) {
            wp_update_post( [
                'ID'           => $id,
                'post_content' => '[excreet_welcome_member]',
            ] );
        }
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   CHECKOUT PAGE SHORTCODE
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_292_checkout_shortcode(): string {
    // If user is already a member, redirect them to the intake form
    if ( function_exists( 'excreet_291_is_member' ) && excreet_291_is_member() ) {
        wp_safe_redirect( get_permalink( EX292_INTAKE_PAGE_ID ) );
        exit;
    }

    ob_start();
    ?>
    <div class="excreet-checkout-wrap" style="
        font-family: Georgia, 'Times New Roman', serif;
        max-width: 700px;
        margin: 0 auto;
    ">
        <!-- Hero header -->
        <div style="
            background: linear-gradient(135deg, <?php echo EX292_PURPLE_DARK; ?> 0%, <?php echo EX292_PURPLE; ?> 100%);
            border-radius: 20px 20px 0 0;
            padding: 36px 40px 32px;
            text-align: center;
        ">
            <div style="
                display: inline-block;
                background: <?php echo EX292_GOLD; ?>;
                border-radius: 50%;
                width: 64px; height: 64px;
                line-height: 64px;
                font-size: 32px; font-weight: 900;
                color: <?php echo EX292_PURPLE_DARK; ?>;
                font-family: serif;
                margin-bottom: 16px;
            ">℮</div>
            <div style="color: <?php echo EX292_GOLD; ?>; font-size: 11px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; margin-bottom: 8px;">Excreet™ — WHealth Intelligence</div>
            <h2 style="color: #fff; font-size: 26px; font-weight: 700; margin: 0 0 8px; line-height: 1.2;">Become an Excreet Member</h2>
            <p style="color: rgba(255,255,255,.8); font-size: 14px; margin: 0; line-height: 1.7;">Your pharmaceutical pattern, decoded. Your health baseline, built.</p>
        </div>

        <!-- Value strip -->
        <div style="
            background: #F7F4FC;
            border-left: 1px solid #D5C5E8;
            border-right: 1px solid #D5C5E8;
            padding: 24px 40px;
            display: flex;
            justify-content: center;
            gap: 32px;
            flex-wrap: wrap;
        ">
            <?php
            $pillars = [
                [ '℃', 'Clinical Pattern Report' ],
                [ '⊕', 'Drug Interaction Loops' ],
                [ '⚑', 'Red Flag Triage' ],
                [ '⊞', 'Lab Marker Triggers' ],
                [ '◎', 'Excreet Health Library' ],
            ];
            foreach ( $pillars as [ $icon, $label ] ) : ?>
            <div style="text-align: center; min-width: 100px;">
                <div style="font-size: 22px; color: <?php echo EX292_PURPLE; ?>; margin-bottom: 4px;"><?php echo $icon; ?></div>
                <div style="font-size: 11px; color: #334e68; font-weight: 600; line-height: 1.4;"><?php echo esc_html( $label ); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Price badge -->
        <div style="
            background: #fff;
            border-left: 1px solid #D5C5E8;
            border-right: 1px solid #D5C5E8;
            padding: 20px 40px 0;
            text-align: center;
        ">
            <div style="
                display: inline-flex;
                align-items: baseline;
                gap: 6px;
                background: linear-gradient(135deg, <?php echo EX292_PURPLE_DARK; ?>, <?php echo EX292_PURPLE; ?>);
                color: #fff;
                border-radius: 999px;
                padding: 10px 28px;
                font-weight: 700;
                font-size: 15px;
                letter-spacing: .04em;
            ">
                <span style="font-size: 22px;">$15</span>
                <span style="opacity:.8;">/ month &nbsp;·&nbsp; Cancel anytime</span>
            </div>
        </div>

        <!-- MemberPress checkout form -->
        <div class="excreet-mepr-form-wrap" style="
            background: #fff;
            border: 1px solid #D5C5E8;
            border-top: none;
            border-radius: 0 0 20px 20px;
            padding: 32px 40px 40px;
        ">
            <?php echo do_shortcode( '[mepr-membership-registration-form id="' . EX292_PRODUCT_ID . '"]' ); ?>
        </div>

        <!-- Trust footer -->
        <div style="text-align: center; margin-top: 20px; padding: 0 20px;">
            <p style="font-size: 12px; color: #8896a4; line-height: 1.7; font-style: italic; margin: 0;">
                Your information is private and used solely to support your awareness as a member.<br>
                Excreet does not diagnose, treat, or replace your physician.<br>
                <strong style="font-style: normal; color: <?php echo EX292_PURPLE; ?>;">Secured by Stripe · SSL encrypted · Cancel anytime</strong>
            </p>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   WELCOME-MEMBER PAGE SHORTCODE
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_292_welcome_shortcode(): string {
    $name        = is_user_logged_in() ? ( get_user_meta( get_current_user_id(), 'first_name', true ) ?: 'Member' ) : 'Member';
    $intake_url  = esc_url( get_permalink( EX292_INTAKE_PAGE_ID ) );
    $library_url = esc_url( home_url( '/excreet-library/' ) );
    $account_url = esc_url( home_url( '/my-account/' ) );

    ob_start();
    ?>
    <div style="
        font-family: Georgia, 'Times New Roman', serif;
        max-width: 680px;
        margin: 0 auto;
        text-align: center;
    ">
        <!-- Logo -->
        <div style="margin-bottom: 32px;">
            <img src="https://excreet.com/wp-content/uploads/2025/12/Hero_Logo_dark_Borderless.png"
                 alt="Excreet"
                 style="width: 140px; height: 140px; object-fit: contain;" />
        </div>

        <!-- Welcome headline -->
        <div style="
            background: linear-gradient(135deg, <?php echo EX292_PURPLE_DARK; ?>, <?php echo EX292_PURPLE; ?>);
            border-radius: 20px;
            padding: 36px 40px;
            margin-bottom: 28px;
        ">
            <div style="color: <?php echo EX292_GOLD; ?>; font-size: 11px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; margin-bottom: 10px;">Welcome to Excreet™</div>
            <h2 style="color: #fff; font-size: 28px; font-weight: 700; margin: 0 0 12px; line-height: 1.2;">
                Welcome, <?php echo esc_html( $name ); ?>.
            </h2>
            <p style="color: rgba(255,255,255,.85); font-size: 15px; margin: 0; line-height: 1.8;">
                Your membership is active. Hermes is ready to read your pharmaceutical pattern and build your first Clinical Pattern Report.
            </p>
        </div>

        <!-- Action cards -->
        <div style="display: flex; gap: 16px; flex-wrap: wrap; justify-content: center; margin-bottom: 28px;">

            <!-- Primary: Start intake -->
            <a href="<?php echo $intake_url; ?>" style="
                flex: 1; min-width: 240px;
                display: block;
                background: linear-gradient(135deg, <?php echo EX292_PURPLE; ?>, <?php echo EX292_PURPLE_DARK; ?>);
                border-radius: 16px;
                padding: 28px 24px;
                text-decoration: none;
                color: #fff;
                text-align: center;
                box-shadow: 0 4px 20px rgba(107,47,160,.25);
                transition: transform .2s;
            ">
                <div style="font-size: 32px; margin-bottom: 10px;">℮</div>
                <div style="font-weight: 700; font-size: 16px; margin-bottom: 6px;">Start My Clinical Intake</div>
                <div style="font-size: 12px; opacity:.8; line-height: 1.5;">Submit your pharmaceutical list — Hermes builds your report in ~30 seconds</div>
                <div style="
                    display: inline-block;
                    margin-top: 14px;
                    background: <?php echo EX292_GOLD; ?>;
                    color: <?php echo EX292_PURPLE_DARK; ?>;
                    border-radius: 999px;
                    padding: 6px 20px;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: .06em;
                    text-transform: uppercase;
                ">Start Now →</div>
            </a>

            <!-- Secondary: Library -->
            <a href="<?php echo $library_url; ?>" style="
                flex: 1; min-width: 240px;
                display: block;
                background: #F7F4FC;
                border: 2px solid #D5C5E8;
                border-radius: 16px;
                padding: 28px 24px;
                text-decoration: none;
                color: <?php echo EX292_PURPLE_DARK; ?>;
                text-align: center;
            ">
                <div style="font-size: 32px; margin-bottom: 10px; color: <?php echo EX292_PURPLE; ?>;">◎</div>
                <div style="font-weight: 700; font-size: 16px; margin-bottom: 6px; color: <?php echo EX292_PURPLE_DARK; ?>;">My Excreet Library</div>
                <div style="font-size: 12px; color: #6B7A8D; line-height: 1.5;">View your saved Clinical Pattern Reports and health baseline over time</div>
                <div style="
                    display: inline-block;
                    margin-top: 14px;
                    border: 2px solid <?php echo EX292_PURPLE; ?>;
                    color: <?php echo EX292_PURPLE; ?>;
                    border-radius: 999px;
                    padding: 6px 20px;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: .06em;
                    text-transform: uppercase;
                ">View Library →</div>
            </a>
        </div>

        <!-- Account link -->
        <p style="font-size: 13px; color: #8896a4; margin: 0;">
            <a href="<?php echo $account_url; ?>" style="color: <?php echo EX292_PURPLE; ?>; text-decoration: none; font-weight: 600;">Manage your membership →</a>
        </p>

        <!-- Brand principle -->
        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #EDE7F6;">
            <p style="font-size: 13px; color: #8896a4; font-style: italic; margin: 0; line-height: 1.7;">
                "We don't guess. We pattern. We don't treat symptoms."<br>
                <strong style="font-style: normal; color: <?php echo EX292_PURPLE; ?>; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;">— Excreet™</strong>
            </p>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   MEMBERPRESS CHECKOUT CSS OVERRIDES
   ════════════════════════════════════════════════════════════════════════════ */

/**
 * True when we are on the native MemberPress /register/ checkout page.
 */
function excreet_292_is_mepr_checkout(): bool {
    if ( class_exists( 'MeprUtils' ) && method_exists( 'MeprUtils', 'is_checkout_page' ) ) {
        return (bool) MeprUtils::is_checkout_page();
    }
    // Fallback: URL contains /register/
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
    return strpos( $uri, '/register/' ) !== false;
}

function excreet_292_checkout_css(): void {
    $on_wp_page     = is_singular( 'page' ) && get_the_ID() === EX292_PAYMENT_PAGE_ID;
    $on_mepr_native = excreet_292_is_mepr_checkout();

    if ( ! $on_wp_page && ! $on_mepr_native ) {
        return;
    }
    ?>
    <style id="excreet-292-mepr-css">

    /* ════════════════════════════════════════════════════════════
       NATIVE MEMBERPRESS /register/ page — global overrides
       ════════════════════════════════════════════════════════════ */

    /* Force ReadyLaunch before-form container to be visible */
    .mepr-before-signup-form {
        display: block !important;
        visibility: visible !important;
        height: auto !important;
        overflow: visible !important;
        min-height: 0 !important;
    }

    /* Page body background on native checkout */
    body.mepr-page #mepr_form,
    body.mepr-page .mepr-wrapper,
    body #mepr_checkout_form,
    body .mepr-checkout-wrap {
        font-family: Georgia, 'Times New Roman', serif !important;
    }

    /* Price heading "Pay Excreet / $15 / Month" */
    body .mepr-product-price-str,
    body .mepr_price_str,
    body .mepr-signup-payment-amount,
    body .mepr-invoice-total {
        color: <?php echo EX292_PURPLE; ?> !important;
        font-family: Georgia, serif !important;
        font-weight: 700 !important;
    }

    /* "Have a coupon?" link */
    body .mepr-coupon-toggle,
    body a.mepr-coupon-toggle {
        color: <?php echo EX292_PURPLE; ?> !important;
        font-size: 13px !important;
    }

    /* Order summary / totals table */
    body .mepr-signup-totals td,
    body .mepr-signup-totals th,
    body .mepr-invoice td,
    body .mepr-invoice th {
        font-family: Georgia, serif !important;
        font-size: 14px !important;
        color: #1A0A2E !important;
        border-color: #EDE7F6 !important;
    }

    /* All text inputs, selects on the native page */
    body #mepr_checkout_form input[type="text"],
    body #mepr_checkout_form input[type="email"],
    body #mepr_checkout_form input[type="password"],
    body #mepr_checkout_form input[type="tel"],
    body #mepr_checkout_form select,
    body .mepr-form input[type="text"],
    body .mepr-form input[type="email"],
    body .mepr-form input[type="password"],
    body .mepr-form input[type="tel"],
    body .mepr-form select {
        border: 1.5px solid #D5C5E8 !important;
        border-radius: 8px !important;
        padding: 11px 14px !important;
        font-size: 14px !important;
        color: #1A0A2E !important;
        background: #FDFBFF !important;
        box-shadow: none !important;
        box-sizing: border-box !important;
        width: 100% !important;
        font-family: Georgia, serif !important;
    }
    body #mepr_checkout_form input:focus,
    body #mepr_checkout_form select:focus,
    body .mepr-form input:focus,
    body .mepr-form select:focus {
        border-color: <?php echo EX292_PURPLE; ?> !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(107,47,160,.12) !important;
    }

    /* Labels */
    body #mepr_checkout_form label,
    body .mepr-form label {
        color: <?php echo EX292_PURPLE_DARK; ?> !important;
        font-family: Georgia, serif !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        letter-spacing: .05em !important;
        text-transform: uppercase !important;
    }

    /* Submit / Sign Up button — overrides the black default */
    body #mepr_checkout_form input[type="submit"],
    body #mepr_checkout_form button[type="submit"],
    body .mepr-form input[type="submit"],
    body .mepr-form button[type="submit"],
    body .mepr-submit,
    body #mepr-submit {
        background: linear-gradient(135deg, <?php echo EX292_PURPLE; ?>, <?php echo EX292_PURPLE_DARK; ?>) !important;
        color: #fff !important;
        border: none !important;
        border-radius: 12px !important;
        padding: 16px 32px !important;
        font-size: 15px !important;
        font-weight: 700 !important;
        letter-spacing: .08em !important;
        text-transform: uppercase !important;
        cursor: pointer !important;
        width: 100% !important;
        box-shadow: 0 4px 16px rgba(107,47,160,.35) !important;
        font-family: Georgia, serif !important;
        transition: opacity .2s !important;
    }
    body #mepr_checkout_form input[type="submit"]:hover,
    body .mepr-form input[type="submit"]:hover,
    body .mepr-submit:hover {
        opacity: .88 !important;
    }

    /* Stripe card element */
    body .mepr-stripe-element,
    body #mepr-stripe-card-element,
    body .StripeElement {
        border: 1.5px solid #D5C5E8 !important;
        border-radius: 8px !important;
        padding: 14px !important;
        background: #FDFBFF !important;
        box-sizing: border-box !important;
    }
    body .StripeElement--focus {
        border-color: <?php echo EX292_PURPLE; ?> !important;
        box-shadow: 0 0 0 3px rgba(107,47,160,.12) !important;
    }

    /* Payment method section headings */
    body .mepr-payment-method-label,
    body .mepr-checkout-label,
    body #mepr_checkout_form h3,
    body #mepr_checkout_form h4 {
        color: <?php echo EX292_PURPLE_DARK; ?> !important;
        font-family: Georgia, serif !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        letter-spacing: .06em !important;
        text-transform: uppercase !important;
        border-bottom: 2px solid <?php echo EX292_GOLD; ?> !important;
        padding-bottom: 8px !important;
        margin: 20px 0 14px !important;
    }

    /* Errors */
    body .mepr-error,
    body .mepr-field-error {
        color: #c0392b !important;
        font-size: 12px !important;
        font-weight: 600 !important;
    }

    /* Already a member / login link */
    body .mepr-login-link,
    body .already-member,
    body .mepr-login-link a {
        color: <?php echo EX292_PURPLE; ?> !important;
        font-weight: 600 !important;
    }

    /* ════════════════════════════════════════════════════════════
       SHORTCODE WRAPPER (.excreet-mepr-form-wrap) — page 630
       ════════════════════════════════════════════════════════════ */

    .excreet-mepr-form-wrap .mepr-form,
    .excreet-mepr-form-wrap .mepr-signup-form {
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        max-width: 100% !important;
    }
    .excreet-mepr-form-wrap label {
        color: <?php echo EX292_PURPLE_DARK; ?> !important;
        font-family: Georgia, serif !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        letter-spacing: .04em !important;
        text-transform: uppercase !important;
    }
    .excreet-mepr-form-wrap input[type="text"],
    .excreet-mepr-form-wrap input[type="email"],
    .excreet-mepr-form-wrap input[type="password"],
    .excreet-mepr-form-wrap input[type="tel"],
    .excreet-mepr-form-wrap select {
        border: 1.5px solid #D5C5E8 !important;
        border-radius: 8px !important;
        padding: 12px 14px !important;
        font-size: 14px !important;
        color: #1A0A2E !important;
        background: #FDFBFF !important;
        box-shadow: none !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .excreet-mepr-form-wrap input[type="submit"],
    .excreet-mepr-form-wrap button[type="submit"],
    .excreet-mepr-form-wrap .mepr-submit {
        background: linear-gradient(135deg, <?php echo EX292_PURPLE; ?>, <?php echo EX292_PURPLE_DARK; ?>) !important;
        color: #fff !important;
        border: none !important;
        border-radius: 12px !important;
        padding: 16px 32px !important;
        font-size: 15px !important;
        font-weight: 700 !important;
        letter-spacing: .06em !important;
        text-transform: uppercase !important;
        width: 100% !important;
        box-shadow: 0 4px 16px rgba(107,47,160,.3) !important;
        font-family: Georgia, serif !important;
        cursor: pointer !important;
    }
    </style>
    <?php
}
