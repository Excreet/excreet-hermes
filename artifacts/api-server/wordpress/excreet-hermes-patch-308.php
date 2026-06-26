<?php
/**
 * Plugin Name: Excreet Patch 308 — Legacy URL Rewrite
 * Description: Output-buffer rewrite for any page still containing the deleted
 *              MemberPress registration URL /register/excreet-gut-guide-membership/.
 *              Replaces it with the PMPro Starter checkout URL sitewide so stale
 *              Elementor / Gutenberg content never sends visitors to a 404.
 *              Covers: /know-the-signals/ "Become a Member" button and any other
 *              page that was not individually patched.
 * Version: 3.0.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'template_redirect', 'excreet_308_start_rewrite_buffer', 1 );

function excreet_308_start_rewrite_buffer(): void {
    // Skip admin, REST, feeds, cron — only rewrite front-end page output
    if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    ob_start( 'excreet_308_rewrite_buffer' );
}

function excreet_308_rewrite_buffer( string $buffer ): string {
    // Map of old → new strings (URLs and display text)
    $replacements = [
        // Legacy MemberPress URLs
        '/register/excreet-gut-guide-membership/'  => '/membership-checkout/?level=1',
        '/membership-payment-page/'                => '/membership-checkout/?level=1',
        '/my-account/'                             => '/membership-account/',
        // "Snapshot" → "Check" rename — catches any Elementor/Gutenberg stored content
        // not covered by individual patch overrides
        "Today&#8217;s Body Snapshot"              => "Today&#8217;s Body Check",
        "Today's Body Snapshot"                    => "Today's Body Check",
        "Today\u2019s Body Snapshot"               => "Today\u2019s Body Check",
        '24/7 Body Snapshot'                       => 'Body Check',
        '24/7 Body Check'                          => 'Body Check',
        'Body Snapshot'                            => 'Body Check',
        'Quick Snapshot'                           => 'Quick Body Check',
        'Quick Check'                              => 'Quick Body Check',
        'Full Snapshot'                            => 'Full Body Check',
        'Full Check'                               => 'Full Body Check',
        'Gut Snapshot'                             => 'Body Check',
    ];

    foreach ( $replacements as $old => $new ) {
        if ( strpos( $buffer, $old ) !== false ) {
            $buffer = str_replace( $old, $new, $buffer );
        }
    }

    return $buffer;
}
