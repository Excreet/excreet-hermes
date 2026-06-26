<?php
/**
 * Plugin Name: Excreet Hermes Patch 2.9.1
 * Description: Member gating — locks intake form, processing page, and library
 *              behind an active PMPro membership (any level).
 *              Redirects non-members to the membership payment page.
 *              PMPro access rules should be configured via WP Admin →
 *              Memberships → Access Rules (programmatic MemberPress rule
 *              creation has been removed — PMPro uses its own rule model).
 * Version:     2.9.1-pmpro
 *
 * Load order (alphabetical mu-plugin order):
 *   excreet-hermes-client.php      ← main plugin
 *   excreet-hermes-patch-272.php   ← job-id / token storage
 *   excreet-hermes-patch-280.php   ← v2 schema rendering
 *   excreet-hermes-patch-290.php   ← Clinical Pattern Report + intake form
 *   excreet-hermes-patch-291.php   ← THIS FILE — member gating
 *
 * Protected pages (by slug):
 *   member-intake-form    (post 21)
 *   intake-processing     (post 849)
 *   member-dashboard      (post 772)
 *
 * Redirect targets:
 *   Not logged in  → WP login with redirect back
 *   Logged in, no membership → /membership-payment-page/ (post 630)
 *   Active member  → pass through
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Constants ─────────────────────────────────────────────────────────────────

define( 'EX291_PAYMENT_URL', '/membership-payment-page/' );
define( 'EX291_ACCOUNT_URL', '/my-account/' );
define( 'EX291_PURPLE',      '#6B2FA0' );
define( 'EX291_PURPLE_DARK', '#3D1060' );
define( 'EX291_GOLD',        '#C9A84C' );
define( 'EX291_GRAY',        '#6B7A8D' );

// Protected page slugs
define( 'EX291_PROTECTED_SLUGS', serialize( [
    'member-intake-form',
    'intake-processing',
    'member-dashboard',
] ) );

/* ════════════════════════════════════════════════════════════════════════════
   HOOKS
   ════════════════════════════════════════════════════════════════════════════ */

add_action( 'template_redirect', 'excreet_291_gate_pages', 1 );

/* ════════════════════════════════════════════════════════════════════════════
   MEMBERSHIP CHECK
   ════════════════════════════════════════════════════════════════════════════ */

/**
 * Returns true if the current user has an active PMPro membership (any level).
 *
 * - If PMPro is not installed, any logged-in user passes (safe fallback).
 * - WordPress admins always pass.
 */
function excreet_291_is_member(): bool {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    // WordPress admins always pass
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }

    // PMPro not installed — let any logged-in user through (safe fallback)
    if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
        return true;
    }

    // null = any active PMPro membership level
    return (bool) pmpro_hasMembershipLevel( null, get_current_user_id() );
}

/* ════════════════════════════════════════════════════════════════════════════
   PAGE GATING — template_redirect
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_291_gate_pages(): void {
    if ( ! is_singular( 'page' ) ) {
        return;
    }

    $slug     = get_post_field( 'post_name', get_queried_object_id() );
    $protected = unserialize( EX291_PROTECTED_SLUGS );

    if ( ! in_array( $slug, $protected, true ) ) {
        return;
    }

    // Not logged in → send to WP login with redirect back
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( get_permalink() ) );
        exit;
    }

    // Logged in but not a member → send to payment page
    if ( ! excreet_291_is_member() ) {
        wp_safe_redirect( home_url( EX291_PAYMENT_URL ) );
        exit;
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   SHORTCODE GATING OVERRIDE
   Wraps 290's intake + processing shortcodes with membership check.
   Non-members see a branded CTA instead of the form.
   ════════════════════════════════════════════════════════════════════════════ */

add_action( 'init', 'excreet_291_wrap_shortcodes', 50 );

function excreet_291_wrap_shortcodes(): void {
    // Wrap pharmaceutical intake form
    if ( shortcode_exists( 'excreet_pharmaceutical_intake' ) ) {
        remove_shortcode( 'excreet_pharmaceutical_intake' );
        add_shortcode( 'excreet_pharmaceutical_intake', 'excreet_291_intake_gated' );
    }

    // Wrap processing result shortcode
    if ( shortcode_exists( 'excreet_hermes_processing_result' ) ) {
        remove_shortcode( 'excreet_hermes_processing_result' );
        add_shortcode( 'excreet_hermes_processing_result', 'excreet_291_processing_gated' );
    }

    // Wrap library shortcode
    if ( shortcode_exists( 'excreet_hermes_latest_result' ) ) {
        remove_shortcode( 'excreet_hermes_latest_result' );
        add_shortcode( 'excreet_hermes_latest_result', 'excreet_291_library_gated' );
    }
}

function excreet_291_intake_gated(): string {
    if ( ! excreet_291_is_member() ) {
        return excreet_291_member_cta(
            'Your Clinical Intake Awaits',
            'This is where Hermes reads your pharmaceutical pattern and builds your personal Clinical Pattern Report. Available to Excreet members.',
            'Become a Member — $15 / month'
        );
    }
    return function_exists( 'excreet_290_intake_form_shortcode' )
        ? excreet_290_intake_form_shortcode()
        : '';
}

function excreet_291_processing_gated(): string {
    if ( ! excreet_291_is_member() ) {
        return excreet_291_member_cta(
            'Members-Only Report',
            'Your Clinical Pattern Report — drug interaction loops, red flags, and lab marker triggers — is waiting. This page is for active Excreet members.',
            'Become a Member — $15 / month'
        );
    }
    return function_exists( 'excreet_290_processing_shortcode' )
        ? excreet_290_processing_shortcode()
        : '';
}

function excreet_291_library_gated(): string {
    if ( ! excreet_291_is_member() ) {
        return excreet_291_member_cta(
            'Your Excreet Library',
            'Your saved Clinical Pattern Reports live here — your personal health baseline over time. This is a members-only space.',
            'Become a Member — $15 / month'
        );
    }
    return function_exists( 'excreet_290_latest_shortcode' )
        ? excreet_290_latest_shortcode()
        : '';
}

/* ════════════════════════════════════════════════════════════════════════════
   BRANDED MEMBERSHIP CTA
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_291_member_cta(
    string $title,
    string $body,
    string $btn_label
): string {
    $payment_url = esc_url( home_url( EX291_PAYMENT_URL ) );
    $login_url   = esc_url( wp_login_url( get_permalink() ) );

    ob_start();
    ?>
    <div style="
        font-family: Georgia, 'Times New Roman', serif;
        max-width: 680px;
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid #D5C5E8;
        box-shadow: 0 4px 24px rgba(107,47,160,.08);
    ">
        <!-- Header bar -->
        <div style="background: linear-gradient(135deg, <?php echo EX291_PURPLE_DARK; ?>, <?php echo EX291_PURPLE; ?>); padding: 28px 32px; display: flex; align-items: center; gap: 14px;">
            <div style="background: <?php echo EX291_GOLD; ?>; border-radius: 50%; width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 900; color: <?php echo EX291_PURPLE_DARK; ?>; font-family: serif; flex-shrink: 0;">℮</div>
            <div>
                <div style="color: <?php echo EX291_GOLD; ?>; font-size: 11px; font-weight: 700; letter-spacing: .15em; text-transform: uppercase;">Excreet™ — Members Only</div>
                <div style="color: #fff; font-size: 20px; font-weight: 700; line-height: 1.2; margin-top: 2px;"><?php echo esc_html( $title ); ?></div>
            </div>
        </div>

        <!-- Body -->
        <div style="background: #fff; padding: 36px 32px;">

            <p style="color: #334e68; font-size: 15px; line-height: 1.8; margin: 0 0 28px;"><?php echo esc_html( $body ); ?></p>

            <!-- Feature list -->
            <div style="background: #F7F4FC; border-radius: 12px; padding: 20px 24px; margin-bottom: 28px;">
                <div style="font-size: 11px; font-weight: 700; color: <?php echo EX291_PURPLE; ?>; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid <?php echo EX291_GOLD; ?>;">What Members Receive</div>
                <?php
                $features = [
                    'Pharmaceutical drug interaction analysis',
                    'Personal Clinical Pattern Report (PDF-quality)',
                    'Red flag triage — HIGH / MODERATE / AWARENESS tiers',
                    'Lab marker triggers — exactly what to ask your doctor to test',
                    'Observable signals matched to your medication pattern',
                    'Excreet Library — your reports saved as a health baseline',
                ];
                foreach ( $features as $f ) : ?>
                <div style="display: flex; align-items: baseline; gap: 10px; margin-bottom: 10px; font-size: 14px; color: #1A0A2E; line-height: 1.5;">
                    <span style="color: <?php echo EX291_GOLD; ?>; font-weight: 700; flex-shrink: 0;">✓</span>
                    <?php echo esc_html( $f ); ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Price badge -->
            <div style="text-align: center; margin-bottom: 24px;">
                <span style="display: inline-block; background: linear-gradient(135deg, <?php echo EX291_PURPLE_DARK; ?>, <?php echo EX291_PURPLE; ?>); color: #fff; border-radius: 999px; padding: 8px 24px; font-size: 13px; font-weight: 700; letter-spacing: .06em;">$15 / month &nbsp;·&nbsp; Cancel anytime</span>
            </div>

            <!-- CTA buttons -->
            <div style="display: flex; flex-direction: column; gap: 12px; align-items: center;">
                <a href="<?php echo $payment_url; ?>" style="
                    display: block;
                    width: 100%;
                    max-width: 400px;
                    text-align: center;
                    padding: 16px 32px;
                    background: linear-gradient(135deg, <?php echo EX291_PURPLE; ?>, <?php echo EX291_PURPLE_DARK; ?>);
                    color: #fff;
                    border-radius: 12px;
                    text-decoration: none;
                    font-size: 15px;
                    font-weight: 700;
                    letter-spacing: .04em;
                    text-transform: uppercase;
                    box-shadow: 0 4px 16px rgba(107,47,160,.3);
                "><?php echo esc_html( $btn_label ); ?></a>

                <?php if ( ! is_user_logged_in() ) : ?>
                <a href="<?php echo $login_url; ?>" style="color: <?php echo EX291_GRAY; ?>; font-size: 13px; text-decoration: none;">Already a member? Log in →</a>
                <?php else : ?>
                <a href="<?php echo esc_url( home_url( EX291_ACCOUNT_URL ) ); ?>" style="color: <?php echo EX291_GRAY; ?>; font-size: 13px; text-decoration: none;">Manage your membership →</a>
                <?php endif; ?>
            </div>

            <!-- Principle -->
            <div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid #EDE7F6; text-align: center;">
                <p style="margin: 0; font-size: 13px; font-style: italic; color: <?php echo EX291_GRAY; ?>; line-height: 1.6;">
                    "We don't guess. We pattern. We don't treat symptoms."<br>
                    <strong style="font-style: normal; color: <?php echo EX291_PURPLE; ?>; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;">— Excreet™</strong>
                </p>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
