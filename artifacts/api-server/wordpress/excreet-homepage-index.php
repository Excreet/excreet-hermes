<?php
/**
 * Front to the WordPress application.
 *
 * Excreet root index.php override (v3.0.7).
 * Handles cache-bypassed page overrides BEFORE WordPress loads.
 * Any path listed here is guaranteed to bypass SiteGround nginx proxy cache.
 *
 * Managed pages:
 *   GET /               → Homepage — Home Clinic (Bathroom) hero, phone mockup
 *   GET /explore/       → Explore page (vision copy, video, tier comparison)
 *   GET /member-login/  → Branded login page (replaces /login/ to avoid stale nginx cache)
 *
 * Deploy: SCP → /home/customer/www/excreet.com/public_html/index.php
 *         then SSH: curl -X PURGE http://localhost/<path> -H "Host: excreet.com"
 *
 * Version: 3.4.7
 */

if ( php_sapi_name() === 'cli' || defined('WP_CLI') ) {
    // WP-CLI: skip all overrides and fall through to WordPress
    define( 'WP_USE_THEMES', true );
    require __DIR__ . '/wp-blog-header.php';
    exit;
}

$req_path   = rtrim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
$req_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$base       = 'https://excreet.com';

/* ═══════════════════════════════════════════════════════
   DIGITAL CARD  GET /card  — serves card.html directly
   ═══════════════════════════════════════════════════════ */
if ( $req_path === '/card' && $req_method === 'GET' ) {
    $card_file = __DIR__ . '/card.html';
    if ( file_exists( $card_file ) ) {
        header( 'Content-Type: text/html; charset=UTF-8' );
        header( 'Cache-Control: no-store' );
        readfile( $card_file );
    } else {
        header( 'HTTP/1.1 404 Not Found' );
        echo '<h1>Card not found</h1>';
    }
    exit;
}

/* ═══════════════════════════════════════════════════════
   HOMEPAGE  GET /  — Home Clinic v3.0.7
   ═══════════════════════════════════════════════════════ */
if ( $req_path === '' && $req_method === 'GET' ) {
    $bg      = $base . '/wp-content/uploads/2026/05/excreet-bathroom-bg.png';
    $logo    = $base . '/wp-content/uploads/2026/05/excreet-hero-logo.png';
    $hp_bottle = $base . '/wp-content/uploads/2026/05/excreet-bottle-hero.png';
    $favicon = $base . '/wp-content/uploads/2025/11/cropped-favicon-32x32.png';

    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Cache-Control: no-store' );
    header( 'X-Ex-Patch: 307-index' );
    ?><!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Excreet — Body Intelligence Platform</title>
<link rel="canonical" href="https://excreet.com/">
<link rel="alternate" hreflang="en" href="https://excreet.com/">
<link rel="alternate" hreflang="es" href="https://excreet.com/es/">
<link rel="alternate" hreflang="x-default" href="https://excreet.com/">
<link rel="icon" href="<?= $favicon ?>" sizes="32x32">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:100%;height:100%;max-height:100%;overflow:hidden;font-family:"Poppins",sans-serif;background:#0c0115}
.ex-hero{width:100vw;height:100dvh;max-height:100dvh;overflow:hidden;display:grid;grid-template-rows:auto 1fr auto;grid-template-columns:1fr;position:relative}
.ex-bg{position:absolute;inset:0;z-index:0;background:url("<?= $bg ?>") center center/cover no-repeat}
.ex-scrim{position:absolute;inset:0;z-index:1;pointer-events:none;background:linear-gradient(to bottom,rgba(15,3,32,.65) 0%,rgba(15,3,32,.08) 28%,rgba(15,3,32,.04) 62%,rgba(15,3,32,.55) 100%),linear-gradient(to right,rgba(15,3,32,.5) 0%,transparent 42%)}
.ex-logo{position:absolute;left:52%;top:28%;transform:translate(-50%,-50%);z-index:6;width:clamp(100px,11vw,175px);filter:drop-shadow(0 0 28px rgba(245,217,122,.8)) drop-shadow(0 0 60px rgba(245,217,122,.35)) drop-shadow(0 4px 12px rgba(0,0,0,.7))}
.ex-tagline{position:absolute;left:52%;top:38%;transform:translateX(-50%);z-index:6;white-space:nowrap;font-size:clamp(11px,1vw,15px);font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:rgba(255,255,255,.92);text-shadow:0 1px 8px rgba(0,0,0,.9),0 0 24px rgba(0,0,0,.7);border-top:1px solid rgba(201,168,76,.5);border-bottom:1px solid rgba(201,168,76,.5);padding:.35em 1.2em;}
.ex-lab-slogan{display:block;margin-top:.4em;font-weight:700;font-style:normal;color:#F5D97A;text-shadow:0 0 22px rgba(245,217,122,.65),0 2px 8px rgba(0,0,0,.9);}
/* Phone */
.ex-phone-wrap{position:absolute;right:6%;top:34%;transform:translateY(-50%) perspective(900px) rotateY(-22deg) rotateX(4deg) scaleY(.67);transform-origin:center center;z-index:6;width:clamp(185px,17vw,240px);filter:drop-shadow(-18px 50px 80px rgba(0,0,0,.75)) drop-shadow(-6px 10px 28px rgba(0,0,0,.55)) drop-shadow(0 0 50px rgba(180,130,20,.25))}
.ex-phone-dock{position:relative;margin:0 10%;height:clamp(22px,2.5vw,34px);border-radius:10px 10px 14px 14px;background:linear-gradient(175deg,#1a1a1a 0%,#0a0a0a 100%);box-shadow:0 0 0 1.5px rgba(201,168,76,.6),inset 0 1px 0 rgba(255,255,255,.05),0 8px 20px rgba(0,0,0,.6)}
.ex-phone-dock::before{content:'';position:absolute;bottom:0;left:0;right:0;height:4px;border-radius:0 0 12px 12px;background:linear-gradient(90deg,#8a6010,#C9A84C 30%,#f5d97a 50%,#C9A84C 70%,#8a6010)}
.ex-phone-dock::after{content:'';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:18%;height:5px;border-radius:4px;background:linear-gradient(90deg,#8a6010,#C9A84C,#8a6010);box-shadow:0 0 6px rgba(201,168,76,.4)}
.ex-phone-shell{position:relative;border-radius:36px;padding:2.5px;background:linear-gradient(155deg,#f5d97a 0%,#C9A84C 15%,#8a6010 30%,#1a1000 45%,#8a6010 60%,#C9A84C 78%,#f5d97a 100%);box-shadow:0 0 0 1px rgba(0,0,0,.5),inset 0 0 0 1px rgba(255,255,255,.04)}
.ex-phone-inner{border-radius:34px;background:linear-gradient(175deg,#0e0e0e 0%,#050505 100%);padding:12px 8px 16px;overflow:hidden;position:relative}
.ex-phone-inner::before{content:'';position:absolute;top:0;left:0;width:60%;height:40%;background:linear-gradient(135deg,rgba(255,255,255,.07) 0%,rgba(255,255,255,.02) 40%,transparent 100%);border-radius:34px 34px 0 0;pointer-events:none;z-index:20}
.ex-phone-inner::after{content:'';position:absolute;bottom:0;left:0;right:0;height:25%;background:linear-gradient(to top,rgba(180,120,10,.07),transparent);pointer-events:none;z-index:20}
.ex-phone-shell .ex-vol-up,.ex-phone-shell .ex-vol-dn,.ex-phone-shell .ex-pwr{position:absolute;right:-3px;width:3px;border-radius:0 2px 2px 0;background:linear-gradient(to right,#8a6010,#C9A84C);box-shadow:1px 0 3px rgba(201,168,76,.3)}
.ex-phone-shell .ex-vol-up{top:20%;height:9%}
.ex-phone-shell .ex-vol-dn{top:32%;height:9%}
.ex-phone-shell .ex-pwr{top:44%;height:6%;left:-3px;right:auto;border-radius:2px 0 0 2px}
.ex-phone-island{width:62px;height:10px;background:#000;border-radius:20px;margin:0 auto 10px;box-shadow:0 0 0 1.5px rgba(201,168,76,.15)}
/* Screen — pale lavender */
.ex-phone-screen{background:linear-gradient(180deg,#ede8f9 0%,#e4dcf5 100%);border-radius:22px;padding:14px 13px 13px;position:relative;overflow:hidden;min-height:clamp(240px,26vw,385px);box-shadow:inset 0 0 0 1px rgba(107,33,168,.08)}
.ex-phone-screen::before{content:'';position:absolute;top:-10%;right:-10%;width:60%;height:60%;background:radial-gradient(circle,rgba(201,168,76,.07) 0%,transparent 65%);pointer-events:none}
.ex-phone-screen::after{content:none}
.ex-s-kicker{font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#8B5CF6;margin-bottom:3px}
.ex-s-title{font-size:16px;font-weight:700;color:#1a0a2e;margin-bottom:14px;letter-spacing:.01em}
.ex-s-score-row{display:flex;align-items:center;gap:14px;margin-bottom:14px}
.ex-s-ring-svg{display:block;flex-shrink:0}
.ex-s-ring-track{fill:none;stroke:rgba(107,33,168,.12);stroke-width:7}
.ex-s-ring-fill{fill:none;stroke:url(#ringGrad);stroke-width:7;stroke-linecap:round;stroke-dasharray:108.6 150.8;stroke-dashoffset:0;transform:rotate(-90deg);transform-origin:50% 50%}
.ex-s-ring-num{font-size:17px;font-weight:700;fill:#6B21A8;dominant-baseline:central;text-anchor:middle}
.ex-s-score-meta{flex:1}
.ex-s-big{font-size:38px;font-weight:700;color:#6B21A8;line-height:1}
.ex-s-delta{font-size:12px;color:#1a7a3a;font-weight:700;margin-top:4px}
.ex-wave{width:100%;height:38px;margin-bottom:14px;overflow:visible}
.ex-wave-bg{fill:url(#waveArea);opacity:.15}
.ex-wave-line{fill:none;stroke:url(#waveGrad);stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.ex-s-divider{height:1px;background:rgba(107,33,168,.1);margin-bottom:12px}
.ex-s-metric{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(107,33,168,.07)}
.ex-s-metric:last-of-type{border-bottom:none;margin-bottom:12px}
.ex-s-m-label{font-size:11.5px;color:#4a3070;white-space:nowrap;min-width:90px;font-weight:500}
.ex-s-m-track{flex:1;height:5px;background:rgba(107,33,168,.12);border-radius:5px;overflow:hidden}
.ex-s-m-fill{height:100%;border-radius:5px;background:linear-gradient(90deg,#6B21A8,#C9A84C)}
.ex-s-m-val{font-size:11.5px;color:#1a0a2e;font-weight:700;min-width:56px;text-align:right}
.ex-s-cta{display:block;padding:13px 14px;background:linear-gradient(90deg,#7B24B8,#A80CA0);border-radius:14px;text-align:center;font-size:12px;font-weight:700;color:#fff;letter-spacing:.05em;position:relative;overflow:hidden}
.ex-s-cta::before{content:'';position:absolute;top:0;left:0;right:0;height:50%;background:linear-gradient(to bottom,rgba(255,255,255,.14),transparent);border-radius:14px 14px 0 0}
/* Nav */
.ex-nav{grid-row:1;position:relative;z-index:10;padding:clamp(14px,2.5vh,28px) clamp(16px,2.5vw,32px);display:flex;flex-direction:column;align-items:flex-start;gap:8px}
.ex-nav a{display:inline-block;padding:7px 20px;background:rgba(255,255,255,.93);color:#56075E;border:3px solid #56075E;border-radius:30px;font-size:clamp(11px,1.1vw,14px);font-weight:600;text-decoration:none;white-space:nowrap;transition:background .2s}
.ex-nav a:hover{background:#f5d6ff}
/* Google Translate — styled as nav pill */
.goog-te-gadget{font-size:0!important;line-height:0!important}
.goog-logo-link,.goog-te-gadget>span{display:none!important}
.goog-te-combo{appearance:none;-webkit-appearance:none;display:inline-block;padding:7px 14px;background:rgba(255,255,255,.93);color:#56075E;border:3px solid #56075E;border-radius:30px;font-size:clamp(11px,1.1vw,14px);font-weight:600;cursor:pointer;outline:none;font-family:"Poppins",sans-serif;white-space:nowrap;max-width:160px;transition:background .2s}
.goog-te-combo:hover{background:#f5d6ff}
.goog-te-banner-frame,.goog-te-banner-frame.skiptranslate{display:none!important;visibility:hidden!important}
html.translated-ltr,html.translated-rtl{overflow-y:auto!important;height:auto!important;max-height:none!important}
body.translated-ltr,body.translated-rtl{overflow-y:auto!important;height:auto!important;max-height:none!important;top:0!important;margin-top:0!important;position:relative!important}
.translated-ltr .ex-wrapper,.translated-rtl .ex-wrapper{overflow:visible!important;height:auto!important;min-height:100vh}
/* Headline */
.ex-middle{grid-row:2;position:relative;z-index:10;display:flex;align-items:flex-end;padding:0 5% clamp(6px,2vh,22px)}
.ex-headline{max-width:50%;font-weight:700;font-size:clamp(15px,2.1vw,34px);line-height:1.35;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.95),0 6px 24px rgba(0,0,0,.8),0 0 36px rgba(180,120,10,.25)}
.ex-accent{display:block;margin-top:.4em;color:#F5D97A;text-shadow:0 0 22px rgba(245,217,122,.65),0 2px 8px rgba(0,0,0,.9)}
/* CTAs */
.ex-ctas{grid-row:3;position:relative;z-index:10;padding:clamp(10px,2vh,22px) 5%;display:flex;flex-direction:row;gap:16px;align-items:center}
.ex-btn{display:inline-block;padding:clamp(10px,1.4vh,15px) clamp(20px,2.2vw,30px);border-radius:30px;font-size:clamp(13px,1.2vw,16px);font-weight:600;text-decoration:none;transition:opacity .2s,transform .15s;white-space:nowrap}
.ex-btn:hover{opacity:.88;transform:translateY(-2px)}
.ex-btn-explore{background:#A10CA2;color:#fff;border:3px solid rgba(255,255,255,.7)}
.ex-btn-member{background:#C8930A;color:#fff;border:3px solid rgba(255,255,255,.7)}
/* Store pill — matches Login/Language nav style, floats between Members Only banner and Bottle */
.ex-store-pill{position:absolute;right:30%;bottom:clamp(18px,3.5vh,32px);z-index:12;display:inline-block;padding:7px 20px;background:rgba(255,255,255,.93);color:#56075E;border:3px solid #56075E;border-radius:30px;font-size:clamp(11px,1.1vw,14px);font-weight:600;text-decoration:none;white-space:nowrap;transition:background .2s}
.ex-store-pill:hover{background:#f5d6ff}
/* Product bottle — independent block */
.ex-prod-bottle{position:absolute;visibility:hidden;z-index:5;width:clamp(140px,12vw,172px);filter:drop-shadow(0 18px 36px rgba(0,0,0,.72)) drop-shadow(0 0 24px rgba(201,168,76,.28)) brightness(1.08)}
.ex-prod-bottle img{width:100%;height:auto;display:block}
/* Members Only banner */
.ex-members-banner{position:absolute;left:50%;transform:translateX(-50%);bottom:clamp(18px,3.5vh,32px);z-index:8;background:linear-gradient(135deg,#1E0538 0%,#3A0A75 35%,#4D0FA0 50%,#3A0A75 65%,#1E0538 100%);border-radius:50px;padding:11px 24px 11px 20px;display:flex;align-items:center;gap:9px;box-shadow:0 0 0 1.5px rgba(201,168,76,.75),0 0 0 3.5px rgba(107,33,168,.25),0 10px 36px rgba(10,2,22,.75),0 0 44px rgba(107,33,168,.3),inset 0 1px 0 rgba(255,255,255,.12),inset 0 -1px 0 rgba(0,0,0,.3);overflow:visible}
.ex-members-banner::before{content:'';position:absolute;inset:-1.5px;border-radius:52px;background:linear-gradient(135deg,#F5D97A,#8B6914 30%,#C9A84C 50%,#8B6914 70%,#F5D97A);-webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none}
.ex-mb-text{font-size:13.5px;font-weight:800;letter-spacing:.22em;text-transform:uppercase;background:linear-gradient(135deg,#5C3D0A 0%,#C9A84C 18%,#F5D97A 32%,#FFFBE0 46%,#F5D97A 57%,#C9A84C 72%,#8B6914 87%,#C9A84C 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;background-size:220% auto;animation:goldShimmer 2.8s linear infinite;white-space:nowrap;filter:drop-shadow(0 0 5px rgba(245,217,122,.35))}
.ex-mb-star{display:inline-block;font-size:15px;background:linear-gradient(135deg,#C9A84C,#F5D97A,#C9A84C);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;filter:drop-shadow(0 0 7px rgba(245,217,122,.95)) drop-shadow(0 0 2px rgba(255,255,255,.6));animation:starTwinkle 1.9s ease-in-out infinite alternate;line-height:1}
.ex-mb-star-b{animation-delay:.95s}
.ex-mb-sparks{position:absolute;inset:0;pointer-events:none;overflow:visible}
.ex-mb-sp{position:absolute;width:4px;height:4px;border-radius:50%;background:#F5D97A;box-shadow:0 0 5px 2px rgba(245,217,122,.85);animation:spFloat 2.6s ease-in-out infinite}
.sp1{top:-9px;left:22%;animation-delay:0s}
.sp2{top:-7px;right:28%;animation-delay:.65s}
.sp3{bottom:-8px;left:38%;animation-delay:1.3s}
.sp4{top:45%;right:-9px;animation-delay:1.95s}
.sp5{bottom:-6px;right:18%;animation-delay:.4s}
@keyframes goldShimmer{0%{background-position:0% center}100%{background-position:220% center}}
@keyframes starTwinkle{0%{transform:scale(.82) rotate(-12deg);filter:drop-shadow(0 0 4px rgba(245,217,122,.55))}100%{transform:scale(1.25) rotate(12deg);filter:drop-shadow(0 0 14px rgba(245,217,122,1)) drop-shadow(0 0 4px rgba(255,255,255,.7))}}
@keyframes spFloat{0%,100%{transform:translateY(0) scale(.8);opacity:.35}50%{transform:translateY(-7px) scale(1.25);opacity:1}}
/* Responsive */
@media(max-width:1024px){.ex-phone-wrap{width:clamp(185px,17vw,240px)}}
@media(max-width:768px){
  .ex-phone-wrap{display:none}
  .ex-prod-bottle{display:none}
  .ex-members-banner{display:none}
  .ex-store-pill{display:none}
  /* Logo: top zone, well above the headline */
  .ex-logo{left:50%;top:17%;width:clamp(72px,20vw,108px)}
  /* Tagline: just below logo, not in headline zone */
  .ex-tagline{top:27%;left:50%;font-size:clamp(8px,2.4vw,11px);letter-spacing:.12em}
  /* Headline: bottom-aligned so it sits above the CTAs */
  .ex-middle{align-items:flex-end;justify-content:center;padding:0 8% clamp(10px,1.5vh,18px)}
  .ex-headline{max-width:92%;text-align:center;font-size:clamp(17px,5.2vw,26px)}
  .ex-ctas{justify-content:center;flex-direction:column;align-items:center;gap:12px;padding-bottom:clamp(22px,4.5vh,44px)}
  .ex-btn{width:min(80vw,300px);text-align:center;font-size:15px}
}
@media(min-width:1600px){.ex-headline{font-size:clamp(28px,2vw,40px)}.ex-logo{top:28%}}
</style>
</head>
<body>
<div class="ex-hero" role="main">
  <div class="ex-bg"></div>
  <div class="ex-scrim"></div>

  <img class="ex-logo" src="<?= $logo ?>" alt="Excreet">
  <p class="ex-tagline">A Pre-Clinical Warning System.</p>

  <!-- Premium phone mockup -->
  <div class="ex-phone-wrap">
    <div class="ex-phone-shell">
      <div class="ex-vol-up"></div>
      <div class="ex-vol-dn"></div>
      <div class="ex-pwr"></div>
      <div class="ex-phone-inner">
        <div class="ex-phone-island"></div>
        <div class="ex-phone-screen">
          <svg width="0" height="0" style="position:absolute;overflow:visible">
            <defs>
              <linearGradient id="ringGrad" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#8B5CF6"/>
                <stop offset="100%" stop-color="#C9A84C"/>
              </linearGradient>
              <linearGradient id="waveGrad" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="#7B24B8"/>
                <stop offset="100%" stop-color="#C9A84C"/>
              </linearGradient>
              <linearGradient id="waveArea" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#C9A84C" stop-opacity="1"/>
                <stop offset="100%" stop-color="#C9A84C" stop-opacity="0"/>
              </linearGradient>
            </defs>
          </svg>
          <div class="ex-s-kicker">Today's</div>
          <div class="ex-s-title">Body Score</div>
          <div class="ex-s-score-row">
            <svg class="ex-s-ring-svg" width="58" height="58" viewBox="0 0 58 58">
              <circle class="ex-s-ring-track" cx="29" cy="29" r="24"/>
              <circle class="ex-s-ring-fill" cx="29" cy="29" r="24"/>
              <text class="ex-s-ring-num" x="29" y="29" font-family="Poppins,sans-serif" font-weight="700" font-size="14">72</text>
            </svg>
            <div class="ex-s-score-meta">
              <div class="ex-s-big">72</div>
              <div class="ex-s-delta">&#9650; +4 from yesterday</div>
            </div>
          </div>
          <svg class="ex-wave" viewBox="0 0 180 36" preserveAspectRatio="none">
            <path class="ex-wave-bg" d="M0 36 L0 28 C12 28 16 10 28 12 C40 14 44 26 56 22 C68 18 72 8 84 10 C96 12 100 24 112 20 C124 16 128 6 140 8 C152 10 156 22 168 18 C176 15 179 13 180 14 L180 36 Z"/>
            <path class="ex-wave-line" d="M0 28 C12 28 16 10 28 12 C40 14 44 26 56 22 C68 18 72 8 84 10 C96 12 100 24 112 20 C124 16 128 6 140 8 C152 10 156 22 168 18 C176 15 179 13 180 14"/>
          </svg>
          <div class="ex-s-divider"></div>
          <div class="ex-s-metric">
            <span class="ex-s-m-label">Inflammation</span>
            <div class="ex-s-m-track"><div class="ex-s-m-fill" style="width:68%"></div></div>
            <span class="ex-s-m-val">Moderate</span>
          </div>
          <div class="ex-s-metric">
            <span class="ex-s-m-label">Gut Motility</span>
            <div class="ex-s-m-track"><div class="ex-s-m-fill" style="width:83%"></div></div>
            <span class="ex-s-m-val">Good</span>
          </div>
          <div class="ex-s-metric">
            <span class="ex-s-m-label">Pattern Trend</span>
            <div class="ex-s-m-track"><div class="ex-s-m-fill" style="width:76%"></div></div>
            <span class="ex-s-m-val">Improving</span>
          </div>
          <div class="ex-s-cta">Run Today&#39;s Body Check &#8594;</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Product bottle hero -->
  <div class="ex-prod-bottle">
    <img src="<?= $hp_bottle ?>" alt="Excreet Cell Ready Minerals">
  </div>

  <!-- Store pill — nav style, floats between Members Only banner and Bottle -->
  <a class="ex-store-pill" href="/shop/" data-i18n="shop">Shop</a>

  <!-- Members Only banner — purple body, sparkling gold border + text -->
  <div class="ex-members-banner" aria-label="Members Only Platform">
    <div class="ex-mb-sparks">
      <span class="ex-mb-sp sp1"></span>
      <span class="ex-mb-sp sp2"></span>
      <span class="ex-mb-sp sp3"></span>
      <span class="ex-mb-sp sp4"></span>
      <span class="ex-mb-sp sp5"></span>
    </div>
    <span class="ex-mb-star">✦</span>
    <span class="ex-mb-text" data-i18n="membersOnly">Members Only</span>
    <span class="ex-mb-star ex-mb-star-b">✦</span>
  </div>

  <nav class="ex-nav" aria-label="Site navigation">
    <a href="/member-login/" data-i18n="login">Login</a>
    <div id="ex-translate-hp" style="min-height:36px"></div>
    <a href="/learn/" data-i18n="articles">Articles</a>
    <a href="/member-stories/">Testimonials</a>
  </nav>
  <div class="ex-middle">
    <p class="ex-headline">
      <span data-i18n="headline1">Your body warns every day.</span><br>
      <span class="ex-accent" data-i18n="accent">Excreet translates that warning with &mdash; just your phone.</span>
      <span class="ex-lab-slogan" data-i18n="labSlogan">Your bathroom becomes your laboratory.</span>
    </p>
  </div>
  <div class="ex-ctas">
    <a class="ex-btn ex-btn-explore" href="/explore/" data-i18n="explore">What is Excreet?</a>
    <a class="ex-btn ex-btn-member" href="/membership-checkout/?level=1" data-i18n="member">Become a Member</a>
  </div>
</div>
<script>
(function(){
  function positionProdBottle(){
    var phone  = document.querySelector('.ex-phone-wrap');
    var prod   = document.querySelector('.ex-prod-bottle');
    if(!phone || !prod) return;
    var hero      = phone.offsetParent;
    var heroRect  = hero.getBoundingClientRect();
    var phoneRect = phone.getBoundingClientRect();
    var GAP = 22;
    prod.style.top        = (phoneRect.bottom - heroRect.top + GAP) + 'px';
    prod.style.left       = (phoneRect.left + phoneRect.width/2 - heroRect.left - prod.offsetWidth/2) + 'px';
    prod.style.right      = 'auto';
    prod.style.bottom     = 'auto';
    prod.style.visibility = 'visible';
  }
  if(document.readyState === 'complete' || document.readyState === 'interactive'){
    setTimeout(positionProdBottle, 0);
  } else {
    document.addEventListener('DOMContentLoaded', positionProdBottle);
  }
  window.addEventListener('resize', positionProdBottle);
})();
</script>
<script>function googleTranslateElementInit(){new google.translate.TranslateElement({pageLanguage:'en',autoDisplay:false},'ex-translate-hp');}</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async></script>
</body>
</html>
<?php
    exit;
}
/* ── End homepage ──────────────────────────────────────────── */


/* ═══════════════════════════════════════════════════════
   EXPLORE PAGE  GET /explore/
   ═══════════════════════════════════════════════════════ */
if ( $req_path === '/explore' && $req_method === 'GET' ) {
    $video_url  = $base . '/wp-content/uploads/2026/04/Excreet_SaaS_Explainer_Video_16x9.mp4';
    $favicon    = $base . '/wp-content/uploads/2025/11/cropped-favicon-32x32.png';
    $logo       = $base . '/wp-content/uploads/2026/05/excreet-hero-logo.png';
    $bg_month   = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg         = $base . '/wp-content/uploads/healer-bg-' . $bg_month . '.jpg';

    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Cache-Control: no-store' );
    header( 'X-Ex-Patch: 305-explore' );
    ?><!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Explore Excreet — Body Intelligence Platform</title>
<link rel="canonical" href="https://excreet.com/explore/">
<link rel="alternate" hreflang="en" href="https://excreet.com/explore/">
<link rel="alternate" hreflang="es" href="https://excreet.com/es/explore/">
<link rel="alternate" hreflang="x-default" href="https://excreet.com/explore/">
<link rel="icon" href="<?= $favicon ?>" sizes="32x32">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,600;0,700;1,300&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
  --purple:#6B21A8;--gold:#C9A84C;--gold-light:#F5D97A;
  --dark:#0f0520;--dark-2:#1a0535;--dark-3:#2d0a50;--dark-card:#1e0a38;
  --text:#f0e8ff;--muted:rgba(240,232,255,.6)
}
html{scroll-behavior:smooth}
body{font-family:'Poppins',sans-serif;background:url("<?= $bg ?>") center/cover no-repeat fixed var(--dark);color:var(--text);min-height:100vh;overflow-x:hidden}

/* Nav */
.ex305-nav{position:fixed;top:0;left:0;right:0;z-index:100;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(to bottom,rgba(15,5,32,.95) 0%,rgba(15,5,32,0) 100%)}
.ex305-nav-left{display:flex;gap:10px;align-items:flex-start}
.ex305-nav-login-group{display:flex;flex-direction:column;align-items:center;gap:5px}
.ex305-nav-home{font-size:14px !important;font-weight:700 !important;letter-spacing:.06em !important;color:#F5D97A !important;border:none !important;background:none !important;padding:0 4px !important;text-decoration:none;transition:color .2s;text-shadow:0 1px 6px rgba(0,0,0,.9)}
.ex305-nav-home:hover{color:#fff !important;background:none !important}
.ex305-nav a{display:inline-block;padding:7px 20px;background:transparent;color:#fff;border:2px solid rgba(255,255,255,.5);border-radius:30px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s}
.ex305-nav a:hover{background:rgba(255,255,255,.12);border-color:#fff}
.ex305-nav-logo{display:flex;align-items:center}.ex305-nav-logo img{width:42px;height:42px;object-fit:contain;filter:drop-shadow(0 0 10px rgba(245,217,122,.7))}

/* Vision Hero */
.ex305-vision{min-height:100vh;background:rgba(15,5,32,.32);display:flex;align-items:center;padding:120px 6% 80px;position:relative;overflow:hidden}
.ex305-vision::before{content:'';position:absolute;top:-30%;right:-10%;width:65vw;height:65vw;max-width:700px;max-height:700px;border-radius:50%;background:radial-gradient(circle,rgba(107,33,168,.25) 0%,transparent 70%);pointer-events:none}
.ex305-vision-inner{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;width:100%}
.ex305-kicker{font-size:13px;font-weight:700;letter-spacing:.25em;text-transform:uppercase;color:var(--gold-light);margin-bottom:20px;display:block;text-shadow:0 1px 8px rgba(0,0,0,.85)}
.ex305-vision-h1{font-size:clamp(26px,3.5vw,46px);font-weight:700;line-height:1.2;color:#fff;margin-bottom:28px}
.ex305-vision-h1 em{font-style:normal;color:var(--gold-light)}
.ex305-vision-bridge{font-size:clamp(15px,1.4vw,19px);font-weight:600;color:var(--gold-light);line-height:1.5;margin-bottom:22px;text-shadow:0 0 20px rgba(245,217,122,.2)}
.ex305-vision-body{font-size:clamp(15px,1.4vw,18px);line-height:1.8;color:#fff;font-weight:400;text-shadow:0 1px 6px rgba(0,0,0,.9)}
.ex305-vision-body p+p{margin-top:16px}
.ex305-vision-body strong{color:#fff;font-weight:700}

/* Bottle */
.ex305-bottle-wrap{display:flex;justify-content:center;align-items:center;position:relative}
.ex305-bottle-glow{position:absolute;width:90%;height:90%;background:radial-gradient(circle,rgba(201,168,76,.22) 0%,transparent 65%);border-radius:50%;pointer-events:none;filter:blur(18px)}
.ex305-bottle-img{width:300px;max-width:100%;position:relative;z-index:1;border-radius:16px;filter:drop-shadow(0 24px 60px rgba(107,33,168,.45));transition:transform .3s ease}
.ex305-bottle-img:hover{transform:scale(1.025)}

/* Tagline bar */
.ex305-tagline-bar{background:linear-gradient(90deg,rgba(107,33,168,.55) 0%,rgba(74,18,117,.55) 100%);padding:20px 6%;text-align:center;backdrop-filter:blur(6px)}
.ex305-tagline-bar p{font-size:clamp(14px,1.6vw,18px);font-weight:600;color:#fff;letter-spacing:.04em}
.ex305-tagline-bar span{color:var(--gold-light)}

/* Video */
.ex305-video-section{padding:175px 6% 80px;background:rgba(26,5,53,.32);text-align:center}
.ex305-section-label{font-size:13px;font-weight:700;letter-spacing:.25em;text-transform:uppercase;color:#F5D97A;margin-bottom:12px;display:block;text-shadow:0 1px 8px rgba(0,0,0,.8)}
.ex305-warning-tagline{display:inline-block;font-size:clamp(11px,1vw,15px);font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:rgba(255,255,255,.92);text-shadow:0 1px 8px rgba(0,0,0,.9),0 0 24px rgba(0,0,0,.7);border-top:1px solid rgba(201,168,76,.5);border-bottom:1px solid rgba(201,168,76,.5);padding:.35em 1.2em;font-style:normal;margin-bottom:20px;white-space:nowrap}
.ex305-section-h2{font-size:clamp(20px,2.5vw,32px);font-weight:700;color:#fff;margin-bottom:40px}
.ex305-video-wrap{max-width:860px;margin:0 auto;border-radius:16px;overflow:hidden;box-shadow:0 0 80px rgba(107,33,168,.4),0 0 0 1px rgba(201,168,76,.2)}
.ex305-video-wrap video{display:block;width:100%;height:auto;background:#000}

/* Tiers */
.ex305-tiers-section{padding:80px 6% 100px;background:rgba(15,5,32,.32)}
.ex305-tiers-header{text-align:center;margin-bottom:52px}
.ex305-tiers-sub{font-size:clamp(15px,1.3vw,17px);color:#fff;max-width:580px;margin:16px auto 0;line-height:1.75;font-weight:400;text-shadow:0 1px 6px rgba(0,0,0,.9)}
.ex305-tiers-grid{display:grid;grid-template-columns:1fr 1fr;gap:28px;max-width:900px;margin:0 auto}
.ex305-tier-card{background:rgba(18,5,38,.60);backdrop-filter:blur(8px);border-radius:20px;padding:36px 32px 40px;border:1px solid rgba(201,168,76,.45);position:relative;display:flex;flex-direction:column;transition:border-color .25s,box-shadow .25s}
.ex305-tier-card:hover{border-color:rgba(201,168,76,.4);box-shadow:0 8px 60px rgba(107,33,168,.25)}
.ex305-tier-card.ex305-featured{border-color:rgba(201,168,76,.5);box-shadow:0 0 0 2px rgba(201,168,76,.3),0 12px 60px rgba(107,33,168,.3)}
.ex305-tier-badge{position:absolute;top:-14px;left:50%;transform:translateX(-50%);background:linear-gradient(90deg,#C9A84C,#a8873a);color:#1a0535;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:5px 18px;border-radius:20px;white-space:nowrap}
.ex305-tier-name{font-size:13px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);margin-bottom:10px}
.ex305-tier-price{font-size:clamp(36px,4vw,52px);font-weight:700;color:#fff;line-height:1;margin-bottom:4px}
.ex305-tier-price sup{font-size:.45em;vertical-align:.55em;color:var(--muted)}
.ex305-tier-price span{font-size:.35em;font-weight:400;color:var(--muted)}
.ex305-tier-period{font-size:13px;color:var(--muted);margin-bottom:28px}
.ex305-tier-divider{height:1px;background:rgba(255,255,255,.08);margin-bottom:24px}
.ex305-tier-features{list-style:none;display:flex;flex-direction:column;gap:13px;flex:1}
.ex305-tier-features li{font-size:clamp(13px,1.1vw,14.5px);color:var(--text);display:flex;align-items:flex-start;gap:10px;line-height:1.45}
.ex305-tier-features li::before{content:'✓';color:var(--gold);font-weight:700;flex-shrink:0;margin-top:1px}
.ex305-tier-features li.ex305-hl{color:var(--gold-light);font-weight:600}
.ex305-tier-features li.ex305-hl::before{color:var(--gold-light)}
.ex305-affiliate-callout{margin-top:20px;padding:14px 16px;border-radius:10px;background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.25)}
.ex305-affiliate-callout p{font-size:12.5px;line-height:1.55;color:rgba(240,232,255,.75)}
.ex305-affiliate-callout strong{color:var(--gold);font-weight:700}
.ex305-tier-cta{display:block;margin-top:28px;padding:14px 24px;border-radius:50px;font-size:15px;font-weight:700;text-align:center;text-decoration:none;transition:opacity .2s,transform .15s;letter-spacing:.02em}
.ex305-tier-cta:hover{opacity:.88;transform:translateY(-2px)}
.ex305-cta-starter{background:#6B21A8;color:#fff;border:2px solid rgba(255,255,255,.2)}
.ex305-cta-premium{background:linear-gradient(135deg,#C9A84C,#a8873a);color:#1a0535;border:none}
.ex305-tiers-note{text-align:center;margin-top:36px;font-size:15px;color:#fff;font-weight:500;line-height:1.7;text-shadow:0 1px 6px rgba(0,0,0,.9)}
.ex305-video-caption{margin-top:16px;font-size:15px;color:rgba(255,255,255,.95);text-align:center;display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap}
.ex305-video-caption .ex305-vid-lang{font-weight:700;letter-spacing:.04em;text-shadow:0 1px 6px rgba(0,0,0,.9)}
.ex305-video-caption a{display:inline-block;padding:7px 20px;border-radius:30px;border:2px solid rgba(245,217,122,.9);color:#F5D97A;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:.06em;transition:background .2s,border-color .2s,color .2s;background:rgba(201,168,76,.18);text-shadow:0 1px 4px rgba(0,0,0,.8)}
.ex305-video-caption a:hover{background:rgba(201,168,76,.35);border-color:#F5D97A;color:#fff}
/* Google Translate — explore */
.goog-te-gadget{font-size:0!important;line-height:0!important}
.goog-logo-link,.goog-te-gadget>span{display:none!important}
.goog-te-combo{appearance:none;-webkit-appearance:none;display:inline-block;padding:6px 12px;background:rgba(255,255,255,.93);color:#56075E;border:3px solid #56075E;border-radius:30px;font-size:13px;font-weight:600;cursor:pointer;outline:none;font-family:"Poppins",sans-serif;white-space:nowrap;max-width:160px;transition:background .2s}
.goog-te-combo:hover{background:#f5d6ff}
.goog-te-banner-frame,.goog-te-banner-frame.skiptranslate{display:none!important;visibility:hidden!important}
html.translated-ltr,html.translated-rtl{overflow-y:auto!important;height:auto!important;max-height:none!important}
body.translated-ltr,body.translated-rtl{overflow-y:auto!important;height:auto!important;max-height:none!important;top:0!important;margin-top:0!important;position:relative!important}
.translated-ltr .ex-wrapper,.translated-rtl .ex-wrapper{overflow:visible!important;height:auto!important;min-height:100vh}

/* Footer */
.ex305-footer{padding:32px 6%;border-top:1px solid rgba(201,168,76,.35);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px}
.ex305-footer a{color:rgba(255,255,255,.9);font-size:15px;font-weight:600;text-decoration:none;transition:color .2s;text-shadow:0 1px 6px rgba(0,0,0,.9)}
.ex305-footer a:hover{color:#F5D97A}
.ex305-footer-brand{font-size:14px;font-weight:700;letter-spacing:.2em;color:#F5D97A;text-shadow:0 1px 8px rgba(0,0,0,.9)}

/* Responsive */
@media(max-width:768px){
  .ex305-vision-inner{grid-template-columns:1fr;gap:40px}
  .ex305-bottle-wrap{order:-1}
  .ex305-bottle-img{width:220px}
  .ex305-tiers-grid{grid-template-columns:1fr;max-width:480px}
  .ex305-tier-card.ex305-featured{margin-top:14px}
  .ex305-footer{justify-content:center;text-align:center}
}
@media(max-width:430px){
  .ex305-vision{padding:100px 5% 60px}
  .ex305-tier-card{padding:28px 22px 32px}
}
</style>
</head>
<body>

<nav class="ex305-nav" aria-label="Site navigation">
  <div class="ex305-nav-left">
    <div class="ex305-nav-login-group">
      <a href="/member-login/">Login</a>
      <a href="/" class="ex305-nav-home">← Back to Home</a>
    </div>
    <div id="ex-translate-ex"></div>
  </div>
  <div class="ex305-nav-logo"><img src="<?= $logo ?>" alt="Excreet"></div>
</nav>

<!-- VIDEO FIRST -->
<section class="ex305-video-section">
  <span class="ex305-warning-tagline">A Pre-Clinical Warning System.</span>
  <h2 class="ex305-section-h2">Watch How Excreet Works</h2>
  <div class="ex305-video-wrap">
    <video controls preload="metadata" controlslist="nodownload" playsinline>
      <source src="<?= $video_url ?>" type="video/mp4">
      <track kind="subtitles" srclang="es" label="Español"
             src="<?= $base ?>/wp-content/uploads/2026/05/excreet-explainer-es.vtt"
             default="">
    </video>
    <p class="ex305-video-caption"><span class="ex305-vid-lang">Video in English</span><a href="/es/explore/" hreflang="es">Ver en Español →</a></p>
  </div>
</section>

<div class="ex305-tagline-bar">
  <p>Your body warns every day. <span>Excreet translates that warning with &mdash; just your phone.</span></p>
</div>

<!-- VISION + BOTTLE BELOW VIDEO -->
<section class="ex305-vision">
  <div class="ex305-vision-inner">
    <div class="ex305-vision-copy">
      <span class="ex305-kicker">What Excreet Is</span>
      <h1 class="ex305-vision-h1">
        The storm builds long before<br>
        <em>you feel the first drop.</em>
      </h1>
      <p class="ex305-vision-bridge">Your body sends the signal years before a doctor finds anything. Excreet is built to read it.</p>
      <div class="ex305-vision-body">
        <p>Modern life is quietly rewriting your body's internal chemistry. Toxins, processed food, contaminated water, electromagnetic exposure — each seems manageable alone. Together, they <strong>shift your pH, drain your cellular energy, and erode your biology long before a single symptom appears.</strong></p>
        <p>By the time a doctor sees you, the storm has already broken. Medications manage. Surgery repairs. <strong>But neither goes back to where the damage started</strong> — at the cellular level, upstream of diagnosis.</p>
        <p><strong>Excreet intercepts the story before it is written.</strong> Daily body signal tracking through your phone's camera, AI pattern recognition, and a cellular supplement engineered to restore what modern life removes: electron balance, alkaline chemistry, cellular energy.</p>
        <p>Your body has been trying to tell you. <strong>Now you can hear it.</strong></p>
      </div>
    </div>
    <div class="ex305-bottle-wrap">
      <div class="ex305-bottle-glow"></div>
      <img class="ex305-bottle-img" src="<?= $base ?>/wp-content/uploads/2026/05/excreet-bottle-product.png" alt="Excreet — Cell Ready Minerals supplement bottle">
    </div>
  </div>
</section>

<section class="ex305-tiers-section">
  <div class="ex305-tiers-header">
    <span class="ex305-section-label">Choose Your Level</span>
    <h2 class="ex305-section-h2">Two ways to begin.<br>Both change the story.</h2>
    <p class="ex305-tiers-sub">Every Excreet membership gives you access to the Body Check intelligence platform — and automatically enrolls you as an affiliate. The tier you choose determines the depth of AI guidance you receive and the size of your affiliate earnings.</p>
  </div>
  <div class="ex305-tiers-grid">

    <div class="ex305-tier-card">
      <div class="ex305-tier-name">Starter</div>
      <div class="ex305-tier-price"><sup>$</sup>15<span>/mo</span></div>
      <div class="ex305-tier-period">Billed monthly · Cancel anytime</div>
      <div class="ex305-tier-divider"></div>
      <ul class="ex305-tier-features">
        <li>Daily Body Check Score</li>
        <li>Body pattern trends &amp; history</li>
        <li>Member Dashboard access</li>
        <li>Clinical Pattern Report (shareable with your provider)</li>
        <li class="ex305-hl">10 Ministry of Healing AI sessions per month</li>
        <li class="ex305-hl">$5 affiliate payout per referred member, per month</li>
      </ul>
      <div class="ex305-affiliate-callout">
        <p>Membership automatically includes affiliate status. Refer 3 active members while an active member yourself and your membership is covered. <strong>Paid every 2 weeks once $50 minimum is accumulated.</strong></p>
      </div>
      <a class="ex305-tier-cta ex305-cta-starter" href="/membership-checkout/?level=1">Start with Starter — $15/mo</a>
    </div>

    <div class="ex305-tier-card ex305-featured">
      <div class="ex305-tier-badge">Highest Earnings</div>
      <div class="ex305-tier-name">Premium</div>
      <div class="ex305-tier-price"><sup>$</sup>25<span>/mo</span></div>
      <div class="ex305-tier-period">Billed monthly · Cancel anytime</div>
      <div class="ex305-tier-divider"></div>
      <ul class="ex305-tier-features">
        <li>Everything in Starter, plus:</li>
        <li class="ex305-hl">20 Ministry of Healing AI sessions per month</li>
        <li>Deeper pattern analysis &amp; trend alerts</li>
        <li>Priority member support</li>
        <li class="ex305-hl">$10 affiliate payout per referred member, per month</li>
      </ul>
      <div class="ex305-affiliate-callout">
        <p>Double the affiliate earnings of Starter. Refer just 3 active members and your membership pays for itself — with income left over. <strong>Paid every 2 weeks once $50 minimum is accumulated. Both tiers require an active membership to receive payouts.</strong></p>
      </div>
      <a class="ex305-tier-cta ex305-cta-premium" href="/membership-checkout/?level=2">Join at Premium — $25/mo</a>
    </div>

  </div>
  <p class="ex305-tiers-note">
    All memberships include affiliate status from day one.<br>
    You can upgrade from Starter to Premium at any time from your member dashboard.
  </p>
</section>

<footer class="ex305-footer">
  <a href="/">← Back to Home</a>
  <div class="ex305-footer-brand">EXCREET · A PRE-CLINICAL WARNING SYSTEM.</div>
  <a href="/membership-checkout/?level=1">Become a Member →</a>
</footer>
<script>function googleTranslateElementInit(){new google.translate.TranslateElement({pageLanguage:'en',autoDisplay:false},'ex-translate-ex');}</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async></script>
</body>
</html>
<?php
    exit;
}
/* ── End explore ───────────────────────────────────────────── */


/* ═══════════════════════════════════════════════════════
   LOGIN PAGE  GET /member-login/
   Runs entirely before WordPress — PMPro cannot intercept.
   Form posts to /wp-login.php which handles auth natively.
   Path renamed from /login/ to /member-login/ (v3.0.6) to escape
   SiteGround nginx cache that had the old PMPro page stuck in /login/.
   ═══════════════════════════════════════════════════════ */
if ( $req_path === '/member-login' && $req_method === 'GET' ) {

    $logo_url  = $base . '/wp-content/uploads/2026/05/excreet-hero-logo.png';
    $favicon   = $base . '/wp-content/uploads/2025/11/cropped-favicon-32x32.png';
    $bg_month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url    = $base . '/wp-content/uploads/healer-bg-' . $bg_month . '.jpg';

    $redirect_to  = isset( $_GET['redirect_to'] ) ? htmlspecialchars( $_GET['redirect_to'], ENT_QUOTES ) : $base . '/welcome-member/';
    $logged_out   = isset( $_GET['loggedout'] ) && $_GET['loggedout'] === 'true';
    $login_failed = isset( $_GET['login'] ) && $_GET['login'] === 'failed';

    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'X-Ex-Patch: 307-index-login' );

    echo '<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Member Login — Excreet</title>
<link rel="icon" href="' . $favicon . '" sizes="32x32">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;font-family:"Inter",sans-serif;background:#0f0320;color:#f0e8ff}
.ex307-bg{position:fixed;inset:0;background:linear-gradient(to bottom,rgba(8,2,20,.72) 0%,rgba(8,2,20,.45) 45%,rgba(8,2,20,.78) 100%),url("' . $bg_url . '") center/cover no-repeat;z-index:0}
.ex307-shell{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem 1.5rem}
.ex307-logo-wrap{display:flex;flex-direction:column;align-items:center;gap:.75rem;margin-bottom:2rem}
.ex307-logo-img{width:110px;height:110px;object-fit:contain;filter:drop-shadow(0 0 22px rgba(212,175,55,.5)) drop-shadow(0 0 8px rgba(140,60,200,.4))}
.ex307-wordmark{font-family:"Cormorant Garamond",serif;font-size:1.35rem;font-weight:700;letter-spacing:.32em;color:#ffe066;text-transform:uppercase;text-shadow:0 0 18px rgba(255,220,80,.7),0 0 40px rgba(255,200,40,.35),0 1px 0 rgba(0,0,0,.6)}
.ex307-card{background:rgba(15,3,32,.75);border:1px solid rgba(212,175,55,.2);border-radius:16px;padding:2.5rem 2rem;width:100%;max-width:380px;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);box-shadow:0 8px 40px rgba(0,0,0,.5)}
.ex307-card-title{font-family:"Cormorant Garamond",serif;font-size:1.7rem;font-weight:400;text-align:center;color:#f0e8ff;margin-bottom:.25rem}
.ex307-card-sub{font-size:.82rem;color:rgba(240,232,255,.5);text-align:center;margin-bottom:1.75rem;font-weight:300}
.ex307-notice{padding:.7rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1.25rem}
.ex307-notice--success{background:rgba(91,45,142,.25);border:1px solid rgba(212,175,55,.3);color:#d4af37}
.ex307-notice--error{background:rgba(180,30,30,.2);border:1px solid rgba(220,80,80,.35);color:#fca5a5}
.ex307-card form{display:flex;flex-direction:column;gap:1rem}
.ex307-field{display:flex;flex-direction:column;gap:.4rem}
.ex307-field label{font-size:.78rem;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:rgba(240,232,255,.6)}
.ex307-field input[type="text"],.ex307-field input[type="password"],.ex307-field input[type="email"]{width:100%;padding:.75rem 1rem;border-radius:8px;border:1px solid rgba(212,175,55,.25);background:rgba(255,255,255,.06);color:#f0e8ff;font-family:"Inter",sans-serif;font-size:.95rem;outline:none;transition:border-color .18s,background .18s}
.ex307-field input:focus{border-color:rgba(212,175,55,.6);background:rgba(255,255,255,.09)}
.ex307-field input::placeholder{color:rgba(240,232,255,.25)}
.ex307-remember{display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:rgba(240,232,255,.5);cursor:pointer}
.ex307-remember input[type="checkbox"]{width:15px;height:15px;accent-color:#7b3fc4;cursor:pointer}
.ex307-submit{width:100%;padding:.85rem 1rem;border-radius:10px;border:none;background:linear-gradient(135deg,#5b2d8e 0%,#7b3fc4 100%);color:#fff;font-family:"Inter",sans-serif;font-size:1rem;font-weight:600;letter-spacing:.02em;cursor:pointer;transition:transform .15s,box-shadow .15s;box-shadow:0 4px 18px rgba(91,45,142,.4);margin-top:.25rem}
.ex307-submit:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(91,45,142,.55)}
.ex307-links{display:flex;justify-content:space-between;margin-top:1.25rem;font-size:.78rem}
.ex307-links a{color:rgba(212,175,55,.7);text-decoration:none;letter-spacing:.02em;transition:color .15s}
.ex307-links a:hover{color:#d4af37}
.ex307-footer{margin-top:1.75rem;font-size:.78rem;color:rgba(240,232,255,.75);text-align:center;letter-spacing:.03em;line-height:1.6;text-shadow:0 1px 4px rgba(0,0,0,.8)}
.ex307-footer a{color:#ffe066;text-decoration:underline;text-underline-offset:2px;text-decoration-color:rgba(255,220,80,.45)}
.ex307-footer a:hover{color:#fff;text-decoration-color:rgba(255,255,255,.6)}
@media(max-width:440px){.ex307-card{padding:2rem 1.5rem;border-radius:14px}}
</style>
</head>
<body>
<div class="ex307-bg"></div>
<div class="ex307-shell">
  <div class="ex307-logo-wrap">
    <img class="ex307-logo-img" src="' . $logo_url . '" alt="Excreet logo">
    <span class="ex307-wordmark">Excreet</span>
  </div>
  <div class="ex307-card">
    <h1 class="ex307-card-title">Member Login</h1>
    <p class="ex307-card-sub">Enter your credentials to access your portal</p>';

    if ( $logged_out ) {
        echo '<div class="ex307-notice ex307-notice--success">You have been signed out.</div>';
    }
    if ( $login_failed ) {
        echo '<div class="ex307-notice ex307-notice--error">Incorrect username or password. Please try again.</div>';
    }

    echo '
    <form name="loginform" id="loginform" action="' . $base . '/wp-login.php" method="post">
      <div class="ex307-field">
        <label for="user_login">Username or Email</label>
        <input type="text" name="log" id="user_login" autocomplete="username" placeholder="you@example.com" required>
      </div>
      <div class="ex307-field">
        <label for="user_pass">Password</label>
        <input type="password" name="pwd" id="user_pass" autocomplete="current-password" placeholder="••••••••" required>
      </div>
      <label class="ex307-remember">
        <input type="checkbox" name="rememberme" id="rememberme" value="forever">
        Remember me
      </label>
      <input type="hidden" name="redirect_to" value="' . $redirect_to . '">
      <input type="hidden" name="testcookie" value="1">
      <button type="submit" class="ex307-submit">Sign In</button>
    </form>
    <div class="ex307-links">
      <a href="' . $base . '/explore/">← Not a member?</a>
      <a href="' . $base . '/wp-login.php?action=lostpassword">Forgot password?</a>
    </div>
  </div>
  <p class="ex307-footer">
    By signing in you agree to our
    <a href="' . $base . '/terms-conditions/">Terms</a>
    and
    <a href="' . $base . '/privacy-policy/">Privacy Policy</a>.
  </p>
</div>
</body>
</html>';
    exit;
}
/* ── End login ──────────────────────────────────────────────── */


/* ═══════════════════════════════════════════════════════
   WORDPRESS FALLBACK — all other paths
   ═══════════════════════════════════════════════════════ */
define( 'WP_USE_THEMES', true );
require __DIR__ . '/wp-blog-header.php';
