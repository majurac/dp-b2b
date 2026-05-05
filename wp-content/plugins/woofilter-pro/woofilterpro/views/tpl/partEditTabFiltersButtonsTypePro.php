<?php 
	$colorsLimit = ( 1 == FrameWpf::_()->getModule( 'options' )->get( 'unlimited_color_filter' ) ? 100000 : 300 );
?>
<div class="row-settings-block wpfButtonsTypeBlock wpfTypeSwitchable" data-type="buttons">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Buttons Settings', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Buttons Settings', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="sub-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Button Type', 'woo-product-filter'); ?>
			</div>
			<?php 
				HtmlWpf::selectbox('f_buttons_type', array(
					'options' => array(
						'square' => esc_attr__('Square', 'woo-product-filter'),
						'corners' => esc_attr__('Square rounded corners', 'woo-product-filter'),
						'edges' => esc_attr__('Square round edges', 'woo-product-filter'),
						'circle' => esc_attr__('Circle', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Multi selection', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_buttons_multiselect', array('checked' => 1)); ?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Inner spacing', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_buttons_inner_spacing', array('value' => 5, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> px
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Outer spacing', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_buttons_outer_spacing', array('value' => 5, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> px
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Font size', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_buttons_font_size', array('value' => 15, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> px
		</div>
		<div class="settings-value settings-w100 inner-float">
			<div class="settings-value-label woobewoo-width120 settings-float">
				<?php esc_html_e('Font color', 'woo-product-filter'); ?>
			</div>
			<div class="settings-float">
				<?php HtmlWpf::colorpicker('f_buttons_font_color', array('value' => '#6d6d6d')); ?> 
				<div class="settings-label-after">
					<?php esc_html_e('normal', 'woo-product-filter'); ?>
				</div>
			</div>
			<div class="settings-float">
				<?php HtmlWpf::colorpicker('f_buttons_font_color_checked', array('value' => '#000000')); ?>
				<div class="settings-label-after">
					<?php esc_html_e('checked', 'woo-product-filter'); ?>
				</div>
			</div>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Border width', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_buttons_border_width', array('value' => 2, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> px
		</div>
		<div class="settings-value settings-w100 inner-float">
			<div class="settings-value-label woobewoo-width120 settings-float">
				<?php esc_html_e('Border color', 'woo-product-filter'); ?>
			</div>
			<div class="settings-float">
				<?php HtmlWpf::colorpicker('f_buttons_border_color', array('value' => '#6d6d6d')); ?>
				<div class="settings-label-after">
					<?php esc_html_e('normal', 'woo-product-filter'); ?>
				</div>
			</div>
			<div class="settings-float">
				<?php HtmlWpf::colorpicker('f_buttons_border_color_checked', array('value' => '#000000')); ?>
				<div class="settings-label-after">
					<?php esc_html_e('checked', 'woo-product-filter'); ?>
				</div>
			</div>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Button width', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('If the value is set, all buttons will have the same width and text that does not fit will be hidden. Otherwise the width will depend on the contents of each button.', 'woo-product-filter'); ?>"></i>
			</div>
			<?php HtmlWpf::text('f_buttons_width', array('value' => '', 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> px
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Button height', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('If the value is set, all buttons will have the same height and text that does not fit will be hidden. Otherwise the height will depend on the contents of each button.', 'woo-product-filter'); ?>"></i>
			</div>
			<?php HtmlWpf::text('f_buttons_height', array('value' => '', 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> px
		</div>
		<div class="settings-value settings-w100 inner-float">
			<div class="settings-value-label woobewoo-width120 settings-float">
				<?php esc_html_e('Background color', 'woo-product-filter'); ?>
			</div>
			<div class="settings-float">
				<?php HtmlWpf::colorpicker('f_buttons_bg_color', array('value' => '#ffffff')); ?>
				<div class="settings-label-after">
					<?php esc_html_e('normal', 'woo-product-filter'); ?>
				</div>
			</div>
			<div class="settings-float">
				<?php HtmlWpf::colorpicker('f_buttons_bg_color_checked', array('value' => '#ffffff')); ?>
				<div class="settings-label-after">
					<?php esc_html_e('checked', 'woo-product-filter'); ?>
				</div>
			</div>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-label-right woobewoo-width120">
				<?php esc_html_e('per button', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_buttons_per_button', array('checked' => 0)); ?>
		</div>

		<div class="settings-value settings-w100 wpfSettingsPerTerm">
			<div class="settings-value-label woobewoo-width120">
			</div>
			<ul class="wpfTermsOptions" data-normal-text="<?php echo esc_attr__('normal', 'woo-product-filter'); ?>" data-checked-text="<?php echo esc_attr__('checked', 'woo-product-filter'); ?>" data-max-terms="<?php echo esc_attr($colorsLimit); ?>" data-error="<?php esc_attr_e('This option is not available due to the large number of elements. You can remove this limit in the plugin settings.', 'woo-product-filter'); ?>">
				<li></li>
			</ul>
		</div>
		<div class="settings-block-values settings-w100 wpfTermsOptionsForm" data-no-preview="1">
			<div class="settings-value settings-w100 wpfTermsColorBg" data-field-temp="color_bg" data-field-type="color-picker">
				<div class="settings-value-label">
					<?php esc_html_e('normal', 'woo-product-filter'); ?>
				</div>
				<?php HtmlWpf::colorpicker('', array()); ?>
			</div>
			<div class="settings-value wpfTermsColorBg" data-field-temp="color_bg_check" data-field-type="color-picker">
				<div class="settings-value-label">
					<?php esc_html_e('checked', 'woo-product-filter'); ?>
				</div>
				<?php HtmlWpf::colorpicker('', array()); ?>
			</div>
			<div class="settings-value wpfTermsTextLabel" data-field-temp="text_label" data-field-type="text">
				<div class="settings-value-label">
					<?php esc_html_e('text', 'woo-product-filter'); ?>
				</div>
				<?php HtmlWpf::text('', array('value' => '', 'attrs' => 'class="woobewoo-flat-input woobewoo-width100" placeholder=""')); ?>
			</div>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="text">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Single selection', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('When this option is enabled, the filter will only allow a single selection in this box.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="sub-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_text_singleselection', array('checked' => 0)); ?>
		</div>
	</div>
</div>
