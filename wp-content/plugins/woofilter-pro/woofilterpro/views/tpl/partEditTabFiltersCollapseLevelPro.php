<div class="row-settings-block wpfTypeSwitchable wpfDependencyHidden" data-parent-switch="f_multi_collapsible" data-type="multi list">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3" >
		<?php esc_html_e('Collapse level', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('The level of child categories from which to start collapsing', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
			HtmlWpf::text('f_collapse_level', array(
				'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
			));
			?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="multi list buttons">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3" >
		<?php esc_html_e('Check page category', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Оn the category page automatically put a check mark for current category', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_set_page_category', array()); ?>
		</div>
		<div class="settings-value settings-w100 wpfDependencyHidden wpfTypeSwitchable wpfAddTypeControl" data-parent-switch="f_set_page_category" data-type="multi">
			<div class="settings-value-label" >
				<?php esc_html_e('Check parents', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_set_parent_page_category', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="multi list">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3" >
		<?php esc_html_e('Check product category', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('On the product page automatically put the check marks for categories of the current product', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_set_product_category', array()); ?>
		</div>
	</div>
</div>

<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e( 'Hide current page category', 'woo-product-filter' ); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr( __( 'The category page automatically hides the current category', 'woo-product-filter' ) ); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle( 'f_hide_page_category', array() ); ?>
		</div>
	</div>
</div>
