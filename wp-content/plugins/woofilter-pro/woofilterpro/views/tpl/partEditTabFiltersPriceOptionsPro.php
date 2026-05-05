<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Set min/max prices', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Set the price min/max value. Doesn\'t working with recount prices option!', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_set_min_max_price'); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_set_min_max_price">
			<div class="settings-block-label woobewoo-width80">
				<?php esc_html_e('Min price', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_min_price', array('attrs' => 'class="woobewoo-flat-input"')); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_set_min_max_price">
			<div class="settings-block-label woobewoo-width80">
				<?php esc_html_e('Max price', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_max_price', array('attrs' => 'class="woobewoo-flat-input"')); ?>
		</div>
		<div class="settings-value" data-parent="f_set_min_max_price">
			<div class="settings-value-label">
				<?php esc_html_e('Use as default', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_use_as_preselect', array('attrs' => 'data-preselect-flag="1"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Set tax rates', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('The values will be changed by the specified percentage', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::text('f_set_tax_rates', array('attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> %
		</div>
	</div>

</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Hide if there are no products with price', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_hide_if_no_prices'); ?>
		</div>
	</div>

</div>
