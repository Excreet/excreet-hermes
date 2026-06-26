<?php
/**
 * Plugin Name: Excreet Hermes Patch 3.6.1
 * Description: Guided Video Testimonial Recorder — /record-my-story/
 *
 *   A — Auto-creates /record-my-story/ WP page on first init
 *   B — [excreet_record] shortcode renders the full tool:
 *         Screen 1 — Informed consent + name/city
 *         Screen 2 — Checkpoint walkthrough (7 prompts, one at a time)
 *         Screen 3 — Camera ready preview
 *         Screen 4 — Live recording with 90-second countdown + teleprompter
 *         Screen 5 — Review playback
 *         Screen 6 — Uploading spinner
 *         Screen 7 — Thank you confirmation
 *   C — POST /api/hermes/testimonial/upload receives the video
 *
 * Version: 3.6.1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EX361_PAGE_SLUG',  'record-my-story' );
define( 'EX361_PAGE_OPT',   '_excreet_361_page_id' );

/* ── A — Auto-create page ──────────────────────────────────────────────────── */
add_action( 'init', 'excreet_361_ensure_page', 20 );
function excreet_361_ensure_page(): void {
    if ( get_option( EX361_PAGE_OPT ) ) return;
    $existing = get_page_by_path( EX361_PAGE_SLUG );
    if ( $existing ) { update_option( EX361_PAGE_OPT, $existing->ID ); return; }
    $id = wp_insert_post( [
        'post_title'   => 'Record My Story',
        'post_name'    => EX361_PAGE_SLUG,
        'post_content' => '[excreet_record]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'meta_input'   => [ '_wp_page_template' => 'elementor_header_footer' ],
    ] );
    if ( $id && ! is_wp_error( $id ) ) update_option( EX361_PAGE_OPT, $id );
}

/* ── Background ────────────────────────────────────────────────────────────── */
add_action( 'wp_head', 'excreet_361_bg', 99 );
function excreet_361_bg(): void {
    if ( ! is_page( EX361_PAGE_SLUG ) ) return;
    $month  = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg     = esc_url( 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg' );
    echo "<style>body,html{background:#0a0318 url('{$bg}') center/cover no-repeat fixed!important;min-height:100vh}</style>\n";
}

/* ── B — Shortcode ─────────────────────────────────────────────────────────── */
add_shortcode( 'excreet_record', 'excreet_361_shortcode' );
function excreet_361_shortcode(): string {
    ob_start();
    ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style id="ex361-styles">
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
#ex361{font-family:'DM Sans',sans-serif;color:#fff;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:32px 16px 60px}

/* ── screens ── */
.ex361-screen{display:none;width:100%;max-width:640px;animation:ex361fade .35s ease}
.ex361-screen.active{display:flex;flex-direction:column;gap:0}
@keyframes ex361fade{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ── wordmark ── */
.ex361-logo{font-family:'Cormorant Garamond',Georgia,serif;font-size:clamp(20px,3vw,26px);font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#F5D97A;text-align:center;margin-bottom:28px}

/* ── card ── */
.ex361-card{background:rgba(10,3,24,.82);border:1px solid rgba(245,217,122,.2);border-radius:22px;padding:clamp(24px,5vw,44px);backdrop-filter:blur(14px)}

/* ── headings ── */
.ex361-tag{font-size:10px;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:#F5D97A;margin-bottom:12px}
.ex361-h{font-family:'Cormorant Garamond',Georgia,serif;font-size:clamp(22px,4vw,32px);font-weight:700;line-height:1.2;margin-bottom:10px}
.ex361-sub{font-size:14px;color:rgba(255,255,255,.65);line-height:1.65;margin-bottom:24px}

/* ── consent checkboxes ── */
.ex361-consent-list{display:flex;flex-direction:column;gap:16px;margin-bottom:28px}
.ex361-chk-row{display:flex;align-items:flex-start;gap:14px}
.ex361-chk-row input[type=checkbox]{width:22px;height:22px;min-width:22px;accent-color:#8B00A0;cursor:pointer;margin-top:2px}
.ex361-chk-row label{font-size:13px;color:rgba(255,255,255,.88);line-height:1.65;cursor:pointer}
.ex361-chk-row label strong{color:#fff}

/* ── inputs ── */
.ex361-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:28px}
.ex361-field{display:flex;flex-direction:column;gap:6px}
.ex361-field label{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.5)}
.ex361-field input{background:rgba(255,255,255,.07);border:1px solid rgba(245,217,122,.25);border-radius:10px;padding:10px 14px;color:#fff;font-size:14px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s}
.ex361-field input:focus{border-color:#F5D97A}
.ex361-field input::placeholder{color:rgba(255,255,255,.3)}

/* ── buttons ── */
.ex361-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 32px;border-radius:30px;font-size:14px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;border:none;cursor:pointer;transition:opacity .2s,transform .15s;width:100%}
.ex361-btn:hover{opacity:.88;transform:translateY(-2px)}
.ex361-btn-gold{background:linear-gradient(135deg,#C9A84C,#a8873a);color:#1a0430}
.ex361-btn-gold:disabled{opacity:.35;pointer-events:none;transform:none}
.ex361-btn-outline{background:transparent;border:2px solid rgba(245,217,122,.5);color:#F5D97A}
.ex361-btn-outline:hover{border-color:#F5D97A}
.ex361-btn-danger{background:rgba(180,30,30,.7);border:1px solid rgba(255,80,80,.4);color:#fff}
.ex361-btn-sm{padding:10px 22px;font-size:12px;width:auto}

/* ── checkpoint walkthrough ── */
.ex361-prompt-card{background:rgba(86,7,94,.15);border:1px solid rgba(245,217,122,.2);border-radius:18px;padding:32px 28px;margin-bottom:24px;text-align:center}
.ex361-prompt-num{font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:10px}
.ex361-prompt-title{font-family:'Cormorant Garamond',Georgia,serif;font-size:clamp(20px,3.5vw,28px);font-weight:700;color:#fff;line-height:1.3;margin-bottom:10px}
.ex361-prompt-tip{font-size:13px;color:rgba(255,255,255,.55);line-height:1.6}
.ex361-dots{display:flex;justify-content:center;gap:8px;margin-bottom:24px}
.ex361-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.2);transition:background .3s}
.ex361-dot.done{background:#F5D97A}
.ex361-dot.active{background:#fff}

/* ── camera screens ── */
.ex361-cam-wrap{position:relative;width:100%;border-radius:18px;overflow:hidden;background:#000;aspect-ratio:9/16;max-height:480px;margin-bottom:20px}
.ex361-cam-wrap video{width:100%;height:100%;object-fit:cover;display:block}

/* ── countdown ring ── */
.ex361-timer{position:absolute;top:14px;right:14px;width:70px;height:70px;z-index:10}
.ex361-timer svg{transform:rotate(-90deg)}
.ex361-timer-bg{fill:none;stroke:rgba(255,255,255,.15);stroke-width:5}
.ex361-timer-ring{fill:none;stroke:#F5D97A;stroke-width:5;stroke-linecap:round;transition:stroke-dashoffset .9s linear;stroke-dasharray:188.5;stroke-dashoffset:0}
.ex361-timer-ring.urgent{stroke:#ff6b6b}
.ex361-timer-num{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#fff}

/* ── teleprompter overlay ── */
.ex361-teleprompter{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(10,3,24,.88) 40%);padding:24px 18px 18px;z-index:9}
.ex361-tp-label{font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.45);margin-bottom:6px}
.ex361-tp-text{font-size:15px;font-weight:500;color:#fff;line-height:1.4}

/* ── rec dot ── */
.ex361-rec-dot{position:absolute;top:14px;left:14px;display:flex;align-items:center;gap:6px;z-index:10;background:rgba(0,0,0,.5);border-radius:20px;padding:5px 12px}
.ex361-rec-dot span:first-child{width:9px;height:9px;border-radius:50%;background:#ff4444;animation:ex361pulse 1.2s infinite}
.ex361-rec-dot span:last-child{font-size:11px;font-weight:700;letter-spacing:.1em;color:#fff}
@keyframes ex361pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* ── review ── */
.ex361-review-vid{width:100%;border-radius:18px;background:#000;margin-bottom:20px;display:block;max-height:420px;object-fit:contain}
.ex361-btn-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}

/* ── upload / done ── */
.ex361-center{text-align:center;padding:40px 0}
.ex361-spinner{width:52px;height:52px;border:4px solid rgba(245,217,122,.2);border-top-color:#F5D97A;border-radius:50%;animation:ex361spin .8s linear infinite;margin:0 auto 20px}
@keyframes ex361spin{to{transform:rotate(360deg)}}
.ex361-check{font-size:52px;margin-bottom:16px}
.ex361-done-h{font-family:'Cormorant Garamond',Georgia,serif;font-size:clamp(24px,4vw,34px);font-weight:700;color:#F5D97A;margin-bottom:12px}
.ex361-done-p{font-size:14px;color:rgba(255,255,255,.7);line-height:1.7;max-width:420px;margin:0 auto}

/* ── error ── */
.ex361-error-box{background:rgba(180,30,30,.2);border:1px solid rgba(255,80,80,.3);border-radius:14px;padding:18px 20px;margin-bottom:20px;font-size:13px;color:#ffaaaa;line-height:1.6}

@media(max-width:480px){
  .ex361-fields{grid-template-columns:1fr}
  .ex361-cam-wrap{aspect-ratio:3/4;max-height:360px}
}
</style>

<div id="ex361">
  <div class="ex361-logo">EXCREET</div>

  <!-- ── SCREEN 1: CONSENT ── -->
  <div class="ex361-screen active" id="ex361-s1">
    <div class="ex361-card">
      <p class="ex361-tag">Share Your Story</p>
      <h2 class="ex361-h">Before We Record</h2>
      <p class="ex361-sub">Please read and confirm each item below. Your video will be reviewed by the Excreet team before it is published.</p>

      <div class="ex361-consent-list">
        <div class="ex361-chk-row">
          <input type="checkbox" id="ex361-c1">
          <label for="ex361-c1">I understand and agree that by submitting this video, I am giving <strong>voluntary, informed consent</strong> for my testimonial to be <strong>published publicly</strong> on excreet.com and Excreet's associated platforms.</label>
        </div>
        <div class="ex361-chk-row">
          <input type="checkbox" id="ex361-c2">
          <label for="ex361-c2">I am an <strong>active consumer of Excreet</strong> sharing my experience to Excreet members voluntarily.</label>
        </div>
        <div class="ex361-chk-row">
          <input type="checkbox" id="ex361-c3">
          <label for="ex361-c3">I will <strong>speak freely</strong> with my most accurate recollection.</label>
        </div>
      </div>

      <div class="ex361-fields">
        <div class="ex361-field">
          <label for="ex361-name">First Name</label>
          <input type="text" id="ex361-name" placeholder="Maria" maxlength="60" autocomplete="given-name">
        </div>
        <div class="ex361-field">
          <label for="ex361-city">City</label>
          <input type="text" id="ex361-city" placeholder="Atlanta" maxlength="60" autocomplete="address-level2">
        </div>
      </div>

      <button class="ex361-btn ex361-btn-gold" id="ex361-consent-btn" disabled onclick="ex361GoCheckpoints()">
        I've Read and Agree — Continue →
      </button>
    </div>
  </div>

  <!-- ── SCREEN 2: CHECKPOINTS ── -->
  <div class="ex361-screen" id="ex361-s2">
    <div class="ex361-card">
      <p class="ex361-tag">Your Script</p>
      <h2 class="ex361-h">What to Cover</h2>
      <p class="ex361-sub">Read each prompt. When you're ready for the next one, tap <em>Got it →</em>. You'll see all of them again as a teleprompter while you record.</p>

      <div class="ex361-dots" id="ex361-dots"></div>

      <div class="ex361-prompt-card">
        <p class="ex361-prompt-num" id="ex361-pnum"></p>
        <p class="ex361-prompt-title" id="ex361-ptitle"></p>
        <p class="ex361-prompt-tip" id="ex361-ptip"></p>
      </div>

      <button class="ex361-btn ex361-btn-gold" id="ex361-next-btn" onclick="ex361NextPrompt()">Got it →</button>
    </div>
  </div>

  <!-- ── SCREEN 3: CAMERA READY ── -->
  <div class="ex361-screen" id="ex361-s3">
    <div class="ex361-card">
      <p class="ex361-tag">Almost There</p>
      <h2 class="ex361-h">Set Up Your Shot</h2>
      <p class="ex361-sub">Your camera is live below. Make sure:</p>
      <ul style="font-size:13px;color:rgba(255,255,255,.75);line-height:2;padding-left:20px;margin-bottom:24px">
        <li>Good light on your face — window light is ideal</li>
        <li>Hold your phone <strong>horizontally</strong> if possible</li>
        <li>Quiet space, no background noise</li>
        <li>You have <strong>90 seconds</strong> — the timer stops automatically</li>
      </ul>
      <div class="ex361-cam-wrap" style="margin-bottom:20px">
        <video id="ex361-preview" autoplay muted playsinline></video>
      </div>
      <div style="display:flex;gap:12px">
        <button class="ex361-btn ex361-btn-outline ex361-btn-sm" onclick="ex361GoCheckpoints2()">← Review Prompts</button>
        <button class="ex361-btn ex361-btn-gold" onclick="ex361StartRecording()" style="flex:1">Start Recording ●</button>
      </div>
    </div>
  </div>

  <!-- ── SCREEN 4: RECORDING ── -->
  <div class="ex361-screen" id="ex361-s4">
    <div class="ex361-card" style="padding:0;overflow:hidden;border-radius:22px">
      <div class="ex361-cam-wrap" style="margin:0;border-radius:0;max-height:520px;aspect-ratio:unset;height:480px">
        <video id="ex361-live" autoplay muted playsinline style="width:100%;height:100%;object-fit:cover"></video>

        <!-- REC indicator -->
        <div class="ex361-rec-dot">
          <span></span><span>REC</span>
        </div>

        <!-- Countdown ring -->
        <div class="ex361-timer">
          <svg width="70" height="70" viewBox="0 0 70 70">
            <circle class="ex361-timer-bg" cx="35" cy="35" r="30"/>
            <circle class="ex361-timer-ring" id="ex361-ring" cx="35" cy="35" r="30"/>
          </svg>
          <div class="ex361-timer-num" id="ex361-countdown">90</div>
        </div>

        <!-- Teleprompter -->
        <div class="ex361-teleprompter">
          <p class="ex361-tp-label" id="ex361-tp-label">Now talking about</p>
          <p class="ex361-tp-text" id="ex361-tp-text"></p>
        </div>
      </div>

      <div style="padding:18px 20px">
        <button class="ex361-btn ex361-btn-danger" onclick="ex361StopEarly()">Stop Early &amp; Review</button>
      </div>
    </div>
  </div>

  <!-- ── SCREEN 5: REVIEW ── -->
  <div class="ex361-screen" id="ex361-s5">
    <div class="ex361-card">
      <p class="ex361-tag">Review Your Video</p>
      <h2 class="ex361-h">Happy with this take?</h2>
      <p class="ex361-sub" style="margin-bottom:18px">Watch it back. If you're not happy, retake — no limit.</p>
      <video id="ex361-review" class="ex361-review-vid" controls playsinline></video>
      <div class="ex361-btn-row">
        <button class="ex361-btn ex361-btn-outline" onclick="ex361Retake()">↩ Retake</button>
        <button class="ex361-btn ex361-btn-gold" onclick="ex361Submit()">Submit My Story →</button>
      </div>
    </div>
  </div>

  <!-- ── SCREEN 6: UPLOADING ── -->
  <div class="ex361-screen" id="ex361-s6">
    <div class="ex361-card">
      <div class="ex361-center">
        <div class="ex361-spinner"></div>
        <p style="font-size:15px;color:rgba(255,255,255,.7)">Uploading your story…<br><span style="font-size:12px;opacity:.6">This may take a moment on mobile.</span></p>
      </div>
    </div>
  </div>

  <!-- ── SCREEN 7: DONE ── -->
  <div class="ex361-screen" id="ex361-s7">
    <div class="ex361-card">
      <div class="ex361-center">
        <div class="ex361-check">✦</div>
        <h2 class="ex361-done-h">Thank You.</h2>
        <p class="ex361-done-p">Your story has been received. The Excreet team will review it and, once approved, it will appear on the Member Stories page.<br><br>Your honesty is what builds real trust.</p>
      </div>
    </div>
  </div>

  <!-- ── SCREEN 8: ERROR ── -->
  <div class="ex361-screen" id="ex361-s8">
    <div class="ex361-card">
      <p class="ex361-tag">Something Went Wrong</p>
      <div class="ex361-error-box" id="ex361-errmsg"></div>
      <button class="ex361-btn ex361-btn-outline" onclick="ex361GoReview()">← Go Back &amp; Try Again</button>
    </div>
  </div>
</div>

<script>
/* ── DATA ── */
const PROMPTS = [
  {
    title: 'Your first name and city',
    tip:   'No surname needed. Just your first name and where you\'re located.',
  },
  {
    title: 'What health challenges brought you to Excreet',
    tip:   'Be specific — fatigue, gut issues, lab results, something a doctor said.',
  },
  {
    title: 'What you found when you took the Body Check',
    tip:   'Your tier or Vitality Score, if you\'re comfortable sharing. What did you learn?',
  },
  {
    title: 'What specifically changed',
    tip:   'Energy, sleep, digestion, a lab result, a conversation with your doctor.',
  },
  {
    title: 'What was hard or didn\'t work',
    tip:   'Honesty here is what builds real trust. No story is perfect and that\'s the point.',
  },
  {
    title: 'Would you recommend Excreet — and to whom',
    tip:   'Who would benefit most from what you\'ve experienced?',
  },
  {
    title: 'Hold up the Bottle if you have one',
    tip:   'If you have your Excreet bottle nearby, hold it up for a moment.',
  },
];

const TOTAL_SECS   = 90;
const RING_CIRCUM  = 2 * Math.PI * 30; // 188.5

let currentPrompt  = 0;
let memberName     = '';
let memberCity     = '';
let mediaStream    = null;
let mediaRecorder  = null;
let recordedChunks = [];
let recordedBlob   = null;
let countdownTimer = null;
let tpTimer        = null;
let tpIndex        = 0;
let secsLeft       = TOTAL_SECS;

/* ── SCREEN NAVIGATION ── */
function ex361Show(id) {
  document.querySelectorAll('.ex361-screen').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── CONSENT VALIDATION ── */
(function() {
  const ids   = ['ex361-c1','ex361-c2','ex361-c3','ex361-name','ex361-city'];
  const btn   = document.getElementById('ex361-consent-btn');
  function validate() {
    const allChecked = ['ex361-c1','ex361-c2','ex361-c3'].every(id => document.getElementById(id).checked);
    const hasName    = document.getElementById('ex361-name').value.trim().length > 0;
    const hasCity    = document.getElementById('ex361-city').value.trim().length > 0;
    btn.disabled     = !(allChecked && hasName && hasCity);
  }
  ids.forEach(id => document.getElementById(id).addEventListener('input', validate));
  ids.forEach(id => document.getElementById(id).addEventListener('change', validate));
})();

/* ── SCREEN 1 → 2 ── */
function ex361GoCheckpoints() {
  memberName     = document.getElementById('ex361-name').value.trim();
  memberCity     = document.getElementById('ex361-city').value.trim();
  currentPrompt  = 0;
  ex361RenderPrompt();
  ex361Show('ex361-s2');
}

/* ── CHECKPOINT WALKTHROUGH ── */
function ex361RenderDots() {
  const container = document.getElementById('ex361-dots');
  container.innerHTML = '';
  PROMPTS.forEach((_, i) => {
    const d = document.createElement('div');
    d.className = 'ex361-dot' + (i < currentPrompt ? ' done' : i === currentPrompt ? ' active' : '');
    container.appendChild(d);
  });
}

function ex361RenderPrompt() {
  const p = PROMPTS[currentPrompt];
  document.getElementById('ex361-pnum').textContent   = `Prompt ${currentPrompt + 1} of ${PROMPTS.length}`;
  document.getElementById('ex361-ptitle').textContent = p.title;
  document.getElementById('ex361-ptip').textContent   = p.tip;
  document.getElementById('ex361-next-btn').textContent =
    currentPrompt < PROMPTS.length - 1 ? 'Got it →' : 'I\'m Ready to Record ●';
  ex361RenderDots();
}

function ex361NextPrompt() {
  if (currentPrompt < PROMPTS.length - 1) {
    currentPrompt++;
    ex361RenderPrompt();
  } else {
    ex361GoCamera();
  }
}

function ex361GoCheckpoints2() {
  ex361Show('ex361-s2');
}

/* ── SCREEN 3: CAMERA SETUP ── */
async function ex361GoCamera() {
  ex361Show('ex361-s3');
  try {
    if (mediaStream) mediaStream.getTracks().forEach(t => t.stop());
    mediaStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
      audio: true,
    });
    document.getElementById('ex361-preview').srcObject = mediaStream;
  } catch (err) {
    alert('Camera access was denied. Please allow camera and microphone access in your browser settings, then try again.');
  }
}

/* ── SCREEN 4: RECORDING ── */
function ex361StartRecording() {
  if (!mediaStream) { alert('Camera not ready. Please try again.'); return; }

  recordedChunks = [];
  recordedBlob   = null;

  const liveVid  = document.getElementById('ex361-live');
  liveVid.srcObject = mediaStream;

  const mimeType = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm','video/mp4']
    .find(m => MediaRecorder.isTypeSupported(m)) || '';

  mediaRecorder = new MediaRecorder(mediaStream, mimeType ? { mimeType } : {});
  mediaRecorder.ondataavailable = e => { if (e.data && e.data.size > 0) recordedChunks.push(e.data); };
  mediaRecorder.onstop = ex361OnRecordStop;
  mediaRecorder.start(250);

  secsLeft = TOTAL_SECS;
  tpIndex  = 0;
  ex361UpdateRing();
  ex361UpdateTeleprompter();

  ex361Show('ex361-s4');

  countdownTimer = setInterval(() => {
    secsLeft--;
    ex361UpdateRing();
    if (secsLeft <= 0) { clearInterval(countdownTimer); ex361StopRecording(); }
  }, 1000);

  const tpInterval = Math.floor(TOTAL_SECS / PROMPTS.length);
  tpTimer = setInterval(() => {
    tpIndex = Math.min(tpIndex + 1, PROMPTS.length - 1);
    ex361UpdateTeleprompter();
  }, tpInterval * 1000);
}

function ex361UpdateRing() {
  const ring = document.getElementById('ex361-ring');
  const num  = document.getElementById('ex361-countdown');
  const pct  = secsLeft / TOTAL_SECS;
  ring.style.strokeDashoffset = RING_CIRCUM * (1 - pct);
  ring.classList.toggle('urgent', secsLeft <= 15);
  num.textContent = secsLeft;
}

function ex361UpdateTeleprompter() {
  const p = PROMPTS[tpIndex];
  document.getElementById('ex361-tp-label').textContent = `Prompt ${tpIndex + 1} of ${PROMPTS.length}`;
  document.getElementById('ex361-tp-text').textContent  = p.title;
}

function ex361StopEarly() {
  ex361StopRecording();
}

function ex361StopRecording() {
  clearInterval(countdownTimer);
  clearInterval(tpTimer);
  if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
}

function ex361OnRecordStop() {
  const ext      = (mediaRecorder.mimeType || '').includes('mp4') ? 'mp4' : 'webm';
  recordedBlob   = new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'video/webm' });
  const url      = URL.createObjectURL(recordedBlob);
  const rev      = document.getElementById('ex361-review');
  rev.src        = url;
  ex361GoReview();
}

/* ── SCREEN 5: REVIEW ── */
function ex361GoReview() {
  ex361Show('ex361-s5');
}

function ex361Retake() {
  mediaRecorder  = null;
  recordedChunks = [];
  recordedBlob   = null;
  ex361GoCamera();
}

/* ── SCREEN 6+7+8: SUBMIT ── */
async function ex361Submit() {
  if (!recordedBlob) { alert('No video found. Please retake.'); return; }
  ex361Show('ex361-s6');

  const ext  = recordedBlob.type.includes('mp4') ? 'mp4' : 'webm';
  const fd   = new FormData();
  fd.append('video',      recordedBlob, `testimonial.${ext}`);
  fd.append('memberName', memberName);
  fd.append('memberCity', memberCity);

  try {
    const res  = await fetch('/api/hermes/testimonial/upload', { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Upload failed');
    if (mediaStream) mediaStream.getTracks().forEach(t => t.stop());
    ex361Show('ex361-s7');
  } catch (err) {
    document.getElementById('ex361-errmsg').textContent =
      err.message || 'An error occurred uploading your video. Please try again.';
    ex361Show('ex361-s8');
  }
}
</script>
    <?php
    return ob_get_clean();
}
