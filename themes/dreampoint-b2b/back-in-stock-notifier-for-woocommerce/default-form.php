<?php
/**
 * Back in Stock Notifier — subscribe form (theme override of the plugin's templates/default-form.php).
 *
 * Supported override path (plugin 7.5.2, CWG_Template::locate_template): <theme>/back-in-stock-notifier-for-woocommerce/default-form.php.
 * Figma PDP-02 (node 11148:32975): one row, email input + primary XL button, no visible heading, no name field.
 *
 * What differs from the plugin template (everything else is kept, the plugin JS/AJAX relies on it):
 * - no Bootstrap panel / row / col wrappers (styling lives in sass/pages/shop-single.scss);
 * - no name input (the plugin JS skips the name check when `.cwgstock_name` is absent; the same markup the plugin
 *   produces with its "Hide Name" setting); the phone input is rendered only when the plugin's "Show Phone" setting is on;
 * - the heading stays in the DOM as screen-reader-only text and labels the form;
 * - the email field has a screen-reader-only label, the Figma placeholder "email" and a localized button label.
 * Kept: `.cwginstock-subscribe-form` + variation class, `.cwgstock_email`, hidden `.cwg-product-id` / `.cwg-variation-id` /
 * `.cwg-security`, `.cwgstock_button`, `.cwgstock_output`, and every `cwg_instock_*` / `cwginstock_*` action hook.
 *
 * @package Dreampoint_B2B
 *
 * Template variables are provided by the plugin (extract of $args).
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 */

defined( 'ABSPATH' ) || exit;

$base_id        = 'cwginstock-form-' . intval( $product_id ) . '-' . intval( $variation_id );
$heading_id     = 'cwginstock-form-title-' . intval( $product_id ) . '-' . intval( $variation_id );
$email_field_id = $base_id . '-email';
$form_title     = isset( $get_option['form_title'] ) && '' !== $get_option['form_title']
	? $instock_api->sanitize_text_field( $get_option['form_title'] )
	: __( 'Obavijest e-mailom kada proizvod bude dostupan', 'dreampoint-b2b' );
?>
<section class="cwginstock-subscribe-form <?php echo esc_attr( $variation_class ); ?> <?php echo esc_attr( $dynamic_wrapper_class ); ?>">
	<?php
	/** This action is documented in the plugin template (templates/default-form.php). */
	do_action( 'cwg_instock_before_heading', $product_id, $variation_id );
	?>
	<h4 id="<?php echo esc_attr( $heading_id ); ?>" class="cwginstock-subscribe-form__title screen-reader-text"><?php echo esc_html( $form_title ); ?></h4>
	<?php do_action( 'cwg_instock_after_heading', $product_id, $variation_id ); ?>

	<div class="cwginstock-subscribe-form__form" role="form" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
		<div class="cwginstock-subscribe-form__row">
			<?php do_action( 'cwg_instock_before_input_fields', $product_id, $variation_id ); ?>
			<label class="screen-reader-text" for="<?php echo esc_attr( $email_field_id ); ?>"><?php esc_html_e( 'E-mail adresa', 'dreampoint-b2b' ); ?></label>
			<input id="<?php echo esc_attr( $email_field_id ); ?>" type="email" class="cwgstock_email"
				name="cwgstock_email"
				placeholder="<?php echo esc_attr_x( 'email', 'back-in-stock email placeholder', 'dreampoint-b2b' ); ?>"
				value="<?php echo esc_attr( $email ); ?>"
				autocomplete="email" inputmode="email" />
			<?php
			// Optional phone field: only when the plugin's "Show Phone" setting is on (same condition and markup as the
			// plugin template). With the setting on, the plugin JS initialises intl-tel-input on `.cwgstock_phone` and
			// `cwginstock_iti.getNumber()` in its submit handler needs it, so it must exist in exactly that case.
			if ( $phone_field_visibility ) :
				$phone_field_id = $base_id . '-phone';
				?>
				<label class="screen-reader-text" for="<?php echo esc_attr( $phone_field_id ); ?>"><?php esc_html_e( 'Broj telefona', 'dreampoint-b2b' ); ?></label>
				<input id="<?php echo esc_attr( $phone_field_id ); ?>" type="tel" class="cwgstock_phone" name="cwgstock_phone" autocomplete="tel" inputmode="tel" />
			<?php endif; ?>
			<?php
			do_action( 'cwg_instock_after_email_field', $product_id, $variation_id );
			?>
			<input type="hidden" class="cwg-product-id" name="cwg-product-id" value="<?php echo intval( $product_id ); ?>" />
			<input type="hidden" class="cwg-variation-id" name="cwg-variation-id" value="<?php echo intval( $variation_id ); ?>" />
			<input type="hidden" class="cwg-security" name="cwg-security" value="<?php echo esc_attr( $security ); ?>" />
			<?php do_action( 'cwg_instock_after_input_fields', $product_id, $variation_id ); ?>
			<?php do_action( 'cwginstock_before_submit_button', $product_id, $variation_id ); ?>
			<?php
			/** This filter is documented in the plugin template (templates/default-form.php). */
			$submit_extra_attr = apply_filters( 'cwgstock_submit_attr', '', $product_id, $variation_id );
			$button_text       = __( 'Obavijesti me', 'dreampoint-b2b' );
			?>
			<button type="submit" name="cwgstock_submit" class="cwgstock_button button button--xl"
				<?php echo do_shortcode( $submit_extra_attr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin filter output, same as the plugin template. ?>
				value="<?php echo esc_attr( $button_text ); ?>"><?php echo esc_html( $button_text ); ?></button>
			<?php do_action( 'cwginstock_after_submit_button', $product_id, $variation_id ); ?>
		</div>
		<div class="cwgstock_output" aria-live="polite" aria-atomic="true"></div>
	</div>
</section>
