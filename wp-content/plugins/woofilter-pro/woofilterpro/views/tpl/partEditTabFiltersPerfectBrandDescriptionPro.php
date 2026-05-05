<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e( 'Display brand description', 'woo-product-filter' ); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip"
		   title="<?php echo esc_attr__( 'Display brand description before product list', 'woo-product-filter' ); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle( 'f_display_description', array() ); ?>
		</div>
	</div>
</div>
