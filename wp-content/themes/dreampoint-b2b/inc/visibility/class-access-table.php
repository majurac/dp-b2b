<?php
/**
 * Access Table
 *
 * Manages the wp_dp_product_access custom table.
 * Used by custom_offer users to avoid post__in with large product ID sets.
 *
 * Schema:
 *   PRIMARY KEY (user_id, product_id)
 *   KEY idx_user_access (user_id, access, product_id)  ← covering index for main JOIN query
 *   KEY idx_product (product_id)                        ← reverse lookup
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dreampoint_B2B_Access_Table {

	public const TABLE_SUFFIX = 'dp_product_access';

	private const DB_VERSION  = '1.0';
	private const VERSION_KEY = 'dp_access_table_version';

	// -------------------------------------------------------------------------
	// Installation
	// -------------------------------------------------------------------------

	public static function get_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function install(): void {
		if ( get_option( self::VERSION_KEY ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;

		$table   = self::get_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			user_id    BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			access     ENUM('allow','deny') NOT NULL DEFAULT 'allow',
			PRIMARY KEY (user_id, product_id),
			KEY idx_user_access (user_id, access, product_id),
			KEY idx_product (product_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::VERSION_KEY, self::DB_VERSION, false );
	}

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	/**
	 * Insert or update a single row. Uses REPLACE INTO so the access column
	 * can be changed without DELETE + INSERT.
	 */
	public function add( int $user_id, int $product_id, string $access = 'allow' ): bool {
		global $wpdb;
		$result = false !== $wpdb->replace(
			self::get_name(),
			[ 'user_id' => $user_id, 'product_id' => $product_id, 'access' => $access ],
			[ '%d', '%d', '%s' ]
		);
		if ( $result ) {
			do_action( 'dp_access_table_changed', $user_id );
		}
		return $result;
	}

	public function remove( int $user_id, int $product_id ): bool {
		global $wpdb;
		$result = false !== $wpdb->delete(
			self::get_name(),
			[ 'user_id' => $user_id, 'product_id' => $product_id ],
			[ '%d', '%d' ]
		);
		if ( $result ) {
			do_action( 'dp_access_table_changed', $user_id );
		}
		return $result;
	}

	public function remove_all_for_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( self::get_name(), [ 'user_id' => $user_id ], [ '%d' ] );
		do_action( 'dp_access_table_changed', $user_id );
	}

	/**
	 * Returns product IDs for the given user filtered by access type.
	 *
	 * @return int[]
	 */
	public function get_for_user( int $user_id, string $access = 'allow' ): array {
		global $wpdb;
		$table   = self::get_name();
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT product_id FROM {$table} WHERE user_id = %d AND access = %s",
				$user_id,
				$access
			)
		);
		return $results ? array_map( 'intval', $results ) : [];
	}

	/** Point lookup — used by access guard on single product pages. */
	public function user_can_see( int $user_id, int $product_id ): bool {
		global $wpdb;
		$table = self::get_name();
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND product_id = %d AND access = 'allow'",
				$user_id,
				$product_id
			)
		);
	}

	/**
	 * Batch insert — chunks to stay within max_allowed_packet limits.
	 *
	 * @param int[] $product_ids
	 */
	public function bulk_set( int $user_id, array $product_ids, string $access = 'allow' ): void {
		if ( empty( $product_ids ) ) {
			return;
		}
		global $wpdb;
		$table  = self::get_name();
		$chunks = array_chunk( $product_ids, 500 );

		foreach ( $chunks as $chunk ) {
			$placeholders = implode( ', ', array_fill( 0, count( $chunk ), '(%d, %d, %s)' ) );
			$values       = [];
			foreach ( $chunk as $product_id ) {
				$values[] = $user_id;
				$values[] = (int) $product_id;
				$values[] = $access;
			}
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- variadic safe
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (user_id, product_id, access) VALUES {$placeholders}
					 ON DUPLICATE KEY UPDATE access = VALUES(access)",
					...$values
				)
			);
		}
		do_action( 'dp_access_table_changed', $user_id );
	}
}
