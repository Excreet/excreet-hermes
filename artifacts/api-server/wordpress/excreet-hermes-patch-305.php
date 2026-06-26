<?php
/**
 * Plugin Name: Excreet Patch 305 — Explore Page Override
 * Description: Replaces /explore/ with a clean full-page layout: vision copy, explainer video,
 *              and Starter vs Premium membership tier comparison with affiliate callout.
 * Version: 3.0.6
 */

add_action( 'template_redirect', 'excreet_305_explore_override', 1 );

function excreet_305_explore_override(): void {
    $path = rtrim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
    if ( $path !== '/explore' ) return;

    $base      = 'https://excreet.com';
    $video_url = $base . '/wp-content/uploads/2026/04/Excreet_SaaS_Explainer_Video_16x9.mp4';
    $logo_url  = $base . '/wp-content/uploads/2025/11/cropped-favicon-32x32.png';
    $bottle_url = $base . '/wp-content/uploads/2026/04/Excreet-bottle-mockup.png';

    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Cache-Control: no-store' );
    header( 'X-Ex-Patch: 305-explore' );
    ?><!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Explore Excreet — Body Intelligence Platform</title>
<link rel="icon" href="<?= $logo_url ?>" sizes="32x32">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
  --purple:#6B21A8;--purple-light:#9333ea;--gold:#C9A84C;--gold-light:#F5D97A;
  --dark:#0f0520;--dark-2:#1a0535;--dark-3:#2d0a50;--dark-card:#1e0a38;
  --text:#f0e8ff;--muted:rgba(240,232,255,.6)
}
html{scroll-behavior:smooth}
body{
  font-family:'Poppins',sans-serif;background:var(--dark);color:var(--text);
  min-height:100vh;overflow-x:hidden
}

/* ── Nav ── */
.ex305-nav{
  position:fixed;top:0;left:0;right:0;z-index:100;
  padding:14px 28px;display:flex;align-items:center;justify-content:space-between;
  background:linear-gradient(to bottom,rgba(15,5,32,.95) 0%,rgba(15,5,32,0) 100%);
  backdrop-filter:blur(4px)
}
.ex305-nav-left{display:flex;gap:10px}
.ex305-nav a{
  display:inline-block;padding:7px 20px;background:transparent;color:#fff;
  border:2px solid rgba(255,255,255,.5);border-radius:30px;
  font-size:13px;font-weight:600;text-decoration:none;transition:all .2s
}
.ex305-nav a:hover{background:rgba(255,255,255,.12);border-color:#fff}
.ex305-nav-logo{
  font-size:15px;font-weight:700;color:var(--gold);letter-spacing:.2em
}

/* ── Vision Hero ── */
.ex305-vision{
  min-height:100vh;
  background:linear-gradient(160deg,var(--dark) 0%,var(--dark-3) 50%,var(--dark-2) 100%);
  display:flex;align-items:center;padding:120px 6% 80px;
  position:relative;overflow:hidden
}
.ex305-vision::before{
  content:'';position:absolute;top:-30%;right:-10%;
  width:65vw;height:65vw;max-width:700px;max-height:700px;
  border-radius:50%;
  background:radial-gradient(circle,rgba(107,33,168,.25) 0%,transparent 70%);
  pointer-events:none
}
.ex305-vision-inner{
  max-width:1100px;margin:0 auto;display:grid;
  grid-template-columns:1fr 1fr;gap:60px;align-items:center;width:100%
}
.ex305-vision-copy{}
.ex305-kicker{
  font-size:11px;font-weight:600;letter-spacing:.25em;text-transform:uppercase;
  color:var(--gold);margin-bottom:20px;display:block
}
.ex305-vision-h1{
  font-size:clamp(26px,3.5vw,46px);font-weight:700;line-height:1.2;
  color:#fff;margin-bottom:28px
}
.ex305-vision-h1 em{
  font-style:normal;color:var(--gold-light)
}
.ex305-vision-body{
  font-size:clamp(14px,1.3vw,17px);line-height:1.75;color:var(--muted);
  font-weight:300
}
.ex305-vision-body p+p{margin-top:16px}
.ex305-vision-body strong{color:var(--text);font-weight:600}

.ex305-bottle-wrap{
  display:flex;justify-content:center;align-items:center;position:relative
}
.ex305-bottle-glow{
  position:absolute;width:80%;height:80%;
  background:radial-gradient(circle,rgba(201,168,76,.18) 0%,transparent 65%);
  border-radius:50%;pointer-events:none
}
.ex305-bottle-img{
  width:100%;max-width:340px;height:auto;
  filter:drop-shadow(0 20px 60px rgba(201,168,76,.25));
  position:relative;z-index:1
}
.ex305-bottle-placeholder{
  width:260px;height:380px;border-radius:16px;
  border:2px solid rgba(201,168,76,.3);
  background:linear-gradient(160deg,rgba(107,33,168,.3),rgba(201,168,76,.08));
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:12px;color:var(--gold);text-align:center;padding:24px;position:relative;z-index:1
}
.ex305-bottle-placeholder .ex305-logo-ring{
  width:80px;height:80px;border-radius:50%;
  border:3px solid var(--gold);display:flex;align-items:center;justify-content:center;
  font-size:36px;font-weight:700;color:var(--gold)
}
.ex305-bottle-label{
  font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;
  color:rgba(201,168,76,.7)
}

/* ── Tagline bar ── */
.ex305-tagline-bar{
  background:linear-gradient(90deg,var(--purple) 0%,#4a1275 100%);
  padding:20px 6%;text-align:center
}
.ex305-tagline-bar p{
  font-size:clamp(14px,1.6vw,18px);font-weight:600;
  color:#fff;letter-spacing:.04em
}
.ex305-tagline-bar span{color:var(--gold-light)}

/* ── Video ── */
.ex305-video-section{
  padding:80px 6%;
  background:var(--dark-2);text-align:center
}
.ex305-section-label{
  font-size:11px;font-weight:600;letter-spacing:.25em;text-transform:uppercase;
  color:var(--gold);margin-bottom:12px;display:block
}
.ex305-section-h2{
  font-size:clamp(20px,2.5vw,32px);font-weight:700;color:#fff;margin-bottom:40px
}
.ex305-video-wrap{
  max-width:860px;margin:0 auto;border-radius:16px;overflow:hidden;
  box-shadow:0 0 80px rgba(107,33,168,.4),0 0 0 1px rgba(201,168,76,.2)
}
.ex305-video-wrap video{
  display:block;width:100%;height:auto;background:#000
}

/* ── Membership Tiers ── */
.ex305-tiers-section{
  padding:80px 6% 100px;
  background:linear-gradient(180deg,var(--dark-2) 0%,var(--dark) 100%)
}
.ex305-tiers-header{text-align:center;margin-bottom:52px}
.ex305-tiers-sub{
  font-size:clamp(13px,1.2vw,15px);color:var(--muted);
  max-width:580px;margin:16px auto 0;line-height:1.65;font-weight:300
}
.ex305-tiers-grid{
  display:grid;grid-template-columns:1fr 1fr;gap:28px;
  max-width:900px;margin:0 auto
}
.ex305-tier-card{
  background:var(--dark-card);border-radius:20px;padding:36px 32px 40px;
  border:1px solid rgba(107,33,168,.35);
  position:relative;display:flex;flex-direction:column;gap:0;
  transition:border-color .25s,box-shadow .25s
}
.ex305-tier-card:hover{
  border-color:rgba(201,168,76,.4);
  box-shadow:0 8px 60px rgba(107,33,168,.25)
}
.ex305-tier-card.ex305-featured{
  border-color:rgba(201,168,76,.5);
  box-shadow:0 0 0 2px rgba(201,168,76,.3),0 12px 60px rgba(107,33,168,.3)
}
.ex305-tier-badge{
  position:absolute;top:-14px;left:50%;transform:translateX(-50%);
  background:linear-gradient(90deg,#C9A84C,#a8873a);
  color:#1a0535;font-size:11px;font-weight:700;letter-spacing:.12em;
  text-transform:uppercase;padding:5px 18px;border-radius:20px;white-space:nowrap
}
.ex305-tier-name{
  font-size:13px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;
  color:var(--muted);margin-bottom:10px
}
.ex305-tier-price{
  font-size:clamp(36px,4vw,52px);font-weight:700;color:#fff;line-height:1;
  margin-bottom:4px
}
.ex305-tier-price sup{font-size:.45em;vertical-align:.55em;color:var(--muted)}
.ex305-tier-price span{font-size:.35em;font-weight:400;color:var(--muted)}
.ex305-tier-period{font-size:13px;color:var(--muted);margin-bottom:28px}
.ex305-tier-divider{
  height:1px;background:rgba(255,255,255,.08);margin-bottom:24px
}
.ex305-tier-features{list-style:none;display:flex;flex-direction:column;gap:13px;flex:1}
.ex305-tier-features li{
  font-size:clamp(13px,1.1vw,14.5px);color:var(--text);
  display:flex;align-items:flex-start;gap:10px;line-height:1.45
}
.ex305-tier-features li::before{
  content:'✓';color:var(--gold);font-weight:700;flex-shrink:0;margin-top:1px
}
.ex305-tier-features li.ex305-highlight{color:var(--gold-light);font-weight:600}
.ex305-tier-features li.ex305-highlight::before{color:var(--gold-light)}
.ex305-affiliate-callout{
  margin-top:20px;padding:14px 16px;border-radius:10px;
  background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.25)
}
.ex305-affiliate-callout p{
  font-size:12.5px;line-height:1.5;color:rgba(240,232,255,.75)
}
.ex305-affiliate-callout strong{color:var(--gold);font-weight:700}
.ex305-tier-cta{
  display:block;margin-top:28px;padding:14px 24px;border-radius:50px;
  font-size:15px;font-weight:700;text-align:center;text-decoration:none;
  transition:opacity .2s,transform .15s;letter-spacing:.02em
}
.ex305-tier-cta:hover{opacity:.88;transform:translateY(-2px)}
.ex305-cta-starter{background:#6B21A8;color:#fff;border:2px solid rgba(255,255,255,.2)}
.ex305-cta-premium{
  background:linear-gradient(135deg,#C9A84C,#a8873a);
  color:#1a0535;border:none
}
.ex305-tiers-note{
  text-align:center;margin-top:36px;font-size:12px;
  color:rgba(240,232,255,.35);line-height:1.6
}

/* ── Footer nav ── */
.ex305-footer{
  padding:32px 6%;border-top:1px solid rgba(255,255,255,.06);
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px
}
.ex305-footer a{
  color:var(--muted);font-size:13px;text-decoration:none;transition:color .2s
}
.ex305-footer a:hover{color:var(--gold)}
.ex305-footer-brand{
  font-size:13px;font-weight:700;letter-spacing:.2em;color:var(--gold)
}

/* ── Responsive ── */
@media(max-width:768px){
  .ex305-vision-inner{grid-template-columns:1fr;gap:40px}
  .ex305-bottle-wrap{order:-1}
  .ex305-bottle-placeholder{width:200px;height:290px}
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

<!-- ── Nav ─────────────────────────────────────── -->
<nav class="ex305-nav" aria-label="Site navigation">
  <div class="ex305-nav-left">
    <a href="/login/">Login</a>
    <a href="#">Language</a>
  </div>
  <div class="ex305-nav-logo">EXCREET</div>
</nav>

<!-- ── Vision Hero ────────────────────────────── -->
<section class="ex305-vision">
  <div class="ex305-vision-inner">
    <div class="ex305-vision-copy">
      <span class="ex305-kicker">What Excreet Is</span>
      <h1 class="ex305-vision-h1">
        The storm builds long<br>
        before <em>you feel the first drop.</em>
      </h1>
      <div class="ex305-vision-body">
        <p>Modern life is quietly changing your body's internal chemistry. Environmental toxins, electromagnetic exposure, agricultural chemicals classified as probable carcinogens, contaminated water, industrially processed food — each alone seems manageable. Together, they <strong>shift your pH, drain your cellular voltage, and erode your biology long before a single symptom appears.</strong></p>
        <p>By the time a doctor sees you, the storm has already broken. Medications manage. Surgery repairs. But none of it goes back to where the damage started — at the cellular level, upstream of diagnosis.</p>
        <p><strong>Excreet is the warning system that intercepts the story before it's written.</strong> Daily body signal tracking, AI pattern recognition, and a cellular support supplement engineered to restore what modern life removes: electron balance, alkaline chemistry, and cellular energy.</p>
        <p>Your body has been trying to tell you. <strong>Now you can hear it.</strong></p>
      </div>
    </div>
    <div class="ex305-bottle-wrap">
      <div class="ex305-bottle-glow"></div>
      <div class="ex305-bottle-placeholder">
        <div class="ex305-logo-ring">e</div>
        <div style="font-size:17px;font-weight:700;letter-spacing:.15em;color:#fff">EXCREET</div>
        <div class="ex305-bottle-label">Internal Body Check<br>Cell Ready Formula</div>
        <div style="font-size:11px;color:rgba(201,168,76,.5);margin-top:8px;line-height:1.5">Whole Food Acquired<br>Dietary Supplement</div>
      </div>
    </div>
  </div>
</section>

<!-- ── Tagline Bar ────────────────────────────── -->
<div class="ex305-tagline-bar">
  <p>Your body warns every day. <span>Excreet translates that warning.</span></p>
</div>

<!-- ── Video ─────────────────────────────────── -->
<section class="ex305-video-section">
  <span class="ex305-section-label">See It In Action</span>
  <h2 class="ex305-section-h2">Watch How Excreet Works</h2>
  <div class="ex305-video-wrap">
    <video controls preload="metadata" controlslist="nodownload" playsinline>
      <source src="<?= $video_url ?>" type="video/mp4">
    </video>
  </div>
</section>

<!-- ── Membership Tiers ───────────────────────── -->
<section class="ex305-tiers-section">
  <div class="ex305-tiers-header">
    <span class="ex305-section-label">Choose Your Level</span>
    <h2 class="ex305-section-h2">Two ways to begin.<br>Both change the story.</h2>
    <p class="ex305-tiers-sub">Every Excreet membership gives you access to the Body Check intelligence platform. The tier you choose determines the depth of AI guidance you receive — and the financial opportunity available to you from day one.</p>
  </div>

  <div class="ex305-tiers-grid">

    <!-- Starter -->
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
        <li class="ex305-highlight">10 Ministry of Healing AI sessions per month</li>
      </ul>
      <a class="ex305-tier-cta ex305-cta-starter" href="/membership-checkout/?level=1">
        Start with Starter — $15/mo
      </a>
    </div>

    <!-- Premium -->
    <div class="ex305-tier-card ex305-featured">
      <div class="ex305-tier-badge">Most Opportunity</div>
      <div class="ex305-tier-name">Premium</div>
      <div class="ex305-tier-price"><sup>$</sup>25<span>/mo</span></div>
      <div class="ex305-tier-period">Billed monthly · Cancel anytime</div>
      <div class="ex305-tier-divider"></div>
      <ul class="ex305-tier-features">
        <li>Everything in Starter, plus:</li>
        <li class="ex305-highlight">20 Ministry of Healing AI sessions per month</li>
        <li>Deeper pattern analysis &amp; trend alerts</li>
        <li>Priority member support</li>
        <li class="ex305-highlight">$10 affiliate payout for every member you refer</li>
      </ul>
      <div class="ex305-affiliate-callout">
        <p>The affiliate opportunity is only available at the Premium tier. Refer just 3 members and your membership pays for itself — with income left over. <strong>This is the only point in the process where this choice is presented.</strong></p>
      </div>
      <a class="ex305-tier-cta ex305-cta-premium" href="/membership-checkout/?level=2">
        Join at Premium — $25/mo
      </a>
    </div>

  </div>

  <p class="ex305-tiers-note">
    All memberships include access to the Excreet Body Intelligence Platform.<br>
    You can upgrade from Starter to Premium at any time from your member dashboard.
  </p>
</section>

<!-- ── Footer nav ─────────────────────────────── -->
<footer class="ex305-footer">
  <a href="/">← Back to Home</a>
  <div class="ex305-footer-brand">EXCREET · A PRE-CLINICAL WARNING SYSTEM.</div>
  <a href="/membership-checkout/?level=1">Become a Member →</a>
</footer>

</body>
</html>
<?php
    exit;
}
