<?php
/**
 * Plugin Name: Excreet Patch 307 — Login Page Override
 * Description: Full branded template override for /login/ — replaces plain WP
 *              default with dark botanical layout, Excreet design system, and
 *              PMPro-aware redirect handling. Uses wp_login_form() for secure auth.
 * Version: 3.0.7
 */

// Fire as early as possible — before PMPro can intercept and redirect.
// `wp` fires after the main query but before template_redirect, catching
// any PMPro wp_redirect() + exit calls that happen in `wp` at priority ≥ 0.
add_action( 'wp',                'excreet_307_login_override', -999 );
add_action( 'template_redirect', 'excreet_307_login_override', -999 );

function excreet_307_login_override(): void {
    static $fired = false;
    if ( $fired ) return;

    $path = rtrim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
    if ( $path !== '/login' ) return;

    $fired = true;

    // If already logged in, send home
    if ( is_user_logged_in() ) {
        $redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( $_GET['redirect_to'] ) : home_url( '/welcome-member/' );
        wp_redirect( $redirect );
        exit;
    }

    $base        = 'https://excreet.com';
    $logo_url    = $base . '/wp-content/uploads/2026/05/excreet-hero-logo.png';
    $favicon_url = $base . '/wp-content/uploads/2025/11/cropped-favicon-32x32.png';
    $month       = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $ver         = date( 'Ym' );
    $bg_url      = $base . '/wp-content/uploads/healer-bg-' . $month . '.jpg?v=' . $ver;
    $redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( $_GET['redirect_to'] ) : home_url( '/welcome-member/' );

    // Handle logout message
    $logged_out  = isset( $_GET['loggedout'] ) && $_GET['loggedout'] === 'true';

    // Handle login errors passed via query string (PMPro redirect)
    $login_error = '';
    if ( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) {
        $login_error = 'Incorrect username or password. Please try again.';
    }

    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'X-Ex-Patch: 307-login' );
    ?><!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Member Login — Excreet</title>
<link rel="icon" href="<?= esc_url( $favicon_url ) ?>" sizes="32x32">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  min-height: 100%;
  font-family: 'Inter', sans-serif;
  background: #0f0320;
  color: #f0e8ff;
}

/* ── Background — vision image as full-page backdrop ── */
.ex307-bg {
  position: fixed;
  inset: 0;
  background:
    linear-gradient(to bottom, rgba(8,2,20,0.72) 0%, rgba(8,2,20,0.45) 45%, rgba(8,2,20,0.78) 100%),
    url('<?= esc_url( $bg_url ) ?>') center center / cover no-repeat;
  z-index: 0;
}

/* ── Layout ── */
.ex307-shell {
  position: relative;
  z-index: 1;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem 1.5rem;
}

/* ── Logo ── */
.ex307-logo-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 2rem;
}
.ex307-logo-img {
  width: 110px;
  height: 110px;
  object-fit: contain;
  filter: drop-shadow(0 0 22px rgba(212,175,55,0.5)) drop-shadow(0 0 8px rgba(140,60,200,0.4));
}
.ex307-wordmark {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.35rem;
  font-weight: 700;
  letter-spacing: 0.32em;
  color: #ffe066;
  text-transform: uppercase;
  text-shadow:
    0 0 18px rgba(255,220,80,0.7),
    0 0 40px rgba(255,200,40,0.35),
    0 1px 0 rgba(0,0,0,0.6);
}

/* ── Card ── */
.ex307-card {
  background: rgba(15,3,32,0.75);
  border: 1px solid rgba(212,175,55,0.2);
  border-radius: 16px;
  padding: 2.5rem 2rem;
  width: 100%;
  max-width: 380px;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow: 0 8px 40px rgba(0,0,0,0.5);
}

.ex307-card-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.7rem;
  font-weight: 400;
  text-align: center;
  color: #f0e8ff;
  margin-bottom: 0.25rem;
}
.ex307-card-sub {
  font-size: 0.82rem;
  color: rgba(240,232,255,0.5);
  text-align: center;
  margin-bottom: 1.75rem;
  font-weight: 300;
}

/* ── Notice ── */
.ex307-notice {
  padding: 0.7rem 1rem;
  border-radius: 8px;
  font-size: 0.85rem;
  margin-bottom: 1.25rem;
  font-weight: 400;
  letter-spacing: 0.01em;
}
.ex307-notice--success {
  background: rgba(91,45,142,0.25);
  border: 1px solid rgba(212,175,55,0.3);
  color: #d4af37;
}
.ex307-notice--error {
  background: rgba(180,30,30,0.2);
  border: 1px solid rgba(220,80,80,0.35);
  color: #fca5a5;
}

/* ── Form ── */
.ex307-card form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.ex307-field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.ex307-field label {
  font-size: 0.78rem;
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgba(240,232,255,0.6);
}
.ex307-field input[type="text"],
.ex307-field input[type="password"],
.ex307-field input[type="email"] {
  width: 100%;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  border: 1px solid rgba(212,175,55,0.25);
  background: rgba(255,255,255,0.06);
  color: #f0e8ff;
  font-family: 'Inter', sans-serif;
  font-size: 0.95rem;
  outline: none;
  transition: border-color 0.18s ease, background 0.18s ease;
}
.ex307-field input:focus {
  border-color: rgba(212,175,55,0.6);
  background: rgba(255,255,255,0.09);
}
.ex307-field input::placeholder { color: rgba(240,232,255,0.25); }

/* ── Remember me ── */
.ex307-remember {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.82rem;
  color: rgba(240,232,255,0.5);
  cursor: pointer;
}
.ex307-remember input[type="checkbox"] {
  width: 15px;
  height: 15px;
  accent-color: #7b3fc4;
  cursor: pointer;
}

/* ── Submit ── */
.ex307-submit {
  width: 100%;
  padding: 0.85rem 1rem;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, #5b2d8e 0%, #7b3fc4 100%);
  color: #fff;
  font-family: 'Inter', sans-serif;
  font-size: 1rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  box-shadow: 0 4px 18px rgba(91,45,142,0.4);
  margin-top: 0.25rem;
}
.ex307-submit:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 24px rgba(91,45,142,0.55);
}
.ex307-submit:active { transform: translateY(0); }

/* ── Links ── */
.ex307-links {
  display: flex;
  justify-content: space-between;
  margin-top: 1.25rem;
  font-size: 0.78rem;
}
.ex307-links a {
  color: rgba(212,175,55,0.7);
  text-decoration: none;
  letter-spacing: 0.02em;
  transition: color 0.15s;
}
.ex307-links a:hover { color: #d4af37; }

/* ── Footer ── */
.ex307-footer {
  margin-top: 1.75rem;
  font-size: 0.78rem;
  color: rgba(240,232,255,0.75);
  text-align: center;
  letter-spacing: 0.03em;
  line-height: 1.6;
  text-shadow: 0 1px 4px rgba(0,0,0,0.8);
}
.ex307-footer a {
  color: #ffe066;
  text-decoration: underline;
  text-underline-offset: 2px;
  text-decoration-color: rgba(255,220,80,0.45);
}
.ex307-footer a:hover {
  color: #fff;
  text-decoration-color: rgba(255,255,255,0.6);
}

/* ── Mobile ── */
@media (max-width: 440px) {
  .ex307-card { padding: 2rem 1.5rem; border-radius: 14px; }
}
</style>
</head>
<body>

<?php wp_head(); ?>

<div class="ex307-bg"></div>

<div class="ex307-shell">

  <!-- Logo -->
  <div class="ex307-logo-wrap">
    <img class="ex307-logo-img" src="<?= esc_url( $logo_url ) ?>" alt="Excreet logo">
    <span class="ex307-wordmark">Excreet</span>
  </div>

  <!-- Card -->
  <div class="ex307-card">

    <h1 class="ex307-card-title">Member Login</h1>
    <p class="ex307-card-sub">Enter your credentials to access your portal</p>

    <?php if ( $logged_out ) : ?>
    <div class="ex307-notice ex307-notice--success">You have been signed out.</div>
    <?php endif; ?>

    <?php if ( $login_error ) : ?>
    <div class="ex307-notice ex307-notice--error"><?= esc_html( $login_error ) ?></div>
    <?php endif; ?>

    <form name="loginform" id="loginform" action="<?= esc_url( site_url( 'wp-login.php', 'login_post' ) ) ?>" method="post">

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

      <input type="hidden" name="redirect_to" value="<?= esc_attr( $redirect_to ) ?>">
      <input type="hidden" name="testcookie" value="1">

      <button type="submit" class="ex307-submit">Sign In</button>

    </form>

    <div class="ex307-links">
      <a href="<?= esc_url( home_url( '/explore/' ) ) ?>">← Not a member?</a>
      <a href="<?= esc_url( wp_lostpassword_url( home_url( '/login/' ) ) ) ?>">Forgot password?</a>
    </div>

  </div>

  <p class="ex307-footer">
    By signing in you agree to our
    <a href="<?= esc_url( home_url( '/terms-conditions/' ) ) ?>">Terms</a>
    and
    <a href="<?= esc_url( home_url( '/privacy-policy/' ) ) ?>">Privacy Policy</a>.
  </p>

</div>

<?php wp_footer(); ?>
</body>
</html>
<?php
    exit;
}
