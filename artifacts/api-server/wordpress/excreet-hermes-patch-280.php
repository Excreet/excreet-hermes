<?php
/**
 * Excreet Hermes Patch 2.8.0
 *
 * Overrides shortcode rendering and result storage to use the new Hermes v2
 * schema (tier / vitalityScore / trajectoryRead / quickActions / medicalPath /
 * ministryPath / disclaimer) without requiring OPcache clearing on the main
 * plugin.
 *
 * Load order (alphabetical mu-plugin order):
 *   excreet-hermes-client.php      ← main plugin (OPcache v2.7.0 frozen)
 *   excreet-hermes-patch-272.php   ← job-id / token storage patch
 *   excreet-hermes-patch-280.php   ← THIS FILE — schema rendering + storage
 *
 * Technique: remove_shortcode() then add_shortcode() to replace the main
 * plugin's callbacks without PHP function redefinition.  New REST endpoint
 * `POST /wp-json/excreet/v1/store-result-v2` lets the processing-page JS
 * persist the result to user_meta (bypassing the old storage function).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ──────────────────────────────────────────────────────────────────
   SHORTCODE OVERRIDES
   ────────────────────────────────────────────────────────────────── */

add_action( 'init', 'excreet_280_override_shortcodes', 20 );

function excreet_280_override_shortcodes(): void {
    remove_shortcode( 'excreet_hermes_processing_result' );
    remove_shortcode( 'excreet_hermes_latest_result' );

    add_shortcode( 'excreet_hermes_processing_result', 'excreet_280_shortcode_processing' );
    add_shortcode( 'excreet_hermes_latest_result',     'excreet_280_shortcode_latest'     );
}

/* ──────────────────────────────────────────────────────────────────
   PROCESSING PAGE SHORTCODE
   ────────────────────────────────────────────────────────────────── */

function excreet_280_shortcode_processing(): string {

    // Delegate job-id resolution to the existing function (no schema touch).
    if ( function_exists( 'excreet_read_processing_job_id' ) ) {
        $job_id = excreet_read_processing_job_id();
    } else {
        $job_id = '';
    }

    if ( $job_id === '' ) {
        return excreet_280_render_processing_shell( '', 'invalid_job' );
    }

    return excreet_280_render_processing_shell( $job_id, 'pending' );
}

function excreet_280_render_processing_shell( string $job_id, string $initial_state ): string {

    $is_pending_token = strpos( $job_id, 'PENDING_TOKEN:' ) === 0;
    $pending_token    = $is_pending_token ? substr( $job_id, 14 ) : '';
    if ( $is_pending_token ) {
        $job_id = '';
    }

    // Hermes public base — uses EXCREET_HERMES_BASE_URL constant if set.
    $hermes_base = defined( 'EXCREET_HERMES_BASE_URL' ) ? rtrim( EXCREET_HERMES_BASE_URL, '/' ) : '';
    $endpoint_base = $hermes_base . '/api/hermes/result/';

    $prestore_endpoint   = rest_url( 'excreet/v1/prestore-token' );
    $store_result_v2     = rest_url( 'excreet/v1/store-result-v2' );
    $resolve_endpoint    = rest_url( 'excreet/v1/resolve-token' );
    $state               = sanitize_key( $initial_state );
    $nonce               = wp_create_nonce( 'wp_rest' );
    $ajax_url            = admin_url( 'admin-ajax.php' );
    $mark_baseline_nonce = wp_create_nonce( 'excreet_moh_chat' );

    ob_start();
    ?>
    <div id="excreet-hermes-processing-card" style="border:1px solid #d9e2ec;border-radius:12px;padding:18px;background:#ffffff;max-width:760px;">
        <h3 style="margin:0 0 8px;font-size:20px;line-height:1.3;color:#102a43;">Your Hermes Intake Result</h3>
        <p id="excreet-hermes-processing-message" style="margin:0;color:#486581;font-size:15px;line-height:1.6;">
            <?php echo esc_html( $state === 'invalid_job'
                ? 'We could not find your latest intake session. Please submit the intake form again.'
                : 'Processing in progress. This page updates automatically.' ); ?>
        </p>
        <div id="excreet-hermes-processing-result" style="margin-top:16px;"></div>
    </div>
    <?php if ( $state !== 'invalid_job' ) : ?>
    <script>
    (function () {
        var jobId              = <?php echo wp_json_encode( $job_id ); ?>;
        var pendingToken       = <?php echo wp_json_encode( $pending_token ); ?>;
        var endpointBase       = <?php echo wp_json_encode( $endpoint_base ); ?>;
        var prestoreEndpoint   = <?php echo wp_json_encode( $prestore_endpoint ); ?>;
        var storeResultV2      = <?php echo wp_json_encode( $store_result_v2 ); ?>;
        var resolveEndpoint    = <?php echo wp_json_encode( $resolve_endpoint ); ?>;
        var nonce              = <?php echo wp_json_encode( $nonce ); ?>;
        var ajaxUrl            = <?php echo wp_json_encode( $ajax_url ); ?>;
        var markBaselineNonce  = <?php echo wp_json_encode( $mark_baseline_nonce ); ?>;
        var messageEl          = document.getElementById('excreet-hermes-processing-message');
        var resultEl           = document.getElementById('excreet-hermes-processing-result');
        var timeoutAt          = Date.now() + (5 * 60 * 1000);
        var intervalMs         = 3000;
        var tokenResolveAttempts    = 0;
        var maxTokenResolveAttempts = 20;

        /* ── Helpers ── */

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function(c) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
            });
        }

        function safeList(items) {
            if (!Array.isArray(items) || items.length === 0) {
                return '<p style="margin:0;color:#627d98;line-height:1.6;">No items provided.</p>';
            }
            return '<ul style="margin:0;padding-left:18px;color:#334e68;line-height:1.6;">' +
                items.map(function(item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') +
                '</ul>';
        }

        function tierLabel(t) {
            return {nudge:'Quick Nudge',checkin:'Check-In',protocol:'Protocol Recommended',alarm:'Attention Needed'}[t] || t;
        }
        function tierColor(t) {
            return {nudge:'#137333',checkin:'#b45309',protocol:'#7c3aed',alarm:'#b91c1c'}[t] || '#243b53';
        }
        function tierBg(t) {
            return {nudge:'#e3fcec',checkin:'#fef3c7',protocol:'#ede9fe',alarm:'#fee2e2'}[t] || '#f0f4f8';
        }
        function scoreColor(s) {
            return s >= 70 ? '#137333' : s >= 45 ? '#b45309' : '#b91c1c';
        }

        /* ── Render completed result (v2 schema) ── */

        function renderCompleted(result) {
            var tier  = (result && result.tier)  ? result.tier  : 'nudge';
            var score = (result && typeof result.vitalityScore === 'number') ? result.vitalityScore : 0;
            var tread = (result && result.trajectoryRead) ? escapeHtml(result.trajectoryRead) : '';
            var quick = (result && Array.isArray(result.quickActions)) ? result.quickActions : [];
            var med   = (result && result.medicalPath  && typeof result.medicalPath  === 'object') ? result.medicalPath  : null;
            var min   = (result && result.ministryPath && typeof result.ministryPath === 'object') ? result.ministryPath : null;
            var disc  = (result && result.disclaimer)  ? escapeHtml(result.disclaimer) : '';

            var html = '';

            /* Header: tier badge + vitality score */
            html += '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">';
            html += '<span style="display:inline-block;padding:5px 12px;border-radius:999px;background:' + tierBg(tier) + ';color:' + tierColor(tier) + ';font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">' + escapeHtml(tierLabel(tier)) + '</span>';
            html += '<div style="text-align:right;"><span style="font-size:13px;color:#627d98;">Vitality Score</span><br><span style="font-size:28px;font-weight:800;color:' + scoreColor(score) + ';">' + score + '</span><span style="font-size:13px;color:#627d98;">&nbsp;/ 100</span></div>';
            html += '</div>';

            /* What your body is signaling */
            if (tread) {
                html += '<div style="margin-bottom:16px;padding:14px;background:#f0f7ff;border-radius:8px;">';
                html += '<h4 style="margin:0 0 6px;font-size:14px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">What Your Body Is Signaling</h4>';
                html += '<p style="margin:0;color:#334e68;line-height:1.7;">' + tread + '</p>';
                html += '</div>';
            }

            /* Immediate actions */
            if (quick.length > 0) {
                html += '<div style="margin-bottom:16px;">';
                html += '<h4 style="margin:0 0 8px;font-size:14px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Immediate Actions</h4>';
                html += safeList(quick);
                html += '</div>';
            }

            /* Ministry of Healing path */
            if (min) {
                html += '<div style="margin-bottom:16px;padding:14px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;">';
                html += '<h4 style="margin:0 0 8px;font-size:14px;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:.06em;">Ministry of Healing Path</h4>';
                if (min.signalCategory) {
                    html += '<p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#7c3aed;">Signal Category: ' + escapeHtml(min.signalCategory) + '</p>';
                }
                if (Array.isArray(min.approach) && min.approach.length > 0) {
                    min.approach.forEach(function(line) {
                        html += '<p style="margin:0 0 6px;color:#4c1d95;line-height:1.65;">' + escapeHtml(line) + '</p>';
                    });
                }
                if (Array.isArray(min.powerMoves) && min.powerMoves.length > 0) {
                    html += '<h5 style="margin:10px 0 6px;font-size:13px;font-weight:700;color:#5b21b6;text-transform:uppercase;letter-spacing:.05em;">Your Power Moves</h5>';
                    html += safeList(min.powerMoves);
                }
                html += '</div>';
            }

            /* Medical navigation path */
            if (med) {
                html += '<div style="margin-bottom:16px;padding:14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;">';
                html += '<h4 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;">Navigating the Medical System</h4>';
                if (Array.isArray(med.questionsToAsk) && med.questionsToAsk.length > 0) {
                    html += '<h5 style="margin:0 0 6px;font-size:13px;font-weight:700;color:#78350f;">Questions to Bring</h5>';
                    html += safeList(med.questionsToAsk);
                }
                if (Array.isArray(med.labTestsToRequest) && med.labTestsToRequest.length > 0) {
                    html += '<h5 style="margin:10px 0 6px;font-size:13px;font-weight:700;color:#78350f;">Lab Tests to Request by Name</h5>';
                    html += safeList(med.labTestsToRequest);
                }
                if (Array.isArray(med.redFlagsToWatch) && med.redFlagsToWatch.length > 0) {
                    html += '<h5 style="margin:10px 0 6px;font-size:13px;font-weight:700;color:#b91c1c;">Red Flags — Seek Urgent Care If You Notice</h5>';
                    html += safeList(med.redFlagsToWatch);
                }
                html += '</div>';
            }

            /* Disclaimer */
            if (disc) {
                html += '<p style="margin:12px 0 0;font-size:12px;color:#829ab1;line-height:1.6;border-top:1px solid #e6edf3;padding-top:12px;">' + disc + '</p>';
            }

            resultEl.innerHTML = html;
            messageEl.textContent = 'Your Excreet Intelligence is ready.';

            /* Persist result to WordPress user_meta via new v2 endpoint */
            if (jobId) {
                fetch(storeResultV2, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ jobId: jobId, result: result })
                }).catch(function() { /* best-effort — ignore errors */ });
            }

            /* If this was a re-baseline submission, inject a marker into Ministry history */
            try {
                if (sessionStorage.getItem('excreet_rebaseline') === '1') {
                    sessionStorage.removeItem('excreet_rebaseline');
                    var fd = new FormData();
                    fd.append('action', 'excreet_moh_mark_rebaseline');
                    fd.append('nonce',  markBaselineNonce);
                    fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                        .catch(function() { /* silent — non-critical */ });
                }
            } catch(e) { /* sessionStorage unavailable — skip */ }
        }

        function setMessage(text) {
            messageEl.textContent = text;
        }

        /* ── Token resolution polling ── */

        function pollForTokenResolution() {
            if (tokenResolveAttempts >= maxTokenResolveAttempts) {
                setMessage('Processing is taking longer than expected. Please refresh this page in a few minutes.');
                return;
            }
            tokenResolveAttempts++;
            setMessage('Connecting to your intake session... (' + tokenResolveAttempts + ')');

            var url = resolveEndpoint + '?token=' + encodeURIComponent(pendingToken) + '&_wpnonce=' + encodeURIComponent(nonce);
            fetch(url, { method: 'GET' })
                .then(function(r) { return r.json().catch(function() { return {}; }); })
                .then(function(data) {
                    if (data && data.resolved && data.jobId) {
                        jobId = data.jobId;
                        setMessage('Your intake is being processed.');
                        poll();
                    } else {
                        setTimeout(pollForTokenResolution, intervalMs);
                    }
                })
                .catch(function() { setTimeout(pollForTokenResolution, intervalMs); });
        }

        /* ── Hermes result polling ── */

        function poll() {
            if (Date.now() >= timeoutAt) {
                setMessage('Processing is taking longer than expected. Please refresh this page in a few minutes.');
                return;
            }

            fetch(endpointBase + encodeURIComponent(jobId), { method: 'GET' })
                .then(function(r) { return r.json().catch(function() { return {}; }); })
                .then(function(data) {
                    var status = (data && data.status) ? String(data.status) : '';

                    if (status === 'pending') {
                        setMessage('Your intake is queued and will begin shortly.');
                        setTimeout(poll, intervalMs);
                        return;
                    }
                    if (status === 'processing') {
                        setMessage('Your intake is being processed.');
                        setTimeout(poll, intervalMs);
                        return;
                    }
                    if (status === 'completed') {
                        renderCompleted(data.result || {});
                        return;
                    }
                    if (status === 'failed') {
                        setMessage('Processing could not be completed. Please submit a new intake.');
                        return;
                    }
                    if (status === 'not_found') {
                        setMessage('We could not find this intake job. Please submit a new intake.');
                        return;
                    }
                    setMessage('Unexpected response while checking status. Please refresh this page shortly.');
                })
                .catch(function() {
                    setMessage('Unable to reach the server right now. Retrying...');
                    setTimeout(poll, intervalMs);
                });
        }

        /* ── Boot ── */
        if (pendingToken && !jobId) {
            pollForTokenResolution();
        } else if (jobId) {
            poll();
        }
    })();
    </script>
    <?php endif; ?>
    <?php
    return (string) ob_get_clean();
}

/* ──────────────────────────────────────────────────────────────────
   LATEST RESULT SHORTCODE
   ────────────────────────────────────────────────────────────────── */

function excreet_280_shortcode_latest(): string {
    $record = excreet_280_get_latest_result_record();

    if ( empty( $record ) ) {
        return excreet_280_empty_state();
    }

    return excreet_280_render_card( $record );
}

function excreet_280_get_latest_result_record(): array {
    $user_id = get_current_user_id();

    if ( $user_id > 0 ) {
        $raw = get_user_meta( $user_id, 'excreet_hermes_completed_result', true );
        if ( is_string( $raw ) && $raw !== '' ) {
            $data = json_decode( $raw, true );
        } elseif ( is_array( $raw ) ) {
            $data = $raw;
        } else {
            $data = null;
        }

        if ( is_array( $data ) && ! empty( $data ) ) {
            return excreet_280_sanitize_record( $data );
        }
    }

    /* Fallback: storage_key from URL (anonymous / option-based storage) */
    $storage_key = sanitize_key( (string) filter_input( INPUT_GET, 'storage_key', FILTER_SANITIZE_SPECIAL_CHARS ) );
    if ( $storage_key === '' ) {
        return [];
    }

    $option = get_option( $storage_key, null );
    if ( ! is_array( $option ) ) {
        return [];
    }

    $cr = isset( $option['completed_result'] ) && is_array( $option['completed_result'] )
        ? $option['completed_result'] : [];

    if ( empty( $cr ) ) {
        return [];
    }

    $hermes_status = isset( $option['hermes_status'] ) ? sanitize_key( (string) $option['hermes_status'] ) : '';
    if ( $hermes_status !== 'completed' ) {
        return [];
    }

    $cr['completed_at'] = isset( $option['completed_at'] ) ? sanitize_text_field( (string) $option['completed_at'] ) : '';
    return excreet_280_sanitize_record( $cr );
}

function excreet_280_sanitize_record( array $d ): array {
    return [
        'tier'           => isset( $d['tier'] )          ? sanitize_key( (string) $d['tier'] ) : 'nudge',
        'vitalityScore'  => isset( $d['vitalityScore'] ) ? (int) $d['vitalityScore']           : 0,
        'trajectoryRead' => isset( $d['trajectoryRead'] ) ? sanitize_textarea_field( (string) $d['trajectoryRead'] ) : '',
        'quickActions'   => excreet_280_string_list( isset( $d['quickActions'] ) ? $d['quickActions'] : [] ),
        'medicalPath'    => ( isset( $d['medicalPath'] )  && is_array( $d['medicalPath'] )  ) ? $d['medicalPath']  : null,
        'ministryPath'   => ( isset( $d['ministryPath'] ) && is_array( $d['ministryPath'] ) ) ? $d['ministryPath'] : null,
        'disclaimer'     => isset( $d['disclaimer'] )    ? sanitize_textarea_field( (string) $d['disclaimer'] )    : '',
        'completed_at'   => isset( $d['completed_at'] )  ? sanitize_text_field( (string) $d['completed_at'] )      : '',
        'status'         => 'completed',
    ];
}

function excreet_280_string_list( $items ): array {
    if ( ! is_array( $items ) ) {
        return is_string( $items ) ? array_filter( array_map( 'trim', explode( "\n", $items ) ) ) : [];
    }
    return array_values( array_filter( array_map( 'sanitize_text_field', $items ) ) );
}

function excreet_280_empty_state(): string {
    ob_start();
    ?>
    <div class="excreet-hermes-card" style="border:1px solid #e6edf3;border-radius:12px;padding:16px;background:#fff;max-width:760px;">
        <h3 style="margin:0 0 8px;font-size:20px;line-height:1.3;color:#102a43;">Your Hermes Intake Result</h3>
        <p style="margin:0;color:#486581;font-size:15px;line-height:1.6;">
            No completed result is available yet. Please check back after processing finishes.
        </p>
    </div>
    <?php
    return (string) ob_get_clean();
}

function excreet_280_render_card( array $r ): string {

    $tier           = sanitize_key( (string) ( $r['tier'] ?? 'nudge' ) );
    $vitality_score = (int) ( $r['vitalityScore'] ?? 0 );
    $trajectory     = (string) ( $r['trajectoryRead'] ?? '' );
    $quick_actions  = is_array( $r['quickActions'] ?? null ) ? $r['quickActions'] : [];
    $medical_path   = is_array( $r['medicalPath']  ?? null ) ? $r['medicalPath']  : null;
    $ministry_path  = is_array( $r['ministryPath'] ?? null ) ? $r['ministryPath'] : null;
    $disclaimer     = (string) ( $r['disclaimer'] ?? '' );
    $completed_at   = sanitize_text_field( (string) ( $r['completed_at'] ?? '' ) );

    $ts    = $completed_at !== '' ? strtotime( $completed_at ) : false;
    $label = $ts ? gmdate( 'F j, Y g:i A T', $ts ) : 'Not available';

    $tier_labels = [ 'nudge' => 'Quick Nudge', 'checkin' => 'Check-In', 'protocol' => 'Protocol Recommended', 'alarm' => 'Attention Needed' ];
    $tier_colors = [ 'nudge' => '#137333', 'checkin' => '#b45309', 'protocol' => '#7c3aed', 'alarm' => '#b91c1c' ];
    $tier_bgs    = [ 'nudge' => '#e3fcec', 'checkin' => '#fef3c7', 'protocol' => '#ede9fe', 'alarm' => '#fee2e2' ];

    $tier_label = $tier_labels[ $tier ] ?? $tier;
    $tier_color = $tier_colors[ $tier ] ?? '#243b53';
    $tier_bg    = $tier_bgs[ $tier ]    ?? '#f0f4f8';

    if ( $vitality_score >= 70 ) {
        $score_color = '#137333';
    } elseif ( $vitality_score >= 45 ) {
        $score_color = '#b45309';
    } else {
        $score_color = '#b91c1c';
    }

    ob_start();
    ?>
    <div class="excreet-hermes-card" style="border:1px solid #d9e2ec;border-radius:12px;padding:20px;background:#ffffff;max-width:760px;">

        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:6px;">
            <div>
                <h3 style="margin:0 0 6px;font-size:20px;line-height:1.3;color:#102a43;">Your Excreet Intelligence</h3>
                <span style="display:inline-block;padding:4px 12px;border-radius:999px;background:<?php echo esc_attr( $tier_bg ); ?>;color:<?php echo esc_attr( $tier_color ); ?>;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.05em;"><?php echo esc_html( $tier_label ); ?></span>
            </div>
            <div style="text-align:right;">
                <span style="font-size:13px;color:#627d98;">Vitality Score</span><br>
                <span style="font-size:32px;font-weight:800;color:<?php echo esc_attr( $score_color ); ?>;"><?php echo esc_html( (string) $vitality_score ); ?></span>
                <span style="font-size:14px;color:#627d98;"> / 100</span>
            </div>
        </div>

        <?php if ( $label !== 'Not available' ) : ?>
        <p style="margin:0 0 16px;font-size:12px;color:#829ab1;">Completed <?php echo esc_html( $label ); ?></p>
        <?php endif; ?>

        <?php if ( $trajectory !== '' ) : ?>
        <div style="margin-bottom:16px;padding:14px;background:#f0f7ff;border-radius:8px;">
            <h4 style="margin:0 0 6px;font-size:14px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">What Your Body Is Signaling</h4>
            <p style="margin:0;color:#334e68;line-height:1.7;"><?php echo esc_html( $trajectory ); ?></p>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $quick_actions ) ) : ?>
        <div style="margin-bottom:16px;">
            <h4 style="margin:0 0 8px;font-size:14px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Immediate Actions</h4>
            <ul style="margin:0;padding-left:18px;color:#334e68;line-height:1.6;">
                <?php foreach ( $quick_actions as $action ) : ?>
                <li><?php echo esc_html( $action ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ( $ministry_path !== null ) : ?>
        <div style="margin-bottom:16px;padding:14px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;">
            <h4 style="margin:0 0 8px;font-size:14px;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:.06em;">Ministry of Healing Path</h4>
            <?php if ( ! empty( $ministry_path['signalCategory'] ) ) : ?>
            <p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#7c3aed;">Signal Category: <?php echo esc_html( (string) $ministry_path['signalCategory'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $ministry_path['approach'] ) && is_array( $ministry_path['approach'] ) ) : ?>
                <?php foreach ( $ministry_path['approach'] as $line ) : ?>
                <p style="margin:0 0 6px;color:#4c1d95;line-height:1.65;"><?php echo esc_html( (string) $line ); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ( ! empty( $ministry_path['powerMoves'] ) && is_array( $ministry_path['powerMoves'] ) ) : ?>
            <h5 style="margin:10px 0 6px;font-size:13px;font-weight:700;color:#5b21b6;text-transform:uppercase;letter-spacing:.05em;">Your Power Moves</h5>
            <ul style="margin:0;padding-left:18px;color:#4c1d95;line-height:1.6;">
                <?php foreach ( $ministry_path['powerMoves'] as $move ) : ?>
                <li><?php echo esc_html( (string) $move ); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( $medical_path !== null ) : ?>
        <div style="margin-bottom:16px;padding:14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;">
            <h4 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;">Navigating the Medical System</h4>
            <?php if ( ! empty( $medical_path['questionsToAsk'] ) && is_array( $medical_path['questionsToAsk'] ) ) : ?>
            <h5 style="margin:0 0 6px;font-size:13px;font-weight:700;color:#78350f;">Questions to Bring</h5>
            <ul style="margin:0 0 10px;padding-left:18px;color:#78350f;line-height:1.6;">
                <?php foreach ( $medical_path['questionsToAsk'] as $q ) : ?>
                <li><?php echo esc_html( (string) $q ); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ( ! empty( $medical_path['labTestsToRequest'] ) && is_array( $medical_path['labTestsToRequest'] ) ) : ?>
            <h5 style="margin:0 0 6px;font-size:13px;font-weight:700;color:#78350f;">Lab Tests to Request by Name</h5>
            <ul style="margin:0 0 10px;padding-left:18px;color:#78350f;line-height:1.6;">
                <?php foreach ( $medical_path['labTestsToRequest'] as $t ) : ?>
                <li><?php echo esc_html( (string) $t ); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ( ! empty( $medical_path['redFlagsToWatch'] ) && is_array( $medical_path['redFlagsToWatch'] ) ) : ?>
            <h5 style="margin:0 0 6px;font-size:13px;font-weight:700;color:#b91c1c;">Red Flags — Seek Urgent Care If You Notice</h5>
            <ul style="margin:0;padding-left:18px;color:#b91c1c;line-height:1.6;">
                <?php foreach ( $medical_path['redFlagsToWatch'] as $flag ) : ?>
                <li><?php echo esc_html( (string) $flag ); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( $disclaimer !== '' ) : ?>
        <p style="margin:12px 0 0;font-size:12px;color:#829ab1;line-height:1.6;border-top:1px solid #e6edf3;padding-top:12px;"><?php echo esc_html( $disclaimer ); ?></p>
        <?php endif; ?>

    </div>
    <?php
    return (string) ob_get_clean();
}

/* ──────────────────────────────────────────────────────────────────
   NEW REST ENDPOINT: POST /wp-json/excreet/v1/store-result-v2
   Called by the processing-page JS after it receives a completed
   result from Hermes, so we can persist new-schema fields to
   user_meta without relying on the OPcache-frozen storage function.
   ────────────────────────────────────────────────────────────────── */

add_action( 'rest_api_init', 'excreet_280_register_routes' );

function excreet_280_register_routes(): void {
    register_rest_route(
        'excreet/v1',
        '/store-result-v2',
        [
            'methods'             => 'POST',
            'callback'            => 'excreet_280_handle_store_result',
            'permission_callback' => 'is_user_logged_in',
        ]
    );
}

function excreet_280_handle_store_result( WP_REST_Request $request ): WP_REST_Response {

    $user_id = get_current_user_id();
    if ( $user_id <= 0 ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'Not authenticated.' ], 401 );
    }

    $body   = $request->get_json_params();
    $job_id = isset( $body['jobId'] ) ? sanitize_text_field( (string) $body['jobId'] ) : '';
    $result = isset( $body['result'] ) && is_array( $body['result'] ) ? $body['result'] : [];

    if ( $job_id === '' || empty( $result ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'Missing jobId or result.' ], 400 );
    }

    $completed_result = [
        'tier'           => isset( $result['tier'] )          ? sanitize_key( (string) $result['tier'] )                    : 'nudge',
        'vitalityScore'  => isset( $result['vitalityScore'] ) ? (int) $result['vitalityScore']                               : 0,
        'trajectoryRead' => isset( $result['trajectoryRead'] ) ? sanitize_textarea_field( (string) $result['trajectoryRead'] ) : '',
        'quickActions'   => excreet_280_string_list( $result['quickActions'] ?? [] ),
        'medicalPath'    => ( isset( $result['medicalPath'] )  && is_array( $result['medicalPath'] )  ) ? $result['medicalPath']  : null,
        'ministryPath'   => ( isset( $result['ministryPath'] ) && is_array( $result['ministryPath'] ) ) ? $result['ministryPath'] : null,
        'disclaimer'     => isset( $result['disclaimer'] ) ? sanitize_textarea_field( (string) $result['disclaimer'] ) : '',
        'completed_at'   => gmdate( 'c' ),
    ];

    update_user_meta( $user_id, 'excreet_hermes_job_status',       'completed' );
    update_user_meta( $user_id, 'excreet_hermes_completed_result', wp_json_encode( $completed_result ) );
    update_user_meta( $user_id, 'excreet_hermes_completed_at',     $completed_result['completed_at'] );

    return rest_ensure_response( [
        'success'  => true,
        'stored'   => true,
        'user_id'  => $user_id,
        'tier'     => $completed_result['tier'],
        'score'    => $completed_result['vitalityScore'],
    ] );
}
