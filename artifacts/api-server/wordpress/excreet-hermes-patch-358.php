<?php
/**
 * Plugin Name: Excreet Hermes — Patch 358 (Public Articles / Learn Hub)
 * Description: Creates a public-facing /learn/ knowledge hub — no login required.
 *
 *              - Auto-creates /learn/ WP page on first load
 *              - [excreet_learn] shortcode renders a branded article listing
 *              - Article cards: title, excerpt, read-time estimate, PDF link
 *              - SEO-friendly: no gate, no redirect, indexable by search engines
 *              - Botanical dark-purple palette, gold Cormorant headings
 *              - Ends every card with a soft membership CTA
 *
 * Version: 3.5.8
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX358_PAGE_OPT', '_excreet_358_learn_page_id' );

/* ════════════════════════════════════════════════════════════════════════════
   SETUP — auto-create /learn/ page once
   ════════════════════════════════════════════════════════════════════════════ */

add_action( 'init', 'excreet_358_setup', 20 );

function excreet_358_setup(): void {
    if ( get_option( EX358_PAGE_OPT ) ) { return; }
    if ( ! is_admin() && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) { return; }

    $existing = get_page_by_path( 'learn' );
    if ( $existing ) {
        update_option( EX358_PAGE_OPT, $existing->ID );
        return;
    }

    $id = wp_insert_post( [
        'post_title'   => 'Learn',
        'post_name'    => 'learn',
        'post_content' => '[excreet_learn]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => 1,
    ] );

    if ( $id && ! is_wp_error( $id ) ) {
        update_option( EX358_PAGE_OPT, $id );
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   SHORTCODE — [excreet_learn]
   ════════════════════════════════════════════════════════════════════════════ */

add_shortcode( 'excreet_learn', 'excreet_358_shortcode' );

function excreet_358_shortcode(): string {
    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url = 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg';

    /* ── Article definitions ── */
    $articles = [
        [
            'tag'      => 'Cancer Research',
            'title'    => 'The Farm Did It First: The Global Pesticide-Breast Cancer Link',
            'excerpt'  => 'A 2026 peer-reviewed study found a 6% higher breast cancer incidence rate in rural counties with the heaviest pesticide use — glyphosate and neonicotinoids showing the strongest associations. This is a global farming crisis, and your body has been absorbing it silently for years.',
            'minutes'  => 7,
            'pdf_url'  => null,
            'pdf_label'=> null,
            'url'      => home_url( '/pesticides-breast-cancer-silent-years-excreet/' ),
        ],
        [
            'tag'      => 'Cellular Energy',
            'title'    => 'Fatigue Is Not a Caffeine Deficiency',
            'excerpt'  => 'Your cells run on voltage, not willpower. When mitochondrial output drops, no amount of coffee closes the gap. This report explains what environmental toxic load does to cellular energy production — and what the body is actually trying to tell you.',
            'minutes'  => 9,
            'pdf_url'  => 'https://core-status-check.replit.app/excreet-article-deck-fatigue.pdf',
            'pdf_label'=> 'Read the Full Report (PDF)',
        ],
        [
            'tag'      => 'Environmental Health',
            'title'    => 'The Invisible Burden: Toxic Load and Your Body\'s Signal System',
            'excerpt'  => 'The average adult carries measurable levels of 700+ synthetic compounds. Your body was never designed to process these at scale. Understanding toxic load is the first step toward pre-clinical awareness — before symptoms become diagnoses.',
            'minutes'  => 6,
            'pdf_url'  => null,
            'pdf_label'=> null,
        ],
        [
            'tag'      => 'Pre-Clinical Awareness',
            'title'    => 'What Your Body Tells You Before the Lab Does',
            'excerpt'  => 'Lab ranges are built around population averages — not your optimal. Pre-clinical cellular health reads the signals that appear months or years before conventional markers move. This is the science behind the Excreet body check.',
            'minutes'  => 7,
            'pdf_url'  => null,
            'pdf_label'=> null,
        ],
    ];

    $membership_url = function_exists( 'pmpro_url' ) ? pmpro_url( 'checkout', '?level=1' ) : home_url( '/membership-levels/' );

    ob_start();
    ?>
    <style id="ex358-learn-styles">
    @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500&display=swap');

    body.page-template-default.page #learn-hub,
    #learn-hub {
        font-family: 'DM Sans', sans-serif;
        color: #fff;
        padding: 0;
    }

    #learn-hub .lh-hero {
        background:
            linear-gradient(to bottom, rgba(10,3,24,0.7) 0%, rgba(10,3,24,0.5) 60%, rgba(10,3,24,0.92) 100%),
            url("<?php echo esc_url($bg_url); ?>") center/cover no-repeat;
        padding: 72px 24px 56px;
        text-align: center;
    }
    #learn-hub .lh-hero-tag {
        font-size: 0.65rem;
        letter-spacing: 0.3em;
        text-transform: uppercase;
        color: #F5C518;
        margin-bottom: 14px;
        opacity: 0.85;
    }
    #learn-hub .lh-hero h1 {
        font-family: 'Cormorant Garamond', serif;
        font-size: clamp(2.2rem, 6vw, 3.2rem);
        font-weight: 700;
        color: #fff;
        margin: 0 0 14px;
        line-height: 1.15;
    }
    #learn-hub .lh-hero p {
        font-size: 1rem;
        color: rgba(255,255,255,0.6);
        max-width: 560px;
        margin: 0 auto;
        line-height: 1.7;
    }

    #learn-hub .lh-grid {
        max-width: 860px;
        margin: 0 auto;
        padding: 40px 20px 60px;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    #learn-hub .lh-card {
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(245,197,24,0.15);
        border-radius: 16px;
        padding: 28px 28px 24px;
        transition: border-color 0.2s;
    }
    #learn-hub .lh-card:hover {
        border-color: rgba(245,197,24,0.35);
    }
    #learn-hub .lh-card-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }
    #learn-hub .lh-tag {
        font-size: 0.6rem;
        letter-spacing: 0.22em;
        text-transform: uppercase;
        color: #F5C518;
        background: rgba(245,197,24,0.08);
        border: 1px solid rgba(245,197,24,0.2);
        border-radius: 20px;
        padding: 3px 10px;
    }
    #learn-hub .lh-time {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.3);
    }
    #learn-hub .lh-card h2 {
        font-family: 'Cormorant Garamond', serif;
        font-size: clamp(1.3rem, 3vw, 1.65rem);
        font-weight: 700;
        color: #fff;
        margin: 0 0 12px;
        line-height: 1.25;
    }
    #learn-hub .lh-card p {
        font-size: 0.875rem;
        color: rgba(255,255,255,0.55);
        line-height: 1.75;
        margin: 0 0 20px;
    }
    #learn-hub .lh-card-actions {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    #learn-hub .lh-btn-pdf {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 20px;
        border: 1px solid #F5C518;
        border-radius: 24px;
        color: #F5C518;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.78rem;
        letter-spacing: 0.06em;
        text-decoration: none;
        transition: background 0.2s, color 0.2s;
    }
    #learn-hub .lh-btn-pdf:hover {
        background: #F5C518;
        color: #0a0318;
    }
    #learn-hub .lh-coming {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.25);
        letter-spacing: 0.06em;
        font-style: italic;
    }

    #learn-hub .lh-cta {
        max-width: 860px;
        margin: 0 auto;
        padding: 0 20px 60px;
    }
    #learn-hub .lh-cta-card {
        background: linear-gradient(135deg, rgba(123,47,160,0.2), rgba(10,3,24,0.6));
        border: 1px solid rgba(245,197,24,0.25);
        border-radius: 16px;
        padding: 32px 28px;
        text-align: center;
    }
    #learn-hub .lh-cta-card h3 {
        font-family: 'Cormorant Garamond', serif;
        font-size: 1.6rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 10px;
        line-height: 1.25;
    }
    #learn-hub .lh-cta-card p {
        font-size: 0.85rem;
        color: rgba(255,255,255,0.55);
        margin: 0 0 20px;
        line-height: 1.65;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }
    #learn-hub .lh-btn-gold {
        display: inline-block;
        padding: 12px 32px;
        background: #fff;
        color: #56075E !important;
        border: 2px solid #fff;
        border-radius: 28px;
        font-family: 'DM Sans', sans-serif;
        font-size: 1.1rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-decoration: none;
        transition: background 0.2s, color 0.2s;
    }
    #learn-hub .lh-btn-gold:hover { background: #f5d6ff; border-color: #f5d6ff; }

    @media (max-width: 600px) {
        #learn-hub .lh-card { padding: 22px 18px 20px; }
        #learn-hub .lh-hero { padding: 52px 18px 40px; }
    }
    </style>

    <div id="learn-hub">
        <div class="lh-hero">
            <p class="lh-hero-tag">Public Education</p>
            <h1>What Your Body Has Been Trying to Tell You</h1>
            <p>Free reports and research notes from the Excreet pre-clinical cellular health library. No login required.</p>
        </div>

        <div class="lh-grid">
            <?php foreach ( $articles as $a ) : ?>
            <div class="lh-card">
                <div class="lh-card-meta">
                    <span class="lh-tag"><?php echo esc_html( $a['tag'] ); ?></span>
                    <span class="lh-time"><?php echo esc_html( $a['minutes'] ); ?> min read</span>
                </div>
                <h2><?php echo esc_html( $a['title'] ); ?></h2>
                <p><?php echo esc_html( $a['excerpt'] ); ?></p>
                <div class="lh-card-actions">
                    <?php if ( ! empty( $a['url'] ) ) : ?>
                    <a class="lh-btn-pdf" href="<?php echo esc_url( $a['url'] ); ?>">
                        Read Article →
                    </a>
                    <?php elseif ( $a['pdf_url'] ) : ?>
                    <a class="lh-btn-pdf" href="<?php echo esc_url( $a['pdf_url'] ); ?>" target="_blank" rel="noopener">
                        ↓ <?php echo esc_html( $a['pdf_label'] ); ?>
                    </a>
                    <?php else : ?>
                    <span class="lh-coming">Full report coming soon</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="lh-cta">
            <div class="lh-cta-card">
                <h3>Read the signals. Act before the symptoms.</h3>
                <p>Excreet members get a personalised body scan, access to the Ministry of Healing AI, and earn $5–$10 per month for every person they refer.</p>
                <a class="lh-btn-gold" href="<?php echo esc_url( $membership_url ); ?>">Become a Member →</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   PAGE BACKGROUND — apply healer-bg to /learn/ like other public pages
   ════════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_head', 'excreet_358_learn_bg', 99 );

function excreet_358_learn_bg(): void {
    $page_id = (int) get_option( EX358_PAGE_OPT, 0 );
    if ( ! $page_id || ! is_page( $page_id ) ) { return; }
    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url = esc_url( 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg' );
    ?>
    <style id="ex358-page-bg">
    html, body {
        background: url("<?php echo $bg_url; ?>") center/cover no-repeat fixed #0a0318 !important;
        min-height: 100vh;
    }
    </style>
    <?php
}
