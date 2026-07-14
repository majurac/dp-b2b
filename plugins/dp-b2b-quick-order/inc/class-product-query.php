<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Product_Query {

	public function __construct(
		private readonly DP_Quick_Order_Visibility_Integration $visibility,
		private readonly DP_Quick_Order_Already_Ordered_Resolver $already_ordered
	) {}

	/**
	 * Query products with a lightweight payload.
	 * Uses fields=>ids first, then hydrates only the paginated result set —
	 * never calls wc_get_product() across the full catalog.
	 *
	 * @param array{page: int, per_page: int, search: string, category: list<int|string>, brand: list<int|string>, price_min: float|null, price_max: float|null, attributes: array} $args
	 * @return array{products: list<array>, total: int, total_pages: int}
	 */
	public function query( array $args ): array {
		$query_args = [
			'post_type'        => 'product',
			'post_status'      => 'publish',
			'posts_per_page'   => (int) $args['per_page'],
			'paged'            => (int) $args['page'],
			'fields'           => 'ids',
			'no_found_rows'    => false,
			'suppress_filters' => false,
			'dp_quick_order'   => true,
		];

		$orderby = $args['orderby'] ?? 'title';
		$order   = strtoupper( $args['order'] ?? 'ASC' );
		$order   = in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'ASC';

		if ( 'price' === $orderby ) {
			$query_args['orderby']  = 'meta_value_num';
			$query_args['meta_key'] = '_price';
		} else {
			$query_args['orderby'] = 'title';
		}
		$query_args['order'] = $order;

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		if ( ! empty( $args['category'] ) ) {
			$category_ids = $this->resolve_taxonomy_term_ids( (array) $args['category'], 'product_cat' );
			// A category filter was requested but nothing resolved (stale/unknown
			// slug) — force zero matches via an impossible term ID rather than
			// silently dropping the clause and returning the whole catalog.
			$query_args['tax_query'][] = [
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $category_ids ?: [ 0 ],
				'operator' => 'IN',
			];
		}

		if ( ! empty( $args['brand'] ) ) {
			$brand_ids = $this->resolve_taxonomy_term_ids( (array) $args['brand'], 'product_brand' );
			// Same reasoning as category above — an unresolvable brand token must
			// not silently fall through to the unfiltered catalog.
			$query_args['tax_query'][] = [
				'taxonomy' => 'product_brand',
				'field'    => 'term_id',
				'terms'    => $brand_ids ?: [ 0 ],
				'operator' => 'IN',
			];
		}

		// Price range filter — maps to _price meta.
		$price_min = isset( $args['price_min'] ) && '' !== $args['price_min'] ? (float) $args['price_min'] : null;
		$price_max = isset( $args['price_max'] ) && '' !== $args['price_max'] ? (float) $args['price_max'] : null;

		if ( null !== $price_min && null !== $price_max ) {
			$query_args['meta_query'][] = [
				'key'     => '_price',
				'value'   => [ $price_min, $price_max ],
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			];
		} elseif ( null !== $price_min ) {
			$query_args['meta_query'][] = [
				'key'     => '_price',
				'value'   => $price_min,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			];
		} elseif ( null !== $price_max ) {
			$query_args['meta_query'][] = [
				'key'     => '_price',
				'value'   => $price_max,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			];
		}

		// Stock status filter — maps to _stock_status post meta.
		$stock_status = $args['stock_status'] ?? '';
		if ( in_array( $stock_status, [ 'instock', 'outofstock', 'onbackorder' ], true ) ) {
			$query_args['meta_query'][] = [
				'key'     => '_stock_status',
				'value'   => $stock_status,
				'compare' => '=',
			];
		}

		// "New" filter — post_date_gmt within the configured threshold.
		// Uses post_date_gmt (always UTC), never post_date (site-local, per
		// Settings > General timezone) — both sides of the comparison are
		// UTC instants by construction, so there is no timezone offset to
		// assume or correct for, regardless of the site's configured
		// timezone. `time()` is likewise always a UTC unix timestamp.
		if ( ! empty( $args['new'] ) ) {
			$max_age_days = apply_filters( 'dp_qo_new_product_max_age_days', DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS );

			// Defensive: a filter callback may return anything. Only a
			// positive integer is valid — anything else (0, negative,
			// non-numeric, non-integer-valued like 5.5) falls back to the
			// configured default rather than producing an inverted or
			// unbounded date range.
			$is_valid_max_age = is_numeric( $max_age_days ) && (int) $max_age_days == $max_age_days && (int) $max_age_days > 0;
			$max_age_days     = $is_valid_max_age ? (int) $max_age_days : DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS;

			// Boundary is inclusive: a product published exactly at the
			// cutoff instant (N days ago, to the second) IS "New"; one
			// second earlier is not. `inclusive => true` makes WP_Date_Query
			// emit `post_date_gmt >= cutoff` instead of its default `>`.
			$query_args['date_query'][] = [
				'column'    => 'post_date_gmt',
				'after'     => gmdate( 'Y-m-d H:i:s', time() - ( $max_age_days * DAY_IN_SECONDS ) ),
				'inclusive' => true,
			];
		}

		// "Best Seller" filter — native WC total_sales meta, configurable floor.
		// No popularity sort, no top-N, no ACF, no duplicated/custom meta —
		// total_sales is WooCommerce's own native counter, updated by core on
		// order completion; this filter only ever reads it.
		if ( ! empty( $args['best_seller'] ) ) {
			$min_sales = apply_filters( 'dp_qo_best_seller_min_sales', DP_Quick_Order_Config::BEST_SELLER_MIN_SALES );

			// Defensive: same validation shape as the "New" filter's max-age
			// override (Task 2) — only a positive integer is valid; anything
			// else (0, negative, non-numeric, non-integer-valued) falls back
			// to the configured default instead of producing a broken or
			// unbounded comparison.
			$is_valid_min_sales = is_numeric( $min_sales ) && (int) $min_sales == $min_sales && (int) $min_sales > 0;
			$min_sales          = $is_valid_min_sales ? (int) $min_sales : DP_Quick_Order_Config::BEST_SELLER_MIN_SALES;

			$query_args['meta_query'][] = [
				'key'     => 'total_sales',
				'value'   => $min_sales,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			];
		}

		// Product attribute filters — each maps to a tax_query entry.
		// $args['attributes'] is an assoc array: ['color' => ['red','blue'], 'size' => ['M']].
		if ( ! empty( $args['attributes'] ) && is_array( $args['attributes'] ) ) {
			foreach ( $args['attributes'] as $attr_name => $terms ) {
				$taxonomy = 'pa_' . sanitize_key( (string) $attr_name );
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				$slugs = array_filter( array_map( 'sanitize_key', (array) $terms ) );
				if ( empty( $slugs ) ) {
					continue;
				}
				$query_args['tax_query'][] = [
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array_values( $slugs ),
					'operator' => 'IN',
				];
			}
		}

		// "Already Ordered" filter — current customer's own order history.
		// Parent-level rollup (business rule, spec §3 revised 2026-07-14):
		// if ANY variation of a parent was previously ordered, the parent
		// qualifies. The resolver already returns deduplicated PARENT
		// product IDs (Task 4) — no row-key parsing or "collapse to parent"
		// step needed at this layer. post__in filters WP_Query at the
		// parent-post level only (product_variation is a different post
		// type), so once a parent qualifies, prepare_product()/
		// get_variation_details() below run completely unmodified and
		// return ALL current variations, exactly as for any other filter —
		// satisfying "render exactly as today."
		if ( ! empty( $args['already_ordered'] ) ) {
			$product_ids = $this->already_ordered->get_ordered_product_ids( get_current_user_id() );
			// Empty result must force zero matches, not fall through to the
			// unfiltered catalog — same convention as the category/brand
			// resolution above.
			$query_args['post__in'] = $product_ids ?: [ 0 ];
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
	 * Resolve mixed taxonomy term identifiers (category or brand) to term IDs.
	 * WBW's own filter-value format is instance/version-dependent — legacy
	 * configs emit a bare numeric term ID, current configs emit a slug (from a
	 * delimited multi-select list, already split by the caller). Accepting
	 * either at this single layer keeps the tax_query itself simple (always
	 * term_id) regardless of which format the frontend forwarded. Shared by
	 * both `product_cat` and `product_brand` — WBW uses the identical engine
	 * and value format for both filter types.
	 *
	 * @param list<int|string> $tokens
	 * @param string           $taxonomy 'product_cat' or 'product_brand'.
	 * @return list<int>
	 */
	private function resolve_taxonomy_term_ids( array $tokens, string $taxonomy ): array {
		$ids = [];
		foreach ( $tokens as $token ) {
			$token = is_string( $token ) ? trim( $token ) : $token;
			if ( '' === $token || null === $token ) {
				continue;
			}
			if ( is_numeric( $token ) ) {
				$ids[] = (int) $token;
				continue;
			}
			$term = get_term_by( 'slug', sanitize_title( (string) $token ), $taxonomy );
			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
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
			'permalink'  => get_permalink( $id ),
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
	 * @return list<array{id:int,sku:string,label:string,attributes:list<array{label:string,value:string}>,price:string,price_html:string,stock_status:string,stock_qty:int|null}>
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

			// Resolved, human-readable label/value pairs — deterministic order
			// (WC_Product_Variation::get_attributes()'s own iteration order).
			// This is the single place attribute names/values are resolved;
			// consumers (chip rendering) must not re-derive them from slugs.
			$attributes = [];
			foreach ( $variation->get_attributes() as $key => $value ) {
				if ( '' === $value ) {
					continue;
				}
				$attr_label  = wc_attribute_label( str_replace( 'attribute_', '', $key ), $variation );
				$taxonomy    = str_replace( 'attribute_', '', $key );
				$term        = get_term_by( 'slug', $value, $taxonomy );
				$value_label = $term instanceof WP_Term ? $term->name : $value;
				$attributes[] = [
					'label' => $attr_label,
					'value' => $value_label,
				];
			}
			$label = $attributes
				? implode( ' / ', array_map( static fn( array $a ): string => $a['label'] . ': ' . $a['value'], $attributes ) )
				/* translators: %d: variation ID */
				: sprintf( __( 'Variation #%d', 'dp-b2b-quick-order' ), $variation_id );

			$result[] = [
				'id'           => $variation_id,
				'sku'          => $variation->get_sku(),
				'label'        => $label,
				'attributes'   => $attributes,
				'price'        => (string) $variation->get_price(),
				'price_html'   => $variation->get_price_html(),
				'stock_status' => $variation->get_stock_status(),
				'stock_qty'    => $variation->get_manage_stock() ? (int) $variation->get_stock_quantity() : null,
			];
		}

		return $result;
	}
}
