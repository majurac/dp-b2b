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
	wp_enqueue_style( 'dreampoint-b2b-woocommerce-style', get_template_directory_uri() . '/css/pages/woocommerce.css', array(), _S_VERSION );

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
