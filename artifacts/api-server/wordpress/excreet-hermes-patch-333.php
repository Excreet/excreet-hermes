<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.3.3
 * Description: Admin access guard — ensures users with manage_options capability
 *              are never redirected away from wp-admin by PMPro or any other plugin.
 *
 *   A — admin_init at priority -999: if current user can manage_options, remove
 *       PMPro's subscriber-redirect action before it fires.
 *
 *   B — login_redirect filter: after wp-login.php authentication, send admins
 *       directly to wp-admin/admin.php?page=pmpro-paymentsettings (or wp-admin/
 *       if redirect_to is empty) instead of front-end pages.
 *
 * Version: 3.3.3
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── A — Strip PMPro's admin redirect for real admins ───────────────────────── */
add_action( 'admin_init', 'excreet_333_protect_admin', -999 );
function excreet_333_protect_admin(): void {
    if ( ! is_user_logged_in() ) { return; }
    if ( ! current_user_can( 'manage_options' ) ) { return; }

    // Remove PMPro's subscriber-from-wp-admin redirect hooks (all known priorities)
    remove_action( 'admin_init', 'pmpro_members_only_admin' );
    remove_action( 'admin_init', 'pmpro_members_only_admin', 1 );
    remove_action( 'admin_init', 'pmpro_members_only_admin', 10 );
    remove_action( 'admin_init', 'pmpro_members_only_admin', 99 );
}

/* ── B — After wp-login.php auth, send admins straight to wp-admin ──────────── */
add_filter( 'login_redirect', 'excreet_333_admin_login_redirect', 99, 3 );
function excreet_333_admin_login_redirect( string $redirect_to, string $requested, $user ): string {
    if ( is_wp_error( $user ) ) { return $redirect_to; }
    if ( ! ( $user instanceof WP_User ) ) { return $redirect_to; }
    if ( ! $user->has_cap( 'manage_options' ) ) { return $redirect_to; }

    // If they were trying to reach a wp-admin page, honour that URL
    if ( strpos( $requested, 'wp-admin' ) !== false && strpos( $requested, 'wp-login' ) === false ) {
        return $requested;
    }

    // Otherwise land on the dashboard
    return admin_url();
}
