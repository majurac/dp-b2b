<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use as default', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Select some stock status as default.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_default_stock', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_default_stock">
			<?php
			$options = array();
			$labels = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('InStock');
			foreach ($labels as $key => $value) {
				$options[$key] = $value;
			}
			$single = $options;
			$keys = array_keys($labels);
			$cntKeys = count($keys);
			foreach ($keys as $k => $key) {
				$value = $labels[$key];
				$i = $k + 1;
				while ($i < $cntKeys) {
					$options[$key . '|' . $keys[$i]] = $value . ' + ' . $labels[$keys[$i]];
					$i++;
				}
			}
			HtmlWpf::selectbox('f_hidden_stock_status', array(
				'options' => $options,
				'attrs' => 'class="woobewoo-flat-input" data-single="' . esc_attr(json_encode($single)) . '" data-multi="' . esc_attr(json_encode($options)) . '"',
			));
			?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_default_stock">
			<div class="settings-value-label woobewoo-width60">
				<?php esc_html_e('Hide filter', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_hidden_stock', array('attrs' => 'data-preselect-flag="1"')); ?>
		</div>
		<div class="settings-value" data-parent="f_default_stock">
			<div class="settings-value-label">
				<?php esc_html_e('No preselection', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_no_preselect'); ?>
		</div>
	</div>
</div>
