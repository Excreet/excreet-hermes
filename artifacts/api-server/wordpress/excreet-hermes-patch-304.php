<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.0.4
 * Description: Responsive Homepage v3.0.4
 *
 *   A — Responsive Homepage Override
 *       Replaces the two-section Elementor homepage with a single, unified
 *       responsive page that fits one viewport on all devices.
 *       - Desktop: full-bleed landscape composite (logo + phone mockup baked in)
 *       - Tablet/Mobile: portrait composite; headline rendered as HTML
 *       - Login URL updated from /mp-login/ → /login/
 *       - Become a Member URL updated to PMPro checkout (Level 1)
 *       - Properly responsive: mobile, tablet, desktop, large desktop
 *
 *   B — "Body Check" rename
 *       Renames "24/7 Body Snapshot" → "Body Check" across:
 *       - WP page title (ID 257)
 *       - Welcome Member page button label
 *       - Member Dashboard card label
 *       - Ministry history references (display strings only)
 *
 * Version: 3.0.4
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ─────────────────────────────────────────────
   A — RESPONSIVE HOMEPAGE OVERRIDE
   ───────────────────────────────────────────── */

add_action( 'muplugins_loaded', function () {
    /* Never run in CLI/WP-CLI/cron contexts */
    if ( php_sapi_name() === 'cli' )               return;
    if ( defined( 'WP_CLI' )        && WP_CLI )    return;
    if ( defined( 'DOING_CRON' )    && DOING_CRON ) return;
    if ( defined( 'DOING_AJAX' )    && DOING_AJAX ) return;
    if ( defined( 'REST_REQUEST' )  && REST_REQUEST ) return;

    /* Fire on plain homepage path only */
    $path = rtrim( parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
    if ( $path !== '' ) return;

    /* Skip admin, feeds, WC-ajax, sitemaps */
    if ( strpos( $_SERVER['REQUEST_URI'] ?? '', '/wp-admin' ) !== false ) return;
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if ( preg_match( '/^(wc-ajax|feed|sitemap|robots)/', $qs ) ) return;

    $login_url  = '/login/';
    $explore_url = '/explore/';
    $member_url = function_exists( 'pmpro_url' )
        ? pmpro_url( 'checkout', '?level=1' )
        : '/membership-checkout/?level=1';

    $desktop_bg = 'https://excreet.com/wp-content/uploads/2026/04/Excreet-Landscape-HomePg-4-Translation-Version.png';
    $mobile_bg  = 'https://excreet.com/wp-content/uploads/2026/04/excreet-mobile-hmpg%20final.jpg';

    status_header( 200 );
    header( 'Content-Type: text/html; charset=utf-8' );
    header( 'Cache-Control: no-store' );

    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php bloginfo( 'name' ); ?> — Body Intelligence Platform</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

html, body {
    width: 100%;
    height: 100%;
    max-height: 100%;
    overflow: hidden !important;
    font-family: 'Poppins', sans-serif;
}

/* ── Hero wrapper — CSS Grid for guaranteed no-overflow ── */
.ex304-hero {
    width: 100vw;
    height: 100vh;
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    background-image: url('<?php echo esc_url( $desktop_bg ); ?>');
    background-size: cover;
    background-position: top center;
    background-repeat: no-repeat;

    /* Grid: top nav row | flex middle | bottom CTAs row */
    display: grid;
    grid-template-rows: auto 1fr auto;
    grid-template-columns: 1fr;
}

/* ── Row 1: Nav bar ── */
.ex304-nav {
    grid-row: 1;
    padding: clamp(14px, 2.5vh, 28px) clamp(16px, 2.5vw, 32px);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    z-index: 20;
}
.ex304-nav a {
    display: inline-block;
    padding: 7px 20px;
    background: #fff;
    color: #56075E;
    border: 3px solid #56075E;
    border-radius: 30px;
    font-family: 'Poppins', sans-serif;
    font-size: clamp(11px, 1.1vw, 14px);
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.2s;
}
.ex304-nav a:hover { background: #f5d6ff; }

/* ── Row 2: Middle — headline sits left, phone mockup is in the bg image ── */
.ex304-middle {
    grid-row: 2;
    display: flex;
    align-items: center;
    padding: 0 5% 0 5%;
    overflow: hidden;
}

.ex304-headline {
    max-width: 48%;
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: clamp(16px, 2.2vw, 36px);
    line-height: 1.3;
    letter-spacing: -0.01em;
    color: #fff;
    text-shadow:
        0 0 40px rgba(200, 147, 10, 0.30),
        0 2px 4px  rgba(0,0,0,0.60),
        0 6px 20px rgba(0,0,0,0.40);
}

/* Gold accent on the payoff line */
.ex304-headline .ex304-accent {
    display: block;
    margin-top: 0.4em;
    color: #F5D97A;
    text-shadow:
        0 0 28px rgba(245, 217, 122, 0.55),
        0 2px 6px  rgba(0,0,0,0.55);
}

/* ── Row 3: CTAs ── */
.ex304-ctas {
    grid-row: 3;
    padding: clamp(10px, 2vh, 24px) 5%;
    display: flex;
    flex-direction: row;
    gap: 16px;
    align-items: center;
}
.ex304-btn {
    display: inline-block;
    padding: clamp(10px, 1.4vh, 15px) clamp(20px, 2.2vw, 30px);
    border-radius: 30px;
    font-family: 'Poppins', sans-serif;
    font-size: clamp(13px, 1.2vw, 16px);
    font-weight: 600;
    text-decoration: none;
    transition: opacity 0.2s, transform 0.15s;
    white-space: nowrap;
}
.ex304-btn:hover { opacity: 0.88; transform: translateY(-2px); }
.ex304-btn-explore {
    background: #A10CA2;
    color: #fff;
    border: 3px solid rgba(255,255,255,0.7);
}
.ex304-btn-member {
    background: #C8930A;
    color: #fff;
    border: 3px solid rgba(255,255,255,0.7);
}

/* ══════════════════════════════
   MOBILE  (≤ 768px)
   ══════════════════════════════ */
@media (max-width: 768px) {
    .ex304-hero {
        background-image: url('<?php echo esc_url( $mobile_bg ); ?>');
        background-position: center center;
        grid-template-rows: auto 1fr auto;
    }
    .ex304-nav {
        padding: clamp(12px, 2vh, 20px) 16px;
        gap: 6px;
    }
    .ex304-nav a {
        padding: 7px 16px;
        font-size: 12px;
    }
    .ex304-middle {
        justify-content: center;
        padding: 0 8%;
    }
    .ex304-headline {
        max-width: 90%;
        text-align: center;
        font-size: clamp(15px, 4.5vw, 22px);
    }
    .ex304-ctas {
        justify-content: center;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding-bottom: clamp(20px, 4vh, 40px);
    }
    .ex304-btn {
        width: min(78vw, 300px);
        text-align: center;
        font-size: 15px;
    }
}

/* ══════════════════════════════
   SMALL PHONE  (≤ 430px)
   ══════════════════════════════ */
@media (max-width: 430px) {
    .ex304-headline { font-size: clamp(14px, 4.8vw, 18px); }
    .ex304-btn { font-size: 14px; padding: 11px 16px; }
}

/* ══════════════════════════════
   LARGE DESKTOP  (≥ 1600px)
   ══════════════════════════════ */
@media (min-width: 1600px) {
    .ex304-headline { font-size: clamp(28px, 2.4vw, 44px); }
}
</style>
</head>
<body>
<div class="ex304-hero" role="main">

    <nav class="ex304-nav" aria-label="Site navigation">
        <a href="<?php echo esc_url( $login_url ); ?>">Login</a>
        <a href="#">Language</a>
    </nav>

    <div class="ex304-middle">
        <p class="ex304-headline">
            Your body sends signals every day.<br>
            Most people never learn to read them.
            <span class="ex304-accent">Excreet helps you translate them.</span>
        </p>
    </div>

    <div class="ex304-ctas">
        <a class="ex304-btn ex304-btn-explore"
           href="<?php echo esc_url( $explore_url ); ?>">Explore</a>
        <a class="ex304-btn ex304-btn-member"
           href="<?php echo esc_url( $member_url ); ?>">Become a Member</a>
    </div>

</div>
</body>
</html>
<?php
    exit;
}, 1 );


/* ─────────────────────────────────────────────
   B — "BODY CHECK" RENAME
   (replaces display strings; does NOT change the /healing-command-center slug)
   ───────────────────────────────────────────── */

/* 1. WP page title for ID 257 */
add_filter( 'the_title', function ( $title, $id = null ) {
    if ( (int) $id === 257 ) {
        return 'Body Check';
    }
    return $title;
}, 10, 2 );

/* 2. Welcome Member page — button label */
add_filter( 'the_content', function ( $content ) {
    if ( ! is_page( 'welcome-member' ) ) return $content;
    $content = str_replace(
        [ "Today's Gut Snapshot", "Today's Body Snapshot", "24/7 Body Snapshot" ],
        "Body Check",
        $content
    );
    return $content;
}, 20 );

/* 3. Member Dashboard card label (patch-297 uses shortcode output) */
add_filter( 'excreet_hcc_display_name', function () {
    return 'Body Check';
} );

/* 4. CSS class used in patch-272 / patch-298 button labels (JS-rendered) */
add_action( 'wp_footer', function () {
    if ( ! is_page( [ 'welcome-member', 'member-dashboard', 'healing-command-center' ] ) ) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('a, button, .ex-nav-item, .ex272-btn').forEach(function (el) {
            var t = el.textContent.trim();
            if (t === "Today\u2019s Gut Snapshot" || t === "Today\u2019s Body Snapshot" || t === "24/7 Body Snapshot") {
                el.textContent = el.textContent.replace(/Today\u2019s Gut Snapshot|Today\u2019s Body Snapshot|24\/7 Body Snapshot/g, "Body Check");
            }
        });
    });
    </script>
    <?php
}, 99 );
