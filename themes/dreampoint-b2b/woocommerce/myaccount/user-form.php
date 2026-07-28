<?php 
$user_id = get_current_user_id();
$phone = get_user_meta($user_id, 'billing_phone', true);
$billing_company = get_user_meta($user_id, 'billing_company', true);
$company_oib = get_user_meta($user_id, 'billing_oib', true); // Fetch 'billing_oib'
?>
<div class="custom-form">
	<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >
	
		<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

		<div class="form-block-holder">
			<div class="ma-holder">
	            <div class="ma-header">
		            <div class="wod-title-holder">
						<h2><img src="<?php echo get_template_directory_uri(); ?>/img/ico/lock.svg" alt=""><?php esc_html_e( 'Podaci Tvrtke', 'dreampoint-b2b' ); ?></h2>
					</div>
					<!-- /.wod-title-holder -->
	            </div>
            	<!-- /.ma-header -->
	            <div class="ma-info">
	                <p><strong><?php esc_html_e('Naziv tvrtke', 'dreampoint-b2b'); ?></strong>: <?php echo esc_html($billing_company); ?></p>
	                <p><strong><?php esc_html_e('Oib', 'dreampoint-b2b'); ?></strong>: <?php echo esc_html($company_oib); ?></p>
	                <p><strong><?php esc_html_e('Phone', 'woocommerce'); ?></strong>: <?php echo esc_html($phone); ?></p>
	            </div>
	            <!-- /.ma-info -->
	        </div>
	        <!-- /.ma-holder -->
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
				/**
				 * Hook where additional fields should be rendered.
				 *
				 * @since 8.7.0
				 */
				do_action( 'woocommerce_edit_account_form_fields' );
			?>
			<button class="save-account-details button button--sm"><?php esc_html_e( 'Spremi izmjenu', 'dreampoint-b2b' ); ?></button>
			<!-- /.save-account-details button button--sm -->
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
			<button type="submit" class="button button--sm woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"><?php esc_html_e( 'Save changes', 'woocommerce' ); ?></button>
			<input type="hidden" name="action" value="save_account_details" />
		</p>

		<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
	</form>
</div>
<!-- /.custom-form -->