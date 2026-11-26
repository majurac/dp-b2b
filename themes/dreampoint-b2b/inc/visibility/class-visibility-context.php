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
		/** product_brand term ID the bucket's assortment rules are scoped to (0 = unscoped). */
		public readonly int    $scope_brand_id       = 0,
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

	/**
	 * Brand-scoped bucket: rule_based / custom_offer whose assortment rules apply ONLY to the
	 * configured brand (IZIPIZI). Products of other brands are open unless an independent
	 * denial applies; products without any brand assignment are denied.
	 */
	public function is_scoped(): bool {
		return $this->scope_brand_id > 0 && ( $this->is_rule_based() || $this->is_custom_offer() );
	}

	public function is_no_access(): bool {
		return self::ACCESS_NONE === $this->access_type;
	}
}
