<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.1.6
 * Description: Store Activation — disables WooCommerce "Store coming soon" mode,
 *              ensures the shop page exists and is wired to WooCommerce settings,
 *              and verifies the Excreet Formula product is published and visible.
 *
 * Version: 3.1.6
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── 1. Disable WooCommerce "coming soon" mode immediately ── */
add_action( 'init', 'excreet_316_disable_coming_soon', 1 );

function excreet_316_disable_coming_soon(): void {
    if ( get_option( 'woocommerce_coming_soon' ) !== 'no' ) {
        update_option( 'woocommerce_coming_soon', 'no' );
    }
}

/* ── 2. One-time: ensure shop page + WooCommerce page options are set ── */
add_action( 'init', 'excreet_316_ensure_shop_setup', 22 );

function excreet_316_ensure_shop_setup(): void {
    if ( ! function_exists( 'wc_get_page_id' ) ) { return; }

    /* Ensure /shop/ page exists */
    $shop_id = wc_get_page_id( 'shop' );

    if ( ! $shop_id || $shop_id < 1 || get_post_status( $shop_id ) !== 'publish' ) {
        $existing = get_page_by_path( 'shop', OBJECT, 'page' );

        if ( $existing ) {
            $shop_id = $existing->ID;
            if ( get_post_status( $shop_id ) !== 'publish' ) {
                wp_publish_post( $shop_id );
            }
        } else {
            $shop_id = wp_insert_post( [
                'post_title'   => 'Shop',
                'post_name'    => 'shop',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '',
            ] );
        }

        update_option( 'woocommerce_shop_page_id', $shop_id );
    }

    /* Ensure cart, checkout, my-account pages exist */
    $pages = [
        'cart'       => [ 'title' => 'Cart',       'slug' => 'cart' ],
        'checkout'   => [ 'title' => 'Checkout',   'slug' => 'checkout' ],
        'myaccount'  => [ 'title' => 'My Account', 'slug' => 'my-account' ],
    ];

    foreach ( $pages as $key => $data ) {
        $page_id = wc_get_page_id( $key );
        if ( ! $page_id || $page_id < 1 ) {
            $existing = get_page_by_path( $data['slug'], OBJECT, 'page' );
            if ( $existing ) {
                update_option( 'woocommerce_' . $key . '_page_id', $existing->ID );
            } else {
                $new_id = wp_insert_post( [
                    'post_title'  => $data['title'],
                    'post_name'   => $data['slug'],
                    'post_status' => 'publish',
                    'post_type'   => 'page',
                    'post_content'=> '',
                ] );
                if ( ! is_wp_error( $new_id ) ) {
                    update_option( 'woocommerce_' . $key . '_page_id', $new_id );
                }
            }
        }
    }

    /* Flush rewrite rules once */
    if ( ! get_option( 'excreet_316_rewrites_flushed' ) ) {
        flush_rewrite_rules( false );
        update_option( 'excreet_316_rewrites_flushed', '1' );
    }
}

/* ── 3. Ensure Excreet Formula product is published + visible ── */
add_action( 'init', 'excreet_316_ensure_formula_published', 23 );

function excreet_316_ensure_formula_published(): void {
    if ( ! function_exists( 'wc_get_product' ) ) { return; }

    $post = get_page_by_path( 'excreet-signature-formula', OBJECT, 'product' );
    if ( ! $post ) { return; }

    if ( $post->post_status !== 'publish' ) {
        wp_publish_post( $post->ID );
    }

    /* Ensure catalog visibility */
    $product = wc_get_product( $post->ID );
    if ( $product && $product->get_catalog_visibility() === 'hidden' ) {
        $product->set_catalog_visibility( 'visible' );
        $product->save();
    }
}

/* ── 4. Admin notice confirming store is now open ── */
add_action( 'admin_notices', 'excreet_316_store_open_notice' );

function excreet_316_store_open_notice(): void {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
    if ( get_option( 'woocommerce_coming_soon' ) !== 'no' ) { return; }
    $shop_url = home_url( '/shop/' );
    ?>
    <div class="notice notice-success is-dismissible" style="border-left:4px solid #C9A84C;">
        <p>
            <strong>Excreet Store is now open.</strong>
            WooCommerce coming-soon mode disabled.
            <a href="<?php echo esc_url( $shop_url ); ?>" target="_blank">View your shop &rarr;</a>
        </p>
    </div>
    <?php
}
