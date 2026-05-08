<?php
/**
 * Visibility Engine
 *
 * Resolves and merges bucket rules + user overrides into a Visibility_Context.
 * Phase 1: request-level static cache only (no Redis). Redis wired in Phase 3.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dreampoint_B2B_Visibility_Engine {

	/** @var array<int, Dreampoint_B2B_Visibility_Context> */
	private static array $context_cache = [];

	/** @var array<int, Dreampoint_B2B_Bucket_Context> */
	private static array $bucket_cache = [];

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	public function get_context( int $user_id ): Dreampoint_B2B_Visibility_Context {
		// Level 1: static request cache.
		if ( isset( self::$context_cache[ $user_id ] ) ) {
			return self::$context_cache[ $user_id ];
		}

		// Level 2: Redis / WP object cache.
		$cache_key = $this->build_context_cache_key( $user_id );
		$cached    = wp_cache_get( $cache_key, 'dp_visibility' );

		if ( $cached instanceof Dreampoint_B2B_Visibility_Context ) {
			$this->debug_log( "Context user={$user_id}: HIT (key={$cache_key})" );
			self::$context_cache[ $user_id ] = $cached;
			return $cached;
		}

		$this->debug_log( "Context user={$user_id}: MISS (key={$cache_key})" );

		try {
			$context = $this->resolve_context( $user_id );
		} catch ( Throwable $e ) {
			$this->debug_log( "Context resolution failed for user {$user_id}: " . $e->getMessage() );
			$context = Dreampoint_B2B_Visibility_Context::no_access();
		}

		self::$context_cache[ $user_id ] = $context;
		wp_cache_set( $cache_key, $context, 'dp_visibility', 12 * HOUR_IN_SECONDS );

		$this->debug_log( "Context user={$user_id}: resolved={$context->access_type}" );

		return $context;
	}

	public function get_bucket_context( int $bucket_id ): Dreampoint_B2B_Bucket_Context {
		if ( isset( self::$bucket_cache[ $bucket_id ] ) ) {
			return self::$bucket_cache[ $bucket_id ];
		}

		$bucket = $this->resolve_bucket( $bucket_id );
		self::$bucket_cache[ $bucket_id ] = $bucket;

		return $bucket;
	}

	public function invalidate_user( int $user_id ): void {
		unset( self::$context_cache[ $user_id ] );
		// Incrementing override_version changes the Redis key → old cached context is orphaned.
		self::increment_override_version( $user_id );
	}

	public function invalidate_bucket( int $bucket_id ): void {
		unset( self::$bucket_cache[ $bucket_id ] );
		// Incrementing bucket_version changes the Redis key for every user assigned to this bucket.
		self::increment_bucket_version( $bucket_id );
		// Static context cache must also be cleared: multiple users may share this bucket.
		self::$context_cache = [];
	}

	public function invalidate_taxonomy(): void {
		// Taxonomy changes affect rule display names, not rule IDs — bucket rule resolution
		// is unaffected. Static caches are cleared; Redis contexts remain valid.
		// If term deletion ever affects stored rule IDs, add per-bucket invalidation here.
		self::$bucket_cache  = [];
		self::$context_cache = [];
	}

	// -------------------------------------------------------------------------
	// Object cache versioning (Phase 3)
	// -------------------------------------------------------------------------

	/**
	 * Builds a versioned Redis key for the user's context.
	 * Key: dp_vis_ctx_{user_id}_{bucket_version}_{override_version}
	 *
	 * When either version changes, the old key is abandoned — no explicit delete needed.
	 * get_user_meta( dp_bucket_id ) is served from WP's own usermeta cache (no extra DB hit).
	 */
	private function build_context_cache_key( int $user_id ): string {
		$bucket_id    = (int) get_user_meta( $user_id, 'dp_bucket_id', true );
		$bucket_ver   = $bucket_id ? self::get_bucket_version( $bucket_id ) : 0;
		$override_ver = self::get_override_version( $user_id );

		$this->debug_log( "Cache key: user={$user_id} bucket={$bucket_id} bv={$bucket_ver} ov={$override_ver}" );

		return "dp_vis_ctx_{$user_id}_{$bucket_ver}_{$override_ver}";
	}

	private static function get_bucket_version( int $bucket_id ): int {
		$ver = wp_cache_get( "dp_bucket_ver_{$bucket_id}", 'dp_visibility' );
		return ( is_int( $ver ) && $ver > 0 ) ? $ver : 1;
	}

	/** Called by on_bucket_saved hook in Bucket_CPT and by invalidate_bucket(). */
	public static function increment_bucket_version( int $bucket_id ): void {
		if ( false === wp_cache_incr( "dp_bucket_ver_{$bucket_id}", 1, 'dp_visibility' ) ) {
			// Key absent (Redis restart or first save) — initialise to 2 so existing
			// cached contexts keyed on default version 1 are invalidated.
			wp_cache_set( "dp_bucket_ver_{$bucket_id}", 2, 'dp_visibility' );
		}
	}

	private static function get_override_version( int $user_id ): int {
		$ver = wp_cache_get( "dp_override_ver_{$user_id}", 'dp_visibility' );
		return ( is_int( $ver ) && $ver > 0 ) ? $ver : 1;
	}

	/** Called via dp_access_table_changed action whenever wp_dp_product_access changes. */
	public static function increment_override_version( int $user_id ): void {
		if ( false === wp_cache_incr( "dp_override_ver_{$user_id}", 1, 'dp_visibility' ) ) {
			wp_cache_set( "dp_override_ver_{$user_id}", 2, 'dp_visibility' );
		}
	}

	// -------------------------------------------------------------------------
	// Resolution
	// -------------------------------------------------------------------------

	private function resolve_context( int $user_id ): Dreampoint_B2B_Visibility_Context {
		$bucket_id = (int) get_user_meta( $user_id, 'dp_bucket_id', true );

		if ( ! $bucket_id ) {
			$this->debug_log( "User {$user_id} has no bucket assigned → no_access" );
			return Dreampoint_B2B_Visibility_Context::no_access();
		}

		$bucket = $this->get_bucket_context( $bucket_id );
		return $this->merge_context( $bucket, $user_id );
	}

	private function resolve_bucket( int $bucket_id ): Dreampoint_B2B_Bucket_Context {
		$post = get_post( $bucket_id );

		if ( ! $post
			|| Dreampoint_B2B_Bucket_CPT::POST_TYPE !== $post->post_type
			|| 'publish' !== $post->post_status
		) {
			return new Dreampoint_B2B_Bucket_Context( Dreampoint_B2B_Visibility_Context::ACCESS_NONE );
		}

		if ( ! function_exists( 'get_field' ) ) {
			$this->debug_log( "ACF not available — bucket {$bucket_id} → no_access" );
			return new Dreampoint_B2B_Bucket_Context( Dreampoint_B2B_Visibility_Context::ACCESS_NONE );
		}

		$access_type = (string) get_field( 'dp_access_type', $bucket_id );

		if ( ! in_array( $access_type, [
			Dreampoint_B2B_Visibility_Context::ACCESS_FULL,
			Dreampoint_B2B_Visibility_Context::ACCESS_RULE,
			Dreampoint_B2B_Visibility_Context::ACCESS_OFFER,
		], true ) ) {
			$this->debug_log( "Bucket {$bucket_id} has invalid access_type '{$access_type}' → no_access" );
			return new Dreampoint_B2B_Bucket_Context( Dreampoint_B2B_Visibility_Context::ACCESS_NONE );
		}

		// full_access and custom_offer don't use ACF rule fields.
		if ( Dreampoint_B2B_Visibility_Context::ACCESS_FULL === $access_type
			|| Dreampoint_B2B_Visibility_Context::ACCESS_OFFER === $access_type
		) {
			return new Dreampoint_B2B_Bucket_Context( $access_type );
		}

		// rule_based: read all rule fields.
		$brand_ids   = $this->extract_ids( get_field( 'dp_allowed_brands', $bucket_id ) );
		$cat_ids     = $this->extract_ids( get_field( 'dp_allowed_categories', $bucket_id ) );
		$include_ids = $this->extract_ids( get_field( 'dp_explicit_include', $bucket_id ) );
		$exclude_ids = $this->extract_ids( get_field( 'dp_explicit_exclude', $bucket_id ) );

		// Unconfigured bucket must not open the catalog (fail-safe).
		if ( empty( $brand_ids ) && empty( $cat_ids ) && empty( $include_ids ) ) {
			$this->debug_log( "Bucket {$bucket_id} is rule_based with no rules → downgraded to no_access" );
			return new Dreampoint_B2B_Bucket_Context( Dreampoint_B2B_Visibility_Context::ACCESS_NONE );
		}

		$this->debug_log( sprintf(
			'Bucket %d resolved: brands=%d, cats=%d, include=%d, exclude=%d',
			$bucket_id, count( $brand_ids ), count( $cat_ids ), count( $include_ids ), count( $exclude_ids )
		) );

		return new Dreampoint_B2B_Bucket_Context( $access_type, $brand_ids, $cat_ids, $include_ids, $exclude_ids );
	}

	private function merge_context( Dreampoint_B2B_Bucket_Context $bucket, int $user_id ): Dreampoint_B2B_Visibility_Context {
		// Passthrough types: no user overrides needed.
		if ( Dreampoint_B2B_Visibility_Context::ACCESS_FULL === $bucket->access_type
			|| Dreampoint_B2B_Visibility_Context::ACCESS_NONE === $bucket->access_type
		) {
			return new Dreampoint_B2B_Visibility_Context( $bucket->access_type );
		}

		$override_add    = array_map( 'intval', (array) ( get_user_meta( $user_id, 'dp_override_add', true ) ?: [] ) );
		$override_remove = array_map( 'intval', (array) ( get_user_meta( $user_id, 'dp_override_remove', true ) ?: [] ) );

		$this->debug_log( sprintf(
			'User %d merged: type=%s, +override=%d, -override=%d',
			$user_id, $bucket->access_type, count( $override_add ), count( $override_remove )
		) );

		return new Dreampoint_B2B_Visibility_Context(
			$bucket->access_type,
			$bucket->allowed_brand_ids,
			$bucket->allowed_cat_ids,
			$bucket->explicit_include_ids,
			$bucket->explicit_exclude_ids,
			$override_add,
			$override_remove,
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Normalises ACF field output to an array of integers.
	 * Handles WP_Term objects, WP_Post objects, term/post IDs, and empty values.
	 *
	 * @param mixed $field_value
	 * @return int[]
	 */
	private function extract_ids( mixed $field_value ): array {
		if ( empty( $field_value ) ) {
			return [];
		}
		return array_map( static function ( mixed $item ): int {
			if ( $item instanceof WP_Term ) {
				return (int) $item->term_id;
			}
			if ( $item instanceof WP_Post ) {
				return (int) $item->ID;
			}
			return (int) $item;
		}, (array) $field_value );
	}

	// -------------------------------------------------------------------------
	// Debug
	// -------------------------------------------------------------------------

	private function debug_log( string $message ): void {
		if ( defined( 'DP_VISIBILITY_DEBUG' ) && DP_VISIBILITY_DEBUG && current_user_can( 'manage_options' ) ) {
			error_log( '[dp_visibility] ' . $message );
		}
	}
}
