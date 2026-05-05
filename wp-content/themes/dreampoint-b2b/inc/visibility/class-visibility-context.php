<?php
/**
 * Visibility Context
 *
 * Immutable value object representing the compiled per-user visibility rule set.
 * Built by Dreampoint_B2B_Visibility_Engine and consumed by enforcement hooks.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dreampoint_B2B_Visibility_Context {

	public const ACCESS_FULL  = 'full_access';
	public const ACCESS_RULE  = 'rule_based';
	public const ACCESS_OFFER = 'custom_offer';
	public const ACCESS_NONE  = 'no_access';

	public function __construct(
		public readonly string $access_type,
		public readonly array  $allowed_brand_ids    = [],
		public readonly array  $allowed_cat_ids      = [],
		public readonly array  $explicit_include_ids = [],
		public readonly array  $explicit_exclude_ids = [],
		public readonly array  $override_add         = [],
		public readonly array  $override_remove      = [],
		/** Reserved for V2 variation-level filtering — always [] in V1. */
		public readonly array  $variation_rules      = [],
	) {}

	public static function no_access(): self {
		return new self( self::ACCESS_NONE );
	}

	public function is_full_access(): bool {
		return self::ACCESS_FULL === $this->access_type;
	}

	public function is_rule_based(): bool {
		return self::ACCESS_RULE === $this->access_type;
	}

	public function is_custom_offer(): bool {
		return self::ACCESS_OFFER === $this->access_type;
	}

	public function is_no_access(): bool {
		return self::ACCESS_NONE === $this->access_type;
	}
}
