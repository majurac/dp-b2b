<?php
/**
 * WBW Product Filter PRO - partEditTabFiltersAttributeSliderPro
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

$skins = array(
	'default' => esc_attr__('Default', 'woo-product-filter'),
	'flat'    => esc_attr__('Flat skin', 'woo-product-filter'),
	'big'     => esc_attr__('Big skin', 'woo-product-filter'),
	'modern'  => esc_attr__('Modern skin', 'woo-product-filter'),
	'sharp'   => esc_attr__('Sharp skin', 'woo-product-filter'),
	'round'   => esc_attr__('Round skin', 'woo-product-filter'),
	'square'  => esc_attr__('Square skin', 'woo-product-filter'),
	'compact' => esc_attr__('Compact skin', 'woo-product-filter'),
	'circle'  => esc_attr__('Circle skin', 'woo-product-filter'),
	'rail'    => esc_attr__('Rail skin', 'woo-product-filter'),
	'trolley' => esc_attr__('Trolley skin', 'woo-product-filter'),
);
?>
<div class="row-settings-block wpfSliderTypeBlock wpfTypeSwitchable" data-type="slider">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Slider skin', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Select the attribute slider skin.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/attribute-filter-settings/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="sub-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php
			HtmlWpf::selectbox('f_skin_type', array(
				'options' => $skins,
				'attrs' => 'class="woobewoo-flat-input"',
			));
			?>
		</div>
		<div class="settings-value">
			<?php DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersPriceSkin'); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable"  data-type="slider">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show attribute input fields', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_show_inputs_slider_attr', array('checked' => 0)); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable"  data-type="slider">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Disable number formatting', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_disable_number_formatting', array('checked' => 0)); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable"  data-type="slider">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use text tooltip instead of input fields', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_attribute_tooltip_show_as', array('checked' => 1)); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="slider">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Force numeric values', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Transform attribute values to numeric', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_force_numeric', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="slider">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show all slider attributes', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Even if values are selected don\'t decrease slider start and end values', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_show_all_slider_attributes', array()); ?>
		</div>
	</div>
</div>
