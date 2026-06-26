<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.4.7
 * Description: Member Intake Form — delegates all rendering to page-member-intake-form.php in theme. This patch only disables Elementor on that page so the template is used cleanly.
 * Version: 3.4.7
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Disable Elementor rendering on the intake form page so WordPress
   falls through to the theme's page-member-intake-form.php template. */
add_filter( 'elementor/page/should_use_elementor', function ( $should, $post_id ) {
    $page = get_page_by_path( 'member-intake-form' );
    if ( $page && (int) $page->ID === (int) $post_id ) {
        return false;
    }
    return $should;
}, 10, 2 );

/* Hide admin bar on the intake form page. */
add_action( 'wp', function () {
    if ( is_page( 'member-intake-form' ) ) {
        show_admin_bar( false );
    }
} );
