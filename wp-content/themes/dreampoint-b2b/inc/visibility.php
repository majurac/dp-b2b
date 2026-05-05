<?php
/**
 * Visibility System Loader
 *
 * Required from functions.php inside the class_exists('WooCommerce') block.
 * Loads all visibility classes, wires hooks, and ensures the custom table exists.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/visibility/class-visibility-context.php';
require __DIR__ . '/visibility/class-bucket-context.php';
require __DIR__ . '/visibility/class-visibility-engine.php';
require __DIR__ . '/visibility/class-access-table.php';
require __DIR__ . '/visibility/class-bucket-cpt.php';
require __DIR__ . '/visibility/class-query-filter.php';
require __DIR__ . '/visibility/class-access-guard.php';

/**
 * Returns the shared Visibility_Engine instance (request-scoped singleton).
 * Used by Bucket_CPT and any future code that needs to invalidate caches.
 */
function dreampoint_b2b_visibility_engine(): Dreampoint_B2B_Visibility_Engine {
	static $instance;
	$instance ??= new Dreampoint_B2B_Visibility_Engine();
	return $instance;
}

// Wire up hooks.
( new Dreampoint_B2B_Bucket_CPT() )->register_hooks();
( new Dreampoint_B2B_Query_Filter( dreampoint_b2b_visibility_engine() ) )->register_hooks();
( new Dreampoint_B2B_Access_Guard( dreampoint_b2b_visibility_engine() ) )->register_hooks();

// Ensure custom table exists. install() is a no-op after first run (option guard).
add_action( 'after_switch_theme', [ 'Dreampoint_B2B_Access_Table', 'install' ] );
add_action( 'admin_init',         [ 'Dreampoint_B2B_Access_Table', 'install' ] );

// Phase 3: increment override_version in object cache whenever the access table changes.
// This causes the versioned Redis context key to change → cached context is orphaned.
add_action( 'dp_access_table_changed', [ 'Dreampoint_B2B_Visibility_Engine', 'increment_override_version' ] );

if ( is_admin() ) {
	require __DIR__ . '/custom-offer-admin.php';
}
