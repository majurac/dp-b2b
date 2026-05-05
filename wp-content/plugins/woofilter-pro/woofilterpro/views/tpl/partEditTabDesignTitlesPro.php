<?php
$settings = $this->getFilterSetting($this->settings, 'settings', array());
$styles = $this->getFilterSetting($settings, 'styles', array());
$module = $this->getModule();
$fontsList = $module->getAllFontsList();
$defaultFont = $module->defaultFont;
$fontStyles = $module->getFontStyles();
$borderStyles = $module->getBorderStyles();

$useTitleStyles = $this->getFilterSetting($settings, 'use_title_styles', 0);
$hiddenStyle = $useTitleStyles ? '' : 'wpfHidden';
?>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use Custom Styles', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Choose custom styles for filter titles. Any settings you leave blank will default.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/filter-title-design/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::checkboxToggle('settings[use_title_styles]', array(
					'checked' => $useTitleStyles,
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_title_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Font', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][title_font_family]', array(
					'value' => $this->getFilterSetting($styles, 'title_font_family', $defaultFont),
					'options' => $fontsList,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::text('settings[styles][title_font_size]', array(
					'value' => $this->getFilterSetting($styles, 'title_font_size', ''),
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
				));
				?>
			<div class="settings-value-label">px</div>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('settings[styles][title_font_style]', array(
					'value' => $this->getFilterSetting($styles, 'title_font_style', ''),
					'options' => $fontStyles,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::colorpicker('settings[styles][title_font_color]', array(
					'value' => $this->getFilterSetting($styles, 'title_font_color', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_title_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Background', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::colorpicker('settings[styles][title_bg_color]', array(
					'value' => $this->getFilterSetting($styles, 'title_bg_color', ''),
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_title_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Borders', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php 
				HtmlWpf::colorpicker('settings[styles][title_border_color]', array(
					'value' => $this->getFilterSetting($styles, 'title_border_color', ''),
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('settings[styles][title_border_style]', array(
					'value' => $this->getFilterSetting($styles, 'title_border_style', ''),
					'options' => $borderStyles,
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_title_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Borders size', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set borders width in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][title_border_top]', array(
					'value' => $this->getFilterSetting($styles, 'title_border_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][title_border_right]', array(
					'value' => $this->getFilterSetting($styles, 'title_border_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][title_border_bottom]', array(
					'value' => $this->getFilterSetting($styles, 'title_border_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][title_border_left]', array(
					'value' => $this->getFilterSetting($styles, 'title_border_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_title_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Padding', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set paddings in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('settings[styles][title_padding_top]', array(
					'value' => $this->getFilterSetting($styles, 'title_padding_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][title_padding_right]', array(
					'value' => $this->getFilterSetting($styles, 'title_padding_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][title_padding_bottom]', array(
					'value' => $this->getFilterSetting($styles, 'title_padding_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][title_padding_left]', array(
					'value' => $this->getFilterSetting($styles, 'title_padding_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_title_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Margin', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('Set margins in this order: top, right, bottom, left.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::text('settings[styles][title_margin_top]', array(
					'value' => $this->getFilterSetting($styles, 'title_margin_top', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][title_margin_right]', array(
					'value' => $this->getFilterSetting($styles, 'title_margin_right', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][title_margin_bottom]', array(
					'value' => $this->getFilterSetting($styles, 'title_margin_bottom', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				HtmlWpf::text('settings[styles][title_margin_left]', array(
					'value' => $this->getFilterSetting($styles, 'title_margin_left', '', true, false, true),
					'attrs' => 'class="wpfMiniInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_title_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Height', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_html(__('You can set title height by that increase clickable title area for a open / close functionality.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-6 col-sm-8">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::text('settings[styles][title_min_height]', array(
					'value' => $this->getFilterSetting($styles, 'title_min_height', ''),
					'attrs' => 'class="wpfSmallerInput"',
				));
				?>
			<div class="wpfRightLabel">px</div>
		</div>
	</div>
</div>
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_title_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Open / Close icon', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php
				HtmlWpf::selectbox('settings[styles][title_icons]', array(
					'value' => $this->getFilterSetting($styles, 'title_icons', ''),
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
<div class="row row-settings-block <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[use_title_styles]">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Open / Close icon position', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php
				HtmlWpf::selectbox('settings[styles][title_icons_position]', array(
					'value' => $this->getFilterSetting($styles, 'title_icons_position', ''),
					'options' => array(
						'after-right' => esc_attr__('After / Right', 'woo-product-filter'),
						'before-left' => esc_attr__('Before / Left', 'woo-product-filter'),
						'before'      => esc_attr__('Before', 'woo-product-filter'),
						'after'       => esc_attr__('After', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
