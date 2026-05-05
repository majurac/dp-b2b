<?php
$settings = $this->getFilterSetting($this->settings, 'settings', array());
$styles = $this->getFilterSetting($settings, 'styles', array());
$mobile = $this->getFilterSetting($styles, 'fmobile', array());
$desktop = $this->getFilterSetting($styles, 'fdesktop', array());
$module = $this->getModule();
$modPath = $module->getModPath();
$defaultIcon = $module->getDefaultFloatingIcon();
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
$bgKeys = array_keys($bgTypes);
$closeIcons = array(
	'times' => esc_attr__('cross', 'woo-product-filter'), 
	'window-close-o' => esc_attr__('square', 'woo-product-filter'), 
	'window-close' => esc_attr__('filled square', 'woo-product-filter'), 
	'times-circle' => esc_attr__('circle', 'woo-product-filter'), 
	'times-circle-o' => esc_attr__('filled circle', 'woo-product-filter'), 
);
$closeKeys = array_keys($closeIcons);
$overlays = array(
	'blackout' => esc_attr__('blackout', 'woo-product-filter'), 
	'blur' => esc_attr__('blur', 'woo-product-filter'),
	'none' => esc_attr__('none', 'woo-product-filter'),
);
$overlayKeys = array_keys($overlays);
$scrollbar = array(
	'auto' => esc_attr__('default', 'woo-product-filter'), 
	'thin' => esc_attr__('thin', 'woo-product-filter'), 
	'none' => esc_attr__('none', 'woo-product-filter'), 
);
$scrollbarKeys = array_keys($scrollbar);
$arrival = array(
	'right' => esc_attr__('right', 'woo-product-filter'), 
	'left' => esc_attr__('left', 'woo-product-filter'), 
	'top' => esc_attr__('top', 'woo-product-filter'),
	'bottom' => esc_attr__('bottom', 'woo-product-filter'), 
);
$arrivalKeys = array_keys($arrival);

$useFloatingMode = $this->getFilterSetting($settings, 'floating_mode', 0);
$devices = $this->getFilterSetting($settings, 'floating_devices', '', false, array('mobile', 'desktop'));
$hiddenStyle = $useFloatingMode ? '' : 'wpfHidden';
$hiddenDesktop = 'mobile' == $devices ? 'wpfHidden' : '';
$hiddenMobile = 'desktop' == $devices ? 'wpfHidden' : '';

HtmlWpf::hidden('', array('value' => $defaultIcon, 'attrs' => 'id="wpfFloatingDefaultIcon"'));
?>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use Floating Modе', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Choose floating mode to place the filter in the popup window.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/buttons-design/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::checkboxToggle('settings[floating_mode]', array(
					'checked' => $useFloatingMode,
				));
				?>
		</div>
		<div class="settings-value settings-w50 <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
			<?php 
				HtmlWpf::selectbox('settings[floating_devices]', array(
					'value' => $devices,
					'options' => array(
						'' => esc_attr__('All devices', 'woo-product-filter'),
						'mobile' => esc_attr__('Only Mobile', 'woo-product-filter'),
						'desktop' => esc_attr__('Only Desktop', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<?php
	$callButton = $this->getFilterSetting($settings, 'floating_call_button', 'plugin', false, array('plugin', 'custom'));
	$customButton = ( 'custom' == $callButton );
	$buttonHidden = !$useFloatingMode || $customButton ? 'wpfHidden' : '';
?>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Сall button', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[floating_call_button]', array(
					'value' => $callButton,
					'options' => array(
						'plugin' => esc_attr__('Plugin button', 'woo-product-filter'),
						'custom' => esc_attr__('Custom button', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50 <?php echo $customButton ? '' : 'wpfHidden'; ?>" data-select="settings[floating_call_button]" data-select-value="custom">
			<?php 
				HtmlWpf::text('', array(
					'value' => 'window.wpfFrontendPage.showFloatingPopup(' . $this->filterId . ');',
					'attrs' => 'readonly class="woobewoo-flat-input woobewoo-width300" onclick="this.setSelectionRange(0, this.value.length);"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3 settings-block-title">
		<?php esc_html_e('Button design', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-label col-xs-4 settings-block-title <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<?php esc_html_e('Desktop', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-label col-xs-4 settings-block-title <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<?php esc_html_e('Mobile', 'woo-product-filter'); ?>
	</div>
</div>
<?php
	$dType = $this->getFilterSetting($desktop, 'button_type', 'text');
	$mType = $this->getFilterSetting($mobile, 'button_type', 'text');
	$dIcon = $this->getFilterSetting($desktop, 'button_icon', $defaultIcon);
	$mIcon = $this->getFilterSetting($mobile, 'button_icon', $defaultIcon);
	$isIconD = ( 'icon' == $dType );
	$isIconM = ( 'icon' == $mType );
?>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Button type', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][button_type]', array(
					'value' => $dType,
					'options' => array(
						'text' => esc_attr__('Text', 'woo-product-filter'),
						'icon' => esc_attr__('Icon', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50 <?php echo !$isIconD ? '' : 'wpfHidden'; ?>" data-select="settings[styles][fdesktop][button_type]" data-select-value="text">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_text]', array(
					'value' => $this->getFilterSetting($desktop, 'button_text', esc_attr__('Filter', 'woo-product-filter')),
					'attrs' => 'class="woobewoo-flat-input woobewoo-width100"',
				));
				?>
		</div>
		<div class="settings-value settings-w50 <?php echo $isIconD ? '' : 'wpfHidden'; ?>" data-select="settings[styles][fdesktop][button_type]" data-select-value="icon">
			<?php 
				HtmlWpf::button(array(
					'value' => esc_attr__('Choose', 'woo-product-filter'),
					'attrs' => 'data-type="image" class="button button-mini wpfSelectFloatingIcon"',
				));
				HtmlWpf::hidden('settings[styles][fdesktop][button_icon]', array(
					'value' => $dIcon,
					'attrs' => 'class="wpfHiddenFloatingIcon"',
				));
				?>
		</div>
		<div class="settings-value settings-w50 <?php echo $isIconD ? '' : 'wpfHidden'; ?>" data-select="settings[styles][fdesktop][button_type]" data-select-value="icon">
			<div class="wpfFloatingIconPreview" style="<?php echo esc_attr($dIcon); ?>"></div>
			<i class="fa fa-times wpfFloatingRemoveIcon woobewoo-tooltip <?php echo $dIcon == $defaultIcon ? 'wpfHidden' : ''; ?>" title="<?php echo esc_attr__('Remove icon', 'woo-product-filter'); ?>"></i>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][button_type]', array(
					'value' => $mType,
					'options' => array(
						'text' => esc_attr__('Text', 'woo-product-filter'),
						'icon' => esc_attr__('Icon', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50 <?php echo !$isIconM ? '' : 'wpfHidden'; ?>" data-select="settings[styles][fmobile][button_type]" data-select-value="text">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_text]', array(
					'value' => $this->getFilterSetting($mobile, 'button_text', esc_attr__('Filter', 'woo-product-filter')),
					'attrs' => 'class="woobewoo-flat-input woobewoo-width100"',
				));
				?>
		</div>
		<div class="settings-value settings-w50 <?php echo $isIconM ? '' : 'wpfHidden'; ?>" data-select="settings[styles][fmobile][button_type]" data-select-value="icon">
			<?php 
				HtmlWpf::button(array(
					'value' => esc_attr__('Choose', 'woo-product-filter'),
					'attrs' => 'data-type="image" class="button button-mini wpfSelectFloatingIcon"',
				));
				HtmlWpf::hidden('settings[styles][fmobile][button_icon]', array(
					'value' => $mIcon,
					'attrs' => 'class="wpfHiddenFloatingIcon"',
				));
				?>
		</div>
		<div class="settings-value settings-w50 <?php echo $isIconM ? '' : 'wpfHidden'; ?>" data-select="settings[styles][fmobile][button_type]" data-select-value="icon">
			<div class="wpfFloatingIconPreview" style="<?php echo esc_attr($mIcon); ?>"></div>
			<i class="fa fa-times wpfFloatingRemoveIcon woobewoo-tooltip <?php echo $mIcon == $defaultIcon ? 'wpfHidden' : ''; ?>" title="<?php echo esc_attr__('Remove icon', 'woo-product-filter'); ?>"></i>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Fixed/float', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('For floating button set position in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				$fixed = $this->getFilterSetting($desktop, 'button_fixed', 'fixed', false, array('fixed', 'float'));
				HtmlWpf::selectbox('settings[styles][fdesktop][button_fixed]', array(
					'value' => $fixed,
					'options' => array(
						'fixed' => esc_attr__('Fixed', 'woo-product-filter'),
						'float' => esc_attr__('Floating', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w100 <?php echo ( 'fixed' == $fixed ? 'wpfHidden' : '' ); ?>" data-select="settings[styles][fdesktop][button_fixed]" data-select-value="float">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_position_top]', array(
					'value' => $this->getFilterSetting($desktop, 'button_position_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_position_right]', array(
					'value' => $this->getFilterSetting($desktop, 'button_position_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_position_bottom]', array(
					'value' => $this->getFilterSetting($desktop, 'button_position_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_position_left]', array(
					'value' => $this->getFilterSetting($desktop, 'button_position_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
		<div class="settings-value settings-w100 <?php echo ( 'fixed' == $fixed ? 'wpfHidden' : '' ); ?>" data-select="settings[styles][fdesktop][button_fixed]" data-select-value="float">
			<div class="settings-value-label"><?php esc_html_e('show if visible products', 'woo-product-filter'); ?></div>
			<?php 
				HtmlWpf::checkboxToggle('settings[styles][fdesktop][button_product_visible]', array(
					'checked' => $this->getFilterSetting($desktop, 'button_product_visible', 0, 1),
				));
				?>
		</div>
		
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				$fixed = $this->getFilterSetting($mobile, 'button_fixed', 'fixed', false, array('fixed', 'float'));
				HtmlWpf::selectbox('settings[styles][fmobile][button_fixed]', array(
					'value' => $fixed,
					'options' => array(
						'fixed' => esc_attr__('Fixed', 'woo-product-filter'),
						'float' => esc_attr__('Floating', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w100 <?php echo ( 'fixed' == $fixed ? 'wpfHidden' : '' ); ?>" data-select="settings[styles][fmobile][button_fixed]" data-select-value="float">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_position_top]', array(
					'value' => $this->getFilterSetting($mobile, 'button_position_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_position_right]', array(
					'value' => $this->getFilterSetting($mobile, 'button_position_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_position_bottom]', array(
					'value' => $this->getFilterSetting($mobile, 'button_position_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_position_left]', array(
					'value' => $this->getFilterSetting($mobile, 'button_position_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
		<div class="settings-value settings-w100 <?php echo ( 'fixed' == $fixed ? 'wpfHidden' : '' ); ?>" data-select="settings[styles][fmobile][button_fixed]" data-select-value="float">
			<div class="settings-value-label"><?php esc_html_e('show if visible products', 'woo-product-filter'); ?></div>
			<?php 
				HtmlWpf::checkboxToggle('settings[styles][fmobile][button_product_visible]', array(
					'checked' => $this->getFilterSetting($mobile, 'button_product_visible', 0, 1),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Font', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][button_font_family]', array(
					'value' => $this->getFilterSetting($desktop, 'button_font_family', $defaultFont),
					'options' => $fontsList,
					'attrs' => 'class="woobewoo-flat-input woobewoo-width120"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_font_size]', array(
					'value' => $this->getFilterSetting($desktop, 'button_font_size', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"',
				));
				?>
			<div class="settings-value-label">px</div>
		</div>
		<div class="clear"></div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width40">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value settings-w50" data-style="button_font_fdesktop">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][button_font_style]', array(
					'value' => $this->getFilterSetting($desktop, 'button_font_style', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input woobewoo-width60"',
				));
				?>
		</div>
		<div class="settings-value settings-w50" data-style="button_font_fdesktop">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_font_color]', array(
					'value' => $this->getFilterSetting($desktop, 'button_font_color', ''),
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width40">
				<a href="#" class="wpfCopyStyles woobewoo-tooltip" title="<?php echo esc_attr__('Copy normal styles', 'woo-product-filter'); ?>" data-style="button_font_fdesktop"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][button_font_style_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_font_style_hover', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input woobewoo-width60"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_font_color_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_font_color_hover', ''),
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][button_font_family]', array(
					'value' => $this->getFilterSetting($mobile, 'button_font_family', $defaultFont),
					'options' => $fontsList,
					'attrs' => 'class="woobewoo-flat-input woobewoo-width120"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_font_size]', array(
					'value' => $this->getFilterSetting($mobile, 'button_font_size', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"',
				));
				?>
			<div class="settings-value-label">px</div>
		</div>
		<div class="clear"></div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width40">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value settings-w50" data-style="button_font_fmobile">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][button_font_style]', array(
					'value' => $this->getFilterSetting($mobile, 'button_font_style', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input woobewoo-width60"',
				));
				?>
		</div>
		<div class="settings-value settings-w50" data-style="button_font_fmobile">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_font_color]', array(
					'value' => $this->getFilterSetting($mobile, 'button_font_color', ''),
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width40">
				<a href="#" class="wpfCopyStyles woobewoo-tooltip" title="<?php echo esc_attr__('Copy normal styles', 'woo-product-filter'); ?>" data-style="button_font_fmobile"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][button_font_style_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_font_style_hover', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input woobewoo-width60"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_font_color_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_font_color_hover', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Text shadow', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Set text shadow in this order: color, X, Y, blur.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_text_shadow_color]', array(
					'value' => $this->getFilterSetting($desktop, 'button_text_shadow_color', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_text_shadow_x]', array(
					'value' => $this->getFilterSetting($desktop, 'button_text_shadow_x', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_text_shadow_y]', array(
					'value' => $this->getFilterSetting($desktop, 'button_text_shadow_y', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_text_shadow_blur]', array(
					'value' => $this->getFilterSetting($desktop, 'button_text_shadow_blur', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_text_shadow_color]', array(
					'value' => $this->getFilterSetting($mobile, 'button_text_shadow_color', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_text_shadow_x]', array(
					'value' => $this->getFilterSetting($mobile, 'button_text_shadow_x', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_text_shadow_y]', array(
					'value' => $this->getFilterSetting($mobile, 'button_text_shadow_y', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_text_shadow_blur]', array(
					'value' => $this->getFilterSetting($mobile, 'button_text_shadow_blur', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Button width', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_width]', array(
					'value' => $this->getFilterSetting($desktop, 'button_width', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][fdesktop][button_width_unit]', array(
					'options' => array('%' => '%', 'px' => 'px'),
					'value' => $this->getFilterSetting($desktop, 'button_width_unit', '%'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_width]', array(
					'value' => $this->getFilterSetting($mobile, 'button_width', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][fmobile][button_width_unit]', array(
					'options' => array('%' => '%', 'px' => 'px'),
					'value' => $this->getFilterSetting($mobile, 'button_width_unit', '%'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Button height', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_height]', array(
					'value' => $this->getFilterSetting($desktop, 'button_height', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				?>
				<div class="settings-value-label">px</div>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_height]', array(
					'value' => $this->getFilterSetting($mobile, 'button_height', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				?>
				<div class="settings-value-label">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Corners radius', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_radius]', array(
					'value' => $this->getFilterSetting($desktop, 'button_radius', '', true, false, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][fdesktop][button_radius_unit]', array(
					'options' => array('px' => 'px', '%' => '%'),
					'value' => $this->getFilterSetting($desktop, 'button_radius_unit', 'px'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_radius]', array(
					'value' => $this->getFilterSetting($mobile, 'button_radius', '', true, false, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][fmobile][button_radius_unit]', array(
					'options' => array('px' => 'px', '%' => '%'),
					'value' => $this->getFilterSetting($mobile, 'button_radius_unit', 'px'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Background', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
<?php 
	$bgType = $this->getFilterSetting($desktop, 'button_bg_type', '', false, $bgKeys);
	$classHiddenUni = !$useFloatingMode || 'unicolored' != $bgType ? 'wpfHidden' : '';
	$classHiddenTwo = $useFloatingMode && ( 'bicolored' == $bgType || 'gradient' == $bgType || 'pyramid' == $bgType ) ? '' : 'wpfHidden';
?>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value" data-style="button_bg_fdesktop">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][button_bg_type]', array(
					'value' => $bgType,
					'options' => $bgTypes,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenUni); ?>" data-select="settings[styles][fdesktop][button_bg_type]" data-select-value="unicolored" data-style="button_bg_fdesktop">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_bg_color]', array(
					'value' => $this->getFilterSetting($desktop, 'button_bg_color', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][fdesktop][button_bg_type]" data-select-value="bicolored|gradient|pyramid" data-style="button_bg_fdesktop">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_bg_color1]', array(
					'value' => $this->getFilterSetting($desktop, 'button_bg_color1', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][fdesktop][button_bg_type]" data-select-value="bicolored|gradient|pyramid" data-style="button_bg_fdesktop">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_bg_color2]', array(
					'value' => $this->getFilterSetting($desktop, 'button_bg_color2', ''),
				));
				?>
		</div>
		<div class="clear"></div>
<?php 
	$bgType = $this->getFilterSetting($desktop, 'button_bg_type_hover', '', false, $bgKeys);
	$classHiddenUni = !$useFloatingMode || 'unicolored' != $bgType ? 'wpfHidden' : '';
	$classHiddenTwo = $useFloatingMode && ( 'bicolored' == $bgType || 'gradient' == $bgType || 'pyramid' == $bgType ) ? '' : 'wpfHidden';
?>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<a href="#" class="wpfCopyStyles" data-style="button_bg_fdesktop"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][button_bg_type_hover]', array(
					'value' => $bgType,
					'options' => $bgTypes,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenUni); ?>" data-select="settings[styles][fdesktop][button_bg_type_hover]" data-select-value="unicolored">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_bg_color_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_bg_color_hover', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][fdesktop][button_bg_type_hover]" data-select-value="bicolored|gradient|pyramid">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_bg_color1_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_bg_color1_hover', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][fdesktop][button_bg_type_hover]" data-select-value="bicolored|gradient|pyramid">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_bg_color2_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_bg_color2_hover', ''),
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
<?php 
	$bgType = $this->getFilterSetting($mobile, 'button_bg_type', '', false, $bgKeys);
	$classHiddenUni = !$useFloatingMode || 'unicolored' != $bgType ? 'wpfHidden' : '';
	$classHiddenTwo = $useFloatingMode && ( 'bicolored' == $bgType || 'gradient' == $bgType || 'pyramid' == $bgType ) ? '' : 'wpfHidden';
?>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value" data-style="button_bg_fmobile">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][button_bg_type]', array(
					'value' => $bgType,
					'options' => $bgTypes,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenUni); ?>" data-select="settings[styles][fmobile][button_bg_type]" data-select-value="unicolored" data-style="button_bg_fmobile">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_bg_color]', array(
					'value' => $this->getFilterSetting($mobile, 'button_bg_color', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][fmobile][button_bg_type]" data-select-value="bicolored|gradient|pyramid" data-style="button_bg_fmobile">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_bg_color1]', array(
					'value' => $this->getFilterSetting($mobile, 'button_bg_color1', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][fmobile][button_bg_type]" data-select-value="bicolored|gradient|pyramid" data-style="button_bg_fmobile">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_bg_color2]', array(
					'value' => $this->getFilterSetting($mobile, 'button_bg_color2', ''),
				));
				?>
		</div>
		<div class="clear"></div>
<?php 
	$bgType = $this->getFilterSetting($mobile, 'button_bg_type_hover', '', false, $bgKeys);
	$classHiddenUni = !$useFloatingMode || 'unicolored' != $bgType ? 'wpfHidden' : '';
	$classHiddenTwo = $useFloatingMode && ( 'bicolored' == $bgType || 'gradient' == $bgType || 'pyramid' == $bgType ) ? '' : 'wpfHidden';
?>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<a href="#" class="wpfCopyStyles" data-style="button_bg_fmobile"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][button_bg_type_hover]', array(
					'value' => $bgType,
					'options' => $bgTypes,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenUni); ?>" data-select="settings[styles][fmobile][button_bg_type_hover]" data-select-value="unicolored">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_bg_color_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_bg_color_hover', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][fmobile][button_bg_type_hover]" data-select-value="bicolored|gradient|pyramid">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_bg_color1_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_bg_color1_hover', ''),
				));
				?>
		</div>
		<div class="settings-value <?php echo esc_attr($classHiddenTwo); ?>" data-select="settings[styles][fmobile][button_bg_type_hover]" data-select-value="bicolored|gradient|pyramid">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_bg_color2_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_bg_color2_hover', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Borders', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Set borders width in this order: color, top, right, bottom, left.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value" data-style="button_border_fdesktop">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_border_color]', array(
					'value' => $this->getFilterSetting($desktop, 'button_border_color', ''),
				));
				?>
		</div>
		<div class="settings-value" data-style="button_border_fdesktop">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_border_top]', array(
					'value' => $this->getFilterSetting($desktop, 'button_border_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_border_right]', array(
					'value' => $this->getFilterSetting($desktop, 'button_border_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_border_bottom]', array(
					'value' => $this->getFilterSetting($desktop, 'button_border_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_border_left]', array(
					'value' => $this->getFilterSetting($desktop, 'button_border_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<a href="#" class="wpfCopyStyles" data-style="button_border_fdesktop"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_border_color_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_border_color_hover', ''),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_border_top_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_border_top_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_border_right_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_border_right_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_border_bottom_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_border_bottom_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_border_left_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_border_left_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value" data-style="button_border_fmobile">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_border_color]', array(
					'value' => $this->getFilterSetting($mobile, 'button_border_color', ''),
				));
				?>
		</div>
		<div class="settings-value" data-style="button_border_fmobile">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_border_top]', array(
					'value' => $this->getFilterSetting($mobile, 'button_border_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_border_right]', array(
					'value' => $this->getFilterSetting($mobile, 'button_border_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_border_bottom]', array(
					'value' => $this->getFilterSetting($mobile, 'button_border_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_border_left]', array(
					'value' => $this->getFilterSetting($mobile, 'button_border_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<a href="#" class="wpfCopyStyles" data-style="button_border_fmobile"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_border_color_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_border_color_hover', ''),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_border_top_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_border_top_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_border_right_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_border_right_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_border_bottom_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_border_bottom_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_border_left_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_border_left_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
</div>

<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Button shadow', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Set button shadow in this order: color, X, Y, blur, spread (px).', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value" data-style="button_shadow_fdesktop">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_shadow_color]', array(
					'value' => $this->getFilterSetting($desktop, 'button_shadow_color', ''),
				));
				?>
		</div>
		<div class="settings-value" data-style="button_shadow_fdesktop">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_shadow_x]', array(
					'value' => $this->getFilterSetting($desktop, 'button_shadow_x', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_shadow_y]', array(
					'value' => $this->getFilterSetting($desktop, 'button_shadow_y', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_shadow_blur]', array(
					'value' => $this->getFilterSetting($desktop, 'button_shadow_blur', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_shadow_spread]', array(
					'value' => $this->getFilterSetting($desktop, 'button_shadow_spread', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<a href="#" class="wpfCopyStyles" data-style="button_shadow_fdesktop"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][button_shadow_color_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_shadow_color_hover', ''),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_shadow_x_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_shadow_x_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_shadow_y_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_shadow_y_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_shadow_blur_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_shadow_blur_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_shadow_spread_hover]', array(
					'value' => $this->getFilterSetting($desktop, 'button_shadow_spread_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<?php esc_html_e('normal', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value" data-style="button_shadow_fmobile">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_shadow_color]', array(
					'value' => $this->getFilterSetting($mobile, 'button_shadow_color', ''),
				));
				?>
		</div>
		<div class="settings-value" data-style="button_shadow_fmobile">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_shadow_x]', array(
					'value' => $this->getFilterSetting($mobile, 'button_shadow_x', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_shadow_y]', array(
					'value' => $this->getFilterSetting($mobile, 'button_shadow_y', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_shadow_blur]', array(
					'value' => $this->getFilterSetting($mobile, 'button_shadow_blur', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_shadow_spread]', array(
					'value' => $this->getFilterSetting($mobile, 'button_shadow_spread', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width40">
				<a href="#" class="wpfCopyStyles" data-style="button_shadow_fmobile"><?php esc_html_e('hover', 'woo-product-filter'); ?></a>
			</div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][button_shadow_color_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_shadow_color_hover', ''),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_shadow_x_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_shadow_x_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_shadow_y_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_shadow_y_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_shadow_blur_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_shadow_blur_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_shadow_spread_hover]', array(
					'value' => $this->getFilterSetting($mobile, 'button_shadow_spread_hover', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Padding', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set paddings in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_padding_top]', array(
					'value' => $this->getFilterSetting($desktop, 'button_padding_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_padding_right]', array(
					'value' => $this->getFilterSetting($desktop, 'button_padding_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_padding_bottom]', array(
					'value' => $this->getFilterSetting($desktop, 'button_padding_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_padding_left]', array(
					'value' => $this->getFilterSetting($desktop, 'button_padding_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_padding_top]', array(
					'value' => $this->getFilterSetting($mobile, 'button_padding_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_padding_right]', array(
					'value' => $this->getFilterSetting($mobile, 'button_padding_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_padding_bottom]', array(
					'value' => $this->getFilterSetting($mobile, 'button_padding_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_padding_left]', array(
					'value' => $this->getFilterSetting($mobile, 'button_padding_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($buttonHidden); ?>" data-parent="settings[floating_mode]" data-select="settings[floating_call_button]" data-select-value="plugin">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Margin', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set margins in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][button_margin_top]', array(
					'value' => $this->getFilterSetting($desktop, 'button_margin_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_margin_right]', array(
					'value' => $this->getFilterSetting($desktop, 'button_margin_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_margin_bottom]', array(
					'value' => $this->getFilterSetting($desktop, 'button_margin_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][button_margin_left]', array(
					'value' => $this->getFilterSetting($desktop, 'button_margin_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][button_margin_top]', array(
					'value' => $this->getFilterSetting($mobile, 'button_margin_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_margin_right]', array(
					'value' => $this->getFilterSetting($mobile, 'button_margin_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_margin_bottom]', array(
					'value' => $this->getFilterSetting($mobile, 'button_margin_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][button_margin_left]', array(
					'value' => $this->getFilterSetting($mobile, 'button_margin_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3 settings-block-title">
		<?php esc_html_e('Title design', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-label col-xs-4 settings-block-title <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<?php esc_html_e('Desktop', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-label col-xs-4 settings-block-title <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<?php esc_html_e('Mobile', 'woo-product-filter'); ?>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Title', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
<?php
	$useTitle = $this->getFilterSetting($desktop, 'use_title', 0, 1);
	$hiddenTitle = !$useFloatingMode || !$useTitle ? 'wpfHidden' : '';
?>
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::checkboxToggle('settings[styles][fdesktop][use_title]', array(
					'checked' => $useTitle,
				));
				?>
		</div>
		<div class="settings-value settings-w50 <?php echo esc_attr($hiddenTitle); ?>" data-parent="settings[styles][fdesktop][use_title]">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][title_text]', array(
					'value' => $this->getFilterSetting($desktop, 'title_text', __('Filter', 'woo-product-filter')),
					'attrs' => 'class="woobewoo-flat-input woobewoo-width120"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
<?php
	$useTitle = $this->getFilterSetting($mobile, 'use_title', 0, 1);
	$hiddenTitle = !$useFloatingMode || !$useTitle ? 'wpfHidden' : '';
?>
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::checkboxToggle('settings[styles][fmobile][use_title]', array(
					'checked' => $useTitle,
				));
				?>
		</div>
		<div class="settings-value settings-w50 <?php echo esc_attr($hiddenTitle); ?>" data-parent="settings[styles][fmobile][use_title]">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][title_text]', array(
					'value' => $this->getFilterSetting($mobile, 'title_text', __('Filter', 'woo-product-filter')),
					'attrs' => 'class="woobewoo-flat-input woobewoo-width120"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Font', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][title_font_family]', array(
					'value' => $this->getFilterSetting($desktop, 'title_font_family', $defaultFont),
					'options' => $fontsList,
					'attrs' => 'class="woobewoo-flat-input woobewoo-width120"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][title_font_size]', array(
					'value' => $this->getFilterSetting($desktop, 'title_font_size', '16', true, false, true, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"',
				));
				?>
			<div class="settings-value-label">px</div>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][title_font_style]', array(
					'value' => $this->getFilterSetting($desktop, 'title_font_style', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input woobewoo-width60"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][title_font_color]', array(
					'value' => $this->getFilterSetting($desktop, 'title_font_color', ''),
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][title_font_family]', array(
					'value' => $this->getFilterSetting($mobile, 'title_font_family', $defaultFont),
					'options' => $fontsList,
					'attrs' => 'class="woobewoo-flat-input woobewoo-width120"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][title_font_size]', array(
					'value' => $this->getFilterSetting($mobile, 'title_font_size', '16', true, false, true, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"',
				));
				?>
			<div class="settings-value-label">px</div>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][title_font_style]', array(
					'value' => $this->getFilterSetting($mobile, 'title_font_style', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input woobewoo-width60"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][title_font_color]', array(
					'value' => $this->getFilterSetting($mobile, 'title_font_color', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Background', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][title_bg_color]', array(
					'value' => $this->getFilterSetting($desktop, 'title_bg_color', '#b9dbf7', false, false, false, true),
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][title_bg_color]', array(
					'value' => $this->getFilterSetting($mobile, 'title_bg_color', '#b9dbf7', false, false, false, true),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Title borders', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Set borders width in this order: color, top, right, bottom, left.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][title_border_color]', array(
					'value' => $this->getFilterSetting($desktop, 'title_border_color', '#a3a3a3', false, false, false, true),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][title_border_top]', array(
					'value' => $this->getFilterSetting($desktop, 'title_border_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][title_border_right]', array(
					'value' => $this->getFilterSetting($desktop, 'title_border_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][title_border_bottom]', array(
					'value' => $this->getFilterSetting($desktop, 'title_border_bottom', '1', true, false, true, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][title_border_left]', array(
					'value' => $this->getFilterSetting($desktop, 'title_border_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][title_border_color]', array(
					'value' => $this->getFilterSetting($mobile, 'title_border_color', '#a3a3a3', false, false, false, true),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][title_border_top]', array(
					'value' => $this->getFilterSetting($mobile, 'title_border_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][title_border_right]', array(
					'value' => $this->getFilterSetting($mobile, 'title_border_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][title_border_bottom]', array(
					'value' => $this->getFilterSetting($mobile, 'title_border_bottom', '1', true, false, true, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][title_border_left]', array(
					'value' => $this->getFilterSetting($mobile, 'title_border_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block wfpFloatingIconClose <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Icon Close', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				$icon = $this->getFilterSetting($desktop, 'popup_close_icon', 'times', false, $closeKeys);
				HtmlWpf::selectbox('settings[styles][fdesktop][popup_close_icon]', array(
					'options' => $closeIcons,
					'value' => $icon,
					'attrs' => 'class="woobewoo-flat-input wfpIconClose"',
				));
				?>
			<div class="wpfRightLabel wfpIconClosePreview"><i class="fa fa-<?php echo esc_attr($icon); ?>"></i></div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][popup_close_color]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_close_color', ''),
				));
				?>
		</div>
		<div class="settings-value">
			<div class="settings-value-label">
				<?php esc_html_e('size', 'woo-product-filter'); ?>
			</div>
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][popup_close_size]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_close_size', 16, true, false, false, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60 wfpIconCloseSize"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				$icon = $this->getFilterSetting($mobile, 'popup_close_icon', 'times', false, $closeKeys);
				HtmlWpf::selectbox('settings[styles][fmobile][popup_close_icon]', array(
					'options' => $closeIcons,
					'value' => $icon,
					'attrs' => 'class="woobewoo-flat-input wfpIconClose"',
				));
				?>
			<div class="wpfRightLabel wfpIconClosePreview"><i class="fa fa-<?php echo esc_attr($icon); ?>"></i></div>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][popup_close_color]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_close_color', ''),
				));
				?>
		</div>
		<div class="settings-value">
			<div class="settings-value-label">
				<?php esc_html_e('size', 'woo-product-filter'); ?>
			</div>
			<?php 
				HtmlWpf::text('settings[styles][fmobile][popup_close_size]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_close_size', 16, true, false, false, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60 wfpIconCloseSize"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Padding', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set paddings in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][title_padding_top]', array(
					'value' => $this->getFilterSetting($desktop, 'title_padding_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][title_padding_right]', array(
					'value' => $this->getFilterSetting($desktop, 'title_padding_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][title_padding_bottom]', array(
					'value' => $this->getFilterSetting($desktop, 'title_padding_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][title_padding_left]', array(
					'value' => $this->getFilterSetting($desktop, 'title_padding_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][title_padding_top]', array(
					'value' => $this->getFilterSetting($mobile, 'title_padding_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][title_padding_right]', array(
					'value' => $this->getFilterSetting($mobile, 'title_padding_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][title_padding_bottom]', array(
					'value' => $this->getFilterSetting($mobile, 'title_padding_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][title_padding_left]', array(
					'value' => $this->getFilterSetting($mobile, 'title_padding_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3 settings-block-title">
		<?php esc_html_e('Popup design', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-label col-xs-4 settings-block-title <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<?php esc_html_e('Desktop', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-label col-xs-4 settings-block-title <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<?php esc_html_e('Mobile', 'woo-product-filter'); ?>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Popup width', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][popup_width]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_width', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][fdesktop][popup_width_unit]', array(
					'options' => array('%' => '%', 'px' => 'px'),
					'value' => $this->getFilterSetting($desktop, 'popup_width_unit', '%'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][popup_width]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_width', 100, true, false, true, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][fmobile][popup_width_unit]', array(
					'options' => array('%' => '%', 'px' => 'px'),
					'value' => $this->getFilterSetting($mobile, 'popup_width_unit', '%'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Popup height', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::text('settings[styles][fdesktop][popup_height]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_height', 100, true, false, true, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][fdesktop][popup_height_unit]', array(
					'options' => array('%' => '%', 'px' => 'px'),
					'value' => $this->getFilterSetting($desktop, 'popup_height_unit', '%'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][popup_height]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_height', 100, true, false, true, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][fmobile][popup_height_unit]', array(
					'options' => array('%' => '%', 'px' => 'px'),
					'value' => $this->getFilterSetting($mobile, 'popup_height_unit', '%'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Background', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][popup_bg_color]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_bg_color', ''),
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][popup_bg_color]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_bg_color', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Popup borders', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Set borders width in this order: color, top, right, bottom, left.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][popup_border_color]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_border_color', '#cccccc', false, false, false, true),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][popup_border_top]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_border_top', '1', true, false, true, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_border_right]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_border_right', '1', true, false, true, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_border_bottom]', array(
					'value' => $this->getFilterSetting($desktop, 'popupn_border_bottom', '1', true, false, true, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_border_left]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_border_left', '1', true, false, true, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][popup_border_color]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_border_color', '#cccccc', false, false, false, true),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][popup_border_top]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_border_top', '1', true, false, true, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_border_right]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_border_right', '1', true, false, true, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_border_bottom]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_border_bottom', '1', true, false, true, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_border_left]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_border_left', '1', true, false, true, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Popup shadow', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Set popup shadow in this order: color, X, Y, blur, spread (px).', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][popup_shadow_color]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_shadow_color', ''),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][popup_shadow_x]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_shadow_x', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_shadow_y]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_shadow_y', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_shadow_blur]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_shadow_blur', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_shadow_spread]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_shadow_spread', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][popup_shadow_color]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_shadow_color', ''),
				));
				?>
		</div>
		<div class="settings-value">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][popup_shadow_x]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_shadow_x', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_shadow_y]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_shadow_y', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_shadow_blur]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_shadow_blur', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_shadow_spread]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_shadow_spread', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Corners radius', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][popup_radius]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_radius', '', true, false, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][fdesktop][popup_radius_unit]', array(
					'options' => array('px' => 'px', '%' => '%'),
					'value' => $this->getFilterSetting($desktop, 'popup_radius_unit', 'px'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][popup_radius]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_radius', '', true, false, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				HtmlWpf::selectbox('settings[styles][fmobile][popup_radius_unit]', array(
					'options' => array('px' => 'px', '%' => '%'),
					'value' => $this->getFilterSetting($mobile, 'popup_radius_unit', 'px'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Padding', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set paddings in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][popup_padding_top]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_padding_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_padding_right]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_padding_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_padding_bottom]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_padding_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_padding_left]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_padding_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][popup_padding_top]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_padding_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_padding_right]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_padding_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_padding_bottom]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_padding_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_padding_left]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_padding_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Scrollbar', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set scrollbar property in this order: thumb color, track color and width.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][popup_scrollbar_thumb]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_scrollbar_thumb', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fdesktop][popup_scrollbar_track]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_scrollbar_track', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][popup_scrollbar_width]', array(
					'options' => $scrollbar,
					'value' => $this->getFilterSetting($desktop, 'popup_scrollbar_width', 'auto', false, $scrollbarKeys),
					'attrs' => 'class="woobewoo-flat-input woobewoo-width100"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][popup_scrollbar_thumb]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_scrollbar_thumb', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::colorpicker('settings[styles][fmobile][popup_scrollbar_track]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_scrollbar_track', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][popup_scrollbar_width]', array(
					'options' => $scrollbar,
					'value' => $this->getFilterSetting($mobile, 'popup_scrollbar_width', 'auto', false, $scrollbarKeys),
					'attrs' => 'class="woobewoo-flat-input woobewoo-width100"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Overlay remaining area', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][popup_overlay]', array(
					'options' => $overlays,
					'value' => $this->getFilterSetting($desktop, 'popup_overlay', 'blackout', false, $overlayKeys),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_overlay_percent]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_overlay_percent', 25, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40" min="0" max="100"',
				));
				?>
			<div class="wpfRightLabel">%</div>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][popup_overlay]', array(
					'options' => $overlays,
					'value' => $this->getFilterSetting($mobile, 'popup_overlay', 'blackout', false, $overlayKeys),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_overlay_percent]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_overlay_percent', 25, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"',
				));
				?>
			<div class="wpfRightLabel">%</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3 settings-block-title">
		<?php esc_html_e('Animation settings', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-label col-xs-4 settings-block-title <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<?php esc_html_e('Desktop', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-label col-xs-4 settings-block-title <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<?php esc_html_e('Mobile', 'woo-product-filter'); ?>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Arrival side', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[styles][fdesktop][popup_arrival_side]', array(
					'options' => $arrival,
					'value' => $this->getFilterSetting($desktop, 'popup_arrival_side', 'right', false, $arrivalKeys),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[styles][fmobile][popup_arrival_side]', array(
					'options' => $arrival,
					'value' => $this->getFilterSetting($mobile, 'popup_arrival_side', 'right', false, $arrivalKeys),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Stop position', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set popup stop position in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][popup_position_top]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_position_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_position_right]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_position_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_position_bottom]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_position_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fdesktop][popup_position_left]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_position_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][popup_position_top]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_position_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_position_right]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_position_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_position_bottom]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_position_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][fmobile][popup_position_left]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_position_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Animation speed', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fdesktop][popup_animation_speed]', array(
					'value' => $this->getFilterSetting($desktop, 'popup_animation_speed', 200, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"',
				));
				?>
			<div class="wpfRightLabel">ms</div>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][fmobile][popup_animation_speed]', array(
					'value' => $this->getFilterSetting($mobile, 'popup_animation_speed', 200, true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"',
				));
				?>
			<div class="wpfRightLabel">ms</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[floating_mode]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Close popup after action', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenDesktop); ?>" data-select="settings[floating_devices]" data-not-value="mobile">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::checkboxToggle('settings[styles][fdesktop][popup_close_after]', array(
					'checked' => $this->getFilterSetting($desktop, 'popup_close_after', 0, true),
				));
				?>
		</div>
	</div>
	<div class="settings-block-values col-xs-4 <?php echo esc_attr($hiddenMobile); ?>" data-select="settings[floating_devices]" data-not-value="desktop">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::checkboxToggle('settings[styles][fmobile][popup_close_after]', array(
					'checked' => $this->getFilterSetting($mobile, 'popup_close_after', 0, true),
				));
				?>
		</div>
	</div>
</div>
