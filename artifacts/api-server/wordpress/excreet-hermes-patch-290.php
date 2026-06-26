<?php
/**
 * Plugin Name: Excreet Hermes Patch 2.9.0
 * Description: Clinical Pattern Report renderer, pharmaceutical intake form,
 *              lab image upload endpoint. Supersedes patch 2.8.0 rendering.
 * Version:     2.9.0
 *
 * Load order (alphabetical mu-plugin order):
 *   excreet-hermes-client.php      ← main plugin
 *   excreet-hermes-patch-272.php   ← job-id / token storage
 *   excreet-hermes-patch-280.php   ← v2 schema rendering (overridden here)
 *   excreet-hermes-patch-290.php   ← THIS FILE — Clinical Pattern Report + intake form
 *
 * Shortcodes provided:
 *   [excreet_pharmaceutical_intake]      — branded member intake form
 *   [excreet_hermes_processing_result]   — OVERRIDE: Clinical Pattern Report poller
 *   [excreet_hermes_latest_result]       — OVERRIDE: library/dashboard viewer
 *
 * REST endpoints added:
 *   POST /wp-json/excreet/v1/upload-lab-image  — authenticated lab image upload
 *   POST /wp-json/excreet/v1/submit-intake     — secure intake proxy → Hermes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Brand tokens ──────────────────────────────────────────────────────────────
define( 'EX290_PURPLE',      '#6B2FA0' );
define( 'EX290_PURPLE_DARK', '#3D1060' );
define( 'EX290_PURPLE_LIGHT','#EDE7F6' );
define( 'EX290_GOLD',        '#C9A84C' );
define( 'EX290_GOLD_LIGHT',  '#FDF6E3' );
define( 'EX290_HIGH',        '#C0392B' );
define( 'EX290_HIGH_BG',     '#FDEDEC' );
define( 'EX290_MOD',         '#E67E22' );
define( 'EX290_MOD_BG',      '#FEF9E7' );
define( 'EX290_AWARE',       '#2980B9' );
define( 'EX290_AWARE_BG',    '#EBF5FB' );
define( 'EX290_DARK',        '#1A0A2E' );
define( 'EX290_GRAY',        '#6B7A8D' );

/* ════════════════════════════════════════════════════════════════════════════
   BOOT
   ════════════════════════════════════════════════════════════════════════════ */

add_action( 'init',            'excreet_290_override_shortcodes', 30 );
add_action( 'rest_api_init',   'excreet_290_register_routes' );

/* ════════════════════════════════════════════════════════════════════════════
   SHORTCODE REGISTRATION
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_290_override_shortcodes(): void {
    // Override 2.8.0 processing + latest result shortcodes
    remove_shortcode( 'excreet_hermes_processing_result' );
    remove_shortcode( 'excreet_hermes_latest_result' );
    add_shortcode( 'excreet_hermes_processing_result', 'excreet_290_processing_shortcode' );
    add_shortcode( 'excreet_hermes_latest_result',     'excreet_290_latest_shortcode' );

    // New: pharmaceutical intake form
    remove_shortcode( 'excreet_pharmaceutical_intake' );
    add_shortcode( 'excreet_pharmaceutical_intake', 'excreet_290_intake_form_shortcode' );
}

/* ════════════════════════════════════════════════════════════════════════════
   REST ROUTES
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_290_register_routes(): void {

    register_rest_route( 'excreet/v1', '/upload-lab-image', [
        'methods'             => 'POST',
        'callback'            => 'excreet_290_upload_lab_image',
        'permission_callback' => 'is_user_logged_in',
    ] );

    register_rest_route( 'excreet/v1', '/submit-intake', [
        'methods'             => 'POST',
        'callback'            => 'excreet_290_submit_intake',
        'permission_callback' => 'is_user_logged_in',
    ] );
}

/* ─── Lab image upload ───────────────────────────────────────────────────── */

function excreet_290_upload_lab_image( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $files = $request->get_file_params();
    if ( empty( $files['lab_image'] ) ) {
        return new WP_Error( 'no_file', 'No file uploaded.', [ 'status' => 400 ] );
    }

    $attachment_id = media_handle_upload( 'lab_image', 0 );
    if ( is_wp_error( $attachment_id ) ) {
        return $attachment_id;
    }

    update_post_meta( $attachment_id, '_excreet_lab_upload', get_current_user_id() );

    return new WP_REST_Response( [
        'attachment_id' => $attachment_id,
        'url'           => wp_get_attachment_url( $attachment_id ),
    ], 200 );
}

/* ─── Secure intake proxy → Hermes ──────────────────────────────────────── */

function excreet_290_submit_intake( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    $body = $request->get_json_params();
    if ( empty( $body ) ) {
        return new WP_Error( 'bad_request', 'JSON body required.', [ 'status' => 400 ] );
    }

    $user_id   = get_current_user_id();
    $member_id = 'wp_user_' . $user_id;

    $hermes_url = defined( 'EXCREET_HERMES_BASE_URL' )
        ? rtrim( EXCREET_HERMES_BASE_URL, '/' ) . '/api/hermes/intake'
        : '';

    if ( $hermes_url === '' ) {
        return new WP_Error( 'config_error', 'Hermes URL not configured.', [ 'status' => 500 ] );
    }

    $api_key = defined( 'EXCREET_HERMES_API_KEY' ) ? EXCREET_HERMES_API_KEY : '';

    $response = wp_remote_post( $hermes_url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body'    => wp_json_encode( [
            'member_id'     => $member_id,
            'workflow_type' => 'pharmaceutical_intake',
            'payload'       => $body,
        ] ),
        'timeout' => 20,
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'hermes_error', $response->get_error_message(), [ 'status' => 502 ] );
    }

    $code         = wp_remote_retrieve_response_code( $response );
    $hermes_body  = json_decode( wp_remote_retrieve_body( $response ), true );
    $job_id       = $hermes_body['jobId'] ?? '';

    if ( $job_id ) {
        set_transient( 'excreet_job_' . $user_id, $job_id, 2 * HOUR_IN_SECONDS );
        update_user_meta( $user_id, 'excreet_active_job_id', $job_id );
    }

    return new WP_REST_Response( $hermes_body, (int) $code );
}

/* ════════════════════════════════════════════════════════════════════════════
   INTAKE FORM SHORTCODE
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_290_intake_form_shortcode(): string {

    /* ── Monthly background rotation (1 = Jan … 12 = Dec) ── */
    $bg_month = str_pad( (int) date( 'n' ), 2, '0', STR_PAD_LEFT );
    $bg_url   = 'https://excreet.com/wp-content/uploads/healer-bg-' . $bg_month . '.jpg';

    if ( ! is_user_logged_in() ) {
        return '<div style="' . excreet_290_card_style() . 'text-align:center;padding:48px 24px;">
            <div style="font-size:40px;margin-bottom:16px;">🔒</div>
            <h3 style="color:' . EX290_PURPLE . ';margin:0 0 10px;">Members Only</h3>
            <p style="color:' . EX290_GRAY . ';margin:0 0 20px;">Please log in to access your intake form.</p>
            <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" style="' . excreet_290_btn_style() . '">Log In</a>
        </div>';
    }

    $submit_url   = rest_url( 'excreet/v1/submit-intake' );
    $upload_url   = rest_url( 'excreet/v1/upload-lab-image' );
    $redirect_url = home_url( '/intake-processing/' );
    $nonce        = wp_create_nonce( 'wp_rest' );
    $hermes_base  = defined( 'EXCREET_HERMES_BASE_URL' ) ? rtrim( EXCREET_HERMES_BASE_URL, '/' ) : '';

    ob_start();
    ?>
    <style>

    /* ── Page-level atmosphere — bright botanical, promise-reflecting ── */

    /* Hide site header on the intake page */
    body.page-id-21 .site-header,
    body.page-id-21 #masthead,
    body.page-id-21 header.site-header,
    body.page-id-21 #site-header { display: none !important; }

    /* Hide redundant page title */
    body.page-id-21 h1.entry-title,
    body.page-id-21 .entry-header,
    body.page-id-21 .page-header { display: none !important; }

    /* Full-page background — monthly botanical image, auto-rotates each month */
    body.page-id-21 {
        background: url('<?php echo esc_url( $bg_url ); ?>') center/cover no-repeat fixed !important;
    }

    /* Let all WP page wrappers be transparent so the photo shows through */
    body.page-id-21 #page,
    body.page-id-21 .site-content,
    body.page-id-21 #content,
    body.page-id-21 #main,
    body.page-id-21 .site-main,
    body.page-id-21 .entry-content {
        background: transparent !important;
    }

    body.page-id-21 .entry-content {
        padding-top: 40px !important;
        padding-bottom: 60px !important;
    }

    /* Form card floats over the photo with a soft lifted shadow */
    body.page-id-21 .ex290-form {
        box-shadow: 0 8px 48px rgba(30, 10, 60, 0.22), 0 2px 12px rgba(30, 10, 60, 0.10) !important;
        margin: 0 auto !important;
    }

    .ex290-form { font-family: 'Georgia', serif; }
    .ex290-form * { box-sizing: border-box; }
    .ex290-field { margin-bottom: 20px; }
    .ex290-field label { display:block; font-size:13px; font-weight:700; color:<?php echo EX290_PURPLE_DARK; ?>; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; }
    .ex290-field input, .ex290-field select, .ex290-field textarea {
        width:100%; padding:12px 14px; border:1.5px solid #D5C5E8;
        border-radius:8px; font-size:15px; color:<?php echo EX290_DARK; ?>;
        background:#FDFBFF; transition:border-color .2s;
        font-family:inherit;
    }
    .ex290-field input:focus, .ex290-field select:focus, .ex290-field textarea:focus {
        outline:none; border-color:<?php echo EX290_PURPLE; ?>; background:#fff;
    }
    .ex290-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .ex290-row-3 { display:grid; grid-template-columns:2fr 1fr 1fr; gap:12px; }
    @media(max-width:600px) { .ex290-row, .ex290-row-3 { grid-template-columns:1fr; } }
    .ex290-med-block { background:#FAF7FF; border:1px solid #D5C5E8; border-radius:10px; padding:16px; margin-bottom:12px; position:relative; }
    .ex290-med-remove { position:absolute; top:10px; right:12px; background:none; border:none; color:#C0392B; font-size:18px; cursor:pointer; line-height:1; }
    .ex290-section-title { font-size:13px; font-weight:700; color:<?php echo EX290_PURPLE; ?>; text-transform:uppercase; letter-spacing:.08em; margin:28px 0 14px; padding-bottom:8px; border-bottom:2px solid <?php echo EX290_GOLD; ?>; }
    .ex290-add-btn { background:none; border:1.5px dashed <?php echo EX290_PURPLE; ?>; color:<?php echo EX290_PURPLE; ?>; border-radius:8px; padding:10px 18px; font-size:13px; font-weight:700; cursor:pointer; text-transform:uppercase; letter-spacing:.05em; width:100%; transition:all .2s; }
    .ex290-add-btn:hover { background:<?php echo EX290_PURPLE_LIGHT; ?>; }
    .ex290-submit-btn { width:100%; padding:16px; background:linear-gradient(135deg, <?php echo EX290_PURPLE; ?>, <?php echo EX290_PURPLE_DARK; ?>); color:#fff; border:none; border-radius:10px; font-size:16px; font-weight:700; cursor:pointer; letter-spacing:.05em; text-transform:uppercase; transition:opacity .2s; margin-top:10px; }
    .ex290-submit-btn:hover { opacity:.88; }
    .ex290-submit-btn:disabled { opacity:.5; cursor:wait; }
    .ex290-upload-area { border:2px dashed #D5C5E8; border-radius:10px; padding:24px; text-align:center; cursor:pointer; transition:border-color .2s; background:#FDFBFF; }
    .ex290-upload-area:hover { border-color:<?php echo EX290_PURPLE; ?>; background:<?php echo EX290_PURPLE_LIGHT; ?>; }
    .ex290-upload-area input[type=file] { display:none; }
    .ex290-upload-label { color:<?php echo EX290_PURPLE; ?>; font-weight:700; font-size:14px; display:block; margin-bottom:4px; }
    .ex290-upload-hint { color:<?php echo EX290_GRAY; ?>; font-size:12px; }
    .ex290-file-list { margin-top:10px; text-align:left; }
    .ex290-file-item { font-size:13px; color:#137333; margin:4px 0; }
    .ex290-error { background:#FDEDEC; border:1px solid #F1948A; border-radius:8px; padding:12px 16px; color:#C0392B; font-size:14px; margin-bottom:16px; display:none; }
    .ex290-status { text-align:center; padding:12px; color:<?php echo EX290_PURPLE; ?>; font-size:14px; font-weight:600; display:none; }
    </style>

    <div class="ex290-form" style="max-width:820px;background:#fff;border-radius:16px;border:1px solid #D5C5E8;overflow:hidden;">
        <!-- Header -->
        <div style="background:linear-gradient(135deg, <?php echo EX290_PURPLE_DARK; ?>, <?php echo EX290_PURPLE; ?>);padding:28px 32px;">
            <div style="display:flex;align-items:center;gap:14px;">
                <img src="https://excreet.com/wp-content/uploads/excreet-hero-logo.png" alt="Excreet" style="width:52px;height:52px;object-fit:contain;flex-shrink:0;">
                <div>
                    <div style="color:<?php echo EX290_GOLD; ?>;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;">Excreet™</div>
                    <div style="color:#fff;font-size:20px;font-weight:700;line-height:1.2;">Member Clinical Intake</div>
                    <div style="color:rgba(255,255,255,.65);font-size:13px;margin-top:2px;">Pharmaceutical pattern analysis — confidential</div>
                </div>
            </div>
        </div>

        <div style="padding:32px;">
            <div class="ex290-error" id="ex290-error"></div>
            <div class="ex290-status" id="ex290-status"></div>

            <form id="ex290-form" novalidate>

                <!-- Profile -->
                <div class="ex290-section-title">Your Profile</div>
                <div class="ex290-row">
                    <div class="ex290-field">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required placeholder="First name">
                    </div>
                    <div class="ex290-field">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required placeholder="Last name">
                    </div>
                </div>
                <div class="ex290-row">
                    <div class="ex290-field">
                        <label>Email *</label>
                        <input type="email" name="email" required placeholder="your@email.com">
                    </div>
                    <div class="ex290-field">
                        <label>Preferred Alias</label>
                        <input type="text" name="alias" placeholder="How should Hermes address you?">
                    </div>
                </div>
                <div class="ex290-row-3">
                    <div class="ex290-field">
                        <label>Age *</label>
                        <input type="number" name="age" required placeholder="e.g. 63" min="18" max="110">
                    </div>
                    <div class="ex290-field">
                        <label>Sex *</label>
                        <select name="sex" required>
                            <option value="">Select</option>
                            <option value="Female">Female</option>
                            <option value="Male">Male</option>
                            <option value="Non-binary">Non-binary</option>
                        </select>
                    </div>
                    <div class="ex290-field">
                        <label>State</label>
                        <input type="text" name="state" placeholder="e.g. CA">
                    </div>
                </div>
                <div class="ex290-field">
                    <label>City</label>
                    <input type="text" name="city" placeholder="e.g. Inglewood">
                </div>

                <!-- Pharmaceutical Profile -->
                <div class="ex290-section-title">Prescribed Medications</div>
                <p style="font-size:13px;color:<?php echo EX290_GRAY; ?>;margin:-8px 0 16px;line-height:1.6;">List every medication your doctor has prescribed, including over-the-counter drugs taken daily. Include dosage and how often you take it. <strong style="color:<?php echo EX290_PURPLE; ?>;">More detail = more precise patterns.</strong></p>

                <div id="ex290-med-list">
                    <div class="ex290-med-block" data-index="0">
                        <button type="button" class="ex290-med-remove" onclick="excreet290RemoveMed(this)" title="Remove" style="display:none;">×</button>
                        <div class="ex290-row-3">
                            <div class="ex290-field" style="margin-bottom:0;">
                                <label>Medication Name *</label>
                                <input type="text" name="med_name_0" placeholder="e.g. Lisinopril">
                            </div>
                            <div class="ex290-field" style="margin-bottom:0;">
                                <label>Dosage</label>
                                <input type="text" name="med_dose_0" placeholder="e.g. 10 mg">
                            </div>
                            <div class="ex290-field" style="margin-bottom:0;">
                                <label>Frequency</label>
                                <input type="text" name="med_freq_0" placeholder="e.g. 2× daily">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="ex290-add-btn" onclick="excreet290AddMed()">+ Add Another Medication</button>

                <!-- Duration -->
                <div class="ex290-section-title">Medication History</div>
                <div class="ex290-field">
                    <label>How long have you been on these medications? *</label>
                    <select name="exposure_duration" required>
                        <option value="">Select duration</option>
                        <option value="Less than 6 months">Less than 6 months</option>
                        <option value="6–12 months">6–12 months</option>
                        <option value="1–2 years">1–2 years</option>
                        <option value="3–5 years">3–5 years</option>
                        <option value="6–10 years (Chronic Use)">6–10 years (Chronic Use)</option>
                        <option value="10+ years (Long-term Chronic Use)">10+ years (Long-term Chronic Use)</option>
                    </select>
                </div>
                <div class="ex290-field">
                    <label>Observable Signals</label>
                    <textarea name="observable_signals" rows="4" placeholder="Describe what you are feeling. e.g. fatigue, dizziness when standing, muscle cramps, cold hands or feet, bruising easily, shortness of breath..."></textarea>
                </div>

                <!-- Lab Upload -->
                <div class="ex290-section-title">Lab Results (Optional)</div>
                <p style="font-size:13px;color:<?php echo EX290_GRAY; ?>;margin:-8px 0 16px;line-height:1.6;">Upload photos or scans of your most recent bloodwork, urinalysis, or any lab report. Your patterns become more precise when Hermes can see your real numbers.</p>
                <div class="ex290-upload-area" onclick="document.getElementById('ex290-file-input').click();">
                    <input type="file" id="ex290-file-input" accept="image/*,.pdf" multiple>
                    <span class="ex290-upload-label">📋 Click to Upload Lab Images</span>
                    <span class="ex290-upload-hint">JPG, PNG, PDF — up to 5 files, 10 MB each</span>
                    <div class="ex290-file-list" id="ex290-file-list"></div>
                </div>

                <!-- Submit -->
                <div class="ex290-section-title">Submit Your Intake</div>
                <p style="font-size:13px;color:<?php echo EX290_GRAY; ?>;margin:-8px 0 20px;line-height:1.6;">Your data is confidential and protected. Hermes will analyze your pharmaceutical pattern and return your personalized Clinical Pattern Report. This typically takes 15–30 seconds.</p>
                <button type="submit" class="ex290-submit-btn" id="ex290-submit">Analyze My Pattern →</button>

                <p style="font-size:11px;color:<?php echo EX290_GRAY; ?>;text-align:center;margin:16px 0 0;line-height:1.6;">This intake is for educational purposes only and does not constitute medical advice. Always consult your physician for clinical decisions.</p>
            </form>
        </div>
    </div>

    <script>
    (function(){
        var medCount = 1;

        window.excreet290AddMed = function() {
            var idx = medCount++;
            var list = document.getElementById('ex290-med-list');
            var div = document.createElement('div');
            div.className = 'ex290-med-block';
            div.dataset.index = idx;
            div.innerHTML = '<button type="button" class="ex290-med-remove" onclick="excreet290RemoveMed(this)" title="Remove">×</button>'
                + '<div class="ex290-row-3">'
                + '<div class="ex290-field" style="margin-bottom:0;"><label>Medication Name</label><input type="text" name="med_name_' + idx + '" placeholder="e.g. Metformin"></div>'
                + '<div class="ex290-field" style="margin-bottom:0;"><label>Dosage</label><input type="text" name="med_dose_' + idx + '" placeholder="e.g. 500 mg"></div>'
                + '<div class="ex290-field" style="margin-bottom:0;"><label>Frequency</label><input type="text" name="med_freq_' + idx + '" placeholder="e.g. 1× daily"></div>'
                + '</div>';
            list.appendChild(div);
        };

        window.excreet290RemoveMed = function(btn) {
            btn.closest('.ex290-med-block').remove();
        };

        // File picker
        var fileInput = document.getElementById('ex290-file-input');
        var fileList  = document.getElementById('ex290-file-list');
        fileInput.addEventListener('change', function() {
            fileList.innerHTML = '';
            Array.from(this.files).forEach(function(f) {
                var item = document.createElement('div');
                item.className = 'ex290-file-item';
                item.textContent = '✓ ' + f.name;
                fileList.appendChild(item);
            });
        });

        var submitBtn  = document.getElementById('ex290-submit');
        var errorEl    = document.getElementById('ex290-error');
        var statusEl   = document.getElementById('ex290-status');
        var uploadUrl  = <?php echo wp_json_encode( $upload_url ); ?>;
        var submitUrl  = <?php echo wp_json_encode( $submit_url ); ?>;
        var redirectTo = <?php echo wp_json_encode( $redirect_url ); ?>;
        var nonce      = <?php echo wp_json_encode( $nonce ); ?>;
        var hermesBase = <?php echo wp_json_encode( $hermes_base ); ?>;

        function showError(msg) {
            errorEl.textContent = msg;
            errorEl.style.display = 'block';
            statusEl.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Analyze My Pattern →';
        }

        function showStatus(msg) {
            statusEl.textContent = msg;
            statusEl.style.display = 'block';
        }

        document.getElementById('ex290-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            errorEl.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Analyzing...';

            var fd = new FormData(this);

            // Collect medications
            var meds = [];
            document.querySelectorAll('#ex290-med-list .ex290-med-block').forEach(function(block) {
                var idx = block.dataset.index;
                var nameEl = block.querySelector('[name="med_name_' + idx + '"]');
                var doseEl = block.querySelector('[name="med_dose_' + idx + '"]');
                var freqEl = block.querySelector('[name="med_freq_' + idx + '"]');
                var name = nameEl ? nameEl.value.trim() : '';
                if (name) {
                    meds.push({
                        name: name,
                        dosage: doseEl ? doseEl.value.trim() : '',
                        frequency: freqEl ? freqEl.value.trim() : ''
                    });
                }
            });

            if (meds.length === 0) {
                showError('Please enter at least one prescribed medication.');
                return;
            }

            // Upload lab images if any
            var labImageUrls = [];
            var files = fileInput.files;
            if (files.length > 0) {
                showStatus('Uploading your lab images...');
                for (var i = 0; i < files.length; i++) {
                    try {
                        var imgForm = new FormData();
                        imgForm.append('lab_image', files[i]);
                        var imgResp = await fetch(uploadUrl, {
                            method: 'POST',
                            headers: { 'X-WP-Nonce': nonce },
                            body: imgForm
                        });
                        var imgData = await imgResp.json();
                        if (imgData.url) labImageUrls.push(imgData.url);
                    } catch(err) {
                        console.warn('Image upload failed:', err);
                    }
                }
            }

            showStatus('Hermes is analyzing your pharmaceutical pattern...');

            var payload = {
                first_name:          fd.get('first_name'),
                last_name:           fd.get('last_name'),
                email:               fd.get('email'),
                alias:               fd.get('alias') || fd.get('first_name'),
                age:                 fd.get('age'),
                sex:                 fd.get('sex'),
                city:                fd.get('city'),
                state:               fd.get('state'),
                exposure_duration:   fd.get('exposure_duration'),
                observable_signals:  fd.get('observable_signals'),
                prescribed_medications: meds,
                lab_image_urls:      labImageUrls
            };

            try {
                var resp = await fetch(submitUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify(payload)
                });
                var data = await resp.json();

                if (!resp.ok || !data.jobId) {
                    showError(data.message || 'Submission failed. Please try again.');
                    return;
                }

                // Store job ID in session storage for processing page pickup
                try { sessionStorage.setItem('excreet_job_id', data.jobId); } catch(e){}

                // Carry re-baseline flag to the processing page via sessionStorage
                try {
                    if (window.location.search.indexOf('rebaseline=1') !== -1) {
                        sessionStorage.setItem('excreet_rebaseline', '1');
                    }
                } catch(e) {}

                window.location.href = redirectTo + '?job_id=' + encodeURIComponent(data.jobId);

            } catch(err) {
                showError('Unable to reach the server. Please check your connection and try again.');
            }
        });
    })();
    </script>
    <?php
    return (string) ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   PROCESSING PAGE SHORTCODE — POLLER + CLINICAL PATTERN REPORT RENDERER
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_290_processing_shortcode(): string {
    // Job ID from: URL param → transient → user meta → session fallback
    $job_id = '';
    if ( isset( $_GET['job_id'] ) ) {
        $job_id = sanitize_key( (string) $_GET['job_id'] );
    }
    if ( $job_id === '' && is_user_logged_in() ) {
        $uid = get_current_user_id();
        $job_id = (string) ( get_transient( 'excreet_job_' . $uid ) ?: get_user_meta( $uid, 'excreet_active_job_id', true ) ?: '' );
    }

    // Delegate token flow to 2.7.2 patch if still using old token system
    if ( $job_id === '' && function_exists( 'excreet_read_processing_job_id' ) ) {
        $raw = excreet_read_processing_job_id();
        if ( strpos( $raw, 'PENDING_TOKEN:' ) !== 0 ) {
            $job_id = sanitize_key( $raw );
        }
    }

    $hermes_base   = defined( 'EXCREET_HERMES_BASE_URL' ) ? rtrim( EXCREET_HERMES_BASE_URL, '/' ) : '';
    $result_url    = $hermes_base . '/api/hermes/result/';
    $store_v2      = rest_url( 'excreet/v1/store-result-v2' );
    $nonce         = wp_create_nonce( 'wp_rest' );

    ob_start();
    ?>
    <?php echo excreet_290_report_styles(); ?>

    <div id="ex290-report-wrap" style="max-width:900px;">

        <!-- Loading state -->
        <div id="ex290-loading" style="background:#fff;border-radius:16px;border:1px solid #D5C5E8;padding:48px;text-align:center;">
            <div style="width:56px;height:56px;border:4px solid <?php echo EX290_PURPLE_LIGHT; ?>;border-top-color:<?php echo EX290_PURPLE; ?>;border-radius:50%;animation:ex290spin 1s linear infinite;margin:0 auto 20px;"></div>
            <h3 id="ex290-load-title" style="color:<?php echo EX290_PURPLE; ?>;margin:0 0 8px;">Hermes is reading your pattern...</h3>
            <p id="ex290-load-msg" style="color:<?php echo EX290_GRAY; ?>;margin:0;font-size:14px;">This typically takes 15–30 seconds. Please stay on this page.</p>
        </div>

        <!-- Error state (hidden) -->
        <div id="ex290-error-state" style="display:none;background:#fff;border-radius:16px;border:1px solid #F1948A;padding:48px;text-align:center;">
            <div style="font-size:40px;margin-bottom:16px;">⚠️</div>
            <h3 style="color:#C0392B;margin:0 0 10px;">Processing Could Not Be Completed</h3>
            <p id="ex290-error-msg" style="color:<?php echo EX290_GRAY; ?>;margin:0 0 20px;font-size:14px;"></p>
            <a href="<?php echo esc_url( home_url( '/member-intake-form/' ) ); ?>" style="<?php echo excreet_290_btn_style(); ?>">Return to Intake Form</a>
        </div>

        <!-- Report (hidden until complete) -->
        <div id="ex290-report" style="display:none;"></div>

    </div>

    <style>
    @keyframes ex290spin { to { transform: rotate(360deg); } }
    </style>

    <?php if ( $job_id !== '' ) : ?>
    <script>
    (function(){
        var jobId       = <?php echo wp_json_encode( $job_id ); ?>;
        var resultBase  = <?php echo wp_json_encode( $result_url ); ?>;
        var storeV2     = <?php echo wp_json_encode( $store_v2 ); ?>;
        var nonce       = <?php echo wp_json_encode( $nonce ); ?>;
        var timeoutAt   = Date.now() + 5 * 60 * 1000;
        var interval    = 3000;

        var loadTitle  = document.getElementById('ex290-load-title');
        var loadMsg    = document.getElementById('ex290-load-msg');
        var loading    = document.getElementById('ex290-loading');
        var errorState = document.getElementById('ex290-error-state');
        var errorMsg   = document.getElementById('ex290-error-msg');
        var reportEl   = document.getElementById('ex290-report');

        function poll() {
            if (Date.now() > timeoutAt) {
                showError('Processing timed out. Please resubmit your intake form.', 'Timeout');
                return;
            }
            fetch(resultBase + encodeURIComponent(jobId))
                .then(function(r){ return r.json().catch(function(){ return {}; }); })
                .then(function(data){
                    var status = data.status || '';
                    if (status === 'pending')    { loadMsg.textContent = 'Queued — your intake is next...'; setTimeout(poll, interval); return; }
                    if (status === 'processing') { loadMsg.textContent = 'Analyzing your pharmaceutical pattern...'; setTimeout(poll, interval); return; }
                    if (status === 'completed')  { renderReport(data.result || {}); return; }
                    if (status === 'failed')     { showError('Analysis could not be completed. ' + (data.error || ''), 'Analysis Failed'); return; }
                    setTimeout(poll, interval);
                })
                .catch(function(){ setTimeout(poll, interval); });
        }

        function showError(msg, title) {
            loading.style.display = 'none';
            errorState.style.display = 'block';
            errorMsg.textContent = msg;
        }

        function renderReport(r) {
            loading.style.display = 'none';

            // Detect schema type — clinical_pattern vs health_intake
            var isClinical = r.memberProfile || r.prescribedPharmaceuticals || r.drugInteractionLoops;

            if (isClinical) {
                reportEl.innerHTML = buildClinicalReport(r);
            } else {
                reportEl.innerHTML = buildHealthReport(r);
            }
            reportEl.style.display = 'block';

            // Store to WP user meta
            fetch(storeV2, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify({ jobId: jobId, result: r })
            }).catch(function(){});
        }

        // ── Clinical Pattern Report builder ─────────────────────────────────

        function esc(s) {
            return String(s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]; });
        }

        function safeArr(v) { return Array.isArray(v) ? v : (v ? [v] : []); }

        function rfColor(level) {
            return { HIGH_RISK: '<?php echo EX290_HIGH; ?>', MODERATE_RISK: '<?php echo EX290_MOD; ?>', AWARENESS: '<?php echo EX290_AWARE; ?>' }[level] || '<?php echo EX290_GRAY; ?>';
        }
        function rfBg(level) {
            return { HIGH_RISK: '<?php echo EX290_HIGH_BG; ?>', MODERATE_RISK: '<?php echo EX290_MOD_BG; ?>', AWARENESS: '<?php echo EX290_AWARE_BG; ?>' }[level] || '#f5f5f5';
        }
        function rfIcon(level) {
            return { HIGH_RISK: '🔴', MODERATE_RISK: '🟠', AWARENESS: '🔵' }[level] || '⚪';
        }
        function rfLabel(level) {
            return { HIGH_RISK: 'HIGH RISK', MODERATE_RISK: 'MODERATE RISK', AWARENESS: 'AWARENESS' }[level] || level;
        }
        function severityColor(s) {
            return { HIGH: '<?php echo EX290_HIGH; ?>', MODERATE: '<?php echo EX290_MOD; ?>', LOW: '#137333' }[s] || '<?php echo EX290_GRAY; ?>';
        }
        function actionColor(a) {
            return { Alert: '<?php echo EX290_HIGH; ?>', Monitor: '<?php echo EX290_MOD; ?>', Optimize: '#137333' }[a] || '<?php echo EX290_GRAY; ?>';
        }

        function buildClinicalReport(r) {
            var profile   = r.memberProfile || {};
            var pharma    = safeArr(r.prescribedPharmaceuticals);
            var redFlags  = safeArr(r.redFlagSummary);
            var loops     = safeArr(r.drugInteractionLoops);
            var labs      = safeArr(r.labMarkerTriggers);
            var signals   = safeArr(r.expectedObservableSignals);
            var interp    = esc(r.excreetInterpretation || '');
            var rec       = esc(r.recommendationSummary || '');
            var principle = esc(r.excreetPrinciple || 'We don\'t guess. We pattern. We don\'t treat symptoms.');
            var disc      = esc(r.disclaimer || '');

            var html = '';

            // ── HEADER ──────────────────────────────────────────────────────
            html += '<div class="ex290-report">';
            html += '<div class="ex290-report-header">';
            html += '<div style="display:flex;align-items:center;gap:14px;">';
            html += '<img class="ex290-logo-mark" src="https://excreet.com/wp-content/uploads/excreet-hero-logo.png" alt="Excreet">';
            html += '<div><div class="ex290-logo-label">EXCREET™</div><div class="ex290-logo-subtitle">CLINICAL PATTERN REPORT</div></div>';
            html += '</div>';
            html += '<div class="ex290-report-meta">';
            html += '<div class="ex290-meta-line"><strong>' + esc((profile.age||'') + ' ' + (profile.sex||'')) + '</strong></div>';
            html += '<div class="ex290-meta-line">Assessment: ' + esc(profile.assessmentDate||new Date().toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'})) + '</div>';
            html += '<div class="ex290-meta-line">Exposure: ' + esc(profile.exposureDuration||'') + '</div>';
            html += '</div>';
            html += '</div>'; // header

            // ── PRESCRIBED PHARMACEUTICALS ──────────────────────────────────
            if (pharma.length > 0) {
                html += '<div class="ex290-section-header" style="background:<?php echo EX290_PURPLE_DARK; ?>;">PRESCRIBED PHARMACEUTICALS</div>';
                html += '<div class="ex290-pharma-grid">';
                pharma.forEach(function(p) {
                    html += '<div class="ex290-pharma-pill">';
                    html += '<div class="ex290-pharma-name">' + esc(p.name||'') + '</div>';
                    html += '<div class="ex290-pharma-detail">' + esc(p.dosage||'') + (p.frequency ? ' · ' + esc(p.frequency) : '') + '</div>';
                    html += '</div>';
                });
                html += '</div>';
            }

            // ── RED FLAGS + INTERACTION LOOPS ───────────────────────────────
            html += '<div class="ex290-two-col">';

            // Left: Red Flag Summary
            html += '<div>';
            html += '<div class="ex290-col-title">RED FLAG SUMMARY</div>';
            redFlags.forEach(function(rf) {
                html += '<div class="ex290-redflag-card" style="background:' + rfBg(rf.level) + ';border-left:4px solid ' + rfColor(rf.level) + ';">';
                html += '<div class="ex290-rf-badge" style="color:' + rfColor(rf.level) + ';">' + rfIcon(rf.level) + ' ' + rfLabel(rf.level) + '</div>';
                html += '<div class="ex290-rf-title">' + esc(rf.title||'') + '</div>';
                html += '<div class="ex290-rf-desc">' + esc(rf.description||'') + '</div>';
                html += '</div>';
            });
            html += '</div>';

            // Right: Drug Interaction Mapping
            html += '<div>';
            html += '<div class="ex290-col-title">DRUG INTERACTION MAPPING</div>';
            loops.forEach(function(loop) {
                var sev = loop.severity || 'MODERATE';
                html += '<div class="ex290-loop-card">';
                html += '<div class="ex290-loop-name">' + esc(loop.name||'') + ' <span style="font-size:10px;font-weight:700;color:' + severityColor(sev) + ';background:' + rfBg(sev === 'HIGH' ? 'HIGH_RISK' : sev === 'MODERATE' ? 'MODERATE_RISK' : '') + ';padding:2px 7px;border-radius:4px;">' + esc(sev) + '</span></div>';
                html += '<div class="ex290-loop-meds">' + safeArr(loop.medications).map(function(m){ return '<span class="ex290-med-tag">' + esc(m) + '</span>'; }).join('') + '</div>';
                html += '<div class="ex290-loop-mech">' + esc(loop.mechanism||'') + '</div>';
                var effects = safeArr(loop.effects);
                if (effects.length) {
                    html += '<ul class="ex290-loop-effects">' + effects.map(function(ef){ return '<li>' + esc(ef) + '</li>'; }).join('') + '</ul>';
                }
                html += '</div>';
            });
            html += '</div>';

            html += '</div>'; // two-col

            // ── LAB MARKER TRIGGERS ─────────────────────────────────────────
            if (labs.length > 0) {
                html += '<div class="ex290-section-header" style="background:<?php echo EX290_PURPLE; ?>;">LAB MARKER TRIGGERS — WHAT TO MONITOR</div>';
                html += '<div style="overflow-x:auto;">';
                html += '<table class="ex290-lab-table">';
                html += '<thead><tr><th>RISK AREA</th><th>LAB MARKER</th><th>WHAT IT INDICATES</th><th>TARGET / ALERT LEVEL</th><th>ACTION</th></tr></thead><tbody>';
                labs.forEach(function(lab) {
                    html += '<tr>';
                    html += '<td><strong style="color:<?php echo EX290_PURPLE; ?>;">' + esc(lab.riskArea||'') + '</strong></td>';
                    html += '<td>' + esc(lab.labMarker||'') + '</td>';
                    html += '<td>' + esc(lab.whatItIndicates||'') + '</td>';
                    html += '<td>' + esc(lab.targetAlertLevel||'') + '</td>';
                    html += '<td><span class="ex290-action-badge" style="color:' + actionColor(lab.action) + ';border-color:' + actionColor(lab.action) + ';">' + esc(lab.action||'') + '</span></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                html += '</div>';
            }

            // ── OBSERVABLE SIGNALS ──────────────────────────────────────────
            if (signals.length > 0) {
                html += '<div class="ex290-signals-section">';
                html += '<div class="ex290-col-title" style="margin-bottom:14px;">EXPECTED OBSERVABLE SIGNALS</div>';
                html += '<div class="ex290-signals-grid">';
                signals.forEach(function(s) {
                    html += '<div class="ex290-signal-item"><span class="ex290-signal-check">✓</span>' + esc(s) + '</div>';
                });
                html += '</div>';
                html += '</div>';
            }

            // ── EXCREET INTERPRETATION ──────────────────────────────────────
            if (interp) {
                html += '<div class="ex290-two-col" style="gap:16px;">';
                html += '<div class="ex290-interp-box">';
                html += '<div style="font-size:24px;margin-bottom:10px;">📖</div>';
                html += '<div class="ex290-col-title">EXCREET INTERPRETATION</div>';
                html += '<p style="color:<?php echo EX290_DARK; ?>;line-height:1.75;font-size:14px;margin:0;">' + interp + '</p>';
                html += '</div>';

                html += '<div>';
                if (rec) {
                    html += '<div class="ex290-rec-box">';
                    html += '<div class="ex290-col-title" style="color:<?php echo EX290_GOLD; ?>;">EXCREET RECOMMENDATION SUMMARY</div>';
                    html += '<p style="color:<?php echo EX290_DARK; ?>;line-height:1.7;font-size:14px;margin:0;">' + rec + '</p>';
                    html += '</div>';
                }
                html += '<div class="ex290-principle-box">';
                html += '<div class="ex290-col-title" style="color:<?php echo EX290_GOLD; ?>;">EXCREET PRINCIPLE</div>';
                html += '<p style="color:<?php echo EX290_GOLD; ?>;font-style:italic;font-size:15px;margin:0;line-height:1.6;">"' + principle + '"</p>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }

            // ── FOOTER ──────────────────────────────────────────────────────
            html += '<div class="ex290-report-footer">';
            html += '<p style="margin:0 0 6px;font-size:11px;color:' + '<?php echo EX290_GRAY; ?>' + ';line-height:1.6;">' + disc + '</p>';
            html += '<p style="margin:0;font-size:11px;color:' + '<?php echo EX290_GRAY; ?>' + ';">© ' + new Date().getFullYear() + ' Excreet™. All Rights Reserved.</p>';
            html += '</div>';

            html += '</div>'; // ex290-report
            return html;
        }

        // ── Health intake fallback renderer (v2 schema) ─────────────────────
        function buildHealthReport(r) {
            var tier   = r.tier || 'nudge';
            var score  = r.vitalityScore || 0;
            var tread  = esc(r.trajectoryRead || '');
            var quick  = safeArr(r.quickActions);
            var med    = r.medicalPath || null;
            var min    = r.ministryPath || null;
            var disc   = esc(r.disclaimer || '');

            var tierLabels = { nudge:'Quick Nudge', checkin:'Check-In', protocol:'Protocol Recommended', alarm:'Attention Needed' };
            var tierColors = { nudge:'#137333', checkin:'#b45309', protocol:'<?php echo EX290_PURPLE; ?>', alarm:'<?php echo EX290_HIGH; ?>' };
            var tierBgs    = { nudge:'#e3fcec', checkin:'#fef3c7', protocol:'<?php echo EX290_PURPLE_LIGHT; ?>', alarm:'<?php echo EX290_HIGH_BG; ?>' };

            var html = '<div class="ex290-report"><div style="padding:28px;">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">';
            html += '<span style="background:' + (tierBgs[tier]||'#eee') + ';color:' + (tierColors[tier]||'#333') + ';padding:6px 14px;border-radius:999px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.06em;">' + (tierLabels[tier]||tier) + '</span>';
            html += '<div style="text-align:right;"><div style="font-size:12px;color:<?php echo EX290_GRAY; ?>;">Vitality Score</div><div style="font-size:36px;font-weight:900;color:' + (score>=70?'#137333':score>=45?'<?php echo EX290_MOD; ?>':'<?php echo EX290_HIGH; ?>') + ';">' + score + '<span style="font-size:14px;color:<?php echo EX290_GRAY; ?>;"> / 100</span></div></div>';
            html += '</div>';
            if (tread) html += '<div style="background:#f0f7ff;border-radius:10px;padding:16px;margin-bottom:16px;"><div style="font-size:12px;font-weight:700;color:<?php echo EX290_DARK; ?>;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">What Your Body Is Signaling</div><p style="margin:0;color:#334e68;line-height:1.75;">' + tread + '</p></div>';
            if (quick.length) html += '<div style="margin-bottom:16px;"><div style="font-size:12px;font-weight:700;color:<?php echo EX290_DARK; ?>;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">Immediate Actions</div><ul style="margin:0;padding-left:18px;color:#334e68;line-height:1.7;">' + quick.map(function(q){ return '<li>' + esc(q) + '</li>'; }).join('') + '</ul></div>';
            if (disc) html += '<p style="font-size:11px;color:<?php echo EX290_GRAY; ?>;border-top:1px solid #e6edf3;padding-top:12px;margin:16px 0 0;line-height:1.6;">' + disc + '</p>';
            html += '</div></div>';
            return html;
        }

        poll();
    })();
    </script>
    <?php else : ?>
    <script>
    document.getElementById('ex290-loading').innerHTML = '<div style="font-size:40px;margin-bottom:16px;">⚠️</div><h3 style="color:<?php echo EX290_PURPLE; ?>;margin:0 0 10px;">No Active Intake Found</h3><p style="color:<?php echo EX290_GRAY; ?>;margin:0 0 20px;font-size:14px;">We could not find an active intake session. Please submit the intake form to begin.</p><a href="<?php echo esc_url( home_url( '/member-intake-form/' ) ); ?>" style="<?php echo excreet_290_btn_style(); ?>">Go to Intake Form</a>';
    </script>
    <?php endif; ?>
    <?php
    return (string) ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   LATEST RESULT SHORTCODE (Excreet Library / Dashboard)
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_290_latest_shortcode(): string {
    if ( ! is_user_logged_in() ) {
        return excreet_290_locked_state( 'Your Excreet Library is for members only.' );
    }

    $user_id = get_current_user_id();
    $raw     = get_user_meta( $user_id, 'excreet_hermes_completed_result', true );

    if ( empty( $raw ) ) {
        ob_start();
        ?>
        <?php echo excreet_290_report_styles(); ?>
        <div style="<?php echo excreet_290_card_style(); ?>text-align:center;padding:48px 24px;max-width:760px;">
            <div style="font-size:48px;margin-bottom:16px;">📋</div>
            <h3 style="color:<?php echo EX290_PURPLE; ?>;margin:0 0 10px;">Your Excreet Library Is Empty</h3>
            <p style="color:<?php echo EX290_GRAY; ?>;margin:0 0 24px;font-size:14px;line-height:1.7;">Once you complete your Member Intake, your Clinical Pattern Report will be saved here as your personal health baseline.</p>
            <a href="<?php echo esc_url( home_url( '/member-intake-form/' ) ); ?>" style="<?php echo excreet_290_btn_style(); ?>">Begin My Intake →</a>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    $result = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );

    ob_start();
    ?>
    <?php echo excreet_290_report_styles(); ?>
    <div id="ex290-library-wrap" style="max-width:900px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="margin:0 0 4px;color:<?php echo EX290_PURPLE_DARK; ?>;">Your Excreet Library</h2>
                <p style="margin:0;font-size:13px;color:<?php echo EX290_GRAY; ?>;">Your Clinical Pattern Reports — your personal health baseline</p>
            </div>
            <a href="<?php echo esc_url( home_url( '/member-intake-form/' ) ); ?>" style="<?php echo excreet_290_btn_style( 'secondary' ); ?>">New Intake</a>
        </div>
        <div id="ex290-library-report"></div>
    </div>
    <script>
    (function(){
        var r = <?php echo wp_json_encode( $result ); ?>;
        // (reuse the same buildClinicalReport / buildHealthReport functions injected by processing page)
        // Since this may be standalone, we provide a simple renderer here.
        var el = document.getElementById('ex290-library-report');
        if (!el) return;

        function esc(s){ return String(s||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];}); }
        function safeArr(v){ return Array.isArray(v)?v:(v?[v]:[]); }
        function rfColor(l){ return{HIGH_RISK:'<?php echo EX290_HIGH; ?>',MODERATE_RISK:'<?php echo EX290_MOD; ?>',AWARENESS:'<?php echo EX290_AWARE; ?>'}[l]||'<?php echo EX290_GRAY; ?>'; }
        function rfBg(l){ return{HIGH_RISK:'<?php echo EX290_HIGH_BG; ?>',MODERATE_RISK:'<?php echo EX290_MOD_BG; ?>',AWARENESS:'<?php echo EX290_AWARE_BG; ?>'}[l]||'#f5f5f5'; }
        function rfLabel(l){ return{HIGH_RISK:'HIGH RISK',MODERATE_RISK:'MODERATE RISK',AWARENESS:'AWARENESS'}[l]||l; }
        function rfIcon(l){ return{HIGH_RISK:'🔴',MODERATE_RISK:'🟠',AWARENESS:'🔵'}[l]||'⚪'; }
        function sevColor(s){ return{HIGH:'<?php echo EX290_HIGH; ?>',MODERATE:'<?php echo EX290_MOD; ?>',LOW:'#137333'}[s]||'<?php echo EX290_GRAY; ?>'; }
        function actColor(a){ return{Alert:'<?php echo EX290_HIGH; ?>',Monitor:'<?php echo EX290_MOD; ?>',Optimize:'#137333'}[a]||'<?php echo EX290_GRAY; ?>'; }

        var isClinical = r.memberProfile || r.prescribedPharmaceuticals || r.drugInteractionLoops;
        if (!isClinical) { el.innerHTML='<p style="color:<?php echo EX290_GRAY; ?>;">Older report format. Please submit a new intake for a full Clinical Pattern Report.</p>'; return; }

        var profile=r.memberProfile||{}, pharma=safeArr(r.prescribedPharmaceuticals), redFlags=safeArr(r.redFlagSummary), loops=safeArr(r.drugInteractionLoops), labs=safeArr(r.labMarkerTriggers), signals=safeArr(r.expectedObservableSignals);
        var interp=esc(r.excreetInterpretation||''), rec=esc(r.recommendationSummary||''), principle=esc(r.excreetPrinciple||"We don't guess. We pattern. We don't treat symptoms."), disc=esc(r.disclaimer||'');

        var html='<div class="ex290-report">';
        html+='<div class="ex290-report-header"><div style="display:flex;align-items:center;gap:14px;"><div class="ex290-logo-mark">℮</div><div><div class="ex290-logo-label">EXCREET™</div><div class="ex290-logo-subtitle">CLINICAL PATTERN REPORT</div></div></div><div class="ex290-report-meta"><div class="ex290-meta-line"><strong>'+esc((profile.age||'')+' '+(profile.sex||''))+'</strong></div><div class="ex290-meta-line">Assessment: '+esc(profile.assessmentDate||'')+'</div><div class="ex290-meta-line">Exposure: '+esc(profile.exposureDuration||'')+'</div></div></div>';
        if(pharma.length){html+='<div class="ex290-section-header" style="background:<?php echo EX290_PURPLE_DARK; ?>;">PRESCRIBED PHARMACEUTICALS</div><div class="ex290-pharma-grid">'+pharma.map(function(p){return'<div class="ex290-pharma-pill"><div class="ex290-pharma-name">'+esc(p.name||'')+'</div><div class="ex290-pharma-detail">'+esc(p.dosage||'')+(p.frequency?' · '+esc(p.frequency):'')+'</div></div>';}).join('')+'</div>';}
        html+='<div class="ex290-two-col"><div><div class="ex290-col-title">RED FLAG SUMMARY</div>'+redFlags.map(function(rf){return'<div class="ex290-redflag-card" style="background:'+rfBg(rf.level)+';border-left:4px solid '+rfColor(rf.level)+';"><div class="ex290-rf-badge" style="color:'+rfColor(rf.level)+';">'+rfIcon(rf.level)+' '+rfLabel(rf.level)+'</div><div class="ex290-rf-title">'+esc(rf.title||'')+'</div><div class="ex290-rf-desc">'+esc(rf.description||'')+'</div></div>';}).join('')+'</div><div><div class="ex290-col-title">DRUG INTERACTION MAPPING</div>'+loops.map(function(loop){var sev=loop.severity||'MODERATE';return'<div class="ex290-loop-card"><div class="ex290-loop-name">'+esc(loop.name||'')+' <span style="font-size:10px;font-weight:700;color:'+sevColor(sev)+';padding:2px 7px;border-radius:4px;border:1px solid '+sevColor(sev)+';">'+esc(sev)+'</span></div><div class="ex290-loop-meds">'+safeArr(loop.medications).map(function(m){return'<span class="ex290-med-tag">'+esc(m)+'</span>';}).join('')+'</div><div class="ex290-loop-mech">'+esc(loop.mechanism||'')+'</div>'+(safeArr(loop.effects).length?'<ul class="ex290-loop-effects">'+safeArr(loop.effects).map(function(ef){return'<li>'+esc(ef)+'</li>';}).join('')+'</ul>':'')+'</div>';}).join('')+'</div></div>';
        if(labs.length){html+='<div class="ex290-section-header" style="background:<?php echo EX290_PURPLE; ?>;">LAB MARKER TRIGGERS — WHAT TO MONITOR</div><div style="overflow-x:auto;"><table class="ex290-lab-table"><thead><tr><th>RISK AREA</th><th>LAB MARKER</th><th>WHAT IT INDICATES</th><th>TARGET / ALERT LEVEL</th><th>ACTION</th></tr></thead><tbody>'+labs.map(function(lab){return'<tr><td><strong style="color:<?php echo EX290_PURPLE; ?>;">'+esc(lab.riskArea||'')+'</strong></td><td>'+esc(lab.labMarker||'')+'</td><td>'+esc(lab.whatItIndicates||'')+'</td><td>'+esc(lab.targetAlertLevel||'')+'</td><td><span class="ex290-action-badge" style="color:'+actColor(lab.action)+';border-color:'+actColor(lab.action)+';">'+esc(lab.action||'')+'</span></td></tr>';}).join('')+'</tbody></table></div>';}
        if(signals.length){html+='<div class="ex290-signals-section"><div class="ex290-col-title" style="margin-bottom:14px;">EXPECTED OBSERVABLE SIGNALS</div><div class="ex290-signals-grid">'+signals.map(function(s){return'<div class="ex290-signal-item"><span class="ex290-signal-check">✓</span>'+esc(s)+'</div>';}).join('')+'</div></div>';}
        if(interp){html+='<div class="ex290-two-col" style="gap:16px;"><div class="ex290-interp-box"><div style="font-size:24px;margin-bottom:10px;">📖</div><div class="ex290-col-title">EXCREET INTERPRETATION</div><p style="color:<?php echo EX290_DARK; ?>;line-height:1.75;font-size:14px;margin:0;">'+interp+'</p></div><div>'+(rec?'<div class="ex290-rec-box"><div class="ex290-col-title" style="color:<?php echo EX290_GOLD; ?>;">EXCREET RECOMMENDATION SUMMARY</div><p style="color:<?php echo EX290_DARK; ?>;line-height:1.7;font-size:14px;margin:0;">'+rec+'</p></div>':'')+'<div class="ex290-principle-box"><div class="ex290-col-title" style="color:<?php echo EX290_GOLD; ?>;">EXCREET PRINCIPLE</div><p style="color:<?php echo EX290_GOLD; ?>;font-style:italic;font-size:15px;margin:0;line-height:1.6;">"'+principle+'"</p></div></div></div>';}
        html+='<div class="ex290-report-footer"><p style="margin:0 0 6px;font-size:11px;color:<?php echo EX290_GRAY; ?>;line-height:1.6;">'+disc+'</p><p style="margin:0;font-size:11px;color:<?php echo EX290_GRAY; ?>;">© '+new Date().getFullYear()+' Excreet™. All Rights Reserved.</p></div>';
        html+='</div>';
        el.innerHTML=html;
    })();
    </script>
    <?php
    return (string) ob_get_clean();
}

/* ════════════════════════════════════════════════════════════════════════════
   SHARED CSS
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_290_report_styles(): string {
    return '<style>
    .ex290-report { font-family: Georgia, "Times New Roman", serif; background:#fff; border-radius:16px; border:1px solid #D5C5E8; overflow:hidden; }
    .ex290-report * { box-sizing:border-box; }
    .ex290-report-header { background:linear-gradient(135deg, ' . EX290_PURPLE_DARK . ', ' . EX290_PURPLE . '); padding:24px 32px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; }
    .ex290-logo-mark { width:52px; height:52px; object-fit:contain; flex-shrink:0; display:block; }
    .ex290-logo-label { color:' . EX290_GOLD . '; font-size:11px; font-weight:700; letter-spacing:.15em; text-transform:uppercase; }
    .ex290-logo-subtitle { color:#fff; font-size:18px; font-weight:700; letter-spacing:.04em; }
    .ex290-report-meta { text-align:right; }
    .ex290-meta-line { color:rgba(255,255,255,.85); font-size:13px; line-height:1.6; }
    .ex290-section-header { padding:10px 28px; font-size:12px; font-weight:700; letter-spacing:.1em; color:#fff; text-transform:uppercase; }
    .ex290-pharma-grid { display:flex; flex-wrap:wrap; gap:10px; padding:20px 28px; background:' . EX290_PURPLE_LIGHT . '; }
    .ex290-pharma-pill { background:#fff; border:1px solid #D5C5E8; border-radius:8px; padding:10px 14px; min-width:160px; }
    .ex290-pharma-name { font-size:13px; font-weight:700; color:' . EX290_PURPLE_DARK . '; }
    .ex290-pharma-detail { font-size:12px; color:' . EX290_GRAY . '; margin-top:2px; }
    .ex290-two-col { display:grid; grid-template-columns:1fr 1fr; gap:0; }
    @media(max-width:680px){ .ex290-two-col { grid-template-columns:1fr; } }
    .ex290-two-col > div { padding:24px 28px; }
    .ex290-two-col > div:first-child { border-right:1px solid #E8E0F0; }
    .ex290-col-title { font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:' . EX290_PURPLE . '; margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid ' . EX290_GOLD . '; }
    .ex290-redflag-card { border-radius:8px; padding:14px; margin-bottom:12px; }
    .ex290-rf-badge { font-size:11px; font-weight:700; letter-spacing:.06em; margin-bottom:6px; }
    .ex290-rf-title { font-size:14px; font-weight:700; color:' . EX290_DARK . '; margin-bottom:4px; }
    .ex290-rf-desc { font-size:13px; color:#444; line-height:1.6; }
    .ex290-loop-card { background:' . EX290_PURPLE_LIGHT . '; border-radius:8px; padding:14px; margin-bottom:12px; }
    .ex290-loop-name { font-size:13px; font-weight:700; color:' . EX290_PURPLE_DARK . '; margin-bottom:8px; }
    .ex290-loop-meds { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; }
    .ex290-med-tag { font-size:11px; background:' . EX290_PURPLE . '; color:#fff; border-radius:4px; padding:2px 8px; font-weight:600; }
    .ex290-loop-mech { font-size:12px; color:' . EX290_GRAY . '; margin-bottom:6px; line-height:1.5; font-style:italic; }
    .ex290-loop-effects { margin:0; padding-left:16px; font-size:13px; color:' . EX290_DARK . '; line-height:1.65; }
    .ex290-lab-table { width:100%; border-collapse:collapse; font-size:13px; }
    .ex290-lab-table th { background:' . EX290_PURPLE_LIGHT . '; color:' . EX290_PURPLE_DARK . '; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; padding:10px 14px; text-align:left; white-space:nowrap; }
    .ex290-lab-table td { padding:10px 14px; border-bottom:1px solid #EDE7F6; color:' . EX290_DARK . '; line-height:1.5; vertical-align:top; }
    .ex290-lab-table tr:last-child td { border-bottom:none; }
    .ex290-action-badge { font-size:11px; font-weight:700; border:1.5px solid; border-radius:4px; padding:2px 8px; white-space:nowrap; }
    .ex290-signals-section { padding:24px 28px; background:' . EX290_PURPLE_LIGHT . '; }
    .ex290-signals-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:10px; }
    .ex290-signal-item { font-size:13px; color:' . EX290_DARK . '; display:flex; align-items:baseline; gap:8px; line-height:1.5; }
    .ex290-signal-check { color:' . EX290_PURPLE . '; font-weight:700; flex-shrink:0; }
    .ex290-interp-box { background:#f0f4ff; border-radius:10px; padding:20px; }
    .ex290-rec-box { background:' . EX290_GOLD_LIGHT . '; border-radius:10px; padding:20px; margin-bottom:12px; }
    .ex290-principle-box { background:' . EX290_PURPLE_DARK . '; border-radius:10px; padding:20px; }
    .ex290-report-footer { background:' . EX290_DARK . '; padding:20px 28px; }
    </style>';
}

/* ════════════════════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════════════════════ */

function excreet_290_card_style(): string {
    return 'border:1px solid #D5C5E8;border-radius:16px;padding:32px;background:#fff;';
}

function excreet_290_btn_style( string $variant = 'primary' ): string {
    if ( $variant === 'secondary' ) {
        return 'display:inline-block;padding:10px 20px;border:1.5px solid ' . EX290_PURPLE . ';color:' . EX290_PURPLE . ';border-radius:8px;text-decoration:none;font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;';
    }
    return 'display:inline-block;padding:12px 28px;background:linear-gradient(135deg,' . EX290_PURPLE . ',' . EX290_PURPLE_DARK . ');color:#fff;border-radius:10px;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;';
}

function excreet_290_locked_state( string $msg ): string {
    return '<div style="' . excreet_290_card_style() . 'text-align:center;padding:48px 24px;max-width:760px;">'
        . '<div style="font-size:40px;margin-bottom:16px;">🔒</div>'
        . '<h3 style="color:' . EX290_PURPLE . ';margin:0 0 10px;">Members Only</h3>'
        . '<p style="color:' . EX290_GRAY . ';margin:0 0 20px;font-size:14px;">' . esc_html( $msg ) . '</p>'
        . '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" style="' . excreet_290_btn_style() . '">Log In</a>'
        . '</div>';
}
