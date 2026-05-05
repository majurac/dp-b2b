<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-lg-3">
		<?php esc_html_e('Do not remove products while loading', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php esc_attr_e('To prevent products container from collapsing during ajax.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
			HtmlWpf::checkboxToggle('settings[ajax_leave_products]', array(
				'checked' => isset( $this->settings['settings']['ajax_leave_products'] ) ? (int) $this->settings['settings']['ajax_leave_products'] : 0,
			));
			?>
		</div>
	</div>
</div>
