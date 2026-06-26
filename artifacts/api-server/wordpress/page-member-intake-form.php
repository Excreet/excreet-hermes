<?php
/**
 * Plugin Name: Excreet Member Intake Form Template
 * Version: 1.1.0
 * Description: Renders the /member-intake-form/ page. Hooked via template_redirect so it
 *              only fires on that specific slug — never on WP admin or other pages.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'template_redirect', function () {
    // Only intercept the member-intake-form page
    if ( ! is_page( 'member-intake-form' ) ) {
        return;
    }

    $month    = date( 'm' );
    $bg_url   = 'https://excreet.com/wp-content/uploads/healer-bg-' . $month . '.jpg';
    $logo_url = 'https://excreet.com/wp-content/uploads/excreet-logo-v3.png';

    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Member Intake &mdash; Baseline &mdash; Excreet</title>
<?php wp_head(); ?>
<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; }
#wpadminbar { display: none !important; }
html { margin-top: 0 !important; }

html, body {
    margin: 0;
    padding: 0;
    min-height: 100vh;
    font-family: 'Georgia', serif;
    background:
        linear-gradient(160deg,
            rgba(8,0,20,.55)  0%,
            rgba(20,4,42,.22) 35%,
            rgba(20,4,42,.18) 65%,
            rgba(8,0,20,.52)  100%),
        url('<?php echo esc_url( $bg_url ); ?>') center/cover no-repeat fixed #0c0115;
}

/* ── Outer wrapper ── */
.ex-wrap {
    min-height: 100vh;
    padding: 3rem 1.5rem 6rem;
}

/* ── Center column ── */
.ex-col {
    max-width: 680px;
    margin: 0 auto;
    text-align: center;
}

/* ── Logo ── */
.ex-logo {
    width: 130px;
    height: 130px;
    display: block;
    margin: 0 auto 1rem;
    border: none;
    box-shadow: none;
    border-radius: 0;
    background: transparent;
}

/* ── Tagline ── */
.ex-tagline {
    font-size: 1.1rem;
    font-weight: 900;
    letter-spacing: .38em;
    text-transform: uppercase;
    color: #ffffff;
    text-shadow: 0 1px 4px rgba(0,0,0,.9), 0 0 12px rgba(0,0,0,.8), 1px 1px 0 rgba(0,0,0,1);
    display: block;
    margin-bottom: 2.2rem;
}

/* ── White card ── */
.ex-card {
    background: #ffffff !important;
    opacity: 1 !important;
    border-radius: 18px;
    padding: 2.5rem 2.2rem 3rem;
    box-shadow: 0 32px 80px rgba(0,0,0,.55), 0 0 0 1px rgba(201,168,76,.18);
    text-align: left;
    position: relative;
    z-index: 10;
}

/* ── Welcome text ── */
.ex-welcome {
    margin-bottom: 1.8rem;
    padding-bottom: 1.6rem;
    border-bottom: 1px solid #e5e5e5;
}
.ex-welcome-head {
    font-size: 1.6rem;
    font-weight: 700;
    color: #111111 !important;
    margin: 0 0 1rem;
}
.ex-welcome p {
    font-size: 1.05rem;
    line-height: 1.75;
    color: #111111 !important;
    margin: 0 0 1rem;
}
.ex-welcome p:last-child { margin-bottom: 0; }

/* ── Forminator field overrides ── */
.ex-card .forminator-ui,
.ex-card .forminator-custom-form,
.ex-card .forminator-row,
.ex-card .forminator-col,
.ex-card .forminator-field,
.ex-card .forminator-form-holder {
    background: #fff !important;
    background-image: none !important;
    color: #111 !important;
}
.ex-card label,
.ex-card .forminator-label { color: #111 !important; }
.ex-card input[type="text"],
.ex-card input[type="email"],
.ex-card input[type="tel"],
.ex-card input[type="number"],
.ex-card textarea,
.ex-card select {
    color: #111 !important;
    background: #fff !important;
}

/* ── Forminator checkbox / radio overrides ── */
/* Use native inputs — accent-color paints them purple, no DOM tricks needed */
.ex-card input[type="checkbox"],
.ex-card input[type="radio"] {
    appearance: auto !important;
    -webkit-appearance: auto !important;
    position: static !important;
    opacity: 1 !important;
    width: 20px !important;
    height: 20px !important;
    min-width: 20px !important;
    margin: 0 !important;
    flex-shrink: 0 !important;
    cursor: pointer !important;
    accent-color: #6b3fa0 !important;
    z-index: auto !important;
    inset: auto !important;
}

.ex-card .forminator-checkbox .forminator-checkbox-label,
.ex-card .forminator-radio   .forminator-radio-label {
    display: flex !important;
    align-items: center !important;
    gap: .7rem !important;
    cursor: pointer !important;
    padding: .55rem .7rem !important;
    border-radius: 8px !important;
    transition: background .15s !important;
    color: #111111 !important;
    font-size: 1rem !important;
    font-weight: 500 !important;
    user-select: none !important;
    position: static !important;
}
.ex-card .forminator-checkbox .forminator-checkbox-label:hover,
.ex-card .forminator-radio   .forminator-radio-label:hover {
    background: #f0eaff !important;
}

/* Remove any fake ::before boxes */
.ex-card .forminator-checkbox .forminator-checkbox-label::before,
.ex-card .forminator-radio   .forminator-radio-label::before {
    display: none !important;
    content: none !important;
}

/* ── Date of Birth picker ── */
.ex-dob-wrap {
    margin-bottom: 0 !important;
}
.ex-dob-label {
    display: block !important;
    font-size: .85rem !important;
    font-weight: 700 !important;
    color: #222 !important;
    letter-spacing: .05em !important;
    text-transform: uppercase !important;
    margin-bottom: .45rem !important;
}
.ex-dob-picker {
    width: 100% !important;
    padding: .65rem .9rem !important;
    font-size: 1rem !important;
    font-family: inherit !important;
    color: #111 !important;
    background: #fff !important;
    border: 1px solid #ccc !important;
    border-radius: 6px !important;
    box-shadow: none !important;
    outline: none !important;
    cursor: pointer !important;
    appearance: auto !important;
    -webkit-appearance: auto !important;
}
.ex-dob-picker:focus {
    border-color: #6b3fa0 !important;
    box-shadow: 0 0 0 3px rgba(107,63,160,.18) !important;
}
.ex-dob-hint {
    margin-top: .35rem !important;
    font-size: .88rem !important;
    min-height: 1.2em !important;
}
.ex-dob-ok  { color: #1a7a3e !important; font-weight: 600 !important; }
.ex-dob-err { color: #b0151a !important; font-weight: 600 !important; }

/* ── File upload section ── */
.ex-upload-section {
    margin-top: 2rem !important;
    padding-top: 1.8rem !important;
    border-top: 1px solid #e5e5e5 !important;
    opacity: 1 !important;
}
.ex-upload-label {
    display: block !important;
    font-size: 1.05rem !important;
    font-weight: 700 !important;
    color: #111111 !important;
    margin-bottom: .35rem !important;
    opacity: 1 !important;
}
.ex-upload-hint {
    font-size: .95rem !important;
    color: #333333 !important;
    margin: 0 0 1rem !important;
    opacity: 1 !important;
}
.ex-drop-zone {
    border: 2px dashed #888888 !important;
    border-radius: 10px !important;
    padding: 2rem 1.5rem !important;
    text-align: center !important;
    cursor: pointer !important;
    transition: border-color .2s, background .2s;
    background: #f5f5f5 !important;
    position: relative !important;
    opacity: 1 !important;
}
.ex-drop-zone:hover,
.ex-drop-zone.ex-drag-over {
    border-color: #6b3fa0 !important;
    background: #f4eeff !important;
}
.ex-drop-zone input[type="file"] {
    position: absolute !important;
    inset: 0 !important;
    opacity: 0 !important;
    cursor: pointer !important;
    width: 100% !important;
    height: 100% !important;
}
.ex-drop-icon { font-size: 2.2rem !important; display: block !important; margin-bottom: .6rem !important; opacity: 1 !important; }
.ex-drop-text { font-size: 1rem !important; color: #222222 !important; margin: 0 !important; font-weight: 600 !important; }
.ex-drop-sub  { font-size: .88rem !important; color: #555555 !important; margin-top: .4rem !important; }
.ex-upload-status {
    margin-top: .9rem !important;
    font-size: .95rem !important;
    min-height: 1.4em !important;
}
.ex-upload-status.ok   { color: #1a7a3e !important; font-weight: 600 !important; }
.ex-upload-status.err  { color: #b0151a !important; font-weight: 600 !important; }
.ex-upload-status.busy { color: #333333 !important; }

@media (max-width: 600px) {
    .ex-wrap  { padding: 2rem 1rem 5rem; }
    .ex-card  { padding: 1.8rem 1.2rem 2.4rem; }
}
</style>
</head>
<body>
<?php wp_body_open(); ?>
<div class="ex-wrap">
    <div class="ex-col">
        <img class="ex-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="Excreet">
        <span class="ex-tagline">A Pre&#8209;Clinical Warning System</span>
        <div class="ex-card">
            <div class="ex-welcome">
                <p class="ex-welcome-head">Welcome.</p>
                <p>Becoming a member of Excreet means choosing awareness over guesswork. This intake helps Excreet understand your environment, habits, possible toxin exposure and patterns so it can translate what your body may be communicating over time.</p>
                <p>This is not a medical exam, and nothing here is used to diagnose or treat. You may answer at your own pace, pause and return if needed, and skip anything you&rsquo;re not comfortable sharing. Your information remains private and is used solely to support your ongoing awareness and guidance as a member.</p>
            </div>
            <?php echo do_shortcode( '[forminator_form id="6"]' ); ?>

            <div class="ex-upload-section">
                <label class="ex-upload-label" for="ex-file-input">Attach a Document <em style="font-weight:400;color:#555;">(optional)</em></label>
                <p class="ex-upload-hint">Lab results, health records, or any relevant document — PDF, image, or Word file. Max 10 MB.</p>
                <div class="ex-drop-zone" id="ex-drop-zone">
                    <input type="file" id="ex-file-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.txt,.doc,.docx">
                    <span class="ex-drop-icon">📎</span>
                    <p class="ex-drop-text">Click to choose a file, or drag it here</p>
                    <p class="ex-drop-sub">PDF · Image · Word · Text</p>
                </div>
                <div class="ex-upload-status" id="ex-upload-status"></div>
            </div>
        </div>
    </div>
</div>
<script>
/* ── Date-of-Birth picker — replaces Forminator "Age" text field ── */
(function(){
  var MAX_ATTEMPTS = 40;
  var attempts     = 0;

  /* Compute today/18 years ago for max/min bounds */
  function isoDate(d){ return d.toISOString().split('T')[0]; }
  var today    = new Date();
  var minDOB   = new Date(today.getFullYear()-120, today.getMonth(), today.getDate());
  var maxDOB   = new Date(today.getFullYear()-18,  today.getMonth(), today.getDate());

  function calcAge(dobStr){
    var d = new Date(dobStr);
    var age = today.getFullYear() - d.getFullYear();
    var m = today.getMonth() - d.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < d.getDate())) age--;
    return age;
  }

  function injectDOB(){
    /* Find any label whose text contains "age" (case-insensitive) */
    var labels = document.querySelectorAll('.forminator-field label, .forminator-row label');
    var ageLabel = null;
    for (var i = 0; i < labels.length; i++){
      if (/\bage\b/i.test(labels[i].textContent)){ ageLabel = labels[i]; break; }
    }
    if (!ageLabel){
      if (++attempts < MAX_ATTEMPTS){ setTimeout(injectDOB, 150); }
      return;
    }

    /* Climb to the row wrapper */
    var row = ageLabel.closest('.forminator-row, .forminator-field-input, .forminator-field');
    if (!row){ row = ageLabel.parentElement; }

    /* Find the original number/text input */
    var origInput = row.querySelector('input[type="number"], input[type="text"]');
    if (!origInput){ if (++attempts < MAX_ATTEMPTS){ setTimeout(injectDOB, 150); } return; }

    /* Hide original input + label, keep in DOM for Forminator validation */
    origInput.style.display    = 'none';
    origInput.style.visibility = 'hidden';
    ageLabel.style.display     = 'none';

    /* Build replacement block */
    var wrap = document.createElement('div');
    wrap.className = 'ex-dob-wrap';

    var lbl = document.createElement('label');
    lbl.textContent = 'Date of Birth';
    lbl.htmlFor     = 'ex-dob-input';
    lbl.className   = 'ex-dob-label';

    var picker = document.createElement('input');
    picker.type  = 'date';
    picker.id    = 'ex-dob-input';
    picker.name  = 'ex_dob';
    picker.min   = isoDate(minDOB);
    picker.max   = isoDate(maxDOB);
    picker.className = 'ex-dob-picker';
    picker.setAttribute('required', '');

    var hint = document.createElement('div');
    hint.id        = 'ex-dob-hint';
    hint.className = 'ex-dob-hint';

    wrap.appendChild(lbl);
    wrap.appendChild(picker);
    wrap.appendChild(hint);

    /* Insert before the hidden original input */
    origInput.parentNode.insertBefore(wrap, origInput);

    picker.addEventListener('change', function(){
      var dob = picker.value;
      if (!dob) return;
      var age = calcAge(dob);
      if (age < 18){
        hint.textContent  = 'You must be 18 or older to join Excreet.';
        hint.className    = 'ex-dob-hint ex-dob-err';
        origInput.value   = '';
        picker.setCustomValidity('Must be 18 or older.');
      } else {
        hint.textContent  = 'Age: ' + age;
        hint.className    = 'ex-dob-hint ex-dob-ok';
        origInput.value   = age;
        picker.setCustomValidity('');
      }
    });

    /* Also block Forminator submit if under 18 */
    var form = row.closest('form');
    if (form){
      form.addEventListener('submit', function(e){
        var dob = picker.value;
        if (!dob || calcAge(dob) < 18){
          e.preventDefault();
          e.stopImmediatePropagation();
          hint.textContent = 'You must be 18 or older to submit this form.';
          hint.className   = 'ex-dob-hint ex-dob-err';
          picker.focus();
        }
      }, true);
    }
  }

  /* Start polling after DOM ready */
  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', injectDOB);
  } else {
    setTimeout(injectDOB, 100);
  }
})();

(function(){
  var zone   = document.getElementById('ex-drop-zone');
  var input  = document.getElementById('ex-file-input');
  var status = document.getElementById('ex-upload-status');
  var UPLOAD_URL = 'https://excreet.com/api/hermes/intake/upload';

  function setStatus(msg, cls) {
    status.textContent = msg;
    status.className = 'ex-upload-status ' + (cls || '');
  }

  function uploadFile(file) {
    if (!file) return;
    setStatus('Uploading \u201c' + file.name + '\u201d\u2026', 'busy');
    var fd = new FormData();
    fd.append('file', file);
    fetch(UPLOAD_URL, { method: 'POST', body: fd })
      .then(function(r){ return r.json().then(function(d){ return {ok: r.ok, d: d}; }); })
      .then(function(res){
        if (res.ok) {
          setStatus('\u2713 \u201c' + (res.d.originalName || file.name) + '\u201d received (' + Math.round(res.d.size/1024) + ' KB)', 'ok');
          zone.querySelector('.ex-drop-text').textContent = res.d.originalName || file.name;
        } else {
          setStatus('\u2715 ' + (res.d.message || 'Upload failed.'), 'err');
        }
      })
      .catch(function(){ setStatus('\u2715 Could not reach server. Please try again.', 'err'); });
  }

  input.addEventListener('change', function(){ uploadFile(this.files[0]); });

  zone.addEventListener('dragover',  function(e){ e.preventDefault(); zone.classList.add('ex-drag-over'); });
  zone.addEventListener('dragleave', function(){ zone.classList.remove('ex-drag-over'); });
  zone.addEventListener('drop',      function(e){
    e.preventDefault();
    zone.classList.remove('ex-drag-over');
    var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) uploadFile(f);
  });
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
<?php
    exit;
} );
