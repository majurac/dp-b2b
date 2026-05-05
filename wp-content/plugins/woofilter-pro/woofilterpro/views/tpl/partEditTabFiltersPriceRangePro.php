<div class="row-settings-block" data-value="hand">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use Under/Over values', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Use Under/Over label instead of minimum and maximum values.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_under_over', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_under_over">
			<?php HtmlWpf::text('f_under_text', array('attrs' => 'class="woobewoo-flat-input woobewoo-width100" placeholder="' . esc_attr__('Under', 'woo-product-filter') . '"')); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_under_over">
			<?php HtmlWpf::text('f_over_text', array('attrs' => 'class="woobewoo-flat-input woobewoo-width100" placeholder="' . esc_attr__('Over', 'woo-product-filter') . '"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="list">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show price input fields', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Add fields for manually entering a price range.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_custom_fields', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_custom_fields">
			<?php HtmlWpf::text('f_custom_text', array('attrs' => 'class="woobewoo-flat-input woobewoo-width100" placeholder="' . esc_attr__('Custom', 'woo-product-filter') . '"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block" data-value="decimals">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use custom number of decimals', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('This sets the number of decimal points shown in displayed prices.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_custom_decimals', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_custom_decimals">
			<?php
			HtmlWpf::input( 'f_custom_decimals_range', array(
				'attrs' => 'class="woobewoo-flat-input woobewoo-width100" min="0"',
				'type'  => 'number',
			) );
			?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Set tax rates', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('The values will be changed by the specified percentage', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::text('f_set_tax_rates', array('attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> %
		</div>
	</div>
</div>
