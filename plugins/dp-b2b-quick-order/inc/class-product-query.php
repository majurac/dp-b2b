<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Product_Query {

	public function __construct(
		private readonly DP_Quick_Order_Visibility_Integration $visibility
	) {}

	/**
	 * Query products with a lightweight payload.
	 * Uses fields=>ids first, then hydrates only the paginated result set —
	 * never calls wc_get_product() across the full catalog.
	 *
	 * @param array{page: int, per_page: int, search: string, category: int, brand: int} $args
	 * @return array{products: list<array>, total: int, total_pages: int}
	 */
	public function query( array $args ): array {
		$query_args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $args['per_page'],
			'paged'          => (int) $args['page'],
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'suppress_filters' => false,
		];

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		if ( ! empty( $args['category'] ) ) {
			$query_args['tax_query'][] = [
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => (int) $args['category'],
			];
		}

		if ( ! empty( $args['brand'] ) ) {
			$query_args['tax_query'][] = [
				'taxonomy' => 'product_brand',
				'field'    => 'term_id',
				'terms'    => (int) $args['brand'],
			];
		}

		$this->visibility->apply_to_query( $query_args );

		$query = new WP_Query( $query_args );

		return [
			'products'    => array_map( [ $this, 'prepare_product' ], array_map( 'intval', $query->posts ) ),
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		];
	}

	/**
	 * Builds a lightweight product payload.
	 * wc_get_product() is called only on the page-level result set (default 50 items),
	 * not on the full catalog. WC object cache (Redis) handles repeated fetches.
	 */
	private function prepare_product( int $id ): array {
		$product = wc_get_product( $id );

		if ( ! $product instanceof WC_Product ) {
			return [ 'id' => $id ];
		}

		$data = [
			'id'         => $id,
			'name'       => $product->get_name(),
			'sku'        => $product->get_sku(),
			'type'       => $product->get_type(),
			'price'      => $product->get_price(),
			'price_html' => $product->get_price_html(),
			'stock'      => [
				'status'   => $product->get_stock_status(),
				'quantity' => $product->get_stock_quantity(),
				'managed'  => $product->get_manage_stock(),
			],
			'image'      => $this->get_thumbnail_url( $id ),
		];

		if ( $product instanceof WC_Product_Variable ) {
			$data['variations'] = $this->get_variation_summary( $product );
		}

		return $data;
	}

	private function get_thumbnail_url( int $id ): string {
		$thumb_id = (int) get_post_thumbnail_id( $id );
		if ( ! $thumb_id ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $thumb_id, 'woocommerce_thumbnail' );
		return $src ? $src[0] : '';
	}

	/**
	 * Lightweight variation summary — avoids get_available_variations().
	 * Returns price range, attribute labels, and variation IDs.
	 * Full per-variation data is fetched on demand (e.g. when user expands a product row).
	 */
	private function get_variation_summary( WC_Product_Variable $product ): array {
		return [
			'price_range'   => [
				'min' => $product->get_variation_price( 'min' ),
				'max' => $product->get_variation_price( 'max' ),
			],
			'attributes'    => $product->get_variation_attributes(),
			'variation_ids' => $product->get_children(),
		];
	}

	/**
	 * Returns lightweight variation details for a variable product.
	 * Loads each variation via wc_get_product() — uses WC object cache (Redis).
	 * Never calls get_available_variations() — avoids full variation tree hydration.
	 *
	 * @param int $product_id Parent variable product ID.
	 * @return list<array{id:int,sku:string,label:string,price:string,price_html:string,stock_status:string,stock_qty:int|null}>
	 */
	public function get_variation_details( int $product_id ): array {
		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product_Variable ) {
			return [];
		}

		$result = [];

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation instanceof WC_Product_Variation || ! $variation->exists() ) {
				continue;
			}

			$attr_parts = [];
			foreach ( $variation->get_attributes() as $key => $value ) {
				if ( '' === $value ) {
					continue;
				}
				$attr_label = wc_attribute_label( str_replace( 'attribute_', '', $key ), $variation );
				$attr_parts[] = $attr_label . ': ' . $value;
			}
			$label = $attr_parts
				? implode( ' / ', $attr_parts )
				/* translators: %d: variation ID */
				: sprintf( __( 'Variation #%d', 'dp-b2b-quick-order' ), $variation_id );

			$result[] = [
				'id'           => $variation_id,
				'sku'          => $variation->get_sku(),
				'label'        => $label,
				'price'        => (string) $variation->get_price(),
				'price_html'   => $variation->get_price_html(),
				'stock_status' => $variation->get_stock_status(),
				'stock_qty'    => $variation->get_manage_stock() ? (int) $variation->get_stock_quantity() : null,
			];
		}

		return $result;
	}
}
