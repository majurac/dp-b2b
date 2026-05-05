<?php
/**
 * WBW Product Filter PRO - Part Edit Tab Design Blocks Pro
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

$settings     = $this->getFilterSetting($this->settings, 'settings', array());
$styles       = $this->getFilterSetting($settings, 'styles', array());
$module       = $this->getModule();
$fontsList    = $module->getAllFontsList();
$defaultFont  = $module->defaultFont;
$fontStyles   = $module->getFontStyles();
$borderStyles = $module->getBorderStyles();

$useBlockStyles = $this->getFilterSetting($settings, 'use_block_styles', 0);
$hiddenStyle    = $useBlockStyles ? '' : 'wpfHidden';
?>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use Custom Styles', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Choose custom styles for filter blocks. Any settings you leave blank will default.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/filter-block-design/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::checkboxToggle('settings[use_block_styles]', array(
					'checked' => $useBlockStyles,
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Font', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w50">
			<?php
				HtmlWpf::selectbox('settings[styles][block_font_family]', array(
					'value' => $this->getFilterSetting($styles, 'block_font_family', $defaultFont),
					'options' => $fontsList,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php
				HtmlWpf::text('settings[styles][block_font_size]', array(
					'value' => $this->getFilterSetting($styles, 'block_font_size', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				?>
			<div class="settings-value-label">px</div>
		</div>
		<div class="settings-value settings-w50">
			<?php
				HtmlWpf::selectbox('settings[styles][block_font_style]', array(
					'value' => $this->getFilterSetting($styles, 'block_font_style', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php
				HtmlWpf::colorpicker('settings[styles][block_font_color]', array(
					'value' => $this->getFilterSetting($styles, 'block_font_color', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Selected Font', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w50">
			<?php
				HtmlWpf::selectbox('settings[styles][block_font_family_selected]', array(
					'value' => $this->getFilterSetting($styles, 'block_font_family_selected', $defaultFont),
					'options' => $fontsList,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php
				HtmlWpf::text('settings[styles][block_font_size_selected]', array(
					'value' => $this->getFilterSetting($styles, 'block_font_size_selected', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				?>
			<div class="settings-value-label">px</div>
		</div>
		<div class="settings-value settings-w50">
			<?php
				HtmlWpf::selectbox('settings[styles][block_font_style_selected]', array(
					'value' => $this->getFilterSetting($styles, 'block_font_style_selected', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php
				HtmlWpf::colorpicker('settings[styles][block_font_color_selected]', array(
					'value' => $this->getFilterSetting($styles, 'block_font_color_selected', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Background', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php
				HtmlWpf::colorpicker('settings[styles][block_bg_color]', array(
					'value' => $this->getFilterSetting($styles, 'block_bg_color', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Borders', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::colorpicker('settings[styles][block_border_color]', array(
					'value' => $this->getFilterSetting($styles, 'block_border_color', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::selectbox('settings[styles][block_border_style]', array(
					'value' => $this->getFilterSetting($styles, 'block_border_style', ''),
					'options' => $borderStyles,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Color of the selected item', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php
				HtmlWpf::colorpicker('settings[styles][block_selected_item_color]', array(
					'value' => $this->getFilterSetting($styles, 'block_selected_item_color', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Borders size', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set borders width in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::text('settings[styles][block_border_top]', array(
					'value' => $this->getFilterSetting($styles, 'block_border_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][block_border_right]', array(
					'value' => $this->getFilterSetting($styles, 'block_border_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][block_border_bottom]', array(
					'value' => $this->getFilterSetting($styles, 'block_border_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][block_border_left]', array(
					'value' => $this->getFilterSetting($styles, 'block_border_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Padding', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set paddings in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::text('settings[styles][block_padding_top]', array(
					'value' => $this->getFilterSetting($styles, 'block_padding_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][block_padding_right]', array(
					'value' => $this->getFilterSetting($styles, 'block_padding_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][block_padding_bottom]', array(
					'value' => $this->getFilterSetting($styles, 'block_padding_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][block_padding_left]', array(
					'value' => $this->getFilterSetting($styles, 'block_padding_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Margin', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set margins in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::text('settings[styles][block_margin_top]', array(
					'value' => $this->getFilterSetting($styles, 'block_margin_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][block_margin_right]', array(
					'value' => $this->getFilterSetting($styles, 'block_margin_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][block_margin_bottom]', array(
					'value' => $this->getFilterSetting($styles, 'block_margin_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][block_margin_left]', array(
					'value' => $this->getFilterSetting($styles, 'block_margin_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Checkboxes', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set styles for checkboxes.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w50">
			<?php
				HtmlWpf::selectbox('settings[styles][block_checkbox_type]', array(
					'value' => $this->getFilterSetting($styles, 'block_checkbox_type', ''),
					'options' => array(
						'' => esc_attr__('Default', 'woo-product-filter'),
						'circle' => esc_attr__('Circle', 'woo-product-filter'),
						'square' => esc_attr__('Square', 'woo-product-filter'),
						'round' => esc_attr__('Square rounded corners', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
			</div>
		<div class="settings-value settings-w50" data-select="settings[styles][block_checkbox_type]" data-select-value="circle|square|round">
			<?php
				HtmlWpf::text('settings[styles][block_checkbox_size]', array(
					'value' => $this->getFilterSetting($styles, 'block_checkbox_size', '', true),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				?>
			<div class="settings-value-label">px</div>
		</div>
		<div class="settings-value settings-w100" data-select="settings[styles][block_checkbox_type]" data-select-value="circle|square|round">
			<div class="settings-value-label">
				<?php esc_html_e('border', 'woo-product-filter'); ?>
			</div>
			<?php
				HtmlWpf::colorpicker('settings[styles][block_checkbox_border]', array(
					'value' => $this->getFilterSetting($styles, 'block_checkbox_border', ''),
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value settings-w100" data-select="settings[styles][block_checkbox_type]" data-select-value="circle|square|round">
			<div class="settings-value-label woobewoo-width80">
				<?php esc_html_e('unchecked', 'woo-product-filter'); ?>
			</div>
			<?php
				HtmlWpf::colorpicker('settings[styles][block_checkbox_color]', array(
					'value' => $this->getFilterSetting($styles, 'block_checkbox_color', ''),
				));
				?>
		</div>
		<div class="clear"></div>
		<div class="settings-value settings-w100" data-select="settings[styles][block_checkbox_type]" data-select-value="circle|square|round">
			<div class="settings-value-label woobewoo-width80">
				<?php esc_html_e('checked', 'woo-product-filter'); ?>
			</div>
			<?php
				HtmlWpf::colorpicker('settings[styles][block_checkbox_checked_color]', array(
					'value' => $this->getFilterSetting($styles, 'block_checkbox_checked_color', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w100" data-select="settings[styles][block_checkbox_type]" data-select-value="circle|square|round">
			<div class="settings-value-label">
				<?php esc_html_e('checkmark', 'woo-product-filter'); ?>
			</div>
			<?php
				HtmlWpf::colorpicker('settings[styles][block_checkbox_mark_color]', array(
					'value' => $this->getFilterSetting($styles, 'block_checkbox_mark_color', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w100" data-select="settings[styles][block_checkbox_type]" data-select-value="circle|square|round">
			<div class="settings-value-label">
				<?php esc_html_e('border color', 'woo-product-filter'); ?>
			</div>
			<?php
				HtmlWpf::colorpicker('settings[styles][block_checkbox_checked_border_color]', array(
					'value' => $this->getFilterSetting($styles, 'block_checkbox_checked_border_color', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Open / Close icon categories', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php
				HtmlWpf::selectbox('settings[styles][categories_icon]', array(
					'value' => $this->getFilterSetting($styles, 'categories_icon', ''),
					'options' => array(
						'' => __('plus/minus', 'woo-product-filter'),
						'chevron' => __('chevron', 'woo-product-filter'),
						'angle-double' => __('angle double', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Size icon categories', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('You can resize the icon for collapsing / expanding categories', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-6 col-sm-8">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::text('settings[styles][categories_size_icon]', array(
					'value' => $this->getFilterSetting($styles, 'categories_size_icon', '14'),
					'attrs' => 'class="wpfSmallerInput woobewoo-flat-input woobewoo-number"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<?php
	$settingValue = ( isset( $this->settings['settings']['styles']['categories_bold_icon'] ) ? (int) $this->settings['settings']['styles']['categories_bold_icon'] : 0 );
?>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Bold icon categories', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Make category icons bold', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::checkboxToggle('settings[styles][categories_bold_icon]', array(
					'checked' => $settingValue,
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_block_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('List line height', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Use this style to adjust the line height.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-6 col-sm-8">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::text('settings[styles][block_line_height]', array(
					'value' => $this->getFilterSetting($styles, 'block_line_height', '', true, false, true),
					'attrs' => 'class="wpfSmallerInput woobewoo-flat-input woobewoo-number"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
