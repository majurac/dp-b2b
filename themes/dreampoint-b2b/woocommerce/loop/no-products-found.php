<?php
/**
 * Displayed when no products are found matching the current query.
 *
 * Override of woocommerce/templates/loop/no-products-found.php: delegates to the shared
 * CAT-05 component so search, archive filters and WBW AJAX responses render identically.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

get_template_part( 'template-parts/no-results' );
