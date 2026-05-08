<?php
/**
 * Bucket CPT
 *
 * Registers the dp_bucket Custom Post Type and its WooCommerce admin submenu.
 * ACF field group is managed via acf-json/ (version-controlled) — no PHP registration needed.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dreampoint_B2B_Bucket_CPT {

	public const POST_TYPE = 'dp_bucket';

	public function register_hooks(): void {
		add_action( 'init',       [ $this, 'register_post_type' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'on_bucket_saved' ] );
		add_filter( 'acf/prepare_field/name=dp_access_type', [ $this, 'link_custom_offer_instructions' ] );
	}

	public function register_post_type(): void {
		register_post_type( self::POST_TYPE, [
			'labels'             => [
				'name'               => __( 'Customer Buckets', 'dreampoint-b2b' ),
				'singular_name'      => __( 'Customer Bucket', 'dreampoint-b2b' ),
				'add_new_item'       => __( 'Add New Bucket', 'dreampoint-b2b' ),
				'edit_item'          => __( 'Edit Bucket', 'dreampoint-b2b' ),
				'new_item'           => __( 'New Bucket', 'dreampoint-b2b' ),
				'view_item'          => __( 'View Bucket', 'dreampoint-b2b' ),
				'search_items'       => __( 'Search Buckets', 'dreampoint-b2b' ),
				'not_found'          => __( 'No buckets found.', 'dreampoint-b2b' ),
				'not_found_in_trash' => __( 'No buckets found in Trash.', 'dreampoint-b2b' ),
			],
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false, // registered manually under WooCommerce
			'show_in_rest'       => false,
			'supports'           => [ 'title' ],
			'capability_type'    => 'post',
			// All management requires manage_woocommerce (shop manager + admin).
			'capabilities'       => [
				'edit_post'          => 'manage_woocommerce',
				'read_post'          => 'manage_woocommerce',
				'delete_post'        => 'manage_woocommerce',
				'edit_posts'         => 'manage_woocommerce',
				'edit_others_posts'  => 'manage_woocommerce',
				'publish_posts'      => 'manage_woocommerce',
				'read_private_posts' => 'manage_woocommerce',
				'delete_posts'       => 'manage_woocommerce',
				'create_posts'       => 'manage_woocommerce',
			],
			'map_meta_cap'       => false,
		] );
	}

	public function register_admin_menu(): void {
		// No callback needed — WP routes edit.php?post_type=dp_bucket natively.
		add_submenu_page(
			'woocommerce',
			__( 'Customer Buckets', 'dreampoint-b2b' ),
			__( 'Customer Buckets', 'dreampoint-b2b' ),
			'manage_woocommerce',
			'edit.php?post_type=' . self::POST_TYPE
		);
	}

	public function on_bucket_saved( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		// Phase 1: clear static request cache. Phase 3: also increment Redis bucket version key.
		dreampoint_b2b_visibility_engine()->invalidate_bucket( $post_id );
	}

	/**
	 * Replaces the plain-text "Custom Offer Products admin page" in the dp_access_type
	 * field instructions with a clickable link to the Custom Offer admin page.
	 * Uses admin_url() so the URL is correct across all environments.
	 *
	 * @param array<string, mixed> $field
	 * @return array<string, mixed>
	 */
	public function link_custom_offer_instructions( array $field ): array {
		$url  = esc_url( admin_url( 'admin.php?page=dp-custom-offer' ) );
		$text = esc_html__( 'Custom Offer Products admin page', 'dreampoint-b2b' );
		$link = '<a href="' . $url . '" target="_blank">' . $text . '</a>';

		$field['instructions'] = str_replace(
			'Custom Offer Products admin page',
			$link,
			$field['instructions']
		);

		return $field;
	}
}
