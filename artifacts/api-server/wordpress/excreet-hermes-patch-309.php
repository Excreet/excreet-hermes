<?php
/**
 * Plugin Name: Excreet Patch 309 — Global Bathroom Background
 * Description: Applies the monthly bathroom background (healer-bg-MM.jpg) to all
 *              standard WordPress pages not already styled by a dedicated patch.
 *              Dedicated patches (297 dashboard/legal, 300 PMPro, 301 provider report)
 *              use more-specific body.page-id-X selectors and will always override this.
 *              Covers: /know-the-signals/, any unclaimed WP page, and future pages.
 *              Monthly rotation: upload healer-bg-MM.jpg on the 1st to refresh all pages.
 * Version: 3.0.9
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_head', 'excreet_309_global_bg', 999 );

function excreet_309_global_bg(): void {
    if ( is_admin() || is_feed() || is_robots() ) {
        return;
    }

    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $ver    = date( 'Ym' );
    $bg_url = esc_url( 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg?v=' . $ver );

    echo '<style id="ex309-global-bg">
/* ── Global bathroom background — all unclaimed WP pages ── */
html, body {
    background: url("' . $bg_url . '") center/cover no-repeat fixed #0c0115 !important;
}
#page,
.site-content,
#content,
#main,
.site-main,
.wp-block-post-content,
.elementor-section-wrap,
article.page,
article.post {
    background: transparent !important;
}
.site-header,
.elementor-location-header {
    background: rgba(15,3,32,.88) !important;
}
.site-footer,
.elementor-location-footer {
    background: rgba(10,2,20,.92) !important;
}
.entry-content,
.entry-title,
.page-title {
    color: #f0e8ff !important;
}
.entry-content a {
    color: #C9A84C !important;
}
</style>' . "\n";
}
