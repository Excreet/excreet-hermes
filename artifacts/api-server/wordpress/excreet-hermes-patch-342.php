<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.4.2
 * Description: Member Intake Form — zero-Elementor custom template.
 *
 *   Uses template_redirect to completely hijack the intake form page,
 *   bypassing Elementor entirely. Extracts the Forminator shortcode and
 *   welcome text from Elementor's stored JSON, then renders a 100%-custom
 *   HTML page:
 *
 *     • Full-screen healer-bg bathroom photo (homepage-identical overlay)
 *     • Narrow scrollable column (640px)
 *     • Dark header: EXCREET wordmark, circular logo, tagline, Back to Home
 *     • Dark welcome section: gold "Welcome." heading + descriptive paragraphs
 *     • White form card: Forminator questionnaire — bright white, clinical
 *       contrast, gold accents, dark labels, gold focus rings, gold submit pill
 *
 *   All Elementor CSS/JS is dequeued for this page. wp_head() and wp_footer()
 *   are retained so Forminator scripts load correctly.
 *
 * Version: 3.4.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ═══════════════════════════════════════════════════════════════════════════
   HELPER — extract welcome HTML + form shortcode from Elementor JSON
   ═══════════════════════════════════════════════════════════════════════════ */
function excreet_342_parse_elementor( int $post_id ): array {
    $raw   = get_post_meta( $post_id, '_elementor_data', true );
    $nodes = json_decode( $raw, true );
    if ( ! is_array( $nodes ) ) {
        return [ 'welcome' => '', 'form' => '' ];
    }

    $welcome = '';
    $form    = '';

    $walk = null;
    $walk = function ( array $elements ) use ( &$walk, &$welcome, &$form ): void {
        foreach ( $elements as $el ) {
            if ( ! empty( $el['elements'] ) ) {
                $walk( $el['elements'] );
            }
            if ( ( $el['elType'] ?? '' ) !== 'widget' ) { continue; }

            $type     = $el['widgetType'] ?? '';
            $settings = $el['settings']   ?? [];

            if ( $type === 'text-editor' && ! empty( $settings['editor'] ) ) {
                /* Skip the tiny "← Back to Home" one-liner */
                $text = wp_strip_all_tags( $settings['editor'] );
                if ( strlen( trim( $text ) ) > 80 ) {
                    $welcome .= $settings['editor'];
                }
            }
            if ( $type === 'shortcode' && ! empty( $settings['shortcode'] ) ) {
                if ( str_contains( $settings['shortcode'], 'forminator_form' ) ) {
                    $form = trim( $settings['shortcode'] );
                }
            }
        }
    };
    $walk( $nodes );

    return [ 'welcome' => $welcome, 'form' => $form ];
}

/* ═══════════════════════════════════════════════════════════════════════════
   DEQUEUE Elementor styles/scripts — only on the intake page
   ═══════════════════════════════════════════════════════════════════════════ */
add_action( 'wp_enqueue_scripts', 'excreet_342_dequeue_elementor', 9999 );
function excreet_342_dequeue_elementor(): void {
    if ( ! is_page( 'member-intake-form' ) ) { return; }

    /* Elementor frontend handles */
    $handles = [
        'elementor-frontend', 'elementor-post-8', 'elementor-icons',
        'elementor-animations', 'elementor-common', 'elementor-widgets',
        'hello-theme-style-css', 'hello-elementor', 'hello-elementor-theme-style',
    ];
    foreach ( $handles as $h ) {
        wp_dequeue_style( $h );
        wp_dequeue_script( $h );
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   TEMPLATE REDIRECT — full custom page render
   ═══════════════════════════════════════════════════════════════════════════ */
add_action( 'template_redirect', 'excreet_342_template', 1 );
function excreet_342_template(): void {
    if ( ! is_page( 'member-intake-form' ) ) { return; }

    $post_id  = get_queried_object_id();
    $parts    = excreet_342_parse_elementor( $post_id );
    $welcome  = $parts['welcome'];
    $form_sc  = $parts['form'];          /* e.g. [forminator_form id="42"] */
    $form_out = $form_sc ? do_shortcode( $form_sc ) : '';

    $month    = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url   = 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg';
    $logo_url = 'https://excreet.com/wp-content/uploads/excreet-hero-logo.png';
    $home_url = esc_url( home_url( '/' ) );

    /* ── Output the custom page ── */
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Member Intake Form &mdash; Excreet</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>

/* ── RESET & CANVAS ─────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    font-family: 'Poppins', sans-serif;
    background:
        linear-gradient(160deg,
            rgba(8,0,20,.55)  0%,
            rgba(20,4,42,.20) 28%,
            rgba(20,4,42,.18) 65%,
            rgba(8,0,20,.52)  100%),
        url("<?php echo esc_url( $bg_url ); ?>") center/cover no-repeat fixed #0a0018;
    min-height: 100vh;
    color: #f0e8ff;
}

/* ── CENTRED COLUMN ─────────────────────────────────────────────────────── */
.ex342-wrap {
    max-width: 640px;
    margin: 0 auto;
    padding: 0 1.5rem 6rem;
}

/* ── HEADER (dark section) ──────────────────────────────────────────────── */
.ex342-header {
    text-align: center;
    padding: 3.4rem 0 2rem;
}
.ex342-wordmark {
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: .55em;
    text-transform: uppercase;
    color: #C9A84C;
    margin-bottom: 1.6rem;
}
.ex342-logo {
    width: 84px;
    height: 84px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    margin: 0 auto .95rem;
    box-shadow:
        0 0 0 2px rgba(201,168,76,.55),
        0 0 38px rgba(201,168,76,.28),
        0 8px 26px rgba(0,0,0,.55);
}
.ex342-tagline {
    display: inline-block;
    font-size: .63rem;
    font-weight: 600;
    letter-spacing: .30em;
    text-transform: uppercase;
    color: rgba(255,255,255,.52);
    border-top: 1px solid rgba(201,168,76,.32);
    border-bottom: 1px solid rgba(201,168,76,.32);
    padding: .4em 1.6em;
    margin-bottom: 1.4rem;
}
.ex342-back-wrap { margin-top: .2rem; }
.ex342-back {
    display: inline-flex;
    align-items: center;
    gap: .38em;
    font-size: .75rem;
    font-weight: 500;
    letter-spacing: .05em;
    color: rgba(201,168,76,.60);
    text-decoration: none;
    border: 1px solid rgba(201,168,76,.22);
    padding: .42rem 1.1rem;
    border-radius: 100px;
    transition: color .2s, border-color .2s, background .2s;
}
.ex342-back:hover {
    color: #C9A84C;
    border-color: rgba(201,168,76,.50);
    background: rgba(201,168,76,.07);
}
.ex342-back svg { flex-shrink:0; transition: transform .2s; }
.ex342-back:hover svg { transform: translateX(-3px); }

/* ── WELCOME SECTION (dark, readable on photo) ──────────────────────────── */
.ex342-welcome {
    padding: 1.6rem 0 2rem;
}
.ex342-welcome h1,
.ex342-welcome h2,
.ex342-welcome h3 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.35rem;
    font-weight: 600;
    color: #C9A84C;
    letter-spacing: .02em;
    margin-bottom: .7rem;
    line-height: 1.35;
}
.ex342-welcome p {
    font-family: 'Poppins', sans-serif;
    font-size: .92rem;
    font-weight: 300;
    line-height: 1.85;
    color: rgba(255,255,255,.78);
    margin-bottom: .9rem;
}
.ex342-welcome strong { font-weight: 600; color: rgba(255,255,255,.9); }

/* ── WHITE FORM CARD ────────────────────────────────────────────────────── */
.ex342-card {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow:
        0 28px 72px rgba(0,0,0,.50),
        0 0 0 1px rgba(201,168,76,.22);
}

/* Gold-to-purple accent bar at top */
.ex342-card::before {
    content: '';
    display: block;
    height: 4px;
    background: linear-gradient(90deg,
        #3D1060 0%, #7B3FA0 20%,
        #C9A84C 40%, #f5d97a 55%,
        #C9A84C 70%, #7B3FA0 80%, #3D1060 100%);
}

/* Inner padding inside the card */
.ex342-card-inner {
    padding: 2rem 2.2rem 2.5rem;
}

/* ── FORMINATOR FIELD LABELS ──────────────────────────────────────────────*/
.ex342-card .forminator-label,
.ex342-card .forminator-field-label,
.ex342-card .forminator-row label,
.ex342-card .forminator-custom-form label {
    font-family: 'Poppins', sans-serif !important;
    font-size: .70rem !important;
    font-weight: 600 !important;
    letter-spacing: .12em !important;
    text-transform: uppercase !important;
    color: #1a0535 !important;
    margin-bottom: .35rem !important;
    display: block !important;
}

/* ── FIELD DESCRIPTIONS ───────────────────────────────────────────────────*/
.ex342-card .forminator-description,
.ex342-card .forminator-field-option-description,
.ex342-card .forminator-input-description {
    font-family: 'Poppins', sans-serif !important;
    font-size: .74rem !important;
    font-style: italic !important;
    font-weight: 400 !important;
    color: #6b5c80 !important;
}

/* ── TEXT INPUTS, SELECT, TEXTAREA ───────────────────────────────────────*/
.ex342-card input[type="text"],
.ex342-card input[type="email"],
.ex342-card input[type="number"],
.ex342-card input[type="tel"],
.ex342-card input[type="url"],
.ex342-card input[type="password"],
.ex342-card select,
.ex342-card textarea {
    font-family: 'Poppins', sans-serif !important;
    font-size: .92rem !important;
    font-weight: 400 !important;
    color: #1a0535 !important;
    background: #f9f7fd !important;
    border: 1px solid #d4c4e8 !important;
    border-radius: 10px !important;
    padding: .75rem 1rem !important;
    width: 100% !important;
    transition: border-color .2s, box-shadow .2s, background .2s !important;
    -webkit-appearance: none !important;
    appearance: none !important;
}
.ex342-card input::placeholder,
.ex342-card textarea::placeholder {
    color: #b0a0c0 !important;
    font-weight: 300 !important;
}
.ex342-card input:focus,
.ex342-card select:focus,
.ex342-card textarea:focus {
    border-color: #C9A84C !important;
    background: #ffffff !important;
    outline: none !important;
    box-shadow: 0 0 0 3px rgba(201,168,76,.14) !important;
}

/* ── SELECT arrow ────────────────────────────────────────────────────────*/
.ex342-card select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%233D1060'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right .9rem center !important;
    padding-right: 2.2rem !important;
    cursor: pointer !important;
}
.ex342-card select option {
    background: #fff !important;
    color: #1a0535 !important;
}

/* ── RADIO / CHECKBOX ────────────────────────────────────────────────────*/
.ex342-card .forminator-checkbox label,
.ex342-card .forminator-radio label {
    font-family: 'Poppins', sans-serif !important;
    font-size: .88rem !important;
    font-weight: 400 !important;
    color: #2d1a4a !important;
    text-transform: none !important;
    letter-spacing: 0 !important;
}
.ex342-card input[type="radio"],
.ex342-card input[type="checkbox"] {
    accent-color: #C9A84C !important;
    width: auto !important;
}

/* ── SECTION TITLES inside form ──────────────────────────────────────────*/
.ex342-card .forminator-title h1,
.ex342-card .forminator-title h2,
.ex342-card .forminator-title h3 {
    font-family: 'Poppins', sans-serif !important;
    font-size: 1rem !important;
    font-weight: 700 !important;
    color: #3D1060 !important;
    letter-spacing: .03em !important;
    margin-bottom: .4rem !important;
}
.ex342-card .forminator-subtitle {
    font-family: 'Poppins', sans-serif !important;
    font-size: .82rem !important;
    color: #6b5c80 !important;
}

/* ── ROW DIVIDERS ────────────────────────────────────────────────────────*/
.ex342-card .forminator-row {
    border-bottom: 1px solid rgba(61,16,96,.07) !important;
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
}
.ex342-card .forminator-row:last-child { border-bottom: none !important; }

/* ── PROGRESS BAR ────────────────────────────────────────────────────────*/
.ex342-card .forminator-pagination .forminator-pagination--title {
    font-family: 'Poppins', sans-serif !important;
    font-size: .72rem !important;
    font-weight: 600 !important;
    letter-spacing: .08em !important;
    color: #3D1060 !important;
}
.ex342-card .forminator-pagination--bar .forminator-step { background: #ede4f7 !important; }
.ex342-card .forminator-pagination--bar .forminator-step--active { background: #C9A84C !important; }
.ex342-card .forminator-pagination--bar .forminator-step--completed { background: rgba(201,168,76,.45) !important; }
.ex342-card .forminator-pagination--nav .forminator-step { color: #b0a0c0 !important; }
.ex342-card .forminator-pagination--nav .forminator-step.forminator-step--completed,
.ex342-card .forminator-pagination--nav .forminator-step.forminator-step--active { color: #C9A84C !important; }
.ex342-card .forminator-ui.forminator-loaded .forminator-pagination--bar--fill { background: #C9A84C !important; }

/* ── SUBMIT button ───────────────────────────────────────────────────────*/
.ex342-card .forminator-btn,
.ex342-card .forminator-btn-submit,
.ex342-card button[type="submit"] {
    font-family: 'Poppins', sans-serif !important;
    background: linear-gradient(135deg, #C9A84C 0%, #a8873a 100%) !important;
    color: #1a0535 !important;
    border: none !important;
    border-radius: 50px !important;
    padding: .9rem 3rem !important;
    font-weight: 700 !important;
    font-size: .88rem !important;
    letter-spacing: .12em !important;
    text-transform: uppercase !important;
    cursor: pointer !important;
    transition: opacity .2s, transform .15s, box-shadow .2s !important;
    box-shadow: 0 4px 22px rgba(201,168,76,.40) !important;
}
.ex342-card .forminator-btn:hover,
.ex342-card .forminator-btn-submit:hover {
    opacity: .9 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 28px rgba(201,168,76,.55) !important;
}

/* ── PREVIOUS / ghost button ─────────────────────────────────────────────*/
.ex342-card .forminator-btn-back,
.ex342-card .forminator-btn--ghost {
    font-family: 'Poppins', sans-serif !important;
    background: transparent !important;
    border: 1px solid rgba(61,16,96,.35) !important;
    color: #3D1060 !important;
    border-radius: 50px !important;
    padding: .7rem 1.8rem !important;
    font-size: .82rem !important;
    font-weight: 500 !important;
    letter-spacing: .06em !important;
    cursor: pointer !important;
    transition: border-color .2s, color .2s !important;
}
.ex342-card .forminator-btn-back:hover,
.ex342-card .forminator-btn--ghost:hover {
    border-color: #C9A84C !important;
    color: #C9A84C !important;
}

/* ── VALIDATION errors ───────────────────────────────────────────────────*/
.ex342-card .forminator-error .forminator-input-errors,
.ex342-card .forminator-input-errors .forminator-error {
    font-family: 'Poppins', sans-serif !important;
    font-size: .74rem !important;
    color: #c0392b !important;
}
.ex342-card .forminator-has-error input,
.ex342-card .forminator-has-error textarea,
.ex342-card .forminator-has-error select {
    border-color: rgba(192,57,43,.55) !important;
    background: #fff5f5 !important;
}

/* ── SUCCESS message ─────────────────────────────────────────────────────*/
.ex342-card .forminator-response-output {
    font-family: 'Poppins', sans-serif !important;
    background: #f0faf4 !important;
    border: 1px solid #a8d5b5 !important;
    border-radius: 10px !important;
    color: #1a5c35 !important;
    padding: 1rem 1.4rem !important;
    margin: .5rem 0 !important;
}

/* ── MOBILE ──────────────────────────────────────────────────────────────*/
@media (max-width: 640px) {
    .ex342-wrap { padding-left: 1rem; padding-right: 1rem; }
    .ex342-header { padding-top: 2.2rem; }
    .ex342-logo { width: 68px; height: 68px; }
    .ex342-card-inner { padding: 1.6rem 1.2rem 2rem; }
}

</style>
</head>
<body class="ex342-body">
<?php wp_body_open(); ?>

<div class="ex342-wrap">

    <!-- ── HEADER ── -->
    <header class="ex342-header">
        <p class="ex342-wordmark">E X C R E E T</p>
        <img class="ex342-logo"
             src="<?php echo esc_url( $logo_url ); ?>"
             alt="Excreet"
             onerror="this.style.display='none'">
        <p class="ex342-tagline">A&nbsp;Pre&#8209;Clinical&nbsp;Warning&nbsp;System</p>
        <div class="ex342-back-wrap">
            <a class="ex342-back" href="<?php echo $home_url; ?>">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                    <path d="M10 3L5 8l5 5" stroke="currentColor"
                          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to Home
            </a>
        </div>
    </header>

    <!-- ── WELCOME TEXT (on dark background) ── -->
    <?php if ( $welcome ) : ?>
    <section class="ex342-welcome">
        <?php echo wp_kses_post( $welcome ); ?>
    </section>
    <?php endif; ?>

    <!-- ── WHITE FORM CARD ── -->
    <div class="ex342-card">
        <div class="ex342-card-inner">
            <?php echo $form_out ?: '<p style="color:#3D1060;font-family:Poppins,sans-serif;padding:2rem;">Form loading…</p>'; ?>
        </div>
    </div>

</div>

<?php wp_footer(); ?>
</body>
</html><?php
    exit;
}
