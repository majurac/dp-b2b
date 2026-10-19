<?php
/**
 * Shared slider navigation (previous / next) for `.section-heading`.
 *
 * Rendered inside `.section-heading`, after the heading. Bound per block by
 * `initSliderNav()` in js/slick-init.js (Slick's own arrows are disabled for
 * these sliders). Styling: `.section-heading .nav-btns` + `.slick-prev/.slick-next`.
 *
 * @package dreampoint-b2b
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="nav-btns">
    <button type="button" class="slick-prev btn-prev" aria-label="<?php esc_attr_e( 'prethodni', 'dreampoint-b2b' ); ?>"></button>
    <button type="button" class="slick-next btn-next" aria-label="<?php esc_attr_e( 'sljedeći', 'dreampoint-b2b' ); ?>"></button>
</div>
