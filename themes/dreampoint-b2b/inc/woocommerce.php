<?php
/**
 * WooCommerce Compatibility File
 *
 * @link https://woocommerce.com/
 *
 * @package Dreampoint_B2B
 */

/**
 * WooCommerce setup function.
 *
 * @link https://docs.woocommerce.com/document/third-party-custom-theme-compatibility/
 * @link https://github.com/woocommerce/woocommerce/wiki/Enabling-product-gallery-features-(zoom,-swipe,-lightbox)
 * @link https://github.com/woocommerce/woocommerce/wiki/Declaring-WooCommerce-support-in-themes
 *
 * @return void
 */
function dreampoint_b2b_woocommerce_setup() {
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 150,
			'single_image_width'    => 300,
			'product_grid'          => array(
				'default_rows'    => 3,
				'min_rows'        => 1,
				'default_columns' => 4,
				'min_columns'     => 1,
				'max_columns'     => 6,
			),
		)
	);

	dreampoint_b2b_seed_product_grid_options();
}
add_action( 'after_setup_theme', 'dreampoint_b2b_woocommerce_setup' );

/**
 * WooCommerce silently falls back to its own internal product grid defaults
 * whenever woocommerce_catalog_columns / woocommerce_catalog_rows do not yet
 * exist as options — a fresh database, or any environment provisioned via
 * DB import + Git deploy rather than a real theme activation, may legitimately
 * never have these options seeded.
 *
 * This helper seeds them exactly once, from the theme's declared product_grid
 * defaults above, so that fallback never happens. Existing installations are
 * never modified: add_option() only creates an option that is missing, it
 * never touches one that already exists.
 *
 * This project intentionally relies on WooCommerce's native product grid
 * mechanism (woocommerce_catalog_columns * woocommerce_catalog_rows) rather
 * than a custom products-per-page implementation — this helper only
 * guarantees that native mechanism has the values it needs to work.
 *
 * @return void
 */
function dreampoint_b2b_seed_product_grid_options(): void {
	add_option( 'woocommerce_catalog_columns', wc_get_theme_support( 'product_grid::default_columns', 4 ) );
	add_option( 'woocommerce_catalog_rows', wc_get_theme_support( 'product_grid::default_rows', 4 ) );
}

/**
 * WooCommerce specific scripts & stylesheets.
 *
 * @return void
 */
function dreampoint_b2b_woocommerce_scripts() {
	wp_enqueue_style( 'dreampoint-b2b-woocommerce-style', get_template_directory_uri() . '/css/pages/woocommerce.css', array(), dreampoint_b2b_asset_ver( 'css/pages/woocommerce.css' ) );

	$font_path   = WC()->plugin_url() . '/assets/fonts/';
	$inline_font = '@font-face {
			font-family: "star";
			src: url("' . $font_path . 'star.eot");
			src: url("' . $font_path . 'star.eot?#iefix") format("embedded-opentype"),
				url("' . $font_path . 'star.woff") format("woff"),
				url("' . $font_path . 'star.ttf") format("truetype"),
				url("' . $font_path . 'star.svg#star") format("svg");
			font-weight: normal;
			font-style: normal;
		}';

	wp_add_inline_style( 'dreampoint-b2b-woocommerce-style', $inline_font );
}
add_action( 'wp_enqueue_scripts', 'dreampoint_b2b_woocommerce_scripts' );

/**
 * Disable the default WooCommerce stylesheet.
 *
 * Removing the default WooCommerce stylesheet and enqueing your own will
 * protect you during WooCommerce core updates.
 *
 * @link https://docs.woocommerce.com/document/disable-the-default-stylesheet/
 */
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/**
 * Add 'woocommerce-active' class to the body tag.
 *
 * @param  array $classes CSS classes applied to the body tag.
 * @return array $classes modified to include 'woocommerce-active' class.
 */
function dreampoint_b2b_woocommerce_active_body_class( $classes ) {
	$classes[] = 'woocommerce-active';

	return $classes;
}
add_filter( 'body_class', 'dreampoint_b2b_woocommerce_active_body_class' );

/**
 * Related Products Args.
 *
 * @param array $args related products args.
 * @return array $args related products args.
 */
function dreampoint_b2b_woocommerce_related_products_args( $args ) {
	$defaults = array(
		'posts_per_page' => 3,
		'columns'        => 3,
	);

	$args = wp_parse_args( $defaults, $args );

	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'dreampoint_b2b_woocommerce_related_products_args' );

/**
 * Remove default WooCommerce wrapper.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

if ( ! function_exists( 'dreampoint_b2b_woocommerce_wrapper_before' ) ) {
	/**
	 * Before Content.
	 *
	 * Wraps all WooCommerce content in wrappers which match the theme markup.
	 *
	 * @return void
	 */
	function dreampoint_b2b_woocommerce_wrapper_before() {
		?>
			<main id="primary" class="site-main">
		<?php
	}
}
add_action( 'woocommerce_before_main_content', 'dreampoint_b2b_woocommerce_wrapper_before' );

if ( ! function_exists( 'dreampoint_b2b_woocommerce_wrapper_after' ) ) {
	/**
	 * After Content.
	 *
	 * Closes the wrapping divs.
	 *
	 * @return void
	 */
	function dreampoint_b2b_woocommerce_wrapper_after() {
		?>
			</main><!-- #main -->
		<?php
	}
}
add_action( 'woocommerce_after_main_content', 'dreampoint_b2b_woocommerce_wrapper_after' );

/**
 * Sample implementation of the WooCommerce Mini Cart.
 *
 * You can add the WooCommerce Mini Cart to header.php like so ...
 *
	<?php
		if ( function_exists( 'dreampoint_b2b_woocommerce_header_cart' ) ) {
			dreampoint_b2b_woocommerce_header_cart();
		}
	?>
 */

if ( ! function_exists( 'dreampoint_b2b_woocommerce_cart_link_fragment' ) ) {
    /**
     * Cart Fragments.
     *
     * Ensure cart contents update when products are added to the cart via AJAX.
     *
     * @param array $fragments Fragments to refresh via AJAX.
     * @return array Fragments to refresh via AJAX.
     */
    function dreampoint_b2b_woocommerce_cart_link_fragment( $fragments ) {
        ob_start();
        dreampoint_b2b_woocommerce_cart_link();
        $fragments['button.cart-contents'] = ob_get_clean();

        return $fragments;
    }
}
add_filter( 'woocommerce_add_to_cart_fragments', 'dreampoint_b2b_woocommerce_cart_link_fragment' );

if ( ! function_exists( 'dreampoint_b2b_woocommerce_cart_link' ) ) {
	/**
	 * Cart Link.
	 *
	 * Displayed a link to the cart including the number of items present and the cart total.
	 *
	 * @return void
	 */
	function dreampoint_b2b_woocommerce_cart_link() {
		?>
		<button class="cart-contents" title="<?php esc_attr_e( 'Pogledajte svoju košaricu', 'dreampoint-b2b' ); ?>">
			<i class="icon-cart"></i>
			<span class="count"><?php echo WC()->cart->get_cart_contents_count(); ?></span>
		</button>
		<?php
	}
}

/**
 * Akcija stranica — native WC product archive filtriran na on-sale proizvode.
 *
 * Tri hooka rade zajedno:
 * 1. pre_get_posts (prio 8)  — pretvara main query u WC product archive
 * 2. template_include         — poslužuje archive-product.php
 * 3. is_woocommerce           — uključuje akcija stranicu u WC kontekst
 *
 * Prioritet 8 osigurava da WooCommerce-ovi hookovi (prio 9–10) vide
 * modificiran query i primijene native ordering, visibility i stock filtere.
 *
 * Multilanguage: za WPML zamijeniti slug provjeru s provjerom po master
 * page ID-u (via icl_object_id filter) kada se WPML konfigurira.
 */
function dreampoint_b2b_akcija_page_detected( ?bool $set = null ): bool {
	static $detected = false;
	if ( null !== $set ) {
		$detected = $set;
	}
	return $detected;
}

add_action( 'pre_get_posts', function( WP_Query $q ): void {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_page ) {
		return;
	}

	$pagename  = $q->get( 'pagename' );
	$page_id   = (int) $q->get( 'page_id' );
	$is_akcija = $pagename === 'akcija'
		|| ( $page_id && get_post( $page_id )?->post_name === 'akcija' );

	if ( ! $is_akcija ) {
		return;
	}

	dreampoint_b2b_akcija_page_detected( true );

	$q->set( 'post_type', 'product' );
	$q->set( 'post_status', 'publish' );

	// Falsy value — WC_Query::product_query() (prio 10, runs after this
	// hook) only applies its native per-page default (woocommerce_catalog_
	// columns × _rows, same as shop/category archives) when posts_per_page
	// is not already truthy. A hardcoded value here would silently diverge
	// from the rest of the catalog's per-page count.
	$q->set( 'posts_per_page', 0 );

	// Clear page-identity query vars — WP_Query::get_posts() builds
	// "AND ID = $reqpage" from $this->queried_object_id (cached during
	// parse_query(), before this hook runs) whenever 'pagename' is still
	// set, regardless of post_type/is_archive overrides below. Without
	// this, the query is pinned to the Akcija page's own post ID and
	// always returns zero rows.
	$q->set( 'pagename', '' );
	$q->set( 'page_id', 0 );
	$q->queried_object    = null;
	$q->queried_object_id = null;

	$q->is_singular          = false;
	$q->is_page              = false;
	$q->is_archive           = true;
	$q->is_post_type_archive = true;
}, 8 );

/**
 * WC_Query::product_query() (pre_get_posts prio 10, runs after the hook
 * above) unconditionally overwrites post__in from this filter — a direct
 * $q->set('post__in', ...) in the prio-8 hook above would be discarded.
 * This is WC's own native extension point for restricting the shop loop,
 * so it composes correctly with ordering, pagination and stock filters.
 */
add_filter( 'loop_shop_post_in', function( array $post_in ): array {
	if ( ! dreampoint_b2b_akcija_page_detected() ) {
		return $post_in;
	}
	$on_sale_ids = wc_get_product_ids_on_sale();
	return $on_sale_ids ?: [ 0 ];
} );

add_filter( 'template_include', function( string $template ): string {
	if ( ! dreampoint_b2b_akcija_page_detected() ) {
		return $template;
	}
	$archive = get_template_directory() . '/woocommerce/archive-product.php';
	return file_exists( $archive ) ? $archive : $template;
} );

add_filter( 'is_woocommerce', function( bool $is_wc ): bool {
	return $is_wc || dreampoint_b2b_akcija_page_detected();
} );

if ( ! function_exists( 'dreampoint_b2b_woocommerce_header_cart' ) ) {
	/**
	 * Display Header Cart.
	 *
	 * @return void
	 */
	function dreampoint_b2b_woocommerce_header_cart() {
		if ( is_cart() ) {
			$class = 'current-menu-item';
		} else {
			$class = '';
		}
		?>
		<ul id="site-header-cart" class="site-header-cart">
			<li class="<?php echo esc_attr( $class ); ?>">
				<?php dreampoint_b2b_woocommerce_cart_link(); ?>
			</li>
			<li>
				<?php
				$instance = array(
					'title' => '',
				);

				the_widget( 'WC_Widget_Cart', $instance );
				?>
			</li>
		</ul>
		<?php
	}
}

/**
 * Neobvezujuća MPC — ručno održavana informativna vrijednost (ACF `dp_non_binding_mpc`).
 * Samo za prikaz na PDP-u; ne učestvuje ni u kakvom obračunu cijene.
 *
 * @param WC_Product $product Proizvod (kod varijabilnih: roditelj).
 * @return float Pozitivan iznos, ili 0.0 ako vrijednost nije postavljena / nije valjana.
 */
function dreampoint_b2b_get_non_binding_mpc( WC_Product $product ): float {
	if ( ! function_exists( 'get_field' ) ) {
		return 0.0;
	}

	$value = get_field( 'dp_non_binding_mpc', $product->get_id() );

	return is_numeric( $value ) && (float) $value > 0 ? (float) $value : 0.0;
}

/**
 * Variable products: an attribute whose variations use exactly one value is
 * pre-selected server-side and flagged with `.dp-single-option` so CSS hides its
 * row (sass/pages/shop-single.scss); `pa_boja` is the exception and stays visible
 * (ADR-026 addendum). Options come from WooCommerce's
 * get_variation_attributes() (values actually used by variations), so unused
 * terms do not count. Attributes with several values are left untouched, and
 * dependent narrowing is never treated as "single" (no row jumping mid-selection).
 *
 * @param array $args Arguments of wc_dropdown_variation_attribute_options().
 * @return array
 */
function dreampoint_b2b_single_option_attribute_args( array $args ): array {
	$options = $args['options'] ?? [];

	if ( empty( $options ) && ! empty( $args['product'] ) && ! empty( $args['attribute'] ) ) {
		$attributes = $args['product']->get_variation_attributes();
		$options    = $attributes[ $args['attribute'] ] ?? [];
	}

	$options = array_values( array_filter( (array) $options, 'strlen' ) );

	if ( 1 !== count( $options ) ) {
		return $args;
	}

	$args['selected'] = $options[0];

	// `.dp-single-selected`: value is auto-restored after "Clear" (product-single.js).
	// `.dp-single-option`: row is hidden (CSS). Colour stays visible as product information.
	$classes = 'dp-single-selected';
	if ( 'pa_boja' !== $args['attribute'] ) {
		$classes .= ' dp-single-option';
	}
	$args['class'] = trim( ( $args['class'] ?? '' ) . ' ' . $classes );

	return $args;
}
add_filter( 'woocommerce_dropdown_variation_attribute_options_args', 'dreampoint_b2b_single_option_attribute_args' );

/**
 * Translations for the third-party silkypress-input-field-block plugin.
 * The plugin ships no language files; load our own per-locale .mo from
 * languages/plugins/ (update-resistant, no plugin file edited). Loaded after the
 * plugin's own init-10 textdomain registration; a missing locale file is a no-op,
 * so an English or other locale falls through to the plugin's source strings.
 */
add_action( 'init', function (): void {
	$mofile = get_template_directory() . '/languages/plugins/silkypress-input-field-block-' . determine_locale() . '.mo';
	if ( is_readable( $mofile ) ) {
		load_textdomain( 'silkypress-input-field-block', $mofile );
	}
}, 20 );

/**
 * Croatian strings for the third-party woo-product-filter (WBW) plugin, same mechanism as above:
 * the plugin ships no hr file, so load our own per-locale .mo from languages/plugins/. Covers only
 * strings the plugin prints through gettext; labels saved in the WBW filter settings are DB data.
 * A missing locale file is a no-op (the admin en_US session keeps the plugin's English strings).
 */
add_action( 'init', function (): void {
	$mofile = get_template_directory() . '/languages/plugins/woo-product-filter-' . determine_locale() . '.mo';
	if ( is_readable( $mofile ) ) {
		load_textdomain( 'woo-product-filter', $mofile );
	}
}, 20 );

/**
 * Croatian strings for the third-party gdpr-cookie-compliance (Moove) banner and preferences panel, same
 * mechanism as above: the plugin ships no hr file, so load our own per-locale .mo from languages/plugins/.
 * Texts saved in the plugin settings are DB data and are not affected. A missing locale file is a no-op.
 */
add_action( 'init', function (): void {
	$mofile = get_template_directory() . '/languages/plugins/gdpr-cookie-compliance-' . determine_locale() . '.mo';
	if ( is_readable( $mofile ) ) {
		load_textdomain( 'gdpr-cookie-compliance', $mofile );
	}
}, 20 );

/**
 * JS (block checkout) translations for the same plugin. Its scripts call
 * wp_set_script_translations() but the plugin ships no `hr` JSON, so strings
 * rendered in JS (e.g. " (optional)") stay English. Point only that text domain
 * at our own per-locale JSON in languages/plugins/; a missing file is a no-op.
 *
 * @param string|false $file   Translation JSON path WordPress resolved.
 * @param string       $handle Script handle.
 * @param string       $domain Text domain.
 * @return string|false
 */
add_filter(
	'load_script_translation_file',
	static function ( $file, string $handle, string $domain ) {
		if ( 'silkypress-input-field-block' !== $domain ) {
			return $file;
		}

		$json = get_template_directory() . '/languages/plugins/silkypress-input-field-block-' . determine_locale() . '.json';

		return is_readable( $json ) ? $json : $file;
	},
	10,
	3
);

/**
 * PDP shows only the native WooCommerce Description tab (long description,
 * `post_content`). WooCommerce registers it only when the product has a long
 * description, so an empty description leaves no tabs and the template renders
 * no wrapper. Additional information, Reviews and tabs added by plugins are
 * removed from the PDP tab UI only; reviews/attribute data and settings are
 * untouched. Future tabs (Specifikacije, Sadržaj paketa, Dokumenti) must be
 * added here deliberately (ADR-028).
 *
 * @param array<string, array<string, mixed>> $tabs Registered product tabs.
 * @return array<string, array<string, mixed>>
 */
function dreampoint_b2b_product_tabs( array $tabs ): array {
	$allowed = [ 'description' ];
	$tabs    = array_intersect_key( $tabs, array_flip( $allowed ) );

	// A variable product whose parent has no long description but whose selectable variations do:
	// register the same native Description tab (same callback/template) so js/product-single.js can fill it
	// with the selected variation's description. The template renders the section hidden until then.
	global $product;
	if (
		! isset( $tabs['description'] )
		&& $product instanceof WC_Product_Variable
		&& dreampoint_b2b_has_variation_description( $product )
	) {
		$tabs['description'] = [
			'title'    => __( 'Description', 'woocommerce' ),
			'priority' => 10,
			'callback' => 'woocommerce_product_description_tab',
		];
	}

	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'dreampoint_b2b_product_tabs', 99 );

/**
 * Whether at least one selectable (visible) variation of a variable product has a description.
 * Uses WooCommerce's own variation visibility rules; stops at the first description found.
 *
 * @param WC_Product_Variable $product Variable product.
 * @return bool
 */
function dreampoint_b2b_has_variation_description( WC_Product_Variable $product ): bool {
	foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
		if ( '' !== trim( (string) $variation->get_description() ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Variation descriptions are shown in the PDP Description tab (js/product-single.js), not under the
 * variation selectors: keep WooCommerce's formatted HTML in `dp_variation_description` and empty the
 * native `variation_description`, so the native `.woocommerce-variation-description` renders nothing.
 * (Only WooCommerce's own variation.php template consumes `variation_description`.)
 *
 * @param array                $data      Variation data for the add-to-cart form.
 * @param WC_Product_Variable  $parent    Parent product.
 * @param WC_Product_Variation $variation Variation.
 * @return array
 */
function dreampoint_b2b_variation_description_payload( array $data, $parent, $variation ): array {
	$data['dp_variation_description'] = trim( (string) ( $data['variation_description'] ?? '' ) );
	$data['variation_description']    = '';

	return $data;
}
add_filter( 'woocommerce_available_variation', 'dreampoint_b2b_variation_description_payload', 20, 3 );

/**
 * Cart item variation lines limited to Boja and Dioptrija (display only). Used by the main Cart and,
 * with `$limit = 2`, by the mini-cart / Quick Cart (E11).
 *
 * The filter is attached just around the call below, so checkout, emails and order
 * data keep the untouched WooCommerce item data. Cart item data, variation ID and stored attribute
 * values are not changed; entries that do not come from a variation attribute (other plugins) are kept.
 * "DIOPTRIJA +1,5" is shown as "+1,5".
 *
 * Optional `$limit` (Quick Cart / mini-cart, E11): after the same selection and ordering, only the first
 * `$limit` entries are kept. 0 (default) means no limit, so the main Cart output is unchanged.
 *
 * @param array $cart_item Cart item.
 * @param int   $limit     Maximum number of entries to display; 0 = unlimited.
 * @return string Formatted item data HTML.
 */
function dreampoint_b2b_cart_item_data_html( array $cart_item, int $limit = 0 ): string {
	$allowed = [ 'pa_boja', 'pa_dioptrija' ];

	$filter = static function ( array $item_data, array $item ) use ( $allowed, $limit ): array {
		if ( empty( $item['variation'] ) || empty( $item['data'] ) ) {
			return $limit > 0 ? array_slice( $item_data, 0, $limit ) : $item_data;
		}

		// Same label WooCommerce used for the variation rows (key => taxonomy).
		$labels = [];
		foreach ( array_keys( $item['variation'] ) as $name ) {
			$taxonomy            = str_replace( 'attribute_', '', $name );
			$labels[ wc_attribute_label( $taxonomy, $item['data'] ) ] = $taxonomy;
		}

		$result = [];
		foreach ( $item_data as $entry ) {
			$taxonomy = $labels[ $entry['key'] ?? '' ] ?? '';
			if ( '' === $taxonomy ) {
				$result[] = $entry; // Not a variation attribute: leave as is.
				continue;
			}
			if ( ! in_array( $taxonomy, $allowed, true ) ) {
				continue;
			}
			if ( 'pa_dioptrija' === $taxonomy ) {
				$entry['display'] = preg_replace( '/^\s*DIOPTRIJA\s*/i', '', wp_strip_all_tags( (string) ( $entry['display'] ?? $entry['value'] ?? '' ) ) );
			}
			$result[] = $entry;
		}

		return $limit > 0 ? array_slice( $result, 0, $limit ) : $result;
	};

	add_filter( 'woocommerce_get_item_data', $filter, 99, 2 );
	$html = wc_get_formatted_cart_item_data( $cart_item );
	remove_filter( 'woocommerce_get_item_data', $filter, 99 );

	return $html;
}

/**
 * Whether the current request belongs to the Checkout page: the first render (hydration runs while the Checkout page
 * itself is being built, `is_checkout()`) or a live Store API call made by the Checkout block, whose Referer is the
 * Checkout page. Any other Store API consumer (other pages, other clients) keeps the untouched cart response.
 */
function dreampoint_b2b_is_checkout_store_api_context(): bool {
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return true;
	}

	$referer = wp_get_raw_referer();
	if ( ! $referer || ! function_exists( 'wc_get_checkout_url' ) ) {
		return false;
	}

	$checkout_path = untrailingslashit( (string) wp_parse_url( wc_get_checkout_url(), PHP_URL_PATH ) );
	$referer_path  = untrailingslashit( (string) wp_parse_url( $referer, PHP_URL_PATH ) );

	return '' !== $checkout_path && ( $referer_path === $checkout_path || 0 === strpos( $referer_path, $checkout_path . '/' ) );
}

/**
 * Checkout order summary (Blocks / Store API): the same variation lines as the Cart.
 *
 * The Cart prints only Boja and Dioptrija (`dreampoint_b2b_cart_item_data_html()`, "DIOPTRIJA +1,5" shown as "+1,5").
 * The Checkout block renders the Store API cart item `variation` array, so the same selection and order are applied
 * to that array in the response. Display only: the cart contents, the variation ID, the stored attribute values and
 * the order item meta are not touched (the order is created from the cart, not from this response), and `item_data`
 * (entries added by other plugins) is left as is, exactly like the Cart logic keeps non-variation entries.
 *
 * Runs on `rest_request_after_callbacks` (live Store API requests) and on `woocommerce_hydration_request_after_callbacks`
 * (the preloaded cart of the first Checkout render, which bypasses the REST dispatch).
 *
 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Response.
 * @param array                                            $handler  Route handler.
 * @param WP_REST_Request                                  $request  Request.
 * @return mixed
 */
function dreampoint_b2b_store_api_cart_variation_display( $response, $handler, $request ) {
	if ( ! $response instanceof WP_HTTP_Response || ! $request instanceof WP_REST_Request ) {
		return $response;
	}
	if ( 0 !== strpos( $request->get_route(), '/wc/store/v1/cart' ) || ! dreampoint_b2b_is_checkout_store_api_context() ) {
		return $response;
	}

	$data = $response->get_data();
	if ( ! is_array( $data ) ) {
		return $response;
	}

	$allowed = [ 'attribute_pa_boja', 'attribute_pa_dioptrija' ];

	$trim_item = static function ( $item ) use ( $allowed ) {
		if ( ! is_array( $item ) || empty( $item['variation'] ) || ! is_array( $item['variation'] ) ) {
			return $item;
		}

		$kept = [];
		foreach ( $item['variation'] as $entry ) {
			$raw = is_array( $entry ) ? (string) ( $entry['raw_attribute'] ?? '' ) : '';
			if ( ! in_array( $raw, $allowed, true ) ) {
				continue;
			}
			if ( 'attribute_pa_dioptrija' === $raw && isset( $entry['value'] ) ) {
				$entry['value'] = preg_replace( '/^\s*DIOPTRIJA\s*/i', '', (string) $entry['value'] );
			}
			$kept[] = $entry;
		}
		$item['variation'] = $kept;

		return $item;
	};

	if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
		$data['items'] = array_map( $trim_item, $data['items'] );
	} elseif ( isset( $data['variation'] ) ) {
		$data = $trim_item( $data ); // Single cart item route.
	} elseif ( array_is_list( $data ) ) {
		$data = array_map( $trim_item, $data ); // Cart items list route.
	} else {
		return $response;
	}

	$response->set_data( $data );

	return $response;
}
add_filter( 'rest_request_after_callbacks', 'dreampoint_b2b_store_api_cart_variation_display', 20, 3 );
// The first Checkout render is hydrated by WooCommerce Blocks straight from the controller (no REST dispatch),
// through its own equivalent of `rest_request_after_callbacks`.
add_filter( 'woocommerce_hydration_request_after_callbacks', 'dreampoint_b2b_store_api_cart_variation_display', 20, 3 );

/**
 * Archive sorting: drop "Sort by average rating" (product ratings / reviews are not used on this shop).
 *
 * Only the dropdown option is removed through the core `woocommerce_catalog_orderby` filter; reviews and ratings stay
 * enabled and an old `?orderby=rating` link keeps working. Every other sorting option is untouched.
 *
 * @param array<string,string> $options Catalog ordering options.
 * @return array<string,string>
 */
function dreampoint_b2b_catalog_orderby_without_rating( array $options ): array {
	unset( $options['rating'] );
	return $options;
}
add_filter( 'woocommerce_catalog_orderby', 'dreampoint_b2b_catalog_orderby_without_rating' );

/**
 * Archive pagination: arrow-only previous/next controls (Figma CAT-02 pagination).
 *
 * Native WooCommerce pagination is kept as is (links, query-string handling, first/last page behaviour); only the
 * previous/next link content changes. Each arrow is a decorative icon (styled in shop-archive.scss) plus a visually
 * hidden, translatable screen-reader label, because native `prev`/`next` links have no accessible name.
 * Limited to the same archives that load shop-archive.css, so the markup is never output without its styles.
 *
 * @param array<string,mixed> $args Pagination arguments of woocommerce/loop/pagination.php.
 * @return array<string,mixed>
 */
function dreampoint_b2b_archive_pagination_args( array $args ): array {
	if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_tax( 'product_brand' ) ) ) {
		return $args;
	}

	$args['prev_text'] = '<span class="dp-pagination__icon dp-pagination__icon--prev" aria-hidden="true"></span><span class="dp-pagination__label">' . esc_html__( 'Prethodna stranica', 'dreampoint-b2b' ) . '</span>';
	$args['next_text'] = '<span class="dp-pagination__icon dp-pagination__icon--next" aria-hidden="true"></span><span class="dp-pagination__label">' . esc_html__( 'Sljedeća stranica', 'dreampoint-b2b' ) . '</span>';

	// Total page count for the compact mobile layout ("2 / 29"); hidden by CSS above 575px (display:none, so it is
	// neither visible nor exposed to assistive technology there). Native markup has no total page count to read.
	$args['after_page_number'] = '<span class="dp-pagination__total"> / ' . esc_html( number_format_i18n( (int) ( $args['total'] ?? 1 ) ) ) . '</span>';

	return $args;
}
add_filter( 'woocommerce_pagination_args', 'dreampoint_b2b_archive_pagination_args' );


/**
 * Croatian plural category of an integer: 'one' (1, 21, 31 ... but not 11), 'few' (2-4, 22-24 ... but not 12-14),
 * 'many' otherwise. Used by the cart counter (E7).
 */
function dreampoint_b2b_hr_plural_form( int $n ): string {
	$n    = abs( $n );
	$m10  = $n % 10;
	$m100 = $n % 100;

	if ( 1 === $m10 && 11 !== $m100 ) {
		return 'one';
	}

	if ( $m10 >= 2 && $m10 <= 4 && ( $m100 < 12 || $m100 > 14 ) ) {
		return 'few';
	}

	return 'many';
}

/**
 * Cart counter data (E7): total quantity of the visible cart lines and the number of DISTINCT effective SKUs.
 *
 * Effective SKU = the value the cart row shows in its SKU column: the APROS article code `_ARTICLE_CODE` of the
 * cart unit itself (the variation for variable products, never the parent). When a unit has no article code the
 * fallback identity is the product / variation ID, so unrelated products without a code are not collapsed into
 * one SKU while two cart lines of the same unit still count once. Reads only the already loaded cart data and
 * post meta that the cart template reads as well (no extra queries, nothing persisted).
 *
 * @return array{quantity:int,skus:int}
 */
function dreampoint_b2b_get_cart_counter_counts(): array {
	$cart = function_exists( 'WC' ) ? WC()->cart : null;

	if ( ! $cart instanceof WC_Cart ) {
		return [ 'quantity' => 0, 'skus' => 0 ];
	}

	$quantity = 0;
	$skus     = [];

	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'] ?? null, $cart_item, $cart_item_key );

		// Same visibility rule as woocommerce/cart/cart.php.
		if ( ! $product instanceof WC_Product || ! $product->exists() || $cart_item['quantity'] <= 0
			|| ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
			continue;
		}

		$quantity += (int) $cart_item['quantity'];

		$code                                          = trim( (string) get_post_meta( $product->get_id(), '_ARTICLE_CODE', true ) );
		$skus[ '' !== $code ? 'code:' . $code : 'id:' . $product->get_id() ] = true;
	}

	return [ 'quantity' => $quantity, 'skus' => count( $skus ) ];
}

/**
 * Cart counter text, for example "6 artikala | 3 SKU-a" (Figma CART-02 11148:38464).
 */
function dreampoint_b2b_get_cart_counter_text(): string {
	$counts = dreampoint_b2b_get_cart_counter_counts();

	$items = [
		'one'  => __( 'artikal', 'dreampoint-b2b' ),
		'few'  => __( 'artikla', 'dreampoint-b2b' ),
		'many' => __( 'artikala', 'dreampoint-b2b' ),
	][ dreampoint_b2b_hr_plural_form( $counts['quantity'] ) ];

	$sku = 'one' === dreampoint_b2b_hr_plural_form( $counts['skus'] )
		? __( 'SKU', 'dreampoint-b2b' )
		: __( 'SKU-a', 'dreampoint-b2b' );

	return sprintf( '%1$d %2$s | %3$d %4$s', $counts['quantity'], $items, $counts['skus'], $sku );
}
