<div class="row-settings-block wpfSkinsBlock">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Skin Settings', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Appearance on a frontend', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="sub-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width150">
				<?php esc_html_e('Color', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::colorpicker('f_skin_color', array('value' => '#000000')); ?>
			<input type="hidden" name="f_skin_css" value="">
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width150">
				<?php esc_html_e('Show min and max labels', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_skin_labels_minmax', array('checked' => 1)); ?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width150">
				<?php esc_html_e('Show from and to labels', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_skin_labels_fromto', array('checked' => 1)); ?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width150">
				<?php esc_html_e('Step', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_html(__('Set sliders step. Always > 0. Could be fractional.', 'woo-product-filter')); ?>"></i>
			</div>
			<?php HtmlWpf::text('f_skin_step', array('value' => '1', 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width150">
				<?php esc_html_e('Show grid', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_skin_grid', array('checked' => 1)); ?>
		</div>
	</div>
	<div class="wpfHidden wpfAttributeStyleAdd"></div>
</div>
