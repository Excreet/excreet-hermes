<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.2.0
 * Description: Bulk adds 9 Amazon affiliate products from owner-provided ASINs.
 *              Also retires the 6 generic placeholder products (draft/unpublish).
 *              Associates all links with tag=excreetshop06-20.
 * Version: 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX320_DONE_OPTION', 'excreet_320_bulk_products_done' );
define( 'EX320_TAG', 'excreetshop06-20' );

add_action( 'init', 'excreet_320_bulk_add_products', 25 );

function excreet_320_bulk_add_products(): void {
    if ( get_option( EX320_DONE_OPTION ) ) { return; }
    if ( ! function_exists( 'wc_get_product' ) ) { return; }

    /* ── Retire the 6 generic REPLACE_ASIN placeholder products ── */
    $old_slugs = [
        'amazon-magnesium-glycinate',
        'amazon-digestive-enzymes',
        'amazon-probiotic-50b',
        'amazon-zinc-bisglycinate',
        'amazon-turmeric-curcumin',
        'amazon-l-glutamine',
    ];
    foreach ( $old_slugs as $slug ) {
        $post = get_page_by_path( $slug, OBJECT, 'product' );
        if ( $post ) {
            wp_update_post( [ 'ID' => $post->ID, 'post_status' => 'trash' ] );
        }
    }

    /* ── New products ── */
    $products = [
        [
            'slug'    => 'amazon-b0859ls4r4',
            'title'   => 'Amazon Partner Pick — B0859LS4R4',
            'excerpt' => 'Recommended partner product. Click to view details and pricing on Amazon.',
            'content' => '<p>A trusted partner pick selected by the Excreet team. Click below to view full product details, ingredients, and current pricing on Amazon.</p>',
            'asin'    => 'B0859LS4R4',
            'price'   => '0.00',
            'cats'    => [ 'partner-picks' ],
            'note'    => 'NEEDS TITLE+IMAGE — owner to confirm product name',
        ],
        [
            'slug'    => 'amazon-heritage-atomidine-iodine',
            'title'   => 'Heritage Store Atomidine — Nascent Iodine Supplement',
            'excerpt' => 'Traditionally used nascent iodine for thyroid and metabolic support. Heritage Store formula.',
            'content' => '<p>Heritage Store Atomidine is a nascent iodine supplement traditionally used to support thyroid function and metabolic balance. Iodine is essential for cellular energy production and hormonal signalling — often depleted in modern diets.</p><ul><li>Nascent (atomic) iodine form — high bioavailability</li><li>1 fl oz liquid — easy to dose</li><li>Supports thyroid, metabolism, and cellular detox pathways</li></ul>',
            'asin'    => 'B00CQ7S1QK',
            'price'   => '17.99',
            'cats'    => [ 'partner-picks', 'minerals-enzymes', 'cellular-health' ],
        ],
        [
            'slug'    => 'amazon-b0dtjc6d72',
            'title'   => 'Amazon Partner Pick — B0DTJC6D72',
            'excerpt' => 'Recommended partner product. Click to view details and pricing on Amazon.',
            'content' => '<p>A trusted partner pick selected by the Excreet team. Click below to view full product details, ingredients, and current pricing on Amazon.</p>',
            'asin'    => 'B0DTJC6D72',
            'price'   => '0.00',
            'cats'    => [ 'partner-picks' ],
            'note'    => 'NEEDS TITLE+IMAGE — owner to confirm product name',
        ],
        [
            'slug'    => 'amazon-california-olive-ranch-olive-oil',
            'title'   => 'California Olive Ranch — Everyday Extra Virgin Olive Oil',
            'excerpt' => 'Cold-pressed California EVOO. Rich in oleocanthal, polyphenols, and anti-inflammatory oleic acid.',
            'content' => '<p>California Olive Ranch Everyday Extra Virgin Olive Oil is cold-pressed from California-grown olives and certified by the California Olive Oil Council. Rich in monounsaturated fats, polyphenols, and oleocanthal — a powerful natural anti-inflammatory that supports gut lining integrity and cellular health.</p><ul><li>COOC certified — verified freshness and quality</li><li>Non-GMO, no additives</li><li>Ideal for cooking, dressings, and daily protocol use</li></ul>',
            'asin'    => 'B00CO1YXL0',
            'price'   => '14.99',
            'cats'    => [ 'partner-picks', 'cellular-health' ],
        ],
        [
            'slug'    => 'amazon-barleans-organic-flax-oil',
            'title'   => "Barlean's Organic Flax Oil — Fresh-Pressed Omega-3",
            'excerpt' => "Certified organic, fresh-pressed flaxseed oil. One of the richest plant sources of ALA Omega-3.",
            'content' => "<p>Barlean's Organic Flax Oil is cold-pressed from certified organic flaxseeds and bottled fresh for maximum potency. Flax oil is one of the richest plant-based sources of ALA Omega-3 fatty acids, supporting cardiovascular health, inflammation reduction, and cellular membrane integrity.</p><ul><li>Certified organic and non-GMO</li><li>Fresh-pressed — refrigerated and nitrogen-flushed</li><li>16 oz liquid — easy daily use</li></ul>",
            'asin'    => 'B00N55ASK4',
            'price'   => '19.99',
            'cats'    => [ 'partner-picks', 'cellular-health' ],
        ],
        [
            'slug'    => 'amazon-viva-naturals-coconut-oil',
            'title'   => 'Viva Naturals Organic Virgin Coconut Oil',
            'excerpt' => 'Cold-pressed, unrefined organic coconut oil — rich in MCTs for energy and gut health.',
            'content' => '<p>Viva Naturals Organic Virgin Coconut Oil is cold-pressed from fresh, non-GMO coconuts with no refining, bleaching, or deodorizing. Rich in medium-chain triglycerides (MCTs) that support rapid energy production, gut microbiome health, and anti-microbial defence.</p><ul><li>USDA Certified Organic</li><li>Unrefined, virgin — retains full nutrient profile</li><li>54 oz — versatile for cooking, blending, or topical use</li></ul>',
            'asin'    => 'B00DS842HS',
            'price'   => '22.99',
            'cats'    => [ 'partner-picks', 'cellular-health' ],
        ],
        [
            'slug'    => 'amazon-barleans-flaxseed-omega-369',
            'title'   => "Barlean's Flaxseed Omega 3-6-9 — Pomegranate Blueberry",
            'excerpt' => "Vegan Omega 3-6-9 from flaxseed in a delicious pomegranate blueberry flavour. No fishy aftertaste.",
            'content' => "<p>Barlean's Total Omega Vegan Swirl delivers a full spectrum of essential fatty acids — Omega 3, 6, and 9 — from organic flaxseed in a delicious pomegranate blueberry flavour. Ideal for those who want comprehensive EFA support without fish-based products.</p><ul><li>Vegan — plant-based Omega 3-6-9</li><li>No fishy aftertaste — great taste compliance</li><li>16 oz liquid — easy to mix into smoothies or take direct</li></ul>",
            'asin'    => 'B002VLZ8DU',
            'price'   => '24.99',
            'cats'    => [ 'partner-picks', 'cellular-health' ],
        ],
        [
            'slug'    => 'amazon-enzymedica-digest-basic',
            'title'   => 'Enzymedica Digest Basic — Essential Enzyme Formula',
            'excerpt' => 'Broad-spectrum digestive enzymes to break down carbs, fats, proteins, and dairy efficiently.',
            'content' => '<p>Enzymedica Digest Basic provides a potent blend of essential digestive enzymes — amylase, protease, lipase, cellulase, and lactase — to support complete macronutrient breakdown. Reduces digestive burden, bloating, and discomfort after meals, and optimises nutrient absorption for your cellular health protocol.</p><ul><li>Thera-blend™ enzymes — active across a wide pH range</li><li>30 capsules per bottle</li><li>Non-GMO, gluten-free, vegan</li></ul>',
            'asin'    => 'B001W44AV8',
            'price'   => '18.99',
            'cats'    => [ 'partner-picks', 'gut-support' ],
        ],
        [
            'slug'    => 'amazon-enduracin-niacin-extended-release',
            'title'   => 'ENDUR-ACIN Niacin — Low-Flush Extended-Release 500mg',
            'excerpt' => 'Extended-release Niacin (Vitamin B3) for cardiovascular and cellular energy support with minimal flushing.',
            'content' => '<p>ENDUR-ACIN delivers 500mg of Niacin (Vitamin B3) in a patented wax-matrix extended-release formula that minimises the uncomfortable flushing reaction associated with immediate-release niacin. Supports healthy cholesterol balance, NAD+ production, and cellular energy metabolism.</p><ul><li>Extended-release wax matrix — gradual delivery over 6–8 hours</li><li>Low-flush formula — significantly reduced flushing vs immediate-release</li><li>150 tablets per bottle</li></ul>',
            'asin'    => 'B014831ICS',
            'price'   => '21.99',
            'cats'    => [ 'partner-picks', 'cellular-health', 'minerals-enzymes' ],
        ],
        [
            'slug'    => 'amazon-nutricost-niacin-500mg',
            'title'   => 'Nutricost Niacin (Vitamin B3) — 500mg, 240 Capsules',
            'excerpt' => 'High-quality Niacin B3 at an excellent value. 240 capsules, non-GMO, gluten-free.',
            'content' => '<p>Nutricost Niacin provides 500mg of Vitamin B3 per capsule — a foundational nutrient for NAD+ biosynthesis, cellular energy production, DNA repair, and cardiovascular health. A cost-effective, high-quality option for daily niacin supplementation.</p><ul><li>500mg per capsule — 240 capsules per bottle</li><li>Non-GMO, gluten-free, third-party tested</li><li>Made in the USA in an FDA-registered facility</li></ul>',
            'asin'    => 'B01IIDB6JE',
            'price'   => '14.99',
            'cats'    => [ 'partner-picks', 'cellular-health', 'minerals-enzymes' ],
        ],
    ];

    foreach ( $products as $p ) {
        if ( get_page_by_path( $p['slug'], OBJECT, 'product' ) ) { continue; }

        $aff_url = 'https://www.amazon.com/dp/' . $p['asin'] . '?tag=' . EX320_TAG;

        $post_id = wp_insert_post( [
            'post_title'   => $p['title'],
            'post_name'    => $p['slug'],
            'post_status'  => 'publish',
            'post_type'    => 'product',
            'post_content' => $p['content'],
            'post_excerpt' => $p['excerpt'],
        ] );

        if ( is_wp_error( $post_id ) ) { continue; }

        wp_set_object_terms( $post_id, $p['cats'], 'product_cat' );
        wp_set_object_terms( $post_id, 'external', 'product_type' );

        update_post_meta( $post_id, '_price',         $p['price'] );
        update_post_meta( $post_id, '_regular_price', $p['price'] );
        update_post_meta( $post_id, '_product_url',   $aff_url );
        update_post_meta( $post_id, '_button_text',   'View on Amazon →' );
        update_post_meta( $post_id, '_stock_status',  'instock' );
        update_post_meta( $post_id, '_visibility',    'visible' );
        update_post_meta( $post_id, '_ex314_asin',    $p['asin'] );
    }

    update_option( EX320_DONE_OPTION, '1' );
}
