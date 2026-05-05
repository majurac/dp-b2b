<?php
	$hideButton = ( isset($this->settings['settings']['display_hide_button']) ? $this->settings['settings']['display_hide_button'] : 'no' );
	$hideMobileButton = ( isset($this->settings['settings']['display_hide_button_mobile']) ? $this->settings['settings']['display_hide_button_mobile'] : 'no' );
	$hideText = esc_attr__('HIDE FILTERS', 'woo-product-filter');
	$showText = esc_attr__('SHOW FILTERS', 'woo-product-filter');
	$hiddenStyle = 'no' == $hideButton && 'no' == $hideMobileButton ? 'wpfHidden' : '';
	$styleHidden = 'no' == $hideMobileButton ? 'wpfHidden' : '';
	$openHidden = 'yes_close' == $hideButton ? '' : 'wpfHidden';
	$openMobileHidden = 'yes_close' == $hideMobileButton ? '' : 'wpfHidden';
?>

<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Display Hide Filters button', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="row">
			<div class="col-xs-12 col-sm-6">
				<div class="settings-value settings-w100">
					<div class="settings-value-label woobewoo-width60">
						<?php esc_html_e('desktop', 'woo-product-filter'); ?>
					</div>
					<?php
						HtmlWpf::selectbox('settings[display_hide_button]', array(
							'options' => array(
								'no' => __('No', 'woo-product-filter'),
								'yes_close' => __('Yes, show as close', 'woo-product-filter'),
								'yes_open' => __('Yes, show as opened', 'woo-product-filter'),
							),
							'value' => $hideButton,
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>
			</div>
			<div class="col-xs-12 col-sm-6 pl-sm-0">
				<div class="settings-value settings-w100">
					<div class="settings-value-label woobewoo-width60">
						<?php esc_html_e('mobile', 'woo-product-filter'); ?>
					</div>
					<?php
						HtmlWpf::selectbox('settings[display_hide_button_mobile]', array(
							'options' => array(
								'no' => __('No', 'woo-product-filter'),
								'yes_close' => __('Yes, show as close', 'woo-product-filter'),
								'yes_open' => __('Yes, show as opened', 'woo-product-filter'),
							),
							'value' => $hideMobileButton,
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>
			</div>
			<div class="col-xs-12 col-sm-6">
				<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>">
					<div class="settings-value-label woobewoo-width60">
						<?php esc_html_e('hide', 'woo-product-filter'); ?>
					</div>
					<?php
					HtmlWpf::text('settings[hide_button_hide_text]', array(
						'value' => ( isset($this->settings['settings']['hide_button_hide_text']) ? $this->settings['settings']['hide_button_hide_text'] : $hideText ),
						'attrs' => 'placeholder="' . esc_attr($hideText) . '" class="woobewoo-flat-input woobewoo-width150"',
					));
					?>
				</div>
			</div>
			<div class="col-xs-12 col-sm-6 pl-sm-0">
				<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>">
					<div class="settings-value-label woobewoo-width60">
						<?php esc_html_e('show', 'woo-product-filter'); ?>
					</div>
					<?php
					HtmlWpf::text('settings[hide_button_show_text]', array(
						'value' => ( isset($this->settings['settings']['hide_button_show_text']) ? $this->settings['settings']['hide_button_show_text'] : $showText ),
						'attrs' => 'placeholder="' . esc_attr($showText) . '" class="woobewoo-flat-input woobewoo-width150"',
					));
					?>
				</div>
			</div>
			<div class="col-xs-12 col-sm-6">
				<div class="settings-value settings-w100 <?php echo esc_attr($openHidden); ?>" data-parent="settings[display_hide_button]" data-values="yes_close">
					<div class="settings-value-label">
						<?php esc_html_e('Open if filtered (desktop)', 'woo-product-filter'); ?>
						<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('When reloading the page, the filter will be opened if there is filtering.', 'woo-product-filter'); ?>"></i>
					</div>
					<?php
					HtmlWpf::checkboxToggle('settings[display_hide_button_filtered_open]', array(
						'checked' => ( isset($this->settings['settings']['display_hide_button_filtered_open']) ? $this->settings['settings']['display_hide_button_filtered_open'] : 0 ),
					));
					?>
				</div>
			</div>
			<div class="col-xs-12 col-sm-6 pl-sm-0">
				<div class="settings-value settings-w100 <?php echo esc_attr($openMobileHidden); ?>" data-parent="settings[display_hide_button_mobile]" data-values="yes_close">
					<div class="settings-value-label">
						<?php esc_html_e('Open if filtered (mobile)', 'woo-product-filter'); ?>
						<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('When reloading the page, the filter will be opened if there is filtering.', 'woo-product-filter'); ?>"></i>
					</div>
					<?php
					HtmlWpf::checkboxToggle('settings[display_hide_button_filtered_open_mobile]', array(
						'checked' => ( isset($this->settings['settings']['display_hide_button_filtered_open_mobile']) ? $this->settings['settings']['display_hide_button_filtered_open_mobile'] : 0 ),
					));
					?>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-12 col-sm-6">
				<div class="settings-value settings-w100 <?php echo esc_attr($styleHidden); ?>" data-parent="settings[display_hide_button_mobile]" data-no-values="no">
					<div class="settings-value-label">
						<?php esc_html_e('Floating button on mobile', 'woo-product-filter'); ?>
						<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('<b>"Option > main > Set Mobile/Desktop Breakpoint"</b> should be configured!', 'woo-product-filter'); ?>"></i>
					</div>
					<?php
					HtmlWpf::checkboxToggle('settings[display_hide_button_floating]', array(
						'checked' => ( isset($this->settings['settings']['display_hide_button_floating']) ? $this->settings['settings']['display_hide_button_floating'] : 0 ),
					));
					?>
				</div>
			</div>
			<div class="col-xs-12 col-sm-6 pl-sm-0">
				<?php
				$styleHidden = isset($this->settings['settings']['display_hide_button_floating']) && $this->settings['settings']['display_hide_button_floating'] ? '' : 'wpfHidden';
				?>
				<div class="settings-value settings-w100 <?php echo esc_attr($styleHidden); ?>" data-parent="settings[display_hide_button_floating]">
					<div class="settings-value settings-w50">
						<div class="settings-value-label">
							<?php esc_html_e('left position', 'woo-product-filter'); ?>
						</div>
					</div>
					<div class="settings-value settings-w50">
						<?php
						HtmlWpf::text('settings[display_hide_button_floating_left]', array(
							'value' => isset($this->settings['settings']['display_hide_button_floating_left']) ? $this->settings['settings']['display_hide_button_floating_left'] : '50',
							'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
						));
						HtmlWpf::selectbox('settings[display_hide_button_floating_left_in]', array(
							'options' => array('%' => '%', 'px' => 'px'),
							'value' => ( isset($this->settings['settings']['display_hide_button_floating_left_in']) ? $this->settings['settings']['display_hide_button_floating_left_in'] : '%' ),
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
					</div>
				</div>
				<div class="settings-value settings-w100 <?php echo esc_attr($styleHidden); ?>" data-parent="settings[display_hide_button_floating]">
					<div class="settings-value settings-w50">
						<div class="settings-value-label">
							<?php esc_html_e('bottom position', 'woo-product-filter'); ?>
						</div>
					</div>
					<div class="settings-value settings-w50">
						<?php
						HtmlWpf::text('settings[display_hide_button_floating_bottom]', array(
							'value' => isset($this->settings['settings']['display_hide_button_floating_bottom']) ? $this->settings['settings']['display_hide_button_floating_bottom'] : '20',
							'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
						));
						HtmlWpf::selectbox('settings[display_hide_button_floating_bottom_in]', array(
							'options' => array('%' => '%', 'px' => 'px'),
							'value' => ( isset($this->settings['settings']['display_hide_button_floating_bottom_in']) ? $this->settings['settings']['display_hide_button_floating_bottom_in'] : 'px' ),
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
// ====== HIDE BUTTON STYLIN START ======
$settings = $this->getFilterSetting($this->settings, 'settings', array());
$hb_settings = $this->getFilterSetting($settings, 'hide_button', array());
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

$useButtonStyles = $this->getFilterSetting($settings, 'use_hide_button_styles', 0);
$hiddenStyle = $useButtonStyles ? '' : 'wpfHidden';
$styleHidden = ( 'no' === $hideButton && 'no' === $hideMobileButton ) ? 'wpfHidden' : '';
?>
<div class="row row-settings-block <?php echo esc_attr($styleHidden); ?>" data-parents="settings[display_hide_button] settings[display_hide_button_mobile]" data-no-value="no">
	<div class="col-xs-12">
		<div class="row row-settings-block" data-block="hide_button_styles">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Use Custom Styles For Hide Button', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Choose custom styles for filter buttons. Any settings you leave blank will default.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/buttons-design/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
			</div>
			<div class="settings-block-values col-xs-8 col-sm-9">
				<div class="settings-value settings-w100">
					<?php 
						HtmlWpf::checkboxToggle('settings[use_hide_button_styles]', array(
							'checked' => $useButtonStyles,
						));
						?>
				</div>
			</div>
		</div>
	</div>
	<div class="col-sm-9 col-sm-offset-3">
		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Font', 'woo-product-filter'); ?>
			</div>
			<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
				<div class="settings-value settings-w50">
					<?php 
						HtmlWpf::selectbox('settings[hide_button][button_font_family]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_font_family', $defaultFont),
							'options' => $fontsList,
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>
				<div class="settings-value settings-w50">
					<?php 
						HtmlWpf::text('settings[hide_button][button_font_size]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_font_size', ''),
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
						HtmlWpf::selectbox('settings[hide_button][button_font_style]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_font_style', ''),
							'options' => $fontStyles,
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>
				<div class="settings-value settings-w50" data-style="button_font">
					<?php 
						HtmlWpf::colorpicker('settings[hide_button][button_font_color]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_font_color', ''),
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
						HtmlWpf::selectbox('settings[hide_button][button_font_style_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_font_style_hover', ''),
							'options' => $fontStyles,
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>
				<div class="settings-value settings-w50">
					<?php 
						HtmlWpf::colorpicker('settings[hide_button][button_font_color_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_font_color_hover', ''),
						));
						?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Text shadow', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Set text shadow in this order: color, X, Y, blur.', 'woo-product-filter'); ?>"></i>
			</div>
			<div class="settings-block-values col-xs-8 col-sm-9">
				<div class="settings-value settings-w100">
					<?php 
						HtmlWpf::colorpicker('settings[hide_button][button_text_shadow_color]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_text_shadow_color', ''),
						));
						?>
				</div>
				<div class="settings-value settings-w100">
					<?php 
						HtmlWpf::text('settings[hide_button][button_text_shadow_x]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_text_shadow_x', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_text_shadow_y]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_text_shadow_y', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_text_shadow_blur]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_text_shadow_blur', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Button width', 'woo-product-filter'); ?>
			</div>
			<div class="settings-block-values col-xs-8 col-sm-9">
				<div class="settings-value settings-w100">
					<?php 
						HtmlWpf::text('settings[hide_button][button_width]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_width', ''),
							'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
						));
						HtmlWpf::selectbox('settings[hide_button][button_width_unit]', array(
							'options' => array('%' => '%', 'px' => 'px'),
							'value' => $this->getFilterSetting($hb_settings, 'button_width_unit', '%'),
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Button max width', 'woo-product-filter'); ?>
			</div>
			<div class="settings-block-values col-xs-8 col-sm-9">
				<div class="settings-value settings-w100">
					<?php 
						HtmlWpf::text('settings[hide_button][button_max_width]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_max_width', ''),
							'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
						));
						HtmlWpf::selectbox('settings[hide_button][button_max_width_unit]', array(
							'options' => array('px' => 'px', '%' => '%'),
							'value' => $this->getFilterSetting($hb_settings, 'button_max_width_unit', '%'),
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Button height', 'woo-product-filter'); ?>
			</div>
			<div class="settings-block-values col-xs-8 col-sm-9">
				<div class="settings-value settings-w100">
					<?php 
						HtmlWpf::text('settings[hide_button][button_height]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_height', ''),
							'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
						));
						?>
						<div class="settings-value-label">px</div>
				</div>
			</div>
		</div>
		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Corners radius', 'woo-product-filter'); ?>
			</div>
			<div class="settings-block-values col-xs-8 col-sm-9">
				<div class="settings-value settings-w100">
					<?php 
						HtmlWpf::text('settings[hide_button][button_radius]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_radius', '', true, false, true),
							'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
						));
						HtmlWpf::selectbox('settings[hide_button][button_radius_unit]', array(
							'options' => array('px' => 'px', '%' => '%'),
							'value' => $this->getFilterSetting($hb_settings, 'button_radius_unit', 'px'),
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>
			</div>
		</div>
		<?php 
			$bgType = $this->getFilterSetting($hb_settings, 'button_bg_type', '', false, array('unicolored', 'bicolored', 'gradient', 'pyramid'));
			$classHiddenUni = !$useButtonStyles || 'unicolored' != $bgType ? 'wpfHidden' : '';
			$classHiddenTwo = $useButtonStyles && ( 'bicolored' == $bgType || 'gradient' == $bgType || 'pyramid' == $bgType ) ? '' : 'wpfHidden';
		?>
		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
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
						HtmlWpf::selectbox('settings[hide_button][button_bg_type]', array(
							'value' => $bgType,
							'options' => $bgTypes,
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>
				<div class="settings-value <?php echo esc_attr($classHiddenUni); ?>" data-select="settings[hide_button][button_bg_type]" data-select-value="unicolored" data-style="button_bg">
					<?php 
						HtmlWpf::colorpicker('settings[hide_button][button_bg_color]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_bg_color', ''),
						));
						?>
				</div>
				<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[hide_button][button_bg_type]" data-select-value="bicolored|gradient|pyramid" data-style="button_bg">
					<?php 
						HtmlWpf::colorpicker('settings[hide_button][button_bg_color1]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_bg_color1', ''),
						));
						?>
				</div>
				<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[hide_button][button_bg_type]" data-select-value="bicolored|gradient|pyramid" data-style="button_bg">
					<?php 
						HtmlWpf::colorpicker('settings[hide_button][button_bg_color2]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_bg_color2', ''),
						));
						?>
				</div>
				<div class="clear"></div>
		<?php 
			$bgType = $this->getFilterSetting($hb_settings, 'button_bg_type_hover', '', false, array('unicolored', 'bicolored', 'gradient', 'pyramid'));
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
						HtmlWpf::selectbox('settings[hide_button][button_bg_type_hover]', array(
							'value' => $bgType,
							'options' => $bgTypes,
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>
				<div class="settings-value <?php echo esc_attr($classHiddenUni); ?>" data-select="settings[hide_button][button_bg_type_hover]" data-select-value="unicolored">
					<?php 
						HtmlWpf::colorpicker('settings[hide_button][button_bg_color_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_bg_color_hover', ''),
						));
						?>
				</div>
				<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[hide_button][button_bg_type_hover]" data-select-value="bicolored|gradient|pyramid">
					<?php 
						HtmlWpf::colorpicker('settings[hide_button][button_bg_color1_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_bg_color1_hover', ''),
						));
						?>
				</div>
				<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[hide_button][button_bg_type_hover]" data-select-value="bicolored|gradient|pyramid">
					<?php 
						HtmlWpf::colorpicker('settings[hide_button][button_bg_color2_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_bg_color2_hover', ''),
						));
						?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
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
						HtmlWpf::colorpicker('settings[hide_button][button_border_color]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_border_color', ''),
						));
						?>
				</div>
				<div class="settings-value" data-style="button_border">
					<?php 
						HtmlWpf::text('settings[hide_button][button_border_top]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_border_top', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_border_right]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_border_right', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_border_bottom]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_border_bottom', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_border_left]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_border_left', '', true, false, true),
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
						HtmlWpf::colorpicker('settings[hide_button][button_border_color_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_border_color_hover', ''),
						));
						?>
				</div>
				<div class="settings-value">
					<?php 
						HtmlWpf::text('settings[hide_button][button_border_top_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_border_top_hover', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_border_right_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_border_right_hover', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_border_bottom_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_border_bottom_hover', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_border_left_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_border_left_hover', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
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
						HtmlWpf::colorpicker('settings[hide_button][button_shadow_color]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_shadow_color', ''),
						));
						?>
				</div>
				<div class="settings-value" data-style="button_shadow">
					<?php 
						HtmlWpf::text('settings[hide_button][button_shadow_x]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_shadow_x', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_shadow_y]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_shadow_y', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_shadow_blur]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_shadow_blur', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_shadow_spread]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_shadow_spread', '', true, false, true),
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
						HtmlWpf::colorpicker('settings[hide_button][button_shadow_color_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_shadow_color_hover', ''),
						));
						?>
				</div>
				<div class="settings-value">
					<?php 
						HtmlWpf::text('settings[hide_button][button_shadow_x_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_shadow_x_hover', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_shadow_y_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_shadow_y_hover', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_shadow_blur_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_shadow_blur_hover', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_shadow_spread_hover]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_shadow_spread_hover', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						?>
				</div>
			</div>
		</div>

		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Padding', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set paddings in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
			</div>
			<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
				<div class="settings-value settings-w100">
					<?php 
						HtmlWpf::text('settings[hide_button][button_padding_top]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_padding_top', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_padding_right]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_padding_right', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_padding_bottom]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_padding_bottom', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_padding_left]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_padding_left', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						?>
					<div class="wpfRightLabel">px</div>
				</div>
			</div>
		</div>
		<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_hide_button_styles]">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Margin', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set margins in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
			</div>
			<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
				<div class="settings-value settings-w100">
					<?php 
						HtmlWpf::text('settings[hide_button][button_margin_top]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_margin_top', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_margin_right]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_margin_right', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_margin_bottom]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_margin_bottom', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						HtmlWpf::text('settings[hide_button][button_margin_left]', array(
							'value' => $this->getFilterSetting($hb_settings, 'button_margin_left', '', true, false, true),
							'attrs' => 'class="wpfMiniInput"',
						));
						?>
					<div class="wpfRightLabel">px</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
// ====== HIDE BUTTON STYLIN END ======
?>
<!-- end of file -->
