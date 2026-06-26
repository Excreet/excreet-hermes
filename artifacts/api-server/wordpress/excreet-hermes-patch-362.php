<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.6.2
 * Description: "Share Your Story" banner on WooCommerce product pages.
 *
 *   Injects a gold "Record a Testimonial" call-to-action strip
 *   below the Add to Cart button on every single product page.
 *   No login required — links to /record-my-story/ which is fully public.
 *
 * Version: 3.6.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'woocommerce_after_add_to_cart_button', 'excreet_362_story_cta', 20 );
function excreet_362_story_cta(): void {
    if ( ! is_product() ) return;
    ?>
    <div class="ex362-story-cta">
        <div class="ex362-story-inner">
            <div class="ex362-story-icon">
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                    <circle cx="14" cy="14" r="12" stroke="#C9A84C" stroke-width="1.4" fill="none"/>
                    <circle cx="14" cy="14" r="4.5" fill="#C9A84C" opacity=".9"/>
                    <circle cx="14" cy="14" r="1.8" fill="#1a0430"/>
                </svg>
            </div>
            <div class="ex362-story-text">
                <strong>Already using Excreet?</strong>
                Record a short video about your experience — members and new visitors see it on the stories page.
            </div>
            <a href="<?php echo esc_url( home_url( '/record-my-story/' ) ); ?>" class="ex362-story-btn">
                Share Your Story →
            </a>
        </div>
    </div>

    <style>
    .ex362-story-cta {
        margin-top: 24px;
        padding: 18px 20px;
        background: linear-gradient(135deg, rgba(26,4,48,.95), rgba(86,7,94,.25));
        border: 1px solid rgba(201,168,76,.35);
        border-radius: 14px;
        font-family: 'Georgia', serif;
    }
    .ex362-story-inner {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .ex362-story-icon { flex-shrink: 0; }
    .ex362-story-text {
        flex: 1;
        min-width: 180px;
        font-size: 13px;
        color: rgba(255,255,255,.80);
        line-height: 1.55;
        font-family: 'DM Sans', 'Helvetica Neue', sans-serif;
    }
    .ex362-story-text strong {
        color: #F5D97A;
        display: block;
        margin-bottom: 3px;
        font-size: 13px;
        letter-spacing: .01em;
    }
    .ex362-story-btn {
        display: inline-block;
        padding: 9px 22px;
        background: linear-gradient(135deg,#C9A84C,#a8873a);
        color: #1a0430 !important;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        text-decoration: none !important;
        white-space: nowrap;
        font-family: 'DM Sans', 'Helvetica Neue', sans-serif;
        transition: opacity .2s;
    }
    .ex362-story-btn:hover { opacity: .85; }
    @media (max-width: 480px) {
        .ex362-story-inner { flex-direction: column; align-items: flex-start; }
        .ex362-story-btn { width: 100%; text-align: center; }
    }
    </style>
    <?php
}
