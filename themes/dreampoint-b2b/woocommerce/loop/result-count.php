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
	// Croatian count label with hr plural forms (1, 21, 31 ... "proizvod", otherwise "proizvoda").
	// The former English msgids in the `woocommerce` domain did not exist in the hr pack; the Croatian msgids
	// live in the theme domain. Output is the same for every branch: total count, plus the screen-reader sort note.
	$total_int   = intval( $total );
	$count_label = ( 1 === $total_int % 10 && 11 !== $total_int % 100 )
		/* translators: %d: number of products (singular form) */
		? sprintf( __( '%d proizvod', 'dreampoint-b2b' ), $total_int )
		/* translators: %d: number of products (plural form) */
		: sprintf( __( '%d proizvoda', 'dreampoint-b2b' ), $total_int );

	echo esc_html( $count_label );

	if ( 1 !== $total_int && ! empty( $orderedby ) ) {
		echo '<span class="screen-reader-text">' . esc_html( $orderedby ) . '</span>';
	}
	// phpcs:enable WordPress.Security
	?>
</p>
