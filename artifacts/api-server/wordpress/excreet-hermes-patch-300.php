<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.0.0
 * Description: PMPro member journey design — Botanical / Healing Environment palette.
 *              Applies to all PMPro pages in workflow order:
 *                1. Membership Levels   (ID 874) — choose your plan
 *                2. Membership Checkout (ID 871) — commit
 *                3. Membership Confirmation (ID 872) — welcome moment
 *                4. Account / Billing / Cancel / Orders / Profile (IDs 868-870, 873, 876)
 *
 *              Design philosophy: modern clinical precision floating over old-world
 *              healing environments. Monthly botanical background auto-rotates
 *              (healer-bg-MM.jpg), shared with the intake form and Ministry pages.
 *
 * Version: 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'EX300_PURPLE',      '#6B2FA0' );
define( 'EX300_PURPLE_DARK', '#3D1060' );
define( 'EX300_PURPLE_LT',   '#EDE7F6' );
define( 'EX300_GOLD',        '#C9A84C' );
define( 'EX300_GOLD_LT',     '#FDF6E3' );
define( 'EX300_DARK',        '#1A0A2E' );
define( 'EX300_GRAY',        '#6B7A8D' );

// All PMPro page IDs covered by this patch
define( 'EX300_PMPRO_PAGE_IDS', [ 874, 871, 872, 868, 869, 870, 873, 876 ] );

// ── Hooks ──────────────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'excreet_300_styles',            99 );
add_action( 'wp_head',            'excreet_300_inline_bg',        100 );
add_action( 'pmpro_before_levels_table', 'excreet_300_levels_header'       );
add_action( 'pmpro_after_levels_table',  'excreet_300_levels_footer'       );
add_filter( 'pmpro_confirmation_message', 'excreet_300_confirmation_msg', 10, 2 );
add_action( 'pmpro_checkout_before_submit_button', 'excreet_300_checkout_trust_bar' );

/* ════════════════════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_300_is_pmpro_page(): bool {
    $pid = (int) get_queried_object_id();
    return in_array( $pid, EX300_PMPRO_PAGE_IDS, true );
}

function excreet_300_bg_url(): string {
    $month = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $ver   = date( 'Ym' );
    return 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg?v=' . $ver;
}

/* ════════════════════════════════════════════════════════════════════════════
   INLINE HEAD: monthly botanical background on PMPro pages
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_300_inline_bg(): void {
    if ( ! excreet_300_is_pmpro_page() ) {
        return;
    }
    $pid     = (int) get_queried_object_id();
    $bg_url  = esc_url( excreet_300_bg_url() );
    $sel     = 'body.page-id-' . $pid;
    echo '<style id="excreet-300-bg">
    /* ── Botanical full-page atmosphere ── */
    ' . $sel . ' {
        background: url("' . $bg_url . '") center/cover no-repeat fixed !important;
        min-height: 100vh;
    }
    ' . $sel . ' #page,
    ' . $sel . ' .site-content,
    ' . $sel . ' #content,
    ' . $sel . ' #main,
    ' . $sel . ' .site-main,
    ' . $sel . ' .entry-content,
    ' . $sel . ' .elementor-section-wrap { background: transparent !important; }

    /* Remove header / footer — full immersion */
    ' . $sel . ' .site-header,
    ' . $sel . ' #masthead,
    ' . $sel . ' header.site-header,
    ' . $sel . ' #site-header,
    ' . $sel . ' .elementor-location-header { display: none !important; }
    ' . $sel . ' .site-footer,
    ' . $sel . ' footer.site-footer,
    ' . $sel . ' #colophon,
    ' . $sel . ' .elementor-location-footer { display: none !important; }

    /* Hide redundant page title */
    ' . $sel . ' h1.entry-title,
    ' . $sel . ' .entry-header,
    ' . $sel . ' .page-header { display: none !important; }

    /* Breathing room above & below the PMPro content */
    ' . $sel . ' .entry-content { padding: 48px 16px 80px !important; }

    /* ── PMPro description text — force dark on white card ── */
    .pmpro_level_name_text,
    .pmpro_level_cost_text,
    .pmpro_level_cost_text p,
    .pmpro_card_content p,
    .pmpro_card_content span,
    #pmpro_level_cost p,
    #pmpro_form p,
    #pmpro_form li,
    #pmpro_form span {
        color: #1A0A2E !important;
        font-size: 14px !important;
        line-height: 1.6 !important;
    }
    .pmpro_level_name_text strong,
    .pmpro_level_cost_text strong,
    #pmpro_level_cost strong,
    #pmpro_form strong,
    #pmpro_form b {
        color: #3D1060 !important;
        font-weight: 700 !important;
    }

    /* ── Full-width on mobile: stop theme wrappers squeezing the form ── */
    @media (max-width: 768px) {
        ' . $sel . ' .entry-content,
        ' . $sel . ' .site-content,
        ' . $sel . ' #content,
        ' . $sel . ' #main,
        ' . $sel . ' .site-main {
            width: 100% !important;
            max-width: 100% !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        ' . $sel . ' #pmpro_form,
        ' . $sel . ' #pmpro_confirmation_div,
        ' . $sel . ' .pmpro_content {
            margin: 0 12px 32px !important;
            padding: 24px 18px !important;
            border-radius: 14px !important;
        }
    }
    </style>' . "\n";
}

/* ════════════════════════════════════════════════════════════════════════════
   STYLESHEET: PMPro element overrides — botanical card palette
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_300_styles(): void {
    if ( ! excreet_300_is_pmpro_page() ) {
        return;
    }
    $p  = EX300_PURPLE;
    $pd = EX300_PURPLE_DARK;
    $pl = EX300_PURPLE_LT;
    $g  = EX300_GOLD;
    $gl = EX300_GOLD_LT;
    $dk = EX300_DARK;
    $gr = EX300_GRAY;

    // Output as inline <style> via wp_add_inline_style trick
    wp_register_style( 'ex300-base', false );
    wp_enqueue_style( 'ex300-base' );
    $css = "
    /* ── PMPro wrapper card ── */
    #pmpro_form,
    #pmpro_confirmation_div,
    .pmpro_content,
    .pmpro_account,
    .pmpro_orders_list,
    .pmpro_invoice {
        background: #fff !important;
        border-radius: 18px !important;
        border: 1px solid #D5C5E8 !important;
        box-shadow: 0 8px 48px rgba(30,10,60,0.20), 0 2px 12px rgba(30,10,60,0.08) !important;
        max-width: 860px !important;
        margin: 0 auto 40px !important;
        overflow: hidden !important;
        padding: 32px 36px !important;
        font-family: system-ui, -apple-system, sans-serif !important;
    }

    /* ── PMPro headings ── */
    .pmpro_content h1,
    .pmpro_content h2,
    .pmpro_content h3,
    #pmpro_form h2,
    #pmpro_form legend,
    .pmpro_account h2,
    .pmpro_account h3 {
        color: {$pd} !important;
        font-family: Georgia, 'Times New Roman', serif !important;
        border-bottom: 2px solid {$g} !important;
        padding-bottom: 8px !important;
        margin-bottom: 20px !important;
    }

    /* ── Form labels ── */
    #pmpro_form label,
    .pmpro_checkout-field label,
    .pmpro_payment_information_fields label {
        font-size: 12px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: .06em !important;
        color: {$pd} !important;
        margin-bottom: 5px !important;
    }

    /* ── Text / email / number inputs ── */
    #pmpro_form input[type='text'],
    #pmpro_form input[type='email'],
    #pmpro_form input[type='tel'],
    #pmpro_form input[type='number'],
    #pmpro_form input[type='password'],
    #pmpro_form select,
    #pmpro_form textarea {
        width: 100% !important;
        padding: 11px 14px !important;
        border: 1.5px solid #D5C5E8 !important;
        border-radius: 8px !important;
        font-size: 15px !important;
        color: {$dk} !important;
        background: #FDFBFF !important;
        transition: border-color .2s !important;
        font-family: inherit !important;
    }
    #pmpro_form input[type='text']:focus,
    #pmpro_form input[type='email']:focus,
    #pmpro_form input[type='tel']:focus,
    #pmpro_form input[type='number']:focus,
    #pmpro_form input[type='password']:focus,
    #pmpro_form select:focus,
    #pmpro_form textarea:focus {
        outline: none !important;
        border-color: {$p} !important;
        background: #fff !important;
    }

    /* ── Body text readability — dark on white card ── */
    #pmpro_form p,
    #pmpro_form span,
    #pmpro_form li,
    #pmpro_level_cost,
    #pmpro_level_cost p,
    .pmpro_level_name_text,
    .pmpro_level_cost_text,
    .pmpro_level_cost_text p,
    .pmpro_card_content p,
    .pmpro_card_content span,
    .pmpro_checkout-field p,
    .pmpro_content p,
    .pmpro_content span,
    .pmpro_content li,
    #pmpro_checkout_box p,
    #pmpro_member_discount_code_div p,
    .pmpro_order-notes p {
        color: {$dk} !important;
        font-size: 14px !important;
        line-height: 1.6 !important;
    }

    /* ── Level cost — price and plan name stand out clearly ── */
    .pmpro_level_name_text strong,
    .pmpro_level_cost_text strong,
    #pmpro_level_cost strong,
    #pmpro_level_cost b,
    #pmpro_form strong,
    #pmpro_form b {
        color: {$pd} !important;
        font-weight: 700 !important;
    }

    /* ── Section dividers inside the checkout form ── */
    #pmpro_form fieldset {
        border: none !important;
        border-top: 2px solid {$g} !important;
        padding: 24px 0 0 !important;
        margin: 24px 0 0 !important;
    }

    /* ── Submit button — full-width gold gradient ── */
    #pmpro_form .pmpro_btn-submit-checkout,
    #pmpro_form input[type='submit'],
    #pmpro_form button[type='submit'],
    .pmpro_btn-checkout {
        display: block !important;
        width: 100% !important;
        padding: 16px 24px !important;
        background: linear-gradient(135deg, {$g}, #a8873a) !important;
        color: {$pd} !important;
        border: none !important;
        border-radius: 10px !important;
        font-size: 16px !important;
        font-weight: 800 !important;
        font-family: Georgia, serif !important;
        letter-spacing: .04em !important;
        text-transform: uppercase !important;
        cursor: pointer !important;
        text-decoration: none !important;
        text-align: center !important;
        transition: opacity .2s !important;
        margin-top: 12px !important;
    }
    #pmpro_form .pmpro_btn-submit-checkout:hover,
    #pmpro_form input[type='submit']:hover,
    .pmpro_btn-checkout:hover { opacity: .88 !important; }


    /* ── Stripe / payment element wrapper ── */
    #pmpro_payment_information_fields {
        background: {$pl} !important;
        border-radius: 10px !important;
        padding: 20px !important;
        margin-top: 8px !important;
    }

    /* ── Links in PMPro context ── */
    .pmpro_content a,
    #pmpro_form a,
    .pmpro_account a { color: {$p} !important; }
    .pmpro_content a:hover,
    #pmpro_form a:hover { color: {$pd} !important; }

    /* ── Success / error messages ── */
    .pmpro_message,
    .pmpro_error {
        border-radius: 8px !important;
        padding: 12px 16px !important;
        font-size: 14px !important;
    }
    .pmpro_error {
        background: #FDEDEC !important;
        border: 1px solid #F1948A !important;
        color: #C0392B !important;
    }
    .pmpro_message {
        background: {$pl} !important;
        border: 1px solid {$p} !important;
        color: {$pd} !important;
    }

    /* ── Account / billing / cancel / orders utility styles ── */
    .pmpro_account-membership { margin-bottom: 20px; }
    table.pmpro { width: 100%; border-collapse: collapse; }
    table.pmpro th {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .08em; color: {$p}; border-bottom: 2px solid {$g};
        padding: 8px 10px; text-align: left;
    }
    table.pmpro td { padding: 10px; border-bottom: 1px solid #E8E0F0; color: {$dk}; font-size: 14px; }
    table.pmpro tr:last-child td { border-bottom: none; }
    table.pmpro tr:hover td { background: {$pl}; }
    ";
    wp_add_inline_style( 'ex300-base', $css );
}

/* ════════════════════════════════════════════════════════════════════════════
   PAGE 874 — MEMBERSHIP LEVELS: custom card grid injected before table
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_300_levels_header(): void {
    $p  = EX300_PURPLE;
    $pd = EX300_PURPLE_DARK;
    $pl = EX300_PURPLE_LT;
    $g  = EX300_GOLD;
    $dk = EX300_DARK;

    // Determine the correct checkout URLs via PMPro
    $checkout_url = function_exists( 'pmpro_url' )
        ? pmpro_url( 'checkout' )
        : home_url( '/membership-checkout/' );

    $levels = [
        [
            'id'       => 1,
            'name'     => 'Starter',
            'price'    => '$15',
            'period'   => '/ month',
            'badge'    => '',
            'featured' => false,
            'perks'    => [
                'Clinical Pattern Report',
                'Drug Interaction Mapping',
                'Red Flag Triage',
                'Lab Marker Triggers',
                'Excreet Health Library',
                '5 Ministry sessions / mo',
            ],
            'cta'      => 'Begin Your Journey',
            'note'     => 'Cancel anytime. No commitment.',
        ],
        [
            'id'       => 2,
            'name'     => 'Premium',
            'price'    => '$25',
            'period'   => '/ month',
            'badge'    => 'Most Complete',
            'featured' => true,
            'perks'    => [
                'Everything in Starter',
                '20 Ministry sessions / mo',
                'Priority pattern analysis',
                'Healing protocol generation',
                'Ministry chat history saved',
                'Early access to new features',
            ],
            'cta'      => 'Claim Premium Access',
            'note'     => 'Our most popular plan.',
        ],
        [
            'id'       => 4,
            'name'     => 'Protocol Session',
            'price'    => '$29',
            'period'   => 'one-time',
            'badge'    => 'Add-On',
            'featured' => false,
            'perks'    => [
                'Single personalized protocol',
                'Curated supplement guidance',
                'Dietary pattern support',
                'Lifestyle adjustment roadmap',
                'Valid for 30 days',
            ],
            'cta'      => 'Get a Protocol',
            'note'     => 'Best paired with a membership plan.',
        ],
    ];
    ?>
    <style>
    .ex300-levels-wrap {
        font-family: system-ui, -apple-system, sans-serif;
        max-width: 960px;
        margin: 0 auto 48px;
    }
    .ex300-levels-intro {
        text-align: center;
        padding: 40px 24px 32px;
        background: #fff;
        border-radius: 18px 18px 0 0;
        border: 1px solid #D5C5E8;
        border-bottom: none;
        box-shadow: 0 8px 48px rgba(30,10,60,0.20), 0 2px 12px rgba(30,10,60,0.08);
    }
    .ex300-levels-logo {
        display: block;
        width: 64px; height: 64px;
        object-fit: contain;
        margin: 0 auto 16px;
    }
    .ex300-levels-eyebrow {
        font-size: 11px; font-weight: 700; letter-spacing: .14em;
        text-transform: uppercase; color: <?php echo $g; ?>; margin-bottom: 8px;
    }
    .ex300-levels-heading {
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 28px; font-weight: 700; color: <?php echo $pd; ?>;
        margin: 0 0 10px; line-height: 1.2;
    }
    .ex300-levels-sub {
        font-size: 15px; color: <?php echo EX300_GRAY; ?>;
        max-width: 560px; margin: 0 auto; line-height: 1.65;
    }
    .ex300-cards-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0;
        background: #fff;
        border: 1px solid #D5C5E8;
        border-top: none;
        border-radius: 0 0 18px 18px;
        box-shadow: 0 8px 48px rgba(30,10,60,0.20), 0 2px 12px rgba(30,10,60,0.08);
        overflow: hidden;
    }
    @media(max-width: 680px) {
        .ex300-cards-grid { grid-template-columns: 1fr; }
    }
    .ex300-card {
        padding: 28px 24px 32px;
        border-right: 1px solid #E8E0F0;
        display: flex; flex-direction: column;
        position: relative;
    }
    .ex300-card:last-child { border-right: none; }
    .ex300-card.ex300-featured {
        background: linear-gradient(180deg, <?php echo $pl; ?> 0%, #fff 60%);
        border-top: 3px solid <?php echo $p; ?>;
    }
    .ex300-card-badge {
        position: absolute; top: 16px; right: 16px;
        background: <?php echo $p; ?>; color: #fff;
        font-size: 10px; font-weight: 700; letter-spacing: .1em;
        text-transform: uppercase; border-radius: 999px; padding: 3px 10px;
    }
    .ex300-card-badge.ex300-addon { background: <?php echo $g; ?>; color: <?php echo $pd; ?>; }
    .ex300-card-name {
        font-family: Georgia, serif; font-size: 18px; font-weight: 700;
        color: <?php echo $pd; ?>; margin-bottom: 4px;
    }
    .ex300-card-price {
        font-size: 36px; font-weight: 800; color: <?php echo $p; ?>;
        line-height: 1; margin: 12px 0 2px;
        font-family: Georgia, serif;
    }
    .ex300-card-period {
        font-size: 12px; color: <?php echo EX300_GRAY; ?>;
        margin-bottom: 20px;
    }
    .ex300-card-divider {
        border: none; border-top: 2px solid <?php echo $g; ?>;
        margin: 0 0 18px;
    }
    .ex300-card-perks { list-style: none; padding: 0; margin: 0 0 24px; flex: 1; }
    .ex300-card-perks li {
        font-size: 13px; color: <?php echo $dk; ?>; padding: 6px 0;
        border-bottom: 1px solid #F0EAF8;
        display: flex; align-items: flex-start; gap: 8px;
        line-height: 1.4;
    }
    .ex300-card-perks li:last-child { border-bottom: none; }
    .ex300-card-perks li::before {
        content: '✓'; color: <?php echo $g; ?>; font-weight: 800;
        font-size: 12px; flex-shrink: 0; margin-top: 1px;
    }
    .ex300-card-cta {
        display: block; width: 100%; padding: 13px 16px;
        background: linear-gradient(135deg, <?php echo $g; ?>, #a8873a);
        color: <?php echo $pd; ?>; border: none; border-radius: 10px;
        font-size: 14px; font-weight: 800; font-family: Georgia, serif;
        letter-spacing: .03em; text-transform: uppercase; cursor: pointer;
        text-decoration: none; text-align: center;
        transition: opacity .2s; margin-bottom: 8px;
    }
    .ex300-card.ex300-featured .ex300-card-cta {
        background: linear-gradient(135deg, <?php echo $p; ?>, <?php echo $pd; ?>);
        color: #fff;
    }
    .ex300-card-cta:hover { opacity: .88; }
    .ex300-card-note {
        font-size: 11px; color: <?php echo EX300_GRAY; ?>;
        text-align: center; line-height: 1.4;
    }
    </style>

    <div class="ex300-levels-wrap">
        <div class="ex300-levels-intro">
            <img src="https://excreet.com/wp-content/uploads/excreet-hero-logo.png" alt="Excreet" class="ex300-levels-logo">
            <div class="ex300-levels-eyebrow">Excreet™ — WHealth Intelligence</div>
            <h2 class="ex300-levels-heading">Choose Your Healing Path</h2>
            <p class="ex300-levels-sub">Every plan gives you a Clinical Pattern Report from your intake — your personal pharmaceutical analysis, red flag triage, and lab marker roadmap to bring to your physician.</p>
        </div>
        <div class="ex300-cards-grid">
        <?php foreach ( $levels as $lvl ) :
            $level_checkout = add_query_arg( 'level', $lvl['id'], $checkout_url );
        ?>
            <div class="ex300-card <?php echo $lvl['featured'] ? 'ex300-featured' : ''; ?>">
                <?php if ( $lvl['badge'] ) : ?>
                <span class="ex300-card-badge <?php echo $lvl['id'] === 4 ? 'ex300-addon' : ''; ?>">
                    <?php echo esc_html( $lvl['badge'] ); ?>
                </span>
                <?php endif; ?>
                <div class="ex300-card-name"><?php echo esc_html( $lvl['name'] ); ?></div>
                <div class="ex300-card-price"><?php echo esc_html( $lvl['price'] ); ?></div>
                <div class="ex300-card-period"><?php echo esc_html( $lvl['period'] ); ?></div>
                <hr class="ex300-card-divider">
                <ul class="ex300-card-perks">
                    <?php foreach ( $lvl['perks'] as $perk ) : ?>
                    <li><?php echo esc_html( $perk ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo esc_url( $level_checkout ); ?>" class="ex300-card-cta">
                    <?php echo esc_html( $lvl['cta'] ); ?>
                </a>
                <div class="ex300-card-note"><?php echo esc_html( $lvl['note'] ); ?></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function excreet_300_levels_footer(): void {
    $p  = EX300_PURPLE;
    $pd = EX300_PURPLE_DARK;
    $g  = EX300_GOLD;
    ?>
    <div style="max-width:960px;margin:0 auto 32px;text-align:center;
                background:rgba(255,255,255,0.82);border-radius:12px;
                padding:20px 28px;box-shadow:0 2px 12px rgba(30,10,60,0.08);
                font-family:system-ui,-apple-system,sans-serif;">
        <p style="margin:0;font-size:13px;color:<?php echo EX300_GRAY; ?>;line-height:1.65;">
            <strong style="color:<?php echo $pd; ?>;">Secure checkout powered by Stripe.</strong>
            &nbsp;All memberships auto-renew and can be cancelled anytime from your account.
            &nbsp;Not sure which plan fits? Start with Starter — you can upgrade anytime.
        </p>
    </div>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   PAGE 871 — CHECKOUT: branded header + trust bar above submit
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_300_checkout_trust_bar(): void {
    $p  = EX300_PURPLE;
    $pd = EX300_PURPLE_DARK;
    $g  = EX300_GOLD;
    ?>
    <div style="background:<?php echo EX300_PURPLE_LT; ?>;border-radius:10px;padding:14px 18px;
                margin:16px 0 8px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;
                font-family:system-ui,-apple-system,sans-serif;">
        <?php
        $trust = [
            [ '🔒', 'SSL Encrypted' ],
            [ '⚡', 'Stripe Secured' ],
            [ '↩', 'Cancel Anytime' ],
            [ '🌿', 'No Hidden Fees' ],
        ];
        foreach ( $trust as $t ) :
        ?>
        <span style="font-size:12px;color:<?php echo $pd; ?>;font-weight:600;
                     display:flex;align-items:center;gap:5px;">
            <span style="font-size:14px;"><?php echo $t[0]; ?></span>
            <?php echo esc_html( $t[1] ); ?>
        </span>
        <?php endforeach; ?>
    </div>
    <?php
}

/* ════════════════════════════════════════════════════════════════════════════
   PAGE 872 — CONFIRMATION: replace PMPro default text with warm welcome
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_300_confirmation_msg( string $message, $level ): string {
    $level_name = is_object( $level ) ? esc_html( $level->name ) : 'Excreet';
    $intake_url = esc_url( home_url( '/member-intake-form/' ) );
    $ministry_url = esc_url( home_url( '/ask-the-healer/' ) );
    $dashboard_url = esc_url( home_url( '/member-dashboard/' ) );
    $p  = EX300_PURPLE;
    $pd = EX300_PURPLE_DARK;
    $pl = EX300_PURPLE_LT;
    $g  = EX300_GOLD;
    ob_start();
    ?>
    <div style="font-family:system-ui,-apple-system,sans-serif;text-align:center;padding:16px 8px 32px;">

        <img src="https://excreet.com/wp-content/uploads/excreet-hero-logo.png" alt="Excreet"
             style="width:80px;height:80px;object-fit:contain;display:block;margin:0 auto 20px;">

        <div style="font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
                    color:<?php echo $g; ?>;margin-bottom:8px;">Welcome to Excreet</div>
        <h2 style="font-family:Georgia,'Times New Roman',serif;font-size:26px;font-weight:700;
                   color:<?php echo $pd; ?>;margin:0 0 10px;line-height:1.3;">
            Your <?php echo $level_name; ?> membership is active.
        </h2>
        <p style="font-size:15px;color:<?php echo EX300_GRAY; ?>;max-width:480px;margin:0 auto 28px;line-height:1.65;">
            You have stepped into a different kind of health intelligence — one that remembers what modern medicine forgot. Take a breath. Your first step is the intake form.
        </p>

        <div style="display:flex;flex-direction:column;gap:12px;max-width:360px;margin:0 auto 28px;">
            <a href="<?php echo $intake_url; ?>" style="
                display:block;padding:15px 24px;
                background:linear-gradient(135deg,<?php echo $g; ?>,#a8873a);
                color:<?php echo $pd; ?>;border-radius:10px;
                font-size:15px;font-weight:800;font-family:Georgia,serif;
                text-decoration:none;letter-spacing:.03em;text-transform:uppercase;
                transition:opacity .2s;">
                → Begin My Intake Form
            </a>
            <a href="<?php echo $dashboard_url; ?>" style="
                display:block;padding:13px 24px;
                border:2px solid <?php echo $p; ?>;color:<?php echo $p; ?>;
                border-radius:10px;font-size:14px;font-weight:700;
                text-decoration:none;text-align:center;transition:opacity .2s;">
                Go to My Dashboard
            </a>
        </div>

        <div style="display:flex;justify-content:center;gap:28px;flex-wrap:wrap;">
            <?php
            $steps = [
                [ '1', 'Complete Intake', 'Your pharmaceutical profile is built from your answers. The more detail, the sharper the pattern.' ],
                [ '2', 'Receive Your Report', 'Clinical Pattern Report delivered — drug interactions, red flags, lab markers to request from your physician.' ],
                [ '3', 'The Ministry Awaits', 'Ask Excreet anything. We were the first to warn, first to guide, and we will hold your hand through it.' ],
            ];
            foreach ( $steps as $s ) :
            ?>
            <div style="max-width:180px;text-align:center;">
                <div style="background:<?php echo $pl; ?>;border-radius:50%;width:36px;height:36px;
                            display:flex;align-items:center;justify-content:center;
                            font-size:14px;font-weight:800;color:<?php echo $p; ?>;
                            margin:0 auto 8px;border:2px solid <?php echo $p; ?>;">
                    <?php echo $s[0]; ?>
                </div>
                <div style="font-size:12px;font-weight:700;color:<?php echo $pd; ?>;margin-bottom:4px;">
                    <?php echo esc_html( $s[1] ); ?>
                </div>
                <div style="font-size:11px;color:<?php echo EX300_GRAY; ?>;line-height:1.5;">
                    <?php echo esc_html( $s[2] ); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
