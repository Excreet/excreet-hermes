<?php

    /**
     * Plugin Name: Excreet Hermes Patch 2.7.2
     * Description: Fixes form "An error occurred" — disables email PDF attachment,
     *              redirects to /intake-processing/ on success, restores token flow.
     *              v2.7.2-b: stores excreet_latest_job_id / excreet_hermes_job_id in
     *              user_meta on every successful webhook so the processing page always
     *              finds the jobId regardless of token or transient expiry.
     * Version:     2.7.2-b
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    // ============================================================================
    // HELPERS
    // ============================================================================

    function excreet_patch_log( string $message ): void {
        if ( function_exists( 'excreet_log' ) ) {
            excreet_log( $message );
        } else {
            $line = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] [Excreet Patch v2.7.2] ' . $message . PHP_EOL;
            error_log( $line, 3, WP_CONTENT_DIR . '/debug.log' );
        }
    }

    function excreet_patch_is_uuid( string $v ): bool {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $v
        );
    }

    // ============================================================================
    // FIX 1 — Suppress email PDF attachment for form 6
    // ============================================================================

    add_action( 'forminator_custom_form_before_save_entry', 'excreet_patch_before_save_entry', 1, 1 );

    function excreet_patch_before_save_entry( $module_id ): void {
        if ( (int) $module_id !== 6 ) {
            return;
        }
        excreet_patch_log( 'before_save_entry | user_id: ' . get_current_user_id() );

        register_shutdown_function( function () {
            $error = error_get_last();
            if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true ) ) {
                excreet_patch_log( 'PHP FATAL DETECTED | ' . json_encode( $error ) );
            }
        } );

        add_action( 'phpmailer_init', 'excreet_patch_clear_mail_attachments', 1 );
    }

    function excreet_patch_clear_mail_attachments( $phpmailer ): void {
        if ( isset( $phpmailer->attachment ) ) {
            $phpmailer->clearAttachments();
        }
    }

    // ============================================================================
    // CAPTURE entry_id while user context IS available
    // ============================================================================

    $excreet_patch_entry_id = 0;

    add_action( 'forminator_custom_form_submit_before_set_fields', 'excreet_patch_capture_entry_id', 1, 3 );

    function excreet_patch_capture_entry_id( $entry, $form_id, $field_data_array ): void {
        global $excreet_patch_entry_id;
        if ( (int) $form_id !== 6 ) {
            return;
        }
        $excreet_patch_entry_id = isset( $entry->entry_id ) ? absint( $entry->entry_id ) : 0;
        excreet_patch_log( 'submit_before_set_fields | entry_id: ' . $excreet_patch_entry_id );
    }

    // ============================================================================
    // FIX 2 — forminator_custom_form_ajax_submit_response at priority 999.
    //          Always returns a valid same-tab redirect to /intake-processing/
    // ============================================================================

    add_filter( 'forminator_custom_form_ajax_submit_response', 'excreet_patch_fix_response', 999, 2 );

    function excreet_patch_fix_response( $response, $module_id ) {
        if ( (int) $module_id !== 6 ) {
            return $response;
        }

        $type    = gettype( $response );
        $success = is_array( $response ) ? var_export( $response['success'] ?? 'MISSING', true ) : 'N/A';
        $keys    = is_array( $response ) ? implode( ',', array_keys( $response ) ) : '';
        excreet_patch_log( 'ajax_submit_response | type: ' . $type . ' | success: ' . $success . ' | keys: [' . $keys . ']' );

        $redirect_url = home_url( '/intake-processing/' );

        $response = array(
            'success' => true,
            'type'    => 'success',
            'form_id' => 6,
            'message' => '',
            'behav'   => 'behaviour-redirect',
            'url'     => $redirect_url,
            'newtab'  => 'sametab',
        );

        excreet_patch_log( 'ajax_submit_response | sending redirect to ' . $redirect_url );
        return $response;
    }

    // ============================================================================
    // STEP A — Store entry_id→token transient while user context is still live.
    //          Also writes the three user_meta keys the processing page relies on
    //          IF a valid jobId is already in the pre-store transient (fast path).
    //          TTL bumped to 2 hours so users who take time filling the form still work.
    // ============================================================================

    add_action( 'forminator_custom_form_after_save_entry', 'excreet_patch_store_entry_token', 5, 2 );

    function excreet_patch_store_entry_token( $module_id, $response ): void {
        global $excreet_patch_entry_id;

        $form_id = defined( 'EXCREET_FORM_ID' ) ? (int) EXCREET_FORM_ID : 6;
        if ( (int) $module_id !== $form_id ) {
            return;
        }

        $entry_id = $excreet_patch_entry_id > 0
            ? $excreet_patch_entry_id
            : ( isset( $response['entry_id'] ) ? absint( $response['entry_id'] ) : 0 );

        $user_id = get_current_user_id();
        excreet_patch_log( 'after_save_entry | user_id: ' . $user_id . ' | entry_id: ' . $entry_id );

        if ( $user_id <= 0 || $entry_id <= 0 ) {
            return;
        }

        $token      = sanitize_text_field( (string) get_user_meta( $user_id, 'excreet_pending_token', true ) );
        $token_time = (int) get_user_meta( $user_id, 'excreet_pending_token_time', true );

        if ( ! preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
            excreet_patch_log( 'after_save_entry | no valid pending token for user_id: ' . $user_id );
            return;
        }

        // 2-hour window instead of 30 min so longer form-fill sessions work.
        if ( $token_time <= 0 || $token_time < ( time() - 7200 ) ) {
            excreet_patch_log( 'after_save_entry | token expired (>2h) for user_id: ' . $user_id );
            return;
        }

        // Link entry_id → token so Step B can back-resolve after the REST webhook.
        set_transient( 'excreet_entry_token_' . $entry_id, $token, 7200 );
        excreet_patch_log( 'after_save_entry | stored entry→token | entry_id: ' . $entry_id . ' | token[:8]: ' . substr( $token, 0, 8 ) );

        // Fast path: if the pre-store transient already holds a real jobId (e.g. the
        // webhook fired first), populate user_meta right now so resolve-token works.
        $existing = get_transient( 'excreet_token_' . $token );
        if ( is_scalar( $existing ) ) {
            $existing = sanitize_text_field( (string) $existing );
            if ( excreet_patch_is_uuid( $existing ) ) {
                excreet_patch_store_job_in_user_meta( $user_id, $existing );
                excreet_patch_log( 'after_save_entry | fast-path user_meta stored | jobId: ' . $existing );
            }
        }
    }

    // ============================================================================
    // FIX 3 — wp_footer: JS redirect fallback on the intake form page
    // ============================================================================

    add_action( 'wp_footer', 'excreet_patch_footer_redirect_js', 99 );

    function excreet_patch_footer_redirect_js(): void {
        $slug = defined( 'EXCREET_INTAKE_FORM_SLUG' ) ? EXCREET_INTAKE_FORM_SLUG : 'member-intake-form';
        if ( ! is_page( $slug ) ) {
            return;
        }
        $redirect_url = esc_url( home_url( '/intake-processing/' ) );
        ?>
        <script>
        (function () {
            var redirectUrl = <?php echo wp_json_encode( $redirect_url ); ?>;
            var formSelector = '#forminator-module-6, .forminator-custom-form';

            function doRedirect() {
                if (redirectUrl && window.location.href.indexOf('intake-processing') === -1) {
                    window.location.href = redirectUrl;
                }
            }

            if (window.jQuery && typeof window.jQuery === 'function') {
                window.jQuery(document)
                    .on('forminator:form:submit:success', formSelector, function () {
                        console.log('[Excreet 272] form:submit:success → redirecting');
                        setTimeout(doRedirect, 300);
                    })
                    .on('forminator:form:submit:failed', formSelector, function (e, formData, errorData) {
                        console.log('[Excreet 272] form:submit:failed | errorData:', errorData, '→ redirecting in 1.5s');
                        setTimeout(doRedirect, 1500);
                    });
            }
        })();
        </script>
        <?php
    }

    // ============================================================================
    // STEP B — rest_post_dispatch: fires in REST context after our webhook handler.
    //
    //  PRIMARY FIX (v2.7.2-b):
    //    Look up the submitting user by email from the webhook body and store the
    //    jobId in ALL three user_meta keys the processing page checks:
    //      - excreet_latest_job_id   (excreet_read_latest_processing_job_from_user_meta)
    //      - excreet_latest_job_time (same function, validates freshness)
    //      - excreet_hermes_job_id   (excreet_handle_resolve_token Path 2)
    //
    //  This makes the processing page work regardless of token / transient expiry.
    // ============================================================================

    add_filter( 'rest_post_dispatch', 'excreet_patch_after_webhook', 10, 3 );

    function excreet_patch_after_webhook( $result, $server, $request ) {
        if (
            $request->get_method() !== 'POST' ||
            $request->get_route()  !== '/excreet/v1/intake'
        ) {
            return $result;
        }

        $body = $request->get_json_params();
        if ( empty( $body ) ) {
            $body = $request->get_body_params();
        }
        if ( ! is_array( $body ) ) {
            return $result;
        }

        // Pull jobId from the Hermes response.
        $data   = is_a( $result, 'WP_REST_Response' ) ? $result->get_data() : [];
        $job_id = isset( $data['jobId'] ) ? sanitize_text_field( (string) $data['jobId'] ) : '';

        if ( ! excreet_patch_is_uuid( $job_id ) ) {
            excreet_patch_log( 'rest_post_dispatch | missing/invalid jobId — skipping user_meta store' );
            return $result;
        }

        // ── Resolve the WP user who submitted this form entry ──────────────────
        // The webhook body always contains hidden_2 (the user's email, added by the
        // Forminator hidden field) and email_1 (Forminator email field).
        $email = '';
        foreach ( [ 'hidden_2', 'email_1', 'email-1' ] as $key ) {
            if ( ! empty( $body[ $key ] ) && is_string( $body[ $key ] ) ) {
                $candidate = sanitize_email( (string) $body[ $key ] );
                if ( $candidate ) {
                    $email = $candidate;
                    break;
                }
            }
        }

        $user_id = 0;
        if ( $email ) {
            $user    = get_user_by( 'email', $email );
            $user_id = $user ? (int) $user->ID : 0;
        }

        if ( $user_id > 0 ) {
            excreet_patch_store_job_in_user_meta( $user_id, $job_id );
            excreet_patch_log( 'rest_post_dispatch | user_meta stored | user_id: ' . $user_id . ' | email: ' . $email . ' | jobId: ' . $job_id );
        } else {
            excreet_patch_log( 'rest_post_dispatch | could not resolve user from email: ' . $email );
        }

        // ── Update pre-store transient if we have the entry→token link ──────────
        $entry_id = isset( $body['hidden_1'] ) ? absint( $body['hidden_1'] ) : 0;
        if ( $entry_id > 0 ) {
            $token = get_transient( 'excreet_entry_token_' . $entry_id );
            if ( is_string( $token ) && preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
                set_transient( 'excreet_token_' . $token, $job_id, 7200 );
                excreet_patch_log( 'rest_post_dispatch | TRANSIENT UPDATED | token[:8]: ' . substr( $token, 0, 8 ) . ' | jobId: ' . $job_id );
            } else {
                excreet_patch_log( 'rest_post_dispatch | no entry→token transient for entry_id: ' . $entry_id );
            }
        }

        return $result;
    }

    /**
     * Write all three user_meta keys the main plugin uses to find a jobId.
     *
     *  excreet_hermes_job_id   → excreet_handle_resolve_token() Path 2
     *  excreet_latest_job_id   → excreet_read_latest_processing_job_from_user_meta()
     *  excreet_latest_job_time → same function (freshness gate: within DAY_IN_SECONDS)
     */
    function excreet_patch_store_job_in_user_meta( int $user_id, string $job_id ): void {
        update_user_meta( $user_id, 'excreet_hermes_job_id',   $job_id );
        update_user_meta( $user_id, 'excreet_latest_job_id',   $job_id );
        update_user_meta( $user_id, 'excreet_latest_job_time', time() );
    }

    // ============================================================================
    // REST ENDPOINTS (guarded — only register if main plugin didn't already)
    // ============================================================================

    add_action( 'rest_api_init', 'excreet_patch_register_routes' );

    function excreet_patch_register_routes(): void {
        if ( ! function_exists( 'excreet_handle_resolve_token' ) ) {
            register_rest_route(
                'excreet/v1',
                '/resolve-token',
                [
                    'methods'             => 'GET',
                    'callback'            => 'excreet_patch_handle_resolve_token',
                    'permission_callback' => '__return_true',
                ]
            );
        }
        if ( ! function_exists( 'excreet_handle_my_latest_job' ) ) {
            register_rest_route(
                'excreet/v1',
                '/my-latest-job',
                [
                    'methods'             => 'GET',
                    'callback'            => 'excreet_patch_handle_my_latest_job',
                    'permission_callback' => '__return_true',
                ]
            );
        }
    }

    function excreet_patch_handle_resolve_token( WP_REST_Request $request ): WP_REST_Response {
        $token = strtolower( sanitize_text_field( (string) $request->get_param( 'token' ) ) );

        if ( preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
            $raw = get_transient( 'excreet_token_' . $token );
            if ( is_scalar( $raw ) ) {
                $val = sanitize_text_field( (string) $raw );
                if ( excreet_patch_is_uuid( $val ) ) {
                    return rest_ensure_response( [ 'resolved' => true, 'jobId' => $val ] );
                }
            }
        }

        $user_id = get_current_user_id();
        if ( $user_id > 0 ) {
            $job_id = sanitize_text_field( (string) get_user_meta( $user_id, 'excreet_hermes_job_id', true ) );
            if ( excreet_patch_is_uuid( $job_id ) ) {
                excreet_patch_update_transient( $token, $job_id );
                return rest_ensure_response( [ 'resolved' => true, 'jobId' => $job_id ] );
            }

            $job_id = excreet_patch_find_latest_job_by_user( $user_id, $token );
            if ( excreet_patch_is_uuid( $job_id ) ) {
                return rest_ensure_response( [ 'resolved' => true, 'jobId' => $job_id ] );
            }
        }

        return rest_ensure_response( [ 'resolved' => false, 'jobId' => '' ] );
    }

    function excreet_patch_handle_my_latest_job( WP_REST_Request $request ): WP_REST_Response {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return new WP_REST_Response( [ 'success' => false, 'error' => 'Not authenticated.' ], 401 );
        }

        $job_id = sanitize_text_field( (string) get_user_meta( $user_id, 'excreet_hermes_job_id', true ) );
        if ( excreet_patch_is_uuid( $job_id ) ) {
            return rest_ensure_response( [ 'success' => true, 'jobId' => $job_id ] );
        }

        $job_id = excreet_patch_find_latest_job_by_user( $user_id, '' );
        if ( excreet_patch_is_uuid( $job_id ) ) {
            return rest_ensure_response( [ 'success' => true, 'jobId' => $job_id ] );
        }

        return rest_ensure_response( [ 'success' => false, 'jobId' => '' ] );
    }

    function excreet_patch_update_transient( string $token, string $job_id ): void {
        if ( preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
            set_transient( 'excreet_token_' . $token, $job_id, 7200 );
        }
    }

    function excreet_patch_find_latest_job_by_user( int $user_id, string $token ): string {
        global $wpdb;
        $form_id = defined( 'EXCREET_FORM_ID' ) ? (int) EXCREET_FORM_ID : 6;

        // Primary: entry_created_by meta (set only when "one per user" limit is on)
        $entry_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT e.entry_id
             FROM {$wpdb->prefix}frmt_form_entry e
             INNER JOIN {$wpdb->prefix}frmt_form_entry_meta m ON e.entry_id = m.entry_id
             WHERE e.form_id = %d AND m.meta_key = 'entry_created_by' AND m.meta_value = %s
             ORDER BY e.entry_id DESC LIMIT 1",
            $form_id,
            (string) $user_id
        ) );

        // Fallback: match by email stored in email-1 or hidden-2 field
        if ( ! $entry_id ) {
            $user  = get_userdata( $user_id );
            $email = $user ? $user->user_email : '';
            if ( $email ) {
                $entry_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT e.entry_id
                     FROM {$wpdb->prefix}frmt_form_entry e
                     INNER JOIN {$wpdb->prefix}frmt_form_entry_meta m ON e.entry_id = m.entry_id
                     WHERE e.form_id = %d AND m.meta_key IN ('email-1','hidden-2') AND m.meta_value = %s
                     ORDER BY e.entry_id DESC LIMIT 1",
                    $form_id,
                    $email
                ) );
            }
        }

        if ( ! $entry_id ) {
            return '';
        }

        $entry_id = absint( $entry_id );

        // Check entry-token transient (set by excreet_patch_store_entry_token)
        $entry_token = get_transient( 'excreet_entry_token_' . $entry_id );
        if ( is_string( $entry_token ) && preg_match( '/^[0-9a-f]{32}$/', $entry_token ) ) {
            $t_val = get_transient( 'excreet_token_' . $entry_token );
            if ( is_scalar( $t_val ) ) {
                $j = sanitize_text_field( (string) $t_val );
                if ( excreet_patch_is_uuid( $j ) ) {
                    excreet_patch_update_transient( $token, $j );
                    return $j;
                }
            }
        }

        // Check entry transient (set by main plugin's prepare_processing_job_recovery)
        $raw = get_transient( 'excreet_entry_' . $entry_id );
        if ( is_scalar( $raw ) ) {
            $j = sanitize_text_field( (string) $raw );
            if ( excreet_patch_is_uuid( $j ) ) {
                excreet_patch_update_transient( $token, $j );
                return $j;
            }
        }

        // Check option-based storage (set by main plugin's store_hermes_job_metadata)
        $option = get_option( 'excreet_hermes_entry_' . $entry_id, null );
        if ( is_array( $option ) && ! empty( $option['jobId'] ) ) {
            $j = sanitize_text_field( (string) $option['jobId'] );
            if ( excreet_patch_is_uuid( $j ) ) {
                excreet_patch_update_transient( $token, $j );
                return $j;
            }
        }

        return '';
    }
