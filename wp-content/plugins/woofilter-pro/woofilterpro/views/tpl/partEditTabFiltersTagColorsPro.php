<?php 
	$colorsLimit = ( 1 == FrameWpf::_()->getModule( 'options' )->get( 'unlimited_color_filter' ) ? 100000 : 300 );
?>
<div class="row-settings-block wpfColorsTypeBlock wpfTypeSwitchable" data-type="colors">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Colors Settings', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Colors Settings', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="sub-block-values wpfColorsTypeOptions col-xs-8 col-sm-9">
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Icon Type', 'woo-product-filter'); ?>
			</div>
			<?php 
				HtmlWpf::selectbox('f_colors_type', array(
					'options' => array(
						'circle' => esc_attr__('Circle', 'woo-product-filter'),
						'square' => esc_attr__('Square', 'woo-product-filter'),
						'round' => esc_attr__('Square rounded corners', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Single selection', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_colors_singleselect', array()); ?>
		</div>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Layout', 'woo-product-filter'); ?>
			</div>
			<?php 
				HtmlWpf::selectbox('f_colors_layout', array(
					'options' => array('hor' => esc_attr__('Horizontal', 'woo-product-filter'), 'ver' => esc_attr__('Vertical', 'woo-product-filter')),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value" data-select="f_colors_layout" data-select-value="ver">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Show labels', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_colors_labels', array('checked' => 1)); ?>
		</div>
		<div class="settings-value" data-select="f_colors_layout" data-select-value="ver">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Count columns', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_colors_ver_columns', array('value' => 2, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"')); ?>
		</div>
		<div class="settings-value" data-select="f_colors_layout" data-select-value="hor">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Rotate if checked', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_colors_rotate_checked', array()); ?>
		</div>
		<div class="settings-value" data-select="f_colors_layout" data-select-value="hor">
			<div class="settings-value-label woobewoo-width150">
				<?php esc_html_e('Show count on icons', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('"Show count" should be activated', 'woo-product-filter'); ?>"></i>
			</div>
			<?php HtmlWpf::checkboxToggle('f_colors_label_count', array()); ?>
		</div>
		<div class="settings-value" data-select="f_colors_layout" data-select-value="hor">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Colors in row', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_colors_hor_row', array('value' => 0, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"')); ?>
		</div>
		<div class="settings-value" data-select="f_colors_layout" data-select-value="hor">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Icons spacing', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_colors_hor_spacing', array('value' => 2, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"')); ?> px
		</div>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Icon size', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_colors_size', array('value' => 16, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"')); ?> px
		</div>
		<div class="settings-value">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Show border', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_colors_border', array()); ?>
		</div>
		<div class="settings-value" data-parent="f_colors_border">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Border width', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_colors_border_width', array('value' => 1, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"')); ?> px
		</div>
		<div class="settings-value" data-parent="f_colors_border">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Border color', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::colorpicker('f_colors_border_color', array('value' => '#000000')); ?>
		</div>
		<div class="settings-value wpfAttributesColors">
			<div class="settings-value-label woobewoo-width120">
				<?php esc_html_e('Icon colors', 'woo-product-filter'); ?>
			</div>
			<ul class="wpfTermsOptions" data-max-terms="<?php echo esc_attr($colorsLimit); ?>" data-error="<?php esc_attr_e('The color filter is not available due to the large number of elements. You can remove this limit in the plugin settings.', 'woo-product-filter'); ?>">
				<li></li>
			</ul>
		</div>
		<div class="clear"></div>
		<div class="settings-block-values wpfTermsOptionsForm" data-no-preview="1">
			<div class="settings-value">
				<div class="settings-value-label woobewoo-width100">
					<?php esc_html_e('Background', 'woo-product-filter'); ?>
				</div>
			</div>
			<div class="settings-value wpfTermsColorBg" data-field-temp="color_bg" data-field-type="color-picker">
				<?php HtmlWpf::colorpicker('', array('attrs' => 'data-type="temp_color_bg"')); ?>
			</div>
			<div class="clear"></div>
			<div class="settings-value">
				<div class="settings-value-label woobewoo-width100">
					<?php esc_html_e('Background bicolor', 'woo-product-filter'); ?>
				</div>
			</div>
			<div class="settings-value wpfTermsColorBgBicolor" data-field-temp="bicolor_bg" data-field-type="color-picker">
				<?php HtmlWpf::colorpicker('', array()); ?>
			</div>
			<div class="clear"></div>
			<div class="settings-value">
				<div class="settings-value-label woobewoo-width100">
					<?php esc_html_e('Background icon', 'woo-product-filter'); ?>
				</div>
			</div>
			<div class="settings-value wpfTermsSelectIcon">
				<?php 
				HtmlWpf::buttonA(array(
					'value' => esc_attr__('Select icon', 'woo-product-filter'),
					'attrs' => 'data-type="image"',
				));
				?>
				<i class="fa fa-times wpfTermsRemoveIcon" title="<?php echo esc_attr__('Remove icon', 'woo-product-filter'); ?>"></i>
			</div>
			<div class="clear"></div>
			<div class="settings-value">
				<div class="settings-value-label woobewoo-width100">
					<?php esc_html_e('Label', 'woo-product-filter'); ?>
				</div>
			</div>
			<div class="settings-value wpfTermsColorLabel" data-field-temp="color_label" data-field-type="color-picker">
				<?php HtmlWpf::colorpicker('', array()); ?>
			</div>
			<div class="settings-value wpfTermsTextLabel" data-field-temp="text_label" data-field-type="text">
				<?php HtmlWpf::text('', array('value' => '', 'attrs' => 'class="woobewoo-flat-input woobewoo-width100"')); ?>
			</div>
		</div>
	</div>
</div>

