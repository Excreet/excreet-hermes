<?php
    
    /**
     * Plugin Name: Excreet Hermes Client
     * Description: REST bridge between Forminator webhooks and the Hermes AI backend.
     * Version:     2.9.0
     *
     * CHANGELOG v2.9.0
     * - FEAT: Full Clinical Pattern Report rendering for pharmaceutical_intake results.
     *         Both PHP (static result card) and JS (live polling render) now detect the
     *         schema type and branch accordingly.
     * - FEAT: excreet_is_pharma_result() schema discriminator — presence of memberProfile
     *         or prescribedPharmaceuticals keys identifies a pharmaceutical_intake result.
     * - FEAT: excreet_sanitize_result_payload() and excreet_store_completed_job_result()
     *         now preserve all pharma fields: memberProfile, prescribedPharmaceuticals,
     *         redFlagSummary, drugInteractionLoops, labMarkerTriggers,
     *         expectedObservableSignals, excreetInterpretation, recommendationSummary,
     *         excreetPrinciple, disclaimer.
     * - FEAT: excreet_get_latest_completed_result_record() passes pharma fields through
     *         from both user_meta and option-store paths.
     * - INHERITS: All fixes from v2.7.1 / v2.8.0
     *
     * CHANGELOG v2.7.1
     * - FIX: Webhook now recovers handoff token by looking up WP user via email in raw
     *        form body (server-to-server webhooks have no user session, so the previous
     *        user-meta recovery always returned empty).
     * - FIX: New REST endpoint /wp-json/excreet/v1/resolve-token replaces the page-reload
     *        loop. JS polls this endpoint; PHP checks transient → user meta →
     *        Forminator DB entry → option store. No more infinite reload cycle.
     * - INHERITS: All fixes from v2.7.0
     */
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }
    
    // ============================================================================
    // CONFIGURATION
    // EXCREET_HERMES_API_KEY lives in wp-config.php.
    // ============================================================================
    
    if ( ! defined( 'EXCREET_FORM_ID' ) ) {
        define( 'EXCREET_FORM_ID', 6 );
    }

    if ( ! defined( 'EXCREET_HERMES_URL' ) ) {
        define( 'EXCREET_HERMES_URL', 'https://core-status-check.replit.app/api/hermes/intake' );
    }
    
    if ( ! defined( 'EXCREET_HERMES_RESULT_PAGE_PATH' ) ) {
        define( 'EXCREET_HERMES_RESULT_PAGE_PATH', '/hermes-result-test/' );
    }
    
    if ( ! defined( 'EXCREET_HERMES_PROCESSING_PAGE_PATH' ) ) {
        define( 'EXCREET_HERMES_PROCESSING_PAGE_PATH', '/intake-processing/' );
    }
    
    // Intake form page slug - token injector restricted to this page only
    if ( ! defined( 'EXCREET_INTAKE_FORM_SLUG' ) ) {
        define( 'EXCREET_INTAKE_FORM_SLUG', 'member-intake-form' );
    }
    
    // ============================================================================
    // REST ENDPOINTS
    // ============================================================================
    
    add_action( 'rest_api_init', 'excreet_register_routes' );
    
    function excreet_register_routes(): void {
    
        // Main intake endpoint
        register_rest_route(
            'excreet/v1',
            '/intake',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => 'excreet_rest_health_check',
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => 'excreet_handle_intake_request',
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    
        // Job status endpoint
        register_rest_route(
            'excreet/v1',
            '/job-status',
            [
                'methods'             => 'GET',
                'callback'            => 'excreet_handle_job_status_request',
                'permission_callback' => '__return_true',
            ]
        );
    
        // v2.7.1: Resolve token endpoint — JS polls this instead of reloading the page
        register_rest_route(
            'excreet/v1',
            '/resolve-token',
            [
                'methods'             => 'GET',
                'callback'            => 'excreet_handle_resolve_token',
                'permission_callback' => '__return_true',
            ]
        );

        // NEW v2.6.6: Pre-store token endpoint
        // Called by browser JS on page load BEFORE form submission
        register_rest_route(
            'excreet/v1',
            '/prestore-token',
            [
                'methods'             => 'POST',
                'callback'            => 'excreet_handle_prestore_token',
                'permission_callback' => '__return_true',
            ]
        );
    }
    
    function excreet_rest_health_check(): WP_REST_Response {
        return rest_ensure_response( [
            'status'  => 'ready',
            'message' => 'Excreet Hermes endpoint ready',
            'version' => '2.7.0',
        ] );
    }
    
    // ============================================================================
    // v2.7.0: PRE-STORE TOKEN HANDLER
    // Called by JS on page load. Stores token as transient immediately.
    // This means by the time the form submits, token is ALREADY safely stored.
    // ============================================================================
    
    function excreet_handle_prestore_token( WP_REST_Request $request ): WP_REST_Response {
    
        $body  = $request->get_json_params();
        $body  = is_array( $body ) ? $body : [];
        $token = isset( $body['token'] ) ? strtolower( sanitize_text_field( (string) $body['token'] ) ) : '';
    
        excreet_log( 'PRE-STORE TOKEN REQUEST | token_received: ' . ( $token !== '' ? 'yes' : 'no' ) );
    
        if ( ! preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
            excreet_log( 'PRE-STORE TOKEN REJECTED | invalid format | token: ' . $token );
            return new WP_REST_Response( [
                'success' => false,
                'error'   => 'Invalid token format.',
            ], 400 );
        }
    
        // Store token as a placeholder transient with 30-minute TTL
        // Value will be updated to real jobId when intake webhook fires
        $transient_key = 'excreet_token_' . $token;
        $existing      = get_transient( $transient_key );
    
        if ( $existing === false ) {
            // Token not yet stored - store placeholder
            set_transient( $transient_key, 'pending', 1800 );
            excreet_log( 'PRE-STORE TOKEN STORED | key: ' . $transient_key . ' | value: pending' );
        } else {
            excreet_log( 'PRE-STORE TOKEN EXISTS | key: ' . $transient_key . ' | existing: ' . (string) $existing );
        }
    
        // Also store in user meta if logged in
        $user_id = get_current_user_id();
        if ( $user_id > 0 ) {
            update_user_meta( $user_id, 'excreet_pending_token', $token );
            update_user_meta( $user_id, 'excreet_pending_token_time', time() );
            excreet_log( 'PRE-STORE TOKEN IN USER META | user_id: ' . $user_id . ' | token: ' . $token );
        }
    
        return rest_ensure_response( [
            'success' => true,
            'token'   => $token,
            'stored'  => true,
        ] );
    }
    
    // ============================================================================
    // INTAKE REQUEST HANDLER
    // ============================================================================
    
    function excreet_handle_intake_request( WP_REST_Request $request ): WP_REST_Response {
    
        excreet_log( 'REST WEBHOOK FIRED | method: ' . $request->get_method() );
    
        $body = $request->get_json_params();
    
        if ( empty( $body ) ) {
            $body = $request->get_body_params();
        }
    
        if ( empty( $body ) ) {
            $body = $request->get_params();
        }
    
        $body = is_array( $body ) ? $body : [];
    
        $sanitized_body = excreet_remove_private_fields( $body );
    
        excreet_log( 'Webhook data (sanitized): ' . wp_json_encode( $sanitized_body ) );
    
        $fields = excreet_extract_fields( $body );
    
        excreet_log( 'Fields extracted: ' . wp_json_encode( $fields ) );
    
        $member_id = excreet_member_id( $body );
        $tag       = excreet_generate_tag();
        $alias     = $fields['alias'] !== '' ? $fields['alias'] : 'member';
        $display   = $alias . ' · ' . $tag;
    
        excreet_log( 'Identity | member_id: ' . $member_id . ' | alias: ' . $alias . ' | tag: ' . $tag );
    
        $payload = [
            'member_id'        => $member_id,
            'intake_timestamp' => gmdate( 'c' ),
            'intake_type'      => 'baseline',
            'alias'            => $alias,
            'tag'              => $tag,
            'age'              => $fields['age'],
            'sex'              => $fields['sex'],
            'symptoms'         => $fields['symptoms'],
            'medications'      => $fields['medications'],
            'concerns'         => $fields['concerns'],
            'surgeries'        => $fields['surgeries'],
            'dietary_habits'   => $fields['dietary_habits'],
            'sleep_patterns'   => $fields['sleep_patterns'],
            'lifestyle_notes'  => $fields['lifestyle_notes'],
        ];
    
        excreet_log( 'Payload prepared: ' . wp_json_encode( $payload ) );

        // v2.8.1: Persist intake snapshot in user meta for $29 protocol generation.
        // Function defined in excreet-hermes-patch-294.php (loaded after this plugin).
        if ( function_exists( 'excreet_store_intake_snapshot' ) ) {
            excreet_store_intake_snapshot( $body, $payload );
        }
    
        // v2.6.6: Extract token BEFORE Hermes call (FIX 2 from v2.6.5)
        $handoff_token = excreet_extract_handoff_token( $body );
        excreet_log( 'Handoff token extracted BEFORE Hermes | token: ' . ( $handoff_token !== '' ? $handoff_token : 'EMPTY' ) );
    
        // v2.6.6: Try to recover token from user meta if hidden field was cleared
        if ( $handoff_token === '' ) {
            $handoff_token = excreet_recover_token_from_user_meta();
            excreet_log( 'Token recovery from user_meta | token: ' . ( $handoff_token !== '' ? $handoff_token : 'NOT FOUND' ) );
        }

        // v2.7.1: Server-to-server webhooks have no user session so user meta recovery
        // always fails. Look up the WP user via email in the raw form body instead.
        if ( $handoff_token === '' ) {
            $handoff_token = excreet_recover_token_from_form_user( $body );
            excreet_log( 'Token recovery via form email lookup | token: ' . ( $handoff_token !== '' ? $handoff_token : 'NOT FOUND' ) );
        }
    
        $hermes  = excreet_post_to_hermes( $payload );
        $storage = excreet_store_hermes_job_metadata( $hermes, $payload, $sanitized_body, $member_id, $alias, $tag );
    
        // v2.6.6: Update pre-stored transient with real jobId
        excreet_update_prestore_token_with_job_id( $hermes, $handoff_token );
    
        $entry_id = excreet_extract_forminator_entry_id( $body );
        excreet_log( 'Entry ID | found: ' . ( $entry_id > 0 ? 'yes' : 'no' ) . ' | entry_id: ' . $entry_id );
        excreet_prepare_processing_job_recovery( $hermes, $entry_id );
    
        $processing_page_url = excreet_build_processing_page_url( isset( $hermes['jobId'] ) ? (string) $hermes['jobId'] : '' );
        $result_page_url     = excreet_build_result_page_url( $storage['storage_key'] );
    
        $response_body = [
            'success'             => (bool) $hermes['success'],
            'alias'               => $alias,
            'tag'                 => $tag,
            'display'             => $display,
            'hermes_status'       => $hermes['hermes_status'],
            'jobId'               => $hermes['jobId'],
            'storage_success'     => $storage['storage_success'],
            'storage_type'        => $storage['storage_type'],
            'storage_key'         => $storage['storage_key'],
            'handoff_token'       => $handoff_token !== '' ? 'present' : 'missing',
            'processing_page_url' => $processing_page_url,
            'result_page_url'     => $result_page_url,
            'hermes'              => $hermes,
        ];
    
        $http_status = $hermes['success'] ? 200 : 502;
    
        return new WP_REST_Response( $response_body, $http_status );
    }
    
    // ============================================================================
    // v2.6.6 NEW: UPDATE PRE-STORED TRANSIENT WITH REAL JOB ID
    // ============================================================================
    
    function excreet_update_prestore_token_with_job_id( array $hermes, string $handoff_token ): void {
    
        if ( $handoff_token === '' ) {
            excreet_log( 'UPDATE PRE-STORE | skipped — no handoff token available' );
            return;
        }
    
        if ( empty( $hermes['success'] ) || empty( $hermes['jobId'] ) ) {
            excreet_log( 'UPDATE PRE-STORE | skipped — Hermes did not return a jobId' );
            return;
        }
    
        $job_id        = sanitize_text_field( (string) $hermes['jobId'] );
        $transient_key = 'excreet_token_' . $handoff_token;
    
        // Update the transient from 'pending' to real jobId
        set_transient( $transient_key, $job_id, 1800 );
        excreet_log( 'UPDATE PRE-STORE | key: ' . $transient_key . ' | jobId: ' . $job_id . ' | stored: yes' );
    
        // Also update user meta token record
        $user_id = get_current_user_id();
        if ( $user_id > 0 ) {
            update_user_meta( $user_id, 'excreet_pending_token', '' );
            update_user_meta( $user_id, 'excreet_hermes_job_id', $job_id );
            excreet_log( 'UPDATE PRE-STORE USER META | user_id: ' . $user_id . ' | jobId: ' . $job_id );
        }
    }
    
    // ============================================================================
    // v2.6.6 NEW: RECOVER TOKEN FROM USER META FALLBACK
    // ============================================================================
    
    function excreet_recover_token_from_user_meta(): string {
    
        $user_id = get_current_user_id();
    
        if ( $user_id <= 0 ) {
            return '';
        }
    
        $token     = sanitize_text_field( (string) get_user_meta( $user_id, 'excreet_pending_token', true ) );
        $token_time = (int) get_user_meta( $user_id, 'excreet_pending_token_time', true );
    
        if ( ! preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
            return '';
        }
    
        // Only use token if it was stored within the last 30 minutes
        if ( $token_time <= 0 || $token_time < ( time() - 1800 ) ) {
            return '';
        }
    
        return $token;
    }

    // ============================================================================
    // v2.7.1 NEW: RECOVER TOKEN VIA EMAIL LOOKUP
    // Forminator webhooks are server-to-server so get_current_user_id() always
    // returns 0. Instead, find the WP user by their email in the raw form body
    // and read their pre-stored pending token from user meta.
    // ============================================================================

    function excreet_recover_token_from_form_user( array $body ): string {

        $email = '';

        // Check explicit email field names first
        foreach ( [ 'email', 'email-1', 'email_1', 'email-2', 'email_2' ] as $key ) {
            if ( ! empty( $body[ $key ] ) && is_string( $body[ $key ] ) ) {
                $candidate = sanitize_email( trim( $body[ $key ] ) );
                if ( is_email( $candidate ) ) {
                    $email = $candidate;
                    break;
                }
            }
        }

        // If no explicit email field, scan all scalar values for an email address
        if ( $email === '' ) {
            foreach ( $body as $value ) {
                if ( is_string( $value ) ) {
                    $candidate = sanitize_email( trim( $value ) );
                    if ( is_email( $candidate ) ) {
                        $email = $candidate;
                        break;
                    }
                }
            }
        }

        if ( $email === '' ) {
            return '';
        }

        $user = get_user_by( 'email', $email );

        if ( ! ( $user instanceof WP_User ) ) {
            excreet_log( 'Token recovery via email | no WP user found for provided email' );
            return '';
        }

        $token      = sanitize_text_field( (string) get_user_meta( $user->ID, 'excreet_pending_token', true ) );
        $token_time = (int) get_user_meta( $user->ID, 'excreet_pending_token_time', true );

        if ( ! preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
            return '';
        }

        if ( $token_time <= 0 || $token_time < ( time() - 1800 ) ) {
            excreet_log( 'Token recovery via email | token expired | user_id: ' . $user->ID );
            return '';
        }

        excreet_log( 'Token recovered via email lookup | user_id: ' . $user->ID );
        return $token;
    }

    // ============================================================================
    // v2.7.1 NEW: RESOLVE TOKEN REST ENDPOINT
    // JS polls this instead of reloading the page. Checks multiple paths:
    // transient → user meta → Forminator DB entry → WP option store.
    // ============================================================================

    function excreet_handle_resolve_token( WP_REST_Request $request ): WP_REST_Response {

        $token = strtolower( sanitize_text_field( (string) $request->get_param( 'token' ) ) );

        // Path 1: transient is already resolved to a real jobId
        if ( preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
            $raw = get_transient( 'excreet_token_' . $token );
            if ( is_scalar( $raw ) ) {
                $val = sanitize_text_field( (string) $raw );
                if ( excreet_is_uuid_v4( $val ) ) {
                    return rest_ensure_response( [ 'resolved' => true, 'jobId' => $val ] );
                }
            }
        }

        // Path 2 & 3: look up via user identity (requires logged-in user on processing page)
        $user_id = get_current_user_id();

        if ( $user_id > 0 ) {

            // Path 2: user meta set when webhook had user context
            $job_id = sanitize_text_field( (string) get_user_meta( $user_id, 'excreet_hermes_job_id', true ) );
            if ( excreet_is_uuid_v4( $job_id ) ) {
                excreet_resolve_token_update_transient( $token, $job_id );
                return rest_ensure_response( [ 'resolved' => true, 'jobId' => $job_id ] );
            }

            // Path 3: Forminator DB + option/transient store (no user context needed in webhook)
            $job_id = excreet_find_latest_job_by_user( $user_id );
            if ( excreet_is_uuid_v4( $job_id ) ) {
                excreet_resolve_token_update_transient( $token, $job_id );
                return rest_ensure_response( [ 'resolved' => true, 'jobId' => $job_id ] );
            }
        }

        return rest_ensure_response( [ 'resolved' => false, 'jobId' => '' ] );
    }

    function excreet_resolve_token_update_transient( string $token, string $job_id ): void {
        if ( preg_match( '/^[0-9a-f]{32}$/', $token ) && excreet_is_uuid_v4( $job_id ) ) {
            set_transient( 'excreet_token_' . $token, $job_id, 1800 );
        }
    }

    function excreet_find_latest_job_by_user( int $user_id ): string {

        global $wpdb;

        // Query Forminator entry table for the most recent entry by this user
        $entry_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT e.entry_id
             FROM {$wpdb->prefix}frmt_form_entry e
             INNER JOIN {$wpdb->prefix}frmt_form_entry_meta m ON e.entry_id = m.entry_id
             WHERE e.form_id = %d
               AND m.meta_key = 'entry_created_by'
               AND m.meta_value = %s
             ORDER BY e.entry_id DESC
             LIMIT 1",
            EXCREET_FORM_ID,
            (string) $user_id
        ) );

        if ( ! $entry_id ) {
            excreet_log( 'resolve-token | no Forminator entry found | user_id: ' . $user_id );
            return '';
        }

        $entry_id = absint( $entry_id );
        excreet_log( 'resolve-token | found entry_id: ' . $entry_id . ' for user_id: ' . $user_id );

        // Check entry-based transient (set by excreet_prepare_processing_job_recovery)
        $raw = get_transient( 'excreet_entry_' . $entry_id );
        if ( is_scalar( $raw ) ) {
            $job_id = sanitize_text_field( (string) $raw );
            if ( excreet_is_uuid_v4( $job_id ) ) {
                excreet_log( 'resolve-token | found jobId via entry transient | entry_id: ' . $entry_id );
                return $job_id;
            }
        }

        // Check option-based storage (set by excreet_store_hermes_job_metadata fallback)
        $option = get_option( 'excreet_hermes_entry_' . $entry_id, null );
        if ( is_array( $option ) && ! empty( $option['jobId'] ) ) {
            $job_id = sanitize_text_field( (string) $option['jobId'] );
            if ( excreet_is_uuid_v4( $job_id ) ) {
                excreet_log( 'resolve-token | found jobId via entry option | entry_id: ' . $entry_id );
                return $job_id;
            }
        }

        return '';
    }
    
    // ============================================================================
    // PROCESSING PAGE URL BUILDERS
    // ============================================================================
    
    function excreet_build_processing_page_url( string $job_id ): string {
    
        $job_id = sanitize_text_field( $job_id );
    
        if ( $job_id === '' ) {
            return '';
        }
    
        $path     = defined( 'EXCREET_HERMES_PROCESSING_PAGE_PATH' ) ? (string) EXCREET_HERMES_PROCESSING_PAGE_PATH : '/intake-processing/';
        $path     = '/' . ltrim( trim( $path ), '/' );
    
        if ( substr( $path, -1 ) !== '/' ) {
            $path .= '/';
        }
    
        $base_url = home_url( $path );
    
        if ( ! is_string( $base_url ) || $base_url === '' ) {
            return '';
        }
    
        return add_query_arg( 'job', rawurlencode( $job_id ), $base_url );
    }
    
    function excreet_build_result_page_url( string $storage_key ): string {
    
        if ( $storage_key === '' ) {
            return '';
        }
    
        $path     = defined( 'EXCREET_HERMES_RESULT_PAGE_PATH' ) ? (string) EXCREET_HERMES_RESULT_PAGE_PATH : '/hermes-result-test/';
        $path     = '/' . ltrim( trim( $path ), '/' );
    
        if ( substr( $path, -1 ) !== '/' ) {
            $path .= '/';
        }
    
        $base_url = home_url( $path );
    
        if ( ! is_string( $base_url ) || $base_url === '' ) {
            return '';
        }
    
        return add_query_arg( 'storage_key', rawurlencode( $storage_key ), $base_url );
    }
    
    // ============================================================================
    // FIELD EXTRACTION
    // ============================================================================
    
    function excreet_extract_fields( array $body ): array {
    
        $body = excreet_remove_private_fields( $body );
    
        return [
            'alias'           => excreet_pluck( $body, [ 'name_1', 'name-1', 'alias', 'private_alias', 'name' ] ),
            'age'             => excreet_pluck( $body, [ 'number_1', 'number-1', 'age' ] ),
            'sex'             => excreet_pluck( $body, [ 'radio_1', 'radio-1', 'select_1', 'select-1', 'sex', 'gender' ] ),
            'symptoms'        => excreet_pluck( $body, [ 'checkbox_2', 'checkbox-2', 'symptoms' ] ),
            'medications'     => excreet_pluck( $body, [ 'textarea_1', 'textarea-1', 'medications' ] ),
            'concerns'        => excreet_pluck( $body, [ 'textarea_2', 'textarea-2', 'concerns' ] ),
            'surgeries'       => excreet_pluck( $body, [ 'textarea_3', 'textarea-3', 'surgeries' ] ),
            'dietary_habits'  => excreet_pluck( $body, [ 'textarea_4', 'textarea-4', 'dietary_habits', 'diet', 'dietary' ] ),
            'sleep_patterns'  => excreet_pluck( $body, [ 'textarea_5', 'textarea-5', 'sleep_patterns', 'sleep', 'sleep_behavior' ] ),
            'lifestyle_notes' => excreet_pluck( $body, [ 'textarea_6', 'textarea-6', 'lifestyle', 'lifestyle_notes' ] ),
        ];
    }
    
    function excreet_remove_private_fields( array $body ): array {
    
        $blocked_exact = [
            'email', 'email-1', 'email-2', 'email_1', 'email_2',
            'hidden_2', 'phone', 'phone-1', 'phone_1',
            'telephone', 'mobile', '_forminator_user_ip',
        ];
    
        foreach ( $blocked_exact as $key ) {
            unset( $body[ $key ] );
        }
    
        foreach ( array_keys( $body ) as $key ) {
            $key_string = strtolower( (string) $key );
    
            if ( strpos( $key_string, 'email' ) !== false ) {
                unset( $body[ $key ] );
                continue;
            }
    
            if ( strpos( $key_string, 'phone' ) !== false ) {
                unset( $body[ $key ] );
                continue;
            }
    
            if ( strpos( $key_string, 'ip' ) !== false ) {
                unset( $body[ $key ] );
                continue;
            }
    
            if ( 'hidden_1' !== $key_string && isset( $body[ $key ] ) && is_scalar( $body[ $key ] ) ) {
                $value = trim( (string) $body[ $key ] );
                if ( $value !== '' && is_email( $value ) ) {
                    unset( $body[ $key ] );
                    continue;
                }
            }
        }
    
        return $body;
    }
    
    function excreet_pluck( array $data, array $candidates ): string {
    
        foreach ( $candidates as $key ) {
            if ( isset( $data[ $key ] ) && $data[ $key ] !== '' && $data[ $key ] !== null ) {
                $value = $data[ $key ];
    
                if ( is_array( $value ) ) {
                    return implode( ', ', array_map( 'sanitize_text_field', $value ) );
                }
    
                return sanitize_textarea_field( (string) $value );
            }
        }
    
        return '';
    }
    
    // ============================================================================
    // MEMBER IDENTITY / TAG
    // ============================================================================
    
    function excreet_member_id( array $body = [] ): string {
    
        $user_id = get_current_user_id();
    
        if ( $user_id > 0 ) {
            return 'wp_user_' . $user_id;
        }
    
        if ( ! empty( $body['hidden_1'] ) ) {
            return 'forminator_entry_' . sanitize_key( (string) $body['hidden_1'] );
        }
    
        return 'guest_' . uniqid( '', true );
    }
    
    function excreet_generate_tag(): string {
    
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $tag   = '';
    
        for ( $i = 0; $i < 4; $i++ ) {
            $tag .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
        }
    
        return $tag;
    }
    
    // ============================================================================
    // HERMES HTTP REQUEST
    // ============================================================================
    
    function excreet_post_to_hermes( array $payload ): array {
    
        if ( ! defined( 'EXCREET_HERMES_API_KEY' ) || empty( EXCREET_HERMES_API_KEY ) ) {
            excreet_log( 'ERROR: EXCREET_HERMES_API_KEY is not defined in wp-config.php. Aborting.' );
            return [
                'success'       => false,
                'hermes_status' => 'config_error',
                'jobId'         => null,
                'error'         => 'EXCREET_HERMES_API_KEY not configured in wp-config.php',
            ];
        }
    
        $body = wp_json_encode( [
            'member_id'     => $payload['member_id'],
            'workflow_type' => 'pharmaceutical_intake',
            'payload'       => $payload,
        ] );
    
        $response = wp_remote_post(
            EXCREET_HERMES_URL,
            [
                'timeout'     => 45,
                'redirection' => 0,
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY,
                ],
                'body'        => $body,
            ]
        );
    
        if ( is_wp_error( $response ) ) {
            excreet_log( 'ERROR: Hermes request failed — ' . $response->get_error_message() );
            return [
                'success'       => false,
                'hermes_status' => 'request_error',
                'jobId'         => null,
                'error'         => $response->get_error_message(),
            ];
        }
    
        $code          = (int) wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $decoded       = json_decode( $response_body, true );
    
        excreet_log( 'Hermes response: HTTP ' . $code . ' | ' . $response_body );
    
        if ( $code === 202 && is_array( $decoded ) && ! empty( $decoded['jobId'] ) ) {
            $job_id = (string) $decoded['jobId'];
            excreet_log( 'Hermes job accepted | jobId: ' . $job_id );
            return [
                'success'       => true,
                'hermes_status' => isset( $decoded['status'] ) ? (string) $decoded['status'] : 'pending',
                'jobId'         => $job_id,
                'message'       => isset( $decoded['message'] ) ? (string) $decoded['message'] : '',
            ];
        }
    
        excreet_log( 'WARNING: Hermes returned unexpected response | HTTP ' . $code );
    
        return [
            'success'       => false,
            'hermes_status' => 'error',
            'jobId'         => null,
            'http_code'     => $code,
            'error'         => is_array( $decoded ) && isset( $decoded['message'] )
                ? (string) $decoded['message']
                : 'Unexpected response from Hermes',
        ];
    }
    
    function excreet_store_hermes_job_metadata(
        array $hermes,
        array $payload,
        array $sanitized_body,
        string $member_id,
        string $alias,
        string $tag
    ): array {
    
        $result = [
            'storage_success' => false,
            'storage_type'    => 'none',
            'storage_key'     => '',
        ];
    
        if ( empty( $hermes['success'] ) || empty( $hermes['jobId'] ) ) {
            return $result;
        }
    
        $job_id        = sanitize_text_field( (string) $hermes['jobId'] );
        $hermes_status = isset( $hermes['hermes_status'] ) ? sanitize_text_field( (string) $hermes['hermes_status'] ) : 'pending';
        $submitted_at  = gmdate( 'c' );
        $snapshot      = excreet_remove_private_fields( $payload );
    
        $current_user_id = get_current_user_id();
    
        if ( $current_user_id > 0 ) {
            $updated  = true;
            $updated  = $updated && false !== update_user_meta( $current_user_id, 'excreet_hermes_job_id', $job_id );
            $updated  = $updated && false !== update_user_meta( $current_user_id, 'excreet_hermes_job_status', $hermes_status );
            $updated  = $updated && false !== update_user_meta( $current_user_id, 'excreet_hermes_submitted_at', $submitted_at );
            $updated  = $updated && false !== update_user_meta( $current_user_id, 'excreet_hermes_member_alias', sanitize_text_field( $alias ) );
            $updated  = $updated && false !== update_user_meta( $current_user_id, 'excreet_hermes_payload_snapshot', wp_json_encode( $snapshot ) );
    
            if ( $updated ) {
                excreet_log( 'Stored Hermes jobId | jobId: ' . $job_id . ' | storage: user_meta' );
                return [
                    'storage_success' => true,
                    'storage_type'    => 'user_meta',
                    'storage_key'     => 'user_' . $current_user_id,
                ];
            }
        }
    
        $option_value = [
            'jobId'            => $job_id,
            'hermes_status'    => $hermes_status,
            'submitted_at'     => $submitted_at,
            'member_id'        => sanitize_text_field( $member_id ),
            'alias'            => sanitize_text_field( $alias ),
            'tag'              => sanitize_text_field( $tag ),
            'payload_snapshot' => $snapshot,
        ];
    
        $hidden_1 = '';
    
        if ( isset( $sanitized_body['hidden_1'] ) && $sanitized_body['hidden_1'] !== '' ) {
            $hidden_1 = sanitize_key( (string) $sanitized_body['hidden_1'] );
        }
    
        if ( $hidden_1 !== '' ) {
            $option_key = 'excreet_hermes_entry_' . $hidden_1;
            $saved      = update_option( $option_key, $option_value, false );
    
            if ( $saved ) {
                excreet_log( 'Stored Hermes jobId | jobId: ' . $job_id . ' | storage: option_fallback' );
                return [
                    'storage_success' => true,
                    'storage_type'    => 'option_fallback',
                    'storage_key'     => $option_key,
                ];
            }
        }
    
        $guest_tag   = excreet_generate_tag();
        $option_key  = 'excreet_hermes_guest_' . strtolower( $guest_tag );
        $saved_guest = update_option( $option_key, $option_value, false );
    
        if ( $saved_guest ) {
            excreet_log( 'Stored Hermes jobId | jobId: ' . $job_id . ' | storage: guest_option' );
            return [
                'storage_success' => true,
                'storage_type'    => 'guest_option',
                'storage_key'     => $option_key,
            ];
        }
    
        return [
            'storage_success' => false,
            'storage_type'    => 'guest_option',
            'storage_key'     => $option_key,
        ];
    }
    
    // ============================================================================
    // JOB STATUS POLLING
    // ============================================================================
    
    function excreet_handle_job_status_request( WP_REST_Request $request ): WP_REST_Response {
    
        $job_id      = sanitize_text_field( (string) $request->get_param( 'job_id' ) );
        $storage_key = sanitize_key( (string) $request->get_param( 'storage_key' ) );
    
        if ( $job_id === '' && $storage_key === '' ) {
            return new WP_REST_Response( [
                'success' => false,
                'error'   => 'Missing required query parameter. Provide job_id or storage_key.',
            ], 400 );
        }
    
        $stored = excreet_read_job_record_by_storage_key( $storage_key );
    
        if ( $job_id === '' ) {
            $job_id = excreet_extract_job_id_from_record( $stored['record'] );
        }
    
        if ( $job_id === '' ) {
            return new WP_REST_Response( [
                'success'     => false,
                'storage_key' => $storage_key,
                'error'       => 'Unable to resolve job_id from provided storage_key.',
            ], 404 );
        }
    
        $hermes_status = excreet_poll_hermes_job_status( $job_id );
    
        if ( empty( $hermes_status['success'] ) ) {
            $error_response = [
                'success'       => false,
                'jobId'         => $job_id,
                'storage_key'   => $storage_key,
                'hermes_status' => $hermes_status['hermes_status'],
                'error'         => $hermes_status['error'],
            ];
    
            if ( isset( $hermes_status['http_code'] ) ) {
                $error_response['hermes_http_code'] = (int) $hermes_status['http_code'];
            }
    
            return new WP_REST_Response(
                $error_response,
                isset( $hermes_status['http_code'] ) ? (int) $hermes_status['http_code'] : 502
            );
        }
    
        $persisted = [
            'stored'       => false,
            'storage_key'  => $stored['storage_key'],
            'storage_type' => $stored['storage_type'],
        ];
    
        if ( $hermes_status['hermes_status'] === 'completed' && ! empty( $hermes_status['result'] ) ) {
            $persisted = excreet_store_completed_job_result( $job_id, $hermes_status, $stored );
        }
    
        return rest_ensure_response( [
            'success'       => true,
            'jobId'         => $job_id,
            'storage_key'   => $persisted['storage_key'],
            'storage_type'  => $persisted['storage_type'],
            'hermes_status' => $hermes_status['hermes_status'],
            'result'        => $hermes_status['result'],
            'persisted'     => (bool) $persisted['stored'],
            'updated_at'    => gmdate( 'c' ),
        ] );
    }
    
    function excreet_poll_hermes_job_status( string $job_id ): array {
    
        if ( ! defined( 'EXCREET_HERMES_API_KEY' ) || empty( EXCREET_HERMES_API_KEY ) ) {
            return [
                'success'       => false,
                'hermes_status' => 'config_error',
                'error'         => 'EXCREET_HERMES_API_KEY not configured in wp-config.php',
            ];
        }
    
        $url = excreet_hermes_base_url() . '/api/hermes/job-status/' . rawurlencode( $job_id );
    
        $response = wp_remote_get( $url, [
            'timeout'     => 45,
            'redirection' => 0,
            'headers'     => [
                'Authorization' => 'Bearer ' . EXCREET_HERMES_API_KEY,
            ],
        ] );
    
        if ( is_wp_error( $response ) ) {
            return [
                'success'       => false,
                'hermes_status' => 'request_error',
                'error'         => $response->get_error_message(),
            ];
        }
    
        $code          = (int) wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $decoded       = json_decode( $response_body, true );
    
        if ( ! is_array( $decoded ) ) {
            return [
                'success'       => false,
                'hermes_status' => 'parse_error',
                'http_code'     => $code,
                'error'         => 'Invalid JSON returned from Hermes job status endpoint.',
            ];
        }
    
        $status           = isset( $decoded['status'] ) ? sanitize_key( (string) $decoded['status'] ) : '';
        $allowed_statuses = [ 'pending', 'processing', 'completed', 'failed' ];
    
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            return [
                'success'       => false,
                'jobId'         => $job_id,
                'hermes_status' => 'error',
                'http_code'     => $code,
                'error'         => 'Unexpected Hermes response shape from job status endpoint.',
            ];
        }
    
        if ( $code < 200 || $code >= 300 ) {
            return [
                'success'       => false,
                'hermes_status' => $status,
                'http_code'     => $code,
                'error'         => isset( $decoded['message'] ) ? sanitize_text_field( (string) $decoded['message'] ) : 'Hermes job status request failed.',
            ];
        }
    
        return [
            'success'        => true,
            'hermes_status'  => $status,
            'jobId'          => isset( $decoded['jobId'] ) ? sanitize_text_field( (string) $decoded['jobId'] ) : $job_id,
            'result'         => excreet_sanitize_result_payload( isset( $decoded['result'] ) ? $decoded['result'] : [] ),
            'status_payload' => excreet_sanitize_status_payload( $decoded ),
        ];
    }
    
    function excreet_hermes_base_url(): string {
    
        $endpoint = defined( 'EXCREET_HERMES_URL' ) ? (string) EXCREET_HERMES_URL : '';
    
        if ( $endpoint === '' ) {
            return '';
        }
    
        $parts = wp_parse_url( $endpoint );
    
        if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return '';
        }
    
        $base = $parts['scheme'] . '://' . $parts['host'];
    
        if ( ! empty( $parts['port'] ) ) {
            $base .= ':' . (int) $parts['port'];
        }
    
        return rtrim( $base, '/' );
    }
    
    function excreet_read_job_record_by_storage_key( string $storage_key ): array {
    
        if ( $storage_key === '' ) {
            return [ 'record' => [], 'storage_key' => '', 'storage_type' => 'none' ];
        }
    
        if ( preg_match( '/^user_(\d+)$/', $storage_key, $matches ) ) {
            $user_id = (int) $matches[1];
            $record  = [
                'jobId'            => (string) get_user_meta( $user_id, 'excreet_hermes_job_id', true ),
                'hermes_status'    => (string) get_user_meta( $user_id, 'excreet_hermes_job_status', true ),
                'submitted_at'     => (string) get_user_meta( $user_id, 'excreet_hermes_submitted_at', true ),
                'alias'            => (string) get_user_meta( $user_id, 'excreet_hermes_member_alias', true ),
                'payload_snapshot' => json_decode( (string) get_user_meta( $user_id, 'excreet_hermes_payload_snapshot', true ), true ),
            ];
    
            return [ 'record' => $record, 'storage_key' => $storage_key, 'storage_type' => 'user_meta', 'user_id' => $user_id ];
        }
    
        $option = get_option( $storage_key, null );
    
        if ( is_array( $option ) ) {
            return [ 'record' => $option, 'storage_key' => $storage_key, 'storage_type' => 'option' ];
        }
    
        return [ 'record' => [], 'storage_key' => $storage_key, 'storage_type' => 'option' ];
    }
    
    function excreet_extract_job_id_from_record( array $record ): string {
    
        if ( isset( $record['jobId'] ) && $record['jobId'] !== '' ) {
            return sanitize_text_field( (string) $record['jobId'] );
        }
    
        if ( isset( $record['job_id'] ) && $record['job_id'] !== '' ) {
            return sanitize_text_field( (string) $record['job_id'] );
        }
    
        return '';
    }
    
    function excreet_store_completed_job_result( string $job_id, array $hermes_status, array $stored ): array {
    
        $result = isset( $hermes_status['result'] ) && is_array( $hermes_status['result'] ) ? $hermes_status['result'] : [];
    
        // Route to schema-specific sanitizer, then append storage timestamp.
        $completed_result                = excreet_sanitize_result_payload( $result );
        $completed_result['completed_at'] = gmdate( 'c' );
    
        if ( isset( $stored['storage_type'] ) && $stored['storage_type'] === 'user_meta' && ! empty( $stored['user_id'] ) ) {
            $user_id = (int) $stored['user_id'];
            $updated = true;
            $updated = $updated && false !== update_user_meta( $user_id, 'excreet_hermes_job_status', 'completed' );
            $updated = $updated && false !== update_user_meta( $user_id, 'excreet_hermes_completed_result', wp_json_encode( $completed_result ) );
            $updated = $updated && false !== update_user_meta( $user_id, 'excreet_hermes_completed_at', $completed_result['completed_at'] );

            // Permanently store the Clinical Pattern Report as the member's onboarding
            // health baseline — used by Ministry of Healing as foundational context.
            if ( excreet_is_pharma_result( $result ) ) {
                update_user_meta( $user_id, 'excreet_hermes_baseline', wp_json_encode( $completed_result ) );
                excreet_log( 'Onboarding baseline stored for user_id: ' . $user_id );
            }

            return [ 'stored' => $updated, 'storage_key' => 'user_' . $user_id, 'storage_type' => 'user_meta' ];
        }
    
        if ( ! empty( $stored['storage_key'] ) ) {
            $option_key   = sanitize_key( (string) $stored['storage_key'] );
            $option_value = is_array( $stored['record'] ) ? $stored['record'] : [];
        } else {
            $option_key   = 'excreet_hermes_job_' . sanitize_key( $job_id );
            $option_value = [];
        }
    
        $option_value['jobId']            = sanitize_text_field( $job_id );
        $option_value['hermes_status']    = 'completed';
        $option_value['completed_result'] = $completed_result;
        $option_value['completed_at']     = $completed_result['completed_at'];
    
        $updated = update_option( $option_key, $option_value, false );
    
        return [ 'stored' => false !== $updated, 'storage_key' => $option_key, 'storage_type' => 'option' ];
    }
    
    /**
     * Returns true when $result matches the pharmaceutical_intake (Clinical Pattern Report) schema.
     * Heuristic: presence of memberProfile or prescribedPharmaceuticals keys.
     *
     * @param array $result Raw result array from Hermes.
     */
    function excreet_is_pharma_result( array $result ): bool {
        return isset( $result['memberProfile'] ) || isset( $result['prescribedPharmaceuticals'] );
    }

    function excreet_sanitize_result_payload( $result ): array {

        if ( ! is_array( $result ) ) {
            return [];
        }

        // ── Pharmaceutical / Clinical Pattern Report schema ──
        if ( excreet_is_pharma_result( $result ) ) {
            return excreet_sanitize_pharma_result( $result );
        }

        // ── Health Intake schema (v2 tier model) ──
        return [
            'tier'           => isset( $result['tier'] ) ? sanitize_key( (string) $result['tier'] ) : 'nudge',
            'vitalityScore'  => isset( $result['vitalityScore'] ) ? (int) $result['vitalityScore'] : 0,
            'trajectoryRead' => isset( $result['trajectoryRead'] ) ? sanitize_textarea_field( (string) $result['trajectoryRead'] ) : '',
            'quickActions'   => excreet_sanitize_string_list( isset( $result['quickActions'] ) ? $result['quickActions'] : [] ),
            'medicalPath'    => isset( $result['medicalPath'] ) && is_array( $result['medicalPath'] ) ? $result['medicalPath'] : null,
            'ministryPath'   => isset( $result['ministryPath'] ) && is_array( $result['ministryPath'] ) ? $result['ministryPath'] : null,
            'disclaimer'     => isset( $result['disclaimer'] ) ? sanitize_textarea_field( (string) $result['disclaimer'] ) : '',
        ];
    }

    /**
     * Sanitizes a pharmaceutical_intake (Clinical Pattern Report) result payload.
     * Nested arrays are preserved as-is after top-level string fields are sanitized.
     *
     * @param array $result Raw pharma result.
     */
    function excreet_sanitize_pharma_result( array $result ): array {

        $member_profile = [];
        if ( isset( $result['memberProfile'] ) && is_array( $result['memberProfile'] ) ) {
            $mp = $result['memberProfile'];
            $member_profile = [
                'age'              => isset( $mp['age'] ) ? sanitize_text_field( (string) $mp['age'] ) : '',
                'sex'              => isset( $mp['sex'] ) ? sanitize_text_field( (string) $mp['sex'] ) : '',
                'exposureDuration' => isset( $mp['exposureDuration'] ) ? sanitize_text_field( (string) $mp['exposureDuration'] ) : '',
                'assessmentDate'   => isset( $mp['assessmentDate'] ) ? sanitize_text_field( (string) $mp['assessmentDate'] ) : '',
            ];
        }

        $pharmaceuticals = [];
        if ( isset( $result['prescribedPharmaceuticals'] ) && is_array( $result['prescribedPharmaceuticals'] ) ) {
            foreach ( $result['prescribedPharmaceuticals'] as $drug ) {
                if ( ! is_array( $drug ) ) { continue; }
                $pharmaceuticals[] = [
                    'name'      => isset( $drug['name'] ) ? sanitize_text_field( (string) $drug['name'] ) : '',
                    'dosage'    => isset( $drug['dosage'] ) ? sanitize_text_field( (string) $drug['dosage'] ) : '',
                    'frequency' => isset( $drug['frequency'] ) ? sanitize_text_field( (string) $drug['frequency'] ) : '',
                ];
            }
        }

        $red_flags = [];
        if ( isset( $result['redFlagSummary'] ) && is_array( $result['redFlagSummary'] ) ) {
            foreach ( $result['redFlagSummary'] as $flag ) {
                if ( ! is_array( $flag ) ) { continue; }
                $red_flags[] = [
                    'level'       => isset( $flag['level'] ) ? sanitize_key( (string) $flag['level'] ) : 'AWARENESS',
                    'title'       => isset( $flag['title'] ) ? sanitize_text_field( (string) $flag['title'] ) : '',
                    'description' => isset( $flag['description'] ) ? sanitize_textarea_field( (string) $flag['description'] ) : '',
                ];
            }
        }

        $interactions = [];
        if ( isset( $result['drugInteractionLoops'] ) && is_array( $result['drugInteractionLoops'] ) ) {
            foreach ( $result['drugInteractionLoops'] as $loop ) {
                if ( ! is_array( $loop ) ) { continue; }
                $interactions[] = [
                    'name'        => isset( $loop['name'] ) ? sanitize_text_field( (string) $loop['name'] ) : '',
                    'medications' => excreet_sanitize_string_list( isset( $loop['medications'] ) ? $loop['medications'] : [] ),
                    'mechanism'   => isset( $loop['mechanism'] ) ? sanitize_textarea_field( (string) $loop['mechanism'] ) : '',
                    'effects'     => excreet_sanitize_string_list( isset( $loop['effects'] ) ? $loop['effects'] : [] ),
                    'severity'    => isset( $loop['severity'] ) ? sanitize_key( (string) $loop['severity'] ) : 'MODERATE',
                ];
            }
        }

        $lab_markers = [];
        if ( isset( $result['labMarkerTriggers'] ) && is_array( $result['labMarkerTriggers'] ) ) {
            foreach ( $result['labMarkerTriggers'] as $marker ) {
                if ( ! is_array( $marker ) ) { continue; }
                $lab_markers[] = [
                    'riskArea'         => isset( $marker['riskArea'] ) ? sanitize_text_field( (string) $marker['riskArea'] ) : '',
                    'labMarker'        => isset( $marker['labMarker'] ) ? sanitize_text_field( (string) $marker['labMarker'] ) : '',
                    'whatItIndicates'  => isset( $marker['whatItIndicates'] ) ? sanitize_textarea_field( (string) $marker['whatItIndicates'] ) : '',
                    'targetAlertLevel' => isset( $marker['targetAlertLevel'] ) ? sanitize_text_field( (string) $marker['targetAlertLevel'] ) : '',
                    'action'           => isset( $marker['action'] ) ? sanitize_key( (string) $marker['action'] ) : 'Monitor',
                ];
            }
        }

        return [
            'schema_type'               => 'pharmaceutical',
            'memberProfile'             => $member_profile,
            'prescribedPharmaceuticals' => $pharmaceuticals,
            'redFlagSummary'            => $red_flags,
            'drugInteractionLoops'      => $interactions,
            'labMarkerTriggers'         => $lab_markers,
            'expectedObservableSignals' => excreet_sanitize_string_list( isset( $result['expectedObservableSignals'] ) ? $result['expectedObservableSignals'] : [] ),
            'excreetInterpretation'     => isset( $result['excreetInterpretation'] ) ? sanitize_textarea_field( (string) $result['excreetInterpretation'] ) : '',
            'recommendationSummary'     => isset( $result['recommendationSummary'] ) ? sanitize_textarea_field( (string) $result['recommendationSummary'] ) : '',
            'excreetPrinciple'          => isset( $result['excreetPrinciple'] ) ? sanitize_textarea_field( (string) $result['excreetPrinciple'] ) : '',
            'disclaimer'                => isset( $result['disclaimer'] ) ? sanitize_textarea_field( (string) $result['disclaimer'] ) : '',
        ];
    }
    
    function excreet_sanitize_string_list( $list ): array {
    
        if ( ! is_array( $list ) ) {
            return [];
        }
    
        $clean = [];
    
        foreach ( $list as $item ) {
            if ( is_scalar( $item ) ) {
                $clean[] = sanitize_textarea_field( (string) $item );
            }
        }
    
        return $clean;
    }
    
    function excreet_sanitize_status_payload( $value ) {
    
        if ( ! is_array( $value ) ) {
            return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
        }
    
        $clean = [];
    
        foreach ( $value as $key => $item ) {
            $key_string = strtolower( (string) $key );
    
            if (
                strpos( $key_string, 'email' ) !== false ||
                strpos( $key_string, 'phone' ) !== false ||
                strpos( $key_string, 'ip' ) !== false ||
                strpos( $key_string, 'secret' ) !== false ||
                strpos( $key_string, 'token' ) !== false ||
                strpos( $key_string, 'authorization' ) !== false ||
                strpos( $key_string, 'api_key' ) !== false ||
                strpos( $key_string, 'password' ) !== false ||
                strpos( $key_string, 'payload' ) !== false ||
                $key_string === 'hidden_2'
            ) {
                continue;
            }
    
            if ( is_array( $item ) ) {
                $clean[ $key ] = excreet_sanitize_status_payload( $item );
                continue;
            }
    
            if ( is_scalar( $item ) ) {
                $clean[ $key ] = sanitize_textarea_field( (string) $item );
            }
        }
    
        return $clean;
    }
    
    // ============================================================================
    // FORMINATOR FIELD HELPERS
    // ============================================================================
    
    function excreet_extract_forminator_entry_id( array $body ): int {
    
        if ( isset( $body['entry_id'] ) ) {
            return absint( $body['entry_id'] );
        }
    
        if ( isset( $body['data'] ) && is_array( $body['data'] ) && isset( $body['data']['entry_id'] ) ) {
            return absint( $body['data']['entry_id'] );
        }
    
        if ( isset( $body['entry'] ) && is_array( $body['entry'] ) && isset( $body['entry']['entry_id'] ) ) {
            return absint( $body['entry']['entry_id'] );
        }
    
        return 0;
    }
    
    function excreet_extract_handoff_token( array $body ): string {
    
        $token = '';
    
        // Direct field checks
        if ( isset( $body['hidden-5'] ) && is_scalar( $body['hidden-5'] ) ) {
            $token = strtolower( sanitize_text_field( (string) $body['hidden-5'] ) );
        } elseif ( isset( $body['hidden_5'] ) && is_scalar( $body['hidden_5'] ) ) {
            $token = strtolower( sanitize_text_field( (string) $body['hidden_5'] ) );
        }
    
        // Nested data[] array fallback (v2.6.5 FIX 3)
        if ( $token === '' && isset( $body['data'] ) && is_array( $body['data'] ) ) {
            if ( isset( $body['data']['hidden-5'] ) ) {
                $token = strtolower( sanitize_text_field( (string) $body['data']['hidden-5'] ) );
            } elseif ( isset( $body['data']['hidden_5'] ) ) {
                $token = strtolower( sanitize_text_field( (string) $body['data']['hidden_5'] ) );
            }
        }
    
        // Nested fields[] array fallback
        if ( $token === '' && isset( $body['fields'] ) && is_array( $body['fields'] ) ) {
            if ( isset( $body['fields']['hidden-5'] ) ) {
                $token = strtolower( sanitize_text_field( (string) $body['fields']['hidden-5'] ) );
            } elseif ( isset( $body['fields']['hidden_5'] ) ) {
                $token = strtolower( sanitize_text_field( (string) $body['fields']['hidden_5'] ) );
            }
        }
    
        excreet_log( 'Token extraction | raw token found: ' . ( $token !== '' ? 'yes' : 'no' ) . ' | valid format: ' . ( preg_match( '/^[0-9a-f]{32}$/', $token ) ? 'yes' : 'no' ) );
    
        if ( ! preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
            return '';
        }
    
        return $token;
    }
    
    // ============================================================================
    // PROCESSING JOB RECOVERY
    // ============================================================================
    
    function excreet_store_handoff_token_job_id( array $hermes, string $handoff_token ): void {
    
        if ( $handoff_token === '' ) {
            return;
        }
    
        if ( empty( $hermes['success'] ) || empty( $hermes['jobId'] ) ) {
            return;
        }
    
        $job_id = sanitize_text_field( (string) $hermes['jobId'] );
    
        if ( ! excreet_is_uuid_v4( $job_id ) ) {
            return;
        }
    
        $stored = set_transient( 'excreet_token_' . $handoff_token, $job_id, 1800 );
        excreet_log( 'Token handoff stored | jobId: ' . $job_id . ' | stored: ' . ( $stored ? 'yes' : 'no' ) );
    }
    
    function excreet_prepare_processing_job_recovery( array $hermes, int $entry_id = 0 ): void {
    
        if ( empty( $hermes['success'] ) || empty( $hermes['jobId'] ) ) {
            return;
        }
    
        $job_id = sanitize_text_field( (string) $hermes['jobId'] );
    
        if ( ! excreet_is_uuid_v4( $job_id ) ) {
            return;
        }
    
        excreet_set_processing_job_cookie( $job_id );
    
        $transient_stored = false;
    
        if ( $entry_id > 0 ) {
            $transient_stored = set_transient( 'excreet_entry_' . $entry_id, $job_id, 1800 );
        }
    
        excreet_log( 'Processing handoff | entry_id: ' . $entry_id . ' | jobId: ' . $job_id . ' | transient: ' . ( $transient_stored ? 'yes' : 'no' ) );
    
        $user_id = get_current_user_id();
    
        if ( $user_id > 0 ) {
            update_user_meta( $user_id, 'excreet_latest_job_id', $job_id );
            update_user_meta( $user_id, 'excreet_latest_job_time', time() );
        }
    }
    
    function excreet_set_processing_job_cookie( string $job_id ): void {
    
        if ( headers_sent() || ! excreet_is_uuid_v4( $job_id ) ) {
            return;
        }
    
        setcookie( 'excreet_job', $job_id, [
            'expires'  => time() + 900,
            'path'     => '/intake-processing/',
            'secure'   => true,
            'httponly' => false,
            'samesite' => 'Strict',
        ] );
    }
    
    function excreet_clear_processing_job_cookie(): void {
    
        if ( headers_sent() ) {
            return;
        }
    
        setcookie( 'excreet_job', '', [
            'expires'  => time() - HOUR_IN_SECONDS,
            'path'     => '/intake-processing/',
            'secure'   => true,
            'httponly' => false,
            'samesite' => 'Strict',
        ] );
    }
    
    // ============================================================================
    // PROCESSING PAGE SHORTCODE
    // ============================================================================
    
    add_shortcode( 'excreet_hermes_processing_result', 'excreet_shortcode_processing_result' );
    
    function excreet_shortcode_processing_result(): string {
    
        $job_id = excreet_read_processing_job_id();
    
        if ( $job_id === '' ) {
            return excreet_render_processing_shell( '', 'invalid_job' );
        }
    
        return excreet_render_processing_shell( $job_id, 'pending' );
    }
    
    function excreet_read_processing_job_id(): string {
    
        // v2.6.6: Check token parameter first (primary path)
        if ( filter_has_var( INPUT_GET, 'token' ) ) {
            $token = sanitize_text_field( (string) filter_input( INPUT_GET, 'token', FILTER_SANITIZE_SPECIAL_CHARS ) );
            $token = strtolower( $token );
    
            if ( preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
                $transient_key = 'excreet_token_' . $token;
                $token_job_raw = get_transient( $transient_key );
                $token_job_id  = is_scalar( $token_job_raw ) ? sanitize_text_field( (string) $token_job_raw ) : '';
    
                excreet_log( 'Processing page | token: ' . $token . ' | transient_value: ' . $token_job_id );
    
                // v2.6.6: If transient value is 'pending', Hermes hasn't responded yet
                // Wait and poll instead of failing
                if ( $token_job_id === 'pending' ) {
                    excreet_log( 'Processing page | token found but job still pending — will poll' );
                    // Return special pending state - shortcode handles display
                    return 'PENDING_TOKEN:' . $token;
                }
    
                if ( excreet_is_uuid_v4( $token_job_id ) ) {
                    excreet_log( 'Processing page | token resolved to jobId: ' . $token_job_id );
                    return $token_job_id;
                }
            }
        }
    
        // Fallback: direct job UUID in URL
        if ( filter_has_var( INPUT_GET, 'job' ) ) {
            $job = sanitize_text_field( (string) filter_input( INPUT_GET, 'job', FILTER_SANITIZE_SPECIAL_CHARS ) );
            return excreet_is_uuid_v4( $job ) ? $job : '';
        }
    
        // Fallback: entry-based transient
        if ( filter_has_var( INPUT_GET, 'entry' ) ) {
            $entry_id     = absint( filter_input( INPUT_GET, 'entry', FILTER_SANITIZE_NUMBER_INT ) );
            $entry_job_id = $entry_id > 0 ? get_transient( 'excreet_entry_' . $entry_id ) : '';
            $entry_job_id = is_scalar( $entry_job_id ) ? sanitize_text_field( (string) $entry_job_id ) : '';
    
            if ( excreet_is_uuid_v4( $entry_job_id ) ) {
                return $entry_job_id;
            }
        }
    
        // Fallback: cookie
        $cookie_job = excreet_read_processing_job_cookie();
    
        if ( isset( $_COOKIE['excreet_job'] ) ) {
            excreet_clear_processing_job_cookie();
        }
    
        if ( $cookie_job !== '' ) {
            return $cookie_job;
        }
    
        // Fallback: v2.7.0 redirect-token cookie (set by JS on form page, survives Forminator redirect)
        if ( ! empty( $_COOKIE['excreet_rt'] ) && is_scalar( $_COOKIE['excreet_rt'] ) ) {
            $rt = strtolower( sanitize_text_field( wp_unslash( (string) $_COOKIE['excreet_rt'] ) ) );
            if ( preg_match( '/^[0-9a-f]{32}$/', $rt ) ) {
                $rt_key     = 'excreet_token_' . $rt;
                $rt_job_raw = get_transient( $rt_key );
                $rt_job_id  = is_scalar( $rt_job_raw ) ? sanitize_text_field( (string) $rt_job_raw ) : '';
                excreet_log( 'Processing page | excreet_rt cookie | rt: ' . $rt . ' | transient_value: ' . $rt_job_id );
                if ( $rt_job_id === 'pending' ) {
                    return 'PENDING_TOKEN:' . $rt;
                }
                if ( excreet_is_uuid_v4( $rt_job_id ) ) {
                    return $rt_job_id;
                }
            }
        }

        // Fallback: user meta
        return excreet_read_latest_processing_job_from_user_meta();
    }
    
    function excreet_is_uuid_v4( string $job_id ): bool {
        return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $job_id );
    }
    
    function excreet_read_processing_job_cookie(): string {
    
        if ( empty( $_COOKIE['excreet_job'] ) || ! is_scalar( $_COOKIE['excreet_job'] ) ) {
            return '';
        }
    
        $job_id = sanitize_text_field( wp_unslash( (string) $_COOKIE['excreet_job'] ) );
    
        return excreet_is_uuid_v4( $job_id ) ? $job_id : '';
    }
    
    function excreet_read_latest_processing_job_from_user_meta(): string {
    
        $user_id = get_current_user_id();
    
        if ( $user_id <= 0 ) {
            return '';
        }
    
        $job_id   = sanitize_text_field( (string) get_user_meta( $user_id, 'excreet_latest_job_id', true ) );
        $job_time = (int) get_user_meta( $user_id, 'excreet_latest_job_time', true );
    
        if ( ! excreet_is_uuid_v4( $job_id ) ) {
            return '';
        }
    
        if ( $job_time <= 0 || $job_time < ( time() - DAY_IN_SECONDS ) ) {
            return '';
        }
    
        return $job_id;
    }
    
    function excreet_render_processing_shell( string $job_id, string $initial_state ): string {
    
        // Handle pending token state (v2.6.6)
        $is_pending_token = strpos( $job_id, 'PENDING_TOKEN:' ) === 0;
        $pending_token    = $is_pending_token ? substr( $job_id, 14 ) : '';
    
        if ( $is_pending_token ) {
            $job_id = '';
        }
    
        $public_endpoint_base = excreet_hermes_base_url() . '/api/hermes/result/';
        $prestore_endpoint    = rest_url( 'excreet/v1/prestore-token' );
        $state                = sanitize_key( $initial_state );
    
        ob_start();
        ?>
        <div id="excreet-hermes-processing-card" style="border:1px solid #d9e2ec;border-radius:12px;padding:18px;background:#ffffff;max-width:760px;">
            <h3 style="margin:0 0 8px;font-size:20px;line-height:1.3;color:#102a43;">Your Hermes Intake Result</h3>
            <p id="excreet-hermes-processing-message" style="margin:0;color:#486581;font-size:15px;line-height:1.6;">
                <?php echo esc_html( $state === 'invalid_job' ? 'We could not find your latest intake session. Please submit the intake form again.' : 'Processing in progress. This page updates automatically.' ); ?>
            </p>
            <div id="excreet-hermes-processing-result" style="margin-top:16px;"></div>
        </div>
        <?php if ( $state !== 'invalid_job' ) : ?>
        <script>
        (function () {
            var jobId = <?php echo wp_json_encode( $job_id ); ?>;
            var pendingToken = <?php echo wp_json_encode( $pending_token ); ?>;
            var endpointBase = <?php echo wp_json_encode( $public_endpoint_base ); ?>;
            var prestoreEndpoint = <?php echo wp_json_encode( $prestore_endpoint ); ?>;
            var nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
            var messageEl = document.getElementById('excreet-hermes-processing-message');
            var resultEl = document.getElementById('excreet-hermes-processing-result');
            var timeoutAt = Date.now() + (5 * 60 * 1000);
            var intervalMs = 3000;
            var tokenResolveAttempts = 0;
            var maxTokenResolveAttempts = 20;
    
            function safeList(items) {
                if (!Array.isArray(items) || items.length === 0) {
                    return '<p style="margin:0;color:#627d98;line-height:1.6;">No items provided.</p>';
                }
                return '<ul style="margin:0;padding-left:18px;color:#334e68;line-height:1.6;">' +
                    items.map(function(item) {
                        return '<li>' + escapeHtml(String(item)) + '</li>';
                    }).join('') + '</ul>';
            }
    
            function escapeHtml(str) {
                return str.replace(/[&<>"']/g, function(c) {
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
                });
            }
    
            function tierLabel(tier) {
                var labels = { nudge: 'Quick Nudge', checkin: 'Check-In', protocol: 'Protocol Recommended', alarm: 'Attention Needed' };
                return labels[tier] || tier;
            }

            function tierColor(tier) {
                var colors = { nudge: '#137333', checkin: '#b45309', protocol: '#7c3aed', alarm: '#b91c1c' };
                return colors[tier] || '#243b53';
            }

            function tierBg(tier) {
                var bgs = { nudge: '#e3fcec', checkin: '#fef3c7', protocol: '#ede9fe', alarm: '#fee2e2' };
                return bgs[tier] || '#f0f4f8';
            }

            function scoreColor(score) {
                if (score >= 70) return '#137333';
                if (score >= 45) return '#b45309';
                return '#b91c1c';
            }

            /* ── Schema discriminator ── */
            function isPharmaResult(result) {
                return result && (result.memberProfile !== undefined || result.prescribedPharmaceuticals !== undefined);
            }

            /* ── Clinical Pattern Report renderer (pharmaceutical_intake) ── */
            function renderClinicalPattern(result) {
                var mp    = result.memberProfile && typeof result.memberProfile === 'object' ? result.memberProfile : null;
                var drugs = Array.isArray(result.prescribedPharmaceuticals) ? result.prescribedPharmaceuticals : [];
                var flags = Array.isArray(result.redFlagSummary) ? result.redFlagSummary : [];
                var loops = Array.isArray(result.drugInteractionLoops) ? result.drugInteractionLoops : [];
                var labs  = Array.isArray(result.labMarkerTriggers) ? result.labMarkerTriggers : [];
                var sigs  = Array.isArray(result.expectedObservableSignals) ? result.expectedObservableSignals : [];
                var interp  = result.excreetInterpretation ? escapeHtml(String(result.excreetInterpretation)) : '';
                var rec     = result.recommendationSummary ? escapeHtml(String(result.recommendationSummary)) : '';
                var princ   = result.excreetPrinciple ? escapeHtml(String(result.excreetPrinciple)) : '';
                var disc    = result.disclaimer ? escapeHtml(String(result.disclaimer)) : '';

                var riskColors = {
                    HIGH_RISK:     { bg: '#fee2e2', border: '#fca5a5', text: '#b91c1c', label: 'High Risk' },
                    MODERATE_RISK: { bg: '#fef3c7', border: '#fcd34d', text: '#92400e', label: 'Moderate Risk' },
                    AWARENESS:     { bg: '#dbeafe', border: '#93c5fd', text: '#1e40af', label: 'Awareness' }
                };
                var sevColors = {
                    HIGH:     { bg: '#fee2e2', text: '#b91c1c' },
                    MODERATE: { bg: '#fef3c7', text: '#92400e' },
                    LOW:      { bg: '#dcfce7', text: '#166534' }
                };
                var actColors = {
                    Alert:    { bg: '#fee2e2', text: '#b91c1c' },
                    Monitor:  { bg: '#fef3c7', text: '#92400e' },
                    Optimize: { bg: '#d1fae5', text: '#065f46' }
                };

                var html = '';

                /* ── CPR header ── */
                html += '<div style="border-bottom:2px solid #6B2FA0;padding-bottom:12px;margin-bottom:16px;">';
                html += '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">';
                html += '<div><p style="margin:0 0 2px;font-size:11px;font-weight:700;color:#6B2FA0;text-transform:uppercase;letter-spacing:.08em;">Clinical Pattern Report</p>';
                html += '<h3 style="margin:0;font-size:21px;line-height:1.25;color:#102a43;">Pharmaceutical Intelligence</h3></div>';
                if (mp) {
                    html += '<div style="background:#f8f4ff;border:1px solid #e9d5ff;border-radius:8px;padding:8px 12px;font-size:12px;color:#4c1d95;line-height:1.8;">';
                    if (mp.age)              { html += '<div><strong>Age:</strong> ' + escapeHtml(String(mp.age)) + '</div>'; }
                    if (mp.sex)              { html += '<div><strong>Sex:</strong> ' + escapeHtml(String(mp.sex)) + '</div>'; }
                    if (mp.exposureDuration) { html += '<div><strong>Exposure:</strong> ' + escapeHtml(String(mp.exposureDuration)) + '</div>'; }
                    if (mp.assessmentDate)   { html += '<div><strong>Assessment:</strong> ' + escapeHtml(String(mp.assessmentDate)) + '</div>'; }
                    html += '</div>';
                }
                html += '</div></div>';

                /* ── Prescribed pharmaceuticals table ── */
                if (drugs.length > 0) {
                    html += '<div style="margin-bottom:16px;">';
                    html += '<h4 style="margin:0 0 8px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Prescribed Pharmaceuticals</h4>';
                    html += '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
                    html += '<thead><tr style="background:#f8f4ff;"><th style="text-align:left;padding:6px 8px;border-bottom:2px solid #e9d5ff;color:#3D1060;font-size:11px;text-transform:uppercase;">Drug</th><th style="text-align:left;padding:6px 8px;border-bottom:2px solid #e9d5ff;color:#3D1060;font-size:11px;text-transform:uppercase;">Dosage</th><th style="text-align:left;padding:6px 8px;border-bottom:2px solid #e9d5ff;color:#3D1060;font-size:11px;text-transform:uppercase;">Frequency</th></tr></thead>';
                    html += '<tbody>';
                    drugs.forEach(function(d, i) {
                        html += '<tr style="background:' + (i % 2 === 0 ? '#fff' : '#fafbfc') + ';">';
                        html += '<td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#334e68;font-weight:600;">' + escapeHtml(String(d.name || '')) + '</td>';
                        html += '<td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#486581;">' + escapeHtml(String(d.dosage || '')) + '</td>';
                        html += '<td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#486581;">' + escapeHtml(String(d.frequency || '')) + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                }

                /* ── Red flag summary ── */
                if (flags.length > 0) {
                    html += '<div style="margin-bottom:16px;">';
                    html += '<h4 style="margin:0 0 8px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Red Flag Summary</h4>';
                    flags.forEach(function(f) {
                        var lvl = f.level ? String(f.level).toUpperCase() : 'AWARENESS';
                        var rc  = riskColors[lvl] || riskColors.AWARENESS;
                        html += '<div style="margin-bottom:8px;padding:10px 14px;background:' + rc.bg + ';border:1px solid ' + rc.border + ';border-radius:8px;">';
                        html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">';
                        html += '<span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;background:' + rc.text + ';color:#fff;text-transform:uppercase;">' + escapeHtml(rc.label) + '</span>';
                        html += '<strong style="font-size:13px;color:' + rc.text + ';">' + escapeHtml(String(f.title || '')) + '</strong></div>';
                        html += '<p style="margin:0;font-size:13px;color:#334e68;line-height:1.6;">' + escapeHtml(String(f.description || '')) + '</p>';
                        html += '</div>';
                    });
                    html += '</div>';
                }

                /* ── Drug interaction loops ── */
                if (loops.length > 0) {
                    html += '<div style="margin-bottom:16px;">';
                    html += '<h4 style="margin:0 0 8px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Drug Interaction Loops</h4>';
                    loops.forEach(function(lp) {
                        var sev = lp.severity ? String(lp.severity).toUpperCase() : 'MODERATE';
                        var sc  = sevColors[sev] || sevColors.MODERATE;
                        var meds = Array.isArray(lp.medications) ? lp.medications : [];
                        var efx  = Array.isArray(lp.effects) ? lp.effects : [];
                        html += '<div style="margin-bottom:10px;padding:12px 14px;border:1px solid #e9d5ff;border-radius:8px;background:#faf5ff;">';
                        html += '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:6px;">';
                        html += '<strong style="font-size:13px;color:#3D1060;">' + escapeHtml(String(lp.name || '')) + '</strong>';
                        html += '<span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;background:' + sc.bg + ';color:' + sc.text + ';text-transform:uppercase;">' + escapeHtml(sev) + '</span></div>';
                        if (meds.length > 0) { html += '<p style="margin:0 0 4px;font-size:12px;color:#4c1d95;"><strong>Drugs involved:</strong> ' + escapeHtml(meds.map(function(m){return String(m);}).join(', ')) + '</p>'; }
                        if (lp.mechanism) { html += '<p style="margin:0 0 6px;font-size:12px;color:#334e68;line-height:1.6;"><strong>Mechanism:</strong> ' + escapeHtml(String(lp.mechanism)) + '</p>'; }
                        if (efx.length > 0) { html += '<p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#243b53;">Effects to watch:</p>' + safeList(efx); }
                        html += '</div>';
                    });
                    html += '</div>';
                }

                /* ── Lab marker triggers table ── */
                if (labs.length > 0) {
                    html += '<div style="margin-bottom:16px;">';
                    html += '<h4 style="margin:0 0 8px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Lab Marker Triggers</h4>';
                    html += '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
                    html += '<thead><tr style="background:#f0f4f8;">';
                    ['Risk Area','Lab Marker','What It Indicates','Target / Alert','Action'].forEach(function(h) {
                        html += '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #d9e2ec;color:#243b53;font-size:11px;text-transform:uppercase;">' + h + '</th>';
                    });
                    html += '</tr></thead><tbody>';
                    labs.forEach(function(lm, i) {
                        var act = lm.action ? String(lm.action) : 'Monitor';
                        var ac  = actColors[act] || actColors.Monitor;
                        html += '<tr style="background:' + (i % 2 === 0 ? '#fff' : '#f8fafc') + ';">';
                        html += '<td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#334e68;font-weight:600;">' + escapeHtml(String(lm.riskArea || '')) + '</td>';
                        html += '<td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#486581;">' + escapeHtml(String(lm.labMarker || '')) + '</td>';
                        html += '<td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#486581;">' + escapeHtml(String(lm.whatItIndicates || '')) + '</td>';
                        html += '<td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#486581;">' + escapeHtml(String(lm.targetAlertLevel || '')) + '</td>';
                        html += '<td style="padding:6px 8px;border-bottom:1px solid #e6edf3;"><span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;background:' + ac.bg + ';color:' + ac.text + ';">' + escapeHtml(act) + '</span></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                }

                /* ── Expected observable signals ── */
                if (sigs.length > 0) {
                    html += '<div style="margin-bottom:16px;padding:12px 14px;background:#f0f7ff;border-radius:8px;">';
                    html += '<h4 style="margin:0 0 8px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Expected Observable Signals</h4>';
                    html += safeList(sigs);
                    html += '</div>';
                }

                /* ── Excreet interpretation ── */
                if (interp) {
                    html += '<div style="margin-bottom:16px;padding:14px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;">';
                    html += '<h4 style="margin:0 0 6px;font-size:13px;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:.06em;">Excreet Interpretation</h4>';
                    html += '<p style="margin:0;color:#4c1d95;line-height:1.7;">' + interp + '</p>';
                    html += '</div>';
                }

                /* ── Recommendation summary ── */
                if (rec) {
                    html += '<div style="margin-bottom:16px;padding:14px;background:#f0fff4;border:1px solid #bbf7d0;border-radius:8px;">';
                    html += '<h4 style="margin:0 0 6px;font-size:13px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.06em;">Recommendation Summary</h4>';
                    html += '<p style="margin:0;color:#065f46;line-height:1.7;">' + rec + '</p>';
                    html += '</div>';
                }

                /* ── Excreet principle ── */
                if (princ) {
                    html += '<p style="margin:0 0 14px;font-style:italic;color:#6B2FA0;font-size:14px;line-height:1.6;text-align:center;padding:10px 16px;border-top:1px solid #e9d5ff;border-bottom:1px solid #e9d5ff;">' + princ + '</p>';
                }

                /* ── Disclaimer ── */
                if (disc) {
                    html += '<p style="margin:10px 0 0;font-size:12px;color:#829ab1;line-height:1.6;border-top:1px solid #e6edf3;padding-top:12px;">' + disc + '</p>';
                }

                resultEl.innerHTML = html;
                messageEl.textContent = 'Your Clinical Pattern Report is ready.';
            }

            function renderCompleted(result) {
                /* ── Route by schema type ── */
                if (isPharmaResult(result)) {
                    renderClinicalPattern(result);
                    return;
                }

                var tier  = result && result.tier ? result.tier : 'nudge';
                var score = result && typeof result.vitalityScore === 'number' ? result.vitalityScore : 0;
                var tread = result && result.trajectoryRead ? escapeHtml(String(result.trajectoryRead)) : '';
                var quick = result && Array.isArray(result.quickActions) ? result.quickActions : [];
                var med   = result && result.medicalPath && typeof result.medicalPath === 'object' ? result.medicalPath : null;
                var min   = result && result.ministryPath && typeof result.ministryPath === 'object' ? result.ministryPath : null;
                var disc  = result && result.disclaimer ? escapeHtml(String(result.disclaimer)) : '';

                var html = '';

                /* ── Header: tier badge + vitality score ── */
                html += '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">';
                html += '<span style="display:inline-block;padding:5px 12px;border-radius:999px;background:' + tierBg(tier) + ';color:' + tierColor(tier) + ';font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">' + escapeHtml(tierLabel(tier)) + '</span>';
                html += '<div style="text-align:right;"><span style="font-size:13px;color:#627d98;">Vitality Score</span><br><span style="font-size:28px;font-weight:800;color:' + scoreColor(score) + ';">' + score + '</span><span style="font-size:13px;color:#627d98;">&nbsp;/ 100</span></div>';
                html += '</div>';

                /* ── What your body is signaling ── */
                if (tread) {
                    html += '<div style="margin-bottom:16px;padding:14px;background:#f0f7ff;border-radius:8px;">';
                    html += '<h4 style="margin:0 0 6px;font-size:14px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">What Your Body Is Signaling</h4>';
                    html += '<p style="margin:0;color:#334e68;line-height:1.7;">' + tread + '</p>';
                    html += '</div>';
                }

                /* ── Tier 1 quick actions ── */
                if (quick.length > 0) {
                    html += '<div style="margin-bottom:16px;">';
                    html += '<h4 style="margin:0 0 8px;font-size:14px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Immediate Actions</h4>';
                    html += safeList(quick);
                    html += '</div>';
                }

                /* ── Ministry of Healing path ── */
                if (min) {
                    html += '<div style="margin-bottom:16px;padding:14px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;">';
                    html += '<h4 style="margin:0 0 8px;font-size:14px;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:.06em;">Ministry of Healing Path</h4>';
                    if (min.signalCategory) {
                        html += '<p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#7c3aed;">Signal Category: ' + escapeHtml(String(min.signalCategory)) + '</p>';
                    }
                    if (Array.isArray(min.approach) && min.approach.length > 0) {
                        min.approach.forEach(function(line) {
                            html += '<p style="margin:0 0 6px;color:#4c1d95;line-height:1.65;">' + escapeHtml(String(line)) + '</p>';
                        });
                    }
                    if (Array.isArray(min.powerMoves) && min.powerMoves.length > 0) {
                        html += '<h5 style="margin:10px 0 6px;font-size:13px;font-weight:700;color:#5b21b6;text-transform:uppercase;letter-spacing:.05em;">Your Power Moves</h5>';
                        html += safeList(min.powerMoves);
                    }
                    html += '</div>';
                }

                /* ── Medical navigation path ── */
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

                /* ── Disclaimer ── */
                if (disc) {
                    html += '<p style="margin:12px 0 0;font-size:12px;color:#829ab1;line-height:1.6;border-top:1px solid #e6edf3;padding-top:12px;">' + disc + '</p>';
                }

                resultEl.innerHTML = html;
                messageEl.textContent = 'Your Excreet Intelligence is ready.';
            }
    
            function setMessage(text) {
                messageEl.textContent = text;
            }
    
            // v2.7.1: Poll /resolve-token instead of reloading the page.
            // Checks transient → user meta → Forminator DB → option store.
            // When resolved, jobId is set and regular Hermes poll() begins.
            function pollForTokenResolution() {
                if (tokenResolveAttempts >= maxTokenResolveAttempts) {
                    setMessage('Processing is taking longer than expected. Please refresh this page in a few minutes.');
                    return;
                }

                tokenResolveAttempts++;
                setMessage('Connecting to your intake session... (' + tokenResolveAttempts + ')');

                var resolveUrl = '/wp-json/excreet/v1/resolve-token'
                    + '?token=' + encodeURIComponent(pendingToken)
                    + '&_wpnonce=' + encodeURIComponent(nonce);

                fetch(resolveUrl, { method: 'GET' })
                    .then(function(response) { return response.json().catch(function() { return {}; }); })
                    .then(function(data) {
                        if (data && data.resolved && data.jobId) {
                            jobId = data.jobId;
                            setMessage('Your intake is being processed.');
                            poll();
                        } else {
                            setTimeout(pollForTokenResolution, intervalMs);
                        }
                    })
                    .catch(function() {
                        setTimeout(pollForTokenResolution, intervalMs);
                    });
            }
    
            function poll() {
                if (Date.now() >= timeoutAt) {
                    setMessage('Processing is taking longer than expected. Please refresh this page in a few minutes.');
                    return;
                }
    
                fetch(endpointBase + encodeURIComponent(jobId), { method: 'GET' })
                    .then(function(response) { return response.json().catch(function() { return {}; }); })
                    .then(function(data) {
                        var status = data && data.status ? String(data.status) : '';
    
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
    
            // v2.6.6: Start correct flow based on state
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
    
    // ============================================================================
    // MEMBER RESULT DISPLAY SHORTCODE
    // ============================================================================
    
    add_shortcode( 'excreet_hermes_latest_result', 'excreet_shortcode_latest_result' );
    
    function excreet_shortcode_latest_result(): string {
    
        $record = excreet_get_latest_completed_result_record();
    
        if ( empty( $record ) ) {
            return excreet_render_result_empty_state();
        }
    
        return excreet_render_completed_result_card( $record );
    }
    
    function excreet_get_latest_completed_result_record(): array {
    
        $current_user_id = get_current_user_id();
    
        if ( $current_user_id > 0 ) {
            $user_result = json_decode( (string) get_user_meta( $current_user_id, 'excreet_hermes_completed_result', true ), true );
    
            if ( is_array( $user_result ) && ! empty( $user_result ) ) {
                // Data was stored via excreet_sanitize_result_payload() — pass through as-is
                // and append the retrieval-time fields.
                $user_result['status'] = 'completed';
                return $user_result;
            }
        }
    
        $storage_key = sanitize_key( (string) filter_input( INPUT_GET, 'storage_key', FILTER_SANITIZE_SPECIAL_CHARS ) );
    
        if ( $storage_key === '' ) {
            return [];
        }
    
        $stored = excreet_read_job_record_by_storage_key( $storage_key );
        $record = isset( $stored['record'] ) && is_array( $stored['record'] ) ? $stored['record'] : [];
    
        if ( empty( $record['completed_result'] ) || ! is_array( $record['completed_result'] ) ) {
            return [];
        }
    
        $completed_result = $record['completed_result'];
        $status_value     = isset( $record['hermes_status'] ) ? sanitize_key( (string) $record['hermes_status'] ) : '';
    
        if ( $status_value !== 'completed' ) {
            return [];
        }
    
        // Data was stored via excreet_sanitize_result_payload() — pass through as-is.
        $completed_result['completed_at'] = isset( $record['completed_at'] ) ? sanitize_text_field( (string) $record['completed_at'] ) : '';
        $completed_result['status']       = 'completed';
        return $completed_result;
    }
    
    function excreet_render_result_empty_state(): string {
    
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
    
    function excreet_render_completed_result_card( array $record ): string {

        // Route pharmaceutical results to the Clinical Pattern Report renderer.
        if ( ! empty( $record['schema_type'] ) && $record['schema_type'] === 'pharmaceutical' ) {
            return excreet_render_clinical_pattern_card( $record );
        }
        // Also detect by key presence for records stored before schema_type was added.
        if ( isset( $record['memberProfile'] ) || isset( $record['prescribedPharmaceuticals'] ) ) {
            return excreet_render_clinical_pattern_card( $record );
        }
    
        $tier           = isset( $record['tier'] ) ? sanitize_key( (string) $record['tier'] ) : 'nudge';
        $vitality_score = isset( $record['vitalityScore'] ) ? (int) $record['vitalityScore'] : 0;
        $trajectory     = isset( $record['trajectoryRead'] ) ? (string) $record['trajectoryRead'] : '';
        $quick_actions  = isset( $record['quickActions'] ) && is_array( $record['quickActions'] ) ? $record['quickActions'] : [];
        $medical_path   = isset( $record['medicalPath'] ) && is_array( $record['medicalPath'] ) ? $record['medicalPath'] : null;
        $ministry_path  = isset( $record['ministryPath'] ) && is_array( $record['ministryPath'] ) ? $record['ministryPath'] : null;
        $disclaimer     = isset( $record['disclaimer'] ) ? (string) $record['disclaimer'] : '';
        $completed_at   = isset( $record['completed_at'] ) ? sanitize_text_field( (string) $record['completed_at'] ) : '';
    
        $completed_timestamp = $completed_at !== '' ? strtotime( $completed_at ) : false;
        $completed_label     = $completed_timestamp ? gmdate( 'F j, Y g:i A T', $completed_timestamp ) : 'Not available';
    
        $tier_labels = [ 'nudge' => 'Quick Nudge', 'checkin' => 'Check-In', 'protocol' => 'Protocol Recommended', 'alarm' => 'Attention Needed' ];
        $tier_colors = [ 'nudge' => '#137333', 'checkin' => '#b45309', 'protocol' => '#7c3aed', 'alarm' => '#b91c1c' ];
        $tier_bgs    = [ 'nudge' => '#e3fcec', 'checkin' => '#fef3c7', 'protocol' => '#ede9fe', 'alarm' => '#fee2e2' ];
    
        $tier_label = isset( $tier_labels[ $tier ] ) ? $tier_labels[ $tier ] : $tier;
        $tier_color = isset( $tier_colors[ $tier ] ) ? $tier_colors[ $tier ] : '#243b53';
        $tier_bg    = isset( $tier_bgs[ $tier ] ) ? $tier_bgs[ $tier ] : '#f0f4f8';
    
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
    
            <?php /* ── Header ── */ ?>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:6px;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:20px;line-height:1.3;color:#102a43;">Your Excreet Intelligence</h3>
                    <span style="display:inline-block;padding:4px 12px;border-radius:999px;background:<?php echo esc_attr( $tier_bg ); ?>;color:<?php echo esc_attr( $tier_color ); ?>;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.05em;"><?php echo esc_html( $tier_label ); ?></span>
                </div>
                <div style="text-align:right;">
                    <span style="font-size:13px;color:#627d98;">Vitality Score</span><br>
                    <span style="font-size:32px;font-weight:800;color:<?php echo esc_attr( $score_color ); ?>;"><?php echo esc_html( (string) $vitality_score ); ?></span>
                    <span style="font-size:14px;color:#627d98;">&thinsp;/ 100</span>
                </div>
            </div>
            <p style="margin:0 0 16px;color:#627d98;font-size:12px;">Completed: <?php echo esc_html( $completed_label ); ?></p>
    
            <?php /* ── Trajectory read ── */ ?>
            <?php if ( $trajectory !== '' ) : ?>
            <div style="margin-bottom:16px;padding:14px;background:#f0f7ff;border-radius:8px;">
                <h4 style="margin:0 0 6px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">What Your Body Is Signaling</h4>
                <p style="margin:0;color:#334e68;line-height:1.7;"><?php echo esc_html( $trajectory ); ?></p>
            </div>
            <?php endif; ?>
    
            <?php /* ── Quick actions (Tier 1 nudge) ── */ ?>
            <?php if ( ! empty( $quick_actions ) ) : ?>
            <div style="margin-bottom:16px;">
                <h4 style="margin:0 0 8px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Immediate Actions</h4>
                <?php echo excreet_render_result_list_section( '', $quick_actions ); ?>
            </div>
            <?php endif; ?>
    
            <?php /* ── Ministry of Healing path ── */ ?>
            <?php if ( $ministry_path !== null ) : ?>
            <div style="margin-bottom:16px;padding:14px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;">
                <h4 style="margin:0 0 8px;font-size:13px;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:.06em;">Ministry of Healing Path</h4>
                <?php if ( ! empty( $ministry_path['signalCategory'] ) ) : ?>
                    <p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#7c3aed;">Signal Category: <?php echo esc_html( (string) $ministry_path['signalCategory'] ); ?></p>
                <?php endif; ?>
                <?php if ( ! empty( $ministry_path['approach'] ) && is_array( $ministry_path['approach'] ) ) : ?>
                    <?php foreach ( $ministry_path['approach'] as $line ) : ?>
                        <p style="margin:0 0 6px;color:#4c1d95;line-height:1.65;"><?php echo esc_html( (string) $line ); ?></p>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ( ! empty( $ministry_path['powerMoves'] ) && is_array( $ministry_path['powerMoves'] ) ) : ?>
                    <h5 style="margin:10px 0 6px;font-size:12px;font-weight:700;color:#5b21b6;text-transform:uppercase;letter-spacing:.05em;">Your Power Moves</h5>
                    <?php echo excreet_render_result_list_section( '', $ministry_path['powerMoves'] ); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
    
            <?php /* ── Medical navigation path ── */ ?>
            <?php if ( $medical_path !== null ) : ?>
            <div style="margin-bottom:16px;padding:14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;">
                <h4 style="margin:0 0 10px;font-size:13px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;">Navigating the Medical System</h4>
                <?php if ( ! empty( $medical_path['questionsToAsk'] ) && is_array( $medical_path['questionsToAsk'] ) ) : ?>
                    <h5 style="margin:0 0 6px;font-size:12px;font-weight:700;color:#78350f;">Questions to Bring</h5>
                    <?php echo excreet_render_result_list_section( '', $medical_path['questionsToAsk'] ); ?>
                <?php endif; ?>
                <?php if ( ! empty( $medical_path['labTestsToRequest'] ) && is_array( $medical_path['labTestsToRequest'] ) ) : ?>
                    <h5 style="margin:10px 0 6px;font-size:12px;font-weight:700;color:#78350f;">Lab Tests to Request by Name</h5>
                    <?php echo excreet_render_result_list_section( '', $medical_path['labTestsToRequest'] ); ?>
                <?php endif; ?>
                <?php if ( ! empty( $medical_path['redFlagsToWatch'] ) && is_array( $medical_path['redFlagsToWatch'] ) ) : ?>
                    <h5 style="margin:10px 0 6px;font-size:12px;font-weight:700;color:#b91c1c;">Red Flags — Seek Urgent Care If You Notice</h5>
                    <?php echo excreet_render_result_list_section( '', $medical_path['redFlagsToWatch'] ); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
    
            <?php /* ── Disclaimer ── */ ?>
            <?php if ( $disclaimer !== '' ) : ?>
            <p style="margin:12px 0 0;font-size:12px;color:#829ab1;line-height:1.6;border-top:1px solid #e6edf3;padding-top:12px;"><?php echo esc_html( $disclaimer ); ?></p>
            <?php endif; ?>
    
        </div>
        <?php
        return (string) ob_get_clean();
    }
    
    function excreet_render_result_list_section( string $title, array $items ): string {
    
        ob_start();
        ?>
        <div style="margin-top:14px;">
            <h4 style="margin:0 0 6px;font-size:15px;color:#243b53;"><?php echo esc_html( $title ); ?></h4>
            <?php if ( empty( $items ) ) : ?>
                <p style="margin:0;color:#627d98;line-height:1.6;">No <?php echo esc_html( strtolower( $title ) ); ?> provided.</p>
            <?php else : ?>
                <ul style="margin:0;padding-left:18px;color:#334e68;line-height:1.6;">
                    <?php foreach ( $items as $item ) : ?>
                        <li><?php echo esc_html( (string) $item ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    // ============================================================================
    // CLINICAL PATTERN REPORT RENDERER (pharmaceutical_intake schema)
    // ============================================================================

    /**
     * Renders the full Clinical Pattern Report card for a pharmaceutical_intake result.
     * Mirrors the branded CPR design: member profile → red flags → drug interactions
     * → lab marker triggers → observable signals → Excreet interpretation → principle.
     *
     * @param array $record Sanitized pharma result array from excreet_sanitize_pharma_result().
     */
    function excreet_render_clinical_pattern_card( array $record ): string {

        $member_profile   = isset( $record['memberProfile'] ) && is_array( $record['memberProfile'] ) ? $record['memberProfile'] : [];
        $pharmaceuticals  = isset( $record['prescribedPharmaceuticals'] ) && is_array( $record['prescribedPharmaceuticals'] ) ? $record['prescribedPharmaceuticals'] : [];
        $red_flags        = isset( $record['redFlagSummary'] ) && is_array( $record['redFlagSummary'] ) ? $record['redFlagSummary'] : [];
        $interactions     = isset( $record['drugInteractionLoops'] ) && is_array( $record['drugInteractionLoops'] ) ? $record['drugInteractionLoops'] : [];
        $lab_markers      = isset( $record['labMarkerTriggers'] ) && is_array( $record['labMarkerTriggers'] ) ? $record['labMarkerTriggers'] : [];
        $signals          = isset( $record['expectedObservableSignals'] ) && is_array( $record['expectedObservableSignals'] ) ? $record['expectedObservableSignals'] : [];
        $interpretation   = isset( $record['excreetInterpretation'] ) ? (string) $record['excreetInterpretation'] : '';
        $recommendation   = isset( $record['recommendationSummary'] ) ? (string) $record['recommendationSummary'] : '';
        $principle        = isset( $record['excreetPrinciple'] ) ? (string) $record['excreetPrinciple'] : '';
        $disclaimer       = isset( $record['disclaimer'] ) ? (string) $record['disclaimer'] : '';
        $completed_at     = isset( $record['completed_at'] ) ? sanitize_text_field( (string) $record['completed_at'] ) : '';

        $completed_ts    = $completed_at !== '' ? strtotime( $completed_at ) : false;
        $completed_label = $completed_ts ? gmdate( 'F j, Y g:i A T', $completed_ts ) : 'Not available';

        $risk_colors = [
            'HIGH_RISK'     => [ 'bg' => '#fee2e2', 'border' => '#fca5a5', 'text' => '#b91c1c', 'label' => 'High Risk' ],
            'MODERATE_RISK' => [ 'bg' => '#fef3c7', 'border' => '#fcd34d', 'text' => '#92400e', 'label' => 'Moderate Risk' ],
            'AWARENESS'     => [ 'bg' => '#dbeafe', 'border' => '#93c5fd', 'text' => '#1e40af', 'label' => 'Awareness' ],
        ];

        $severity_colors = [
            'HIGH'     => [ 'bg' => '#fee2e2', 'text' => '#b91c1c' ],
            'MODERATE' => [ 'bg' => '#fef3c7', 'text' => '#92400e' ],
            'LOW'      => [ 'bg' => '#dcfce7', 'text' => '#166534' ],
        ];

        $action_colors = [
            'Alert'    => [ 'bg' => '#fee2e2', 'text' => '#b91c1c' ],
            'Monitor'  => [ 'bg' => '#fef3c7', 'text' => '#92400e' ],
            'Optimize' => [ 'bg' => '#d1fae5', 'text' => '#065f46' ],
        ];

        ob_start();
        ?>
        <div class="excreet-hermes-card excreet-cpr" style="border:1px solid #d9e2ec;border-radius:12px;padding:20px;background:#ffffff;max-width:760px;">

            <?php /* ── Header ── */ ?>
            <div style="border-bottom:2px solid #6B2FA0;padding-bottom:14px;margin-bottom:18px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
                    <div>
                        <p style="margin:0 0 2px;font-size:11px;font-weight:700;color:#6B2FA0;text-transform:uppercase;letter-spacing:.08em;">Clinical Pattern Report</p>
                        <h3 style="margin:0 0 4px;font-size:22px;line-height:1.25;color:#102a43;">Pharmaceutical Intelligence</h3>
                        <p style="margin:0;font-size:12px;color:#829ab1;">Completed: <?php echo esc_html( $completed_label ); ?></p>
                    </div>
                    <?php if ( ! empty( $member_profile ) ) : ?>
                    <div style="background:#f8f4ff;border:1px solid #e9d5ff;border-radius:8px;padding:10px 14px;font-size:12px;color:#4c1d95;line-height:1.8;">
                        <?php if ( ! empty( $member_profile['age'] ) ) : ?>
                            <div><strong>Age:</strong> <?php echo esc_html( $member_profile['age'] ); ?></div>
                        <?php endif; ?>
                        <?php if ( ! empty( $member_profile['sex'] ) ) : ?>
                            <div><strong>Sex:</strong> <?php echo esc_html( $member_profile['sex'] ); ?></div>
                        <?php endif; ?>
                        <?php if ( ! empty( $member_profile['exposureDuration'] ) ) : ?>
                            <div><strong>Exposure:</strong> <?php echo esc_html( $member_profile['exposureDuration'] ); ?></div>
                        <?php endif; ?>
                        <?php if ( ! empty( $member_profile['assessmentDate'] ) ) : ?>
                            <div><strong>Assessment:</strong> <?php echo esc_html( $member_profile['assessmentDate'] ); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php /* ── Prescribed Pharmaceuticals ── */ ?>
            <?php if ( ! empty( $pharmaceuticals ) ) : ?>
            <div style="margin-bottom:18px;">
                <h4 style="margin:0 0 10px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Prescribed Pharmaceuticals</h4>
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#f8f4ff;">
                            <th style="text-align:left;padding:7px 10px;border-bottom:2px solid #e9d5ff;color:#3D1060;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Drug</th>
                            <th style="text-align:left;padding:7px 10px;border-bottom:2px solid #e9d5ff;color:#3D1060;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Dosage</th>
                            <th style="text-align:left;padding:7px 10px;border-bottom:2px solid #e9d5ff;color:#3D1060;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Frequency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $pharmaceuticals as $i => $drug ) : ?>
                        <tr style="background:<?php echo $i % 2 === 0 ? '#ffffff' : '#fafbfc'; ?>;">
                            <td style="padding:7px 10px;border-bottom:1px solid #e6edf3;color:#334e68;font-weight:600;"><?php echo esc_html( (string) ( $drug['name'] ?? '' ) ); ?></td>
                            <td style="padding:7px 10px;border-bottom:1px solid #e6edf3;color:#486581;"><?php echo esc_html( (string) ( $drug['dosage'] ?? '' ) ); ?></td>
                            <td style="padding:7px 10px;border-bottom:1px solid #e6edf3;color:#486581;"><?php echo esc_html( (string) ( $drug['frequency'] ?? '' ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php /* ── Red Flag Summary ── */ ?>
            <?php if ( ! empty( $red_flags ) ) : ?>
            <div style="margin-bottom:18px;">
                <h4 style="margin:0 0 10px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Red Flag Summary</h4>
                <?php foreach ( $red_flags as $flag ) :
                    $level = isset( $flag['level'] ) ? strtoupper( (string) $flag['level'] ) : 'AWARENESS';
                    $rc    = isset( $risk_colors[ $level ] ) ? $risk_colors[ $level ] : $risk_colors['AWARENESS'];
                ?>
                <div style="margin-bottom:8px;padding:10px 14px;background:<?php echo esc_attr( $rc['bg'] ); ?>;border:1px solid <?php echo esc_attr( $rc['border'] ); ?>;border-radius:8px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;background:<?php echo esc_attr( $rc['text'] ); ?>;color:#fff;text-transform:uppercase;letter-spacing:.05em;"><?php echo esc_html( $rc['label'] ); ?></span>
                        <strong style="font-size:13px;color:<?php echo esc_attr( $rc['text'] ); ?>;"><?php echo esc_html( (string) ( $flag['title'] ?? '' ) ); ?></strong>
                    </div>
                    <p style="margin:0;font-size:13px;color:#334e68;line-height:1.6;"><?php echo esc_html( (string) ( $flag['description'] ?? '' ) ); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php /* ── Drug Interaction Loops ── */ ?>
            <?php if ( ! empty( $interactions ) ) : ?>
            <div style="margin-bottom:18px;">
                <h4 style="margin:0 0 10px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Drug Interaction Loops</h4>
                <?php foreach ( $interactions as $loop ) :
                    $sev = isset( $loop['severity'] ) ? strtoupper( (string) $loop['severity'] ) : 'MODERATE';
                    $sc  = isset( $severity_colors[ $sev ] ) ? $severity_colors[ $sev ] : $severity_colors['MODERATE'];
                    $meds = isset( $loop['medications'] ) && is_array( $loop['medications'] ) ? $loop['medications'] : [];
                    $effects = isset( $loop['effects'] ) && is_array( $loop['effects'] ) ? $loop['effects'] : [];
                ?>
                <div style="margin-bottom:10px;padding:12px 14px;border:1px solid #e9d5ff;border-radius:8px;background:#faf5ff;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:6px;">
                        <strong style="font-size:13px;color:#3D1060;"><?php echo esc_html( (string) ( $loop['name'] ?? '' ) ); ?></strong>
                        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;background:<?php echo esc_attr( $sc['bg'] ); ?>;color:<?php echo esc_attr( $sc['text'] ); ?>;text-transform:uppercase;"><?php echo esc_html( $sev ); ?></span>
                    </div>
                    <?php if ( ! empty( $meds ) ) : ?>
                        <p style="margin:0 0 4px;font-size:12px;color:#4c1d95;"><strong>Drugs involved:</strong> <?php echo esc_html( implode( ', ', array_map( 'strval', $meds ) ) ); ?></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $loop['mechanism'] ) ) : ?>
                        <p style="margin:0 0 6px;font-size:12px;color:#334e68;line-height:1.6;"><strong>Mechanism:</strong> <?php echo esc_html( (string) $loop['mechanism'] ); ?></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $effects ) ) : ?>
                        <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#243b53;">Effects to watch:</p>
                        <ul style="margin:0;padding-left:16px;color:#334e68;font-size:12px;line-height:1.6;">
                            <?php foreach ( $effects as $effect ) : ?>
                                <li><?php echo esc_html( (string) $effect ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php /* ── Lab Marker Triggers ── */ ?>
            <?php if ( ! empty( $lab_markers ) ) : ?>
            <div style="margin-bottom:18px;">
                <h4 style="margin:0 0 10px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Lab Marker Triggers</h4>
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="background:#f0f4f8;">
                            <th style="text-align:left;padding:6px 8px;border-bottom:2px solid #d9e2ec;color:#243b53;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Risk Area</th>
                            <th style="text-align:left;padding:6px 8px;border-bottom:2px solid #d9e2ec;color:#243b53;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Lab Marker</th>
                            <th style="text-align:left;padding:6px 8px;border-bottom:2px solid #d9e2ec;color:#243b53;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">What It Indicates</th>
                            <th style="text-align:left;padding:6px 8px;border-bottom:2px solid #d9e2ec;color:#243b53;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Target / Alert</th>
                            <th style="text-align:left;padding:6px 8px;border-bottom:2px solid #d9e2ec;color:#243b53;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $lab_markers as $i => $marker ) :
                            $action = isset( $marker['action'] ) ? (string) $marker['action'] : 'Monitor';
                            $ac     = isset( $action_colors[ $action ] ) ? $action_colors[ $action ] : $action_colors['Monitor'];
                        ?>
                        <tr style="background:<?php echo $i % 2 === 0 ? '#ffffff' : '#f8fafc'; ?>;">
                            <td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#334e68;font-weight:600;"><?php echo esc_html( (string) ( $marker['riskArea'] ?? '' ) ); ?></td>
                            <td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#486581;"><?php echo esc_html( (string) ( $marker['labMarker'] ?? '' ) ); ?></td>
                            <td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#486581;"><?php echo esc_html( (string) ( $marker['whatItIndicates'] ?? '' ) ); ?></td>
                            <td style="padding:6px 8px;border-bottom:1px solid #e6edf3;color:#486581;"><?php echo esc_html( (string) ( $marker['targetAlertLevel'] ?? '' ) ); ?></td>
                            <td style="padding:6px 8px;border-bottom:1px solid #e6edf3;">
                                <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;background:<?php echo esc_attr( $ac['bg'] ); ?>;color:<?php echo esc_attr( $ac['text'] ); ?>;"><?php echo esc_html( $action ); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php /* ── Expected Observable Signals ── */ ?>
            <?php if ( ! empty( $signals ) ) : ?>
            <div style="margin-bottom:18px;padding:12px 14px;background:#f0f7ff;border-radius:8px;">
                <h4 style="margin:0 0 8px;font-size:13px;font-weight:700;color:#243b53;text-transform:uppercase;letter-spacing:.06em;">Expected Observable Signals</h4>
                <ul style="margin:0;padding-left:18px;color:#334e68;font-size:13px;line-height:1.7;">
                    <?php foreach ( $signals as $signal ) : ?>
                        <li><?php echo esc_html( (string) $signal ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php /* ── Excreet Interpretation ── */ ?>
            <?php if ( $interpretation !== '' ) : ?>
            <div style="margin-bottom:18px;padding:14px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;">
                <h4 style="margin:0 0 6px;font-size:13px;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:.06em;">Excreet Interpretation</h4>
                <p style="margin:0;color:#4c1d95;line-height:1.7;"><?php echo esc_html( $interpretation ); ?></p>
            </div>
            <?php endif; ?>

            <?php /* ── Recommendation Summary ── */ ?>
            <?php if ( $recommendation !== '' ) : ?>
            <div style="margin-bottom:18px;padding:14px;background:#f0fff4;border:1px solid #bbf7d0;border-radius:8px;">
                <h4 style="margin:0 0 6px;font-size:13px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.06em;">Recommendation Summary</h4>
                <p style="margin:0;color:#065f46;line-height:1.7;"><?php echo esc_html( $recommendation ); ?></p>
            </div>
            <?php endif; ?>

            <?php /* ── Excreet Principle ── */ ?>
            <?php if ( $principle !== '' ) : ?>
            <p style="margin:0 0 14px;font-style:italic;color:#6B2FA0;font-size:14px;line-height:1.6;text-align:center;padding:10px 16px;border-top:1px solid #e9d5ff;border-bottom:1px solid #e9d5ff;"><?php echo esc_html( $principle ); ?></p>
            <?php endif; ?>

            <?php /* ── Disclaimer ── */ ?>
            <?php if ( $disclaimer !== '' ) : ?>
            <p style="margin:10px 0 0;font-size:12px;color:#829ab1;line-height:1.6;border-top:1px solid #e6edf3;padding-top:12px;"><?php echo esc_html( $disclaimer ); ?></p>
            <?php endif; ?>

        </div>
        <?php
        return (string) ob_get_clean();
    }
    
    // ============================================================================
    // LOGGING
    // ============================================================================
    
    function excreet_log( string $message ): void {
        $line = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] [Excreet Hermes v2.7.0] ' . $message . PHP_EOL;
        error_log( $line, 3, WP_CONTENT_DIR . '/debug.log' );
    }
    
    // ============================================================================
    // v2.6.6 TOKEN INJECTOR
    // Restricted to intake form page only.
    // PRE-STORES token server-side via AJAX on page load.
    // Also injects into hidden-5 field as backup.
    // ============================================================================
    
    add_action( 'wp_footer', 'excreet_inject_handoff_token_field', 99 );
    
    function excreet_inject_handoff_token_field(): void {
    
        // FIX 1: Only run on intake form page
        $slug = defined( 'EXCREET_INTAKE_FORM_SLUG' ) ? EXCREET_INTAKE_FORM_SLUG : 'member-intake-form';
    
        if ( ! is_page( $slug ) ) {
            return;
        }
    
        $token = '';
    
        try {
            $token = bin2hex( random_bytes( 16 ) );
        } catch ( Exception $exception ) {
            return;
        }
    
        $prestore_url = rest_url( 'excreet/v1/prestore-token' );
        $nonce        = wp_create_nonce( 'wp_rest' );
    
        ?>
        <script>
        (function () {
            var token = <?php echo wp_json_encode( $token ); ?>;
            var prestoreUrl = <?php echo wp_json_encode( $prestore_url ); ?>;
            var nonce = <?php echo wp_json_encode( $nonce ); ?>;
    
            if (!token || !/^[0-9a-f]{32}$/.test(token)) {
                return;
            }
    
            // v2.6.6 FIX 1: PRE-STORE TOKEN SERVER-SIDE IMMEDIATELY ON PAGE LOAD
            // This is the key fix - token is stored BEFORE form submission
            // So even if Forminator clears the hidden field, token is already safe
            function prestoreToken() {
                fetch(prestoreUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ token: token })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        console.log('[Excreet v2.6.6] Token pre-stored server-side successfully');
                    } else {
                        console.warn('[Excreet v2.6.6] Token pre-store warning:', data);
                    }
                })
                .catch(function(err) {
                    console.warn('[Excreet v2.6.6] Token pre-store request failed:', err);
                });
            }
    
            // Pre-store immediately on page load
            prestoreToken();

            // v2.7.0 FIX: Write token to a first-party cookie so the processing page
            // can retrieve it even if Forminator clears the hidden field before redirect.
            document.cookie = 'excreet_rt=' + token + '; path=/; max-age=1800; samesite=strict' + (location.protocol === 'https:' ? '; secure' : '');
    
            // Also inject into hidden field as backup
            function injectToken() {
                var field = document.querySelector('input[name="hidden-5"]');
                if (field && field.value !== token) {
                    field.value = token;
                }
            }
    
            var observer = null;
            var observerScheduled = false;
            var eventsBound = false;
    
            function scheduleInjection() {
                if (observerScheduled) { return; }
                observerScheduled = true;
                window.setTimeout(function() {
                    observerScheduled = false;
                    injectToken();
                }, 0);
            }
    
            function bindForminatorStepEvents() {
                if (eventsBound) { return; }
                eventsBound = true;
    
                if (window.jQuery && typeof window.jQuery === 'function') {
                    window.jQuery(document).on('forminator:form:page:changed', function() {
                        scheduleInjection();
                    });
                }
    
                document.addEventListener('click', function(event) {
                    var target = event.target;
                    if (!target || typeof target.closest !== 'function') { return; }
                    var btn = target.closest('.forminator-button-next, .forminator-button-back, .forminator-pagination--next, .forminator-pagination--prev');
                    if (btn) { scheduleInjection(); }
                }, true);
    
                document.addEventListener('change', function(event) {
                    var target = event.target;
                    if (!target || typeof target.closest !== 'function') { return; }
                    if (target.closest('.forminator-pagination, .forminator-page')) { scheduleInjection(); }
                }, true);
    
                // FIX 5: Re-inject right before AJAX submit
                document.addEventListener('submit', function() { injectToken(); }, true);
    
                if (window.jQuery && typeof window.jQuery === 'function') {
                    window.jQuery(document).on('before.submit.forminator', function() {
                        injectToken();
                    });
                }
            }
    
            function startObserver() {
                if (observer || !window.MutationObserver) { return; }
                observer = new MutationObserver(function() { scheduleInjection(); });
                observer.observe(document.body, { childList: true, subtree: true });
            }
    
            injectToken();
            bindForminatorStepEvents();
    
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    injectToken();
                    startObserver();
                });
                return;
            }
    
            startObserver();
        })();
        </script>
        <?php
    }