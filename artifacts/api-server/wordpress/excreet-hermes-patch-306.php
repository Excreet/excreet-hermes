<?php
/**
 * Plugin Name: Excreet Patch 306 — Welcome Member Page Override
 * Description: Full template override for /welcome-member/ — correct button labels
 *              ("Today's Body Check" / "Member Dashboard"), brand styling,
 *              auth gate, and personalized greeting. Replaces stale WP page content.
 * Version: 3.0.6
 */

add_action( 'template_redirect', 'excreet_306_welcome_override', 1 );

function excreet_306_welcome_override(): void {
    $path = rtrim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
    if ( $path !== '/welcome-member' ) return;

    // Auth gate — redirect to login if not logged in
    if ( ! is_user_logged_in() ) {
        wp_redirect( home_url( '/login/?redirect_to=/welcome-member/' ) );
        exit;
    }

    $base     = 'https://excreet.com';
    $logo_url = $base . '/wp-content/uploads/2025/11/cropped-favicon-32x32.png';
    $bg_url   = $base . '/wp-content/uploads/2026/04/excreet-healer-bg-1.jpg';

    $current_user = wp_get_current_user();
    $first_name   = ! empty( $current_user->first_name ) ? $current_user->first_name : $current_user->display_name;

    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'X-Ex-Patch: 306-welcome' );
    ?><!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Welcome — Excreet Member Portal</title>
<link rel="icon" href="<?= esc_url( $logo_url ) ?>" sizes="32x32">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  height: 100%;
  font-family: 'Inter', sans-serif;
  background: #0f0320;
  color: #f0e8ff;
  overflow: hidden;
}

/* ── Full-page background ── */
.ex306-bg {
  position: fixed;
  inset: 0;
  background:
    linear-gradient(to bottom, rgba(15,3,32,0.55) 0%, rgba(15,3,32,0.35) 40%, rgba(15,3,32,0.65) 100%),
    url('<?= esc_url( $bg_url ) ?>') center center / cover no-repeat;
  z-index: 0;
}

/* ── Layout shell ── */
.ex306-shell {
  position: relative;
  z-index: 1;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem 1.5rem;
  gap: 0;
}

/* ── Logo ── */
.ex306-logo-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 2.5rem;
}
.ex306-logo-img {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  border: 2px solid rgba(212,175,55,0.5);
  background: rgba(15,3,32,0.6);
  padding: 4px;
}
.ex306-wordmark {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.1rem;
  letter-spacing: 0.25em;
  color: #d4af37;
  text-transform: uppercase;
}

/* ── Greeting ── */
.ex306-greeting {
  font-family: 'Cormorant Garamond', serif;
  font-size: clamp(2rem, 5vw, 3rem);
  font-weight: 400;
  line-height: 1.2;
  text-align: center;
  color: #f0e8ff;
  margin-bottom: 0.4rem;
}
.ex306-greeting em {
  font-style: italic;
  color: #d4af37;
}

.ex306-sub {
  font-size: clamp(0.9rem, 2vw, 1.05rem);
  color: rgba(240,232,255,0.65);
  text-align: center;
  font-weight: 300;
  letter-spacing: 0.02em;
  margin-bottom: 3rem;
}

/* ── Navigation cards ── */
.ex306-nav {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 100%;
  max-width: 380px;
}

.ex306-btn {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.1rem 1.5rem;
  border-radius: 12px;
  text-decoration: none;
  font-size: 1rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
  cursor: pointer;
  border: none;
}
.ex306-btn:hover {
  transform: translateY(-2px);
}

.ex306-btn-primary {
  background: linear-gradient(135deg, #5b2d8e 0%, #7b3fc4 100%);
  color: #fff;
  box-shadow: 0 4px 20px rgba(91,45,142,0.45);
}
.ex306-btn-primary:hover {
  background: linear-gradient(135deg, #6b3da0 0%, #8b4fd4 100%);
  box-shadow: 0 6px 28px rgba(91,45,142,0.6);
}

.ex306-btn-secondary {
  background: rgba(255,255,255,0.07);
  color: #f0e8ff;
  border: 1px solid rgba(212,175,55,0.35);
  box-shadow: 0 2px 12px rgba(0,0,0,0.25);
}
.ex306-btn-secondary:hover {
  background: rgba(212,175,55,0.12);
  border-color: rgba(212,175,55,0.6);
  box-shadow: 0 4px 18px rgba(212,175,55,0.2);
}

.ex306-btn-icon {
  flex-shrink: 0;
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: rgba(255,255,255,0.12);
  display: flex;
  align-items: center;
  justify-content: center;
}

.ex306-btn-body {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}
.ex306-btn-label {
  font-weight: 600;
  font-size: 1rem;
}
.ex306-btn-hint {
  font-size: 0.78rem;
  font-weight: 300;
  opacity: 0.7;
}

/* ── Divider ── */
.ex306-divider {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: rgba(240,232,255,0.3);
  font-size: 0.75rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}
.ex306-divider::before,
.ex306-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(240,232,255,0.15);
}

/* ── Footer note ── */
.ex306-footer {
  margin-top: 3rem;
  font-size: 0.75rem;
  color: rgba(240,232,255,0.3);
  text-align: center;
  letter-spacing: 0.03em;
}
.ex306-footer a {
  color: rgba(212,175,55,0.6);
  text-decoration: none;
}
.ex306-footer a:hover { color: #d4af37; }

/* ── Mobile ── */
@media (max-width: 480px) {
  .ex306-shell { justify-content: center; padding: 1.5rem 1rem; }
  .ex306-logo-wrap { margin-bottom: 2rem; }
  .ex306-sub { margin-bottom: 2.25rem; }
  .ex306-btn { padding: 1rem 1.25rem; }
}
</style>
</head>
<body>

<div class="ex306-bg"></div>

<div class="ex306-shell">

  <!-- Logo -->
  <div class="ex306-logo-wrap">
    <img class="ex306-logo-img" src="<?= esc_url( $logo_url ) ?>" alt="Excreet">
    <span class="ex306-wordmark">Excreet</span>
  </div>

  <!-- Greeting -->
  <h1 class="ex306-greeting">
    Welcome<?php if ( $first_name ) : ?>, <em><?= esc_html( $first_name ) ?></em><?php endif; ?>.
  </h1>
  <p class="ex306-sub">How would you like to proceed?</p>

  <!-- Navigation -->
  <nav class="ex306-nav" aria-label="Member navigation">

    <!-- Primary: Body Check -->
    <a class="ex306-btn ex306-btn-primary" href="<?= esc_url( home_url( '/healing-command-center/' ) ) ?>">
      <div class="ex306-btn-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
      </div>
      <div class="ex306-btn-body">
        <span class="ex306-btn-label">Today's Body Check</span>
        <span class="ex306-btn-hint">Submit today's saliva, urine &amp; bowel observations</span>
      </div>
    </a>

    <div class="ex306-divider">or</div>

    <!-- Secondary: Member Dashboard -->
    <a class="ex306-btn ex306-btn-secondary" href="<?= esc_url( home_url( '/member-dashboard/' ) ) ?>">
      <div class="ex306-btn-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7"/>
          <rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/>
          <rect x="3" y="14" width="7" height="7"/>
        </svg>
      </div>
      <div class="ex306-btn-body">
        <span class="ex306-btn-label">Member Dashboard</span>
        <span class="ex306-btn-hint">View your scores, patterns, and health history</span>
      </div>
    </a>

  </nav>

  <p class="ex306-footer">
    <a href="<?= esc_url( home_url( '/membership-account/' ) ) ?>">My Account</a>
    &nbsp;·&nbsp;
    <a href="<?= esc_url( wp_logout_url( home_url( '/' ) ) ) ?>">Sign Out</a>
  </p>

</div>

</body>
</html>
<?php
    exit;
}
