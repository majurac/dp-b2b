<?php
$settings = $this->getFilterSetting($this->settings, 'settings', array());
$styles = $this->getFilterSetting($settings, 'styles', array());
$module = $this->getModule();
$fontsList = $module->getAllFontsList();
$defaultFont = $module->defaultFont;
$fontStyles = $module->getFontStyles();
$borderStyles = $module->getBorderStyles();
$bgTypes = array(
	'' => esc_attr__('none', 'woo-product-filter'),
	'unicolored' => esc_attr__('unicolored', 'woo-product-filter'),
	'bicolored' => esc_attr__('bicolored', 'woo-product-filter'),
	'gradient' => esc_attr__('simple gradient', 'woo-product-filter'),
	'pyramid' => esc_attr__('pyramid gradient', 'woo-product-filter'),
);

$useButtonStyles = $this->getFilterSetting($settings, 'use_button_styles', 0);
$hiddenStyle = $useButtonStyles ? '' : 'wpfHidden';
?>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use Custom Styles', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Choose custom styles for filter buttons. Any settings you leave blank will default.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/buttons-design/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::checkboxToggle('settings[use_button_styles]', array(
					'checked' => $useButtonStyles,
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Block align', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[styles][button_block_align]', array(
					'value' => $this->getFilterSetting($styles, 'button_block_align', ''),
					'options' => array(
						'' => '',
						'center' => esc_attr__('center', 'woo-product-filter'),
						'left' => esc_attr__('left', 'woo-product-filter'),
						'right' => esc_attr__('right', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Block float', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('We recommend use these option in tandem with "Filter Block Width" and "Select Filter Buttons Position" options tryin to align block button on left or right side of filter block.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::selectbox('settings[styles][button_block_float]', array(
					'value' => $this->getFilterSetting($styles, 'button_block_align', ''),
					'options' => array(
						'' => '',
						'left' => esc_attr__('left', 'woo-product-filter'),
						'right' => esc_attr__('right', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Font', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][button_font_family]', array(
					'value' => $this->getFilterSetting($styles, 'button_font_family', $defaultFont),
					'options' => $fontsList,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::text('settings[styles][button_font_size]', array(
					'value' => $this->getFilterSetting($styles, 'button_font_size', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				?>
			<div class="settings-value-label">px</div>
		</div>
		<div class="clear"></div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width60">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value settings-w50" data-style="button_font">
			<?php 
				HtmlWpf::selectbox('settings[styles][button_font_style]', array(
					'value' => $this->getFilterSetting($styles, 'button_font_style', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50" data-style="button_font">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_font_color]', array(
					'value' => $this->getFilterSetting($styles, 'button_font_color', ''),
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width60">
				<a href="#" class="wpfCopyStyles woobewoo-tooltip" title="<?php echo esc_attr__('Copy normal styles', 'woo-product-filter'); ?>" data-style="button_font"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][button_font_style_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_font_style_hover', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_font_color_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_font_color_hover', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Text shadow', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Set text shadow in this order: color, X, Y, blur.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_text_shadow_color]', array(
					'value' => $this->getFilterSetting($styles, 'button_text_shadow_color', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][button_text_shadow_x]', array(
					'value' => $this->getFilterSetting($styles, 'button_text_shadow_x', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_text_shadow_y]', array(
					'value' => $this->getFilterSetting($styles, 'button_text_shadow_y', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_text_shadow_blur]', array(
					'value' => $this->getFilterSetting($styles, 'button_text_shadow_blur', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Button width', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][button_width]', array(
					'value' => $this->getFilterSetting($styles, 'button_width', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][button_width_unit]', array(
					'options' => array('%' => '%', 'px' => 'px'),
					'value' => $this->getFilterSetting($styles, 'button_width_unit', '%'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Button height', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][button_height]', array(
					'value' => $this->getFilterSetting($styles, 'button_height', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				?>
				<div class="settings-value-label">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Corners radius', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][button_radius]', array(
					'value' => $this->getFilterSetting($styles, 'button_radius', '', true, false, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][button_radius_unit]', array(
					'options' => array('px' => 'px', '%' => '%'),
					'value' => $this->getFilterSetting($styles, 'button_radius_unit', 'px'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<?php 
	$bgType = $this->getFilterSetting($styles, 'button_bg_type', '', false, array('unicolored', 'bicolored', 'gradient', 'pyramid'));
	$classHiddenUni = !$useButtonStyles || 'unicolored' != $bgType ? 'wpfHidden' : '';
	$classHiddenTwo = $useButtonStyles && ( 'bicolored' == $bgType || 'gradient' == $bgType || 'pyramid' == $bgType ) ? '' : 'wpfHidden';
?>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Background', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width60">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value" data-style="button_bg">
			<?php 
				HtmlWpf::selectbox('settings[styles][button_bg_type]', array(
					'value' => $bgType,
					'options' => $bgTypes,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenUni); ?>" data-select="settings[styles][button_bg_type]" data-select-value="unicolored" data-style="button_bg">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_bg_color]', array(
					'value' => $this->getFilterSetting($styles, 'button_bg_color', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][button_bg_type]" data-select-value="bicolored|gradient|pyramid" data-style="button_bg">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_bg_color1]', array(
					'value' => $this->getFilterSetting($styles, 'button_bg_color1', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][button_bg_type]" data-select-value="bicolored|gradient|pyramid" data-style="button_bg">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_bg_color2]', array(
					'value' => $this->getFilterSetting($styles, 'button_bg_color2', ''),
				));
				?>
		</div>
		<div class="clear"></div>
<?php 
	$bgType = $this->getFilterSetting($styles, 'button_bg_type_hover', '', false, array('unicolored', 'bicolored', 'gradient', 'pyramid'));
	$classHiddenUni = !$useButtonStyles || 'unicolored' != $bgType ? 'wpfHidden' : '';
	$classHiddenTwo = $useButtonStyles && ( 'bicolored' == $bgType || 'gradient' == $bgType || 'pyramid' == $bgType ) ? '' : 'wpfHidden';
?>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width60">
				<a href="#" class="wpfCopyStyles" data-style="button_bg"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::selectbox('settings[styles][button_bg_type_hover]', array(
					'value' => $bgType,
					'options' => $bgTypes,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenUni); ?>" data-select="settings[styles][button_bg_type_hover]" data-select-value="unicolored">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_bg_color_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_bg_color_hover', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][button_bg_type_hover]" data-select-value="bicolored|gradient|pyramid">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_bg_color1_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_bg_color1_hover', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][button_bg_type_hover]" data-select-value="bicolored|gradient|pyramid">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_bg_color2_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_bg_color2_hover', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Borders', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Set borders width in this order: color, top, right, bottom, left.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width60">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value" data-style="button_border">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_border_color]', array(
					'value' => $this->getFilterSetting($styles, 'button_border_color', ''),
				));
				?>
		</div>
		<div class="settings-value" data-style="button_border">
			<?php 
				HtmlWpf::text('settings[styles][button_border_top]', array(
					'value' => $this->getFilterSetting($styles, 'button_border_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_border_right]', array(
					'value' => $this->getFilterSetting($styles, 'button_border_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_border_bottom]', array(
					'value' => $this->getFilterSetting($styles, 'button_border_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_border_left]', array(
					'value' => $this->getFilterSetting($styles, 'button_border_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width60">
				<a href="#" class="wpfCopyStyles" data-style="button_border"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_border_color_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_border_color_hover', ''),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][button_border_top_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_border_top_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_border_right_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_border_right_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_border_bottom_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_border_bottom_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_border_left_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_border_left_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Button shadow', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Set button shadow in this order: color, X, Y, blur, spread (px).', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width60">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value" data-style="button_shadow">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_shadow_color]', array(
					'value' => $this->getFilterSetting($styles, 'button_shadow_color', ''),
				));
				?>
		</div>
		<div class="settings-value" data-style="button_shadow">
			<?php 
				HtmlWpf::text('settings[styles][button_shadow_x]', array(
					'value' => $this->getFilterSetting($styles, 'button_shadow_x', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_shadow_y]', array(
					'value' => $this->getFilterSetting($styles, 'button_shadow_y', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_shadow_blur]', array(
					'value' => $this->getFilterSetting($styles, 'button_shadow_blur', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_shadow_spread]', array(
					'value' => $this->getFilterSetting($styles, 'button_shadow_spread', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width60">
				<a href="#" class="wpfCopyStyles" data-style="button_shadow"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][button_shadow_color_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_shadow_color_hover', ''),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][button_shadow_x_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_shadow_x_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_shadow_y_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_shadow_y_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_shadow_blur_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_shadow_blur_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_shadow_spread_hover]', array(
					'value' => $this->getFilterSetting($styles, 'button_shadow_spread_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
</div>

<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Padding', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set paddings in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][button_padding_top]', array(
					'value' => $this->getFilterSetting($styles, 'button_padding_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_padding_right]', array(
					'value' => $this->getFilterSetting($styles, 'button_padding_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_padding_bottom]', array(
					'value' => $this->getFilterSetting($styles, 'button_padding_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_padding_left]', array(
					'value' => $this->getFilterSetting($styles, 'button_padding_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_button_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Margin', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set margins in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][button_margin_top]', array(
					'value' => $this->getFilterSetting($styles, 'button_margin_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_margin_right]', array(
					'value' => $this->getFilterSetting($styles, 'button_margin_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_margin_bottom]', array(
					'value' => $this->getFilterSetting($styles, 'button_margin_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][button_margin_left]', array(
					'value' => $this->getFilterSetting($styles, 'button_margin_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
