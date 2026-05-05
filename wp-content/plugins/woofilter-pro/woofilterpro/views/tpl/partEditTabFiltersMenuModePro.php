<div class="row-settings-block wpfTypeSwitchable" data-not-type="mul_dropdown multi">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Switch to menu mode', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'When this option is activated, this section of categories loses the ability to filter products and works as a navigation menu', 'woo-product-filter' ); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php HtmlWpf::checkboxToggle('f_menu_mode', array()); ?>
		</div>
	</div>
</div>
