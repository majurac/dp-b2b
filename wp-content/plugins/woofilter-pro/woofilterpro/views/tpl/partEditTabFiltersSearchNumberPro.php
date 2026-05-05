<?php
/**
 * WBW Product Filter PRO - partEditTabFiltersSearchNumberPro
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

$attrDisplay  = $this->settings['attrDisplay'];
$tamplatePath = FrameWpf::_()->getModule('woofilters')->getView()->getPath('woofiltersEditTabCommonTitle');

// fallback for depricated optionality
if ( $tamplatePath ) {
	FrameWpf::_()->getModule('woofilters')->getView()->display('woofiltersEditTabCommonTitle');
} else {
	ViewWpf::display('woofiltersEditTabCommonTitleDepricated');
}
?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Select attribute', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
			$attrDisplay['custom_meta_field_check'] = 'Custom meta field';
			HtmlWpf::selectbox( 'f_list', array(
				'options' => $attrDisplay,
				'attrs'   => 'class="woobewoo-flat-input"',
			) );
			?>
		</div>
		<?php
			include 'partEditTabFiltersCustomMetaFieldPro.php';
		?>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use title as placeholder', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="
		<?php
			echo esc_attr(__('Set filter title as search input placeholder.', 'woo-product-filter'));
		?>
		"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_title_as_placeholder', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpf-multi-attributes">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use additional attributes', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="
		<?php
			echo esc_attr(__('Select additional attributes for filtration.', 'woo-product-filter'));
		?>
		"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_multi_attributes', array()); ?>
		</div>
		<div class="settings-value settings-w100 wpf-multi-select" data-parent="f_multi_attributes">
			<?php
			list( $attrDisplay ) = FrameWpf::_()->getModule('woofilters')->getAttributesDisplay(false);
			unset($attrDisplay[0]);
			HtmlWpf::selectlist( 'f_additional_attributes_list', array(
				'options' => $attrDisplay,
			) );
			?>
		</div>
	</div>
</div>

<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Search logic', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::selectbox('f_search_logic', array(
					'options' => array(
						'min'  => esc_attr__( 'min', 'woo-product-filter' ),
						'max'  => esc_attr__( 'max', 'woo-product-filter' ),
						'more' => esc_attr__( 'more', 'woo-product-filter' ),
						'less' => esc_attr__( 'less', 'woo-product-filter' ),
						'equ'  => esc_attr__( 'equals', 'woo-product-filter' ),
					),
					'value' => 'min',
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Label before', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Set label before input field.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::text('f_label_before', array('attrs' => 'class="woobewoo-flat-input"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Label after', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Set label after input field.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::text('f_label_after', array('attrs' => 'class="woobewoo-flat-input"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Disable auto filtering when focus out', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('You can disable filtering products if focus out search input', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9 settings-value-elementor-row-revert">
		<div class="settings-value">
			<?php HtmlWpf::checkboxToggle('f_disable_autofiltering', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Hide without products', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Hide this block if there are no products with this attribute.', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9 settings-value-elementor-row-revert">
		<div class="settings-value">
			<?php HtmlWpf::checkboxToggle('f_conrol_products', array()); ?>
		</div>
	</div>
</div>
