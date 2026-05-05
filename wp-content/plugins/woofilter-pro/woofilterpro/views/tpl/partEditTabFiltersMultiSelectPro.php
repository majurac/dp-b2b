<div class="row-settings-block wpfTypeSwitchable" data-type="mul_dropdown">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e( 'Single select mode', 'woo-product-filter' ); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip"
		   title="<?php echo esc_attr__( 'Allow only one item from the list to be selected at a time', 'woo-product-filter' ); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle( 'f_single_select', array() ); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="mul_dropdown">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e( 'Hide checkboxes', 'woo-product-filter' ); ?>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle( 'f_hide_checkboxes', array() ); ?>
		</div>
	</div>
</div>
