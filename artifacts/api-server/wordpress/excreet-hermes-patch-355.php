<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.5.5
 * Description: Progressive Web App (PWA) — manifest.json, sw.js, install meta tags v3.5.5
 * Version: 3.5.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EXCREET_PWA_ICON', 'https://excreet.com/wp-content/uploads/2026/06/excreet-pwa-icon.png' );

// ── 1. Intercept /manifest.json and /sw.js early (before WP routing) ──────────

add_action( 'init', 'excreet_355_intercept_pwa_files', 1 );
function excreet_355_intercept_pwa_files() {
    $uri = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );

    if ( $uri === '/manifest.json' ) {
        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        header( 'Cache-Control: public, max-age=86400' );
        header( 'Access-Control-Allow-Origin: *' );

        echo json_encode( [
            'name'             => 'Excreet',
            'short_name'       => 'Excreet',
            'description'      => 'Decode your body\'s daily signals. Pre-clinical cellular health insights — in under 5 minutes a day.',
            'start_url'        => '/?pwa=1',
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'theme_color'      => '#0c0115',
            'background_color' => '#0c0115',
            'categories'       => [ 'health', 'wellness', 'medical' ],
            'lang'             => 'en-US',
            'icons'            => [
                [
                    'src'     => EXCREET_PWA_ICON,
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => EXCREET_PWA_ICON,
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => EXCREET_PWA_ICON,
                    'sizes'   => '1024x1024',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name'        => 'My Dashboard',
                    'short_name'  => 'Dashboard',
                    'url'         => '/member-dashboard/',
                    'description' => 'Your Body Score and health signals',
                    'icons'       => [ [ 'src' => EXCREET_PWA_ICON, 'sizes' => '96x96' ] ],
                ],
                [
                    'name'        => 'Ministry of Healing',
                    'short_name'  => 'Ministry',
                    'url'         => '/ask-the-healer/',
                    'description' => 'Chat with your AI health guide',
                    'icons'       => [ [ 'src' => EXCREET_PWA_ICON, 'sizes' => '96x96' ] ],
                ],
                [
                    'name'        => 'Body Check',
                    'short_name'  => 'Body Check',
                    'url'         => '/hcc/',
                    'description' => 'Submit your morning body check',
                    'icons'       => [ [ 'src' => EXCREET_PWA_ICON, 'sizes' => '96x96' ] ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    if ( $uri === '/sw.js' ) {
        header( 'Content-Type: application/javascript; charset=utf-8' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Service-Worker-Allowed: /' );
        ?>
/* Excreet Service Worker v3.5.5 */
const CACHE = 'excreet-v1';

self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE).then(c => c.addAll(['/', '/member-dashboard/', '/ask-the-healer/']))
    );
    self.skipWaiting();
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

/* Network-first: always try live, fall back to cache */
self.addEventListener('fetch', e => {
    if (e.request.method !== 'GET') return;
    const url = e.request.url;
    if (!url.startsWith('https://excreet.com')) return;
    /* Skip wp-admin and API calls — always live */
    if (url.includes('/wp-admin') || url.includes('/wp-json') || url.includes('/api/')) return;

    e.respondWith(
        fetch(e.request)
            .then(res => {
                if (res.ok) {
                    const clone = res.clone();
                    caches.open(CACHE).then(c => c.put(e.request, clone));
                }
                return res;
            })
            .catch(() => caches.match(e.request).then(r => r || caches.match('/')))
    );
});
        <?php
        exit;
    }
}

// ── 2. Head meta tags (priority 1 — before everything else) ──────────────────

add_action( 'wp_head', 'excreet_355_head_meta', 1 );
function excreet_355_head_meta() {
    $icon = EXCREET_PWA_ICON;
    ?>
<!-- Excreet PWA v3.5.5 -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0c0115">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Excreet">
<link rel="apple-touch-icon" href="<?php echo esc_url( $icon ); ?>">
<link rel="apple-touch-icon" sizes="152x152" href="<?php echo esc_url( $icon ); ?>">
<link rel="apple-touch-icon" sizes="167x167" href="<?php echo esc_url( $icon ); ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $icon ); ?>">
<meta name="msapplication-TileColor" content="#0c0115">
<meta name="msapplication-TileImage" content="<?php echo esc_url( $icon ); ?>">
    <?php
}

// ── 3. Register service worker (footer) ──────────────────────────────────────

add_action( 'wp_footer', 'excreet_355_register_sw', 99 );
function excreet_355_register_sw() {
    ?>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' });
    });
}
</script>
    <?php
}
