<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('In stock always show first', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Sort products by stock status first then by the selected criterion.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_first_instock', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use as default', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Select some sort option as default.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_default_sortby', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_default_sortby">
			<?php
			$options = array();
			$labels  = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('SortBy');
			foreach ($labels as $key => $value) {
				$options[$key] = $value;
			}
			HtmlWpf::selectbox('f_hidden_sortby', array(
				'options' => $options,
				'attrs' => 'class="woobewoo-flat-input"',
			));
			?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_default_sortby">
			<div class="settings-value-label woobewoo-width60">
				<?php esc_html_e('Hide filter', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_hidden_sort', array('attrs' => 'data-preselect-flag="1"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="dropdown">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show number of products', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show dropdouwn box to choose the number of products shown on the page. In the next field you can set the number of products to be displayed on one pagination page. Set multiple numbers, separated by commas, so users can choose them personally.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_show_per_page', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_show_per_page">
			<?php
			HtmlWpf::text('f_per_page_list', array(
				'placeholder' => '48,24,12',
				'attrs' => 'class="woobewoo-flat-input"',
			));
			?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_show_per_page">
			<div class="settings-value-label woobewoo-width60">
				<?php esc_html_e('position', 'woo-product-filter'); ?>
			</div>
			<?php
			HtmlWpf::selectbox('f_per_page_position', array(
				'options' => array('left' => __('left', 'woo-product-filter'), 'right' => __('right', 'woo-product-filter')),
				'attrs' => 'class="woobewoo-flat-input"',
			));
			?>
		</div>
	</div>
</div>
