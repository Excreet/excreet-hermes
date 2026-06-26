<?php
/**
 * Plugin Name: Excreet Hermes Patch 2.7.1 (SUPERSEDED — no-op)
 * Description: Superseded by excreet-hermes-patch-272.php.
 *              This file is intentionally a no-op to prevent PHP fatal errors
 *              from duplicate function declarations. Both patches defined the
 *              same function names (excreet_patch_store_entry_token, etc.).
 *              Patch-272 is the authoritative version and must be the only
 *              active copy of these functions on the server.
 * Version:     2.7.1-superseded
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// No-op: all token-resolution logic lives in excreet-hermes-patch-272.php.
