<?php
/**
 * Result Count
 *
 * Shows text: Showing x - x of x results.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/result-count.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="woocommerce-result-count" role="alert" aria-relevant="all" <?php echo ( empty( $orderedby ) || 1 === intval( $total ) ) ? '' : 'data-is-sorted-by="true"'; ?>>
	<?php
	// phpcs:disable WordPress.Security
	if ( 1 === intval( $total ) ) {
		_e( '1 Product', 'woocommerce' );
	} elseif ( $total <= $per_page || -1 === $per_page ) {
		$orderedby_placeholder = empty( $orderedby ) ? '%2$s' : '<span class="screen-reader-text">%2$s</span>';
		/* translators: 1: total results 2: sorted by */
		printf( _n( 'All %1$d products', 'All %1$d products', $total, 'woocommerce' ) . $orderedby_placeholder, $total, esc_html( $orderedby ) );
	} else {
		$first                 = ( $per_page * $current ) - $per_page + 1;
		$last                  = min( $total, $per_page * $current );
		$orderedby_placeholder = empty( $orderedby ) ? '%4$s' : '<span class="screen-reader-text">%4$s</span>';
		/* translators: 1: first result 2: last result 3: total results 4: sorted by */
		printf( _nx( '%3$d products', '%3$d products', $total, 'with first and last result', 'woocommerce' ) . $orderedby_placeholder, $first, $last, $total, esc_html( $orderedby ) );
	}
	// phpcs:enable WordPress.Security
	?>
</p>
