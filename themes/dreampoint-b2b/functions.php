<?php
/**
 * Dreampoint B2B — Theme Functions
 *
 * Stack: LiteSpeed Cache + Redis Object Cache + Cloudflare + Safe SVG + Converter for Media
 *
 * Defer/async strategija:
 *   - Theme-specific skripte: PHP-level wp_script_add_data('strategy','defer')
 *   - Plugin/WooCommerce skripte: prepušteno LSCache JS Defer u admin panelu
 *   - Razlog: LSCache procesira finalni HTML output i pouzdanije obrađuje resurse trećih strana
 *
 * wp_is_mobile() je proširen filterom koji dodaje detekciju iOS desktop moda.
 * dreampoint_b2b_is_below_lg() koristi CF-Device-Type header na stagingu/prod, fallback lokalno.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

// ============================================================================
// KONSTANTE
// ============================================================================

if ( ! defined( '_S_VERSION' ) ) {
    define( '_S_VERSION', (string) max(
        filemtime( get_template_directory() . '/style.css' ),
        filemtime( get_template_directory() . '/js/theme.min.js' )
    ) );
}

// ============================================================================
// DEVICE DETECTION
// ============================================================================

/**
 * Proširuje wp_is_mobile() za iOS uređaje u desktop modu.
 *
 * Problem: iPhone/iPad u "Request Desktop Site" modu šalju UA bez "Mobile" stringa,
 * pa ih wp_is_mobile() ne prepoznaje. Ovaj filter to ispravlja i utiče na sve
 * pozive wp_is_mobile() u WP core-u i dodacima.
 */
add_filter( 'wp_is_mobile', function ( bool $is_mobile ): bool {
    if ( $is_mobile ) return true;

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return str_contains( $ua, 'iPhone' ) || str_contains( $ua, 'iPad' );
} );

/**
 * Detektuje mobilne i tablet uređaje za server-side uslove.
 *
 * Prioritet:
 *   1. Cloudflare CF-Device-Type header — pouzdan, dostupan na stagingu/produkciji
 *   2. wp_is_mobile() (uključuje iOS ispravku iznad) + prošireni tablet UA regex — rezerva za lokalni razvoj
 *
 * Napomena: Koristiti samo za PHP-side uslove (uslovni enqueue, delovi template-a).
 * Layout odluke rešavati CSS media query-jima.
 */
function dreampoint_b2b_is_below_lg(): bool {
    $cf_device = $_SERVER['HTTP_CF_DEVICE_TYPE'] ?? '';
    if ( $cf_device !== '' ) {
        return in_array( $cf_device, [ 'mobile', 'tablet' ], true );
    }

    if ( wp_is_mobile() ) return true;

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return (bool) preg_match( '/iPad|Android(?!.*Mobile)|Tablet|PlayBook|Silk/i', $ua );
}

// ============================================================================
// THEME SETUP
// ============================================================================

add_action( 'after_setup_theme', 'dreampoint_b2b_setup' );
function dreampoint_b2b_setup(): void {
    load_theme_textdomain( 'dreampoint-b2b', get_template_directory() . '/languages' );

    foreach ( [ 'automatic-feed-links', 'title-tag', 'post-thumbnails' ] as $feature ) {
        add_theme_support( $feature );
    }

    add_theme_support( 'html5', [
        'search-form', 'comment-form', 'comment-list',
        'gallery', 'caption', 'style', 'script',
    ] );

    register_nav_menus( [ 'menu-1' => esc_html__( 'Primary', 'dreampoint-b2b' ) ] );

    // Prilagođene veličine slika.
    // Nakon dodavanja nove veličine pokrenuti: wp media regenerate --yes (WP-CLI)
    add_image_size( 'product-loop',    340, 462, true );
    add_image_size( 'category-loop',   460, 495, true );
    add_image_size( 'about-photo',     700, 605, true );
    add_image_size( 'gallery_thumb',   400, 300, true );
    add_image_size( 'category-slider', 460, 307, true ); // koristiti u slider template-u
}

add_action( 'after_setup_theme', 'dreampoint_b2b_content_width', 0 );
function dreampoint_b2b_content_width(): void {
    $GLOBALS['content_width'] = apply_filters( 'dreampoint_b2b_content_width', 640 );
}

// ============================================================================
// UKLANJANJE NEPOTREBNIH <head> ELEMENATA
// ============================================================================

/**
 * Uklanja WordPress meta tagove koji nisu potrebni za WooCommerce prodavnicu.
 * Smanjuje veličinu HTML-a i skriva WordPress fingerprint.
 * Svi remove_action pozivi su na najvišem nivou — semantički ispravno.
 */
remove_action( 'wp_head', 'feed_links',              2  );
remove_action( 'wp_head', 'feed_links_extra',        3  );
remove_action( 'wp_head', 'wp_generator'                );
remove_action( 'wp_head', 'wlwmanifest_link'            );
remove_action( 'wp_head', 'rsd_link'                    );
remove_action( 'wp_head', 'rest_output_link_wp_head'    );
remove_action( 'wp_head', 'wp_shortlink_wp_head',   10  );

// Emoji — uklanjamo JS (23 kB) + inline skriptu; nije potrebno na prodavnici
remove_action( 'wp_head',          'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles',  'print_emoji_styles'              );
remove_filter( 'the_content_feed', 'wp_staticize_emoji'               );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji'               );
remove_filter( 'wp_mail',          'wp_staticize_emoji_for_email'     );

// ============================================================================
// INCLUDES
// ============================================================================

require     get_template_directory() . '/inc/template-tags.php';
require     get_template_directory() . '/inc/custom-breadcrumb.php';
require     get_template_directory() . '/inc/my-account.php';
require_once get_template_directory() . '/blocks/products-tabs.php';
require_once get_template_directory() . '/inc/product-inquiry.php';

if ( class_exists( 'WooCommerce' ) ) {
    require get_template_directory() . '/inc/woocommerce.php';
    require get_template_directory() . '/inc/b2b-registration.php';
    require get_template_directory() . '/inc/registration-approval.php';
    add_filter( 'woocommerce_email_classes', function( array $email_classes ): array {
        require_once get_template_directory() . '/inc/emails.php';
        return $email_classes;
    }, 5 );
    require get_template_directory() . '/inc/visibility.php';
    require get_template_directory() . '/inc/checkout-logic.php';
    require get_template_directory() . '/inc/ajax-handlers.php';
    require get_template_directory() . '/inc/brand-hero.php';
    require get_template_directory() . '/inc/wbw-multi-search-compat.php';
}

require get_template_directory() . '/inc/nav-categories.php';
require get_template_directory() . '/inc/enqueue-block-styles.php';
require get_template_directory() . '/inc/enqueue-plugin-overrides.php';
require get_template_directory() . '/inc/enqueue-navigation.php';

// ACF Blokovi — učitavamo sve blokove iz /blocks/ osim index.php
if ( class_exists( 'ACF' ) ) {
    add_action( 'after_setup_theme', function (): void {
        foreach ( glob( __DIR__ . '/blocks/*.php' ) as $block_path ) {
            if ( ! str_ends_with( $block_path, 'index.php' ) ) {
                include_once $block_path;
            }
        }
    }, 5 );
}

// Dev tooling — WP-CLI only; zero overhead on web and admin requests.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require get_template_directory() . '/inc/dev/dev-tools.php';
}

// ============================================================================
// HELPER FUNKCIJE
// ============================================================================

/**
 * Da li trenutna stranica zahteva Select2?
 * Koristi se za uslovni enqueue Select2 JS i CSS.
 *
 * Shop/archive stranice su uključene jer header-shop-archive.php renderuje
 * .sort-area select (woocommerce_catalog_ordering) koji se inicijalizuje
 * u select2-init.js. is_woocommerce() nije korišten jer uključuje i
 * is_product() — single product stranice imaju zasebnu potrebu za Select2
 * (.variations select) i mogu se dodati odvojeno po potrebi.
 */
function dreampoint_b2b_needs_select2(): bool {
    return is_cart()                         ||
           is_checkout()                     ||
           is_account_page()                 ||
           is_wc_endpoint_url()              ||
           is_page_template( 'contact.php' ) ||
           is_shop()                         ||
           is_product_category()             ||
           is_product_tag()                  ||
           is_tax( 'product_brand' )         ||
           dreampoint_b2b_akcija_page_detected();
}

/**
 * Da li trenutna stranica zahteva FancyBox galeriju?
 * Proizvod, blog post i naslovna — jedini konteksti s prikazom galerije.
 */
function dreampoint_b2b_needs_fancybox(): bool {
    return is_product() || is_singular( 'post' ) || is_front_page();
}

/**
 * Da li trenutna stranica zahteva Toastify toast obaveštenja?
 * Sve stranice s akcijom "dodaj u košaricu".
 */
function dreampoint_b2b_needs_toastify(): bool {
    return is_front_page()            ||
           is_product()               ||
           is_shop()                  ||
           is_product_category()      ||
           is_tax( 'product_brand' )  ||
           is_page( 'quick-order' );
}

/**
 * Da li trenutna stranica zahteva Slick Slider?
 * Naslovna, blog arhiva, blog post, stranica proizvoda.
 */
function dreampoint_b2b_needs_slick(): bool {
    return is_front_page() || is_singular( 'post' ) || is_home() || is_product();
}

/**
 * Ispisuje ACF link polje kao <a> tag.
 *
 * @param array|null $link       ACF link niz (url, title, target)
 * @param string     $class_name CSS klasa za <a> tag
 */
function the_acf_link( ?array $link = null, string $class_name = '' ): void {
    if ( empty( $link['url'] ) ) return;

    printf(
        '<a class="%s" href="%s" title="%s" target="%s">%s</a>',
        esc_attr( $class_name ),
        esc_url( $link['url'] ),
        esc_attr( $link['title'] ?? '' ),
        esc_attr( $link['target'] ?? '_self' ),
        esc_html( $link['title'] ?? '' )
    );
}

/**
 * Prikazuje poruku uredniku kada je ACF blok prazan.
 * Vidljivo samo korisnicima sa edit_posts pravima.
 *
 * @param array $block ACF blok niz (title, ...)
 */
function empty_block( array $block = [] ): void {
    if ( ! current_user_can( 'edit_posts' ) ) return;

    printf(
        '<p class="empty-block">Block "%s" is empty <small>(only editors see this)</small></p>',
        esc_html( $block['title'] ?? 'unknown' )
    );
}

/**
 * Omotač za Relevanssi "Did you mean?" sugestiju.
 *
 * @param string|null $search_string Pojam za pretragu
 * @return string HTML sugestija ili prazan string
 */
function did_you_mean( ?string $search_string = null ): string {
    if ( ! function_exists( 'relevanssi_didyoumean' ) ) return '';

    $search_string = $search_string ?: get_search_query( false );

    return relevanssi_didyoumean(
        $search_string,
        '<p>' . esc_html__( 'Da li ste mislili:', 'dreampoint-b2b' ) . ' ',
        '</p>',
        5,
        false
    );
}

// ============================================================================
// ENQUEUE — FONT PRELOADS
// ============================================================================

/**
 * Kritični font preloadi — fontovi koji se koriste iznad preloma stranice.
 * rel="preload" govori pregledaču da ih preuzme odmah, bez čekanja na CSS parser.
 * crossorigin je obavezan za woff2 čak i kada je font na istom domenu.
 *
 * ⚠️  Ažurirati listu prema stvarnim fontovima projekta.
 *     Previše preload-ova (>4-5) je kontraproduktivno — pregledač gubi prioritizaciju.
 *     Preloadati samo fontove vidljive u prvom prikazu stranice (body, nav, h1).
 */
add_action( 'wp_head', 'dreampoint_b2b_font_preloads', 2 );
function dreampoint_b2b_font_preloads(): void {
    $uri = get_template_directory_uri();

    $fonts = [
        // Dodati putanje do woff2 fontova projekta, npr:
        // '/fonts/primary-regular.woff2',
        // '/fonts/primary-semibold.woff2',
        // '/fonts/heading-bold.woff2',
    ];

    foreach ( $fonts as $font ) {
        printf(
            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
            esc_url( $uri . $font )
        );
    }
}

// ============================================================================
// ENQUEUE — SKRIPTE I STILOVI
// ============================================================================

add_action( 'wp_enqueue_scripts', 'dreampoint_b2b_scripts' );
function dreampoint_b2b_scripts(): void {

    // --- CSS ---
    wp_enqueue_style( 'dp-style', get_stylesheet_uri(), [], _S_VERSION );
    wp_style_add_data( 'dp-style', 'rtl', 'replace' );

    // --- CSS: Page-specific styles ---
    if ( is_cart() ) {
        wp_enqueue_style( 'dp-page-cart', get_template_directory_uri() . '/css/pages/cart.css', [ 'dp-style' ], _S_VERSION );
    }
    if ( is_checkout() ) {
        wp_enqueue_style( 'dp-page-checkout', get_template_directory_uri() . '/css/pages/checkout.css', [ 'dp-style' ], _S_VERSION );
    }
    if ( is_account_page() ) {
        wp_enqueue_style( 'dp-page-myaccount', get_template_directory_uri() . '/css/pages/myaccount.css', [ 'dp-style' ], _S_VERSION );
    }
    if ( is_shop() || is_product_category() || is_product_tag() || is_tax( 'product_brand' ) ) {
        wp_enqueue_style( 'dp-page-shop-archive', get_template_directory_uri() . '/css/pages/shop-archive.css', [ 'dp-style' ], _S_VERSION );
    }
    if ( is_product() ) {
        wp_enqueue_style( 'dp-page-shop-single', get_template_directory_uri() . '/css/pages/shop-single.css', [ 'dp-style' ], _S_VERSION );
    }
    if ( is_page_template( 'brands.php' ) || is_tax( 'product_brand' ) ) {
        wp_enqueue_style( 'dp-page-brands', get_template_directory_uri() . '/css/pages/brands.css', [ 'dp-style' ], _S_VERSION );
    }
    if ( function_exists( 'tinv_wishlist_is_wishlist' ) && tinv_wishlist_is_wishlist() ) {
        wp_enqueue_style( 'dp-page-wishlist', get_template_directory_uri() . '/css/pages/wishlist.css', [ 'dp-style' ], _S_VERSION );
    }

    // --- JS: Core ---
    wp_enqueue_script( 'jquery' );

    // Passive touch event override — dozvoljava preventDefault() u swipe handlers
    // (npr. Slick Carousel). passive: false je namerno — passive: true bi pokvarilo
    // swipe geste na touch uređajima.
    wp_add_inline_script( 'jquery', "
        jQuery.event.special.touchstart = {
            setup: function( _, ns, handle ) {
                this.addEventListener( 'touchstart', handle, { passive: false } );
            }
        };
        jQuery.event.special.touchmove = {
            setup: function( _, ns, handle ) {
                this.addEventListener( 'touchmove', handle, { passive: false } );
            }
        };
    " );

    // FancyBox — samo na stranicama gde se galerija koristi
    if ( dreampoint_b2b_needs_fancybox() ) {
        wp_enqueue_style(
            'dreampoint-b2b-fancybox',
            get_template_directory_uri() . '/css/src/fancybox.min.css',
            [],
            _S_VERSION
        );
        wp_enqueue_script(
            'dreampoint-b2b-fancybox',
            get_template_directory_uri() . '/js/fancybox.umd.js',
            [],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-fancybox', 'strategy', 'defer' );

        wp_enqueue_script(
            'dreampoint-b2b-fancybox-init',
            get_template_directory_uri() . '/js/fancybox-init.js',
            [ 'dreampoint-b2b-fancybox' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-fancybox-init', 'strategy', 'defer' );
    }

    // Glavni theme JS
    wp_enqueue_script(
        'dreampoint-b2b-scripts',
        get_template_directory_uri() . '/js/theme.min.js',
        [ 'jquery' ],
        _S_VERSION,
        true
    );
    wp_script_add_data( 'dreampoint-b2b-scripts', 'strategy', 'defer' );

    // Select2 — samo na stranicama koje koriste dropdown select elemente
    if ( dreampoint_b2b_needs_select2() ) {
        wp_enqueue_style(
            'dreampoint-b2b-select2',
            get_template_directory_uri() . '/css/src/select2.min.css',
            [],
            _S_VERSION
        );
        wp_enqueue_script(
            'dreampoint-b2b-select2',
            get_template_directory_uri() . '/js/select2.min.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-select2', 'strategy', 'defer' );

        wp_enqueue_script(
            'dreampoint-b2b-select2-init',
            get_template_directory_uri() . '/js/select2-init.js',
            [ 'dreampoint-b2b-select2' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-select2-init', 'strategy', 'defer' );
    }

    // --- JS: AJAX search ---
    // nonce se prenosi u JS kao dpAjax.nonce
    wp_enqueue_script(
        'dreampoint-b2b-ajax-search',
        get_template_directory_uri() . '/js/ajax-search.js',
        [ 'jquery' ],
        _S_VERSION,
        true
    );
    wp_script_add_data( 'dreampoint-b2b-ajax-search', 'strategy', 'defer' );
    wp_localize_script( 'dreampoint-b2b-ajax-search', 'dpAjax', [
        'url'      => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'dp_search_nonce' ),
        'loading'  => esc_html__( 'Pretraga...', 'dreampoint-b2b' ),
        'minChars' => esc_html__( 'Unesite najmanje 2 znaka...', 'dreampoint-b2b' ),
        'noResults'=> esc_html__( 'Nema rezultata pretrage.', 'dreampoint-b2b' ),
        'timeout'  => esc_html__( 'Pretraga traje predugo. Pokušajte ponovo.', 'dreampoint-b2b' ),
        'error'    => esc_html__( 'Greška pri pretrazi. Pokušajte ponovo.', 'dreampoint-b2b' ),
    ] );

    // --- CSS/JS: Toastify — obaveštenja za dodavanje u korpu ---
    // Učitava se na stranicama gde korisnik može da dodaje proizvode u korpu
    if ( dreampoint_b2b_needs_toastify() ) {
        wp_enqueue_style(
            'dreampoint-b2b-toastify',
            get_template_directory_uri() . '/css/src/toastify.min.css',
            [],
            _S_VERSION
        );
        wp_enqueue_script(
            'dreampoint-b2b-toastify',
            get_template_directory_uri() . '/js/toastify.min.js',
            [],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-toastify', 'strategy', 'defer' );
    }

    // --- JS: AJAX dodavanje u korpu ---
    // Ne učitava se na account stranicama za nelogovane korisnike (login/register)
    if ( ! is_account_page() || is_user_logged_in() ) {
        wp_enqueue_script(
            'dreampoint-b2b-ajax-add-to-cart',
            get_template_directory_uri() . '/js/ajax-add-to-cart.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-ajax-add-to-cart', 'strategy', 'defer' );
        wp_localize_script( 'dreampoint-b2b-ajax-add-to-cart', 'dpAddToCart', [
            'added_to_cart' => esc_html__( 'Proizvod dodat u košaricu!', 'dreampoint-b2b' ),
        ] );
    }

    // --- JS: Kontrola količine u korpi — samo na stranici korpe ---
    if ( is_cart() ) {
        wp_enqueue_script(
            'dreampoint-b2b-cart-quantity',
            get_template_directory_uri() . '/js/cart-quantity.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-cart-quantity', 'strategy', 'defer' );
        wp_localize_script( 'dreampoint-b2b-cart-quantity', 'dpCartQty', [
            'maxQty' => esc_html__( 'Maksimalna količina:', 'dreampoint-b2b' ),
        ] );
    }

    // --- CSS/JS: Slick Slider ---
    if ( dreampoint_b2b_needs_slick() ) {
        wp_enqueue_style(
            'dreampoint-b2b-slick',
            get_template_directory_uri() . '/css/src/slick.min.css',
            [],
            _S_VERSION
        );
        wp_enqueue_style(
            'dreampoint-b2b-slick-theme',
            get_template_directory_uri() . '/css/vendors/slick.css',
            [ 'dreampoint-b2b-slick' ],
            _S_VERSION
        );
        wp_enqueue_script(
            'dreampoint-b2b-slick',
            get_template_directory_uri() . '/js/slick.min.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-slick', 'strategy', 'defer' );

        wp_enqueue_script(
            'dreampoint-b2b-slick-init',
            get_template_directory_uri() . '/js/slick-init.js',
            [ 'dreampoint-b2b-slick' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-slick-init', 'strategy', 'defer' );
        wp_localize_script( 'dreampoint-b2b-slick-init', 'dpSlick', [
            'sliderLabel' => esc_html__( 'Slider', 'dreampoint-b2b' ),
        ] );
    }

    // --- JS: Mobilni filteri — isti uslov kao WPF plugin (shop, taksonomije, pretraga) ---
    if ( is_shop() || is_product_taxonomy() || is_search() ) {
        wp_enqueue_script(
            'dreampoint-b2b-mobile-product-filters',
            get_template_directory_uri() . '/js/mobile-product-filters.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-mobile-product-filters', 'strategy', 'defer' );
    }

    // --- JS: B2B registracija — višekorački obrazac, samo za nelogirane na account stranicama ---
    if ( is_account_page() && ! is_user_logged_in() ) {
        wp_enqueue_script(
            'dreampoint-b2b-b2b-registration',
            get_template_directory_uri() . '/js/b2b-registration.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-b2b-registration', 'strategy', 'defer' );
        wp_localize_script( 'dreampoint-b2b-b2b-registration', 'dpB2bRegister', [
            'invalidEmail'    => esc_html__( 'Molimo unesite ispravnu e-mail adresu.', 'dreampoint-b2b' ),
            'postcodeShort'   => esc_html__( 'Poštanski broj mora sadržati točno 5 cifara.', 'dreampoint-b2b' ),
            'postcodePattern' => esc_html__( 'Poštanski broj mora biti 5 cifara (samo brojevi).', 'dreampoint-b2b' ),
            'requiredFields'  => esc_html__( 'Molimo vas da ispravno ispunite sva obavezna polja.', 'dreampoint-b2b' ),
        ] );
    }

    // --- JS: Navigacija naloga — samo na my account stranicama za ulogovane korisnike ---
    if ( is_account_page() && is_user_logged_in() ) {
        wp_enqueue_script(
            'dreampoint-b2b-account-navigation',
            get_template_directory_uri() . '/js/account-navigation.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-account-navigation', 'strategy', 'defer' );
    }

    // --- JS: FAQ accordion i tabovi — samo na faq.php template-u ---
    if ( is_page_template( 'faq.php' ) ) {
        wp_enqueue_script(
            'dreampoint-b2b-faq',
            get_template_directory_uri() . '/js/faq.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-faq', 'strategy', 'defer' );
    }

    // --- JS: Stranica proizvoda ---
    if ( is_product() ) {
        wp_enqueue_script(
            'dreampoint-b2b-product-single',
            get_template_directory_uri() . '/js/product-single.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-product-single', 'strategy', 'defer' );
        wp_localize_script( 'dreampoint-b2b-product-single', 'dpProductSingle', [
            'maxQty' => esc_html__( 'Maksimalna količina:', 'dreampoint-b2b' ),
        ] );

        // Prikaz dostupnosti varijacija — prikazuje in/out stock poruku pri odabiru varijacije
        wp_enqueue_script(
            'dreampoint-b2b-variation-stock',
            get_template_directory_uri() . '/js/variation-stock.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-variation-stock', 'strategy', 'defer' );
        wp_localize_script( 'dreampoint-b2b-variation-stock', 'stockText', [
            'inStock'  => esc_html__( 'Proizvod na stanju', 'dreampoint-b2b' ),
            'outStock' => esc_html__( 'Proizvod nije na stanju', 'dreampoint-b2b' ),
        ] );

        // WooCommerce tabovi → select dropdown na mobilnim uređajima (< 767px)
        wp_enqueue_script(
            'dreampoint-b2b-tabs-dropdown',
            get_template_directory_uri() . '/js/tabs-dropdown.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-tabs-dropdown', 'strategy', 'defer' );
    }

    // Komentari
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}

/**
 * LCP optimizacija — fetchpriority="high" + loading="eager" za prvih 4 slike proizvoda
 * na shop/category/brand stranicama (iznad preloma stranice).
 *
 * Pregledač po podrazumevanoj vrednosti lazy-loada sve slike — ovo eksplicitno označava prvih 4
 * kao prioritetne, što direktno poboljšava LCP rezultat.
 * static $count osigurava da se brojač ne resetuje između poziva u istom zahtevu.
 *
 * Primenjuje se samo na 'product-loop' veličinu — ostale slike (npr. baneri)
 * imaju sopstvenu logiku prioriteta.
 */
add_filter( 'wp_get_attachment_image_attributes', function ( array $attr, WP_Post $attachment, $size ): array {
    if ( $size !== 'product-loop' ) return $attr;
    if ( ! is_shop() && ! is_product_category() && ! is_tax( 'product_brand' ) ) return $attr;

    static $count = 0;
    $count++;

    if ( $count <= 4 ) {
        $attr['fetchpriority'] = 'high';
        $attr['loading']       = 'eager';
    }

    return $attr;
}, 10, 3 );

// ============================================================================
// WOOCOMMERCE — QUERY OPTIMIZACIJA
// ============================================================================

/**
 * Optimizuje WooCommerce upit na shop/archive stranicama.
 *
 * update_post_meta_cache i update_post_term_cache su uključeni po podrazumevanoj vrednosti i
 * izvršavaju dodatne JOIN upite za svaki post u petlji.
 * Na shop stranicama gde ne koristimo prilagođeni meta u glavnom upitu, možemo
 * ih isključiti — WC objekti proizvoda učitavaju meta po potrebi interno.
 *
 * ⚠️  Revertovati ako koristite prilagođene meta query filtere u glavnom WP upitu
 *     ili ako primetite da atributi/kategorije proizvoda nedostaju.
 */
add_action( 'pre_get_posts', 'dreampoint_b2b_optimize_product_query' );
function dreampoint_b2b_optimize_product_query( WP_Query $query ): void {
    if ( ! is_admin() && $query->is_main_query() && ( is_shop() || is_product_category() || is_product_tag() ) ) {
        $query->set( 'update_post_meta_cache', false );
        $query->set( 'update_post_term_cache', false );
    }
}

// ============================================================================
// SELECT2 ADMIN BAR FIX
// ============================================================================

/**
 * Select2 padajući meni se pozicionira ispod admin bara na WooCommerce stranicama.
 * Inline CSS je opravdan — vezan je za dinamičan WP admin bar (32px/46px).
 */
add_action( 'wp_head', 'dreampoint_b2b_select2_admin_bar_fix' );
function dreampoint_b2b_select2_admin_bar_fix(): void {
    if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) return;
    if ( ! is_admin_bar_showing() ) return;

    $type_attr = current_theme_supports( 'html5', 'style' ) ? '' : ' type="text/css"';
    ?>
    <style<?php echo $type_attr; ?> media="screen">
        div#wpadminbar ~ span.select2-container,
        body.admin-bar > span.select2-container {
            padding-top: 32px;
        }
        .woocommerce-edit-account > span.select2-container,
        .woocommerce-cart > span.select2-container,
        .woocommerce-checkout > span.select2-container {
            padding-top: 0 !important;
        }
        @media screen and (max-width: 782px) {
            div#wpadminbar ~ span.select2-container,
            body.admin-bar > span.select2-container {
                padding-top: 46px;
            }
            .woocommerce-cart > span.select2-container {
                padding-top: 0 !important;
            }
        }
    </style>
    <?php
}

// ============================================================================
// WOOCOMMERCE — PRODUCT RIBBON
// ============================================================================

/**
 * Prikazuje oznaku proizvoda (ribbon) po prioritetu:
 *   1. Nema na zalihi
 *   2. Popust (prikazuje % iznos)
 *   3. Izdvojeno
 *   4. Novo (unutar 10 dana)
 *
 * Upotreba: add_action('woocommerce_before_shop_loop_item_title', 'display_new_product_ribbon_and_discount', 5)
 * ili direktno u template-u.
 */
function display_new_product_ribbon_and_discount(): void {
    global $product;

    if ( ! $product instanceof WC_Product ) return;

    // 1. Nema na lageru
    if ( ! $product->is_in_stock() ) {
        echo '<span class="tag tag--out-of-stock">' . esc_html__( 'Nema na zalihi', 'dreampoint-b2b' ) . '</span>';
        return;
    }

    // 2. Popust
    if ( $product->is_on_sale() ) {
        $regular = $product->is_type( 'variable' )
            ? (float) $product->get_variation_regular_price( 'max' )
            : (float) $product->get_regular_price();

        $sale = $product->is_type( 'variable' )
            ? (float) $product->get_variation_sale_price( 'min' )
            : (float) $product->get_sale_price();

        if ( $regular > 0 && $sale >= 0 ) {
            $discount = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
            printf( '<span class="tag tag--discount">-%d%%</span>', $discount );
        }
        return;
    }

    // 3. Izdvojeno
    if ( $product->is_featured() ) {
        echo '<span class="tag tag--featured">' . esc_html__( 'Izdvojeno', 'dreampoint-b2b' ) . '</span>';
        return;
    }

    // 4. Novo (unutar 10 dana)
    $created = $product->get_date_created();
    if ( $created ) {
        $days_old = ( time() - $created->getTimestamp() ) / DAY_IN_SECONDS;
        if ( $days_old <= 10 ) {
            echo '<span class="tag tag--new">' . esc_html__( 'Novo', 'dreampoint-b2b' ) . '</span>';
        }
    }
}

add_filter( 'the_content', function( string $content ): string {
    if ( is_checkout() && has_block( 'woocommerce/checkout' ) ) {
        $content = '<div class="container custom-form">' . $content . '</div>';
    }
    return $content;
} );

/**
 * Enqueue i lokalizacija B2B checkout info skripte.
 * Prikazuje readonly podatke tvrtke koje korisnik ne može mijenjati na checkout-u.
 * Labeli se lokalizuju ovdje kako bi bili prevodivi via .po/.mo fajlove.
 */
add_action( 'wp_enqueue_scripts', function(): void {
    if ( ! is_checkout() ) {
        return;
    }

    $user_id = get_current_user_id();

    wp_enqueue_script(
        'dreampoint-b2b-checkout-info',
        get_template_directory_uri() . '/js/checkout-b2b-info.js',
        [],
        _S_VERSION,
        true
    );
    wp_script_add_data( 'dreampoint-b2b-checkout-info', 'strategy', 'defer' );

    wp_localize_script(
        'dreampoint-b2b-checkout-info',
        'dpCheckoutB2B',
        [
            'billing_company' => esc_html( get_user_meta( $user_id, 'billing_company', true ) ),
            'company_oib'     => esc_html( get_user_meta( $user_id, 'billing_oib', true ) ),
            'phone'           => esc_html( get_user_meta( $user_id, 'billing_phone', true ) ),
            'billing_address' => esc_html( get_user_meta( $user_id, 'billing_address_1', true ) ),
            'postcode'        => esc_html( get_user_meta( $user_id, 'billing_postcode', true ) ),
            'city'            => esc_html( get_user_meta( $user_id, 'billing_city', true ) ),
            'country'         => esc_html(
                WC()->countries->get_countries()[ get_user_meta( $user_id, 'billing_country', true ) ] ?? ''
            ),
            'label_company_section' => __( 'Podaci tvrtke', 'dreampoint-b2b' ),
            'label_company_name'    => __( 'Naziv tvrtke', 'dreampoint-b2b' ),
            'label_oib'             => __( 'OIB', 'dreampoint-b2b' ),
            'label_phone'           => __( 'Telefon', 'dreampoint-b2b' ),
            'label_address_section' => __( 'Adresa naplate', 'dreampoint-b2b' ),
            'label_address'         => __( 'Adresa', 'dreampoint-b2b' ),
            'label_postcode'        => __( 'Poštanski broj', 'dreampoint-b2b' ),
            'label_city'            => __( 'Grad', 'dreampoint-b2b' ),
            'label_country'         => __( 'Država', 'dreampoint-b2b' ),
        ]
    );
} );

// ============================================================================
// SHORTCODE — COMPANY FEATURES
// ============================================================================

/**
 * Prikazuje prednosti kompanije iz ACF opcija.
 * Upotreba: [company_features]
 */
add_shortcode( 'company_features', 'dreampoint_b2b_company_features_shortcode' );
function dreampoint_b2b_company_features_shortcode(): string {
    $features_items = get_field( 'features_items', 'option' );

    if ( empty( $features_items ) || ! is_array( $features_items ) ) return '';

    ob_start();
    ?>
    <div class="features block">
        <div class="container">
            <div class="features-content company-features-slider">
                <?php foreach ( $features_items as $item ) :
                    $item_title       = $item['title'] ?? '';
                    $item_description = $item['text']  ?? '';
                    $item_image       = $item['image']  ?? null;

                    if ( empty( $item_title ) && empty( $item_description ) ) continue;
                ?>
                    <div class="item">
                        <?php if ( ! empty( $item_image['url'] ) ) : ?>
                            <div class="features-icon">
                                <img
                                    src="<?php echo esc_url( $item_image['url'] ); ?>"
                                    alt="<?php echo esc_attr( $item_image['alt'] ?: $item_title ?: __( 'Ikonica', 'dreampoint-b2b' ) ); ?>"
                                    width="<?php echo isset( $item_image['width'] )  ? absint( $item_image['width'] )  : ''; ?>"
                                    height="<?php echo isset( $item_image['height'] ) ? absint( $item_image['height'] ) : ''; ?>"
                                    loading="lazy"
                                >
                            </div>
                        <?php endif; ?>
                        <div class="features-text">
                            <?php if ( ! empty( $item_title ) ) : ?>
                                <div class="features-title"><?php echo esc_html( $item_title ); ?></div>
                            <?php endif; ?>
                            <?php if ( ! empty( $item_description ) ) : ?>
                                <p><?php echo esc_html( $item_description ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================================
// ADMIN / EDITOR FIXES
// ============================================================================

/**
 * Ispravka varijacija wishlist-e za administratore na frontend stranicama proizvoda.
 * Sprečava pogrešno ponašanje tinvwl wishlist dodatka pri pregledu varijacija.
 *
 * Inline skripta je opravdana — uslov je isključivo PHP-side (admin + stranica proizvoda),
 * kod je minimalan i ne sadrži korisničke podatke.
 */
add_action( 'wp_footer', 'dreampoint_b2b_admin_wishlist_variation_fix', 999 );
function dreampoint_b2b_admin_wishlist_variation_fix(): void {
    if ( ! current_user_can( 'administrator' ) || is_admin() || ! is_product() ) return;
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('form.variations_form').off('found_variation.tinvwl').off('reset_data.tinvwl');
    });
    </script>
    <?php
}
