<div class="row-settings-block wpfTypeSwitchable dataParentIgnore" data-type="dropdown radio list mul_dropdown buttons text multi" data-parent="f_list" data-no-values="custom_meta_field_check">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Select default id', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Selects the default filter value by id', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value" data-error="<?php esc_attr_e('Please enter numeric values only for the ID', 'woo-product-filter'); ?>">
			<?php
			HtmlWpf::text('f_select_default_id', array(
				'value' => ( isset($this->settings['f_select_default_id']) ? (int) $this->settings['f_select_default_id'] : '' ),
				'attrs' => 'class="woobewoo-flat-input"',
			));
			?>
		</div>
	</div>
</div>
<?php if (!empty($this->settings['customFilter']) && 'Tag' == $this->settings['customFilter']) { ?>
<div class="row-settings-block wpfTypeSwitchable" data-type="list buttons dropdown mul_dropdown text">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3" >
		<?php esc_html_e('Check page tag', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Оn the tag page automatically put a check mark for current tag', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_set_page_tag', array()); ?>
		</div>
	</div>
</div>
<?php } ?>

