<?php
/**
 * Dev Tooling Bootstrap
 *
 * Loaded ONLY in WP-CLI context — zero overhead on web or admin requests.
 * Registers WP-CLI command group: wp dp-b2b
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/dev/class-dev-catalog-generator.php';

WP_CLI::add_command( 'dp-b2b', Dreampoint_B2B_Dev_Catalog_Generator::class );
