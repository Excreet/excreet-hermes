<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.1.4
 * Description: Store Population — auto-creates WooCommerce product categories and
 *              seed products on first run. Adds two sections to /shop/:
 *              (1) Excreet Signature Formula (own product, WooCommerce Simple)
 *              (2) Partner Picks (Amazon affiliate External products, tag excreetshop06-20)
 *              Admin notice guides owner to fill in real images + affiliate URLs.
 *              Safe to re-run — uses get_page_by_path / wc_get_product checks.
 *
 * Version: 3.1.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  CONSTANTS                                                                   */
/* ═══════════════════════════════════════════════════════════════════════════ */

define( 'EX314_ASSOCIATE_ID', 'excreetshop06-20' );
define( 'EX314_SETUP_OPTION', 'excreet_314_store_seeded_v1' );

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  A — RUN SETUP ONCE ON INIT                                                  */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'init', 'excreet_314_maybe_seed_store', 20 );

function excreet_314_maybe_seed_store(): void {
    if ( get_option( EX314_SETUP_OPTION ) ) { return; }
    if ( ! function_exists( 'wc_get_product' ) ) { return; }

    excreet_314_ensure_categories();
    excreet_314_seed_own_product();
    excreet_314_seed_affiliate_products();

    update_option( EX314_SETUP_OPTION, '1' );
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  B — CATEGORIES                                                              */
/* ═══════════════════════════════════════════════════════════════════════════ */

function excreet_314_ensure_categories(): void {
    $cats = [
        'excreet-formula'   => 'Excreet Formula',
        'partner-picks'     => 'Partner Picks',
        'gut-support'       => 'Gut Support',
        'cellular-health'   => 'Cellular Health',
        'minerals-enzymes'  => 'Minerals & Enzymes',
    ];

    foreach ( $cats as $slug => $name ) {
        if ( ! term_exists( $slug, 'product_cat' ) ) {
            wp_insert_term( $name, 'product_cat', [ 'slug' => $slug ] );
        }
    }
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  C — OWN PRODUCT (Excreet Signature Formula)                                 */
/* ═══════════════════════════════════════════════════════════════════════════ */

function excreet_314_seed_own_product(): void {
    if ( excreet_314_product_exists( 'excreet-signature-formula' ) ) { return; }

    $post_id = wp_insert_post( [
        'post_title'   => 'Excreet Signature Formula',
        'post_name'    => 'excreet-signature-formula',
        'post_status'  => 'publish',
        'post_type'    => 'product',
        'post_content' => '<p>The Excreet Signature Formula is a precision-crafted cellular health supplement designed to support gut motility, reduce systemic inflammation, and restore your body\'s natural signalling rhythms.</p><p>Formulated with bioavailable minerals, digestive enzymes, and botanicals — each batch third-party tested for purity.</p><ul><li>30-day supply per bottle</li><li>No fillers, no binders, no artificial colours</li><li>Ships within 3 business days</li></ul>',
        'post_excerpt' => 'Precision cellular health formula — minerals, enzymes, and botanicals. 30-day supply.',
    ] );

    if ( is_wp_error( $post_id ) ) { return; }

    wp_set_object_terms( $post_id, [ 'excreet-formula', 'cellular-health' ], 'product_cat' );
    wp_set_object_terms( $post_id, 'simple', 'product_type' );

    update_post_meta( $post_id, '_price',         '49.00' );
    update_post_meta( $post_id, '_regular_price', '49.00' );
    update_post_meta( $post_id, '_sku',           'EX-FORMULA-001' );
    update_post_meta( $post_id, '_manage_stock',  'no' );
    update_post_meta( $post_id, '_stock_status',  'instock' );
    update_post_meta( $post_id, '_visibility',    'visible' );
    update_post_meta( $post_id, '_virtual',       'no' );
    update_post_meta( $post_id, '_featured',      'yes' );

    update_post_meta( $post_id, '_ex314_needs_image', '1' );
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  D — AMAZON AFFILIATE PRODUCTS (seed placeholders)                           */
/* ═══════════════════════════════════════════════════════════════════════════ */

function excreet_314_seed_affiliate_products(): void {
    $products = [
        [
            'title'    => 'Magnesium Glycinate — High Absorption',
            'slug'     => 'amazon-magnesium-glycinate',
            'excerpt'  => 'Third-party tested magnesium glycinate for deep cellular absorption and muscle recovery.',
            'content'  => '<p>Magnesium is involved in over 300 enzymatic reactions. This highly bioavailable form supports gut motility, sleep quality, and cellular energy production. A foundational mineral for any healing protocol.</p>',
            'price'    => '0.00',
            'cats'     => [ 'partner-picks', 'minerals-enzymes' ],
            'asin'     => 'REPLACE_ASIN',
        ],
        [
            'title'    => 'Digestive Enzyme Complex — Full Spectrum',
            'slug'     => 'amazon-digestive-enzymes',
            'excerpt'  => 'Broad-spectrum digestive enzymes to support nutrient absorption and reduce bloating.',
            'content'  => '<p>A comprehensive enzyme blend including amylase, lipase, protease, cellulase, and lactase. Supports complete macronutrient breakdown and reduces digestive burden — ideal alongside the Excreet Formula.</p>',
            'price'    => '0.00',
            'cats'     => [ 'partner-picks', 'gut-support' ],
            'asin'     => 'REPLACE_ASIN',
        ],
        [
            'title'    => 'Probiotic 50 Billion CFU — Multi-Strain',
            'slug'     => 'amazon-probiotic-50b',
            'excerpt'  => 'High-potency multi-strain probiotic to restore gut flora and support immune function.',
            'content'  => '<p>50 billion CFU across 10 clinically studied strains. Shelf-stable, delayed-release capsules ensure live delivery to the gut. Supports microbiome diversity and systemic inflammation reduction.</p>',
            'price'    => '0.00',
            'cats'     => [ 'partner-picks', 'gut-support' ],
            'asin'     => 'REPLACE_ASIN',
        ],
        [
            'title'    => 'Zinc Bisglycinate — Immune & Cellular Repair',
            'slug'     => 'amazon-zinc-bisglycinate',
            'excerpt'  => 'Gentle, highly bioavailable zinc to support immune defence and cellular repair cycles.',
            'content'  => '<p>Zinc bisglycinate is the most gentle and absorbable form of zinc — critical for DNA synthesis, immune function, and the cellular repair processes central to the Excreet protocol.</p>',
            'price'    => '0.00',
            'cats'     => [ 'partner-picks', 'minerals-enzymes' ],
            'asin'     => 'REPLACE_ASIN',
        ],
        [
            'title'    => 'Organic Turmeric with Black Pepper Extract',
            'slug'     => 'amazon-turmeric-curcumin',
            'excerpt'  => 'Certified organic curcumin with piperine for maximum anti-inflammatory support.',
            'content'  => '<p>Curcumin with black pepper extract (BioPerine) provides up to 20x enhanced absorption. A cornerstone anti-inflammatory botanical for systemic healing and gut lining repair.</p>',
            'price'    => '0.00',
            'cats'     => [ 'partner-picks', 'cellular-health' ],
            'asin'     => 'REPLACE_ASIN',
        ],
        [
            'title'    => 'L-Glutamine Powder — Gut Lining Support',
            'slug'     => 'amazon-l-glutamine',
            'excerpt'  => 'Pharmaceutical grade L-Glutamine to rebuild and maintain the gut mucosal lining.',
            'content'  => '<p>L-Glutamine is the primary fuel source for intestinal cells. This unflavoured pharmaceutical-grade powder supports tight junction integrity, reduces intestinal permeability, and accelerates gut lining repair.</p>',
            'price'    => '0.00',
            'cats'     => [ 'partner-picks', 'gut-support' ],
            'asin'     => 'REPLACE_ASIN',
        ],
    ];

    $tag = EX314_ASSOCIATE_ID;

    foreach ( $products as $p ) {
        if ( excreet_314_product_exists( $p['slug'] ) ) { continue; }

        $aff_url = 'https://www.amazon.com/dp/' . $p['asin'] . '?tag=' . $tag;

        $post_id = wp_insert_post( [
            'post_title'   => $p['title'],
            'post_name'    => $p['slug'],
            'post_status'  => 'draft',
            'post_type'    => 'product',
            'post_content' => $p['content'],
            'post_excerpt' => $p['excerpt'],
        ] );

        if ( is_wp_error( $post_id ) ) { continue; }

        wp_set_object_terms( $post_id, $p['cats'], 'product_cat' );
        wp_set_object_terms( $post_id, 'external', 'product_type' );

        update_post_meta( $post_id, '_price',            $p['price'] );
        update_post_meta( $post_id, '_regular_price',    $p['price'] );
        update_post_meta( $post_id, '_product_url',      $aff_url );
        update_post_meta( $post_id, '_button_text',      'View on Amazon →' );
        update_post_meta( $post_id, '_stock_status',     'instock' );
        update_post_meta( $post_id, '_visibility',       'visible' );
        update_post_meta( $post_id, '_ex314_needs_setup','1' );
        update_post_meta( $post_id, '_ex314_asin',       $p['asin'] );
    }
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  E — ADMIN NOTICE: SETUP CHECKLIST                                           */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_notices', 'excreet_314_admin_notice' );

function excreet_314_admin_notice(): void {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }

    $draft_products = excreet_314_get_draft_affiliate_count();
    $needs_image    = excreet_314_get_needs_image_count();

    if ( $draft_products === 0 && $needs_image === 0 ) { return; }

    $shop_url     = admin_url( 'edit.php?post_type=product' );
    $cats_url     = admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' );
    ?>
    <div class="notice notice-warning" style="border-left:4px solid #C9A84C;padding:14px 18px;">
        <p style="margin:0 0 8px;font-weight:700;color:#3D1060;">
            Excreet Store — Setup Checklist (patch-314)
        </p>
        <ul style="margin:0;padding-left:1.2em;line-height:1.9;color:#333;">
            <?php if ( $needs_image > 0 ) : ?>
            <li>
                <strong>Add product image</strong> to <em>Excreet Signature Formula</em>
                — upload your bottle photo in
                <a href="<?php echo esc_url( $shop_url ); ?>">Products</a>.
            </li>
            <?php endif; ?>
            <?php if ( $draft_products > 0 ) : ?>
            <li>
                <strong><?php echo (int) $draft_products; ?> Amazon affiliate products</strong>
                are saved as <em>Drafts</em>.
                For each one: open the product, replace the ASIN in the URL with the real Amazon ASIN,
                set a real price (optional), upload an image, then
                <strong>Publish</strong>.
            </li>
            <li>
                Your Associate ID <code>excreetshop06-20</code> is already appended to every Amazon link.
            </li>
            <li>
                Need help finding ASINs?
                Go to the product on Amazon — the ASIN is in the URL after <code>/dp/</code>.
            </li>
            <?php endif; ?>
            <li>
                <a href="<?php echo esc_url( $cats_url ); ?>">Product categories</a>
                created: Excreet Formula, Partner Picks, Gut Support, Cellular Health, Minerals &amp; Enzymes.
            </li>
        </ul>
        <p style="margin:8px 0 0;font-size:0.82em;color:#777;">
            This notice disappears once all products are published and have images.
        </p>
    </div>
    <?php
}

function excreet_314_get_draft_affiliate_count(): int {
    $q = new WP_Query( [
        'post_type'   => 'product',
        'post_status' => 'draft',
        'meta_key'    => '_ex314_needs_setup',
        'meta_value'  => '1',
        'fields'      => 'ids',
        'numberposts' => -1,
    ] );
    return (int) $q->found_posts;
}

function excreet_314_get_needs_image_count(): int {
    $q = new WP_Query( [
        'post_type'   => 'product',
        'post_status' => 'publish',
        'meta_key'    => '_ex314_needs_image',
        'meta_value'  => '1',
        'fields'      => 'ids',
        'numberposts' => -1,
    ] );
    return (int) $q->found_posts;
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  F — SHOP PAGE: TWO-SECTION LAYOUT (Own Product | Partner Picks)             */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'woocommerce_before_shop_loop', 'excreet_314_section_dividers', 6 );

function excreet_314_section_dividers(): void {
    if ( ! is_shop() ) { return; }
    ?>
    <style id="ex314-section-styles">
    .ex314-section-heading {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin: 2rem 0 1rem;
    }
    .ex314-section-heading span.ex314-label {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #C9A84C;
        white-space: nowrap;
    }
    .ex314-section-heading::after {
        content: '';
        flex: 1;
        height: 1px;
        background: rgba(201,168,76,0.18);
    }
    .ex314-own-product-wrap {
        background: linear-gradient(135deg, rgba(107,47,160,0.2), rgba(61,16,96,0.3));
        border: 1px solid rgba(201,168,76,0.28);
        border-radius: 18px;
        padding: 1.5rem;
        margin-bottom: 2.5rem;
    }
    .ex314-partner-note {
        font-size: 0.78rem;
        color: rgba(240,232,255,0.45);
        margin-bottom: 1rem;
        font-style: italic;
    }
    .ex314-associate-disclosure {
        font-size: 0.72rem;
        color: rgba(240,232,255,0.35);
        border-top: 1px solid rgba(255,255,255,0.06);
        padding-top: 1rem;
        margin-top: 2rem;
        line-height: 1.6;
    }
    </style>
    <?php
}

/* Inject section headings + disclosure around the product loop */
add_action( 'woocommerce_before_shop_loop_item', 'excreet_314_maybe_inject_section', 1 );

function excreet_314_maybe_inject_section(): void {
    if ( ! is_shop() ) { return; }
    global $product, $ex314_section_state;

    if ( ! $product ) { return; }

    $cats      = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'slugs' ] );
    $is_own    = in_array( 'excreet-formula', $cats, true );
    $is_partner = in_array( 'partner-picks', $cats, true );

    if ( $is_own && empty( $ex314_section_state['own'] ) ) {
        $ex314_section_state['own'] = true;
        echo '<div class="ex314-section-heading"><span class="ex314-label">Excreet Signature Formula</span></div>';
        echo '<div class="ex314-own-product-wrap">';
    }

    if ( $is_partner && empty( $ex314_section_state['partner'] ) ) {
        if ( ! empty( $ex314_section_state['own'] ) ) {
            echo '</div>';
        }
        $ex314_section_state['partner'] = true;
        echo '<div class="ex314-section-heading"><span class="ex314-label">Partner Picks — Trusted by Excreet</span></div>';
        echo '<p class="ex314-partner-note">These are products we genuinely recommend. As an Amazon Associate we earn a small commission at no extra cost to you.</p>';
    }
}

add_action( 'woocommerce_after_shop_loop', 'excreet_314_close_sections', 5 );

function excreet_314_close_sections(): void {
    if ( ! is_shop() ) { return; }
    global $ex314_section_state;

    if ( ! empty( $ex314_section_state['own'] ) && empty( $ex314_section_state['partner'] ) ) {
        echo '</div>';
    }

    echo '<p class="ex314-associate-disclosure">Excreet is a participant in the Amazon Services LLC Associates Program, an affiliate advertising program designed to provide a means for sites to earn advertising fees by advertising and linking to Amazon.com. Associate ID: excreetshop06-20.</p>';
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  G — ADMIN: RESET SEED (WP Admin > Tools > Excreet Store Reset)              */
/* ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_menu', 'excreet_314_tools_menu' );

function excreet_314_tools_menu(): void {
    add_submenu_page(
        'tools.php',
        'Excreet Store Reset',
        'Excreet Store Reset',
        'manage_options',
        'excreet-store-reset',
        'excreet_314_tools_page'
    );
}

function excreet_314_tools_page(): void {
    if ( isset( $_POST['excreet_314_reset'] ) && check_admin_referer( 'excreet_314_reset' ) ) {
        delete_option( EX314_SETUP_OPTION );
        echo '<div class="updated"><p>Store seed reset. Reload WP admin to re-run setup.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Excreet Store Reset</h1>
        <p>Use this to re-run the store seed (adds missing categories and products again). Existing products are never deleted.</p>
        <form method="post">
            <?php wp_nonce_field( 'excreet_314_reset' ); ?>
            <input type="hidden" name="excreet_314_reset" value="1">
            <?php submit_button( 'Reset & Re-seed Store' ); ?>
        </form>
    </div>
    <?php
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/*  HELPER                                                                      */
/* ═══════════════════════════════════════════════════════════════════════════ */

function excreet_314_product_exists( string $slug ): bool {
    $post = get_page_by_path( $slug, OBJECT, 'product' );
    return ( $post instanceof WP_Post );
}
