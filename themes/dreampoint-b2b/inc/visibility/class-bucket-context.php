<?php
/**
 * Bucket Context
 *
 * Immutable value object representing the resolved ACF rule fields for one dp_bucket CPT post.
 * This is the Tier-1 cache payload — bucket rules before user overrides are applied.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dreampoint_B2B_Bucket_Context {

	public function __construct(
		public readonly string $access_type,
		public readonly array  $allowed_brand_ids    = [],
		public readonly array  $allowed_cat_ids      = [],
		public readonly array  $explicit_include_ids = [],
		public readonly array  $explicit_exclude_ids = [],
		/** Reserved for V2 variation-level filtering — always [] in V1. */
		public readonly array  $variation_rules      = [],
	) {}
}
