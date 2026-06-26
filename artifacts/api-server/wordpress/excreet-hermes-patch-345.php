<?php
/**
 * Plugin Name: Excreet Patch 345 — Shop Page Redesign
 * Description: Replaces the WooCommerce /shop/ grid with a single large white-panel
 *              layout. All products displayed in rows of 4, evenly spaced, each card
 *              showing product image, name, size/count spec, price, and a Shop Partner
 *              affiliate link.
 * Version: 3.4.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Size/count specs keyed by product ID ── */
add_action( 'template_redirect', 'excreet_345_shop_override', 1 );

function excreet_345_shop_override() {
    if ( ! function_exists( 'is_shop' ) || ! is_shop() ) return;

    /* Size/count specs not stored in WC — mapped by product ID */
    $specs = [
        890 => '30-day supply · 32 ounces',
        899 => '250 g powder',
        902 => '1 fl oz liquid',
        904 => '16.9 fl oz bottle',
        905 => '12 fl oz bottle',
        906 => '54 fl oz jar',
        907 => '16 fl oz bottle',
        908 => '90 capsules',
        909 => '200 tablets · 500 mg',
        910 => '240 capsules · 500 mg',
        953 => '150 strips · 14 parameters',
    ];

    /* Fetch all published products */
    $raw = wc_get_products( [
        'status'  => 'publish',
        'limit'   => -1,
        'orderby' => 'menu_order',
        'order'   => 'ASC',
    ] );

    /* Exclude virtual / non-visible products (e.g. dashboard placeholder) */
    $products = array_filter( $raw, fn( $p ) => $p->is_visible() && $p->get_price() !== '' );
    $products = array_values( $products );

    /* Put Excreet Signature Formula first */
    usort( $products, function ( $a, $b ) {
        if ( $a->get_id() === 890 ) return -1;
        if ( $b->get_id() === 890 ) return  1;
        return strcmp( $a->get_name(), $b->get_name() );
    } );

    $month  = date( 'm' );
    $bg_url = 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg';

    status_header( 200 );
    header( 'Content-Type: text/html; charset=utf-8' );
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Excreet Store — <?php bloginfo( 'name' ); ?></title>
<?php wp_head(); ?>
<style>
/* ── Reset & base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    background: url('<?php echo esc_url( $bg_url ); ?>') center center / cover no-repeat fixed;
    min-height: 100vh;
    color: #1a1a2e;
}

/* ── Sticky nav bar ── */
.ex345-nav {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(86, 7, 94, 0.96);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 32px;
    gap: 16px;
}
.ex345-nav-brand {
    font-size: 20px;
    font-weight: 700;
    color: #F5D97A;
    text-decoration: none;
    letter-spacing: 0.03em;
}
.ex345-nav-links { display: flex; gap: 20px; align-items: center; }
.ex345-nav-links a {
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    opacity: 0.88;
    transition: opacity 0.15s;
}
.ex345-nav-links a:hover { opacity: 1; }

/* ── Page wrapper ── */
.ex345-page {
    max-width: 1320px;
    margin: 0 auto;
    padding: 40px 24px 64px;
}

/* ── Page header ── */
.ex345-header {
    text-align: center;
    margin-bottom: 32px;
    background: rgba(10, 0, 20, 0.72);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    border-radius: 16px;
    padding: 28px 32px 24px;
    border: 1px solid rgba(86,7,94,0.4);
}
.ex345-header h1 {
    font-size: clamp(26px, 3.4vw, 44px);
    font-weight: 900;
    color: #ffffff;
    -webkit-text-fill-color: #ffffff;
    -webkit-text-stroke: 0;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    text-shadow: none;
}
.ex345-header h1 span { color: #ffffff; -webkit-text-fill-color: #ffffff; }
.ex345-disclaimer {
    margin-top: 10px;
    font-size: 12px;
    color: rgba(255,255,255,0.75);
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
    text-shadow: 0 1px 4px rgba(0,0,0,0.4);
}

/* ── White panel ── */
.ex345-panel {
    background: #fff;
    border-radius: 20px;
    padding: 40px 36px 48px;
    box-shadow: 0 8px 48px rgba(0,0,0,0.18);
}

/* ── Product grid ── */
.ex345-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 28px;
}

/* ── Product card ── */
.ex345-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    border: 1.5px solid #f0eaf7;
    border-radius: 14px;
    padding: 24px 16px 20px;
    background: #fdfbff;
    transition: box-shadow 0.2s, transform 0.15s;
    text-decoration: none;
    color: inherit;
    position: relative;
    overflow: hidden;
}
.ex345-card:hover {
    box-shadow: 0 6px 28px rgba(86,7,94,0.14);
    transform: translateY(-3px);
}

/* Own-brand badge */
.ex345-card.ex345-own-brand {
    border-color: #C8930A;
    background: linear-gradient(160deg, #fffdf5 0%, #fff8e0 100%);
}
.ex345-own-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: #C8930A;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 20px;
}

/* Partner badge */
.ex345-partner-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: rgba(86,7,94,0.12);
    color: #56075E;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 20px;
}

/* Product image */
.ex345-img-wrap {
    width: 100%;
    height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    margin-top: 4px;
}
.ex345-img-wrap img {
    max-height: 160px;
    max-width: 100%;
    width: auto;
    object-fit: contain;
}
.ex345-img-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f5eaff, #e8d5fa);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

/* Product name */
.ex345-name {
    font-size: 13px;
    font-weight: 600;
    color: #1a1a2e;
    text-align: center;
    line-height: 1.4;
    margin-bottom: 6px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ID / SKU row */
.ex345-id {
    font-size: 10px;
    color: #999;
    letter-spacing: 0.04em;
    margin-bottom: 8px;
    text-align: center;
}

/* Spec pill */
.ex345-spec {
    background: #f0ebf9;
    color: #56075E;
    font-size: 10.5px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    margin-bottom: 12px;
    text-align: center;
    letter-spacing: 0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

/* Spacer pushes price/CTA to bottom */
.ex345-spacer { flex: 1; }

/* Price */
.ex345-price {
    font-size: 22px;
    font-weight: 700;
    color: #C8930A;
    margin-bottom: 14px;
    letter-spacing: -0.01em;
}

/* CTA button */
.ex345-btn {
    display: block;
    width: 100%;
    text-align: center;
    padding: 10px 16px;
    background: #56075E;
    color: #fff;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    letter-spacing: 0.03em;
    transition: background 0.18s, transform 0.12s;
    white-space: nowrap;
}
.ex345-btn:hover { background: #7a0a84; transform: translateY(-1px); }
.ex345-card.ex345-own-brand .ex345-btn {
    background: #C8930A;
}
.ex345-card.ex345-own-brand .ex345-btn:hover { background: #a97509; }

/* ── Panel footer note ── */
.ex345-footer-note {
    text-align: center;
    margin-top: 32px;
    font-size: 11px;
    color: #aaa;
    line-height: 1.6;
}
.ex345-footer-note a { color: #56075E; }

/* ── Responsive ── */
@media (max-width: 1100px) {
    .ex345-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .ex345-panel { padding: 24px 16px 32px; }
    .ex345-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .ex345-nav { padding: 10px 16px; }
}
@media (max-width: 480px) {
    .ex345-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<nav class="ex345-nav">
    <a class="ex345-nav-brand" href="/">Excreet</a>
    <div class="ex345-nav-links">
        <a href="/welcome-member/">Dashboard</a>
        <a href="/healing-command-center/">Body Check</a>
        <a href="/membership-account/">My Account</a>
    </div>
</nav>

<div class="ex345-page">

    <div class="ex345-header">
        <h1>Excreet Store — <span>Trusted by Excreet</span></h1>
        <p class="ex345-disclaimer">
            These products are genuinely recommended by the Excreet team as part of our cellular health protocol.
            As an Amazon Associate, Excreet earns a small commission at no extra cost to you.
        </p>
    </div>

    <div class="ex345-panel">

        <div class="ex345-grid">
        <?php foreach ( $products as $product ) :
            $id       = $product->get_id();
            $name     = $product->get_name();
            $price    = wc_price( $product->get_price() );
            $link     = get_permalink( $id );
            $sku      = $product->get_sku();
            $img_id   = $product->get_image_id();
            $img_url  = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';
            $spec     = $specs[ $id ] ?? '';
            $is_own   = ( $id === 890 );
            $btn_text = $is_own ? 'Add to Cart &rarr;' : 'Shop Partner &rarr;';
            $id_label = $sku ? 'SKU: ' . esc_html( $sku ) : 'ID: ' . $id;
        ?>
        <a class="ex345-card<?php echo $is_own ? ' ex345-own-brand' : ''; ?>"
           href="<?php echo esc_url( $link ); ?>">

            <?php if ( $is_own ) : ?>
                <span class="ex345-own-badge">Excreet Formula</span>
            <?php else : ?>
                <span class="ex345-partner-badge">Partner Pick</span>
            <?php endif; ?>

            <div class="ex345-img-wrap">
                <?php if ( $img_url ) : ?>
                    <img src="<?php echo esc_url( $img_url ); ?>"
                         alt="<?php echo esc_attr( $name ); ?>"
                         loading="lazy">
                <?php else : ?>
                    <div class="ex345-img-placeholder">🌿</div>
                <?php endif; ?>
            </div>

            <div class="ex345-name"><?php echo esc_html( $name ); ?></div>
            <div class="ex345-id"><?php echo esc_html( $id_label ); ?></div>

            <?php if ( $spec ) : ?>
                <div class="ex345-spec"><?php echo esc_html( $spec ); ?></div>
            <?php endif; ?>

            <div class="ex345-spacer"></div>

            <div class="ex345-price"><?php echo $price; ?></div>
            <span class="ex345-btn"><?php echo $btn_text; ?></span>

        </a>
        <?php endforeach; ?>
        </div>

        <div class="ex345-footer-note">
            All prices shown are approximate and may vary on the partner site. &nbsp;·&nbsp;
            <a href="/terms/">Terms</a> &nbsp;·&nbsp;
            <a href="/privacy-policy/">Privacy</a>
        </div>

    </div><!-- .ex345-panel -->

</div><!-- .ex345-page -->

<?php wp_footer(); ?>
</body>
</html>
<?php
    exit;
}
