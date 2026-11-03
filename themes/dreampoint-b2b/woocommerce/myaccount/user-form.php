<?php 
$user_id = get_current_user_id();
$phone = get_user_meta($user_id, 'billing_phone', true);
$billing_company = get_user_meta($user_id, 'billing_company', true);
$company_oib = get_user_meta($user_id, 'billing_oib', true); // Fetch 'billing_oib'
?>
<div class="custom-form">
	<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >
	
		<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

		<div class="ma-holder">
	            <div class="ma-header">
		            <div class="wod-title-holder">
						<h2><?php esc_html_e( 'Podaci o tvrtci', 'dreampoint-b2b' ); ?></h2>
					</div>
					<!-- /.wod-title-holder -->
	            </div>
            	<!-- /.ma-header -->
	            <div class="ma-info">
	                <p><span><?php esc_html_e('Naziv firme', 'dreampoint-b2b'); ?>:</span> <strong><?php echo esc_html($billing_company); ?></strong></p>
	                <p><span><?php esc_html_e('OIB', 'dreampoint-b2b'); ?>:</span> <strong><?php echo esc_html($company_oib); ?></strong></p>
	                <p><span><?php esc_html_e('Phone', 'woocommerce'); ?>:</span> <strong><?php echo esc_html($phone); ?></strong></p>
	            </div>
	            <!-- /.ma-info -->
	            <?php
	            get_template_part(
				'template-parts/account-erp-notice',
				null,
				array( 'text' => __( 'Podaci o firmi preuzimaju se iz ERP sistema. Za izmjene kontaktirajte svog prodajnog predstavnika ili korisničku podršku.', 'dreampoint-b2b' ) )
	            );
	            ?>
	        </div>
	        <!-- /.ma-holder -->

		<div class="form-block-holder">
			<div class="wod-title-holder">
				<h2><?php esc_html_e( 'Podaci o računu', 'dreampoint-b2b' ); ?></h2>
			</div>
			<!-- /.wod-title-holder -->

			<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
	            <label for="account_first_name"><?php esc_html_e( 'First name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
	            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( get_user_meta( $user->ID, 'billing_first_name', true ) ); ?>" />
	        </p>

	        <!-- Last Name -->
	        <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
	            <label for="account_last_name"><?php esc_html_e( 'Last name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
	            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( get_user_meta( $user->ID, 'billing_last_name', true ) ); ?>" />
	        </p>
			<div class="clear"></div>
			
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="account_display_name"><?php esc_html_e( 'Display name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" value="<?php echo esc_attr( $user->display_name ); ?>" aria-describedby="account_display_name_description" /> <span id="account_display_name_description"><em><?php esc_html_e( 'This will be how your name will be displayed in the account section and in reviews', 'woocommerce' ); ?></em></span>
			</p>
			<div class="clear"></div>
			
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="account_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" />
			</p>
			
			<?php
				// The single phone field (storage: billing_phone); see inc/my-account.php.
				if ( function_exists( 'dreampoint_b2b_add_phone_field_to_account' ) ) {
					dreampoint_b2b_add_phone_field_to_account();
				}

				/**
				 * Hook where additional fields should be rendered.
				 * Company name and OIB are NOT editable here (client rule, shown in the read-only card above).
				 *
				 * @since 8.7.0
				 */
				do_action( 'woocommerce_edit_account_form_fields' );
			?>
		</div>
		<!-- /.form-block-holder -->
	
	
		<div class="form-block-holder">
			<div class="wod-title-holder">
				<h2><?php esc_html_e( 'Lozinka', 'dreampoint-b2b' ); ?></h2>
			</div>
			<!-- /.wod-title-holder -->
				
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password_current"><?php esc_html_e( 'Current password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="off" />
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password_1"><?php esc_html_e( 'New password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="off" />
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password_2"><?php esc_html_e( 'Confirm new password', 'woocommerce' ); ?></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="off" />
			</p>
			
			<div class="clear"></div>
		</div>
		<!-- /.form-block-holder -->
	
		<?php
			/**
			 * My Account edit account form.
			 *
			 * @since 2.6.0
			 */
			do_action( 'woocommerce_edit_account_form' );
		?>

		<p>
			<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
			<button type="submit" class="button button--save woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"><?php esc_html_e( 'Spremi izmjenu', 'dreampoint-b2b' ); ?></button>
			<input type="hidden" name="action" value="save_account_details" />
		</p>

		<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
	</form>
</div>
<!-- /.custom-form -->