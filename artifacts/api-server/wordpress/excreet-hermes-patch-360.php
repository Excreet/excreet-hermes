<?php
/**
 * Plugin Name: Excreet Hermes — Patch 360 (Member Stories / Video Testimonials)
 * Description: Renders /member-stories/ with a disclaimer acceptance gate, then
 *              reveals a branded video testimonial grid. Add new videos by editing
 *              the $testimonials array below.
 * Version: 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'template_redirect', 'excreet_360_member_stories_page', 1 );

function excreet_360_member_stories_page(): void {
    $page_id = (int) get_option( '_excreet_testimonials_page_id' );
    if ( ! $page_id || ! is_page( $page_id ) ) { return; }

    /* ── ADD VIDEOS HERE as they arrive ────────────────────────────────────
     * Each entry:
     *   'name'  => First name + city  (no surnames)
     *   'tier'  => 'Starter' | 'Premium'
     *   'quote' => One-sentence pull quote shown below the video
     *   'video' => YouTube video ID   (empty string = "coming soon" card)
     *   'since' => 'Member since Month YYYY'
     * ────────────────────────────────────────────────────────────────────── */
    $testimonials = [
        // [
        //     'name'  => 'Maria, Rural Ohio',
        //     'tier'  => 'Premium',
        //     'quote' => 'My Vitality Score went from 34 to 71 in four months.',
        //     'video' => 'YOUTUBE_VIDEO_ID',
        //     'since' => 'Member since January 2026',
        // ],
    ];

    $month      = date( 'm' );
    $bg_url     = 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg';
    $has_videos = ! empty( $testimonials );

    status_header( 200 );
    header( 'Content-Type: text/html; charset=utf-8' );
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Member Stories — Excreet</title>
<?php wp_head(); ?>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Poppins','Segoe UI',sans-serif;background:url('<?php echo esc_url($bg_url); ?>') center/cover no-repeat fixed;min-height:100vh;color:#f0e8ff}

/* Nav */
.ex360-nav{position:sticky;top:0;z-index:100;background:rgba(86,7,94,.96);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:space-between;padding:12px 32px}
.ex360-brand{font-size:20px;font-weight:700;color:#F5D97A;text-decoration:none}
.ex360-back{font-size:13px;color:rgba(255,255,255,.8);text-decoration:none;font-weight:500}
.ex360-back:hover{color:#fff}

/* Wrap */
.ex360-wrap{max-width:1100px;margin:0 auto;padding:48px 24px 80px}

/* Hero */
.ex360-hero{text-align:center;margin-bottom:40px}
.ex360-hero h1{font-size:clamp(24px,3.5vw,42px);font-weight:800;color:#fff;letter-spacing:.06em;text-transform:uppercase;margin-bottom:12px}
.ex360-hero p{font-size:clamp(14px,1.3vw,16px);color:rgba(255,255,255,.8);max-width:560px;margin:0 auto;line-height:1.7}

/* Gate */
.ex360-gate{background:rgba(10,0,20,.82);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid rgba(86,7,94,.5);border-radius:20px;padding:40px 44px;max-width:760px;margin:0 auto 48px;text-align:center}
.ex360-gate-icon{font-size:36px;margin-bottom:20px;display:block}
.ex360-gate h2{font-size:clamp(16px,1.8vw,22px);font-weight:700;color:#F5D97A;margin-bottom:20px;letter-spacing:.04em;text-transform:uppercase}
.ex360-gate-text{font-size:15px;color:rgba(255,255,255,.9);line-height:1.85;margin-bottom:32px;text-align:left}
.ex360-gate-text strong{color:#fff;font-weight:700}
.ex360-gate-text p+p{margin-top:14px}
.ex360-accept-row{display:flex;align-items:flex-start;gap:14px;margin-bottom:28px;text-align:left}
.ex360-accept-row input[type=checkbox]{width:22px;height:22px;min-width:22px;accent-color:#8B00A0;cursor:pointer;margin-top:2px}
.ex360-accept-row label{font-size:14px;color:rgba(255,255,255,.85);line-height:1.6;cursor:pointer}
.ex360-accept-btn{display:inline-block;padding:14px 40px;background:linear-gradient(135deg,#8B00A0,#56075E);color:#fff;font-size:15px;font-weight:700;border:none;border-radius:30px;cursor:pointer;letter-spacing:.06em;text-transform:uppercase;transition:opacity .2s,transform .15s;opacity:.4;pointer-events:none}
.ex360-accept-btn.on{opacity:1;pointer-events:auto}
.ex360-accept-btn.on:hover{opacity:.88;transform:translateY(-2px)}

/* Stories */
.ex360-stories{display:none}
.ex360-stories.show{display:block}
.ex360-stories-heading{text-align:center;font-size:clamp(18px,2vw,26px);font-weight:700;color:#fff;letter-spacing:.05em;text-transform:uppercase;margin-bottom:32px}
.ex360-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:28px;margin-bottom:56px}

/* Card */
.ex360-card{background:rgba(10,0,20,.78);border:1px solid rgba(86,7,94,.45);border-radius:18px;overflow:hidden;transition:transform .2s,box-shadow .2s}
.ex360-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(86,7,94,.4)}
.ex360-vid{position:relative;padding-top:56.25%;background:rgba(26,4,48,.9)}
.ex360-vid iframe{position:absolute;inset:0;width:100%;height:100%;border:none}
.ex360-vid-pending{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:rgba(255,255,255,.35);font-size:13px;gap:10px}
.ex360-vid-pending span{font-size:32px;opacity:.4}
.ex360-card-body{padding:20px 22px 24px}
.ex360-card-name{font-size:15px;font-weight:700;color:#fff;margin-bottom:4px}
.ex360-card-tier{display:inline-block;font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:3px 10px;border-radius:20px;margin-bottom:12px;background:rgba(86,7,94,.55);color:#F5D97A;border:1px solid rgba(245,217,122,.3)}
.ex360-card-quote{font-size:13px;color:rgba(255,255,255,.75);line-height:1.65;font-style:italic;border-left:2px solid rgba(245,217,122,.4);padding-left:12px}
.ex360-card-since{margin-top:10px;font-size:11px;color:rgba(255,255,255,.35)}

/* Empty state */
.ex360-empty{text-align:center;padding:60px 24px;color:rgba(255,255,255,.5)}
.ex360-empty-icon{font-size:48px;margin-bottom:16px;display:block}
.ex360-empty p{font-size:15px;line-height:1.7;max-width:480px;margin:0 auto}

/* Submit box */
.ex360-submit{background:rgba(10,0,20,.78);border:1px solid rgba(245,217,122,.25);border-radius:20px;padding:40px 44px;text-align:center;max-width:680px;margin:0 auto}
.ex360-submit h3{font-size:clamp(16px,1.8vw,22px);font-weight:700;color:#F5D97A;text-transform:uppercase;letter-spacing:.06em;margin-bottom:16px}
.ex360-submit>p{font-size:14px;color:rgba(255,255,255,.8);line-height:1.75;margin-bottom:24px}
.ex360-checklist{text-align:left;background:rgba(86,7,94,.2);border:1px solid rgba(86,7,94,.35);border-radius:12px;padding:24px 28px;margin-bottom:24px}
.ex360-checklist h4{font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#F5D97A;margin-bottom:14px}
.ex360-len-badge{display:inline-block;background:rgba(245,217,122,.15);border:1px solid rgba(245,217,122,.4);color:#F5D97A;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.08em;margin-bottom:16px}
.ex360-checklist ul{list-style:none;display:flex;flex-direction:column;gap:10px}
.ex360-checklist li{font-size:13px;color:rgba(255,255,255,.85);line-height:1.5;padding-left:24px;position:relative}
.ex360-checklist li::before{content:'✓';position:absolute;left:0;color:#F5D97A;font-weight:700}
.ex360-checklist li strong{color:#fff}
.ex360-legal{font-size:11px;color:rgba(255,255,255,.4);line-height:1.65;max-width:540px;margin:0 auto 24px;font-style:italic}
/* Informed consent block */
.ex360-consent-box{background:rgba(86,7,94,.18);border:1px solid rgba(245,217,122,.3);border-radius:14px;padding:22px 24px;margin:0 0 24px;text-align:left}
.ex360-consent-box h4{font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#F5D97A;margin-bottom:14px}
.ex360-consent-row{display:flex;align-items:flex-start;gap:14px}
.ex360-consent-row input[type=checkbox]{width:22px;height:22px;min-width:22px;accent-color:#8B00A0;cursor:pointer;margin-top:2px}
.ex360-consent-row label{font-size:13px;color:rgba(255,255,255,.88);line-height:1.65;cursor:pointer}
.ex360-consent-row label strong{color:#ffffff}
.ex360-record-btn{display:inline-block;padding:14px 40px;background:linear-gradient(135deg,#C9A84C,#a8873a);color:#1a0430!important;font-size:14px;font-weight:700;border-radius:30px;text-decoration:none!important;letter-spacing:.06em;text-transform:uppercase;transition:opacity .2s,transform .15s}
.ex360-record-btn:hover{opacity:.88;transform:translateY(-2px)}

@media(max-width:600px){
    .ex360-gate,.ex360-submit{padding:28px 20px}
    .ex360-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<nav class="ex360-nav">
    <a href="/" class="ex360-brand">Excreet</a>
    <a href="/" class="ex360-back">← Back to Home</a>
</nav>

<div class="ex360-wrap">

    <div class="ex360-hero">
        <h1>Member Stories</h1>
        <p>Real members. Unfiltered accounts. Every experience — including the hard parts.</p>
    </div>

    <div class="ex360-gate" id="ex360-gate">
        <span class="ex360-gate-icon">⚠️</span>
        <h2>Before You Watch — Please Read</h2>
        <div class="ex360-gate-text">
            <p>The videos on this page are <strong>voluntary, unscripted statements made by Excreet members speaking freely to their fellow community members.</strong></p>
            <p>These individuals are sharing their personal experiences in their own words. Their statements reflect their own observations and opinions — not the official claims, representations, or guarantees of Excreet. <strong>Individual results vary.</strong></p>
            <p><strong>You are viewing these testimonials at your own risk.</strong> Excreet publishes them in the spirit of full transparency — including any negative experiences members choose to share. Nothing you watch here constitutes medical advice, diagnosis, or a promise of any specific outcome.</p>
        </div>
        <div class="ex360-accept-row">
            <input type="checkbox" id="ex360-chk">
            <label for="ex360-chk">
                I understand that these are personal accounts shared freely between community members, not verified medical outcomes or company claims. I accept responsibility for how I interpret what I watch.
            </label>
        </div>
        <button class="ex360-accept-btn" id="ex360-btn" onclick="ex360go()">
            I Understand — Show Me the Stories
        </button>
    </div>

    <div class="ex360-stories" id="ex360-stories">
        <div class="ex360-stories-heading">Community Testimonials</div>

        <?php if ( $has_videos ) : ?>
        <div class="ex360-grid">
            <?php foreach ( $testimonials as $t ) : ?>
            <div class="ex360-card">
                <div class="ex360-vid">
                    <?php if ( ! empty( $t['video'] ) ) : ?>
                    <iframe
                        src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr( $t['video'] ); ?>?rel=0&modestbranding=1"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen loading="lazy"
                        title="<?php echo esc_attr( $t['name'] ); ?> — Excreet member story">
                    </iframe>
                    <?php else : ?>
                    <div class="ex360-vid-pending"><span>🎬</span>Video coming soon</div>
                    <?php endif; ?>
                </div>
                <div class="ex360-card-body">
                    <div class="ex360-card-name"><?php echo esc_html( $t['name'] ); ?></div>
                    <span class="ex360-card-tier"><?php echo esc_html( $t['tier'] ); ?> Member</span>
                    <?php if ( ! empty( $t['quote'] ) ) : ?>
                    <p class="ex360-card-quote">"<?php echo esc_html( $t['quote'] ); ?>"</p>
                    <?php endif; ?>
                    <?php if ( ! empty( $t['since'] ) ) : ?>
                    <p class="ex360-card-since"><?php echo esc_html( $t['since'] ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else : ?>
        <div class="ex360-empty">
            <span class="ex360-empty-icon">🎬</span>
            <p>Member videos are on their way — our first contributors are recording now.<br>Check back soon.</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="ex360-submit">
        <h3>Have a story to share?</h3>
        <p>Your experience — honest, complicated, unexpected — is exactly what someone else needs to hear before they decide. Record a 90-second video right from your phone. No account needed.</p>
        <a href="<?php echo esc_url( home_url( '/record-my-story/' ) ); ?>" class="ex360-record-btn">
            ● &nbsp;Record My Story
        </a>
        <p class="ex360-legal" style="margin-top:18px">
            You'll be walked through seven guided prompts and given a chance to review before you submit. Consent is collected in the tool.
        </p>
    </div>

</div>

<?php wp_footer(); ?>
<script>
document.getElementById('ex360-chk').addEventListener('change',function(){
    document.getElementById('ex360-btn').classList.toggle('on',this.checked);
});
function ex360go(){
    document.getElementById('ex360-gate').style.display='none';
    var s=document.getElementById('ex360-stories');
    s.classList.add('show');
    s.scrollIntoView({behavior:'smooth',block:'start'});
    try{sessionStorage.setItem('ex360ok','1');}catch(e){}
}
(function(){
    try{
        if(sessionStorage.getItem('ex360ok')==='1'){
            document.getElementById('ex360-gate').style.display='none';
            document.getElementById('ex360-stories').classList.add('show');
        }
    }catch(e){}
})();
</script>
</body>
</html>
    <?php
    exit;
}
